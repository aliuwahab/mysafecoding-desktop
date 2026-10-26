<?php

namespace App\Services\Scanners;

use App\Services\Scanners\Contracts\RuleInterface;
use App\Services\Scanners\Rules;

class LaravelRuleEngine
{
    /** @var RuleInterface[] */
    protected array $rules = [];

    public function __construct()
    {
        $this->registerRules();
    }

    protected function registerRules(): void
    {
        $ruleClasses = [
            Rules\DebugModeRule::class,
            Rules\EmptyGuardedRule::class,
            Rules\MissingFillableRule::class,
            Rules\CsrfRouteExceptionRule::class,
            Rules\BladeRawOutputRule::class,
            Rules\WhereRawInjectionRule::class,
            Rules\OrderByInjectionRule::class,
            Rules\MissingAuthorizationRule::class,
            Rules\MissingThrottleRule::class,
            Rules\OpenRedirectRule::class,
            Rules\MissingMimeValidationRule::class,
            Rules\HardcodedSecretRule::class,
            Rules\InsecureCookieRule::class,
            Rules\WildcardCorsRule::class,
            Rules\WeakHashRule::class,
            Rules\UnserializeInputRule::class,
            Rules\EvalDynamicRule::class,
            Rules\CommandInjectionRule::class,
            Rules\EnvExposedRule::class,
        ];

        foreach ($ruleClasses as $class) {
            $this->rules[] = new $class;
        }
    }

    /**
     * @return array{findings: array, summary: array{total: int, CRITICAL: int, HIGH: int, MEDIUM: int}}
     */
    public function analyze(string $projectPath): array
    {
        $findings = [];

        foreach ($this->rules as $rule) {
            $results = $rule->check($projectPath);
            array_push($findings, ...$results);
        }

        $summary = ['total' => count($findings), 'CRITICAL' => 0, 'HIGH' => 0, 'MEDIUM' => 0];
        foreach ($findings as $finding) {
            $severity = $finding['severity'] ?? '';
            if (isset($summary[$severity])) {
                $summary[$severity]++;
            }
        }

        return ['findings' => $findings, 'summary' => $summary];
    }
}
