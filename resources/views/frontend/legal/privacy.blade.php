@extends('frontend.layouts.app')
@section('title', 'Məxfilik siyasəti - 50 Qəpik')

@section('content')
<section class="page-hero page-hero-sm">
    <div class="container">
        <span class="eyebrow">50 Qəpik</span>
        <h1>Məxfilik siyasəti</h1>
        <p>Məlumatlarınızı necə toplayır və istifadə edirik</p>
    </div>
</section>

<section class="section pt-4">
    <div class="container">
        <article class="legal-card">
            <p class="legal-updated">Son yenilənmə: 19 noyabr 2026</p>

            <p>Bu səhifə <b>50 Qəpik</b> (Elmlər, Bakı) saytında — <b>50qepik.com</b> — qeydiyyatdan keçən və oyunlarda iştirak edən istifadəçilərin məlumatlarının necə toplandığını, istifadə edildiyini və qorunduğunu izah edir.</p>

            <h2>1. Hansı məlumatları toplayırıq</h2>
            <ul>
                <li><b>Ad və soyad</b> — qeydiyyatda yazdığınız və ya Google hesabınızdakı ad.</li>
                <li><b>Mobil nömrə</b> — Telegram ilə qeydiyyatdan keçmisinizsə. Nömrə Telegram-da sizin razılığınızla («Təsdiqlə» düyməsi) yoxlanılır.</li>
                <li><b>E-poçt ünvanı</b> — e-poçt və ya Google ilə qeydiyyatdan keçmisinizsə.</li>
                <li><b>Telegram söhbət identifikatoru</b> — Telegram ilə təsdiq zamanı, sizə bildiriş göndərə bilmək üçün.</li>
                <li><b>Google hesab identifikatoru</b> — «Google ilə davam et» istifadə etmisinizsə. Google-dan yalnız adınızı və e-poçtunuzu alırıq; Gmail məktublarınıza, kontaktlarınıza və ya digər məlumatlarınıza giriş istəmirik.</li>
                <li><b>Oyun nəticələri, xallar və hədiyyə kodları</b> — liderlər cədvəli və hədiyyələr üçün.</li>
                <li><b>Texniki məlumatlar</b> — sessiya üçün zəruri cookie-lər və saytda son aktivlik vaxtı («İndi saytda» siyahısı üçün).</li>
                <li><b>Ziyarət statistikası</b> — saytı yaxşılaşdırmaq üçün <b>Google Analytics</b> istifadə edirik: hansı səhifələrə baxıldığı, cihaz növü, təxmini şəhər/ölkə və saytda keçirilən vaxt anonim şəkildə toplanır. Bunun üçün Google Analytics cookie-ləri istifadə olunur; adınız, nömrəniz və e-poçtunuz Google Analytics-ə göndərilmir.</li>
            </ul>
            <p>Şifrəniz yalnız şifrələnmiş (hash) şəkildə saxlanılır — biz onu görə bilmirik.</p>

            <h2>2. Məlumatları nə üçün istifadə edirik</h2>
            <ul>
                <li>Hesabınızı yaratmaq, təsdiqləmək və hesabınıza daxil olmanızı təmin etmək;</li>
                <li>şifrə bərpası üçün (Telegram və ya e-poçt vasitəsilə);</li>
                <li>oyun nəticələrinizi, xallarınızı və liderlər cədvəlini göstərmək;</li>
                <li>qazandığınız hədiyyə kodlarını sizə çatdırmaq və restoranda yoxlamaq;</li>
                <li>dəvət (referal) xallarını hesablamaq;</li>
                <li>sui-istifadənin (eyni adamın çoxlu hesab açmasının) qarşısını almaq.</li>
            </ul>
            <p>Məlumatlarınızı reklam üçün istifadə etmirik və satmırıq.</p>

            <h2>3. Məlumatları kimlə paylaşırıq</h2>
            <p>Məlumatlarınızı üçüncü şəxslərə satmırıq və ötürmürük. Yalnız aşağıdakı xidmətlər işin texniki hissəsini yerinə yetirir:</p>
            <ul>
                <li><b>Telegram</b> — təsdiq və bildiriş mesajları üçün;</li>
                <li><b>Google</b> — «Google ilə davam et» girişi, e-poçt məktublarının göndərilməsi (Gmail) və ziyarət statistikası (Google Analytics) üçün;</li>
                <li><b>hostinq provayderi</b> — saytın və verilənlər bazasının saxlanması üçün.</li>
            </ul>
            <p>Liderlər cədvəlində adınız qısaldılmış şəkildə (soyad və adın baş hərfi) göstərilir; nömrəniz və e-poçtunuz heç vaxt açıq göstərilmir.</p>

            <h2>4. Məlumatların saxlanması və qorunması</h2>
            <p>Məlumatlar hesabınız mövcud olduğu müddətdə saxlanılır. Sayta bağlantı şifrələnmiş (HTTPS) kanalla qurulur, verilənlər bazasına giriş məhduddur.</p>

            <h2>5. Hüquqlarınız və hesabın silinməsi</h2>
            <p>Siz istənilən vaxt hesabınızdakı məlumatlar barədə soruşa, onların düzəldilməsini və ya hesabınızın tam silinməsini tələb edə bilərsiniz. Hesab silinəndə ad, nömrə, e-poçt, oyun nəticələri və hədiyyə kodları birdəfəlik silinir.</p>
            <p>Bunun üçün bizimlə əlaqə saxlayın:</p>
            <ul>
                <li>E-poçt: <a href="mailto:50qepikelmler50@gmail.com">50qepikelmler50@gmail.com</a></li>
                <li>Telefon: <a href="tel:+994517171565">+994 51 717 15 65</a></li>
                <li>Instagram: <a href="https://www.instagram.com/50qepik_elmler/" target="_blank" rel="noopener">@50qepik_elmler</a></li>
            </ul>

            <h2>6. Dəyişikliklər</h2>
            <p>Bu siyasət dəyişə bilər. Yeni versiya bu səhifədə «Son yenilənmə» tarixi ilə dərc olunur.</p>
        </article>
    </div>
</section>
@endsection
