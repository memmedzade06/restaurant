<?php

namespace Tests\Feature;

use App\Http\Controllers\Frontend\ReservationController;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
    }

    private function tomorrow(): string
    {
        return ReservationController::now()->addDay()->toDateString();
    }

    /** Təsdiqlənmiş istifadəçi (e-poçt ilə, nömrəsiz — nömrəni formda yazır) */
    private function verifiedUser(array $attrs = []): User
    {
        $u = User::create($attrs + ['name' => 'Rezerv Testi', 'email' => 'v' . uniqid() . '@example.com', 'password' => 'secret123']);
        $u->forceFill(['email_verified_at' => now()])->save();

        return $u;
    }

    /** Rezerv et: hər dəfə yeni təsdiqlənmiş istifadəçi adından (və ya verilmiş $user adından) */
    private function book(array $over = [], ?User $user = null)
    {
        return $this->actingAs($user ?? $this->verifiedUser())->postJson(route('reservations.store'), $over + [
            'table' => '2-m20',
            'date' => $this->tomorrow(),
            'time' => '19:00',
            'name' => '  Əli   Məmmədov ',
            'phone' => '050 111 22 33',
        ]);
    }

    public function test_page_shows_both_floors_and_all_tables(): void
    {
        $res = $this->get(route('reservations'))->assertOk()
            ->assertSee('1-ci mərtəbə')->assertSee('2-ci mərtəbə')
            ->assertSee('Kabinet 1')->assertSee('id="rvModal"', false);

        foreach (array_keys(Reservation::tables()) as $key) {
            $res->assertSee('data-table="' . $key . '"', false);
        }
        $this->assertCount(13, config('reservations.floors.1.tables'));
        $this->assertCount(25, config('reservations.floors.2.tables'));
    }

    public function test_only_verified_accounts_can_book(): void
    {
        $data = ['table' => '2-m20', 'date' => $this->tomorrow(), 'time' => '19:00', 'name' => 'Əli Məmmədov', 'phone' => '050 111 22 33'];

        // daxil olmayan — 401, təsdiqlənməmiş — 403; heç bir rezervasiya yazılmır
        $this->postJson(route('reservations.store'), $data)->assertUnauthorized();
        $unverified = User::create(['name' => 'Təsdiqsiz', 'email' => 'u' . uniqid() . '@example.com', 'password' => 'secret123']);
        $this->actingAs($unverified)->postJson(route('reservations.store'), $data)->assertForbidden();
        $this->assertSame(0, Reservation::where('table_key', '2-m20')->whereDate('date', $this->tomorrow())->count());
    }

    public function test_form_is_open_to_everyone_and_login_is_asked_on_submit(): void
    {
        // qonaq formu görür; giriş xəbərdarlığı "Rezerv et" basılanda çıxır (səhifədə gizli hazırdır)
        auth()->logout();
        $this->get(route('reservations'))->assertOk()
            ->assertSee('id="rvSubmit"', false)->assertSee('name="phone"', false)
            ->assertSee('id="rvGate" hidden', false)
            ->assertSee('Son addım: rezervi təsdiqləmək üçün hesabınıza daxil olun.')
            ->assertSee(route('reservations.continue', ['to' => 'login']), false);

        $unverified = User::create(['name' => 'Təsdiqsiz', 'email' => 'u' . uniqid() . '@example.com', 'password' => 'secret123']);
        $this->actingAs($unverified)->get(route('reservations'))->assertOk()
            ->assertSee('id="rvSubmit"', false)->assertSee('Son addım: rezervi təsdiqləmək üçün profilinizi təsdiqləyin.');

        $this->actingAs($this->verifiedUser())->get(route('reservations'))->assertOk()
            ->assertSee('id="rvSubmit"', false)->assertDontSee('id="rvGate"', false);
    }

    public function test_continue_link_returns_to_reservations_after_login_or_verification(): void
    {
        auth()->logout();
        $this->get(route('reservations.continue', ['to' => 'login']))->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', route('reservations'));
        $this->get(route('reservations.continue', ['to' => 'register']))->assertRedirect(route('register'));

        // təsdiqlənməmiş: təsdiq səhifəsinə, təsdiqdən sonra (status) rezervasiyaya qayıdır
        $u = User::create(['name' => 'Təsdiqsiz', 'phone' => '+99450' . random_int(1000000, 9999999), 'password' => 'secret123']);
        $this->actingAs($u)->get(route('reservations.continue'))->assertRedirect(route($u->verificationRoute()));
        $u->forceFill(['phone_verified_at' => now()])->save();
        $this->actingAs($u)->getJson(route('verify.telegram.status'))->assertJson(['verified' => true, 'redirect' => route('reservations')]);
        // ikinci dəfə — adi yol (Oyunlar)
        $this->actingAs($u)->getJson(route('verify.telegram.status'))->assertJson(['redirect' => route('games.index')]);
    }

    public function test_phone_is_always_entered_and_account_phone_is_only_prefilled(): void
    {
        $u = $this->verifiedUser(['phone' => '+994551234567']);
        $this->actingAs($u)->get(route('reservations'))->assertOk()
            ->assertSee('name="phone"', false)->assertSee('value="+994 55 123 45 67"', false);

        // formda yazılan nömrə rezervasiyaya düşür
        $this->book(['phone' => '070 999 99 99'], $u)->assertOk();
        $this->assertSame('+994709999999', Reservation::latest('id')->first()->phone);
        // nömrəsiz göndərmək olmaz
        $this->book(['table' => '1-m1', 'phone' => ''], $u)->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_one_account_has_limited_upcoming_reservations_even_with_other_phones(): void
    {
        $u = $this->verifiedUser();
        foreach (['1-m1', '1-m2', '1-m3'] as $i => $t) {
            $this->book(['table' => $t, 'phone' => '050 111 22 4' . $i], $u)->assertOk();
        }
        $this->book(['table' => '1-m4', 'phone' => '050 111 22 49'], $u)->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_verified_user_can_book_a_table(): void
    {
        $this->book()->assertOk()->assertJson(['ok' => true])->assertJsonPath('busy.2-m20.0', '19:00');

        $r = Reservation::latest('id')->first();
        $this->assertSame('2-m20', $r->table_key);
        $this->assertSame('+994501112233', $r->phone);
        $this->assertSame('Əli Məmmədov', $r->name);
        $this->assertSame('2-ci mərtəbə · Masa 20', $r->table_name);
    }

    public function test_same_table_cannot_be_booked_within_hours(): void
    {
        config(['reservations.hours' => 2]);
        $this->book()->assertOk();
        $this->book(['time' => '20:30', 'phone' => '050 111 22 34'])->assertStatus(422)->assertJsonValidationErrors('time');
        // 2 saat sonra və ya başqa masa — olar
        $this->book(['time' => '21:00', 'phone' => '050 111 22 35'])->assertOk();
        $this->book(['table' => '1-m7', 'time' => '19:00', 'phone' => '050 111 22 36'])->assertOk();
    }

    public function test_cancelled_reservation_frees_the_table(): void
    {
        $this->book()->assertOk();
        Reservation::latest('id')->first()->update(['status' => Reservation::STATUS_CANCELLED]);
        $this->book(['phone' => '050 111 22 34'])->assertOk();
    }

    public function test_validation(): void
    {
        $this->book(['table' => 'yoxdur'])->assertStatus(422)->assertJsonValidationErrors('table');
        $this->book(['phone' => '123'])->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->book(['name' => ''])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->book(['time' => '03:00'])->assertStatus(422)->assertJsonValidationErrors('time');
        $past = ReservationController::now()->subDay()->toDateString();
        $this->book(['date' => $past])->assertStatus(422)->assertJsonValidationErrors('date');
        $far = ReservationController::now()->addDays(config('reservations.days_ahead') + 1)->toDateString();
        $this->book(['date' => $far])->assertStatus(422)->assertJsonValidationErrors('date');
    }

    public function test_one_phone_has_limited_upcoming_reservations(): void
    {
        $tables = ['1-m1', '1-m2', '1-m3', '1-m4'];
        foreach (array_slice($tables, 0, ReservationController::MAX_PER_PHONE) as $t) {
            $this->book(['table' => $t])->assertOk();
        }
        $this->book(['table' => '1-m4'])->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_busy_endpoint_returns_times_without_personal_data(): void
    {
        $this->book()->assertOk();
        $json = $this->getJson(route('reservations.busy', ['date' => $this->tomorrow()]))->assertOk()->json();

        $this->assertSame(['19:00'], $json['busy']['2-m20']);
        $this->assertStringNotContainsString('Məmmədov', json_encode($json, JSON_UNESCAPED_UNICODE));
        $this->getJson(route('reservations.busy', ['date' => 'pis']))->assertStatus(422);
    }

    public function test_logged_in_user_is_prefilled_and_linked(): void
    {
        $u = $this->verifiedUser(['name' => 'Rəşad Quliyev', 'phone' => '+994551234567']);

        $this->actingAs($u)->get(route('reservations'))->assertOk()
            ->assertSee('value="Rəşad Quliyev"', false)->assertSee('Ad, soyad')->assertSee('+994 55 123 45 67');
        $this->book([], $u)->assertOk();
        $this->assertSame($u->id, Reservation::latest('id')->first()->user_id);
    }

    public function test_admin_lists_and_cancels_reservations(): void
    {
        $this->book()->assertOk();
        $r = Reservation::latest('id')->first();
        auth()->logout();
        $admin = User::create(['name' => 'Admin', 'email' => 'a' . uniqid() . '@example.com', 'password' => 'secret123']);
        $admin->forceFill(['type' => User::TYPE_ADMIN])->save();
        $guest = User::create(['name' => 'Qonaq', 'email' => 'g' . uniqid() . '@example.com', 'password' => 'secret123']);

        $this->actingAs($guest)->get(route('admin.reservations'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.reservations', ['date' => $this->tomorrow()]))->assertOk()
            ->assertSee('Əli Məmmədov')->assertSee('2-ci mərtəbə · Masa 20')->assertSee('+994 50 111 22 33');

        $this->actingAs($admin)->post(route('admin.reservations.cancel', $r))->assertRedirect();
        $this->assertSame(Reservation::STATUS_CANCELLED, $r->fresh()->status);
    }
}
