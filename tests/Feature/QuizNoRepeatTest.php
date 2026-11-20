<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Bilik Yarışı: bir oyunçuya gün ərzində eyni sual təkrar düşmür */
class QuizNoRepeatTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Cache::flush();
        // kiçik baza: 6 sual, mərhələdə 2 sual — tez bitsin
        config([
            'quiz.per_stage' => 2,
            'quiz.stages' => [[2, 0, 0]],
            'quiz.guest_stages' => 99,
            'quiz.questions' => array_map(fn ($i) => ['q' => "Sual $i?", 'a' => "Düz $i", 'w' => ["Səhv {$i}a", "Səhv {$i}b"], 'l' => 1], range(1, 6)),
        ]);
    }

    /** Oyunu başladıb bütün sualları düz cavablayır; verilən sualların mətnlərini qaytarır */
    private function playThrough(): array
    {
        $asked = [];
        $r = $this->postJson(route('games.quiz.start'));
        if ($r->status() === 409) {
            return [$asked, $r];
        }
        $q = $r->assertOk()->json('question');
        while (true) {
            $asked[] = $q['text'];
            $num = (int) filter_var($q['text'], FILTER_SANITIZE_NUMBER_INT);
            $choice = array_search("Düz $num", $q['options'], true);
            $a = $this->postJson(route('games.quiz.answer'), ['choice' => $choice])->assertOk();
            if ($a->json('allDone') || $a->json('gameOver')) {
                return [$asked, $a];
            }
            $n = $this->postJson(route('games.quiz.next'));
            if ($n->status() === 409) {
                return [$asked, $n];
            }
            $q = $n->json('question');
        }
    }

    public function test_no_question_repeats_within_a_day(): void
    {
        [$first] = $this->playThrough();
        $this->assertCount(6, $first);
        $this->assertCount(6, array_unique($first));

        // eyni gün yeni oyun — sual qalmayıb, təkrar verilmir
        $this->postJson(route('games.quiz.start'))->assertStatus(409)->assertJson(['todayDone' => true]);
    }

    public function test_questions_return_the_next_day(): void
    {
        $this->playThrough();
        $this->travel(1)->days();
        $this->postJson(route('games.quiz.start'))->assertOk()->assertJsonStructure(['question' => ['text']]);
    }

    public function test_second_game_same_day_gets_only_new_questions(): void
    {
        // birinci oyun: ilk sualda səhv cavab verib bitir (3 can) — yalnız bir neçə sual görülür
        $start = $this->postJson(route('games.quiz.start'))->assertOk()->json('question');
        $seen = [$start['text']];
        $num = (int) filter_var($start['text'], FILTER_SANITIZE_NUMBER_INT);
        $wrong = array_search("Səhv {$num}a", $start['options'], true);
        $this->postJson(route('games.quiz.answer'), ['choice' => $wrong])->assertOk();

        // ikinci oyun: əvvəlki sual yenidən düşmür
        [$second] = $this->playThrough();
        $this->assertEmpty(array_intersect($seen, $second));
        $this->assertCount(5, $second);
    }
}
