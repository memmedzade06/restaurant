<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Frontend\ReservationController as Booking;
use App\Models\Reservation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Admin: masa rezervasiyalarının siyahısı (gün üzrə) və ləğvi */
class ReservationController extends Controller
{
    public function index(Request $request): View
    {
        $today = Booking::now()->toDateString();
        $date = (string) $request->query('date', '');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '';

        $query = Reservation::query()->with('user:id,name')->orderBy('date')->orderBy('time');
        // tarix seçilməyibsə — bu gündən sonrakı bütün rezervasiyalar
        $date ? $query->whereDate('date', $date) : $query->whereDate('date', '>=', $today);

        return view('frontend.admin.reservations', [
            'reservations' => $query->paginate(50)->withQueryString(),
            'date' => $date,
            'today' => $today,
        ]);
    }

    public function cancel(Reservation $reservation): RedirectResponse
    {
        $reservation->update(['status' => Reservation::STATUS_CANCELLED]);

        return back()->with('success', "{$reservation->name} — {$reservation->table_name} rezervasiyası ləğv olundu.");
    }
}
