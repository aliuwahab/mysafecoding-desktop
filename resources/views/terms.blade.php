<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terms of Use · CheckMyCode</title>
    <meta name="description" content="Terms of use for CheckMyCode, the free and open-source security scanner. Your code is never stored.">
    @include('partials.favicons')
    @vite(['resources/css/app.css'])
    <style>
        :root {
            --font-display: "Instrument Serif", Georgia, serif;
            --font-sans: "Inter Tight", -apple-system, BlinkMacSystemFont, sans-serif;
            --font-mono: "JetBrains Mono", ui-monospace, Menlo, monospace;
            --accent: #f53003; --accent-hi: #ff6a48; --ok: #3dd68c;
            --bg-0: #0e0c0b; --bg-2: #1a1715; --line-1: #2b2623; --line-2: #20201d;
            --fg-1: #f5efe7; --fg-2: #bfb5a8; --fg-3: #867d71;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg-0); color: var(--fg-2); font-family: var(--font-sans); -webkit-font-smoothing: antialiased; line-height: 1.7; font-size: 15px; }
        a { color: var(--accent-hi); }
        header { border-bottom: 1px solid var(--line-1); }
        .wrap { max-width: 760px; margin: 0 auto; padding: 0 20px; }
        .bar { display: flex; align-items: center; justify-content: space-between; height: 56px; }
        .brand { display: flex; align-items: center; gap: 9px; color: var(--fg-1); text-decoration: none; font-family: var(--font-display); font-size: 21px; }
        .back { font-size: 13px; color: var(--fg-2); text-decoration: none; border: 1px solid var(--line-1); border-radius: 7px; padding: 6px 12px; }
        h1 { font-family: var(--font-display); font-weight: 400; color: var(--fg-1); font-size: 44px; line-height: 1.1; margin: 44px 0 6px; letter-spacing: -0.01em; }
        h2 { color: var(--fg-1); font-size: 17px; font-weight: 600; margin: 34px 0 8px; }
        .meta { color: var(--fg-3); font-size: 13px; margin-bottom: 28px; }
        .callout { background: color-mix(in srgb, var(--ok) 7%, var(--bg-2)); border: 1px solid color-mix(in srgb, var(--ok) 25%, var(--line-1)); border-radius: 12px; padding: 18px 20px; color: var(--fg-1); }
        .callout strong { color: var(--ok); }
        ul, ol { padding-left: 22px; }
        li { margin: 4px 0; }
        strong { color: var(--fg-1); }
        code, pre { font-family: var(--font-mono); font-size: 12.5px; }
        pre { background: var(--bg-2); border: 1px solid var(--line-2); border-radius: 10px; padding: 16px 18px; white-space: pre-wrap; color: var(--fg-2); }
        .caps { font-size: 13.5px; }
        footer { border-top: 1px solid var(--line-1); margin-top: 56px; padding: 22px 0 40px; font-size: 13px; color: var(--fg-3); }
    </style>
</head>
<body>
<header>
    <div class="wrap bar">
        <a class="brand" href="{{ url('/') }}">
            <img src="{{ asset('logo.svg') }}" width="24" height="24" alt="">
            CheckMyCode
        </a>
        <a class="back" href="{{ url('/') }}">← Back to the scanner</a>
    </div>
</header>

<main class="wrap">
    <h1>Terms of Use</h1>
    <div class="meta">Last updated: {{ $updated }}</div>

    <div class="callout">
        <strong>The short version:</strong> CheckMyCode is free and open source. Your code is sent to the scanner only to run your scan and is <strong>deleted as soon as the scan finishes</strong>. We never store, sell, share or train on it. Results are automated and come with no guarantees. Only scan code you're allowed to scan. <strong>By using the scanner on this website you agree to these terms.</strong>
    </div>

    <h2 id="1-about-these-terms">1. About these terms</h2>
    <p>These terms apply to the hosted scanner at <strong>{{ $host }}</strong> (the "Service"), run by the CheckMyCode maintainers ("we", "us"). By selecting files or a folder and pressing <strong>Scan</strong>, or otherwise using the Service, you agree to these terms. If you don't agree, please don't use the Service. You can run the same scanner yourself with the desktop app or the command-line tool instead.</p>
    <p>The CheckMyCode <em>software</em> (its source code, desktop app, CLI and Docker image) is licensed under the MIT License (see section 9), not under these terms.</p>

    <h2 id="2-the-service">2. The Service</h2>
    <p>The Service is free. We provide it as a community tool, with no account and no paid tier. We may change, limit (for example with rate limits or file-size limits) or stop the Service at any time, without notice. There is no guaranteed availability.</p>

    <h2 id="3-your-code-what-happens-to-it">3. Your code: what happens to it</h2>
    <ol>
        <li><strong>In your browser:</strong> folders you exclude (such as <code>node_modules</code>, <code>vendor</code> and <code>.git</code>), media and large binary files are filtered out before anything is sent. They never leave your device.</li>
        <li><strong>Sent over HTTPS:</strong> the remaining files are compressed and sent, encrypted in transit, to our server for this one scan.</li>
        <li><strong>Scanned in a temporary workspace:</strong> open-source scanners run against the files in a private, temporary folder.</li>
        <li><strong>Deleted immediately:</strong> the folder and every uploaded file are permanently deleted as soon as the scan finishes, whether it succeeds or fails. The scan summary shows the time of deletion. If a scan is interrupted (for example by a server restart), a cleanup task deletes anything left behind within 75 minutes.</li>
        <li><strong>Results expire:</strong> the scan results (findings, file names, line numbers and short code snippets around each finding) are kept temporarily so you can view them. They are tied to your browser session, not visible to anyone else, and automatically expire {{ $ttl }} minutes after the scan. Detected secret values are masked. Download a JSON or SARIF report if you want to keep them.</li>
    </ol>
    <p><strong>We do not store your code.</strong> We don't keep copies, back it up, sell it, share it, look through it, or use it to train any model. You keep all rights to your code. You give us only the limited permission needed to process it to run the scan you requested.</p>

    <h2 id="4-other-information-we-handle">4. Other information we handle</h2>
    <ul>
        <li><strong>Scan counter:</strong> we count completed scans per day (a date and a number) to show the community counter. Nothing about you or your project is recorded with it.</li>
        <li><strong>Server logs:</strong> like most websites, our web server keeps standard access logs (IP address, time, requested page, browser type) for security and abuse prevention. They are deleted after about 7 days. Your IP address is also used briefly to apply rate limits.</li>
        <li><strong>Cookies:</strong> we use only a session cookie and a security (CSRF) cookie, which the scanner needs to work. No analytics, advertising or tracking cookies.</li>
        <li><strong>Third parties:</strong> your source code is never sent to a third party. To check dependencies, the scanner compares package names and versions against public vulnerability databases it downloads to our server (Trivy and OSV.dev), audits GitHub Actions workflows offline, fetches open-source rule sets, and looks up runtime and framework versions (for example "Laravel 11" or "Node.js 20") on endoflife.date. Fonts and scripts are served from our own server.</li>
    </ul>

    <h2 id="5-acceptable-use">5. Acceptable use</h2>
    <p>You agree that you will:</p>
    <ul>
        <li>only scan code that you own or are authorized to scan;</li>
        <li>not upload content you are not allowed to share, and avoid uploading personal data about other people;</li>
        <li>not upload malware, or anything intended to harm, overload or break into the Service or other systems;</li>
        <li>not try to access other people's scans or results, bypass rate limits, or run automated or bulk scanning beyond normal use;</li>
        <li>not use the Service for anything unlawful.</li>
    </ul>
    <p>We may block access or stop a scan if we believe these terms are being broken or the Service is being abused.</p>

    <h2 id="6-scan-results-are-not-a-guarantee">6. Scan results are not a guarantee</h2>
    <p>Results are produced automatically by open-source tools and rules. They can miss real problems (false negatives) and report problems that don't exist (false positives). A clean result or a good grade does <strong>not</strong> mean your code is secure. The Service is not a security audit, penetration test or professional advice. You are responsible for reviewing the results and for any decisions or changes you make based on them.</p>

    <h2 id="7-security-issues-in-the-service">7. Security issues in the Service</h2>
    <p>If you find a vulnerability in CheckMyCode itself, please report it privately via <a href="{{ $github }}" rel="noopener">GitHub</a> rather than publicly, and don't access data that isn't yours while testing.</p>

    <h2 id="8-open-source-components">8. Open-source components</h2>
    <p>The scanner relies on open-source projects, each under its own license, including <a href="https://github.com/aquasecurity/trivy" rel="noopener">Trivy</a> (Apache-2.0), <a href="https://github.com/google/osv-scanner" rel="noopener">OSV-Scanner</a> (Apache-2.0), <a href="https://github.com/semgrep/semgrep" rel="noopener">Semgrep</a> (LGPL-2.1) and its rule registry, <a href="https://github.com/gitleaks/gitleaks" rel="noopener">Gitleaks</a> (MIT), <a href="https://github.com/zizmorcore/zizmor" rel="noopener">zizmor</a> (MIT), and data from <a href="https://endoflife.date" rel="noopener">endoflife.date</a>. Those projects are not affiliated with CheckMyCode and don't endorse it.</p>

    <h2 id="9-license">9. License</h2>
    <p>The CheckMyCode software is free and open source under the MIT License:</p>
    <pre>{{ $license }}</pre>

    <h2 id="10-no-warranty">10. No warranty</h2>
    <p class="caps">THE SERVICE AND THE SOFTWARE ARE PROVIDED "AS IS" AND "AS AVAILABLE", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING WITHOUT LIMITATION ANY WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE, ACCURACY, AVAILABILITY OR NON-INFRINGEMENT.</p>

    <h2 id="11-limitation-of-liability">11. Limitation of liability</h2>
    <p class="caps">TO THE MAXIMUM EXTENT PERMITTED BY LAW, WE WILL NOT BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, CONSEQUENTIAL OR PUNITIVE DAMAGES, OR ANY LOSS OF DATA, PROFITS, REVENUE OR BUSINESS, ARISING OUT OF OR RELATED TO YOUR USE OF (OR INABILITY TO USE) THE SERVICE OR ITS RESULTS, INCLUDING ANY SECURITY ISSUE THE SERVICE DID OR DID NOT REPORT. THE SERVICE IS FREE, AND OUR TOTAL LIABILITY FOR ANY CLAIM IS ZERO, OR THE SMALLEST AMOUNT ALLOWED BY APPLICABLE LAW.</p>
    <p>Some jurisdictions don't allow certain warranty exclusions or liability limits, so some of the above may not apply to you. Nothing in these terms limits rights you have under laws that can't be waived.</p>

    <h2 id="12-your-responsibility">12. Your responsibility</h2>
    <p>You agree to be responsible for the content you submit and how you use the Service, and to cover claims made against us by others that result from content you submitted without permission or from your breach of these terms, to the extent permitted by law.</p>

    <h2 id="13-changes">13. Changes</h2>
    <p>We may update these terms. The date at the top shows the latest version. If you keep using the Service after a change, the updated terms apply.</p>

    <h2 id="14-contact">14. Contact</h2>
    <p>Questions about these terms: reach the maintainer on <a href="{{ $github }}" rel="noopener">GitHub</a>.</p>
</main>

<footer>
    <div class="wrap">CheckMyCode · free and open source under the MIT License · <a href="{{ url('/') }}">Scanner</a> · <a href="{{ $github }}" rel="noopener">GitHub</a></div>
</footer>
</body>
</html>
