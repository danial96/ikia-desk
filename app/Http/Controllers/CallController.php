<?php

namespace App\Http\Controllers;

use App\Models\Call;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use App\Models\User;
use App\Support\Realtime;
use App\Support\WebPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * One-to-one voice calls. The audio itself flows browser-to-browser (WebRTC); this controller is only
 * the switchboard: it rings people, relays the handshake messages (offer / answer / ICE candidates)
 * through the realtime channel, and writes the call into the chat as "Voice call · 2:31" / "Missed voice call".
 */
class CallController extends Controller
{
    /** Needs realtime push: signalling rides on it, so without Pusher there is nothing to ring with. */
    private function requireRealtime(): void
    {
        if (!Realtime::enabled()) abort(404);
    }

    private function findFor(int $id): Call
    {
        $call = Call::findOrFail($id);
        if (!$call->involves((int) auth()->id())) abort(403);
        $this->expireStale($call);
        return $call;
    }

    /** What every browser needs to show the call. Ids and names only. */
    private function state(Call $call, ?int $by = null): array
    {
        $call->loadMissing('caller', 'callee');
        $person = fn (?User $u) => $u ? ['id' => $u->id, 'name' => $u->name, 'avatar' => $u->avatar_url] : null;
        return [
            'id'          => $call->id,
            'status'      => $call->status,
            'caller'      => $person($call->caller),
            'callee'      => $person($call->callee),
            'conv_id'     => $call->conversation_id,
            'answered_at' => $call->answered_at?->timestamp,
            'duration'    => $call->duration,
            'by'          => $by,
        ];
    }

    private function broadcast(Call $call, ?int $by = null): array
    {
        $state = $this->state($call, $by);
        Realtime::publishToUsers([$call->caller_id, $call->callee_id], 'call.state', $state);
        return $state;
    }

    /** Close calls whose browsers disappeared, so nobody stays "busy" forever. */
    private function expireStale(?Call $only = null): void
    {
        $q = Call::stale();
        if ($only) $q->whereKey($only->id);
        foreach ($q->get() as $c) {
            // A call whose browsers vanished lasted until their last heartbeat, not until we noticed.
            $this->finish($c, $c->status === 'ringing' ? 'missed' : 'ended', null, $c->status === 'active' ? $c->updated_at : null);
        }
        if ($only) $only->refresh();
    }

    /** Move a live call to its final state, write it into the chat and tell both sides. Idempotent. */
    private function finish(Call $call, string $status, ?int $by = null, $endedAt = null): Call
    {
        if (!$call->isLive()) return $call;

        $answered = $call->answered_at !== null;
        $endedAt  = $endedAt ?? now();
        $call->forceFill([
            'status'   => $status,
            'ended_at' => $endedAt,
            'duration' => $answered ? (int) max(0, floor($call->answered_at->diffInSeconds($endedAt))) : null,
        ])->save();

        $this->logToChat($call);
        $this->broadcast($call, $by);

        if (!$answered) {      // the callee never got to talk: leave a bell + push so it isn't lost
            Notification::mention([$call->callee_id], $call->caller, 'Missed voice call from ' . $call->caller->name, null,
                $call->conversation_id ? '/chat?conv=' . $call->conversation_id : '/chat');
        }
        return $call;
    }

    private function logToChat(Call $call): void
    {
        if (!$call->conversation_id) return;
        $text = $call->answered_at
            ? '📞 Voice call · ' . intdiv((int) $call->duration, 60) . ':' . str_pad((string) ((int) $call->duration % 60), 2, '0', STR_PAD_LEFT)
            : '📞 Missed voice call';
        $msg = Message::create(['conversation_id' => $call->conversation_id, 'user_id' => $call->caller_id, 'content' => $text]);
        Realtime::publishToUsers([$call->caller_id, $call->callee_id], 'chat.changed',
            ['c' => $call->conversation_id, 'k' => 'new', 'm' => $msg->id, 'by' => $call->caller_id]);
    }

    private function directConversation(User $a, User $b): Conversation
    {
        $existing = Conversation::where('type', 'direct')
            ->whereHas('members', fn ($q) => $q->where('user_id', $a->id))
            ->whereHas('members', fn ($q) => $q->where('user_id', $b->id))->first();
        if ($existing) return $existing;

        $conv = Conversation::create(['type' => 'direct', 'created_by' => $a->id]);
        $conv->members()->attach([$a->id, $b->id]);
        return $conv;
    }

    private function busy(int $userId): bool
    {
        return Call::live()->where(fn ($q) => $q->where('caller_id', $userId)->orWhere('callee_id', $userId))->exists();
    }

    // ── endpoints ───────────────────────────────────────────────────────────

    /** ICE servers (STUN, plus TURN when configured) for the browser's RTCPeerConnection. */
    public function ice(): JsonResponse
    {
        $this->requireRealtime();
        return response()->json(['iceServers' => $this->iceServers()]);
    }

    private function iceServers(): array
    {
        $c = config('services.webrtc');
        $servers = [];

        $stun = array_values(array_filter(array_map('trim', explode(',', (string) ($c['stun'] ?? '')))));
        if ($stun) $servers[] = ['urls' => $stun];

        if (!empty($c['cf_turn_key_id']) && !empty($c['cf_turn_token'])) {
            // Cloudflare hands out short-lived relay credentials; ask once an hour, not per call.
            $cf = Cache::remember('webrtc.cf_ice', 3300, function () use ($c) {
                try {
                    $res = Http::timeout(5)->withToken($c['cf_turn_token'])
                        ->post("https://rtc.live.cloudflare.com/v1/turn/keys/{$c['cf_turn_key_id']}/credentials/generate-ice-servers", ['ttl' => 86400]);
                    return $res->successful() ? ($res->json('iceServers') ?? []) : [];
                } catch (\Throwable $e) {
                    Log::warning('Cloudflare TURN credentials failed: ' . $e->getMessage());
                    return [];
                }
            });
            // Their list repeats STUN; ours already has one, so keep only the relay entries.
            foreach ($cf as $s) if (!empty($s['username'])) $servers[] = $s;
        }

        $turn = array_values(array_filter(array_map('trim', explode(',', (string) ($c['turn_urls'] ?? '')))));
        if ($turn && !empty($c['turn_username']) && !empty($c['turn_credential'])) {
            $servers[] = ['urls' => $turn, 'username' => $c['turn_username'], 'credential' => $c['turn_credential']];
        }
        return $servers;
    }

    public function start(Request $request): JsonResponse
    {
        $this->requireRealtime();
        $request->validate(['callee_id' => 'required|integer|exists:users,id']);
        $me     = auth()->user();
        $callee = User::findOrFail($request->callee_id);

        if ($callee->id === $me->id) return response()->json(['error' => 'You cannot call yourself.'], 422);
        if (!$callee->is_active)     return response()->json(['error' => 'This person is not available.'], 422);

        $this->expireStale();
        if ($this->busy($me->id))     return response()->json(['error' => 'You are already on a call.', 'code' => 'self_busy'], 409);
        if ($this->busy($callee->id)) return response()->json(['error' => $callee->name . ' is on another call.', 'code' => 'busy'], 409);

        $conv = $this->directConversation($me, $callee);
        $call = Call::create(['caller_id' => $me->id, 'callee_id' => $callee->id, 'conversation_id' => $conv->id, 'status' => 'ringing']);

        $state = $this->broadcast($call, $me->id);

        // Ring phones/browsers that have Desk closed. The page-open case is covered by the realtime event above.
        WebPush::sendToUsers([$callee->id], [
            'title' => $me->name, 'body' => 'Incoming voice call', 'url' => '/?call=' . $call->id,
            'tag' => 'call-' . $call->id, 'type' => 'call', 'requireInteraction' => true, 'ttl' => 45, 'urgency' => 'high',
        ]);

        return response()->json(['ok' => true, 'call' => $state]);
    }

    public function show(int $id): JsonResponse
    {
        $this->requireRealtime();
        return response()->json(['call' => $this->state($this->findFor($id))]);
    }

    public function accept(int $id): JsonResponse
    {
        $this->requireRealtime();
        $call = $this->findFor($id);
        if ((int) $call->callee_id !== (int) auth()->id()) abort(403);
        if ($call->status !== 'ringing') return response()->json(['error' => 'This call is no longer ringing.', 'call' => $this->state($call)], 409);

        $call->forceFill(['status' => 'active', 'answered_at' => now()])->save();
        return response()->json(['ok' => true, 'call' => $this->broadcast($call, auth()->id())]);
    }

    /** Hang up / decline / cancel — which one it is depends on who presses it and whether it was answered. */
    public function end(int $id): JsonResponse
    {
        $this->requireRealtime();
        $call = $this->findFor($id);
        $me   = (int) auth()->id();

        $status = match (true) {
            $call->status === 'active'     => 'ended',
            $me === (int) $call->callee_id => 'declined',
            default                        => 'missed',     // the caller gave up before it was answered
        };
        $this->finish($call, $status, $me);
        return response()->json(['ok' => true, 'call' => $this->state($call->refresh())]);
    }

    /** Browsers ping while a call is answered so a vanished one can be detected. */
    public function ping(int $id): JsonResponse
    {
        $this->requireRealtime();
        $call = $this->findFor($id);
        if ($call->status === 'active') $call->touch();
        return response()->json(['ok' => true, 'status' => $call->status]);
    }

    /** Relay one handshake message to the other person's browser. */
    public function signal(Request $request, int $id): JsonResponse
    {
        $this->requireRealtime();
        $data = $request->validate([
            'type' => 'required|in:offer,answer,ice',
            'data' => 'required|array',
        ]);
        if (strlen(json_encode($data['data'])) > 20000) return response()->json(['error' => 'Signal too large.'], 422);

        $call = $this->findFor($id);
        if (!$call->isLive()) return response()->json(['ok' => false, 'status' => $call->status], 409);

        $me = (int) auth()->id();
        Realtime::publishToUsers([$call->otherId($me)], 'call.signal',
            ['id' => $call->id, 'type' => $data['type'], 'data' => $data['data'], 'from' => $me]);
        return response()->json(['ok' => true]);
    }
}
