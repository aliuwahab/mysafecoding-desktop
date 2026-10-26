# MySafeCoding Desktop — Product Roadmap (WARP)

This document tracks the feature roadmap for MySafeCoding Desktop, the free open-source local security scanner for popular languages and frameworks.

## Vision

MySafeCoding Desktop is the fast, local-first security scanner developers can use before moving to the hosted MySafeCoding workflow. It runs entirely on the developer's machine: no accounts, no SaaS dependency, no data leaving the laptop. The desktop app is the entry point to the broader MySafeCoding platform at https://www.mysafecoding.com/.

---

## Shipped

### v1.0 — Core Scanner
- [x] Native folder picker for local projects
- [x] Trivy dependency vulnerability scanning
- [x] Semgrep static analysis (SAST)
- [x] Gitleaks secrets detection
- [x] 20 custom framework security rules for Laravel apps
- [x] Multi-language project detection for PHP, JavaScript/TypeScript, Python, Ruby, Go, Rust, Elixir, Java, and .NET
- [x] Popular framework detection for Laravel, Symfony, Next.js, Nuxt, React, Vue, Svelte, Angular, NestJS, Express, Django, Flask, FastAPI, Rails, Phoenix, and Spring
- [x] Real-time log streaming
- [x] Scan history sidebar (last 20 scans)
- [x] Summary, Trivy, Semgrep, Gitleaks, Framework Rules, .env Audit, and Code Quality tabs
- [x] JSON and SARIF export
- [x] GitHub Actions workflow generator
- [x] First-run dependency installer (Homebrew)
- [x] Exclusion list management
- [x] Hosted Scan CTA to mysafecoding.com
- [x] Open-source contributor and security reporting docs

---

### v2.0: One engine, three surfaces
- [x] Shared PHP scan engine for desktop, web and CLI (`mysafecoding:scan`)
- [x] Hosted web mode: browser folder/file picking, client-side exclusions, scan-and-delete, no accounts or database
- [x] Docker image for CLI, CI and self-hosting the web scanner
- [x] Ported hosted-platform engines: custom Semgrep rules, cross-language pattern rules, endoflife.date lifecycle checks
- [x] Trivy misconfiguration (IaC) and license scanning
- [x] Single-file scanning
- [x] CI workflow generator uses the Docker image and uploads SARIF to code scanning

---

## In Progress

### v1.1 — Quick Wins & Medium Value Features

#### Quick Wins
- [x] **Security Grade (A-F)** — Letter grade in the Summary tab based on severity of findings. Makes results instantly understandable and screenshot-worthy.
- [x] **Scan Diff** — Compare two scans of the same project side-by-side to see what's new/fixed since last scan. Helps developers track remediation progress.

#### Medium Effort, High Value
- [x] **.env Audit Tab** — Dedicated tab that checks `.env` values (debug mode, app key strength, session driver, mail driver, database password, etc.) without needing external tools. Zero-config, runs automatically with every scan.
- [x] **Native Notifications** — macOS notifications when a scan completes, with finding count. Non-intrusive awareness for background scans.

---

## Planned

### v1.2 — Reports & Monitoring
- [ ] **PDF Report Export** — Generate a branded PDF report with charts and severity breakdown. Useful for freelancers showing clients or for compliance records.
- [ ] **Scheduled/Watch Mode** — Auto-rescan when project files change (file watcher). Continuous security feedback during development.
- [ ] **Dependency Update Checker** — For vulnerable packages, show which newer versions fix the CVE and link to changelogs.

### v1.3 — Multi-Project & Polish
- [ ] **Multi-Project Dashboard** — Scan and monitor multiple projects from one view with a health overview grid.
- [ ] **Dark/Light Theme Toggle** — System-aware theme switching.
- [ ] **Auto-Update** — Ship updates seamlessly via NativePHP's built-in updater.

### v1.4 — Remediation & Community
- [ ] **Auto-Fix for Common Issues** — One-click fixes for debug mode, missing fillable, raw Blade output, weak .env values.
- [ ] **Remediation Guides** — Inline before/after code examples for each finding type, tailored to each supported framework.
- [ ] **Baseline/Ignore Management** — Mark findings as "accepted risk" so they don't clutter future scans.
- [x] **CI Config Generator** — Export a GitHub Actions YAML that runs the same scans in CI.

### v1.5 — Ecosystem
- [ ] **Plugin System** — Community-contributed rule packs (Livewire-specific, Inertia-specific, Filament-specific).
- [ ] **Share Scan Results** — Generate a shareable link or gist of the report.
- [ ] **Anonymous Telemetry (Opt-in)** — Aggregate stats on common framework vulnerabilities for community insights.

---

## Architecture Principles

1. **Local-first** — All scanning happens on the developer's machine. No data leaves the laptop.
2. **Zero config** — Works out of the box. Select a folder, click scan.
3. **Framework-aware** — Built with Laravel-powered Livewire + NativePHP, while scanning the popular stacks developers ship every day.
4. **Open source** — Free forever for individual developers.
5. **Gateway to MySafeCoding** — The desktop app proves the workflow; the platform extends it with team features, hosted dashboards, and CI integrations.

---

## Non-Goals (for this repo)

These belong in the MySafeCoding platform, not the desktop app:
- User authentication / accounts
- Team management
- Hosted API / webhooks
- Nightly scheduled scans (cloud)
- Billing
- Organization workflows

See https://www.mysafecoding.com/ for the full platform vision.
