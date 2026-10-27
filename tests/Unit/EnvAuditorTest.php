<?php

namespace Tests\Unit;

use App\Services\EnvAuditor;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

class EnvAuditorTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir().'/checkmycode-env-'.uniqid();
        mkdir($this->path, 0755, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->path);
        parent::tearDown();
    }

    private function audit(string $env, string $type): array
    {
        file_put_contents($this->path.'/.env', $env);

        $result = EnvAuditor::audit($this->path, $type);
        $this->assertSame(count($result['findings']), $result['summary']['total']);

        return array_column($result['findings'], null, 'key');
    }

    public function test_node_project_checks(): void
    {
        $findings = $this->audit(implode("\n", [
            'NODE_ENV=development',
            'NEXT_PUBLIC_SUPABASE_SERVICE_ROLE_KEY=eyJhbGciOi',
            'NODE_TLS_REJECT_UNAUTHORIZED=0',
            'JWT_SECRET=changeme',
            'DATABASE_URL=postgres://app:postgres@db:5432/app',
            'CORS_ORIGIN=*',
            'NEXTAUTH_URL=http://example.com',
            'export SESSION_SECRET=short',
        ]), 'nextjs');

        $this->assertSame('MEDIUM', $findings['NODE_ENV']['severity']);
        $this->assertSame('HIGH', $findings['NEXT_PUBLIC_SUPABASE_SERVICE_ROLE_KEY']['severity']);
        $this->assertSame('CRITICAL', $findings['NODE_TLS_REJECT_UNAUTHORIZED']['severity']);
        $this->assertSame('HIGH', $findings['JWT_SECRET']['severity']);
        $this->assertSame('HIGH', $findings['DATABASE_URL']['severity']);
        $this->assertSame('MEDIUM', $findings['CORS_ORIGIN']['severity']);
        $this->assertSame('MEDIUM', $findings['NEXTAUTH_URL']['severity']);
        $this->assertArrayHasKey('SESSION_SECRET', $findings);

        foreach (['NEXT_PUBLIC_SUPABASE_SERVICE_ROLE_KEY', 'JWT_SECRET', 'DATABASE_URL'] as $key) {
            $this->assertSame('(hidden)', $findings[$key]['current'], "{$key} value must not be copied into results");
        }
    }

    public function test_django_and_flask_checks(): void
    {
        $findings = $this->audit("DEBUG=True\nSECRET_KEY=django-insecure-abc123\nALLOWED_HOSTS=*\nFLASK_ENV=development\n", 'django');

        $this->assertSame('CRITICAL', $findings['DEBUG']['severity']);
        $this->assertSame('HIGH', $findings['SECRET_KEY']['severity']);
        $this->assertSame('HIGH', $findings['ALLOWED_HOSTS']['severity']);
        $this->assertSame('MEDIUM', $findings['FLASK_ENV']['severity']);
    }

    public function test_other_stacks_development_modes(): void
    {
        $findings = $this->audit("RAILS_ENV=development\nGIN_MODE=debug\nASPNETCORE_ENVIRONMENT=Development\nSPRING_PROFILES_ACTIVE=dev,h2\nMIX_ENV=dev\nRUST_LOG=debug\n", 'go');

        foreach (['RAILS_ENV', 'GIN_MODE', 'ASPNETCORE_ENVIRONMENT', 'SPRING_PROFILES_ACTIVE', 'MIX_ENV', 'RUST_LOG'] as $key) {
            $this->assertArrayHasKey($key, $findings);
        }
    }

    public function test_production_settings_pass(): void
    {
        $findings = $this->audit(implode("\n", [
            'NODE_ENV=production',
            'NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY=pk_live_123',
            'JWT_SECRET='.str_repeat('a1', 32),
            'DATABASE_URL=postgres://app:Xk29fjQp1Lz@db:5432/app',
            'NEXTAUTH_URL=http://localhost:3000',
            'LOG_LEVEL=info',
        ]), 'nextjs');

        $this->assertSame([], $findings);
    }

    public function test_laravel_keeps_its_checks_without_duplicates(): void
    {
        file_put_contents($this->path.'/.env', "APP_DEBUG=true\nAPP_KEY=base64:".str_repeat('x', 40)."\nVITE_PUSHER_SECRET=abc\n");
        $keys = array_column(EnvAuditor::audit($this->path, 'laravel')['findings'], 'key');

        $this->assertSame(1, count(array_keys($keys, 'APP_DEBUG')));
        $this->assertContains('VITE_PUSHER_SECRET', $keys);
    }
}
