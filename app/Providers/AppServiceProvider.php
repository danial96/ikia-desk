<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
                $me = Auth::id();

                // Cache per user for 60 seconds — avoids heavy join on every page load
                $users = Cache::remember("sidebar_users_{$me}", 60, function () use ($me) {
                    $lastMsgSub = DB::table('messages')
                        ->select('conversation_id', DB::raw('MAX(created_at) as last_msg_at'))
                        ->whereNull('deleted_at')
                        ->groupBy('conversation_id');

                    return User::where('is_active', true)
                        ->where('id', '!=', $me)
                        ->leftJoinSub(
                            DB::table('conversation_members as cm1')
                                ->select('cm2.user_id', DB::raw('MAX(lm.last_msg_at) as last_msg_at'))
                                ->joinSub($lastMsgSub, 'lm', 'lm.conversation_id', '=', 'cm1.conversation_id')
                                ->join('conversation_members as cm2', function ($j) use ($me) {
                                    $j->on('cm2.conversation_id', '=', 'cm1.conversation_id')
                                      ->where('cm2.user_id', '!=', $me);
                                })
                                ->join('conversations as c', function ($j) {
                                    $j->on('c.id', '=', 'cm1.conversation_id')
                                      ->where('c.type', 'direct');
                                })
                                ->where('cm1.user_id', $me)
                                ->groupBy('cm2.user_id'),
                            'conv_last',
                            'conv_last.user_id',
                            '=',
                            'users.id'
                        )
                        ->orderByRaw('conv_last.last_msg_at IS NULL ASC')
                        ->orderByRaw('conv_last.last_msg_at DESC')
                        ->orderBy('users.name')
                        ->get(['users.*', 'conv_last.last_msg_at'])
                        ->map(function ($u) {
                            $u->is_online = $u->last_seen_at && \Carbon\Carbon::parse($u->last_seen_at)->diffInMinutes(now()) < 5;
                            return $u;
                        });
                });

                $view->with('onlineUsers', $users);
            }
        });
    }
}
