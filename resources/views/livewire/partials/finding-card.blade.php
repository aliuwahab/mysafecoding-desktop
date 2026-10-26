{{-- Shared card for rule-style findings: Laravel rules, custom rules, IaC config, lifecycle. --}}
@php
  $lfp = $finding['fingerprint'] ?? (($finding['rule_id'] ?? '').':'.($finding['file_path'] ?? '').':'.($finding['line_start'] ?? ''));
  $lig = $this->isIgnored($scanner, $lfp);
  $lsevColors = ['CRITICAL'=>'var(--sev-critical)','HIGH'=>'var(--sev-high)','MEDIUM'=>'var(--sev-medium)','LOW'=>'var(--sev-low)','INFO'=>'var(--sev-info)'];
  $lsc = $lsevColors[$finding['severity'] ?? 'MEDIUM'] ?? 'var(--fg-3)';
@endphp
<div style="background:var(--bg-2);border:1px solid var(--line-1);border-radius:10px;padding:14px 16px;display:flex;align-items:flex-start;gap:14px;opacity:{{ $lig ? '0.45' : '1' }};">
  <div style="width:34px;height:34px;flex-shrink:0;border-radius:7px;background:color-mix(in srgb,var(--accent) 14%,transparent);border:1px solid color-mix(in srgb,var(--accent) 26%,transparent);display:grid;place-items:center;">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent-hi);">{!! $icon !!}</svg>
  </div>
  <div style="flex:1;min-width:0;">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:3px;flex-wrap:wrap;">
      <span style="display:inline-flex;align-items:center;font-size:9.5px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;padding:2px 6px;border-radius:3px;color:{{ $lsc }};background:color-mix(in srgb,{{ $lsc }} 14%,transparent);border:1px solid color-mix(in srgb,{{ $lsc }} 24%,transparent);font-family:var(--font-mono);">{{ $finding['severity'] ?? 'MEDIUM' }}</span>
      <span class="mono" style="font-size:11px;color:var(--fg-3);">{{ $finding['rule_id'] ?? '' }}</span>
      <span style="font-size:13px;color:var(--fg-1);font-weight:600;">{{ $finding['title'] ?? '' }}</span>
    </div>
    <div style="font-size:12.5px;color:var(--fg-2);line-height:1.5;margin-top:4px;">{{ $finding['description'] ?? '' }}</div>
    <div style="display:flex;align-items:center;gap:6px;font-size:11px;color:var(--fg-3);margin-top:6px;">
      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/></svg>
      <span class="mono" style="color:var(--fg-2);">{{ $finding['file_path'] ?? '' }}{{ !empty($finding['line_start']) ? ':'.$finding['line_start'] : '' }}</span>
      @if (!empty($finding['link']))
        <a href="{{ $finding['link'] }}" target="_blank" rel="noopener" style="color:var(--accent-hi);text-decoration:none;margin-left:6px;">Details ↗</a>
      @endif
    </div>
    @if (!empty($finding['code_snippet']))
      <pre style="margin-top:8px;padding:8px 12px;background:var(--log-bg);border:1px solid var(--line-2);border-radius:6px;font-size:11.5px;color:var(--fg-1);overflow-x:auto;white-space:pre-wrap;">{{ $finding['code_snippet'] }}</pre>
    @endif
    @if (!empty($finding['fix_guidance']))
      <div style="margin-top:6px;padding:8px 12px;background:color-mix(in srgb,var(--sev-ok) 8%,transparent);border:1px solid color-mix(in srgb,var(--sev-ok) 20%,transparent);border-radius:6px;font-size:11.5px;color:var(--sev-ok);">
        <strong>Fix:</strong> {{ $finding['fix_guidance'] }}
      </div>
    @endif
  </div>
  @if ($lig)
    <div style="display:flex;align-items:center;gap:4px;flex-shrink:0;">
      <span style="font-size:9.5px;font-weight:700;padding:2px 6px;border-radius:3px;color:var(--sev-medium);background:color-mix(in srgb,var(--sev-medium) 14%,transparent);" title="{{ $this->getIgnoreReason($scanner, $lfp) }}">IGNORED</span>
      <button wire:click="unignoreFinding('{{ $scanner }}', '{{ $lfp }}')" style="font-size:11px;padding:4px 8px;background:var(--bg-3);border:1px solid var(--line-1);border-radius:5px;color:var(--fg-2);">Remove</button>
    </div>
  @else
    <button wire:click="promptIgnore('{{ $scanner }}:{{ $lfp }}')" style="font-size:11px;padding:4px 10px;background:var(--bg-3);border:1px solid var(--line-1);border-radius:5px;color:var(--fg-2);flex-shrink:0;">Ignore</button>
  @endif
</div>
