{{--
    Söhbət bloku (istifadəçi səhifəsi, admin səhifəsi və sağ aşağı küncdəki pəncərə üçün ortaq).
    $messages — SupportMessage::toChat() massivləri, $postUrl, $pollUrl, $empty — boş söhbət mətni, $placeholder,
    $lazy (istəyə görə) — mesajlar pəncərə açılanda yüklənir, $attach (istəyə görə) — fayl əlavə etmək olar (admin)
--}}
<div class="chat" data-support-chat
     data-post="{{ $postUrl }}" data-poll="{{ $pollUrl }}"
     data-after="{{ $messages->last()['id'] ?? 0 }}" @if (! empty($lazy)) data-lazy="1" @endif>
    <div class="chat-box" aria-live="polite">
        <p class="chat-empty" @if ($messages->isNotEmpty()) hidden @endif>{{ $empty }}</p>
        @foreach ($messages as $m)
            <div class="chat-msg {{ $m['mine'] ? 'mine' : 'theirs' }}" data-id="{{ $m['id'] }}">
                <div class="chat-bubble">@if ($m['file'] ?? null)@include('frontend.support._file', ['f' => $m['file']])@endif{{ \App\Models\SupportMessage::linkify($m['body']) }}@if ($m['link'] ?? null)<a href="{{ $m['link']['url'] }}" class="chat-link-btn" @if ($m['link']['external']) target="_blank" rel="noopener nofollow" @endif>{{ $m['link']['label'] }} <i class="fa fa-arrow-right ms-1"></i></a>@endif</div>
                <div class="chat-meta">@if ($m['author']){{ $m['author'] }} · @endif{{ $m['time'] }}@if ($m['mine'])<span @class(['chat-ticks', 'read' => $m['read']]) title="{{ $m['read'] ? 'Oxunub' : 'Göndərilib' }}"><i class="fa {{ $m['read'] ? 'fa-check-double' : 'fa-check' }}"></i></span>@endif</div>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ $postUrl }}" class="chat-form" @if (! empty($attach)) enctype="multipart/form-data" @endif>
        @csrf
        @if (! empty($attach))
            <label class="chat-attach" title="Fayl əlavə et (ən çox {{ \App\Models\SupportMessage::FILE_MAX_KB / 1024 }} MB)">
                <i class="fa fa-paperclip"></i>
                <input type="file" name="file" accept=".{{ str_replace(',', ',.', \App\Models\SupportMessage::FILE_TYPES) }}" hidden>
            </label>
        @endif
        <textarea name="body" rows="2" maxlength="{{ \App\Models\SupportMessage::MAX_LENGTH }}"
                  placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}" @if (empty($attach)) required @endif>{{ empty($lazy) ? old('body') : '' }}</textarea>
        <button type="submit" class="btn btn-wine chat-send" aria-label="Göndər"><i class="fa fa-paper-plane"></i></button>
    </form>
    @if (! empty($attach))<div class="chat-file-picked" hidden><i class="fa fa-paperclip me-1"></i><span></span> <button type="button" class="chat-file-clear" aria-label="Faylı sil">&times;</button></div>@endif
    <div class="chat-error" @if (! empty($lazy) || ! $errors->has('body')) hidden @endif>{{ empty($lazy) ? $errors->first('body') : '' }}</div>
    <p class="chat-hint">Enter — göndər, Shift+Enter — yeni sətir</p>
</div>
