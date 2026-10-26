# CheckMyCode

[![Sponsor](https://img.shields.io/badge/Sponsor-%E2%9D%A4-ff6b9a?logo=githubsponsors&logoColor=white)](https://github.com/sponsors/aliuwahab)
[![GitHub followers](https://img.shields.io/github/followers/aliuwahab?style=social)](https://github.com/aliuwahab)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

**Free and open source.** Scan in the browser at **[checkmycode.dev](https://checkmycode.dev)** with no account, or run it locally with the desktop app, the CLI or Docker.

Open-source security scanner for the projects developers ship every day. Point it at a folder (or a single file) and it finds:

| Engine | What it checks | Powered by |
|---|---|---|
| **Dependencies** | Every lockfile against two public vulnerability databases (Trivy's NVD/GitHub Advisories DB and OSV.dev, checked offline), merged without duplicates: Composer, npm/yarn/pnpm, pip/Poetry/uv, Bundler, Go modules, Cargo, Maven/Gradle, NuGet, Mix and more | Trivy + OSV-Scanner |
| **Code** | Language-aware SAST with the Semgrep registry packs for the detected stack and framework (Django, Flask, FastAPI, React, Node/Express, Kotlin, …) plus OWASP Top 10 rules, and CheckMyCode's own rules in `resources/scanners/` | Semgrep |
| **Secrets** | API keys, tokens and passwords in files and (desktop/CLI) full git history | Gitleaks |
| **Rules** | CheckMyCode pattern rules for PHP, Python/Django, JS/TS, Ruby, Go, plus the 20-rule Laravel pack | built in |
| **Config / IaC** | Dockerfile, Kubernetes, Terraform, CloudFormation and Helm misconfiguration | Trivy |
| **Workflows** | GitHub Actions security: script injection, dangerous triggers (`pull_request_target`), excessive permissions, unpinned actions | zizmor |
| **Lifecycle** | Runtimes and frameworks that are out of (or nearly out of) security support | endoflife.date |
| **.env audit** | Debug mode, weak keys, insecure drivers | built in |
| **Licenses** | Copyleft and restricted licenses (reported, not graded) | Trivy |
| **Quality** | Best-practice suggestions (reported, not graded) | Semgrep |

Supported stacks: PHP (Laravel, Symfony), JavaScript/TypeScript (Next.js, Nuxt, React, Vue, Svelte, Angular, NestJS, Express), Python (Django, Flask, FastAPI), Ruby (Rails), Go, Rust, Elixir (Phoenix), Java (Spring) and .NET.

## One engine, three ways to run it

Desktop, web and CLI all run the same pipeline (`app/Scanning/ScanEngine.php`), so results match wherever you scan.

### 1. Web: hosted, anonymous, nothing stored

Pick a folder or file in the browser (or drag it in) and press **Scan**.

- Excluded folders (`node_modules`, `vendor`, …) are filtered **in the browser** and never uploaded.
- The rest is zipped client-side, uploaded, scanned in a temporary workspace and **deleted as soon as the scan finishes**.
- There are no accounts and no database records. Results live in a short-lived cache (60 minutes by default), visible only to the browser session that started the scan. Secret values are redacted.
- Git history is not uploaded, so secrets are checked in current files only. Use desktop or CLI for history.

Self-host it:

```bash
docker compose up -d          # → http://localhost:8080
# or
docker run --rm -p 8080:8080 ghcr.io/aliuwahab/checkmycode web
```

Behind nginx, add `error_page 502 504 /errors/50x.html;` so visitors see a branded page if PHP is down; every other error page is rendered by the app.

Limits are set with environment variables (see `.env.example`): upload size, file count, scans per hour per IP, result TTL, and concurrent workers (`CHECKMYCODE_WORKERS`).

### 2. Desktop: NativePHP app

Native folder/file picker, SQLite scan history, compare with previous scans, ignore findings, and full git-history secret scanning.

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate && php artisan migrate --force
./bin/native-run
```

On first launch the app checks for `trivy`, `semgrep` and `gitleaks` and can install them with Homebrew.

### 3. CLI: local or CI

```bash
# Docker: no local toolchain needed
docker run --rm -v "$PWD:/src" ghcr.io/aliuwahab/checkmycode scan /src

# From a checkout (needs PHP 8.2+, trivy, semgrep, gitleaks)
./bin/checkmycode scan ~/Code/my-app
./bin/checkmycode scan . --format sarif --output report.sarif --fail-on high
./bin/checkmycode scan . --exclude tests --exclude fixtures --offline
```

| Option | |
|---|---|
| `--exclude <path>` | Skip a folder or file (repeatable). Default exclusions apply unless `--no-defaults`. |
| `--format text\|json\|sarif` | Report printed at the end (or written with `--output <file>`). |
| `--fail-on critical\|high\|medium\|any` | Exit with code 1 when findings reach that severity. Good for CI. |
| `--output-dir <dir>` | Keep raw scanner artifacts and `results.json`. |
| `--no-history` | Skip git-history secret scanning. |
| `--offline` | Skip endoflife.date lookups. Trivy and Semgrep still need their databases and rules. |

Set `CHECKMYCODE_CI_WORKFLOW=true` to show a **CI YAML** button that generates a GitHub Actions workflow. The workflow runs the Docker image on every PR and uploads SARIF to GitHub code scanning. Enable it only once the Docker image is publicly pullable.

## How it works

```text
app/Scanning/
  ScanEngine.php            The pipeline: Trivy → Semgrep → Gitleaks → rule packs → .env → lifecycle → quality
  ProjectDetector.php       Stack detection (Laravel, Next.js, Django, Spring, …)
  SemgrepRulesets.php       Registry packs + custom rules per stack
  ScanGrade.php             A–F security grade
  Rules/PatternRuleScanner  Cross-language pattern rules
  Lifecycle/                endoflife.date support checks
  Store/                    DatabaseScanStore (desktop) · EphemeralScanStore (web: cache + session)
  Web/ArchiveExtractor      Safe upload extraction (zip-slip, symlink, size and count limits)
app/Console/Commands/ScanCommand.php   `checkmycode:scan`: CLI, Docker and the desktop child process
app/Jobs/RunWebScan.php                Web: scan, then delete the uploaded code
app/Livewire/Scanner.php               The UI for both desktop and web
resources/scanners/*.yml               CheckMyCode custom Semgrep rules
resources/js/web-scan.js               Browser folder picking, filtering, zipping, upload
```

- **Desktop** runs `php artisan checkmycode:scan --machine` as a NativePHP child process and streams its log lines into SQLite.
- **Web** extracts the upload, queues `RunWebScan`, and the UI polls the cache for logs and results.
- **CLI** calls the engine directly.

## Development

```bash
composer run dev     # desktop UI in the browser (php artisan serve + vite)
composer run web     # web mode: server + queue worker + vite
php artisan test
```

See [CONTRIBUTING.md](./CONTRIBUTING.md) to add a stack, a rule or an export format, and [WARP.md](./WARP.md) for the roadmap.

## Support the project

CheckMyCode is free, has no paid tier and doesn't keep your code. If it saves you time or catches something before production:

- 👋 **[Follow on GitHub](https://github.com/aliuwahab)** for updates
- ❤️ **[Sponsor on GitHub](https://github.com/sponsors/aliuwahab)** to keep the hosted scanner running and new rules coming
- 🐛 Report false positives and contribute rules (see [CONTRIBUTING.md](./CONTRIBUTING.md))
