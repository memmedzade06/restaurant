<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Frontend\MenuController;
use App\Http\Controllers\Frontend\ContactController;
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
});

Route::middleware('auth')->group(function () {
    Route::post('/cixis', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/tesdiq', [TelegramVerifyController::class, 'show'])->name('verify.telegram');
    Route::get('/tesdiq/status', [TelegramVerifyController::class, 'status'])->middleware('throttle:60,1')->name('verify.telegram.status');

    // Yalnız təsdiqlənmiş istifadəçilər
    Route::middleware('phone.verified')->group(function () {
        Route::get('/hesabim', fn () => view('frontend.account.index'))->name('account');
    });
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
    Route::post('/stekan/netice', 'stackResult')->middleware(['auth', 'phone.verified', 'throttle:30,1'])->name('stack.result');
    Route::get('/bext-carxi', 'wheel')->name('wheel');
    Route::post('/bext-carxi/firlat', 'spin')->middleware('throttle:15,1')->name('wheel.spin');
    Route::post('/bext-carxi/gotur', 'claim')->middleware(['auth', 'phone.verified', 'throttle:15,1'])->name('wheel.claim');
});
