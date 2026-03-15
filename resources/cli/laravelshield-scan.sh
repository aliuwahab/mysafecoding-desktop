#!/bin/bash
# =============================================================================
# LaravelShield CLI — Scan Runner
# Runs Trivy + Semgrep + Gitleaks against a Laravel project and produces
# a clean summary + raw JSON files for analysis.
#
# Usage:
#   ./laravelshield-scan.sh /path/to/project [--exclude <folder>] [--exclude <folder>]
#
# Examples:
#   ./laravelshield-scan.sh .
#   ./laravelshield-scan.sh /var/www/myapp --exclude vendor
#   ./laravelshield-scan.sh /var/www/myapp --exclude vendor --exclude tests --exclude _ide_helper.php
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
    echo "  ./laravelshield-scan.sh /path/to/project [--exclude folder] [--exclude file]"
    echo ""
    echo -e "${BOLD}Examples:${RESET}"
    echo "  ./laravelshield-scan.sh ."
    echo "  ./laravelshield-scan.sh /var/www/myapp --exclude vendor"
    echo "  ./laravelshield-scan.sh /var/www/myapp --exclude vendor --exclude tests --exclude _ide_helper.php"
    echo "  ./laravelshield-scan.sh /var/www/myapp --no-defaults --exclude vendor"
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

if [ ! -f "$PROJECT_PATH/artisan" ]; then
    if [ "$NON_INTERACTIVE" = "1" ]; then
        fail "No 'artisan' file found in $PROJECT_PATH."
    fi

    warn "No 'artisan' file found — are you sure this is a Laravel project?"
    echo -n "  Continue anyway? (y/N): "
    read -r confirm
    [[ "$confirm" =~ ^[Yy]$ ]] || fail "Aborted."
fi

# ── Setup output directory ─────────────────────────────────────────────────
if [ -z "$OUTPUT_DIR" ]; then
    OUTPUT_DIR="./ls-output/$(date +%Y%m%d_%H%M%S)"
fi
mkdir -p "$OUTPUT_DIR"

TRIVY_OUT="$OUTPUT_DIR/trivy-raw.json"
SEMGREP_OUT="$OUTPUT_DIR/semgrep-raw.json"
GITLEAKS_OUT="$OUTPUT_DIR/gitleaks-raw.json"
SUMMARY_OUT="$OUTPUT_DIR/summary.txt"
COMBINED_OUT="$OUTPUT_DIR/all-findings.json"

START_TIME=$(date +%s)

# ── Header ─────────────────────────────────────────────────────────────────
echo ""
echo -e "${BOLD}████████████████████████████████████████████████████████████${RESET}"
echo -e "${BOLD}   LaravelShield CLI — Security Scan                     ${RESET}"
echo -e "${BOLD}████████████████████████████████████████████████████████████${RESET}"
echo ""
echo "  Project:  $PROJECT_PATH"
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
step "SCANNER 1/3 — Trivy (SCA: Dependency CVEs)"
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
step "SCANNER 2/3 — Semgrep (SAST: Static Code Analysis)"
echo ""
info "Scanning: PHP source code, Blade templates..."
info "Rules: php.laravel (official) + php.lang.security + generic.secrets"
echo ""

semgrep \
    --config "p/php" \
    --config "p/secrets" \
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
step "SCANNER 3/3 — Gitleaks (Secrets: API keys, tokens, passwords)"
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
LaravelShield CLI Scan Summary
====================================
Project:    $PROJECT_PATH
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
3. Note any Laravel-specific patterns Semgrep missed
   (these become custom LaravelShield rules)
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
echo -e "${GREEN}${BOLD}LaravelShield CLI scan complete. Review your results above.${RESET}"
echo ""
