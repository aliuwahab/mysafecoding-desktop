<?php

namespace App\Services;

class GitHubActionsWorkflowGenerator
{
    public static function generate(array $exclusions = [], string $image = 'ghcr.io/aliuwahab/mysafecoding:latest'): string
    {
        $excludeArgs = implode('', array_map(
            fn (string $exclusion): string => ' --exclude '.escapeshellarg($exclusion),
            array_values(array_unique(array_filter($exclusions))),
        ));

        $scanCommand = 'docker run --rm -v "$PWD:/src" -w /src '.$image
            .' scan /src --no-defaults'.$excludeArgs
            .' --output-dir /src/mysafecoding-results --format sarif --output /src/mysafecoding.sarif --fail-on critical';

        return <<<YAML
name: MySafeCoding

on:
  pull_request:
  push:
    branches: [main]
  workflow_dispatch:

permissions:
  contents: read
  security-events: write

jobs:
  scan:
    name: Security scan
    runs-on: ubuntu-latest

    steps:
      - name: Checkout repository
        uses: actions/checkout@v4
        with:
          fetch-depth: 0 # full history so secrets in past commits are found

      # Trivy (public CVEs + IaC + licenses), Semgrep, Gitleaks, rule packs and
      # end-of-life checks all ship inside the image. Change --fail-on to
      # high, medium, any or none to tune when the job fails.
      - name: Run MySafeCoding scan
        run: {$scanCommand}

      - name: Upload results to GitHub code scanning
        if: always()
        uses: github/codeql-action/upload-sarif@v3
        with:
          sarif_file: mysafecoding.sarif
          category: mysafecoding

      - name: Upload raw scan artifacts
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: mysafecoding-results
          path: mysafecoding-results

YAML;
    }
}
