<?php

namespace Tests\Unit;

use App\Scanning\ScanEngine;
use ReflectionClass;
use Tests\TestCase;

class DependencyParentsTest extends TestCase
{
    public function test_vulnerable_packages_are_traced_to_the_direct_dependencies_that_pull_them_in(): void
    {
        $node = fn (string $name, string $version, bool $direct, bool $dev, array $deps = []) => compact('name', 'version', 'direct', 'dev') + ['dependsOn' => $deps];
        $graph = [
            'vite-plus@0.3.0' => $node('vite-plus', '0.3.0', true, true, ['vitest@4.1.11']),
            'vitest@4.1.11' => $node('vitest', '4.1.11', false, true, ['@vitest/mocker@4.1.11']),
            '@vitest/mocker@4.1.11' => $node('@vitest/mocker', '4.1.11', false, true),
            'laravel-vite-plugin@2.0.0' => $node('laravel-vite-plugin', '2.0.0', true, true, ['vitest@4.1.11']),
            'axios@1.13.6' => $node('axios', '1.13.6', true, false),
        ];
        $vuln = fn (string $id, string $name, string $version, array $extra = []) => ['VulnerabilityID' => $id, 'PkgName' => $name, 'InstalledVersion' => $version, 'Severity' => 'HIGH'] + $extra;
        $results = ['summary' => [], 'trivy' => ['Results' => [[
            'Target' => 'package-lock.json',
            'Vulnerabilities' => [
                $vuln('CVE-1', '@vitest/mocker', '4.1.11', ['PkgID' => '@vitest/mocker@4.1.11']),
                $vuln('CVE-2', 'vite-plus', '0.3.0', ['PkgID' => 'vite-plus@0.3.0']),
                $vuln('CVE-3', 'axios', '1.13.6'), // OSV finding: no PkgID, matched by name@version
                $vuln('CVE-4', 'left-pad', '1.0.0'), // not in the graph
            ],
        ]]]];

        $engine = new ScanEngine;
        $reflection = new ReflectionClass($engine);
        $reflection->getProperty('dependencyGraphs')->setValue($engine, ['package-lock.json' => $graph]);
        $results = $reflection->getMethod('annotateDependencyParents')->invoke($engine, $results);
        [$mocker, $vitePlus, $axios, $leftPad] = $results['trivy']['Results'][0]['Vulnerabilities'];

        $this->assertSame(['direct' => false, 'via' => ['laravel-vite-plugin 2.0.0', 'vite-plus 0.3.0']], $mocker['IntroducedBy']);
        $this->assertTrue($mocker['DevDependency']);
        $this->assertSame(['direct' => true, 'via' => []], $vitePlus['IntroducedBy']);
        $this->assertSame(['direct' => true, 'via' => []], $axios['IntroducedBy']);
        $this->assertArrayNotHasKey('DevDependency', $axios);
        $this->assertArrayNotHasKey('IntroducedBy', $leftPad);

        // Only axios and left-pad are graded; the two dev findings are not.
        $this->assertSame(2, $results['summary']['trivy_total']);
        $this->assertSame(2, $results['summary']['dev_dependencies_total']);
    }
}
