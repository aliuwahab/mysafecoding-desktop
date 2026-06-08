# Contributing to MySafeCoding Desktop

Thanks for helping improve MySafeCoding Desktop. This project is a local-first NativePHP app with bundled scanner scripts for dependency, static analysis, secret, environment, framework-rule, and quality checks.

## Development Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
```

Start the desktop app:

```bash
./bin/native-run
```

Run checks before opening a PR:

```bash
./vendor/bin/pint --dirty
bash -n resources/cli/mysafecoding-scan.sh
bash -n resources/cli/mysafecoding-setup.sh
bash -n resources/cli/mysafecoding-inspect.sh
php artisan test
npm run build
```

## Contribution Areas

- framework detection for additional popular stacks
- scanner result parsing and exports
- SARIF, JSON, and CI workflow improvements
- custom framework rule packs
- setup and dependency health checks
- reports, baselines, and remediation guidance

## Adding Framework Detection

Project type detection exists in two places:

- `resources/cli/mysafecoding-scan.sh` writes `project-type.txt`
- `app/Services/ScannerService.php` has a PHP fallback when scan output is missing

Update both paths and add coverage in `tests/Unit/ProjectTypeDetectionTest.php`.

## Adding Export Formats

Keep export transformation logic in an `App\Services` class so it can be unit tested without the NativePHP dialog APIs. Wire UI actions through `app/Livewire/Scanner.php`.

## Security Reports

Do not open public issues for vulnerabilities in MySafeCoding itself. Use the process in `SECURITY.md`.
