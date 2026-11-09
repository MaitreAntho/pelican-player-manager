<?php

return [
    // Host the panel uses to query game servers. Empty: the allocation IP, or the node's FQDN
    // when the allocation listens on 0.0.0.0. Set it when the panel reaches nodes another way.
    'host' => env('PLAYER_MANAGER_HOST', ''),

    // Keep a history of who played and the daily peak, collected every minute.
    'history' => env('PLAYER_MANAGER_HISTORY', true),

    // Days of history kept.
    'retention_days' => (int) env('PLAYER_MANAGER_RETENTION_DAYS', 30),

    // Seconds between two refreshes of the player list on the page.
    'refresh' => (int) env('PLAYER_MANAGER_REFRESH', 15),
];
