<?php

namespace Tests\Unit;

use App\Services\Scanners\LaravelRuleEngine;
use App\Services\Scanners\Rules\BladeRawOutputRule;
use App\Services\Scanners\Rules\CommandInjectionRule;
use App\Services\Scanners\Rules\CsrfRouteExceptionRule;
use App\Services\Scanners\Rules\DebugModeRule;
use App\Services\Scanners\Rules\EmptyGuardedRule;
use App\Services\Scanners\Rules\EnvExposedRule;
use App\Services\Scanners\Rules\EvalDynamicRule;
use App\Services\Scanners\Rules\HardcodedSecretRule;
use App\Services\Scanners\Rules\InsecureCookieRule;
use App\Services\Scanners\Rules\MissingAuthorizationRule;
use App\Services\Scanners\Rules\MissingFillableRule;
use App\Services\Scanners\Rules\MissingMimeValidationRule;
use App\Services\Scanners\Rules\MissingThrottleRule;
use App\Services\Scanners\Rules\OpenRedirectRule;
use App\Services\Scanners\Rules\OrderByInjectionRule;
use App\Services\Scanners\Rules\OutdatedVersionRule;
use App\Services\Scanners\Rules\UnserializeInputRule;
use App\Services\Scanners\Rules\WeakHashRule;
use App\Services\Scanners\Rules\WhereRawInjectionRule;
use App\Services\Scanners\Rules\WildcardCorsRule;
use PHPUnit\Framework\TestCase;

class LaravelRuleEngineTest extends TestCase
{
    protected string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fixturePath = sys_get_temp_dir().'/laraguard-test-'.uniqid();
        mkdir($this->fixturePath, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixturePath);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) return;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    private function createFile(string $relativePath, string $content): void
    {
        $fullPath = $this->fixturePath.'/'.$relativePath;
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($fullPath, $content);
    }

    // ─── 1. DebugModeRule ───

    public function test_debug_mode_detects_true(): void
    {
        $this->createFile('.env', "APP_NAME=Test\nAPP_DEBUG=true\nAPP_KEY=base64:abc");
        $rule = new DebugModeRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(1, $findings);
        $this->assertEquals('laravel.debug-mode', $findings[0]['rule_id']);
        $this->assertEquals('CRITICAL', $findings[0]['severity']);
    }

    public function test_debug_mode_passes_when_false(): void
    {
        $this->createFile('.env', "APP_NAME=Test\nAPP_DEBUG=false\nAPP_KEY=base64:abc");
        $rule = new DebugModeRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(0, $findings);
    }

    // ─── 2. EmptyGuardedRule ───

    public function test_empty_guarded_detected(): void
    {
        $this->createFile('app/Models/Post.php', '<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Post extends Model {
    protected $guarded = [];
}');
        $rule = new EmptyGuardedRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(1, $findings);
        $this->assertEquals('laravel.mass-assignment.empty-guarded', $findings[0]['rule_id']);
        $this->assertEquals('HIGH', $findings[0]['severity']);
    }

    public function test_empty_guarded_passes_with_fillable(): void
    {
        $this->createFile('app/Models/Post.php', '<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Post extends Model {
    protected $fillable = ["title", "body"];
}');
        $rule = new EmptyGuardedRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(0, $findings);
    }

    // ─── 3. MissingFillableRule ───

    public function test_missing_fillable_detected(): void
    {
        $this->createFile('app/Models/Comment.php', '<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Comment extends Model {
    public function post() { return $this->belongsTo(Post::class); }
}');
        $rule = new MissingFillableRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(1, $findings);
        $this->assertEquals('MEDIUM', $findings[0]['severity']);
    }

    public function test_missing_fillable_passes_with_guarded(): void
    {
        $this->createFile('app/Models/Comment.php', '<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Comment extends Model {
    protected $guarded = ["id"];
}');
        $rule = new MissingFillableRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(0, $findings);
    }

    // ─── 4. CsrfRouteExceptionRule ───

    public function test_csrf_exception_detected_laravel10(): void
    {
        $this->createFile('app/Http/Middleware/VerifyCsrfToken.php', '<?php
namespace App\Http\Middleware;
class VerifyCsrfToken {
    protected $except = [
        "/webhook/stripe",
    ];
}');
        $rule = new CsrfRouteExceptionRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(1, $findings);
        $this->assertEquals('HIGH', $findings[0]['severity']);
    }

    public function test_csrf_exception_detected_laravel11(): void
    {
        $this->createFile('bootstrap/app.php', '<?php
return Application::configure()
    ->withMiddleware(function ($middleware) {
        $middleware->validateCsrfTokens(except: [
            "/stripe/webhook",
        ]);
    })->create();');
        $rule = new CsrfRouteExceptionRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(1, $findings);
    }

    public function test_csrf_passes_with_no_exceptions(): void
    {
        $this->createFile('app/Http/Middleware/VerifyCsrfToken.php', '<?php
namespace App\Http\Middleware;
class VerifyCsrfToken {
    protected $except = [];
}');
        $rule = new CsrfRouteExceptionRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(0, $findings);
    }

    // ─── 5. BladeRawOutputRule ───

    public function test_blade_raw_output_detected(): void
    {
        $this->createFile('resources/views/posts/show.blade.php', '<div>
    <h1>{{ $post->title }}</h1>
    <div>{!! $post->body !!}</div>
</div>');
        $rule = new BladeRawOutputRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(1, $findings);
        $this->assertEquals('HIGH', $findings[0]['severity']);
        $this->assertEquals('XSS', $findings[0]['category']);
    }

    public function test_blade_escaped_output_passes(): void
    {
        $this->createFile('resources/views/posts/show.blade.php', '<div>
    <h1>{{ $post->title }}</h1>
    <p>{{ $post->body }}</p>
</div>');
        $rule = new BladeRawOutputRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(0, $findings);
    }

    // ─── 6. WhereRawInjectionRule ───

    public function test_whereraw_injection_detected(): void
    {
        $this->createFile('app/Http/Controllers/SearchController.php', '<?php
namespace App\Http\Controllers;
class SearchController {
    public function index(Request $request) {
        return User::whereRaw("name LIKE %" . $request->input("q") . "%")->get();
    }
}');
        $rule = new WhereRawInjectionRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(1, $findings);
        $this->assertEquals('CRITICAL', $findings[0]['severity']);
        $this->assertEquals('SQLi', $findings[0]['category']);
    }

    public function test_whereraw_with_bindings_passes(): void
    {
        $this->createFile('app/Http/Controllers/SearchController.php', '<?php
namespace App\Http\Controllers;
class SearchController {
    public function index(Request $request) {
        return User::whereRaw("name LIKE ?", ["%".$validated["q"]."%"])->get();
    }
}');
        $rule = new WhereRawInjectionRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(0, $findings);
    }

    // ─── 7. OrderByInjectionRule ───

    public function test_orderby_injection_detected(): void
    {
        $this->createFile('app/Http/Controllers/UserController.php', '<?php
namespace App\Http\Controllers;
class UserController {
    public function index(Request $request) {
        return User::query()->orderBy($request->input("sort"))->get();
    }
}');
        $rule = new OrderByInjectionRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(1, $findings);
        $this->assertEquals('HIGH', $findings[0]['severity']);
    }

    public function test_orderby_static_column_passes(): void
    {
        $this->createFile('app/Http/Controllers/UserController.php', '<?php
namespace App\Http\Controllers;
class UserController {
    public function index() {
        return User::orderBy("created_at", "desc")->get();
    }
}');
        $rule = new OrderByInjectionRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(0, $findings);
    }

    // ─── 8. MissingAuthorizationRule ───

    public function test_missing_authorization_detected(): void
    {
        $this->createFile('app/Http/Controllers/PostController.php', '<?php
namespace App\Http\Controllers;
class PostController extends Controller {
    public function store(Request $request) {
        Post::create($request->all());
    }
    public function destroy(Post $post) {
        $post->delete();
    }
}');
        $rule = new MissingAuthorizationRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(1, $findings);
        $this->assertEquals('AuthZ', $findings[0]['category']);
    }

    public function test_authorization_passes_with_gate(): void
    {
        $this->createFile('app/Http/Controllers/PostController.php', '<?php
namespace App\Http\Controllers;
class PostController extends Controller {
    public function store(Request $request) {
        $this->authorize("create", Post::class);
        Post::create($request->validated());
    }
    public function destroy(Post $post) {
        $this->authorize("delete", $post);
        $post->delete();
    }
}');
        $rule = new MissingAuthorizationRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(0, $findings);
    }

    // ─── 9. MissingThrottleRule ───

    public function test_missing_throttle_detected(): void
    {
        $this->createFile('routes/web.php', '<?php
use Illuminate\Support\Facades\Route;
Route::post("/login", [AuthController::class, "login"]);
Route::post("/register", [AuthController::class, "register"]);
');
        $rule = new MissingThrottleRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(2, $findings);
        $this->assertEquals('MEDIUM', $findings[0]['severity']);
    }

    public function test_throttle_passes_when_present(): void
    {
        $this->createFile('routes/web.php', '<?php
use Illuminate\Support\Facades\Route;
Route::middleware("throttle:5,1")->group(function () {
    Route::post("/login", [AuthController::class, "login"]);
});
');
        $rule = new MissingThrottleRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(0, $findings);
    }

    // ─── 10. OpenRedirectRule ───

    public function test_open_redirect_detected(): void
    {
        $this->createFile('app/Http/Controllers/AuthController.php', '<?php
namespace App\Http\Controllers;
class AuthController {
    public function callback(Request $request) {
        return redirect($request->input("redirect_url"));
    }
}');
        $rule = new OpenRedirectRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(1, $findings);
        $this->assertEquals('HIGH', $findings[0]['severity']);
        $this->assertEquals('Redirect', $findings[0]['category']);
    }

    public function test_redirect_to_route_passes(): void
    {
        $this->createFile('app/Http/Controllers/AuthController.php', '<?php
namespace App\Http\Controllers;
class AuthController {
    public function callback() {
        return redirect()->route("dashboard");
    }
}');
        $rule = new OpenRedirectRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(0, $findings);
    }

    // ─── 11. MissingMimeValidationRule ───

    public function test_missing_mime_validation_detected(): void
    {
        $this->createFile('app/Http/Controllers/UploadController.php', '<?php
namespace App\Http\Controllers;
class UploadController {
    public function store(Request $request) {
        $path = $request->file("document")->store("uploads");
    }
}');
        $rule = new MissingMimeValidationRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(1, $findings);
        $this->assertEquals('Upload', $findings[0]['category']);
    }

    public function test_mime_validation_passes(): void
    {
        $this->createFile('app/Http/Controllers/UploadController.php', '<?php
namespace App\Http\Controllers;
class UploadController {
    public function store(Request $request) {
        $request->validate(["document" => "required|file|mimes:pdf,docx"]);
        $path = $request->file("document")->store("uploads");
    }
}');
        $rule = new MissingMimeValidationRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(0, $findings);
    }

    // ─── 12. HardcodedSecretRule ───

    public function test_hardcoded_stripe_key_detected(): void
    {
        $this->createFile('app/Services/PaymentService.php', '<?php
namespace App\Services;
class PaymentService {
    private $key = "sk_live_abcdefghijklmnopqrstuvwx";
}');
        $rule = new HardcodedSecretRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(1, $findings);
        $this->assertEquals('CRITICAL', $findings[0]['severity']);
        $this->assertEquals('Secrets', $findings[0]['category']);
    }

    public function test_hardcoded_aws_key_detected(): void
    {
        $this->createFile('config/services.php', '<?php return [
    "aws" => ["key" => "AKIAIOSFODNN7EXAMPLE"],
];');
        $rule = new HardcodedSecretRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(1, $findings);
    }

    public function test_env_reference_passes(): void
    {
        $this->createFile('config/services.php', '<?php return [
    "stripe" => ["secret" => env("STRIPE_SECRET")],
];');
        $rule = new HardcodedSecretRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(0, $findings);
    }

    // ─── 13. InsecureCookieRule ───

    public function test_insecure_cookie_detected(): void
    {
        $this->createFile('config/session.php', "<?php return [
    'secure' => false,
    'http_only' => false,
];");
        $rule = new InsecureCookieRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(2, $findings);
        $this->assertEquals('MEDIUM', $findings[0]['severity']);
    }

    public function test_secure_cookie_passes(): void
    {
        $this->createFile('config/session.php', "<?php return [
    'secure' => true,
    'http_only' => true,
];");
        $rule = new InsecureCookieRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(0, $findings);
    }

    // ─── 14. WildcardCorsRule ───

    public function test_wildcard_cors_detected(): void
    {
        $this->createFile('config/cors.php', "<?php return [
    'allowed_origins' => ['*'],
];");
        $rule = new WildcardCorsRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(1, $findings);
        $this->assertEquals('MEDIUM', $findings[0]['severity']);
    }

    public function test_specific_cors_passes(): void
    {
        $this->createFile('config/cors.php', "<?php return [
    'allowed_origins' => ['https://example.com'],
];");
        $rule = new WildcardCorsRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(0, $findings);
    }

    // ─── 15. WeakHashRule ───

    public function test_weak_hash_near_password_detected(): void
    {
        $this->createFile('app/Services/AuthService.php', '<?php
namespace App\Services;
class AuthService {
    public function hashPassword($password) {
        return md5($password);
    }
}');
        $rule = new WeakHashRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(1, $findings);
        $this->assertEquals('HIGH', $findings[0]['severity']);
        $this->assertEquals('Crypto', $findings[0]['category']);
    }

    public function test_md5_for_cache_key_passes(): void
    {
        $this->createFile('app/Services/CacheService.php', '<?php
namespace App\Services;
class CacheService {
    public function cacheKey($url) {
        return md5($url);
    }
}');
        $rule = new WeakHashRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(0, $findings);
    }

    // ─── 16. UnserializeInputRule ───

    public function test_unserialize_user_input_detected(): void
    {
        $this->createFile('app/Http/Controllers/DataController.php', '<?php
namespace App\Http\Controllers;
class DataController {
    public function import(Request $request) {
        $data = unserialize($request->input("payload"));
    }
}');
        $rule = new UnserializeInputRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(1, $findings);
        $this->assertEquals('CRITICAL', $findings[0]['severity']);
        $this->assertEquals('RCE', $findings[0]['category']);
    }

    public function test_unserialize_internal_data_passes(): void
    {
        $this->createFile('app/Http/Controllers/DataController.php', '<?php
namespace App\Http\Controllers;
class DataController {
    public function load() {
        $data = unserialize(file_get_contents(storage_path("cache.dat")));
    }
}');
        $rule = new UnserializeInputRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(0, $findings);
    }

    // ─── 17. EvalDynamicRule ───

    public function test_eval_dynamic_detected(): void
    {
        $this->createFile('app/Services/TemplateService.php', '<?php
namespace App\Services;
class TemplateService {
    public function render($code) {
        eval("return " . $code . ";");
    }
}');
        $rule = new EvalDynamicRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(1, $findings);
        $this->assertEquals('CRITICAL', $findings[0]['severity']);
    }

    public function test_no_eval_passes(): void
    {
        $this->createFile('app/Services/TemplateService.php', '<?php
namespace App\Services;
class TemplateService {
    public function render($data) {
        return view("template", $data);
    }
}');
        $rule = new EvalDynamicRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(0, $findings);
    }

    // ─── 18. CommandInjectionRule ───

    public function test_command_injection_detected(): void
    {
        $this->createFile('app/Http/Controllers/ToolController.php', '<?php
namespace App\Http\Controllers;
class ToolController {
    public function ping(Request $request) {
        $output = shell_exec("ping -c 1 " . $request->input("host"));
    }
}');
        $rule = new CommandInjectionRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(1, $findings);
        $this->assertEquals('CRITICAL', $findings[0]['severity']);
        $this->assertEquals('RCE', $findings[0]['category']);
    }

    public function test_process_facade_passes(): void
    {
        $this->createFile('app/Http/Controllers/ToolController.php', '<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\Process;
class ToolController {
    public function ping() {
        $result = Process::run(["ping", "-c", "1", "127.0.0.1"]);
    }
}');
        $rule = new CommandInjectionRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(0, $findings);
    }

    // ─── 19. OutdatedVersionRule ───

    public function test_outdated_laravel_detected(): void
    {
        $this->createFile('composer.json', json_encode([
            'require' => ['laravel/framework' => '^9.0'],
        ], JSON_UNESCAPED_SLASHES));
        $rule = new OutdatedVersionRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(1, $findings);
        $this->assertEquals('HIGH', $findings[0]['severity']);
        $this->assertEquals('Dependency', $findings[0]['category']);
    }

    public function test_current_laravel_passes(): void
    {
        $this->createFile('composer.json', json_encode([
            'require' => ['laravel/framework' => '^11.0'],
        ], JSON_UNESCAPED_SLASHES));
        $rule = new OutdatedVersionRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(0, $findings);
    }

    public function test_laravel_12_passes(): void
    {
        $this->createFile('composer.json', json_encode([
            'require' => ['laravel/framework' => '^12.0'],
        ], JSON_UNESCAPED_SLASHES));
        $rule = new OutdatedVersionRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(0, $findings);
    }

    // ─── 20. EnvExposedRule ───

    public function test_env_in_public_detected(): void
    {
        $this->createFile('public/.env', "APP_KEY=base64:secret\nDB_PASSWORD=root");
        $rule = new EnvExposedRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(1, $findings);
        $this->assertEquals('CRITICAL', $findings[0]['severity']);
    }

    public function test_env_backup_in_public_detected(): void
    {
        $this->createFile('public/.env.backup', "APP_KEY=base64:secret");
        $rule = new EnvExposedRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(1, $findings);
    }

    public function test_no_env_in_public_passes(): void
    {
        $this->createFile('public/index.php', '<?php require __DIR__."/../vendor/autoload.php";');
        $rule = new EnvExposedRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertCount(0, $findings);
    }

    // ─── LaravelRuleEngine integration ───

    public function test_engine_aggregates_all_findings(): void
    {
        $this->createFile('.env', "APP_DEBUG=true");
        $this->createFile('app/Models/User.php', '<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class User extends Model {
    protected $guarded = [];
}');
        $this->createFile('resources/views/test.blade.php', '<div>{!! $name !!}</div>');

        $engine = new LaravelRuleEngine;
        $result = $engine->analyze($this->fixturePath);

        $this->assertArrayHasKey('findings', $result);
        $this->assertArrayHasKey('summary', $result);
        $this->assertGreaterThanOrEqual(3, $result['summary']['total']);
        $this->assertGreaterThanOrEqual(1, $result['summary']['CRITICAL']);
        $this->assertGreaterThanOrEqual(1, $result['summary']['HIGH']);
    }

    public function test_engine_returns_zero_on_clean_project(): void
    {
        $this->createFile('.env', "APP_DEBUG=false");
        $this->createFile('app/Models/User.php', '<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class User extends Model {
    protected $fillable = ["name", "email"];
}');
        $this->createFile('composer.json', json_encode([
            'require' => ['laravel/framework' => '^11.0'],
        ]));

        $engine = new LaravelRuleEngine;
        $result = $engine->analyze($this->fixturePath);

        $this->assertEquals(0, $result['summary']['total']);
        $this->assertEmpty($result['findings']);
    }

    // ─── Finding structure validation ───

    public function test_finding_has_required_fields(): void
    {
        $this->createFile('.env', "APP_DEBUG=true");
        $rule = new DebugModeRule;
        $findings = $rule->check($this->fixturePath);

        $this->assertNotEmpty($findings);
        $finding = $findings[0];

        $requiredKeys = ['rule_id', 'title', 'description', 'severity', 'category', 'file_path', 'line_start', 'code_snippet', 'fix_guidance'];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $finding, "Finding missing required key: {$key}");
        }
    }
}
