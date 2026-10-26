<?php

namespace App\Scanning\Lifecycle;

use Carbon\CarbonImmutable;

/**
 * Checks detected runtimes and frameworks against endoflife.date support
 * windows (e.g. "Laravel 9 is out of support", "Node 16 is EOL").
 */
class EndOfLifeScanner
{
    public function __construct(
        protected LifecycleComponentDetector $detectedLifecycleComponents = new LifecycleComponentDetector,
        protected EndOfLifeClient $endOfLifeClient = new EndOfLifeClient,
    ) {}

    /**
     * @return array{components: array<int, array<string, mixed>>, findings: array<int, array<string, mixed>>, summary: array{total:int, components:int, CRITICAL:int, HIGH:int, MEDIUM:int}}
     */
    public function scan(string $repositoryPath): array
    {
        $components = collect($this->detectedLifecycleComponents->forRepository(rtrim($repositoryPath, '/')))
            ->map(fn (array $component): array => $this->evaluateComponent($component))
            ->values()
            ->all();

        $findings = collect($components)
            ->pluck('finding')
            ->filter(fn (mixed $finding): bool => is_array($finding))
            ->values()
            ->all();

        $components = array_map(function (array $component): array {
            unset($component['finding']);

            return $component;
        }, $components);

        $summary = ['total' => count($findings), 'components' => count($components), 'CRITICAL' => 0, 'HIGH' => 0, 'MEDIUM' => 0];
        foreach ($findings as $finding) {
            if (isset($summary[$finding['severity']])) {
                $summary[$finding['severity']]++;
            }
        }

        return ['components' => $components, 'findings' => $findings, 'summary' => $summary];
    }

    /**
     * @param  array<string, string>  $component
     * @return array<string, mixed>
     */
    protected function evaluateComponent(array $component): array
    {
        $product = $this->endOfLifeClient->product($component['product']);

        if (! is_array($product)) {
            return [
                ...$component,
                'status' => 'unavailable',
                'status_label' => 'Lookup unavailable',
                'finding' => null,
            ];
        }

        $release = collect($product['releases'] ?? [])
            ->first(fn (array $candidate): bool => $this->matchesCycle($component['detected_cycle'], (string) ($candidate['name'] ?? '')));

        if (! is_array($release)) {
            return [
                ...$component,
                'product_link' => data_get($product, 'links.html'),
                'status' => 'unknown',
                'status_label' => 'No matching support cycle',
                'finding' => null,
            ];
        }

        $activeSupportEndsAt = $this->parseDate($release['eoasFrom'] ?? null);
        $securitySupportEndsAt = $this->parseDate($release['eolFrom'] ?? null);
        $recommendedRelease = $this->recommendedRelease((array) ($product['releases'] ?? []));
        $recommendedCycle = is_array($recommendedRelease)
            ? (string) ($recommendedRelease['label'] ?? $recommendedRelease['name'] ?? '')
            : null;
        $suggestedVersion = $this->firstFilledString(
            is_array($recommendedRelease) ? data_get($recommendedRelease, 'latest.name') : null,
            data_get($release, 'latest.name'),
        );
        $suggestedVersionDate = $this->firstFilledString(
            is_array($recommendedRelease) ? data_get($recommendedRelease, 'latest.date') : null,
            data_get($release, 'latest.date'),
        );
        $warnDays = max(0, (int) config('mysafecoding.endoflife.warn_days', 90));
        $daysUntilEol = $securitySupportEndsAt !== null
            ? CarbonImmutable::now()->diffInDays($securitySupportEndsAt, false)
            : null;

        [$status, $statusLabel] = match (true) {
            $securitySupportEndsAt !== null && $securitySupportEndsAt->isPast() => ['end-of-life', 'Out of support'],
            $activeSupportEndsAt !== null && $activeSupportEndsAt->isPast() => ['security-support', 'Security support only'],
            $securitySupportEndsAt !== null && CarbonImmutable::now()->diffInDays($securitySupportEndsAt, false) <= $warnDays => ['approaching-eol', 'Support ending soon'],
            default => ['supported', 'Supported'],
        };

        return [
            ...$component,
            'product_link' => data_get($product, 'links.html'),
            'status' => $status,
            'status_label' => $statusLabel,
            'release_label' => $release['label'] ?? $release['name'] ?? $component['detected_cycle'],
            'release_date' => $release['releaseDate'] ?? null,
            'active_support_ends_at' => $release['eoasFrom'] ?? null,
            'security_support_ends_at' => $release['eolFrom'] ?? null,
            'latest_release' => $suggestedVersion,
            'latest_release_date' => $suggestedVersionDate,
            'recommended_cycle' => $recommendedCycle,
            'suggested_version' => $suggestedVersion,
            'suggested_version_date' => $suggestedVersionDate,
            'is_maintained' => (bool) ($release['isMaintained'] ?? false),
            'is_lts' => (bool) ($release['isLts'] ?? false),
            'days_until_security_eol' => $daysUntilEol,
            'finding' => $this->findingFor(
                component: $component,
                release: $release,
                recommendedRelease: $recommendedRelease,
                product: $product,
                status: $status,
                daysUntilEol: $daysUntilEol,
            ),
        ];
    }

    /**
     * @param  array<string, string>  $component
     * @param  array<string, mixed>  $release
     * @param  array<string, mixed>|null  $recommendedRelease
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>|null
     */
    protected function findingFor(array $component, array $release, ?array $recommendedRelease, array $product, string $status, ?int $daysUntilEol): ?array
    {
        if (! in_array($status, ['end-of-life', 'security-support', 'approaching-eol'], true)) {
            return null;
        }

        $label = (string) ($product['label'] ?? $component['label']);
        $releaseLabel = (string) ($release['label'] ?? $release['name'] ?? $component['detected_cycle']);
        $severity = match ($status) {
            'end-of-life' => 'critical',
            'security-support' => $daysUntilEol !== null && $daysUntilEol <= 90 ? 'high' : 'medium',
            default => $daysUntilEol !== null && $daysUntilEol <= 30 ? 'high' : 'medium',
        };
        $title = match ($status) {
            'end-of-life' => $label.' '.$releaseLabel.' is out of support',
            'security-support' => $label.' '.$releaseLabel.' is in security-support-only maintenance',
            default => $label.' '.$releaseLabel.' reaches end of support soon',
        };
        $why = match ($status) {
            'end-of-life' => 'Unsupported software no longer receives security fixes, so newly disclosed vulnerabilities remain permanently exposed.',
            'security-support' => 'Active feature support has ended. Remaining time on the release line is limited, so upgrade planning should start now.',
            default => 'This release line is nearing the end of support and should be scheduled for upgrade before it becomes unsupported.',
        };
        $recommendedCycle = is_array($recommendedRelease)
            ? (string) ($recommendedRelease['label'] ?? $recommendedRelease['name'] ?? '')
            : null;
        $suggestedVersion = $this->firstFilledString(
            is_array($recommendedRelease) ? data_get($recommendedRelease, 'latest.name') : null,
            data_get($release, 'latest.name'),
        );
        $fix = $this->upgradeAdvice(
            label: $label,
            detectedCycle: $component['detected_cycle'],
            recommendedCycle: $recommendedCycle,
            suggestedVersion: $suggestedVersion,
        );

        return [
            'rule_id' => 'endoflife.'.$component['product'].'.'.$status,
            'title' => $title,
            'severity' => strtoupper($severity),
            'category' => 'lifecycle',
            'description' => $why,
            'file_path' => $component['source'],
            'line_start' => null,
            'code_snippet' => $label.' '.$component['detected_version'],
            'fix_guidance' => $fix,
            'link' => data_get($product, 'links.html'),
            'fingerprint' => sha1('endoflife|'.$component['product'].'|'.$component['detected_cycle'].'|'.$component['source']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $releases
     * @return array<string, mixed>|null
     */
    protected function recommendedRelease(array $releases): ?array
    {
        $collection = collect($releases);

        $activelyMaintained = $collection->first(fn (array $candidate): bool => $this->releaseIsActivelyMaintained($candidate));

        if (is_array($activelyMaintained)) {
            return $activelyMaintained;
        }

        $supported = $collection->first(fn (array $candidate): bool => $this->releaseHasSecuritySupport($candidate));

        if (is_array($supported)) {
            return $supported;
        }

        $first = $collection->first();

        return is_array($first) ? $first : null;
    }

    /**
     * @param  array<string, mixed>  $release
     */
    protected function releaseIsActivelyMaintained(array $release): bool
    {
        if ((bool) ($release['isMaintained'] ?? false)) {
            return true;
        }

        $activeSupportEndsAt = $this->parseDate($release['eoasFrom'] ?? null);

        return $activeSupportEndsAt === null || ! $activeSupportEndsAt->isPast();
    }

    /**
     * @param  array<string, mixed>  $release
     */
    protected function releaseHasSecuritySupport(array $release): bool
    {
        $securitySupportEndsAt = $this->parseDate($release['eolFrom'] ?? null);

        return $securitySupportEndsAt === null || ! $securitySupportEndsAt->isPast();
    }

    protected function upgradeAdvice(string $label, string $detectedCycle, ?string $recommendedCycle, ?string $suggestedVersion): string
    {
        if ($suggestedVersion === null) {
            return 'Upgrade to a currently maintained '.$label.' release line.';
        }

        if ($recommendedCycle !== null && trim($recommendedCycle) !== '' && trim($recommendedCycle) !== trim($detectedCycle)) {
            return 'Upgrade to the latest stable '.$label.' release line: '.$recommendedCycle.' ('.$suggestedVersion.').';
        }

        return 'Upgrade to the latest stable '.$label.' version for the '.$detectedCycle.' release line: '.$suggestedVersion.'.';
    }

    protected function firstFilledString(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    protected function matchesCycle(string $detectedCycle, string $releaseCycle): bool
    {
        return trim($detectedCycle) !== '' && trim($detectedCycle) === trim($releaseCycle);
    }

    protected function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value);
    }
}
