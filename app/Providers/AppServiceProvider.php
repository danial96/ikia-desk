<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        View::composer('layouts.app', function ($view) {
            if (Auth::check()) {
                $view->with('onlineUsers', $this->teamRail(Auth::id()));
            }
        });
    }

    /**
     * The avatar rail on the right: every active colleague, people you chatted with most recently first.
     *
     * This runs on EVERY page. It used to aggregate MAX(created_at) over the whole messages table to find
     * "last message per conversation" (~330ms on production, on every single navigation). Now it only touches
     * the handful of direct conversations you are in, and finds each one's newest message through the (conversation_id, deleted_at, id) index.
     */
    private function teamRail(int $me)
    {
        // my direct conversations -> who is on the other side
        $others = DB::table('conversation_members as mine')
            ->join('conversations as c', function ($j) {
                $j->on('c.id', '=', 'mine.conversation_id')->where('c.type', 'direct');
            })
            ->join('conversation_members as theirs', function ($j) use ($me) {
                $j->on('theirs.conversation_id', '=', 'mine.conversation_id')->where('theirs.user_id', '!=', $me);
            })
            ->where('mine.user_id', $me)
            ->pluck('theirs.user_id', 'mine.conversation_id');           // conversation_id => other user id

        $lastByUser = [];
        if ($others->isNotEmpty()) {
            // NB whereIntegerInRaw, not whereIn: with ids sent as bound parameters MySQL planned this GROUP BY
            // badly and read every message in these conversations (~260ms, measured on production); with the
            // ids written into the SQL it uses the (conversation_id, deleted_at, id) index (~2ms).
            $newestIds = DB::table('messages')
                ->whereIntegerInRaw('conversation_id', $others->keys()->map(fn ($k) => (int) $k)->all())
                ->whereNull('deleted_at')
                ->groupBy('conversation_id')
                ->selectRaw('MAX(id) as id')
                ->pluck('id')->map(fn ($i) => (int) $i)->all();

            foreach (DB::table('messages')->whereIntegerInRaw('id', $newestIds)->get(['conversation_id', 'created_at']) as $m) {
                $uid = $others[$m->conversation_id] ?? null;
                if ($uid && (!isset($lastByUser[$uid]) || $m->created_at > $lastByUser[$uid])) $lastByUser[$uid] = $m->created_at;
            }
        }

        return User::where('is_active', true)
            ->where('id', '!=', $me)
            ->get()
            ->each(function ($u) use ($lastByUser) {
                $u->last_msg_at = $lastByUser[$u->id] ?? null;
                $u->is_online   = $u->last_seen_at && \Carbon\Carbon::parse($u->last_seen_at)->diffInMinutes(now()) < 5;
            })
            ->sort(function ($a, $b) {
                // people with a chat first, newest conversation on top; the rest alphabetically
                if (($a->last_msg_at === null) !== ($b->last_msg_at === null)) return $a->last_msg_at === null ? 1 : -1;
                if ($a->last_msg_at !== $b->last_msg_at) return strcmp((string) $b->last_msg_at, (string) $a->last_msg_at);
                return strcasecmp((string) $a->name, (string) $b->name);
            })
            ->values();
    }
}
