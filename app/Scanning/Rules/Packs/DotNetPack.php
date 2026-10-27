<?php

namespace App\Scanning\Rules\Packs;

/**
 * C# / ASP.NET Core, including Razor views and appsettings files.
 */
class DotNetPack extends RulePack
{
    protected const VIEWS = ['cshtml', 'razor'];

    public function appliesTo(string $projectType): bool
    {
        return $projectType === 'dotnet';
    }

    public function extensions(): array
    {
        return ['cs'];
    }

    public function rules(): array
    {
        return [
            [
                'pattern' => '/(FromSqlRaw|ExecuteSqlRaw|ExecuteSqlRawAsync|SqlQueryRaw)\s*\(\s*(\$"[^"]*\{|"[^"]*"\s*\+)|new\s+SqlCommand\s*\(\s*(\$"[^"]*\{|"[^"]*"\s*\+)|\.CommandText\s*=\s*(\$"[^"]*\{|"[^"]*"\s*\+)|\.Query(<[^>]+>)?(Async)?\s*\(\s*(\$"[^"]*\{|"[^"]*"\s*\+)/',
                'rule_id' => 'checkmycode.dotnet.sql-injection',
                'title' => 'SQL query built with interpolation or concatenation',
                'severity' => 'critical',
                'category' => 'injection',
                'why' => 'Interpolating values into raw SQL bypasses parameterization. Note FromSqlRaw($"...") is NOT parameterized.',
                'fix' => 'Use FromSql($"...")/FromSqlInterpolated (which parameterize), SqlParameter, or Dapper parameters.',
            ],
            [
                'pattern' => '/new\s+(BinaryFormatter|NetDataContractSerializer|SoapFormatter|LosFormatter|ObjectStateFormatter)\s*\(|TypeNameHandling\s*=\s*TypeNameHandling\.(All|Auto|Objects|Arrays)|JavaScriptSerializer\s*\(\s*new\s+SimpleTypeResolver/',
                'rule_id' => 'checkmycode.dotnet.unsafe-deserialization',
                'title' => 'Unsafe .NET deserialization',
                'severity' => 'critical',
                'category' => 'deserialization',
                'why' => 'BinaryFormatter and Json.NET TypeNameHandling let a payload choose the types to create, a well-known code-execution path.',
                'fix' => 'Use System.Text.Json with concrete types and keep TypeNameHandling.None.',
            ],
            [
                'pattern' => '/Html\.Raw\s*\(|new\s+HtmlString\s*\(|\(MarkupString\)/',
                'extensions' => [...self::VIEWS, 'cs'],
                'rule_id' => 'checkmycode.dotnet.html-raw',
                'title' => 'Raw HTML output (escaping disabled)',
                'severity' => 'high',
                'category' => 'xss',
                'why' => 'Html.Raw, HtmlString and MarkupString skip Razor\'s encoding; user content there becomes XSS.',
                'fix' => 'Render the value normally (@Model.Value) or sanitize it with HtmlSanitizer first.',
            ],
            [
                'pattern' => '/\[IgnoreAntiforgeryToken\]|\.DisableAntiforgery\s*\(\s*\)/',
                'rule_id' => 'checkmycode.dotnet.csrf-disabled',
                'title' => 'Antiforgery (CSRF) validation disabled',
                'severity' => 'medium',
                'category' => 'csrf',
                'why' => 'Cookie-authenticated endpoints without antiforgery tokens can be triggered by any site the user visits.',
                'fix' => 'Remove the opt-out, or add [ValidateAntiForgeryToken]/AutoValidateAntiforgeryTokenAttribute globally.',
            ],
            [
                'pattern' => '/\[HttpPost[^\]]*\]/',
                'when_file' => '/:\s*Controller\b/',
                'unless_file' => '/AutoValidateAntiforgeryToken|ValidateAntiForgeryToken|\[ApiController\]/',
                'rule_id' => 'checkmycode.dotnet.post-without-antiforgery',
                'title' => 'MVC POST action without antiforgery validation',
                'severity' => 'medium',
                'category' => 'csrf',
                'why' => 'MVC controllers do not validate antiforgery tokens unless told to, so POST actions are CSRF-able.',
                'fix' => 'Add [ValidateAntiForgeryToken] to the action, or register AutoValidateAntiforgeryTokenAttribute as a global filter.',
            ],
            [
                'pattern' => '/\.AllowAnyOrigin\s*\(\s*\)|SetIsOriginAllowed\s*\(\s*_?\w*\s*=>\s*true\s*\)|WithOrigins\s*\(\s*"\*"\s*\)/',
                'rule_id' => 'checkmycode.dotnet.cors-wildcard',
                'title' => 'CORS allows every origin',
                'severity' => 'medium',
                'category' => 'cors',
                'why' => 'Any website can call your API from a visitor\'s browser; with credentials it can act as the user.',
                'fix' => 'Use WithOrigins("https://app.example") with the real frontend origins.',
            ],
            [
                'pattern' => '/\b(MD5|SHA1)\.(Create|HashData)\s*\(|new\s+(MD5|SHA1)(CryptoServiceProvider|Managed)\s*\(/',
                'rule_id' => 'checkmycode.dotnet.weak-hash',
                'title' => 'Weak hashing algorithm (MD5/SHA-1)',
                'severity' => 'medium',
                'category' => 'cryptography',
                'why' => 'MD5 and SHA-1 are broken for security use and cheap to brute-force.',
                'fix' => 'Use SHA256.HashData; for passwords use ASP.NET Core Identity\'s PasswordHasher or Rfc2898DeriveBytes.Pbkdf2.',
            ],
            [
                'pattern' => '/\b(DES|TripleDES|RC2)\.Create\s*\(|new\s+(DES|TripleDES|RC2)CryptoServiceProvider\s*\(|CipherMode\.ECB/',
                'rule_id' => 'checkmycode.dotnet.weak-cipher',
                'title' => 'Weak cipher or ECB mode',
                'severity' => 'high',
                'category' => 'cryptography',
                'why' => 'DES, 3DES and RC2 are obsolete, and ECB mode leaks patterns in the plaintext.',
                'fix' => 'Use AesGcm with a random nonce per message.',
            ],
            [
                'pattern' => '/ServerCertificateCustomValidationCallback\s*=|DangerousAcceptAnyServerCertificateValidator|ServerCertificateValidationCallback\s*\+?=.*=>\s*true|RemoteCertificateValidationCallback.*=>\s*true/',
                'rule_id' => 'checkmycode.dotnet.tls-verification-disabled',
                'title' => 'TLS certificate validation overridden',
                'severity' => 'high',
                'category' => 'transport-security',
                'why' => 'Accepting any certificate lets anyone on the network path intercept HTTPS traffic.',
                'fix' => 'Remove the callback and trust the right CA in the OS or container trust store.',
            ],
            [
                'pattern' => '/(ValidateIssuerSigningKey|ValidateLifetime|RequireSignedTokens|RequireExpirationTime|ValidateAudience|ValidateIssuer)\s*=\s*false/',
                'rule_id' => 'checkmycode.dotnet.jwt-validation-disabled',
                'title' => 'JWT validation check turned off',
                'severity' => 'high',
                'category' => 'authentication',
                'why' => 'Turning off signature, lifetime, issuer or audience checks lets forged, expired or foreign tokens through.',
                'fix' => 'Keep these TokenValidationParameters set to true and configure the expected issuer and audience.',
            ],
            [
                'pattern' => '/\bRedirect\s*\(\s*(returnUrl|returnURL|url|redirectUrl|next|Request\.Query)/',
                'unless_nearby' => '/IsLocalUrl|LocalRedirect/',
                'window' => 6,
                'rule_id' => 'checkmycode.dotnet.open-redirect',
                'title' => 'Redirect to an unvalidated URL',
                'severity' => 'high',
                'category' => 'redirect',
                'why' => 'Redirecting to a user-supplied URL lets attackers bounce victims to a phishing page.',
                'fix' => 'Use LocalRedirect(returnUrl), or check Url.IsLocalUrl(returnUrl) before calling Redirect().',
            ],
            [
                'pattern' => '/(File\.(ReadAllText|ReadAllBytes|OpenRead|Open|WriteAllBytes|Create)|PhysicalFile|new\s+FileStream|Path\.Combine)\s*\(.*(\.FileName\b|Request\.(Query|Form)|\[FromQuery\])/',
                'unless' => '/Path\.GetFileName\s*\(|GetRandomFileName/',
                'rule_id' => 'checkmycode.dotnet.path-traversal',
                'title' => 'File path built from request input',
                'severity' => 'high',
                'category' => 'path-traversal',
                'why' => 'IFormFile.FileName and query values can contain ../ and escape the intended folder.',
                'fix' => 'Use Path.GetRandomFileName() for stored uploads, or Path.GetFileName() and verify the full path stays under the root.',
            ],
            [
                'pattern' => '/Process\.Start\s*\(|new\s+ProcessStartInfo\s*\(/',
                'rule_id' => 'checkmycode.dotnet.process-start',
                'title' => 'Operating system process started',
                'severity' => 'medium',
                'category' => 'command-injection',
                'why' => 'Starting processes with user-controlled arguments (especially via cmd.exe /c or bash -c) allows command injection.',
                'fix' => 'Use ProcessStartInfo.ArgumentList with UseShellExecute = false and validate every argument.',
            ],
            [
                'pattern' => '/\.UseDeveloperExceptionPage\s*\(\s*\)/',
                'unless_nearby' => '/IsDevelopment\s*\(/',
                'window' => 4,
                'rule_id' => 'checkmycode.dotnet.developer-exception-page',
                'title' => 'Developer exception page enabled outside development',
                'severity' => 'high',
                'category' => 'configuration',
                'why' => 'The developer exception page shows stack traces, source and request details to every visitor.',
                'fix' => 'Wrap it in if (app.Environment.IsDevelopment()) and use UseExceptionHandler in production.',
            ],
            [
                'pattern' => '/HttpOnly\s*=\s*false|\.Secure\s*=\s*false|CookieSecurePolicy\.None|SecurePolicy\s*=\s*CookieSecurePolicy\.SameAsRequest/',
                'rule_id' => 'checkmycode.dotnet.insecure-cookie',
                'title' => 'Cookie set without HttpOnly or Secure',
                'severity' => 'medium',
                'category' => 'session',
                'why' => 'Without HttpOnly, injected scripts can read the cookie; without Secure, it is sent over plain HTTP.',
                'fix' => 'Set HttpOnly = true and SecurePolicy = CookieSecurePolicy.Always.',
            ],
            [
                'pattern' => '/\[HttpPost[^\]]*\(\s*"[^"]*(login|signin|register|signup|password|reset|otp)[^"]*"/i',
                'unless_file' => '/EnableRateLimiting|RateLimiter|AspNetCoreRateLimit/',
                'rule_id' => 'checkmycode.dotnet.auth-missing-rate-limit',
                'title' => 'Authentication endpoint without rate limiting',
                'severity' => 'medium',
                'category' => 'brute-force',
                'why' => 'Login, signup and password-reset endpoints without a rate limit can be brute-forced.',
                'fix' => 'Add the built-in rate limiter (builder.Services.AddRateLimiter + [EnableRateLimiting("auth")]).',
            ],
            [
                'pattern' => '/\b(Password|Pwd)\s*=\s*(?![$\{;"])[^;"]+/i',
                'extensions' => ['json'],
                'paths' => '#(^|/)appsettings[^/]*\.json$#',
                'skip' => '#appsettings\.(Development|Local|Test)\.json$#',
                'rule_id' => 'checkmycode.dotnet.connection-string-password',
                'title' => 'Database password in appsettings.json',
                'severity' => 'high',
                'category' => 'secret',
                'why' => 'Connection strings with passwords in committed appsettings files are visible to anyone with repository access.',
                'fix' => 'Use user-secrets in development and environment variables or Key Vault in production.',
            ],
        ];
    }
}
