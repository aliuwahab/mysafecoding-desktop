<?php

namespace Tests\Unit;

use App\Scanning\Rules\Packs;
use App\Scanning\Rules\PatternRuleScanner;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every pack rule is checked against a vulnerable snippet that must trigger
 * it and, where it matters, a safe variant of the same code that must not.
 */
class RulePackTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir().'/checkmycode-packs-'.uniqid();
        mkdir($this->path, 0755, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->path);
        parent::tearDown();
    }

    /**
     * @param  array<string, string>  $files
     * @return list<string>
     */
    private function scan(string $type, array $files): array
    {
        foreach ($files as $relative => $contents) {
            if (! is_dir(dirname($this->path.'/'.$relative))) {
                mkdir(dirname($this->path.'/'.$relative), 0755, true);
            }
            file_put_contents($this->path.'/'.$relative, $contents);
        }

        return array_column((new PatternRuleScanner)->scan($this->path, $type)['findings'], 'rule_id');
    }

    /**
     * [project type, file path, vulnerable code, rule id, safe code or null]
     */
    public static function cases(): array
    {
        return [
            // Python / Django / Flask / FastAPI
            'py shell=True' => ['python', 'app/jobs.py', 'subprocess.run(f"convert {name}", shell=True)', 'python.shell-true', 'subprocess.run(["convert", name])'],
            'py sql f-string' => ['python', 'app/db.py', 'cursor.execute(f"SELECT * FROM users WHERE id = {uid}")', 'python.sql-string-format', 'cursor.execute("SELECT * FROM users WHERE id = %s", [uid])'],
            'py sql %' => ['django', 'app/db.py', 'User.objects.raw("SELECT * FROM auth_user WHERE name = \'%s\'" % name)', 'python.sql-string-format', null],
            'py yaml' => ['python', 'app/cfg.py', 'data = yaml.load(body)', 'python.yaml-unsafe-load', 'data = yaml.load(body, Loader=yaml.SafeLoader)'],
            'py jwt' => ['python', 'app/auth.py', 'jwt.decode(token, options={"verify_signature": False})', 'python.jwt-signature-not-verified', 'jwt.decode(token, key, algorithms=["HS256"])'],
            'py redirect' => ['flask', 'app/views.py', 'return redirect(request.args.get("next"))', 'python.open-redirect', 'return redirect(url_for("home"))'],
            'py upload' => ['flask', 'app/upload.py', 'f.save(os.path.join(UPLOADS, f.filename))', 'python.upload-unsafe-filename', 'f.save(os.path.join(UPLOADS, secure_filename(f.filename)))'],
            'py path traversal' => ['flask', 'app/files.py', 'return send_file(request.args["path"])', 'python.path-traversal', null],
            'py random token' => ['python', 'app/tokens.py', 'reset_token = "".join(random.choice(chars) for _ in range(32))', 'python.insecure-random', 'reset_token = secrets.token_urlsafe(32)'],
            'py login no limit' => ['flask', 'app/views.py', "@app.post('/login')\ndef login():\n    pass", 'python.auth-missing-rate-limit', "@limiter.limit('5/minute')\n@app.post('/login')\ndef login():\n    pass"],
            'django debug' => ['django', 'mysite/settings.py', 'DEBUG = True', 'django.debug-enabled', 'DEBUG = os.environ.get("DEBUG") == "1"'],
            'django secret' => ['django', 'mysite/settings.py', "SECRET_KEY = 'django-insecure-abcdefghijk'", 'django.hardcoded-secret-key', "SECRET_KEY = os.environ['SECRET_KEY']"],
            'django cookie' => ['django', 'mysite/settings.py', 'SESSION_COOKIE_SECURE = False', 'django.insecure-cookie-setting', 'SESSION_COOKIE_SECURE = True'],
            'django cors' => ['django', 'mysite/settings.py', 'CORS_ALLOW_ALL_ORIGINS = True', 'django.cors-allow-all', null],
            'django csrf' => ['django', 'shop/views.py', "@csrf_exempt\ndef checkout(request): pass", 'django.csrf-exempt', null],
            'django mark_safe' => ['django', 'shop/views.py', 'html = mark_safe(comment.body)', 'django.mark-safe', 'html = format_html("<b>{}</b>", comment.body)'],
            'django template safe' => ['django', 'shop/templates/c.html', '{{ comment.body|safe }}', 'python.template-autoescape-off', '{{ comment.body }}'],
            'django all fields' => ['django', 'shop/forms.py', "    fields = '__all__'", 'django.modelform-all-fields', "    fields = ['title']"],
            'flask debug' => ['flask', 'app.py', 'app.run(host="0.0.0.0", debug=True)', 'flask.debug-enabled', 'app.run(host="0.0.0.0")'],
            'flask secret' => ['flask', 'app.py', 'app.secret_key = "dev-secret"', 'flask.hardcoded-secret-key', 'app.secret_key = os.environ["SECRET_KEY"]'],
            'flask ssti' => ['flask', 'app.py', 'return render_template_string(tpl)', 'flask.render-template-string', 'return render_template("x.html")'],
            'fastapi cors' => ['fastapi', 'main.py', 'app.add_middleware(CORSMiddleware, allow_origins=["*"])', 'python.cors-wildcard', 'app.add_middleware(CORSMiddleware, allow_origins=["https://app.example"])'],

            // JavaScript / TypeScript
            'js exec' => ['node', 'src/run.js', 'exec(`convert ${file}`)', 'js.command-injection', "execFile('convert', [file])"],
            'js sql' => ['express', 'src/db.ts', 'await db.query(`SELECT * FROM users WHERE id = ${id}`)', 'js.sql-injection', "await db.query('SELECT * FROM users WHERE id = $1', [id])"],
            'js prisma unsafe' => ['nextjs', 'src/db.ts', 'await prisma.$queryRawUnsafe(sql, id)', 'js.prisma-raw-unsafe', 'await prisma.$queryRaw`SELECT 1`'],
            'react html' => ['react', 'src/C.tsx', '<div dangerouslySetInnerHTML={{ __html: body }} />', 'react.dangerously-set-inner-html', '<div dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(body) }} />'],
            'vue v-html' => ['vue', 'src/C.vue', '<div v-html="comment.body"></div>', 'js.template-raw-html', '<div>{{ comment.body }}</div>'],
            'svelte html' => ['svelte', 'src/C.svelte', '{@html post.body}', 'js.template-raw-html', '{post.body}'],
            'angular bypass' => ['angular', 'src/c.ts', 'this.html = this.sanitizer.bypassSecurityTrustHtml(v)', 'angular.bypass-security-trust', null],
            'js redirect' => ['express', 'src/r.js', 'res.redirect(req.query.next)', 'js.open-redirect', "res.redirect('/home')"],
            'js path' => ['express', 'src/f.js', 'res.sendFile(path.join(__dirname, req.params.name))', 'js.path-traversal', null],
            'js mass assignment' => ['express', 'src/u.js', 'await User.create(req.body)', 'js.mass-assignment', 'await User.create({ name: req.body.name })'],
            'js cors' => ['express', 'src/app.js', 'app.use(cors())', 'js.cors-wildcard', "app.use(cors({ origin: ['https://app.example'] }))"],
            'js cookie' => ['express', 'src/app.js', "res.cookie('sid', v, { httpOnly: false })", 'js.insecure-cookie', "res.cookie('sid', v, { httpOnly: true })"],
            'js session secret' => ['express', 'src/app.js', "app.use(session({ secret: 'keyboard cat' }))", 'js.hardcoded-session-secret', 'app.use(session({ secret: process.env.SESSION_SECRET }))'],
            'js jwt sign' => ['node', 'src/auth.js', "jwt.sign(payload, 'supersecret')", 'js.jwt-hardcoded-secret', 'jwt.sign(payload, process.env.JWT_SECRET)'],
            'js jwt decode' => ['node', 'src/auth.js', 'const user = jwt.decode(token)', 'js.jwt-not-verified', 'const user = jwt.verify(token, key)'],
            'js weak hash' => ['node', 'src/h.js', "crypto.createHash('md5')", 'js.weak-hash', "crypto.createHash('sha256')"],
            'js random' => ['node', 'src/t.js', 'const resetToken = Math.random().toString(36)', 'js.insecure-random', "const resetToken = crypto.randomBytes(32).toString('hex')"],
            'js tls' => ['node', 'src/h.js', 'new https.Agent({ rejectUnauthorized: false })', 'js.tls-verification-disabled', null],
            'js node-serialize' => ['node', 'src/s.js', "const s = require('node-serialize')", 'js.unsafe-deserialization', null],
            'next public secret' => ['nextjs', 'src/a.ts', 'const k = process.env.NEXT_PUBLIC_STRIPE_SECRET_KEY', 'js.secret-in-public-env', 'const k = process.env.NEXT_PUBLIC_STRIPE_PUBLISHABLE_KEY'],
            'express login no limit' => ['express', 'src/routes.js', "router.post('/login', loginHandler)", 'js.auth-missing-rate-limit', "router.post('/login', loginLimiter, loginHandler)"],
            'express static root' => ['express', 'src/app.js', 'app.use(express.static(__dirname))', 'js.static-serves-project-root', "app.use(express.static(path.join(__dirname, 'public')))"],

            // Ruby / Rails
            'rails order sql' => ['rails', 'app/models/u.rb', 'User.order("#{params[:sort]} ASC")', 'rails.sql-injection-raw', 'User.order(created_at: :desc)'],
            'rails html_safe' => ['rails', 'app/views/p.html.erb', '<%= comment.body.html_safe %>', 'rails.html-safe', '<%= comment.body %>'],
            'rails csrf' => ['rails', 'app/controllers/a_controller.rb', 'skip_before_action :verify_authenticity_token', 'rails.csrf-disabled', null],
            'rails redirect' => ['rails', 'app/controllers/a_controller.rb', 'redirect_to params[:return_to]', 'rails.open-redirect', 'redirect_to root_path'],
            'rails send_file' => ['rails', 'app/controllers/a_controller.rb', 'send_file params[:path]', 'rails.path-traversal', null],
            'rails constantize' => ['rails', 'app/controllers/a_controller.rb', 'params[:type].constantize.new', 'rails.dynamic-dispatch', null],
            'ruby yaml' => ['ruby', 'lib/c.rb', 'YAML.load(body)', 'ruby.yaml-unsafe-load', 'YAML.safe_load(body)'],
            'ruby weak hash' => ['ruby', 'lib/c.rb', 'Digest::MD5.hexdigest(v)', 'ruby.weak-hash', 'Digest::SHA256.hexdigest(v)'],
            'ruby tls' => ['ruby', 'lib/c.rb', 'http.verify_mode = OpenSSL::SSL::VERIFY_NONE', 'ruby.tls-verification-disabled', 'http.verify_mode = OpenSSL::SSL::VERIFY_PEER'],
            'rails cookie' => ['rails', 'app/controllers/a_controller.rb', 'cookies[:t] = { value: t, httponly: false }', 'rails.insecure-cookie', null],
            'rails cors' => ['rails', 'config/initializers/cors.rb', "    origins '*'", 'rails.cors-wildcard', "    origins 'https://app.example'"],
            'rails force_ssl' => ['rails', 'config/environments/production.rb', '  config.force_ssl = false', 'rails.force-ssl-disabled', '  config.force_ssl = true'],
            'rails debug' => ['rails', 'config/environments/production.rb', '  config.consider_all_requests_local = true', 'rails.debug-in-production', '  config.consider_all_requests_local = false'],
            'rails permit role' => ['rails', 'app/controllers/u_controller.rb', 'params.require(:user).permit(:name, :role)', 'rails.privileged-attribute-permitted', 'params.require(:user).permit(:name)'],
            'rails login no limit' => ['rails', 'app/controllers/sessions_controller.rb', "class SessionsController\n  def create\n  end\nend", 'rails.auth-missing-rate-limit', "class SessionsController\n  rate_limit to: 5, within: 1.minute\n  def create\n  end\nend"],

            // Go
            'go shell' => ['go', 'cmd/run.go', 'exec.Command("sh", "-c", cmd)', 'go.shell-command', 'exec.Command("convert", in, out)'],
            'go sql concat' => ['go', 'db/q.go', 'db.Query("SELECT * FROM users WHERE id = " + id)', 'go.sql-string-concat', 'db.Query("SELECT * FROM users WHERE id = $1", id)'],
            'go weak crypto' => ['go', 'h/h.go', 'h := sha1.New()', 'go.weak-crypto', 'h := sha256.New()'],
            'go math rand' => ['go', 'h/t.go', "import \"math/rand\"\nvar n = rand.Intn(999999)", 'go.insecure-random', "import \"crypto/rand\"\nvar _, _ = rand.Read(b)"],
            'go template html' => ['go', 'web/h.go', 'data := template.HTML(r.FormValue("bio"))', 'go.template-unescaped', null],
            'go redirect' => ['go', 'web/h.go', 'http.Redirect(w, r, r.URL.Query().Get("next"), http.StatusFound)', 'go.open-redirect', 'http.Redirect(w, r, "/home", http.StatusFound)'],
            'gin redirect' => ['go', 'web/h.go', 'c.Redirect(http.StatusFound, c.Query("next"))', 'go.framework-open-redirect', null],
            'go path' => ['go', 'web/h.go', 'http.ServeFile(w, r, r.URL.Query().Get("f"))', 'go.path-traversal', null],
            'go cors' => ['go', 'web/m.go', 'AllowedOrigins: []string{"*"},', 'go.cors-wildcard', 'AllowedOrigins: []string{"https://app.example"},'],
            'go cookie' => ['go', 'web/m.go', 'HttpOnly: false,', 'go.insecure-cookie', 'HttpOnly: true,'],
            'go jwt' => ['go', 'auth/j.go', 'jwt.ParseUnverified(tok, claims)', 'go.jwt-not-verified', null],
            'gin debug' => ['go', 'main.go', 'gin.SetMode(gin.DebugMode)', 'go.gin-debug-mode', 'gin.SetMode(gin.ReleaseMode)'],
            'go pprof' => ['go', 'main.go', 'import _ "net/http/pprof"', 'go.pprof-exposed', null],
            'go no timeouts' => ['go', 'main.go', 'http.ListenAndServe(":8080", mux)', 'go.http-server-no-timeouts', "srv := &http.Server{ReadHeaderTimeout: 5 * time.Second}\nhttp.ListenAndServe(\":8080\", mux)"],

            // Java / Kotlin / Spring
            'java sql' => ['spring', 'src/main/java/a/Repo.java', 'stmt.executeQuery("SELECT * FROM users WHERE id = " + id);', 'java.sql-injection', 'ps = conn.prepareStatement("SELECT * FROM users WHERE id = ?");'],
            'java jpql' => ['spring', 'src/main/java/a/Repo.java', 'em.createQuery("FROM User WHERE name = \'" + name + "\'");', 'java.sql-injection', null],
            'java exec' => ['java', 'src/main/java/a/Run.java', 'Runtime.getRuntime().exec(cmd);', 'java.command-execution', null],
            'java deserialization' => ['java', 'src/main/java/a/S.java', 'ObjectInputStream in = new ObjectInputStream(stream);', 'java.unsafe-deserialization', null],
            'java xxe' => ['java', 'src/main/java/a/X.java', 'DocumentBuilderFactory f = DocumentBuilderFactory.newInstance();', 'java.xxe', "DocumentBuilderFactory f = DocumentBuilderFactory.newInstance();\nf.setFeature(\"http://apache.org/xml/features/disallow-doctype-decl\", true);"],
            'java weak hash' => ['java', 'src/main/java/a/H.java', 'MessageDigest.getInstance("MD5");', 'java.weak-hash', 'MessageDigest.getInstance("SHA-256");'],
            'java ecb' => ['java', 'src/main/java/a/C.java', 'Cipher.getInstance("AES");', 'java.weak-cipher', 'Cipher.getInstance("AES/GCM/NoPadding");'],
            'java hostname' => ['java', 'src/main/java/a/T.java', 'builder.setSSLHostnameVerifier(NoopHostnameVerifier.INSTANCE);', 'java.tls-verification-disabled', null],
            'java trust all' => ['java', 'src/main/java/a/T.java', 'public void checkServerTrusted(X509Certificate[] c, String a) {}', 'java.trust-all-certificates', null],
            'java random' => ['java', 'src/main/java/a/T.java', 'int resetCode = new Random().nextInt(999999);', 'java.insecure-random', 'int resetCode = new SecureRandom().nextInt(999999);'],
            'java upload path' => ['spring', 'src/main/java/a/U.java', 'Path p = Paths.get(dir, file.getOriginalFilename());', 'java.path-traversal', null],
            'spring redirect' => ['spring', 'src/main/java/a/C.java', 'return "redirect:" + next;', 'java.open-redirect', 'return "redirect:/home";'],
            'java cookie' => ['java', 'src/main/java/a/C.java', 'cookie.setHttpOnly(false);', 'java.insecure-cookie', 'cookie.setHttpOnly(true);'],
            'spring csrf' => ['spring', 'src/main/java/a/Sec.java', 'http.csrf(AbstractHttpConfigurer::disable);', 'spring.csrf-disabled', null],
            'spring csrf kotlin' => ['spring', 'src/main/kotlin/a/Sec.kt', 'http { csrf { disable() } }', 'spring.csrf-disabled', null],
            'spring permitAll' => ['spring', 'src/main/java/a/Sec.java', '.anyRequest().permitAll()', 'spring.permit-all', '.anyRequest().authenticated()'],
            'spring cors' => ['spring', 'src/main/java/a/C.java', '@CrossOrigin(origins = "*")', 'spring.cors-wildcard', '@CrossOrigin(origins = "https://app.example")'],
            'spring noop' => ['spring', 'src/main/java/a/Sec.java', 'return NoOpPasswordEncoder.getInstance();', 'spring.plaintext-passwords', 'return new BCryptPasswordEncoder();'],
            'spring actuator' => ['spring', 'src/main/resources/application.properties', 'management.endpoints.web.exposure.include=*', 'spring.actuator-exposed', 'management.endpoints.web.exposure.include=health,info'],
            'spring stacktrace' => ['spring', 'src/main/resources/application.properties', 'server.error.include-stacktrace=always', 'spring.debug-config', 'server.error.include-stacktrace=never'],
            'spring datasource' => ['spring', 'src/main/resources/application.properties', 'spring.datasource.password=hunter22', 'spring.hardcoded-secret', 'spring.datasource.password=${DB_PASSWORD}'],
            'spring login no limit' => ['spring', 'src/main/java/a/AuthController.java', '@PostMapping("/login")', 'spring.auth-missing-rate-limit', "@RateLimiter(name = \"auth\")\n@PostMapping(\"/login\")"],
            'spring entity body' => ['spring', 'src/main/java/a/UserController.java', "@PostMapping\npublic User save(@RequestBody User user) {}", 'spring.entity-as-request-body', "@PostMapping\npublic User save(@RequestBody CreateUserRequest user) {}"],

            // .NET
            'cs sql' => ['dotnet', 'Data/Repo.cs', 'db.Users.FromSqlRaw($"SELECT * FROM Users WHERE Name = \'{name}\'");', 'dotnet.sql-injection', 'db.Users.FromSql($"SELECT * FROM Users WHERE Name = {name}");'],
            'cs sqlcommand' => ['dotnet', 'Data/Repo.cs', 'var cmd = new SqlCommand("SELECT * FROM Users WHERE Id = " + id, conn);', 'dotnet.sql-injection', null],
            'cs binaryformatter' => ['dotnet', 'Svc/S.cs', 'var f = new BinaryFormatter();', 'dotnet.unsafe-deserialization', null],
            'cs typenamehandling' => ['dotnet', 'Svc/S.cs', 'TypeNameHandling = TypeNameHandling.All', 'dotnet.unsafe-deserialization', 'TypeNameHandling = TypeNameHandling.None'],
            'cs html raw' => ['dotnet', 'Views/Home/Index.cshtml', '@Html.Raw(Model.Bio)', 'dotnet.html-raw', '@Model.Bio'],
            'cs ignore antiforgery' => ['dotnet', 'Controllers/A.cs', '[IgnoreAntiforgeryToken]', 'dotnet.csrf-disabled', null],
            'cs mvc post' => ['dotnet', 'Controllers/AccountController.cs', "public class AccountController : Controller {\n[HttpPost]\npublic IActionResult Save() {}\n}", 'dotnet.post-without-antiforgery', "public class AccountController : Controller {\n[HttpPost]\n[ValidateAntiForgeryToken]\npublic IActionResult Save() {}\n}"],
            'cs cors' => ['dotnet', 'Program.cs', 'p.AllowAnyOrigin().AllowAnyHeader();', 'dotnet.cors-wildcard', 'p.WithOrigins("https://app.example");'],
            'cs weak hash' => ['dotnet', 'Svc/H.cs', 'using var md5 = MD5.Create();', 'dotnet.weak-hash', 'SHA256.HashData(bytes);'],
            'cs weak cipher' => ['dotnet', 'Svc/H.cs', 'var des = DES.Create();', 'dotnet.weak-cipher', null],
            'cs tls' => ['dotnet', 'Svc/H.cs', 'handler.ServerCertificateCustomValidationCallback = HttpClientHandler.DangerousAcceptAnyServerCertificateValidator;', 'dotnet.tls-verification-disabled', null],
            'cs jwt' => ['dotnet', 'Program.cs', 'ValidateLifetime = false,', 'dotnet.jwt-validation-disabled', 'ValidateLifetime = true,'],
            'cs redirect' => ['dotnet', 'Controllers/A.cs', 'return Redirect(returnUrl);', 'dotnet.open-redirect', "if (Url.IsLocalUrl(returnUrl))\n    return Redirect(returnUrl);"],
            'cs upload path' => ['dotnet', 'Controllers/U.cs', 'var path = Path.Combine(root, file.FileName);', 'dotnet.path-traversal', 'var path = Path.Combine(root, Path.GetRandomFileName());'],
            'cs process' => ['dotnet', 'Svc/P.cs', 'Process.Start("cmd.exe", "/c " + arg);', 'dotnet.process-start', null],
            'cs dev page' => ['dotnet', 'Program.cs', 'app.UseDeveloperExceptionPage();', 'dotnet.developer-exception-page', "if (app.Environment.IsDevelopment())\n{\n    app.UseDeveloperExceptionPage();\n}"],
            'cs cookie' => ['dotnet', 'Program.cs', 'o.Cookie.SecurePolicy = CookieSecurePolicy.None;', 'dotnet.insecure-cookie', 'o.Cookie.SecurePolicy = CookieSecurePolicy.Always;'],
            'cs login no limit' => ['dotnet', 'Controllers/AuthController.cs', '[HttpPost("login")]', 'dotnet.auth-missing-rate-limit', "[EnableRateLimiting(\"auth\")]\n[HttpPost(\"login\")]"],
            'cs appsettings' => ['dotnet', 'appsettings.json', '"Default": "Server=db;Database=app;User Id=sa;Password=Hunter2!;"', 'dotnet.connection-string-password', '"Default": "Server=db;Database=app;Integrated Security=true;"'],

            // Rust
            'rust sql' => ['rust', 'src/db.rs', 'sqlx::query(&format!("SELECT * FROM users WHERE id = {}", id))', 'rust.sql-injection', 'sqlx::query("SELECT * FROM users WHERE id = $1").bind(id)'],
            'rust shell' => ['rust', 'src/run.rs', 'Command::new("sh").arg("-c").arg(cmd)', 'rust.shell-command', 'Command::new("convert").arg(input)'],
            'rust tls' => ['rust', 'src/http.rs', 'reqwest::Client::builder().danger_accept_invalid_certs(true)', 'rust.tls-verification-disabled', null],
            'rust weak hash' => ['rust', 'src/h.rs', 'let digest = md5::compute(data);', 'rust.weak-hash', 'let digest = Sha256::digest(data);'],
            'rust cors' => ['rust', 'src/main.rs', '.layer(CorsLayer::permissive())', 'rust.cors-wildcard', null],
            'rust preescaped' => ['rust', 'src/view.rs', 'html! { (PreEscaped(bio)) }', 'rust.unescaped-html', null],
            'rust jwt' => ['rust', 'src/auth.rs', 'validation.insecure_disable_signature_validation();', 'rust.jwt-not-verified', null],
            'rust unsafe' => ['rust', 'src/ffi.rs', 'let v = unsafe { *ptr };', 'rust.unsafe-block', null],

            // Elixir / Phoenix
            'ex eval' => ['phoenix', 'lib/app/calc.ex', 'Code.eval_string(expr)', 'elixir.code-eval', null],
            'ex binary_to_term' => ['elixir', 'lib/app/s.ex', ':erlang.binary_to_term(data)', 'elixir.unsafe-binary-to-term', ':erlang.binary_to_term(data, [:safe])'],
            'ex atoms' => ['phoenix', 'lib/app/s.ex', 'String.to_atom(params["sort"])', 'elixir.atom-exhaustion', 'String.to_existing_atom(params["sort"])'],
            'ex shell' => ['elixir', 'lib/app/s.ex', ':os.cmd(String.to_charlist(cmd))', 'elixir.shell-command', 'System.cmd("convert", [input])'],
            'ex sql' => ['phoenix', 'lib/app/q.ex', 'Repo.query("SELECT * FROM users WHERE name = \'#{name}\'")', 'elixir.sql-injection', 'Repo.query("SELECT * FROM users WHERE name = $1", [name])'],
            'ex fragment' => ['phoenix', 'lib/app/q.ex', 'where: fragment("lower(#{field}) = ?", ^v)', 'elixir.sql-injection', 'where: fragment("lower(?) = ?", u.name, ^v)'],
            'heex raw' => ['phoenix', 'lib/app_web/components/c.html.heex', '<%= raw(@bio) %>', 'phoenix.raw-html', '<%= @bio %>'],
            'heex raw variable' => ['phoenix', 'lib/app_web/layouts/app.html.heex', 'message: raw(flash)', 'phoenix.raw-html', '<%= raw(MyAppWeb.Email.footer(:html)) %>'],
            'heex raw json' => ['phoenix', 'lib/app_web/page.html.heex', 'var data = <%= raw(Jason.encode!(@data)) %>;', 'phoenix.raw-json-in-script', 'var data = <%= raw(Jason.encode!(@data, escape: :html_safe)) %>;'],
            'phoenix redirect' => ['phoenix', 'lib/app_web/controllers/a.ex', 'redirect(conn, external: params["next"])', 'phoenix.open-redirect', 'redirect(conn, to: ~p"/home")'],
            'ex weak hash' => ['elixir', 'lib/app/h.ex', ':crypto.hash(:md5, data)', 'elixir.weak-hash', ':crypto.hash(:sha256, data)'],
            'phoenix check_origin' => ['phoenix', 'config/prod.exs', 'config :app, AppWeb.Endpoint, check_origin: false', 'phoenix.check-origin-disabled', null],
            'phoenix debug prod' => ['phoenix', 'config/prod.exs', 'config :app, AppWeb.Endpoint, debug_errors: true', 'phoenix.debug-in-production', null],
            'phoenix secret' => ['phoenix', 'config/prod.exs', 'secret_key_base: "aGVsbG8gd29ybGQgdGhpcyBpcyBzZWNyZXQ="', 'phoenix.hardcoded-secret', 'secret_key_base: System.fetch_env!("SECRET_KEY_BASE")'],
            'phoenix csrf' => ['phoenix', 'lib/app_web/router.ex', "pipeline :browser do\n  plug :fetch_session\nend", 'phoenix.csrf-missing', "pipeline :browser do\n  plug :protect_from_forgery\nend"],
            'phoenix login no limit' => ['phoenix', 'lib/app_web/router.ex', 'post "/users/log_in", UserSessionController, :create', 'phoenix.auth-missing-rate-limit', "plug Hammer.Plug\npost \"/users/log_in\", UserSessionController, :create"],

            // Plain PHP / Symfony
            'php sql' => ['php', 'src/db.php', '$pdo->query("SELECT * FROM users WHERE id = " . $id);', 'php.sql-concatenation', '$pdo->prepare("SELECT * FROM users WHERE id = ?");'],
            'php sql interpolation' => ['php', 'src/db.php', 'mysqli_query($conn, "SELECT * FROM users WHERE id = $id");', 'php.sql-concatenation', null],
            'php include' => ['php', 'index.php', 'include $_GET["page"] . ".php";', 'php.file-inclusion', 'include __DIR__."/pages/home.php";'],
            'php xss' => ['php', 'index.php', 'echo $_GET["q"];', 'php.reflected-xss', 'echo htmlspecialchars($_GET["q"], ENT_QUOTES);'],
            'php extract' => ['php', 'index.php', 'extract($_POST);', 'php.extract-request', null],
            'php curl tls' => ['php', 'src/http.php', 'curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);', 'php.curl-tls-verification-disabled', 'curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);'],
            'php cors' => ['php', 'api.php', 'header("Access-Control-Allow-Origin: *");', 'php.cors-wildcard', null],
            'php upload' => ['php', 'upload.php', 'move_uploaded_file($_FILES["f"]["tmp_name"], "uploads/" . $_FILES["f"]["name"]);', 'php.upload-client-filename', null],
            'symfony redirect' => ['symfony', 'src/Controller/A.php', 'return new RedirectResponse($request->query->get("next"));', 'php.raw-open-redirect', 'return $this->redirectToRoute("home");'],
            'twig raw' => ['symfony', 'templates/p.html.twig', '{{ comment.body|raw }}', 'symfony.twig-raw', '{{ comment.body }}'],
            'symfony csrf' => ['symfony', 'config/packages/framework.yaml', '    csrf_protection: false', 'symfony.csrf-disabled', '    csrf_protection: true'],
            'symfony public admin' => ['symfony', 'config/packages/security.yaml', '        - { path: ^/admin, roles: PUBLIC_ACCESS }', 'symfony.admin-public-access', '        - { path: ^/admin, roles: ROLE_ADMIN }'],
        ];
    }

    #[DataProvider('cases')]
    public function test_rule_flags_vulnerable_code_and_passes_safe_code(string $type, string $file, string $vulnerable, string $rule, ?string $safe): void
    {
        $rule = 'checkmycode.'.$rule;

        $this->assertContains($rule, $this->scan($type, [$file => $vulnerable]), "Expected {$rule} on: {$vulnerable}");

        if ($safe !== null) {
            $this->assertNotContains($rule, $this->scan($type, [$file => $safe]), "Did not expect {$rule} on: {$safe}");
        }
    }

    public function test_every_pack_rule_has_the_fields_a_finding_needs(): void
    {
        foreach ([Packs\UniversalPack::class, Packs\PhpPack::class, Packs\PythonPack::class, Packs\JavaScriptPack::class, Packs\RubyPack::class, Packs\GoPack::class, Packs\JavaPack::class, Packs\RustPack::class, Packs\DotNetPack::class, Packs\ElixirPack::class] as $class) {
            foreach ((new $class)->rules() as $rule) {
                foreach (['pattern', 'rule_id', 'title', 'severity', 'category', 'why', 'fix'] as $key) {
                    $this->assertNotEmpty($rule[$key] ?? null, "{$class} rule ".($rule['rule_id'] ?? '?')." is missing {$key}");
                }
                foreach (['pattern', 'unless', 'unless_nearby', 'when_file', 'unless_file', 'paths', 'skip'] as $key) {
                    if (isset($rule[$key])) {
                        $this->assertNotFalse(@preg_match($rule[$key], ''), "{$rule['rule_id']} has an invalid {$key} regex");
                    }
                }
            }
        }
    }

    public function test_test_files_are_skipped_by_language_rules(): void
    {
        $ids = $this->scan('node', [
            'src/__tests__/a.test.js' => 'res.redirect(req.query.next)',
            'src/a.spec.ts' => 'res.redirect(req.query.next)',
        ]);

        $this->assertNotContains('checkmycode.js.open-redirect', $ids);
    }

    public function test_framework_rules_only_run_for_their_framework(): void
    {
        $this->assertNotContains('checkmycode.django.csrf-exempt', $this->scan('flask', ['app/views.py' => '@csrf_exempt']));
    }

    public function test_each_line_is_reported_once_per_rule_family(): void
    {
        $ids = $this->scan('python', ['app/jobs.py' => 'subprocess.run(cmd, shell=True)']);
        $this->assertContains('checkmycode.python.shell-true', $ids);
        $this->assertNotContains('checkmycode.python.subprocess-usage', $ids);

        $ids = $this->scan('go', ['cmd/run.go' => 'db.Query(fmt.Sprintf("SELECT * FROM t WHERE id = %s", id))']);
        $this->assertSame(['checkmycode.go.sql-string-format'], $ids);
    }
}
