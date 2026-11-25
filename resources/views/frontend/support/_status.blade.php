{{-- Admin saytdadırsa yaşıl nöqtə ilə "Admin onlayndır"; support-chat.js özü yeniləyir. $online, $off — oflayn mətni --}}
<small @class(['chat-status', 'online' => $online]) data-chat-status data-on="Admin onlayndır" data-off="{{ $off }}">
    <span class="chat-status-dot" aria-hidden="true"></span><span class="chat-status-text">{{ $online ? 'Admin onlayndır' : $off }}</span>
</small>
