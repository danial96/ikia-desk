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
            'Images in chats and comments load as small previews (click one for the original, quality unchanged).',
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
];
