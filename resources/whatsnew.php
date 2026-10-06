<?php

/*
 * Short, plain-English release notes shown in the "A new version of Desk is available" banner.
 * Newest release LAST, ids ascending. Add an entry with every deploy that people would notice.
 * A browser on an older version is shown every release newer than the one it loaded with (max 3).
 */
return [
    [
        'id' => 1, 'date' => '2026-10-02',
        'items' => [
            'Chat and task comments now arrive instantly.',
            'Browser notifications when Desk is closed (turn them on in Profile > Notifications).',
            'Messages up to 15,000 characters; file names are kept on download.',
        ],
    ],
    [
        'id' => 2, 'date' => '2026-10-03',
        'items' => [
            'New: voice calls from any one-to-one chat (phone icon in the chat header). The call opens in its own small window, so you can keep browsing.',
            'Voice notes: click the waveform to jump, choose 1x / 1.5x / 2x speed, and see a live level while recording.',
        ],
    ],
    [
        'id' => 3, 'date' => '2026-10-05',
        'items' => [
            'Faster everywhere: pages open noticeably quicker.',
            'The Kanban board no longer redraws everything each time someone edits a task.',
            'Fixed: Forward message did nothing. Fixed: voice calls failing to connect across networks.',
        ],
    ],
    [
        'id' => 4, 'date' => '2026-10-05',
        'items' => [
            'New: this notice. When Desk is updated, open tabs now tell you and list what changed, so a reload picks up the fixes.',
        ],
    ],
    [
        'id' => 5, 'date' => '2026-10-05',
        'items' => [
            'Behind the scenes: Desk now records how smoothly pages run on each computer (no content, just timings), to track down lag.',
        ],
    ],
    [
        'id' => 6, 'date' => '2026-10-05',
        'items' => [
            'Smoother on slower computers: the animated background no longer loops endlessly, and Desk can switch to a lighter look by itself (Profile > Notifications > Performance mode).',
        ],
    ],
    [
        'id' => 7, 'date' => '2026-10-05',
        'items' => [
            'Images are shown in their full original quality (the small-preview change was reverted).',
        ],
    ],
    [
        'id' => 8, 'date' => '2026-10-05',
        'items' => [
            'Chats, the chat list and tasks you opened before now appear instantly when you open them again; attachments and images are kept by the browser instead of being downloaded every time.',
        ],
    ],
    [
        'id' => 9, 'date' => '2026-10-05',
        'items' => [
            'Fixed: voice notes sent from the chat panel (the side chat) were not being sent.',
        ],
    ],
    [
        'id' => 10, 'date' => '2026-10-05',
        'items' => [
            "Task attachments: clicking an image now opens a viewer with next / previous arrows for the task's other images.",
        ],
    ],
    [
        'id' => 11, 'date' => '2026-10-06',
        'items' => [
            'Fixed: clicking a file attachment such as a .sql, .zip or .txt now downloads it instead of opening a new tab.',
        ],
    ],
];
