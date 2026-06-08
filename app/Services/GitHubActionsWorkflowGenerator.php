<?php

namespace App\Services;

class GitHubActionsWorkflowGenerator
{
    public static function generate(array $exclusions = []): string
    {
        $excludeArgs = implode('', array_map(
            fn (string $exclusion): string => ' --exclude '.escapeshellarg($exclusion),
            array_values(array_unique(array_filter($exclusions))),
        ));

        $scanCommand = '/tmp/mysafecoding-scan.sh . --output-dir mysafecoding-results --non-interactive'.$excludeArgs;

        return <<<YAML
name: MySafeCoding

on:
  pull_request:
  push:
    branches: [main]
  workflow_dispatch:

permissions:
  contents: read

jobs:
  scan:
    name: Security scan
    runs-on: ubuntu-latest

    steps:
      - name: Checkout repository
        uses: actions/checkout@v4
        with:
          fetch-depth: 0

      - name: Install scanners
        run: |
          sudo apt-get update
          sudo apt-get install -y jq wget
          curl -sfL https://raw.githubusercontent.com/aquasecurity/trivy/main/contrib/install.sh | sudo sh -s -- -b /usr/local/bin
          python3 -m pip install --user semgrep
          wget -qO- https://github.com/gitleaks/gitleaks/releases/download/v8.24.2/gitleaks_8.24.2_linux_x64.tar.gz | sudo tar -xz -C /usr/local/bin gitleaks
          echo "\$HOME/.local/bin" >> "\$GITHUB_PATH"

      - name: Download MySafeCoding scanner
        run: |
          curl -fsSL https://raw.githubusercontent.com/aliuwahab/laraguard-desktop/main/resources/cli/mysafecoding-scan.sh -o /tmp/mysafecoding-scan.sh
          chmod +x /tmp/mysafecoding-scan.sh

      - name: Run MySafeCoding scan
        run: {$scanCommand}

      - name: Upload raw scan artifacts
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: mysafecoding-results
          path: mysafecoding-results

YAML;
    }
}
