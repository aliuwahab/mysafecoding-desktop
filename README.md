# LaraGuard

LaraGuard is a NativePHP desktop app for scanning Laravel projects locally.

It wraps the bundled `laravelshield` CLI scripts in a desktop UI so a developer can:

- pick a Laravel project folder
- run a security scan without touching the terminal
- watch scan output in real time
- inspect Trivy, Semgrep, and Gitleaks results
- keep local scan history
- export results as JSON

## Product Direction

This repository is the local desktop implementation of the broader LaravelShield vision documented in [WARP.md](./WARP.md).

Today, LaraGuard is the fast local scanner experience:

- local-first
- zero SaaS dependency
- SQLite-backed
- NativePHP desktop shell
- Laravel + Livewire UI

The larger LaravelShield direction is a full Laravel-native security platform for Laravel and PHP teams, including:

- hosted dashboard
- APIs and webhooks
- nightly scanning
- team workflows
- Laravel-specific rule intelligence
- compliance and reporting

This app is the practical first surface: a downloadable desktop scanner that proves the workflow end to end.

## How It Works

The app is a Laravel application running inside NativePHP/Electron.

### Scan flow

1. The user selects a Laravel project folder in the desktop UI.
2. A `Scan` record is created in SQLite.
3. The app starts the bundled bash scanner in `resources/cli/laravelshield-scan.sh`.
4. NativePHP child-process events stream logs back into the app.
5. Raw scanner output is written into `resources/cli/ls-output/...`.
6. The app parses the JSON artifacts and stores normalized results on the scan record.
7. The Livewire UI polls for updates and switches to the summary/results tabs when the scan completes.

### Current engines

- Trivy for dependency vulnerability scanning
- Semgrep for static analysis
- Gitleaks for secrets detection
- `jq` for parsing and summary generation in the bundled scripts

## Tech Stack

- Laravel 12
- NativePHP Desktop
- Livewire 4
- Tailwind CSS 4
- SQLite
- Electron (via NativePHP)

## Project Structure

```text
app/
  Livewire/Scanner.php          Main desktop UI state + polling
  Models/Scan.php               Scan history and parsed results
  Models/ScanLog.php            Per-line process logs
  Services/ScannerService.php   Child-process orchestration + result parsing
  Providers/NativeAppServiceProvider.php

resources/
  cli/
    laravelshield-scan.sh
    laravelshield-setup.sh
    laravelshield-inspect.sh
  views/livewire/scanner.blade.php

database/
  migrations/                   Scan and scan_log schema
  database.sqlite               Standard Laravel/dev database
  nativephp.sqlite              NativePHP desktop runtime database

bin/
  native-run                    Starts the app with Homebrew php@8.4 and runs native migrations
```

## Running Locally

### Requirements

- macOS
- Homebrew
- `php@8.4`
- Node.js / npm
- Composer

Scanner dependencies:

- `trivy`
- `semgrep`
- `gitleaks`
- `jq`

### Install app dependencies

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
```

### Start the desktop app

Use the provided launcher:

```bash
./bin/native-run
```

This launcher:

- uses Homebrew `php@8.4`
- runs `php artisan native:migrate --force`
- starts `php artisan native:run --no-queue`

## Data Storage

There are two SQLite databases in development:

- `database/database.sqlite`
  Used by standard Laravel commands and tests.
- `database/nativephp.sqlite`
  Used by the NativePHP desktop runtime.

If you add migrations for app state used in the desktop app, they must be available to both environments. The launcher already runs `native:migrate` before startup.

## Current Features

- Native folder picker
- exclusion list management
- scan history sidebar
- real-time log streaming
- summary tab with aggregated counts
- Trivy / Semgrep / Gitleaks result tabs
- JSON export
- first-run dependency check screen

## Notes

- The desktop app is intentionally single-user and local-first right now.
- The scanner scripts are bundled with the app so the workflow is version-locked.
- The terminal may show NativePHP scheduler/dev-console noise in development; that is expected and does not mean the scan failed.

## Next Product Layer

The next step after this desktop app is not “more Laravel boilerplate.” It is moving upward into the LaravelShield platform described in `WARP.md`:

- shared scan API
- hosted team dashboard
- repository integrations
- scheduled scans
- Laravel-specific rules engine
- compliance reporting
- billing and organization workflows

This repo represents the local scanner foundation for that roadmap.
