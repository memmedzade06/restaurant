@extends('frontend.layouts.app')
@section('title', 'Əlaqə - 50 Qəpik')

@section('content')

<section class="page-hero">
    <div class="container">
        <span class="eyebrow">Bizi ziyarət edin</span>
        <h1>Bizimlə əlaqə</h1>
        <p>Masa sifarişi və suallarınız üçün bizə yazın və ya zəng edin</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-5">
                <a class="contact-card" href="tel:+994517171565">
                    <span class="ico"><i class="fa fa-phone-alt"></i></span>
                    <div>
                        <h5>Telefon</h5>
                        <p>+994 51 717 15 65</p>
                    </div>
                </a>
                <a class="contact-card" href="https://www.instagram.com/50qepik_elmler/" target="_blank" rel="noopener">
                    <span class="ico insta"><i class="fab fa-instagram"></i></span>
                    <div>
                        <h5>Instagram</h5>
                        <p>@50qepik_elmler</p>
                    </div>
                </a>
                <a class="contact-card" href="https://maps.google.com/?q=50QepikElmler" target="_blank" rel="noopener">
                    <span class="ico gold"><i class="fa fa-map-marker-alt"></i></span>
                    <div>
                        <h5>Ünvan</h5>
                        <p>Elmlər, Bakı — xəritədə aç</p>
                    </div>
                </a>
                <div class="cta-band mt-4" style="padding:30px">
                    <div>
                        <h2 style="font-size:24px">Masa sifarişi</h2>
                        <p>Zəng edin, sizin üçün yer ayıraq.</p>
                    </div>
                    <a href="tel:+994517171565" class="btn btn-primary">Zəng et</a>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="map-wrap">
                    <iframe loading="lazy" allowfullscreen title="50 Qəpik xəritədə"
                            src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d1519.6578035203752!2d49.81621445818276!3d40.379697241564664!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x40307d7c38b55a79%3A0xd9d0a11062a1c586!2s50QepikElmler!5e0!3m2!1saz!2saz!4v1702559532718!5m2!1saz!2saz"></iframe>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection
