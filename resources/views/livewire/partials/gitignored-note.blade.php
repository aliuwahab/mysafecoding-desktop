{{-- Explains findings skipped because the file is gitignored and was never committed. --}}
@if (($results['gitignored']['skipped_findings'] ?? 0) > 0)
  @php $ignoredFiles = $results['gitignored']['files'] ?? []; @endphp
  <div style="padding:12px 16px;background:color-mix(in srgb,var(--sev-low) 8%,transparent);border:1px solid color-mix(in srgb,var(--sev-low) 22%,transparent);border-radius:8px;font-size:12px;color:var(--fg-2);line-height:1.5;">
    Skipped {{ $results['gitignored']['skipped_findings'] }} {{ Str::plural('finding', $results['gitignored']['skipped_findings']) }} in files your <span class="mono">.gitignore</span> excludes:
    <span class="mono" style="color:var(--fg-1);">{{ implode(', ', array_slice($ignoredFiles, 0, 6)) }}{{ count($ignoredFiles) > 6 ? ', …' : '' }}</span>.
    They were never committed, so they aren't leaks. Secrets found in committed files or git history are always reported.
  </div>
@endif
