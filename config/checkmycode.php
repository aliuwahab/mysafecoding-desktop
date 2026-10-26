<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Run mode
    |--------------------------------------------------------------------------
    |
    | "desktop" runs inside NativePHP: native folder picker, SQLite scan
    | history, scans run as a child process with full git history.
    |
    | "web" is the hosted, anonymous mode: the browser uploads the selected
    | folder, the scan runs in a throwaway workspace that is deleted as soon
    | as the scan finishes, and results live only in a short-lived cache entry.
    | No accounts, no database records, no stored code.
    |
    */
    'mode' => env('CHECKMYCODE_MODE', 'desktop'),

    'default_exclusions' => [
        'vendor',
        'node_modules',
        'storage',
        'bootstrap/cache',
        'public/build',
        'dist',
        'build',
        'coverage',
        '.next',
        '.nuxt',
        '.venv',
        '__pycache__',
        'target',
    ],

    'binaries' => [
        'trivy' => env('CHECKMYCODE_TRIVY_BINARY', 'trivy'),
        'semgrep' => env('CHECKMYCODE_SEMGREP_BINARY', 'semgrep'),
        'gitleaks' => env('CHECKMYCODE_GITLEAKS_BINARY', 'gitleaks'),
    ],

    'scanner_timeout_seconds' => (int) env('CHECKMYCODE_SCANNER_TIMEOUT_SECONDS', 600),

    /*
    | Lifecycle (end-of-life) checks call https://endoflife.date. Disable for
    | fully offline scans.
    */
    'endoflife' => [
        'enabled' => filter_var(env('CHECKMYCODE_ENDOFLIFE_ENABLED', true), FILTER_VALIDATE_BOOL),
        'base_url' => env('ENDOFLIFE_BASE_URL', 'https://endoflife.date'),
        'timeout_seconds' => (int) env('ENDOFLIFE_TIMEOUT_SECONDS', 10),
        'cache_seconds' => (int) env('ENDOFLIFE_CACHE_SECONDS', 86400),
        'warn_days' => (int) env('ENDOFLIFE_WARN_DAYS', 90),
    ],

    'license_policy' => [
        'flagged_licenses' => array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', (string) env('CHECKMYCODE_FLAGGED_LICENSES', 'AGPL-1.0-only,AGPL-1.0-or-later,AGPL-3.0,AGPL-3.0-only,AGPL-3.0-or-later,SSPL-1.0,GPL-3.0,GPL-3.0-only,GPL-3.0-or-later,BUSL-1.1')),
        ))),
    ],

    /*
    | Hosted web mode limits. Uploads arrive as a single zip built in the
    | browser from the folder the user picked.
    */
    'web' => [
        'max_upload_mb' => (int) env('CHECKMYCODE_WEB_MAX_UPLOAD_MB', 50),
        'max_uncompressed_mb' => (int) env('CHECKMYCODE_WEB_MAX_UNCOMPRESSED_MB', 200),
        'max_files' => (int) env('CHECKMYCODE_WEB_MAX_FILES', 20000),
        'results_ttl_minutes' => (int) env('CHECKMYCODE_WEB_RESULTS_TTL_MINUTES', 60),
        'scans_per_hour' => (int) env('CHECKMYCODE_WEB_SCANS_PER_HOUR', 10),
        // Keep this outside any git repository: Semgrep honours a parent repo's
        // .gitignore and would silently skip the uploaded files.
        'workspace_path' => env('CHECKMYCODE_WEB_WORKSPACE', sys_get_temp_dir().'/checkmycode-web-scans'),
        'history_limit' => 20,
    ],

    'docker_image' => env('CHECKMYCODE_DOCKER_IMAGE', 'ghcr.io/aliuwahab/checkmycode:latest'),

    // The "CI YAML" button generates a workflow that pulls docker_image. Only
    // enable it once that image is publicly pullable, or the workflow fails.
    'ci_workflow' => filter_var(env('CHECKMYCODE_CI_WORKFLOW', false), FILTER_VALIDATE_BOOL),

    // Shown in the sidebar: CheckMyCode is free and open source.
    'github_url' => env('CHECKMYCODE_GITHUB_URL', 'https://github.com/aliuwahab'),
    'sponsor_url' => env('CHECKMYCODE_SPONSOR_URL', 'https://github.com/sponsors/aliuwahab'),
];
