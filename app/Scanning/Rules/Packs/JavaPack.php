<?php

namespace App\Scanning\Rules\Packs;

use App\Scanning\ProjectDetector;

/**
 * Java and Kotlin, including Spring Boot configuration files.
 */
class JavaPack extends RulePack
{
    protected const CONFIG = ['properties', 'yml', 'yaml'];

    protected const CONFIG_PATHS = '#(^|/)(application|bootstrap)[^/]*\.(properties|ya?ml)$#';

    public function appliesTo(string $projectType): bool
    {
        return ProjectDetector::isJava($projectType);
    }

    public function extensions(): array
    {
        return ['java', 'kt', 'kts', 'groovy'];
    }

    public function rules(): array
    {
        return [
            [
                'pattern' => '/\.(executeQuery|executeUpdate|execute|prepareStatement|createQuery|createNativeQuery|queryForObject|queryForList|query|update|batchUpdate)\s*\(\s*"[^"]*"\s*\+|\.(executeQuery|executeUpdate|execute|createQuery|createNativeQuery|query)\s*\(\s*"[^"]*\$\{?\w/',
                'rule_id' => 'checkmycode.java.sql-injection',
                'title' => 'SQL/JPQL query built with string concatenation',
                'severity' => 'critical',
                'category' => 'injection',
                'why' => 'Concatenating values into SQL or JPQL bypasses parameter binding and allows injection.',
                'fix' => 'Use PreparedStatement placeholders (?), named parameters (:id) with setParameter, or Spring Data query methods.',
            ],
            [
                'pattern' => '/Runtime\.getRuntime\(\)\.exec\s*\(|new\s+ProcessBuilder\s*\(|ProcessBuilder\s*\(/',
                'rule_id' => 'checkmycode.java.command-execution',
                'title' => 'Operating system command executed',
                'severity' => 'high',
                'category' => 'command-injection',
                'why' => 'Commands built from user input, especially via "sh -c" or a single string, allow command injection.',
                'fix' => 'Pass a fixed program and an argument array, never a shell string, and validate every argument.',
            ],
            [
                'pattern' => '/new\s+ObjectInputStream\s*\(|\.readObject\s*\(\s*\)|new\s+XMLDecoder\s*\(|XStream\s*\(\s*\)|enableDefaultTyping\s*\(|activateDefaultTyping\s*\(/',
                'rule_id' => 'checkmycode.java.unsafe-deserialization',
                'title' => 'Unsafe Java deserialization',
                'severity' => 'critical',
                'category' => 'deserialization',
                'why' => 'Java native serialization, XMLDecoder, XStream and Jackson default typing can execute code via gadget chains.',
                'fix' => 'Use JSON with explicit DTO types; if ObjectInputStream is unavoidable, set an ObjectInputFilter allowlist.',
            ],
            [
                'pattern' => '/(DocumentBuilderFactory|SAXParserFactory|XMLInputFactory|TransformerFactory|SchemaFactory)\.newInstance\s*\(|new\s+SAXReader\s*\(|new\s+SAXBuilder\s*\(/',
                'unless_nearby' => '/disallow-doctype-decl|FEATURE_SECURE_PROCESSING|ACCESS_EXTERNAL_DTD|SUPPORT_DTD|external-general-entities|setExpandEntityReferences\s*\(\s*false/',
                'window' => 15,
                'rule_id' => 'checkmycode.java.xxe',
                'title' => 'XML parser created without XXE protection',
                'severity' => 'high',
                'category' => 'injection',
                'why' => 'Default Java XML parsers resolve external entities, letting attackers read local files or reach internal hosts (XXE).',
                'fix' => 'Call factory.setFeature("http://apache.org/xml/features/disallow-doctype-decl", true) before parsing.',
            ],
            [
                'pattern' => '/MessageDigest\.getInstance\s*\(\s*"(MD5|MD2|SHA-?1)"|DigestUtils\.(md5|sha1)(Hex)?\s*\(/i',
                'rule_id' => 'checkmycode.java.weak-hash',
                'title' => 'Weak hashing algorithm (MD5/SHA-1)',
                'severity' => 'medium',
                'category' => 'cryptography',
                'why' => 'MD5 and SHA-1 are broken for security use and cheap to brute-force.',
                'fix' => 'Use SHA-256 or stronger; for passwords use Spring Security\'s BCryptPasswordEncoder or Argon2PasswordEncoder.',
            ],
            [
                'pattern' => '/Cipher\.getInstance\s*\(\s*"(DES|DESede|RC4|RC2|Blowfish|AES)(\/ECB[^"]*)?"\s*\)|Cipher\.getInstance\s*\(\s*"[^"]*\/ECB\//',
                'rule_id' => 'checkmycode.java.weak-cipher',
                'title' => 'Weak cipher or ECB mode',
                'severity' => 'high',
                'category' => 'cryptography',
                'why' => 'DES/RC4 are broken, and "AES" alone defaults to ECB, which leaks patterns in the plaintext.',
                'fix' => 'Use Cipher.getInstance("AES/GCM/NoPadding") with a random 12-byte IV per message.',
            ],
            [
                'pattern' => '/NoopHostnameVerifier|ALLOW_ALL_HOSTNAME_VERIFIER|TrustAllStrategy|TrustSelfSignedStrategy|InsecureTrustManagerFactory|setHostnameVerifier\s*\(\s*\(?[^)]*\)?\s*->\s*true/',
                'rule_id' => 'checkmycode.java.tls-verification-disabled',
                'title' => 'TLS certificate or hostname verification disabled',
                'severity' => 'high',
                'category' => 'transport-security',
                'why' => 'Trust-all managers and no-op hostname verifiers let anyone on the network path intercept HTTPS traffic.',
                'fix' => 'Use the default trust manager and hostname verifier; add private CAs to a truststore instead.',
            ],
            [
                'pattern' => '/void\s+checkServerTrusted\s*\([^)]*\)\s*(throws\s+[\w.,\s]+)?\{\s*\}/',
                'rule_id' => 'checkmycode.java.trust-all-certificates',
                'title' => 'X509TrustManager accepts every certificate',
                'severity' => 'critical',
                'category' => 'transport-security',
                'why' => 'An empty checkServerTrusted accepts any certificate, so HTTPS gives no protection.',
                'fix' => 'Delete the custom TrustManager and rely on the JVM truststore.',
            ],
            [
                'pattern' => '/\b\w*(token|secret|password|otp|nonce|salt|key|code)\w*\s*=.*new\s+(java\.util\.)?Random\s*\(|Math\.random\s*\(\).*\b(token|secret|password|otp|code)/i',
                'rule_id' => 'checkmycode.java.insecure-random',
                'title' => 'Security value generated with java.util.Random',
                'severity' => 'medium',
                'category' => 'cryptography',
                'why' => 'java.util.Random and Math.random are predictable; tokens generated with them can be guessed.',
                'fix' => 'Use java.security.SecureRandom.',
            ],
            [
                'pattern' => '/new\s+File(InputStream|Reader)?\s*\(\s*[^)]*(request\.getParameter|@RequestParam|@PathVariable|getOriginalFilename\s*\()|Paths?\.(get|of)\s*\([^)]*(request\.getParameter|getOriginalFilename\s*\()/',
                'unless' => '/FilenameUtils\.getName|normalize\(\)\.startsWith/',
                'rule_id' => 'checkmycode.java.path-traversal',
                'title' => 'File path built from request input',
                'severity' => 'high',
                'category' => 'path-traversal',
                'why' => 'Request-controlled names, including MultipartFile.getOriginalFilename(), can contain ../ and escape the target folder.',
                'fix' => 'Generate the filename server-side, or normalize the path and check it starts with the intended root.',
            ],
            [
                'pattern' => '/"redirect:"\s*\+|sendRedirect\s*\(\s*request\.getParameter|new\s+RedirectView\s*\(\s*\w+\s*\)/',
                'rule_id' => 'checkmycode.java.open-redirect',
                'title' => 'Redirect target built from input',
                'severity' => 'high',
                'category' => 'redirect',
                'why' => 'Redirecting to a user-supplied URL lets attackers bounce victims to a phishing page.',
                'fix' => 'Redirect only to fixed routes or validate the target against an allowlist of hosts.',
            ],
            [
                'pattern' => '/\.parseExpression\s*\(|SpelExpressionParser\s*\(\s*\).*\(\s*\w+\s*\)/',
                'when_file' => '/@(RequestParam|PathVariable|RequestBody)|getParameter\(/',
                'rule_id' => 'checkmycode.java.spel-injection',
                'title' => 'SpEL expression parsed at runtime',
                'severity' => 'high',
                'category' => 'code-execution',
                'why' => 'Parsing a Spring Expression built from user input allows arbitrary method calls (remote code execution).',
                'fix' => 'Never parse user-controlled expressions; if needed use SimpleEvaluationContext.',
            ],
            [
                'pattern' => '/setHttpOnly\s*\(\s*false\s*\)|setSecure\s*\(\s*false\s*\)/',
                'rule_id' => 'checkmycode.java.insecure-cookie',
                'title' => 'Cookie set without HttpOnly or Secure',
                'severity' => 'medium',
                'category' => 'session',
                'why' => 'Without HttpOnly, injected scripts can read the cookie; without Secure, it is sent over plain HTTP.',
                'fix' => 'Call setHttpOnly(true) and setSecure(true), or use ResponseCookie with .httpOnly(true).secure(true).',
            ],

            // Spring
            [
                'pattern' => '/csrf\s*\(\s*\)\s*\.\s*disable\s*\(|csrf\s*\(\s*(AbstractHttpConfigurer::disable|\w+\s*->\s*\w+\.disable\(\))|csrf\s*\{\s*disable\s*\(\s*\)/',
                'rule_id' => 'checkmycode.spring.csrf-disabled',
                'title' => 'Spring Security CSRF protection disabled',
                'severity' => 'medium',
                'category' => 'csrf',
                'why' => 'With CSRF disabled, any site can submit state-changing requests using a logged-in user\'s session cookie.',
                'fix' => 'Keep CSRF on for cookie/session auth; disable it only for stateless token-authenticated APIs.',
            ],
            [
                'pattern' => '/anyRequest\s*\(\s*\)\s*\.\s*permitAll\s*\(|\/\*\*["\']\s*\)\s*\.\s*permitAll\s*\(|requestMatchers\s*\(\s*"\/\*\*"\s*\)\.permitAll/',
                'rule_id' => 'checkmycode.spring.permit-all',
                'title' => 'Every request permitted without authentication',
                'severity' => 'high',
                'category' => 'authorization',
                'why' => 'anyRequest().permitAll() leaves every endpoint, including admin ones, open to anonymous users.',
                'fix' => 'Use anyRequest().authenticated() and open only the specific public paths.',
            ],
            [
                'pattern' => '/@CrossOrigin\s*(\(\s*\)|$|\(\s*(origins\s*=\s*)?"\*")|allowedOrigins\s*\(\s*"\*"|allowedOriginPatterns\s*\(\s*"\*"|setAllowedOrigins\s*\(.*"\*"|addAllowedOrigin\s*\(\s*"\*"/',
                'rule_id' => 'checkmycode.spring.cors-wildcard',
                'title' => 'CORS allows every origin',
                'severity' => 'medium',
                'category' => 'cors',
                'why' => 'Any website can call your API from a visitor\'s browser and read the responses.',
                'fix' => 'List the real frontend origins in @CrossOrigin(origins = ...) or the CorsConfiguration.',
            ],
            [
                'pattern' => '/NoOpPasswordEncoder|\{noop\}/',
                'rule_id' => 'checkmycode.spring.plaintext-passwords',
                'title' => 'Passwords stored or compared in plain text',
                'severity' => 'critical',
                'category' => 'authentication',
                'why' => 'NoOpPasswordEncoder keeps passwords unhashed, so a database leak exposes every password.',
                'fix' => 'Use PasswordEncoderFactories.createDelegatingPasswordEncoder() (bcrypt by default).',
            ],
            [
                'pattern' => '/^\s*management\.endpoints\.web\.exposure\.include\s*[=:]\s*["\']?\*|^\s*include\s*:\s*["\']\*["\']/',
                'extensions' => self::CONFIG,
                'paths' => self::CONFIG_PATHS,
                'rule_id' => 'checkmycode.spring.actuator-exposed',
                'title' => 'All Spring Boot Actuator endpoints exposed',
                'severity' => 'high',
                'category' => 'information-disclosure',
                'why' => 'Exposing every actuator endpoint publishes env vars, heap dumps, config and sometimes shutdown.',
                'fix' => 'Expose only health and info, and secure the rest behind authentication on a management port.',
            ],
            [
                'pattern' => '/^\s*(server\.error\.include-(stacktrace|message|exception)\s*[=:]\s*["\']?(always|true)|debug\s*[=:]\s*true|spring\.h2\.console\.enabled\s*[=:]\s*true)/',
                'extensions' => self::CONFIG,
                'paths' => self::CONFIG_PATHS,
                'rule_id' => 'checkmycode.spring.debug-config',
                'title' => 'Debug output or H2 console enabled',
                'severity' => 'medium',
                'category' => 'configuration',
                'why' => 'Stack traces in error responses and the H2 web console leak internals and can allow database access.',
                'fix' => 'Turn these off in the production profile (application-prod).',
            ],
            [
                'pattern' => '/^\s*(spring\.datasource\.password|spring\.mail\.password|spring\.security\.user\.password|password|secret|client-secret|jwt\.secret)\s*[=:]\s*["\']?(?!\$\{)[^\s"\'#]{4,}/i',
                'extensions' => self::CONFIG,
                'paths' => self::CONFIG_PATHS,
                'rule_id' => 'checkmycode.spring.hardcoded-secret',
                'title' => 'Secret hardcoded in Spring configuration',
                'severity' => 'high',
                'category' => 'secret',
                'why' => 'Passwords committed in application.properties/yml are visible to anyone with repository access.',
                'fix' => 'Reference an environment variable instead: spring.datasource.password=${DB_PASSWORD}.',
            ],
            [
                'pattern' => '/^\s*(server\.servlet\.session\.cookie\.(secure|http-only)|secure|http-only)\s*[=:]\s*false/',
                'extensions' => self::CONFIG,
                'paths' => self::CONFIG_PATHS,
                'rule_id' => 'checkmycode.spring.insecure-session-cookie',
                'title' => 'Session cookie without Secure or HttpOnly',
                'severity' => 'medium',
                'category' => 'session',
                'why' => 'Session cookies without these flags can be stolen over HTTP or read by injected scripts.',
                'fix' => 'Set server.servlet.session.cookie.secure=true and http-only=true.',
            ],
            [
                'pattern' => '/@PostMapping\s*\(\s*(value\s*=\s*|path\s*=\s*)?"[^"]*(login|signin|register|signup|password|reset|otp)[^"]*"/i',
                'unless_file' => '/RateLimiter|@RateLimit|Bucket4j|bucket4j|Bucket\b|resilience4j/',
                'rule_id' => 'checkmycode.spring.auth-missing-rate-limit',
                'title' => 'Authentication endpoint without rate limiting',
                'severity' => 'medium',
                'category' => 'brute-force',
                'why' => 'Login, signup and password-reset endpoints without a rate limit can be brute-forced.',
                'fix' => 'Add rate limiting (Bucket4j, Resilience4j @RateLimiter, or a gateway limit) to these endpoints.',
            ],
            [
                'pattern' => '/@RequestBody\s+(final\s+)?(\w+)\s+\w+/',
                'when_file' => '/@(Post|Put|Patch)Mapping/',
                'unless' => '/@RequestBody\s+(final\s+)?(\w*(Dto|DTO|Request|Command|Form|Input|Payload|Params|Body)|Map|String|List|JsonNode)\b/',
                'rule_id' => 'checkmycode.spring.entity-as-request-body',
                'title' => 'Request body bound directly to a domain object',
                'severity' => 'low',
                'category' => 'mass-assignment',
                'why' => 'Binding JSON straight onto an entity lets clients set any field, such as role or ownerId.',
                'fix' => 'Bind to a dedicated request DTO with only the editable fields, validate it, then map to the entity.',
            ],
        ];
    }
}
