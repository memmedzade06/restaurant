<?php

namespace Tests\Feature;

use App\Http\Controllers\Frontend\GameController;
use App\Http\Controllers\Frontend\QuizController;
use App\Models\User;
use App\Services\Referrals;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Xal kimə yazılır:
 *  - qonaq: oyunlardan (Stəkan bonusu, Bilik Yarışı) və dəvət linkinə görə;
 *  - ofisiant: yalnız dəvət linkinə görə;
 *  - admin: heç birindən.
 */
class PointsByUserTypeTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $type = User::TYPE_GUEST): User
    {
        $u = User::create([
            'name' => 'Xal Testi',
            'email' => 'x' . uniqid() . '@example.com',
            'phone' => '+99450' . random_int(1000000, 9999999),
            'password' => 'secret123',
        ]);
        $u->forceFill(['phone_verified_at' => now(), 'type' => $type])->save();

        return $u->fresh();
    }

    private function invokePrivate(object $controller, string $method, ...$args)
    {
        $m = new \ReflectionMethod($controller, $method);
        $m->setAccessible(true);

        return $m->invoke($controller, ...$args);
    }

    public function test_type_rules(): void
    {
        $this->assertTrue($this->user()->earnsGamePoints());
        $this->assertTrue($this->user()->earnsReferralPoints());
        $this->assertFalse($this->user(User::TYPE_WAITER)->earnsGamePoints());
        $this->assertTrue($this->user(User::TYPE_WAITER)->earnsReferralPoints());
        $this->assertFalse($this->user(User::TYPE_ADMIN)->earnsGamePoints());
        $this->assertFalse($this->user(User::TYPE_ADMIN)->earnsReferralPoints());
    }

    public function test_referral_points_go_to_guests_and_waiters_not_admins(): void
    {
        config(['referral.levels' => [0.5, 0.25, 0.125]]);

        foreach ([User::TYPE_GUEST => 0.5, User::TYPE_WAITER => 0.5, User::TYPE_ADMIN => 0.0] as $type => $expected) {
            $referrer = $this->user($type);
            $invited = $this->user();
            $invited->forceFill(['referred_by' => $referrer->id])->save();

            Referrals::reward($invited->fresh());

            $this->assertEqualsWithDelta($expected, (float) $referrer->fresh()->points, 0.0001, "{$type} dəvət xalı");
        }
    }

    public function test_admin_in_the_middle_of_chain_gets_nothing_but_others_do(): void
    {
        config(['referral.levels' => [0.5, 0.25, 0.125]]);
        $waiter = $this->user(User::TYPE_WAITER);   // 3-cü səviyyə
        $admin = $this->user(User::TYPE_ADMIN);     // 2-ci səviyyə
        $admin->forceFill(['referred_by' => $waiter->id])->save();
        $guest = $this->user();                      // 1-ci səviyyə
        $guest->forceFill(['referred_by' => $admin->id])->save();
        $invited = $this->user();
        $invited->forceFill(['referred_by' => $guest->id])->save();

        Referrals::reward($invited->fresh());

        $this->assertEqualsWithDelta(0.5, (float) $guest->fresh()->points, 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $admin->fresh()->points, 0.0001);
        $this->assertEqualsWithDelta(0.125, (float) $waiter->fresh()->points, 0.0001);
    }

    public function test_stack_bonus_only_for_guests(): void
    {
        config(['games.stack.bonus' => ['tiers' => [10 => 0.5]]]);
        $controller = app(GameController::class);

        foreach ([User::TYPE_GUEST => 0.5, User::TYPE_WAITER => 0.0, User::TYPE_ADMIN => 0.0] as $type => $expected) {
            $u = $this->user($type);
            Cache::flush();
            $given = $this->invokePrivate($controller, 'awardStackBonus', $u, 10);

            $this->assertEqualsWithDelta($expected, (float) $given, 0.0001, "{$type} Stəkan bonusu");
            $this->assertEqualsWithDelta($expected, (float) $u->fresh()->points, 0.0001);
        }
    }

    public function test_quiz_points_only_for_guests(): void
    {
        $controller = app(QuizController::class);

        foreach ([User::TYPE_GUEST => true, User::TYPE_WAITER => false, User::TYPE_ADMIN => false] as $type => $earns) {
            $u = $this->user($type);
            Cache::flush();
            $given = (float) $this->invokePrivate($controller, 'awardStage', $u, 3)[0]; // xal 3-cü mərhələdən

            $earns ? $this->assertGreaterThan(0, $given, "{$type} xal almalıdır") : $this->assertSame(0.0, $given, "{$type} xal almamalıdır");
            $this->assertEqualsWithDelta($given, (float) $u->fresh()->points, 0.0001);
        }
    }

    public function test_quiz_points_start_from_third_stage(): void
    {
        config(['quiz.points_from_stage' => 3, 'quiz.stage_points' => 0.25, 'quiz.stage_points_step' => 0.25]);
        $this->assertSame(0.0, (float) QuizController::stageReward(1));
        $this->assertSame(0.0, (float) QuizController::stageReward(2));
        $this->assertSame(0.25, QuizController::stageReward(3));
        $this->assertSame(0.5, QuizController::stageReward(4));
        $this->assertSame(1.0, QuizController::stageReward(6));
        $this->assertSame('3-cü', QuizController::ordinal(3));
        $this->assertSame('6-cı', QuizController::ordinal(6));

        // 1-ci və 2-ci mərhələ qonağa xal vermir
        $controller = app(QuizController::class);
        $u = $this->user();
        $this->assertSame(0.0, (float) $this->invokePrivate($controller, 'awardStage', $u, 1)[0]);
        $this->assertSame(0.0, (float) $this->invokePrivate($controller, 'awardStage', $u, 2)[0]);
        $this->assertSame(0.25, (float) $this->invokePrivate($controller, 'awardStage', $u, 3)[0]);
        $this->assertEqualsWithDelta(0.25, (float) $u->fresh()->points, 0.0001);

        // yeni oyunda 3-cü mərhələni təkrar keçmək xal vermir — yalnız növbəti mərhələlər
        $this->assertSame([0, true], $this->invokePrivate($controller, 'awardStage', $u, 3));
        $this->assertSame(0.0, (float) $this->invokePrivate($controller, 'awardStage', $u, 2)[0]);
        $this->assertSame(0.5, (float) $this->invokePrivate($controller, 'awardStage', $u, 4)[0]);
        $this->assertSame([0, true], $this->invokePrivate($controller, 'awardStage', $u, 4));
        $this->assertEqualsWithDelta(0.75, (float) $u->fresh()->points, 0.0001);

        // sabah yenidən 3-cü mərhələdən xal qazanmaq olur
        $this->travel(1)->days();
        $this->assertSame(0.25, (float) $this->invokePrivate($controller, 'awardStage', $u, 3)[0]);
        $this->assertEqualsWithDelta(1.0, (float) $u->fresh()->points, 0.0001);

        // səhifələrdə də 3-cü mərhələdən yazılır
        $this->actingAs($u)->get(route('games.quiz'))->assertOk()
            ->assertSee('3-cü mərhələdən')->assertDontSee('1-ci mərhələ:');
        $this->get(route('games.index'))->assertOk()->assertSee('3-cü mərhələdən artan xallar');
    }

    public function test_staff_see_no_points_warning(): void
    {
        $this->actingAs($this->user(User::TYPE_WAITER))->get(route('games.quiz'))
            ->assertOk()->assertDontSee('Oyunlardan xal yalnız qonaqlara yazılır.')->assertDontSee('ki, xal qazanasınız');
        $this->actingAs($this->user())->get(route('games.quiz'))
            ->assertOk()->assertDontSee('Oyunlardan xal yalnız qonaqlara yazılır.');
    }
}
