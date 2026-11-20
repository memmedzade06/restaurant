<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Frontend\MenuController;
use App\Http\Controllers\Frontend\ContactController;
use App\Http\Controllers\Auth\EmailVerifyController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\TelegramVerifyController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    // Ana səhifədə göstərilən seçmə yeməklər (şəkli olanlardan təsadüfi 6 dənə)
    // items cədvəli yoxdursa (baza boşdur) bölmə sadəcə göstərilmir
    $featured = ! \Illuminate\Support\Facades\Schema::hasTable('items') ? collect() : \App\Models\Item::query()
        ->where('status', 1)
        ->whereNotNull('image')
        ->where('category_id', 3) // yalnız qazan yeməkləri
        ->inRandomOrder()
        ->take(6)
        ->get();

    return view('frontend.home.index', compact('featured'));
})->name('home');



// Menyu səhifəsi ləğv olunub: .env-dəki MENU_URL ünvanına yönləndirir
Route::get('/menu', function () {
    $url = config('app.menu_url');
    abort_unless($url, 404);

    return redirect()->away($url);
})->name('menu');
Route::get('/contact', [ContactController::class, 'index'])->name('contact');
// Masa rezervasiyası: planda masanı seç → tarix, saat, ad, soyad, nömrə
Route::get('/rezervasiya', [\App\Http\Controllers\Frontend\ReservationController::class, 'index'])->name('reservations');
Route::get('/rezervasiya/davam', [\App\Http\Controllers\Frontend\ReservationController::class, 'continue'])->name('reservations.continue');
Route::get('/rezervasiya/mesgul', [\App\Http\Controllers\Frontend\ReservationController::class, 'busy'])->middleware('throttle:60,1')->name('reservations.busy');
// rezerv etmək — yalnız təsdiqlənmiş hesab (boş-boşuna rezerv olmasın)
Route::post('/rezervasiya', [\App\Http\Controllers\Frontend\ReservationController::class, 'store'])->middleware(['auth', 'phone.verified', 'throttle:6,1'])->name('reservations.store');
Route::view('/mexfilik', 'frontend.legal.privacy')->name('privacy'); // Məxfilik siyasəti (Google girişi üçün tələb olunur)


Route::get('/get_items',[MenuController::class,'get_items_by_category'])->name('get_items_by_category');


/*
|--------------------------------------------------------------------------
| Qeydiyyat və giriş — telefon Telegram bot ilə təsdiqlənir
|--------------------------------------------------------------------------
*/
// Hədiyyə marketi: xalları hədiyyəyə çevirmək (baxmaq hamıya açıqdır, almaq — təsdiqlənmiş istifadəçiyə)
Route::get('/hediyye-marketi', [\App\Http\Controllers\Frontend\MarketController::class, 'index'])->name('market');
Route::post('/hediyye-marketi/{key}', [\App\Http\Controllers\Frontend\MarketController::class, 'buy'])
    ->middleware(['auth', 'phone.verified', 'throttle:10,1'])->name('market.buy');

// Dəvət linki və QR kod (profildə): kodu yadda saxlayıb qeydiyyata aparır
Route::get('/devet/{code}', [\App\Http\Controllers\Frontend\ReferralController::class, 'visit'])
    ->where('code', '[A-Za-z0-9]{4,16}')->middleware('throttle:30,1')->name('referral');

Route::middleware('guest')->group(function () {
    Route::get('/qeydiyyat', [RegisterController::class, 'show'])->name('register');
    Route::post('/qeydiyyat', [RegisterController::class, 'store'])->middleware('throttle:6,1');
    Route::get('/giris', [LoginController::class, 'show'])->name('login');

    // Google ilə davam et (qeydiyyat və giriş)
    Route::get('/auth/google', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'redirect'])->middleware('throttle:20,1')->name('google.redirect');
    Route::post('/giris', [LoginController::class, 'store'])->middleware('throttle:10,1');

    // Şifrəmi unutdum: nömrə → Telegram ilə təsdiq (və ya e-poçt → link) → yeni şifrə
    Route::controller(ForgotPasswordController::class)->prefix('sifremi-unutdum')->name('password.')->group(function () {
        Route::get('/', 'show')->name('request');
        Route::post('/', 'store')->middleware('throttle:6,1');
        Route::get('/telegram', 'telegram')->name('telegram');
        Route::get('/e-poct', 'emailSent')->name('email.sent');
        Route::get('/e-poct/{user}/{hash}', 'emailConfirm')->middleware(['signed', 'throttle:10,1'])->where('user', '\d+')->name('email.confirm');
        Route::get('/status', 'status')->middleware('throttle:60,1')->name('status');
        Route::get('/yeni', 'edit')->name('reset');
        Route::post('/yeni', 'update')->middleware('throttle:10,1')->name('update');
    });
});

// Google-dan qayıdış: həm giriş/qeydiyyat (qonaq), həm də "Google ilə təsdiqlə" (daxil olmuş) üçün
Route::get('/auth/google/callback', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'callback'])->middleware('throttle:20,1')->name('google.callback');

// E-poçtdakı təsdiq linki: imzalıdır, girişsiz də (məs. telefonda) açıla bilər
Route::get('/tesdiq/e-poct/{user}/{hash}', [EmailVerifyController::class, 'confirm'])
    ->middleware(['signed', 'throttle:10,1'])->where('user', '\d+')->name('verify.email.confirm');

Route::middleware('auth')->group(function () {
    Route::post('/cixis', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/tesdiq', [TelegramVerifyController::class, 'show'])->name('verify.telegram');
    Route::get('/tesdiq/status', [TelegramVerifyController::class, 'status'])->middleware('throttle:60,1')->name('verify.telegram.status');
    // E-poçt ilə qeydiyyat: məktubdakı "Təsdiqlə" düyməsi
    // Təsdiqlənməmiş profil Google hesabını bağlayıb təsdiqləyir
    Route::get('/tesdiq/google', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'link'])->middleware('throttle:20,1')->name('verify.google');
    Route::get('/tesdiq/e-poct', [EmailVerifyController::class, 'show'])->name('verify.email');
    Route::get('/tesdiq/e-poct/status', [EmailVerifyController::class, 'status'])->middleware('throttle:60,1')->name('verify.email.status');
    Route::post('/tesdiq/e-poct/yeniden', [EmailVerifyController::class, 'resend'])->middleware('throttle:3,1')->name('verify.email.resend');

    // Yalnız təsdiqlənmiş istifadəçilər
    Route::middleware('phone.verified')->group(function () {
        Route::get('/hesabim', fn () => view('frontend.account.index'))->name('account');
        // Profildə: Google hesabını bağla (sonra "Google ilə davam et" eyni hesaba aparsın)
        Route::get('/hesabim/google', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'link'])->middleware('throttle:20,1')->name('account.google');
        Route::get('/hediyyelerim', [\App\Http\Controllers\Frontend\PrizeController::class, 'index'])->name('prizes');
    });

    // "İndi saytda" siqnalları (açıq səhifədən avtomatik göndərilir)
    Route::post('/aktiv', [\App\Http\Controllers\Frontend\PresenceController::class, 'ping'])->middleware('throttle:poll')->name('presence.ping');
    Route::post('/aktiv/cixis', [\App\Http\Controllers\Frontend\PresenceController::class, 'leave'])->middleware('throttle:poll')->name('presence.leave');
});

Route::get('/oyunlar/onlayn', [\App\Http\Controllers\Frontend\PresenceController::class, 'online'])->middleware('throttle:poll')->name('games.online');

/*
| İki nəfərlik nərd: dəvətlər və oyun (yalnız təsdiqlənmiş istifadəçilər).
| Brauzer vəziyyəti tez-tez soruşduğu üçün limitlər yüksəkdir.
*/
Route::prefix('oyunlar/nerd')->name('games.nard.')->middleware(['game.enabled:nard', 'auth', 'phone.verified'])
    ->controller(\App\Http\Controllers\Frontend\NardOnlineController::class)->group(function () {
        Route::get('/oyuncular', 'players')->middleware('throttle:poll')->name('players');
        Route::post('/devet', 'invite')->middleware('throttle:game-action')->name('invite');
        Route::get('/devetler', 'inbox')->middleware('throttle:poll')->name('inbox');
        Route::post('/devet/{invite}/qebul', 'accept')->middleware('throttle:game-action')->name('invite.accept');
        Route::post('/devet/{invite}/redd', 'decline')->middleware('throttle:game-action')->name('invite.decline');
        Route::post('/devet/{invite}/legv', 'cancel')->middleware('throttle:game-action')->name('invite.cancel');
        Route::get('/oyun/{match}', 'show')->name('match');
        Route::get('/oyun/{match}/veziyyet', 'state')->middleware('throttle:poll')->name('match.state');
        Route::post('/oyun/{match}/zer', 'roll')->middleware('throttle:game-action')->name('match.roll');
        Route::post('/oyun/{match}/gedis', 'move')->middleware('throttle:game-action')->name('match.move');
        Route::post('/oyun/{match}/teslim', 'resign')->middleware('throttle:game-action')->name('match.resign');
    });

/*
|--------------------------------------------------------------------------
| Oyunlar — hamı oynaya bilər; xal və hədiyyələr təsdiqlənmiş istifadəçilərə
|--------------------------------------------------------------------------
*/
Route::prefix('oyunlar')->name('games.')->controller(\App\Http\Controllers\Frontend\GameController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    // Nərd müvəqqəti gizlədilə bilər (.env: NARD_ENABLED)
    Route::get('/nerd', 'nard')->middleware('game.enabled:nard')->name('nard');
    Route::post('/nerd/netice', 'nardResult')->middleware(['game.enabled:nard', 'auth', 'phone.verified', 'throttle:20,1'])->name('nard.result');
    Route::get('/stekan', 'stack')->name('stack');
    Route::get('/stekan/hava', 'stackWeather')->middleware('throttle:30,1')->name('stack.weather');
    Route::post('/stekan/basla', 'stackStart')->middleware(['auth', 'phone.verified', 'throttle:60,1'])->name('stack.start');
    Route::post('/stekan/hediyye', 'stackPrize')->middleware(['auth', 'phone.verified', 'throttle:10,1'])->name('stack.prize');
    Route::post('/stekan/netice', 'stackResult')->middleware(['auth', 'phone.verified', 'throttle:30,1'])->name('stack.result');
    Route::get('/carx-oyunu', 'wheel')->name('wheel');
    Route::post('/carx-oyunu/firlat', 'spin')->middleware('throttle:30,1')->name('wheel.spin');
    Route::post('/carx-oyunu/dayan', 'stop')->middleware('throttle:30,1')->name('wheel.stop');
    Route::post('/carx-oyunu/gotur', 'claim')->middleware(['auth', 'phone.verified', 'throttle:15,1'])->name('wheel.claim');
});
// Bilik Yarışı (sual oyunu): oyun serverdə gedir (düz cavab brauzerə getmir), mərhələ keçəndə xal yazılır
Route::prefix('oyunlar/bilik-yarisi')->name('games.quiz')->controller(\App\Http\Controllers\Frontend\QuizController::class)->group(function () {
    Route::get('/', 'show');
    Route::post('/basla', 'start')->middleware('throttle:30,1')->name('.start');
    Route::post('/cavab', 'answer')->middleware('throttle:120,1')->name('.answer');
    Route::post('/novbeti', 'next')->middleware('throttle:120,1')->name('.next');
});
// Krossvord ləğv olunub — köhnə linklər Bilik Yarışına aparır
Route::permanentRedirect('/oyunlar/krossvord', '/oyunlar/bilik-yarisi');
// Köhnə ünvan (Sual oyunu) — linklər və əlfəcinlər yeni səhifəyə aparır
Route::permanentRedirect('/oyunlar/suallar', '/oyunlar/bilik-yarisi');
// Köhnə ünvan (Bəxt çarxı): QR kodlar və köhnə linklər yeni səhifəyə yönləndirilir
Route::permanentRedirect('/oyunlar/bext-carxi', '/oyunlar/carx-oyunu');

/*
|--------------------------------------------------------------------------
| Hədiyyə Kranı — şarın içindən real hədiyyə (gündə 3 cəhd), qonaqlar üçün sınaq
|--------------------------------------------------------------------------
*/
// Müvəqqəti bağlana bilər: .env-də CLAW_ENABLED=false — bütün ünvanlar 404 olur
Route::prefix('oyunlar/hediyye-krani')->name('games.claw')->middleware('game.enabled:claw')->controller(App\Http\Controllers\Frontend\ClawController::class)->group(function () {
    Route::get('/', 'show');
    Route::post('/basla', 'start')->middleware('throttle:30,1')->name('.start');
    Route::post('/ac', 'open')->middleware('throttle:30,1')->name('.open');
    Route::post('/gotur', 'claim')->middleware(['auth', 'phone.verified', 'throttle:15,1'])->name('.claim');
});

/*
|--------------------------------------------------------------------------
| Admin panel — hədiyyə kodlarının yoxlanması (php artisan admin:make <telefon>)
|--------------------------------------------------------------------------
*/
// Rezervasiyalar: admin və ofisiantlar baxa bilər, ləğv etmək — yalnız admin (aşağıdakı qrupda)
Route::prefix('admin')->name('admin.')->middleware(['auth', 'staff'])->group(function () {
    Route::get('/rezervasiyalar', [\App\Http\Controllers\Admin\ReservationController::class, 'index'])->name('reservations');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', fn () => redirect()->route('admin.prizes'));
    Route::get('/hediyyeler', [\App\Http\Controllers\Admin\PrizeController::class, 'index'])->name('prizes');
    Route::post('/hediyyeler/{prize}/istifade', [\App\Http\Controllers\Admin\PrizeController::class, 'redeem'])->name('prizes.redeem');
    // hədiyyənin QR kodu: admin oxudub açanda kod istifadə olunur
    Route::get('/hediyyeler/qr/{code}', [\App\Http\Controllers\Admin\PrizeController::class, 'qr'])->where('code', '[A-Za-z0-9-]+')->name('prizes.qr');
    Route::post('/rezervasiyalar/{reservation}/legv', [\App\Http\Controllers\Admin\ReservationController::class, 'cancel'])->name('reservations.cancel');
    Route::get('/istifadeciler', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('users');
    Route::get('/istifadeciler/{user}/duzelis', [\App\Http\Controllers\Admin\UserController::class, 'edit'])->name('users.edit');
    Route::put('/istifadeciler/{user}', [\App\Http\Controllers\Admin\UserController::class, 'update'])->name('users.update');
    Route::delete('/istifadeciler/{user}', [\App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('users.destroy');
});
