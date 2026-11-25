@extends('frontend.layouts.app')
@section('title', 'Mesajlar - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">Mesajlar</span>
        <h1>Adminə yazın</h1>
        <p>Sualınızı yazın — cavab burada görünəcək. Yeni cavab gələndə menyuda bildiriş çıxacaq.</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="side-card">
                    @include('frontend.support._chat', [
                        'postUrl' => route('support.store'),
                        'pollUrl' => route('support.poll'),
                        'empty' => 'Hələ mesaj yoxdur. Rezervasiya, hədiyyələr, oyunlar və ya başqa mövzuda sualınızı yazın.',
                        'placeholder' => 'Sualınızı yazın…',
                    ])
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

