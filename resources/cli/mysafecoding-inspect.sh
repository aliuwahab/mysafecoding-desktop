#!/bin/bash
# =============================================================================
# MySafeCoding — Deep-dive inspector for scan output
# Use AFTER running mysafecoding-scan.sh
#
# Usage:
#   ./mysafecoding-inspect.sh ./ls-output/YYYYMMDD_HHMMSS
# =============================================================================

set -e
CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'; RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'

if [ -z "$1" ]; then
    # Auto-find most recent scan
    SCAN_DIR=$(ls -dt ./ls-output/*/ 2>/dev/null | head -1)
    if [ -z "$SCAN_DIR" ]; then
        echo "Usage: ./mysafecoding-inspect.sh ./ls-output/YYYYMMDD_HHMMSS"
        exit 1
    fi
    echo -e "${CYAN}Using most recent scan: $SCAN_DIR${RESET}"
else
    SCAN_DIR="$1"
fi

TRIVY="$SCAN_DIR/trivy-raw.json"
SEMGREP="$SCAN_DIR/semgrep-raw.json"
GITLEAKS="$SCAN_DIR/gitleaks-raw.json"

echo ""
echo -e "${BOLD}MySafeCoding CLI — Inspector — $SCAN_DIR${RESET}"
echo ""

# ── Menu ───────────────────────────────────────────────────────────────────
echo "What do you want to inspect?"
echo ""
echo "  1) All CRITICAL CVEs (Trivy)"
echo "  2) All HIGH CVEs (Trivy)"
echo "  3) Packages with fixes available"
echo "  4) All SAST findings by file (Semgrep)"
echo "  5) SAST findings — errors only"
echo "  6) All secrets found (Gitleaks)"
echo "  7) Full dependency inventory (what packages you have)"
echo "  8) Findings grouped by rule type"
echo "  9) Raw JSON explorer (Trivy)"
echo " 10) Raw JSON explorer (Semgrep)"
echo " 11) Raw JSON explorer (Gitleaks)"
echo ""
echo -n "Enter number: "
read -r choice

case "$choice" in
  1)
    echo -e "\n${RED}${BOLD}CRITICAL CVEs:${RESET}"
    jq -r '
      .Results[]?
      | .Target as $t
      | .Vulnerabilities[]?
      | select(.Severity=="CRITICAL")
      | "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
        "CVE:         \(.VulnerabilityID)",
        "Package:     \(.PkgName) v\(.InstalledVersion)",
        "Fix Version: \(.FixedVersion // "NO FIX AVAILABLE")",
        "File:        \($t)",
        "Title:       \(.Title // "N/A")",
        "CVSS Score:  \(.CVSS.nvd.V3Score // "N/A")",
        "Description: \(.Description // "N/A" | .[0:200])..."
    ' "$TRIVY" 2>/dev/null || echo "No CRITICAL CVEs found"
    ;;
  2)
    echo -e "\n${YELLOW}${BOLD}HIGH CVEs:${RESET}"
    jq -r '
      .Results[]?
      | .Target as $t
      | .Vulnerabilities[]?
      | select(.Severity=="HIGH")
      | "[\(.VulnerabilityID)] \(.PkgName) \(.InstalledVersion) → fix: \(.FixedVersion // "no fix") (\($t))"
    ' "$TRIVY" 2>/dev/null || echo "No HIGH CVEs found"
    ;;
  3)
    echo -e "\n${GREEN}${BOLD}Packages with fixes available:${RESET}"
    jq -r '
      .Results[]?
      | .Vulnerabilities[]?
      | select(.FixedVersion != null and .FixedVersion != "")
      | "\(.Severity)\t\(.PkgName)\t\(.InstalledVersion)\t→\t\(.FixedVersion)\t(\(.VulnerabilityID))"
    ' "$TRIVY" 2>/dev/null | sort -k1,1 | column -t || echo "None"
    ;;
  4)
    echo -e "\n${BOLD}All SAST findings by file:${RESET}"
    jq -r '
      .results[]?
      | "\(.path):\(.start.line)  [\(.extra.severity)] \(.check_id)\n  \(.extra.message | split("\n")[0])\n"
    ' "$SEMGREP" 2>/dev/null | sort || echo "No SAST findings"
    ;;
  5)
    echo -e "\n${RED}${BOLD}SAST ERROR findings only:${RESET}"
    jq -r '
      .results[]?
      | select(.extra.severity=="ERROR")
      | "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
        "Rule:    \(.check_id)",
        "File:    \(.path):\(.start.line)-\(.end.line)",
        "Message: \(.extra.message)",
        "Code:    \(.extra.lines | .[0:200])"
    ' "$SEMGREP" 2>/dev/null || echo "No ERROR findings"
    ;;
  6)
    echo -e "\n${RED}${BOLD}Secrets found:${RESET}"
    jq -r '
      .[]
      | "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
        "Rule:    \(.RuleID)",
        "Desc:    \(.Description)",
        "File:    \(.File):\(.StartLine)",
        "Commit:  \(.Commit // "filesystem scan")",
        "Match:   \(.Match | .[0:100])"
    ' "$GITLEAKS" 2>/dev/null || echo "No secrets found"
    ;;
  7)
    echo -e "\n${BOLD}Full dependency inventory:${RESET}"
    jq -r '
      .Results[]?
      | .Target as $t
      | "=== \($t) ===",
        (.Packages[]? | "  \(.Name) \(.Version)")
    ' "$TRIVY" 2>/dev/null || echo "No package inventory available"
    ;;
  8)
    echo -e "\n${BOLD}SAST findings grouped by rule:${RESET}"
    jq -r '[.results[]? | .check_id] | group_by(.) | map({rule: .[0], count: length}) | sort_by(-.count) | .[] | "\(.count)x  \(.rule)"' \
      "$SEMGREP" 2>/dev/null || echo "No SAST findings"
    ;;
  9)
    echo -e "\n${CYAN}Trivy raw JSON (first 100 lines):${RESET}"
    cat "$TRIVY" | jq '.' | head -100
    ;;
  10)
    echo -e "\n${CYAN}Semgrep raw JSON (first 100 lines):${RESET}"
    cat "$SEMGREP" | jq '.' | head -100
    ;;
  11)
    echo -e "\n${CYAN}Gitleaks raw JSON:${RESET}"
    cat "$GITLEAKS" | jq '.'
    ;;
  *)
    echo "Invalid choice"
    ;;
esac
echo ""
