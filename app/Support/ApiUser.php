<?php

namespace App\Support;

use App\Models\User;

/** How a person is described to the mobile app (login reply and /me). Never includes anything secret. */
class ApiUser
{
    public static function payload(User $u): array
    {
        return [
            'id'         => $u->id,
            'name'       => $u->name,
            'first_name' => $u->first_name,
            'last_name'  => $u->last_name,
            'email'      => $u->email,
            'role'       => $u->role,
            'position'   => $u->position,
            'phone'      => $u->phone,
            'avatar'     => $u->avatar_url,
            'time_zone'  => $u->viewTz(),
            // The private Pusher channel this person listens on (null when realtime is not set up on the server).
            'realtime_channel' => Realtime::enabled() ? Realtime::userChannel($u->id) : null,
            // What the app may offer. The server still checks every action; this only decides which buttons to show.
            'can' => [
                'view_all_tasks'  => $u->canViewAllTasks(),
                'create_tasks'    => $u->isAdmin() || !empty($u->permissions['create_tasks']),
                'create_projects' => $u->canCreateProjects(),
                'is_admin'        => $u->isAdmin(),
                'is_super_admin'  => $u->isSuperAdmin(),
            ],
            'notifications' => [
                'messages'       => (bool) $u->notify_messages,
                'messages_sound' => (bool) $u->notify_messages_sound,
                'tasks'          => (bool) $u->notify_tasks,
                'tasks_sound'    => (bool) $u->notify_tasks_sound,
            ],
        ];
    }
}
