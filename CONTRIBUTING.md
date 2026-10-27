# Contributing to CheckMyCode

Thanks for helping improve CheckMyCode. One scan engine (`app/Scanning/ScanEngine.php`) powers the NativePHP desktop app, the hosted web scanner and the CLI, so a change to scanning lands in all three.

## Development Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
```

Start the desktop app, the web mode, or run the CLI:

```bash
./bin/native-run
composer run web
./bin/checkmycode scan /path/to/project
```

Run checks before opening a PR:

```bash
./vendor/bin/pint --dirty
bash -n resources/cli/checkmycode-setup.sh
for rules in resources/scanners/*.yml; do semgrep scan --validate --config "$rules" --metrics off; done
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

Detection lives in `app/Scanning/ProjectDetector.php`. Map the new type to Semgrep packs in `app/Scanning/SemgrepRulesets.php` and add coverage in `tests/Unit/ProjectTypeDetectionTest.php`.

## Adding Rules

- **Semgrep rules** (preferred, AST-aware) go in `resources/scanners/<language>.yml` with ids prefixed `checkmycode.`. Validate them with `semgrep scan --validate`, and check them against both a vulnerable and a safe sample. Remember that `patterns:` means AND; use `pattern-either:` for alternatives.
- **Pattern rules** (regex, any file type) go in the language's pack in `app/Scanning/Rules/Packs/`. The keys a rule can use (`frameworks`, `paths`, `unless`, `unless_nearby`, `when_file`, …) are documented in `RulePack.php`. Add a vulnerable and a safe sample to `tests/Unit/RulePackTest.php`.
- **Laravel rules** are classes in `app/Services/Scanners/Rules/`, registered in `LaravelRuleEngine`.

## Web Mode Rules

Web mode must never keep user code. Anything new that touches uploads has to work inside `RunWebScan`'s workspace, which is deleted in a `finally` block. Results must go through `EphemeralScanStore` (cache plus session) and never into the database.

## Adding Export Formats

Keep export transformation logic in an `App\Services` class so it can be unit tested without the NativePHP dialog APIs. Wire UI actions through `app/Livewire/Scanner.php`.

## Security Reports

Do not open public issues for vulnerabilities in CheckMyCode itself. Use the process in `SECURITY.md`.
