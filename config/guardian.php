<?php

declare(strict_types=1);

use Masq\Guardian\Actions\BanAction;
use Masq\Guardian\Actions\FreezeAction;
use Masq\Guardian\Actions\QueueForReviewAction;
use Masq\Guardian\Decay\HalfLifeDecay;
use Masq\Guardian\Decay\LinearDecay;
use Masq\Guardian\Decay\NoDecay;
use Masq\Guardian\Detectors\ThrottleHitDetector;
use Masq\Guardian\Enums\TrustState;

return [

    /*
    |--------------------------------------------------------------------------
    | Tracks — independent trust tracks
    |--------------------------------------------------------------------------
    | Each track is self-contained for the same subject (own score,
    | thresholds, detectors, actions, state and ban). Define one per concern,
    | e.g. "default" (anti-cheat) and "behavior" (conduct/chat).
    |
    | Usage: Guardian::track('behavior')->inspect($user, $ctx),
    |        $user->trustState('behavior'), $user->isBanned('behavior').
    | An undefined track name falls back to the default track's rules.
    */
    'default_track' => 'default',

    /*
    | The trust-state ladder. Point this at your own enum (implementing
    | Masq\Guardian\Contracts\TrustStateContract) to add/rename states — your
    | enum cases are then used everywhere (thresholds, actions, middleware).
    */
    'state_enum' => TrustState::class,

    'tracks' => [

        'default' => [

            // Minimum live score -> trust state (ascending; highest reached wins).
            'thresholds' => [
                0 => TrustState::Trusted,
                20 => TrustState::Watch,
                50 => TrustState::Restricted,
                80 => TrustState::Review,
                120 => TrustState::Banned,
            ],

            // Worst state accumulated SOFT points can reach. Only a fatal hard
            // signal bans automatically. null = no clamp (soft can reach Banned).
            'soft_max_state' => TrustState::Review,

            // Action classes run when the subject ENTERS a state. Use the list
            // form below to pass the enum case directly, or a keyed map with
            // string keys (the state's value or name) — both work.
            'actions' => [
                ['state' => TrustState::Restricted, 'actions' => [FreezeAction::class]],
                ['state' => TrustState::Review,     'actions' => [QueueForReviewAction::class]],
                ['state' => TrustState::Banned,     'actions' => [QueueForReviewAction::class, BanAction::class]],
            ],

            // Checks for this track. Toggle with `enabled`; tune via options.
            'detectors' => [
                'throttle_hits' => [
                    'class' => ThrottleHitDetector::class,
                    'enabled' => true,
                    'allowed_hits' => 5,          // free hits in the window before scoring
                    'window_seconds' => 900,      // rolling counter window (15 min)
                    'base_points' => 12,          // points once the allowance is exceeded
                    'points_per_extra_hit' => 6,  // added per hit beyond the allowance
                    'max_points' => 100,
                    'decay' => 'half_life',
                ],
            ],

            // Detector key used by Guardian::recordThrottleHit() in this track.
            'throttle_detector' => 'throttle_hits',
        ],

        // 'behavior' => [
        //     'thresholds' => [0 => TrustState::Trusted, 70 => TrustState::Review],
        //     'detectors' => [
        //         'chat_filter' => ['class' => App\Guardian\Detectors\ChatFilterDetector::class],
        //     ],
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Decay strategies (shared by all tracks)
    |--------------------------------------------------------------------------
    | A signal references a strategy by key. "none" keeps points forever (hard
    | violations); "linear" fades to zero over N days; "half_life" halves them.
    */
    'decay' => [
        'default' => 'half_life',
        'strategies' => [
            'none' => ['class' => NoDecay::class],
            'linear' => ['class' => LinearDecay::class, 'days' => 30],
            'half_life' => ['class' => HalfLifeDecay::class, 'days' => 14],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache (shared)
    |--------------------------------------------------------------------------
    | Trust standing (score/state/banned) and throttle counters are cached so
    | hot reads never hit the database. `store` null = default cache store.
    */
    'cache' => [
        'store' => env('GUARDIAN_CACHE_STORE'),
        'ttl' => 86400,
        'prefix' => 'guardian',
    ],

    /*
    |--------------------------------------------------------------------------
    | Ban hook (shared)
    |--------------------------------------------------------------------------
    | BanAction calls this method on the subject if it exists, then fires the
    | Banned event.
    */
    'ban_method' => 'guardianBan',

    /*
    |--------------------------------------------------------------------------
    | Table names (shared)
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'events' => 'suspicion_events',
        'profiles' => 'trust_profiles',
        'reviews' => 'moderator_reviews',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pruning (shared)
    |--------------------------------------------------------------------------
    | Fully-decayed events older than this many days are deleted by
    | ReevaluateTrust. null = keep the full audit log forever.
    */
    'prune_after_days' => 180,
];
