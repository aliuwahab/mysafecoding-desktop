{{-- Copies a ready-made prompt that tells a coding agent how to fix this tab's open findings. --}}
@php $fixPrompt = \App\Scanning\FixPrompt::forTab($tab, $results ?? [], $ignoredFindings ?? [], $projectPath ? basename($projectPath) : null); @endphp
@if ($fixPrompt)
<div wire:key="fix-prompt-{{ $tab }}-{{ md5($fixPrompt) }}" style="display:inline-flex;align-self:center;margin-left:12px;"
     x-data="{
        copied: false,
        copy() {
            const text = this.$refs.prompt.value;
            const done = () => { this.copied = true; setTimeout(() => this.copied = false, 2500); };
            const fallback = () => { this.$refs.prompt.select(); document.execCommand('copy'); done(); };
            navigator.clipboard && window.isSecureContext ? navigator.clipboard.writeText(text).then(done, fallback) : fallback();
        },
     }">
    <textarea x-ref="prompt" readonly tabindex="-1" aria-hidden="true" style="position:fixed;left:-9999px;top:0;width:1px;height:1px;opacity:0;">{{ $fixPrompt }}</textarea>
    <button type="button" @click="copy()"
            title="Copies a prompt for Claude Code, Cursor, Copilot or any coding agent: it lists these issues and asks for fixes that keep existing functionality unchanged. Secret values are never included."
            style="display:inline-flex;align-items:center;gap:6px;height:28px;padding:0 11px;font-size:11.5px;font-weight:600;color:var(--fg-1);background:var(--bg-2);border:1px solid var(--line-1);border-radius:7px;white-space:nowrap;">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="color:var(--accent-hi);"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3Z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15Z"/></svg>
        <span x-text="copied ? 'Copied! Paste it into your coding agent' : 'Copy fix prompt'">Copy fix prompt</span>
    </button>
</div>
@endif
