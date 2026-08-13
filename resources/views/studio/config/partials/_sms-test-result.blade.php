<div data-region="sms-test-result" style="margin-top:0.9rem;">
    <div class="studio-callout {{ $ok ? 'studio-callout--success' : 'studio-callout--danger' }}">
        <b>{{ $ok ? '✓ SMS sent successfully' : '✕ SMS was not sent' }}</b>
        <span style="opacity:.75;">· via {{ $provider }} → {{ $phone }}</span>
        <div style="margin-top:0.3rem;font-weight:600;">{{ $message }}</div>
        @unless ($ok)
            <div style="margin-top:0.4rem;font-size:0.78rem;opacity:.85;">This is the provider's own response. A "recharge" or "balance" message means your SMS account is out of credit — top it up with your SMS provider, then test again.</div>
        @endunless
    </div>
</div>
