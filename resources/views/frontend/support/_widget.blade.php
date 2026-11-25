{{--
    Sağ aşağı küncdə "Adminə yaz" pəncərəsi (chatbot kimi) — bütün səhifələrdə.
    Qonaq: daxil olmağa dəvət. İstifadəçi: söhbət (açılanda yüklənir). Admin: gələn mesajlara keçid.
    Mesajlar səhifəsinin özündə görünmür (orada söhbət onsuz da açıqdır).
--}}
@php($me = auth()->user())
@if ($me?->is_admin)
    @unless (request()->routeIs('admin.support*'))
        @php($adminUnread = \App\Models\SupportMessage::unreadForAdmin()->count())
        @php($adminLink = route('admin.support', $adminUnread ? ['filter' => 'unread'] : []))
        <a href="{{ $adminLink }}" class="chat-fab-label" tabindex="-1" aria-hidden="true">
            @if ($adminUnread)
                <b>{{ $adminUnread }} yeni mesaj</b> — cavab gözləyir
            @else
                <b>Mesajlar</b> — istifadəçilərin sualları
            @endif
            <span class="chat-typing"><i></i><i></i><i></i></span>
        </a>
        <a href="{{ $adminLink }}" class="chat-fab chat-fab-pulse" aria-label="Gələn mesajlar">
            <i class="fa fa-comments chat-fab-open"></i>
            <span class="chat-fab-badge" @if (! $adminUnread) hidden @endif>{{ $adminUnread }}</span>
        </a>
        <script>document.body.classList.add('has-chat-fab');</script>
    @endunless
@elseif (! request()->routeIs('support'))
    @php($userUnread = $me ? \App\Models\SupportMessage::unreadForUser($me->id)->count() : 0)
    @php($adminOnline = \App\Models\SupportMessage::adminOnline())
    <div class="chat-widget" id="chatWidget" @if ($me) data-unread-url="{{ route('support.unread') }}" @endif>
        <div class="chat-panel" id="chatPanel" role="dialog" aria-label="Adminə yaz" hidden>
            <div class="chat-panel-head">
                <img src="{{ asset('img/logo.png') }}" alt="" class="chat-panel-logo">
                <div class="chat-panel-title">
                    <b>50 Qəpik</b>
                    @include('frontend.support._status', ['online' => $adminOnline, 'off' => 'Sualınızı yazın — admin cavab verəcək'])
                </div>
                @if ($me)
                    <a href="{{ route('support') }}" class="chat-panel-btn" aria-label="Tam ekranda aç" title="Tam ekranda aç"><i class="fa fa-expand-alt"></i></a>
                @endif
                <button type="button" class="chat-panel-btn" data-chat-close aria-label="Bağla"><i class="fa fa-times"></i></button>
            </div>
            <div class="chat-panel-body">
                @if ($me)
                    @include('frontend.support._chat', [
                        'messages' => collect(),
                        'postUrl' => route('support.store'),
                        'pollUrl' => route('support.poll'),
                        'empty' => 'Salam! Rezervasiya, hədiyyələr, oyunlar və ya başqa mövzuda sualınızı yazın.',
                        'placeholder' => 'Mesajınızı yazın…',
                        'lazy' => true,
                    ])
                @else
                    <div class="chat-guest">
                        <i class="fa fa-comments"></i>
                        <p>Adminə yazmaq üçün hesabınıza daxil olun.</p>
                        <a href="{{ route('login') }}" class="btn btn-wine w-100 mb-2" data-chat-leave>Daxil ol</a>
                        <a href="{{ route('register') }}" class="btn btn-outline-secondary w-100" data-chat-leave>Qeydiyyat</a>
                    </div>
                @endif
            </div>
        </div>
        <button type="button" class="chat-fab-label" id="chatFabLabel" tabindex="-1" aria-hidden="true">
            @if ($userUnread)
                <b>Yeni mesajınız var!</b> Oxuyun
            @else
                <b>Sualınız var?</b> Bizə yazın
            @endif
            <span class="chat-typing" aria-hidden="true"><i></i><i></i><i></i></span>
        </button>
        <button type="button" @class(['chat-fab', 'chat-fab-pulse', 'is-online' => $adminOnline]) id="chatFab" data-online-ring aria-label="Adminə yaz" aria-expanded="false" aria-controls="chatPanel">
            <i class="fa fa-comments chat-fab-open"></i>
            <span class="chat-fab-badge" @if (! $userUnread) hidden @endif>{{ $userUnread }}</span>
        </button>
    </div>
    <script>
        (function () {
            var widget = document.getElementById('chatWidget');
            var panel = document.getElementById('chatPanel');
            var fab = document.getElementById('chatFab');
            var badge = fab.querySelector('.chat-fab-badge');
            var KEY = 'supportChatOpen';

            function setBadge(n) { badge.textContent = n; badge.hidden = !n; }

            function open(focus) {
                panel.hidden = false;
                widget.classList.add('is-open');
                fab.setAttribute('aria-expanded', 'true');
                setBadge(0);
                try { sessionStorage.setItem(KEY, '1'); } catch (e) {}
                var chatEl = panel.querySelector('[data-support-chat]');
                var chat = chatEl && window.SupportChat && window.SupportChat.get(chatEl);
                if (chat) chat.start();
                var ta = panel.querySelector('textarea');
                if (focus && ta) ta.focus();
            }
            function close() {
                panel.hidden = true;
                widget.classList.remove('is-open');
                fab.setAttribute('aria-expanded', 'false');
                try { sessionStorage.removeItem(KEY); } catch (e) {}
            }

            fab.addEventListener('click', function () { panel.hidden ? open(true) : close(); });
            document.getElementById('chatFabLabel').addEventListener('click', function () { open(true); });
            panel.querySelector('[data-chat-close]').addEventListener('click', close);
            // "Daxil ol" / "Qeydiyyat": pəncərə bağlanır və növbəti səhifədə yenidən açılmır
            panel.querySelectorAll('[data-chat-leave]').forEach(function (a) { a.addEventListener('click', close); });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) close(); });
            document.body.classList.add('has-chat-fab');

            // pəncərə bağlıdırsa, yeni cavab gəlib-gəlmədiyini arabir yoxlayırıq
            var url = widget.dataset.unreadUrl;
            if (url) {
                setInterval(function () {
                    if (!panel.hidden || document.hidden) return;
                    fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(function (r) { return r.ok ? r.json() : null; })
                        .then(function (d) {
                            if (!d) return;
                            setBadge(d.unread);
                            if (window.SupportChat) window.SupportChat.setOnline(d.admin_online);
                        })
                        .catch(function () {});
                }, 30000);
            }

            // başqa səhifəyə keçəndə açıq pəncərə açıq qalsın (support-chat.js yükləndikdən sonra)
            var wasOpen = false;
            try { wasOpen = sessionStorage.getItem(KEY) === '1'; } catch (e) {}
            if (wasOpen) window.addEventListener('DOMContentLoaded', function () { open(false); });
        })();
    </script>
@endif
