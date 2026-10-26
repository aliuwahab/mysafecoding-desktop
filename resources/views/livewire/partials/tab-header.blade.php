<div style="display:flex;align-items:baseline;gap:12px;padding:20px 32px 16px;border-bottom:1px solid var(--line-2);">
    <h2 class="serif" style="margin:0;font-size:22px;color:var(--fg-1);letter-spacing:-0.01em;">{{ $title }}</h2>
    <span class="mono" style="font-size:12px;color:var(--fg-3);">{{ $count }} results</span>
    <span style="flex:1;"></span>
    <span style="font-size:11.5px;color:var(--fg-3);">{{ $subtitle }}</span>
    @isset($promptTab)
        @include('livewire.partials.fix-prompt-button', ['tab' => $promptTab])
    @endisset
</div>
