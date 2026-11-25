@extends('frontend.layouts.app')
@section('title', 'Admin · Hamıya mesaj - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Admin panel · Mesajlar</span>
        <h1>Hamıya mesaj</h1>
        <p>Mesaj seçdiyiniz istifadəçilərin hamısının söhbətinə gedir — onlar onu profildə və chat-də görür</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        @include('frontend.admin._nav', ['active' => 'support'])

        @foreach (['success' => 'success', 'error' => 'danger'] as $key => $cls)
            @if (session($key))
                <div class="alert alert-{{ $cls }} admin-alert">{{ session($key) }}</div>
            @endif
        @endforeach

        <div class="row justify-content-center">
            <div class="col-lg-9">
                <a href="{{ route('admin.support') }}" class="btn btn-sm btn-outline-secondary mb-3"><i class="fa fa-arrow-left me-1"></i>Bütün söhbətlər</a>

                <div class="side-card">
                    <form method="POST" action="{{ route('admin.support.broadcast.store') }}" id="broadcastForm">
                        @csrf
                        <label class="form-label fw-bold" for="broadcastBody">Mesaj</label>
                        <textarea name="body" id="broadcastBody" rows="4" maxlength="{{ \App\Models\SupportMessage::MAX_LENGTH }}" class="form-control mb-1"
                                  placeholder="Məs.: Bu axşam saat 20:00-da canlı musiqi! Masanızı əvvəlcədən rezerv edin." required>{{ old('body') }}</textarea>
                        @error('body') <div class="text-danger small mb-2">{{ $message }}</div> @enderror

                        <div class="fw-bold mt-3 mb-2">Kimə</div>
                        @foreach ($audiences as $key => $a)
                            <label class="broadcast-audience">
                                <input type="radio" name="audience" value="{{ $key }}" data-count="{{ $a['count'] }}" @checked(old('audience', 'all') === $key)>
                                <span><b>{{ $a['label'] }}</b> <span class="badge bg-secondary ms-1">{{ $a['count'] }} nəfər</span>
                                    <small class="d-block text-muted">{{ $a['hint'] }}</small></span>
                            </label>
                        @endforeach
                        @error('audience') <div class="text-danger small mb-2">{{ $message }}</div> @enderror

                        <p class="small text-muted mt-3 mb-3"><i class="fa fa-info-circle me-1"></i>Ofisiant və adminlərə getmir. İstifadəçi cavab yazsa, söhbət <a href="{{ route('admin.support') }}">Mesajlar</a> siyahısında görünəcək.</p>
                        <button type="submit" class="btn btn-wine px-4"><i class="fa fa-paper-plane me-2"></i>Göndər</button>
                    </form>
                </div>

                <div class="side-card mt-4">
                    <h5 class="mb-3"><i class="fa fa-history me-2 text-primary"></i>Göndərilmiş mesajlar</h5>
                    @if ($history->isEmpty())
                        <p class="text-muted mb-0">Hələ hamıya mesaj göndərilməyib.</p>
                    @else
                        <div class="broadcast-history">
                            @foreach ($history as $b)
                                <div class="broadcast-item">
                                    <div class="broadcast-meta">
                                        <span>{{ $b->created_at->timezone('Asia/Baku')->format('d.m.Y H:i') }} · {{ $b->admin?->name ?? '—' }}</span>
                                        <span>{{ $b->audienceLabel() }} · <b>{{ $b->recipients }} nəfər</b></span>
                                    </div>
                                    <div class="broadcast-body">{{ $b->body }}</div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-3">{{ $history->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('js')
<script>
    // göndərməzdən əvvəl: neçə nəfərə gedəcəyini soruşuruq
    document.getElementById('broadcastForm').addEventListener('submit', function (e) {
        var r = this.querySelector('input[name="audience"]:checked');
        var n = r ? r.dataset.count : '?';
        if (!confirm('Mesaj ' + n + ' istifadəçinin söhbətinə göndəriləcək. Göndərilsin?')) e.preventDefault();
    });
</script>
@endsection
