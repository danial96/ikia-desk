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
     * the handful of direct conversations you are in, and finds each one's newest message with one index seek.
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
            // One index seek per conversation (newest non-deleted message). A GROUP BY / MAX() over these
            // conversations was measured at ~260ms on production because MySQL reads every message in them.
            $rows = DB::table('conversations as c')
                ->whereIn('c.id', $others->keys())
                ->selectRaw('c.id, (select m.created_at from messages m where m.conversation_id = c.id and m.deleted_at is null order by m.id desc limit 1) as last_at')
                ->get();
            foreach ($rows as $r) {
                $uid = $others[$r->id] ?? null;
                if ($uid && $r->last_at && (!isset($lastByUser[$uid]) || $r->last_at > $lastByUser[$uid])) $lastByUser[$uid] = $r->last_at;
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
