<?php

namespace Tests\Unit;

use App\Scanning\UpgradePath;
use PHPUnit\Framework\TestCase;

class UpgradePathTest extends TestCase
{
    public function test_classifies_the_smallest_fixing_upgrade(): void
    {
        $cases = [
            ['4.1.11', '4.1.12', 'patch', '4.1.12'],
            ['2.1.1', '2.1.2', 'patch', '2.1.2'],
            ['4.17.1', '4.19.2, 5.0.0-beta.3', 'minor', '4.19.2'],
            ['4.17.1', '5.0.0-beta.3, 4.20.0', 'minor', '4.20.0'],
            ['8.5.1', '9.0.0', 'major', '9.0.0'],
            ['0.3.0', '1.1.0', 'major', '1.1.0'],
            ['0.3.0', '0.4.2', 'major', '0.4.2'], // below 1.0, a minor bump may break the API
            ['0.3.0', '0.3.4', 'patch', '0.3.4'],
            ['v8.0.6', 'v8.0.9', 'patch', 'v8.0.9'],
            ['1.0.0-rc1', '1.0.0', 'patch', '1.0.0'],
            ['8.0.6', '', 'none', null],
        ];

        foreach ($cases as [$installed, $fixed, $type, $target]) {
            $this->assertSame(['type' => $type, 'target' => $target], UpgradePath::classify($installed, $fixed), "{$installed} -> {$fixed}");
        }
    }
}
