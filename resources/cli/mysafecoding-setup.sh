#!/bin/bash
# =============================================================================
# MySafeCoding CLI — Setup Script
# Installs Trivy, Semgrep, and Gitleaks on macOS via Homebrew
# Run: chmod +x mysafecoding-setup.sh
# =============================================================================

set -e  # Exit on any error

# ── Colors ────────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
RESET='\033[0m'

# ── Helpers ───────────────────────────────────────────────────────────────────
ok()   { echo -e "${GREEN}✔${RESET}  $1"; }
info() { echo -e "${BLUE}→${RESET}  $1"; }
warn() { echo -e "${YELLOW}⚠${RESET}  $1"; }
fail() { echo -e "${RED}✘${RESET}  $1"; exit 1; }
step() { echo -e "\n${BOLD}${BLUE}── $1 ──────────────────────────────────────────${RESET}"; }

echo ""
echo -e "${BOLD}████████████████████████████████████████████████████${RESET}"
echo -e "${BOLD}   MySafeCoding CLI — Setup (macOS)    ${RESET}"
echo -e "${BOLD}████████████████████████████████████████████████████${RESET}"
echo ""

# ── Step 1: Check Homebrew ─────────────────────────────────────────────────
step "1. Checking Homebrew"
if command -v brew &>/dev/null; then
    ok "Homebrew found: $(brew --version | head -1)"
else
    fail "Homebrew not found. Install it first: https://brew.sh"
fi

# ── Step 2: Install Trivy ──────────────────────────────────────────────────
step "2. Installing Trivy (SCA — dependency vulnerability scanner)"
if command -v trivy &>/dev/null; then
    ok "Trivy already installed: $(trivy --version 2>&1 | head -1)"
else
    info "Installing via Homebrew..."
    brew install aquasecurity/trivy/trivy
    ok "Trivy installed: $(trivy --version 2>&1 | head -1)"
fi

# ── Step 3: Install Semgrep ────────────────────────────────────────────────
step "3. Installing Semgrep (SAST — static code analysis)"
if command -v semgrep &>/dev/null; then
    ok "Semgrep already installed: $(semgrep --version)"
else
    info "Installing via Homebrew..."
    brew install semgrep
    ok "Semgrep installed: $(semgrep --version)"
fi

# ── Step 4: Install Gitleaks ───────────────────────────────────────────────
step "4. Installing Gitleaks (Secrets detection)"
if command -v gitleaks &>/dev/null; then
    ok "Gitleaks already installed: $(gitleaks version 2>&1)"
else
    info "Installing via Homebrew..."
    brew install gitleaks
    ok "Gitleaks installed: $(gitleaks version 2>&1)"
fi

echo ""
echo -e "${BOLD}${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${RESET}"
echo -e "${BOLD}${GREEN}  All scanners installed successfully!               ${RESET}"
echo -e "${BOLD}${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${RESET}"
echo ""
echo "  Scanner versions:"
echo "  - Trivy:    $(trivy --version 2>&1 | head -1)"
echo "  - Semgrep:  $(semgrep --version)"
echo "  - Gitleaks: $(gitleaks version 2>&1)"
echo ""
echo -e "${BOLD}Next step:${RESET} Return to MySafeCoding and press Recheck, or from a checkout run:"
echo "  ./bin/mysafecoding scan /path/to/your/project"
echo ""
