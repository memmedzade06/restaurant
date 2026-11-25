@extends('frontend.layouts.app')
@section('title', 'Hədiyyələrim - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Hesabım</span>
        <h1>Hədiyyələrim</h1>
        <p>Kodu restoranda ofisianta göstərin — istifadə olunan kod qüvvədən düşür</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container" style="max-width: 760px">
        <div class="side-card">
            <h5><i class="fa fa-gift me-2 text-primary"></i>Aktiv hədiyyələr <span class="online-count ms-1">{{ $active->count() }}</span></h5>
            @if ($active->isEmpty())
                <p class="text-muted mb-0">Hazırda aktiv hədiyyəniz yoxdur.</p>
            @else
                <ul class="prize-cards">
                    @foreach ($active as $p)
                        @include('frontend.prizes._card', ['p' => $p])
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- İstifadə olunmuş kuponlar (vaxtı keçmişlər göstərilmir) --}}
        <div class="side-card" id="istifade-olunub">
            <h5><i class="fa fa-check-circle me-2 text-primary"></i>İstifadə olunmuş kuponlar <span class="online-count ms-1">{{ $used->count() }}</span></h5>
            @if ($used->isEmpty())
                <p class="text-muted mb-0">Hələ istifadə etdiyiniz kupon yoxdur.</p>
            @else
                <ul class="prize-cards">
                    @foreach ($used as $p)
                        @include('frontend.prizes._card', ['p' => $p])
                    @endforeach
                </ul>
            @endif
        </div>

        <a href="{{ route('account') }}" class="btn btn-ghost-dark"><i class="fa fa-arrow-left me-2"></i>Hesabıma qayıt</a>
    </div>
</section>
@endsection
