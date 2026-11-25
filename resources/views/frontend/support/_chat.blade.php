{{--
    Söhbət bloku (istifadəçi səhifəsi, admin səhifəsi və sağ aşağı küncdəki pəncərə üçün ortaq).
    $messages — SupportMessage::toChat() massivləri, $postUrl, $pollUrl, $empty — boş söhbət mətni, $placeholder,
    $lazy (istəyə görə) — mesajlar pəncərə açılanda yüklənir
--}}
<div class="chat" data-support-chat
     data-post="{{ $postUrl }}" data-poll="{{ $pollUrl }}"
     data-after="{{ $messages->last()['id'] ?? 0 }}" @if (! empty($lazy)) data-lazy="1" @endif>
    <div class="chat-box" aria-live="polite">
        <p class="chat-empty" @if ($messages->isNotEmpty()) hidden @endif>{{ $empty }}</p>
        @foreach ($messages as $m)
            <div class="chat-msg {{ $m['mine'] ? 'mine' : 'theirs' }}" data-id="{{ $m['id'] }}">
                <div class="chat-bubble">{{ $m['body'] }}</div>
                <div class="chat-meta">@if ($m['author']){{ $m['author'] }} · @endif{{ $m['time'] }}@if ($m['mine'])<span @class(['chat-ticks', 'read' => $m['read']]) title="{{ $m['read'] ? 'Oxunub' : 'Göndərilib' }}"><i class="fa {{ $m['read'] ? 'fa-check-double' : 'fa-check' }}"></i></span>@endif</div>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ $postUrl }}" class="chat-form">
        @csrf
        <textarea name="body" rows="2" maxlength="{{ \App\Models\SupportMessage::MAX_LENGTH }}"
                  placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}" required>{{ empty($lazy) ? old('body') : '' }}</textarea>
        <button type="submit" class="btn btn-wine chat-send" aria-label="Göndər"><i class="fa fa-paper-plane"></i></button>
    </form>
    <div class="chat-error" @if (! empty($lazy) || ! $errors->has('body')) hidden @endif>{{ empty($lazy) ? $errors->first('body') : '' }}</div>
    <p class="chat-hint">Enter — göndər, Shift+Enter — yeni sətir</p>
</div>
