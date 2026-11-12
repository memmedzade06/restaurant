<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\Frontend\MenuController;
use App\Http\Controllers\Frontend\ContactController;

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
    $featured = \App\Models\Item::query()
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

