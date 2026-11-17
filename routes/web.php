<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Frontend\MenuController;
use App\Http\Controllers\Frontend\ContactController;
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


Route::get('/get_items',[MenuController::class,'get_items_by_category'])->name('get_items_by_category');


/*
|--------------------------------------------------------------------------
| Qeydiyyat və giriş — telefon Telegram bot ilə təsdiqlənir
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/qeydiyyat', [RegisterController::class, 'show'])->name('register');
    Route::post('/qeydiyyat', [RegisterController::class, 'store'])->middleware('throttle:6,1');
    Route::get('/giris', [LoginController::class, 'show'])->name('login');
    Route::post('/giris', [LoginController::class, 'store'])->middleware('throttle:10,1');

    // Şifrəmi unutdum: nömrə → Telegram ilə təsdiq → yeni şifrə
    Route::controller(ForgotPasswordController::class)->prefix('sifremi-unutdum')->name('password.')->group(function () {
        Route::get('/', 'show')->name('request');
        Route::post('/', 'store')->middleware('throttle:6,1');
        Route::get('/telegram', 'telegram')->name('telegram');
        Route::get('/status', 'status')->middleware('throttle:60,1')->name('status');
        Route::get('/yeni', 'edit')->name('reset');
        Route::post('/yeni', 'update')->middleware('throttle:10,1')->name('update');
    });
});

Route::middleware('auth')->group(function () {
    Route::post('/cixis', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/tesdiq', [TelegramVerifyController::class, 'show'])->name('verify.telegram');
    Route::get('/tesdiq/status', [TelegramVerifyController::class, 'status'])->middleware('throttle:60,1')->name('verify.telegram.status');

    // Yalnız təsdiqlənmiş istifadəçilər
    Route::middleware('phone.verified')->group(function () {
        Route::get('/hesabim', fn () => view('frontend.account.index'))->name('account');
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
Route::prefix('oyunlar/nerd')->name('games.nard.')->middleware(['auth', 'phone.verified'])
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
    Route::get('/nerd', 'nard')->name('nard');
    Route::post('/nerd/netice', 'nardResult')->middleware(['auth', 'phone.verified', 'throttle:20,1'])->name('nard.result');
    Route::get('/stekan', 'stack')->name('stack');
    Route::get('/stekan/hava', 'stackWeather')->middleware('throttle:30,1')->name('stack.weather');
    Route::post('/stekan/basla', 'stackStart')->middleware(['auth', 'phone.verified', 'throttle:60,1'])->name('stack.start');
    Route::post('/stekan/hediyye', 'stackPrize')->middleware(['auth', 'phone.verified', 'throttle:10,1'])->name('stack.prize');
    Route::post('/stekan/netice', 'stackResult')->middleware(['auth', 'phone.verified', 'throttle:30,1'])->name('stack.result');
    Route::get('/hediyye-krani', 'claw')->name('claw');
    Route::get('/krossvord', 'crossword')->name('crossword');
    Route::get('/bext-carxi', 'wheel')->name('wheel');
    Route::post('/bext-carxi/firlat', 'spin')->middleware('throttle:15,1')->name('wheel.spin');
    Route::post('/bext-carxi/gotur', 'claim')->middleware(['auth', 'phone.verified', 'throttle:15,1'])->name('wheel.claim');
});

/*
|--------------------------------------------------------------------------
| Admin panel — hədiyyə kodlarının yoxlanması (php artisan admin:make <telefon>)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', fn () => redirect()->route('admin.prizes'));
    Route::get('/hediyyeler', [\App\Http\Controllers\Admin\PrizeController::class, 'index'])->name('prizes');
    Route::post('/hediyyeler/{prize}/istifade', [\App\Http\Controllers\Admin\PrizeController::class, 'redeem'])->name('prizes.redeem');
    Route::get('/istifadeciler', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('users');
});
