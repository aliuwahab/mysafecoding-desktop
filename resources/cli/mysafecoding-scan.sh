#!/bin/bash
# =============================================================================
# MySafeCoding CLI — Scan Runner
# Runs Trivy + Semgrep + Gitleaks against a project and produces
# a clean summary + raw JSON files for analysis.
#
# Usage:
#   ./mysafecoding-scan.sh /path/to/project [--exclude <folder>] [--exclude <folder>]
#
# Examples:
#   ./mysafecoding-scan.sh .
#   ./mysafecoding-scan.sh /var/www/myapp --exclude vendor
#   ./mysafecoding-scan.sh /var/www/myapp --exclude vendor --exclude tests --exclude _ide_helper.php
#
# Default exclusions (always applied unless you use --no-defaults):
#   vendor, node_modules, storage, bootstrap/cache, public/build
#
# Flags:
#   --exclude <path>    Exclude a file or folder from all scanners
#   --no-defaults       Remove the built-in default exclusions
#   --output-dir <dir>  Write scan artifacts to a specific directory
#   --non-interactive   Fail instead of prompting for confirmation
#
# Output:
#   ./ls-output/
#     ├── trivy-raw.json       Raw Trivy SCA output
#     ├── semgrep-raw.json     Raw Semgrep SAST output
#     ├── gitleaks-raw.json    Raw Gitleaks secrets output
#     ├── summary.txt          Human-readable summary
#     └── all-findings.json    Normalized, combined findings
# =============================================================================

set -e

# ── Colors ────────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
BOLD='\033[1m'
RESET='\033[0m'

ok()   { echo -e "${GREEN}✔${RESET}  $1"; }
info() { echo -e "${BLUE}→${RESET}  $1"; }
warn() { echo -e "${YELLOW}⚠${RESET}  $1"; }
fail() { echo -e "${RED}✘${RESET}  $1"; exit 1; }
step() { echo -e "\n${BOLD}${CYAN}╔══════════════════════════════════════════════════╗${RESET}"; \
         echo -e "${BOLD}${CYAN}  $1${RESET}"; \
         echo -e "${BOLD}${CYAN}╚══════════════════════════════════════════════════╝${RESET}"; }

# ── Parse arguments ────────────────────────────────────────────────────────
if [ -z "$1" ]; then
    echo ""
    echo -e "${BOLD}Usage:${RESET}"
    echo "  ./mysafecoding-scan.sh /path/to/project [--exclude folder] [--exclude file]"
    echo ""
    echo -e "${BOLD}Examples:${RESET}"
    echo "  ./mysafecoding-scan.sh ."
    echo "  ./mysafecoding-scan.sh /var/www/myapp --exclude vendor"
    echo "  ./mysafecoding-scan.sh /var/www/myapp --exclude vendor --exclude tests --exclude _ide_helper.php"
    echo "  ./mysafecoding-scan.sh /var/www/myapp --no-defaults --exclude vendor"
    echo ""
    exit 1
fi

PROJECT_PATH="$1"
shift  # remove project path from args, leaving only flags

# Default exclusions — always excluded unless --no-defaults is passed
DEFAULT_EXCLUDES=("vendor" "node_modules" "storage" "bootstrap/cache" "public/build" "*.min.js" ".git")
USER_EXCLUDES=()
USE_DEFAULTS=true
OUTPUT_DIR=""
NON_INTERACTIVE="${LS_NON_INTERACTIVE:-0}"

# Parse remaining flags
while [[ $# -gt 0 ]]; do
    case "$1" in
        --exclude)
            if [ -z "$2" ]; then fail "--exclude requires a value (e.g. --exclude vendor)"; fi
            USER_EXCLUDES+=("$2")
            shift 2
            ;;
        --no-defaults)
            USE_DEFAULTS=false
            shift
            ;;
        --output-dir)
            if [ -z "$2" ]; then fail "--output-dir requires a value"; fi
            OUTPUT_DIR="$2"
            shift 2
            ;;
        --non-interactive)
            NON_INTERACTIVE=1
            shift
            ;;
        *)
            warn "Unknown flag: $1 (ignored)"
            shift
            ;;
    esac
done

# Build final exclusion list
ALL_EXCLUDES=()
if [ "$USE_DEFAULTS" = true ]; then
    ALL_EXCLUDES+=("${DEFAULT_EXCLUDES[@]}")
fi
ALL_EXCLUDES+=("${USER_EXCLUDES[@]}")

# Build per-scanner exclude flags
# Trivy uses:    --skip-dirs <dir>
# Semgrep uses:  --exclude <pattern>
# Gitleaks uses: --ignore-path <pattern> (written to a temp .gitleaksignore)
TRIVY_EXCLUDES=()
SEMGREP_EXCLUDES=()
GITLEAKS_IGNORE_PATTERNS=()

for ex in "${ALL_EXCLUDES[@]}"; do
    TRIVY_EXCLUDES+=("--skip-dirs" "$ex")
    SEMGREP_EXCLUDES+=("--exclude" "$ex")
    GITLEAKS_IGNORE_PATTERNS+=("$ex")
done

# ── Validate project path ─────────────────────────────────────────────────
if [ ! -d "$PROJECT_PATH" ]; then
    fail "Directory not found: $PROJECT_PATH"
fi

# ── Detect project type ───────────────────────────────────────────────────
PROJECT_TYPE="unknown"
json_pkg_exists() {
    local file="$1"
    local package="$2"
    jq -e --arg package "$package" '
      (.dependencies // {}) + (.devDependencies // {}) + (.peerDependencies // {}) + (.optionalDependencies // {})
      | has($package)
    ' "$file" >/dev/null 2>&1
}

file_contains() {
    local file="$1"
    local needle="$2"
    [ -f "$file" ] && grep -qi "$needle" "$file" 2>/dev/null
}

if [ -f "$PROJECT_PATH/composer.json" ]; then
    if [ -f "$PROJECT_PATH/artisan" ] || file_contains "$PROJECT_PATH/composer.json" '"laravel/framework"'; then
        PROJECT_TYPE="laravel"
    elif file_contains "$PROJECT_PATH/composer.json" '"symfony/framework-bundle"'; then
        PROJECT_TYPE="symfony"
    else
        PROJECT_TYPE="php"
    fi
elif [ -f "$PROJECT_PATH/package.json" ]; then
    if json_pkg_exists "$PROJECT_PATH/package.json" "next"; then
        PROJECT_TYPE="nextjs"
    elif json_pkg_exists "$PROJECT_PATH/package.json" "nuxt"; then
        PROJECT_TYPE="nuxt"
    elif json_pkg_exists "$PROJECT_PATH/package.json" "@angular/core" || json_pkg_exists "$PROJECT_PATH/package.json" "angular"; then
        PROJECT_TYPE="angular"
    elif json_pkg_exists "$PROJECT_PATH/package.json" "svelte"; then
        PROJECT_TYPE="svelte"
    elif json_pkg_exists "$PROJECT_PATH/package.json" "vue"; then
        PROJECT_TYPE="vue"
    elif json_pkg_exists "$PROJECT_PATH/package.json" "react"; then
        PROJECT_TYPE="react"
    elif json_pkg_exists "$PROJECT_PATH/package.json" "@nestjs/core"; then
        PROJECT_TYPE="nestjs"
    elif json_pkg_exists "$PROJECT_PATH/package.json" "express"; then
        PROJECT_TYPE="express"
    else
        PROJECT_TYPE="node"
    fi
elif [ -f "$PROJECT_PATH/requirements.txt" ] || [ -f "$PROJECT_PATH/pyproject.toml" ] || [ -f "$PROJECT_PATH/setup.py" ]; then
    if file_contains "$PROJECT_PATH/requirements.txt" "django" || file_contains "$PROJECT_PATH/pyproject.toml" "django" || file_contains "$PROJECT_PATH/setup.py" "django"; then
        PROJECT_TYPE="django"
    elif file_contains "$PROJECT_PATH/requirements.txt" "fastapi" || file_contains "$PROJECT_PATH/pyproject.toml" "fastapi" || file_contains "$PROJECT_PATH/setup.py" "fastapi"; then
        PROJECT_TYPE="fastapi"
    elif file_contains "$PROJECT_PATH/requirements.txt" "flask" || file_contains "$PROJECT_PATH/pyproject.toml" "flask" || file_contains "$PROJECT_PATH/setup.py" "flask"; then
        PROJECT_TYPE="flask"
    else
        PROJECT_TYPE="python"
    fi
elif [ -f "$PROJECT_PATH/Gemfile" ]; then
    if file_contains "$PROJECT_PATH/Gemfile" "rails"; then
        PROJECT_TYPE="rails"
    else
        PROJECT_TYPE="ruby"
    fi
elif [ -f "$PROJECT_PATH/go.mod" ]; then
    PROJECT_TYPE="go"
elif [ -f "$PROJECT_PATH/Cargo.toml" ]; then
    PROJECT_TYPE="rust"
elif [ -f "$PROJECT_PATH/mix.exs" ]; then
    if file_contains "$PROJECT_PATH/mix.exs" "phoenix"; then
        PROJECT_TYPE="phoenix"
    else
        PROJECT_TYPE="elixir"
    fi
elif [ -f "$PROJECT_PATH/build.gradle" ] || [ -f "$PROJECT_PATH/build.gradle.kts" ] || [ -f "$PROJECT_PATH/pom.xml" ]; then
    if file_contains "$PROJECT_PATH/build.gradle" "spring-boot" || file_contains "$PROJECT_PATH/build.gradle" "springframework" || file_contains "$PROJECT_PATH/build.gradle.kts" "spring-boot" || file_contains "$PROJECT_PATH/build.gradle.kts" "springframework" || file_contains "$PROJECT_PATH/pom.xml" "spring-boot" || file_contains "$PROJECT_PATH/pom.xml" "springframework"; then
        PROJECT_TYPE="spring"
    else
        PROJECT_TYPE="java"
    fi
elif find "$PROJECT_PATH" -maxdepth 2 -name '*.csproj' -print -quit 2>/dev/null | grep -q .; then
    PROJECT_TYPE="dotnet"
fi

info "Detected project type: $PROJECT_TYPE"

# ── Setup output directory ─────────────────────────────────────────────────
if [ -z "$OUTPUT_DIR" ]; then
    OUTPUT_DIR="./ls-output/$(date +%Y%m%d_%H%M%S)"
fi
mkdir -p "$OUTPUT_DIR"

# Write project type for the desktop app to read
echo "$PROJECT_TYPE" > "$OUTPUT_DIR/project-type.txt"

TRIVY_OUT="$OUTPUT_DIR/trivy-raw.json"
SEMGREP_OUT="$OUTPUT_DIR/semgrep-raw.json"
GITLEAKS_OUT="$OUTPUT_DIR/gitleaks-raw.json"
QUALITY_OUT="$OUTPUT_DIR/quality-raw.json"
SUMMARY_OUT="$OUTPUT_DIR/summary.txt"
COMBINED_OUT="$OUTPUT_DIR/all-findings.json"

START_TIME=$(date +%s)

# ── Header ─────────────────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}████████████████████████████████████████████████████████████${RESET}"
echo -e "${BOLD}   MySafeCoding CLI — Security Scan                      ${RESET}"
echo -e "${BOLD}████████████████████████████████████████████████████████████${RESET}"
echo ""
echo "  Project:  $PROJECT_PATH"
echo "  Type:     $PROJECT_TYPE"
echo "  Output:   $OUTPUT_DIR"
echo "  Started:  $(date '+%Y-%m-%d %H:%M:%S')"

# Show exclusions
if [ ${#ALL_EXCLUDES[@]} -gt 0 ]; then
    echo ""
    echo -e "  ${CYAN}Excluded from all scanners:${RESET}"
    for ex in "${ALL_EXCLUDES[@]}"; do
        echo "    ✗  $ex"
    done
fi
echo ""

# ══════════════════════════════════════════════════════════════════════════════
# SCANNER 1: TRIVY — Dependency Vulnerability Scan (SCA)
# ══════════════════════════════════════════════════════════════════════════════
step "SCANNER 1/4 — Trivy (SCA: Dependency CVEs)"
echo ""
info "Scanning: composer.lock, package-lock.json, yarn.lock..."
info "Database: OSV + NVD (auto-updated on first run)"
echo ""

trivy fs \
    --scanners vuln \
    --format json \
    --output "$TRIVY_OUT" \
    --severity CRITICAL,HIGH,MEDIUM,LOW \
    "${TRIVY_EXCLUDES[@]}" \
    --quiet \
    "$PROJECT_PATH" 2>/dev/null || true

# Parse Trivy results
if [ -f "$TRIVY_OUT" ] && [ -s "$TRIVY_OUT" ]; then
    TRIVY_CRITICAL=$(jq '[.Results[]?.Vulnerabilities[]? | select(.Severity=="CRITICAL")] | length' "$TRIVY_OUT" 2>/dev/null || echo 0)
    TRIVY_HIGH=$(jq     '[.Results[]?.Vulnerabilities[]? | select(.Severity=="HIGH")]     | length' "$TRIVY_OUT" 2>/dev/null || echo 0)
    TRIVY_MEDIUM=$(jq   '[.Results[]?.Vulnerabilities[]? | select(.Severity=="MEDIUM")]   | length' "$TRIVY_OUT" 2>/dev/null || echo 0)
    TRIVY_LOW=$(jq      '[.Results[]?.Vulnerabilities[]? | select(.Severity=="LOW")]      | length' "$TRIVY_OUT" 2>/dev/null || echo 0)
    TRIVY_TOTAL=$((TRIVY_CRITICAL + TRIVY_HIGH + TRIVY_MEDIUM + TRIVY_LOW))

    echo -e "  ${BOLD}Trivy Results:${RESET}"
    echo -e "  ┌────────────────────────────────────────┐"
    [ "$TRIVY_CRITICAL" -gt 0 ] && echo -e "  │  ${RED}CRITICAL${RESET}   $TRIVY_CRITICAL" || echo -e "  │  CRITICAL   $TRIVY_CRITICAL"
    [ "$TRIVY_HIGH" -gt 0 ]     && echo -e "  │  ${YELLOW}HIGH${RESET}       $TRIVY_HIGH"     || echo -e "  │  HIGH       $TRIVY_HIGH"
    echo -e "  │  MEDIUM     $TRIVY_MEDIUM"
    echo -e "  │  LOW        $TRIVY_LOW"
    echo -e "  │  ─────────────────────────────────"
    echo -e "  │  TOTAL      $TRIVY_TOTAL"
    echo -e "  └────────────────────────────────────────┘"

    # Show top CRITICAL findings in terminal
    if [ "$TRIVY_CRITICAL" -gt 0 ]; then
        echo ""
        echo -e "  ${RED}${BOLD}Top CRITICAL findings:${RESET}"
        jq -r '
          .Results[]?.Vulnerabilities[]?
          | select(.Severity=="CRITICAL")
          | "  🔴 \(.VulnerabilityID) — \(.PkgName) \(.InstalledVersion) → fix: \(.FixedVersion // "no fix")\n     \(.Title // "")"
        ' "$TRIVY_OUT" 2>/dev/null | head -30
    fi

    if [ "$TRIVY_HIGH" -gt 0 ]; then
        echo ""
        echo -e "  ${YELLOW}${BOLD}Top HIGH findings:${RESET}"
        jq -r '
          .Results[]?.Vulnerabilities[]?
          | select(.Severity=="HIGH")
          | "  🟡 \(.VulnerabilityID) — \(.PkgName) \(.InstalledVersion) → fix: \(.FixedVersion // "no fix")\n     \(.Title // "")"
        ' "$TRIVY_OUT" 2>/dev/null | head -20
    fi

    ok "Trivy complete. Raw JSON saved to: $TRIVY_OUT"
else
    warn "Trivy produced no output (no dependency files found or no vulnerabilities)"
    TRIVY_TOTAL=0; TRIVY_CRITICAL=0; TRIVY_HIGH=0; TRIVY_MEDIUM=0; TRIVY_LOW=0
    echo '{"Results":[]}' > "$TRIVY_OUT"
fi

# ══════════════════════════════════════════════════════════════════════════════
# SCANNER 2: SEMGREP — Static Code Analysis (SAST)
# ══════════════════════════════════════════════════════════════════════════════
step "SCANNER 2/4 — Semgrep (SAST: Static Code Analysis)"
echo ""
# Build Semgrep config flags based on detected project type
SEMGREP_CONFIGS=("--config" "p/secrets")
case "$PROJECT_TYPE" in
    laravel|symfony|php)
        SEMGREP_CONFIGS+=("--config" "p/php")
        info "Scanning: PHP source code and templates..."
        info "Rules: PHP security + generic secrets"
        ;;
    react|nextjs|node|vue|nuxt|svelte|angular|nestjs|express)
        SEMGREP_CONFIGS+=("--config" "p/javascript" "--config" "p/typescript")
        info "Scanning: JavaScript/TypeScript source code..."
        info "Rules: JavaScript + TypeScript + generic secrets"
        ;;
    django|flask|fastapi|python)
        SEMGREP_CONFIGS+=("--config" "p/python")
        info "Scanning: Python source code..."
        info "Rules: Python security + generic secrets"
        ;;
    rails|ruby)
        SEMGREP_CONFIGS+=("--config" "p/ruby")
        info "Scanning: Ruby source code..."
        info "Rules: Ruby security + generic secrets"
        ;;
    go)
        SEMGREP_CONFIGS+=("--config" "p/golang")
        info "Scanning: Go source code..."
        info "Rules: Go security + generic secrets"
        ;;
    rust)
        SEMGREP_CONFIGS+=("--config" "p/rust")
        info "Scanning: Rust source code..."
        info "Rules: rust.lang.security + generic.secrets"
        ;;
    phoenix|elixir)
        info "Scanning: Elixir source code..."
        info "Rules: generic.secrets (no official Elixir ruleset)"
        ;;
    spring|java)
        SEMGREP_CONFIGS+=("--config" "p/java")
        info "Scanning: Java source code..."
        info "Rules: Java security + generic secrets"
        ;;
    dotnet)
        SEMGREP_CONFIGS+=("--config" "p/csharp")
        info "Scanning: .NET/C# source code..."
        info "Rules: C# security + generic secrets"
        ;;
    *)
        info "Scanning: source code (generic rules)..."
        info "Rules: generic.secrets"
        ;;
esac
echo ""

semgrep \
    "${SEMGREP_CONFIGS[@]}" \
    --json \
    --output "$SEMGREP_OUT" \
    "${SEMGREP_EXCLUDES[@]}" \
    --quiet \
    "$PROJECT_PATH" 2>/dev/null || true

# Parse Semgrep results
if [ -f "$SEMGREP_OUT" ] && [ -s "$SEMGREP_OUT" ]; then
    SEMGREP_ERROR=$(jq   '[.results[]? | select(.extra.severity=="ERROR")]   | length' "$SEMGREP_OUT" 2>/dev/null || echo 0)
    SEMGREP_WARN=$(jq    '[.results[]? | select(.extra.severity=="WARNING")] | length' "$SEMGREP_OUT" 2>/dev/null || echo 0)
    SEMGREP_INFO=$(jq    '[.results[]? | select(.extra.severity=="INFO")]    | length' "$SEMGREP_OUT" 2>/dev/null || echo 0)
    SEMGREP_TOTAL=$((SEMGREP_ERROR + SEMGREP_WARN + SEMGREP_INFO))

    echo -e "  ${BOLD}Semgrep Results:${RESET}"
    echo -e "  ┌────────────────────────────────────────┐"
    [ "$SEMGREP_ERROR" -gt 0 ] && echo -e "  │  ${RED}ERROR${RESET}      $SEMGREP_ERROR  (maps to HIGH)"  || echo -e "  │  ERROR      $SEMGREP_ERROR"
    [ "$SEMGREP_WARN" -gt 0 ]  && echo -e "  │  ${YELLOW}WARNING${RESET}    $SEMGREP_WARN  (maps to MEDIUM)" || echo -e "  │  WARNING    $SEMGREP_WARN"
    echo -e "  │  INFO       $SEMGREP_INFO  (maps to LOW)"
    echo -e "  │  ─────────────────────────────────"
    echo -e "  │  TOTAL      $SEMGREP_TOTAL"
    echo -e "  └────────────────────────────────────────┘"

    # Show top ERROR findings
    if [ "$SEMGREP_ERROR" -gt 0 ]; then
        echo ""
        echo -e "  ${RED}${BOLD}Top ERROR findings:${RESET}"
        jq -r '
          .results[]?
          | select(.extra.severity=="ERROR")
          | "  🔴 \(.check_id)\n     File: \(.path):\(.start.line)\n     \(.extra.message | split("\n")[0])"
        ' "$SEMGREP_OUT" 2>/dev/null | head -40
    fi

    ok "Semgrep complete. Raw JSON saved to: $SEMGREP_OUT"
else
    warn "Semgrep produced no output"
    SEMGREP_TOTAL=0; SEMGREP_ERROR=0; SEMGREP_WARN=0; SEMGREP_INFO=0
    echo '{"results":[],"errors":[]}' > "$SEMGREP_OUT"
fi

# ══════════════════════════════════════════════════════════════════════════════
# SCANNER 3: GITLEAKS — Secrets Detection
# ══════════════════════════════════════════════════════════════════════════════
step "SCANNER 3/4 — Gitleaks (Secrets: API keys, tokens, passwords)"
echo ""
info "Scanning: current files AND full git history..."
echo ""

# Build a temporary .gitleaksignore file from exclusions
GITLEAKS_IGNORE_FILE="$OUTPUT_DIR/.gitleaksignore"
if [ ${#GITLEAKS_IGNORE_PATTERNS[@]} -gt 0 ]; then
    printf '%s\n' "${GITLEAKS_IGNORE_PATTERNS[@]}" > "$GITLEAKS_IGNORE_FILE"
    info "Gitleaks ignore file: $GITLEAKS_IGNORE_FILE"
    cat "$GITLEAKS_IGNORE_FILE" | while read -r line; do echo "    ✗  $line"; done
    echo ""
fi

# Check if the project is a git repo
if [ -d "$PROJECT_PATH/.git" ]; then
    gitleaks detect \
        --source "$PROJECT_PATH" \
        ${GITLEAKS_IGNORE_FILE:+--gitleaks-ignore-path "$GITLEAKS_IGNORE_FILE"} \
        --report-format json \
        --report-path "$GITLEAKS_OUT" \
        --no-banner \
        --log-level error \
        2>/dev/null || true   # exit 1 when leaks found — expected
else
    warn "Not a git repo — running filesystem-only scan (no git history check)"
    gitleaks detect \
        --source "$PROJECT_PATH" \
        --no-git \
        ${GITLEAKS_IGNORE_FILE:+--gitleaks-ignore-path "$GITLEAKS_IGNORE_FILE"} \
        --report-format json \
        --report-path "$GITLEAKS_OUT" \
        --no-banner \
        --log-level error \
        2>/dev/null || true
fi

# Parse Gitleaks results
if [ -f "$GITLEAKS_OUT" ] && [ -s "$GITLEAKS_OUT" ]; then
    GITLEAKS_TOTAL=$(jq 'length' "$GITLEAKS_OUT" 2>/dev/null || echo 0)

    echo -e "  ${BOLD}Gitleaks Results:${RESET}"
    echo -e "  ┌────────────────────────────────────────┐"
    [ "$GITLEAKS_TOTAL" -gt 0 ] && \
        echo -e "  │  ${RED}SECRETS FOUND${RESET}  $GITLEAKS_TOTAL" || \
        echo -e "  │  ${GREEN}No secrets found${RESET}  ✔"
    echo -e "  └────────────────────────────────────────┘"

    if [ "$GITLEAKS_TOTAL" -gt 0 ]; then
        echo ""
        echo -e "  ${RED}${BOLD}Secrets found:${RESET}"
        jq -r '.[] | "  🔴 [\(.RuleID)] \(.Description)\n     File:   \(.File):\(.StartLine)\n     Match:  \(.Match | .[0:80])...\n     Commit: \(.Commit // "filesystem")"' \
            "$GITLEAKS_OUT" 2>/dev/null | head -60
    fi

    ok "Gitleaks complete. Raw JSON saved to: $GITLEAKS_OUT"
else
    GITLEAKS_TOTAL=0
    echo '[]' > "$GITLEAKS_OUT"
    ok "Gitleaks: No secrets detected ✔"
fi

# ══════════════════════════════════════════════════════════════════════════════
# SCANNER 4/4: SEMGREP CODE QUALITY — Best Practices & Code Standards
# ══════════════════════════════════════════════════════════════════════════════
step "SCANNER 4/4 — Code Quality (Best Practices & Standards)"
echo ""

# Build quality-specific Semgrep configs per project type
QUALITY_CONFIGS=()
case "$PROJECT_TYPE" in
    laravel|symfony|php)
        QUALITY_CONFIGS+=("--config" "p/phpcs-security-audit" "--config" "p/php")
        info "Quality rules: PHP best practices, PSR standards, framework patterns"
        ;;
    react)
        QUALITY_CONFIGS+=("--config" "p/react-best-practices" "--config" "p/eslint-plugin-react" "--config" "p/typescript")
        info "Quality rules: React best practices, hooks rules, TypeScript patterns"
        ;;
    nextjs)
        QUALITY_CONFIGS+=("--config" "p/nextjs" "--config" "p/react-best-practices" "--config" "p/typescript")
        info "Quality rules: Next.js, React best practices, TypeScript patterns"
        ;;
    vue|nuxt)
        QUALITY_CONFIGS+=("--config" "p/javascript" "--config" "p/typescript")
        info "Quality rules: Vue/Nuxt JavaScript & TypeScript best practices"
        ;;
    node|express|nestjs)
        QUALITY_CONFIGS+=("--config" "p/nodejs" "--config" "p/typescript")
        info "Quality rules: Node.js best practices, async patterns, TypeScript"
        ;;
    django|flask|fastapi|python)
        QUALITY_CONFIGS+=("--config" "p/python" "--config" "p/bandit" "--config" "p/flask" "--config" "p/django")
        info "Quality rules: Python best practices, security checks, framework patterns"
        ;;
    rails|ruby)
        QUALITY_CONFIGS+=("--config" "p/ruby" "--config" "p/rails")
        info "Quality rules: Ruby best practices, Rails patterns"
        ;;
    go)
        QUALITY_CONFIGS+=("--config" "p/golang" "--config" "p/gosec")
        info "Quality rules: Go best practices, effective Go, gosec"
        ;;
    rust)
        QUALITY_CONFIGS+=("--config" "p/rust")
        info "Quality rules: Rust best practices, clippy-like patterns"
        ;;
    spring|java)
        QUALITY_CONFIGS+=("--config" "p/java" "--config" "p/spring")
        info "Quality rules: Java best practices, Spring patterns"
        ;;
    dotnet)
        QUALITY_CONFIGS+=("--config" "p/csharp")
        info "Quality rules: .NET/C# best practices"
        ;;
    phoenix|elixir)
        info "Skipping code quality (no Semgrep ruleset for Elixir)"
        ;;
    *)
        info "Skipping code quality (unknown project type)"
        ;;
esac
echo ""

QUALITY_TOTAL=0
if [ ${#QUALITY_CONFIGS[@]} -gt 0 ]; then
    semgrep \
        "${QUALITY_CONFIGS[@]}" \
        --json \
        --output "$QUALITY_OUT" \
        "${SEMGREP_EXCLUDES[@]}" \
        --quiet \
        "$PROJECT_PATH" 2>/dev/null || true

    if [ -f "$QUALITY_OUT" ] && [ -s "$QUALITY_OUT" ]; then
        # Filter out findings already in the security scan to avoid duplicates
        if [ -f "$SEMGREP_OUT" ] && [ -s "$SEMGREP_OUT" ]; then
            # Get security finding check_ids and remove them from quality results
            SECURITY_IDS=$(jq -r '[.results[]?.check_id] | unique | .[]' "$SEMGREP_OUT" 2>/dev/null || echo "")
            if [ -n "$SECURITY_IDS" ]; then
                # Create a temp file with deduplicated quality results
                jq --argjson sec_ids "$(jq '[.results[]?.check_id] | unique' "$SEMGREP_OUT" 2>/dev/null || echo '[]')" \
                    '.results = [.results[]? | select(.check_id as $id | $sec_ids | index($id) | not)]' \
                    "$QUALITY_OUT" > "$QUALITY_OUT.tmp" 2>/dev/null && mv "$QUALITY_OUT.tmp" "$QUALITY_OUT"
            fi
        fi

        QUALITY_ERROR=$(jq   '[.results[]? | select(.extra.severity=="ERROR")]   | length' "$QUALITY_OUT" 2>/dev/null || echo 0)
        QUALITY_WARN=$(jq    '[.results[]? | select(.extra.severity=="WARNING")] | length' "$QUALITY_OUT" 2>/dev/null || echo 0)
        QUALITY_INFO=$(jq    '[.results[]? | select(.extra.severity=="INFO")]    | length' "$QUALITY_OUT" 2>/dev/null || echo 0)
        QUALITY_TOTAL=$((QUALITY_ERROR + QUALITY_WARN + QUALITY_INFO))

        echo -e "  ${BOLD}Code Quality Results:${RESET}"
        echo -e "  ┌────────────────────────────────────────┐"
        echo -e "  │  NEEDS FIX     $QUALITY_ERROR"
        echo -e "  │  IMPROVE       $QUALITY_WARN"
        echo -e "  │  SUGGESTION    $QUALITY_INFO"
        echo -e "  │  ─────────────────────────────────"
        echo -e "  │  TOTAL         $QUALITY_TOTAL"
        echo -e "  └────────────────────────────────────────┘"

        ok "Code quality scan complete. Raw JSON saved to: $QUALITY_OUT"
    else
        echo '{"results":[],"errors":[]}' > "$QUALITY_OUT"
        ok "Code quality: no issues found"
    fi
else
    echo '{"results":[],"errors":[]}' > "$QUALITY_OUT"
    ok "Code quality scan skipped (no ruleset for $PROJECT_TYPE)"
fi

# ══════════════════════════════════════════════════════════════════════════════
# COMBINED SUMMARY
# ══════════════════════════════════════════════════════════════════════════════
END_TIME=$(date +%s)
DURATION=$((END_TIME - START_TIME))

GRAND_TOTAL=$((TRIVY_TOTAL + SEMGREP_TOTAL + GITLEAKS_TOTAL))

echo ""
echo -e "${BOLD}████████████████████████████████████████████████████████████${RESET}"
echo -e "${BOLD}   SCAN COMPLETE — SUMMARY                                   ${RESET}"
echo -e "${BOLD}████████████████████████████████████████████████████████████${RESET}"
echo ""
echo -e "  Project:    $PROJECT_PATH"
echo -e "  Duration:   ${DURATION}s"
echo -e "  Timestamp:  $(date '+%Y-%m-%d %H:%M:%S')"
echo ""
echo -e "  ┌─────────────────────────────────────────────────────────┐"
echo -e "  │  Scanner          Findings   (Critical/High/Other)      │"
echo -e "  ├─────────────────────────────────────────────────────────┤"

if   [ "$TRIVY_TOTAL" -gt 0 ]; then
    echo -e "  │  ${RED}Trivy (SCA)${RESET}        $TRIVY_TOTAL          ($TRIVY_CRITICAL / $TRIVY_HIGH / $((TRIVY_MEDIUM + TRIVY_LOW)))              │"
else
    echo -e "  │  ${GREEN}Trivy (SCA)${RESET}        $TRIVY_TOTAL          (clean ✔)                       │"
fi

if   [ "$SEMGREP_TOTAL" -gt 0 ]; then
    echo -e "  │  ${RED}Semgrep (SAST)${RESET}     $SEMGREP_TOTAL          ($SEMGREP_ERROR high / $SEMGREP_WARN med / $SEMGREP_INFO low)   │"
else
    echo -e "  │  ${GREEN}Semgrep (SAST)${RESET}     $SEMGREP_TOTAL          (clean ✔)                       │"
fi

if   [ "$GITLEAKS_TOTAL" -gt 0 ]; then
    echo -e "  │  ${RED}Gitleaks (Secrets)${RESET} $GITLEAKS_TOTAL          (all HIGH)                      │"
else
    echo -e "  │  ${GREEN}Gitleaks (Secrets)${RESET} $GITLEAKS_TOTAL          (clean ✔)                       │"
fi

echo ""
echo -e "  │  ${CYAN}Code Quality${RESET}       $QUALITY_TOTAL          (not counted as security issues)  │"

echo -e "  ├─────────────────────────────────────────────────────────┤"
if   [ "$GRAND_TOTAL" -gt 0 ]; then
    echo -e "  │  ${RED}${BOLD}TOTAL              $GRAND_TOTAL findings across all scanners${RESET}       │"
else
    echo -e "  │  ${GREEN}${BOLD}TOTAL              0  — No findings detected 🎉${RESET}              │"
fi
echo -e "  └─────────────────────────────────────────────────────────┘"
echo ""

# ── Produce package-level breakdown from Trivy ─────────────────────────────
if [ "$TRIVY_TOTAL" -gt 0 ]; then
    echo -e "${BOLD}Vulnerable packages (Trivy):${RESET}"
    jq -r '
        .Results[]?
        | .Target as $target
        | .Vulnerabilities[]?
        | "\(.Severity)\t\(.PkgName)\t\(.InstalledVersion)\t\(.FixedVersion // "no fix")\t\(.VulnerabilityID)\t\($target)"
    ' "$TRIVY_OUT" 2>/dev/null \
    | sort \
    | awk -F'\t' 'BEGIN{OFS="\t"} {
        sev=$1; pkg=$2; inst=$3; fix=$4; cve=$5; file=$6;
        if(sev=="CRITICAL") prefix="🔴";
        else if(sev=="HIGH") prefix="🟡";
        else if(sev=="MEDIUM") prefix="🟠";
        else prefix="⚪";
        printf "  %s  %-40s  %-15s → %-15s  %s\n", prefix, pkg" ("file")", inst, fix, cve
    }' | head -40
    echo ""
fi

# ── Write summary file ─────────────────────────────────────────────────────
cat > "$SUMMARY_OUT" << EOF
MySafeCoding CLI Scan Summary
====================================
Project:    $PROJECT_PATH
Type:       $PROJECT_TYPE
Scanned:    $(date '+%Y-%m-%d %H:%M:%S')
Duration:   ${DURATION}s
Excluded:   ${ALL_EXCLUDES[*]:-none}

RESULTS
-------
Trivy (SCA - Dependencies):
  CRITICAL: $TRIVY_CRITICAL
  HIGH:     $TRIVY_HIGH
  MEDIUM:   $TRIVY_MEDIUM
  LOW:      $TRIVY_LOW
  TOTAL:    $TRIVY_TOTAL

Semgrep (SAST - Static Analysis):
  ERROR:    $SEMGREP_ERROR
  WARNING:  $SEMGREP_WARN
  INFO:     $SEMGREP_INFO
  TOTAL:    $SEMGREP_TOTAL

Gitleaks (Secrets Detection):
  TOTAL:    $GITLEAKS_TOTAL

GRAND TOTAL: $GRAND_TOTAL findings

OUTPUT FILES
------------
  Raw JSON files: $OUTPUT_DIR/
  - trivy-raw.json    (dependency CVEs)
  - semgrep-raw.json  (static analysis)
  - gitleaks-raw.json (secrets)
  - summary.txt       (this file)

NEXT STEPS
----------
1. Review CRITICAL and HIGH findings first
2. For each finding, decide: real issue or false positive?
3. Note any framework-specific patterns Semgrep missed
   (these can become custom MySafeCoding hosted rules)
4. Run: cat $OUTPUT_DIR/trivy-raw.json | jq '.Results[].Vulnerabilities[]? | {id:.VulnerabilityID, pkg:.PkgName, severity:.Severity, fix:.FixedVersion}'
EOF

echo -e "${BOLD}Output files written to:${RESET}"
ls -la "$OUTPUT_DIR/"
echo ""
echo -e "${BOLD}Useful follow-up commands:${RESET}"
echo ""
echo -e "  ${CYAN}# See all Trivy CVE IDs and fixes:${RESET}"
echo "  cat $TRIVY_OUT | jq -r '.Results[]?.Vulnerabilities[]? | \"\(.Severity) | \(.VulnerabilityID) | \(.PkgName) \(.InstalledVersion) → \(.FixedVersion // \"no fix\")\"'"
echo ""
echo -e "  ${CYAN}# See all Semgrep findings with file and line:${RESET}"
echo "  cat $SEMGREP_OUT | jq -r '.results[]? | \"\(.extra.severity) | \(.check_id) | \(.path):\(.start.line)\"'"
echo ""
echo -e "  ${CYAN}# See all secrets found:${RESET}"
echo "  cat $GITLEAKS_OUT | jq -r '.[] | \"[\(.RuleID)] \(.File):\(.StartLine) — \(.Description)\"'"
echo ""
echo -e "  ${CYAN}# Count findings by severity (Trivy):${RESET}"
echo "  cat $TRIVY_OUT | jq '[.Results[]?.Vulnerabilities[]?.Severity] | group_by(.) | map({(.[0]): length}) | add'"
echo ""
echo -e "${GREEN}${BOLD}MySafeCoding CLI scan complete. Review your results above.${RESET}"
echo ""
