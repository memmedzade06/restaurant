<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\WheelSpin;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Oyunçunun öz hədiyyələri: aktiv, istifadə olunmuş və vaxtı keçmiş kodlar */
class PrizeController extends Controller
{
    public function index(Request $request): View
    {
        $prizes = WheelSpin::prizes()->where('user_id', $request->user()->id)->latest('id')->get();
        $grouped = $prizes->groupBy(fn (WheelSpin $p) => $p->status());

        return view('frontend.prizes.index', [
            'active' => $grouped->get(WheelSpin::ACTIVE, collect()),
            'inactive' => $prizes->reject(fn (WheelSpin $p) => $p->isActive()),
        ]);
    }
}
