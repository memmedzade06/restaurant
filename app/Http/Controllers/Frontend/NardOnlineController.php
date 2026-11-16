<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\NardInvite;
use App\Models\NardMatch;
use App\Models\User;
use App\Services\NardLeaderboard;
use App\Services\NardRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * İki nəfərlik nərd: saytdakı təsdiqlənmiş oyunçular bir-birini dəvət edir və oynayır.
 * Saytda canlı əlaqə (websocket) olmadığı üçün brauzer vəziyyəti bir neçə saniyədən bir soruşur.
 * Oyunun vəziyyəti serverdədir: zərləri server atır, hər gedişi NardRules ilə yoxlayır.
 * Xal yalnız bu oyunlarda verilir və günün cədvəlinə düşür (App\Services\NardLeaderboard):
 * oyun sona qədər oynanmalıdır, eyni iki oyunçu arasında gündə məhdud sayda oyun xal verir.
 */
class NardOnlineController extends Controller
{
    /** Dəvət oluna bilən oyunçular: saytdadır, özü deyil, hazırda oyunda deyil */
    public function players(Request $request): JsonResponse
    {
        $me = $request->user();
        $busy = $this->busyUserIds();
        $users = User::online()->where('id', '!=', $me->id)->orderByDesc('last_seen_at')->limit(30)->get(['id', 'name']);

        return response()->json([
            'players' => $users->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->short_name,
                'busy' => in_array($u->id, $busy, true),
            ]),
        ]);
    }

    /** Dəvət göndər */
    public function invite(Request $request): JsonResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer']]);
        $me = $request->user();
        $to = User::online()->find($data['user_id']);

        if (! $to || $to->id === $me->id) {
            return response()->json(['message' => 'Bu oyunçu hazırda saytda deyil.'], 422);
        }
        if (NardMatch::activeFor($me->id)) {
            return response()->json(['message' => 'Əvvəlcə davam edən oyununuzu bitirin.'], 422);
        }
        if (NardMatch::activeFor($to->id)) {
            return response()->json(['message' => 'Bu oyunçu hazırda başqa oyundadır.'], 422);
        }

        // köhnə gözləyən dəvətlərimi ləğv edirik — eyni anda bir dəvət
        NardInvite::where('from_user_id', $me->id)->where('status', 'pending')->update(['status' => 'cancelled']);

        $invite = NardInvite::create([
            'from_user_id' => $me->id,
            'to_user_id' => $to->id,
            'expires_at' => now()->addSeconds(NardInvite::TTL),
        ]);

        return response()->json(['invite' => ['id' => $invite->id, 'to' => $to->short_name, 'expires_in' => NardInvite::TTL]]);
    }

    /**
     * Saytın bütün səhifələri bunu bir neçə saniyədən bir soruşur:
     * gələn dəvətlər, göndərdiyim dəvətin vəziyyəti, davam edən oyunum.
     */
    public function inbox(Request $request): JsonResponse
    {
        $me = $request->user();
        $incoming = NardInvite::pending()->where('to_user_id', $me->id)->with('from:id,name')->latest('id')->limit(3)->get();
        $outgoing = NardInvite::where('from_user_id', $me->id)->latest('id')->first();
        $match = NardMatch::activeFor($me->id);

        $out = null;
        if ($outgoing && $outgoing->updated_at > now()->subSeconds(NardInvite::TTL + 30)) {
            $status = $outgoing->status === 'pending' && $outgoing->expires_at <= now() ? 'expired' : $outgoing->status;
            $out = [
                'id' => $outgoing->id,
                'status' => $status,
                'to' => optional($outgoing->to)->short_name,
                'match_url' => $outgoing->match_id ? route('games.nard.match', $outgoing->match_id) : null,
            ];
        }

        return response()->json([
            'incoming' => $incoming->map(fn (NardInvite $i) => [
                'id' => $i->id,
                'from' => $i->from->short_name,
                'expires_in' => max(0, now()->diffInSeconds($i->expires_at, false)),
            ]),
            'outgoing' => $out,
            'match_url' => $match ? route('games.nard.match', $match) : null,
        ]);
    }

    /** Dəvəti qəbul et — oyun yaranır */
    public function accept(Request $request, NardInvite $invite): JsonResponse
    {
        $me = $request->user();
        abort_unless((int) $invite->to_user_id === (int) $me->id, 403);

        return DB::transaction(function () use ($invite, $me) {
            $invite = NardInvite::whereKey($invite->id)->lockForUpdate()->first();
            if ($invite->status !== 'pending' || $invite->expires_at <= now()) {
                return response()->json(['message' => 'Dəvətin vaxtı keçib və ya ləğv olunub.'], 422);
            }
            if (NardMatch::activeFor($me->id) || NardMatch::activeFor($invite->from_user_id)) {
                $invite->update(['status' => 'cancelled']);

                return response()->json(['message' => 'Oyunçulardan biri artıq başqa oyundadır.'], 422);
            }

            // dəvət edən ağ daşlarla (oyunçu 0) oynayır
            $match = NardMatch::create([
                'player0_id' => $invite->from_user_id,
                'player1_id' => $me->id,
                'state' => NardRules::newGame(),
                'player0_seen_at' => now(),
                'player1_seen_at' => now(),
            ]);
            $invite->update(['status' => 'accepted', 'match_id' => $match->id]);
            // bu oyunçulara gələn digər dəvətlər artıq lazım deyil
            NardInvite::where('status', 'pending')
                ->whereIn('to_user_id', [$me->id, $invite->from_user_id])
                ->update(['status' => 'cancelled']);

            return response()->json(['match_url' => route('games.nard.match', $match)]);
        });
    }

    public function decline(Request $request, NardInvite $invite): JsonResponse
    {
        abort_unless((int) $invite->to_user_id === (int) $request->user()->id, 403);
        if ($invite->status === 'pending') {
            $invite->update(['status' => 'declined']);
        }

        return response()->json(['ok' => true]);
    }

    public function cancel(Request $request, NardInvite $invite): JsonResponse
    {
        abort_unless((int) $invite->from_user_id === (int) $request->user()->id, 403);
        if ($invite->status === 'pending') {
            $invite->update(['status' => 'cancelled']);
        }

        return response()->json(['ok' => true]);
    }

    /** Oyun səhifəsi — nərd lövhəsi iki nəfərlik rejimdə */
    public function show(Request $request, NardMatch $match): View
    {
        $seat = $match->seatOf($request->user());
        abort_if($seat === null, 403);
        NardLeaderboard::finalizePastDays();
        $opp = $seat === 0 ? $match->player1 : $match->player0;

        return view('frontend.games.nard', [
            'leaders' => NardLeaderboard::today(),
            'lastWinners' => NardLeaderboard::yesterdayWinners(),
            'myStats' => NardLeaderboard::myToday($request->user()->id),
            'match' => $match,
            'opponentName' => $opp->short_name,
        ]);
    }

    /** Oyunun vəziyyəti (brauzer hər ~1.5 saniyədən bir soruşur) */
    public function state(Request $request, NardMatch $match): JsonResponse
    {
        $seat = $match->seatOf($request->user());
        abort_if($seat === null, 403);

        $match = DB::transaction(function () use ($match, $seat) {
            $match = NardMatch::whereKey($match->id)->lockForUpdate()->first();
            $match->forceFill(["player{$seat}_seen_at" => now()]);
            // rəqib çoxdan səhifədə deyilsə — oyundan çıxmış sayılır, qalan oyunçu qalib gəlir
            $oppSeen = $match->{'player' . (1 - $seat) . '_seen_at'};
            if ($match->isPlaying() && $oppSeen && $oppSeen->lt(now()->subSeconds(NardMatch::SEEN_TIMEOUT))) {
                $this->finish($match, $seat, 'left');
            }
            $match->save();

            return $match;
        });

        return response()->json($this->payload($match, $seat));
    }

    /** Zər at — növbə mənimdirsə və hələ atmamışamsa */
    public function roll(Request $request, NardMatch $match): JsonResponse
    {
        return $this->act($request, $match, function (array $s, int $seat) {
            if ($s['turn'] !== $seat || $s['rolled']) {
                return null;
            }
            $s = NardRules::roll($s, $d1 = random_int(1, 6), $d2 = random_int(1, 6));
            if (! NardRules::legalMoves($s)) {
                // atılan zərlə heç bir gediş yoxdur — növbə rəqibə keçir, ekranda bu göstərilir
                $s = NardRules::passTurn($s);
                $s['skip'] = ['by' => $seat, 'rolled' => [$d1, $d2]];
            }

            return $s;
        });
    }

    /** Gediş et: from/to oyunçunun öz baxışındakı xanalardır (to = 'off' — daşı çıxar) */
    public function move(Request $request, NardMatch $match): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'integer', 'between:0,23'],
            'to' => ['required'],
            'die' => ['nullable', 'integer', 'between:1,6'],
        ]);
        $to = $data['to'] === 'off' ? 'off' : (is_numeric($data['to']) ? (int) $data['to'] : null);
        abort_if($to === null || (is_int($to) && ($to < 0 || $to > 23)), 422);

        return $this->act($request, $match, function (array $s, int $seat) use ($data, $to) {
            if ($s['turn'] !== $seat) {
                return null;
            }

            return NardRules::move($s, NardRules::fromView($data['from'], $seat), NardRules::fromView($to, $seat), $data['die'] ?? null);
        });
    }

    /** Təslim ol */
    public function resign(Request $request, NardMatch $match): JsonResponse
    {
        $seat = $match->seatOf($request->user());
        abort_if($seat === null, 403);

        $match = DB::transaction(function () use ($match, $seat) {
            $match = NardMatch::whereKey($match->id)->lockForUpdate()->first();
            if ($match->isPlaying()) {
                $this->finish($match, 1 - $seat, 'resigned');
                $match->save();
            }

            return $match;
        });

        return response()->json($this->payload($match, $seat));
    }

    /**
     * Oyunçunun hərəkəti: vəziyyəti kilidləyib dəyişir. $change null qaytarsa — hərəkət qaydalara uyğun deyil.
     */
    private function act(Request $request, NardMatch $match, callable $change): JsonResponse
    {
        $seat = $match->seatOf($request->user());
        abort_if($seat === null, 403);

        return DB::transaction(function () use ($match, $seat, $change) {
            $match = NardMatch::whereKey($match->id)->lockForUpdate()->first();
            if (! $match->isPlaying()) {
                return response()->json(['message' => 'Oyun bitib.'] + $this->payload($match, $seat), 422);
            }
            $s = $change($match->state, $seat);
            if ($s === null) {
                return response()->json(['message' => 'Bu gediş mümkün deyil.'] + $this->payload($match, $seat), 422);
            }
            $match->state = $s;
            $match->version++;
            $match->forceFill(["player{$seat}_seen_at" => now()]);
            if (($w = NardRules::winner($s)) !== null) {
                $this->finish($match, $w, 'finished');
            }
            $match->save();

            return response()->json($this->payload($match, $seat));
        });
    }

    private function finish(NardMatch $match, int $winner, string $reason): void
    {
        $s = $match->state;
        $match->status = 'finished';
        $match->winner = $winner;
        $match->mars = $reason === 'finished' && $s['off'][1 - $winner] === 0;
        $match->end_reason = $reason;
        $s['turn'] = null;
        // günün cədvəli üçün xal (yalnız sona qədər oynanan oyun)
        $s['awarded'] = NardLeaderboard::award($match);
        $match->state = $s;
        $match->version++;
    }

    /** Brauzerə göndərilən vəziyyət — həmişə oyunçunun öz baxışından (o, ağ daşlarla, oyunçu 0) */
    private function payload(NardMatch $match, int $seat): array
    {
        $s = $match->state;
        $legal = $match->isPlaying() && $s['turn'] === $seat && $s['dice']
            ? array_map(fn ($m) => [
                NardRules::fromView($m['from'], $seat),   // 12 xanalıq sürüşmə öz-özünün tərsidir
                NardRules::fromView($m['to'], $seat),
                $m['die'],
            ], NardRules::legalMoves($s))
            : [];
        $v = NardRules::viewFor($s, $seat);
        $oppSeen = $match->{'player' . (1 - $seat) . '_seen_at'};

        return [
            'version' => $match->version,
            'status' => $match->status,
            'pts' => $v['pts'],
            'off' => $v['off'],
            'turn' => $v['turn'],                 // 0 — mənim növbəm, 1 — rəqibin
            'rolled' => $v['rolled'],
            'dice' => $v['dice'],
            'opening' => $v['opening'],
            'last' => $v['last'] ?? [],
            'turnMoves' => $v['turnMoves'] ?? [],
            'legal' => $legal,
            'result' => $match->isPlaying() ? null : [
                'won' => (int) $match->winner === $seat,
                'mars' => $match->mars,
                'reason' => $match->end_reason,
                'points' => (int) $match->winner === $seat ? (int) ($s['awarded'] ?? 0) : 0, // qalibin bu oyundan aldığı xal
            ],
            'oppOnline' => $oppSeen && $oppSeen->gt(now()->subSeconds(20)),
            // son atışda gediş olmayıbsa: kim atdı (me / opp) və nə düşdü
            'skip' => isset($s['skip']) ? ['who' => $s['skip']['by'] === $seat ? 'me' : 'opp', 'rolled' => $s['skip']['rolled']] : null,
        ];
    }

    /** Hazırda oyunda olan istifadəçilər */
    private function busyUserIds(): array
    {
        return NardMatch::where('status', 'playing')->get(['player0_id', 'player1_id'])
            ->flatMap(fn ($m) => [(int) $m->player0_id, (int) $m->player1_id])->all();
    }
}
