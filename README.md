# Masq Guardian

A trust & abuse-scoring engine for Laravel.

Guardian watches any model (usually your `User`), gives it **suspicion points**
when something looks off, lets those points **fade over time**, and as they add
up moves the subject through trust states — *trusted → watch → restricted →
review → banned*. Reaching "review" opens a moderator case; only a certain,
physically-impossible violation bans automatically. It's domain-agnostic: it
knows nothing about your app, only about points and states.

---

## How it works (the whole idea in one minute)

```
            you call a check
                  │
            ┌─────▼──────┐     returns a
            │  Detector  │─────────────────▶  Signal  (points + how they decay)
            └────────────┘                      │
                                                ▼
                                     stored as a suspicion event
                                                │
            ┌───────────────────────────────────▼─────────────────────────┐
            │  ENGINE: sum the still-"alive" (decayed) points  =  score     │
            │  compare score to thresholds            =  trust state        │
            │  state got worse?                       =  run actions        │
            └───────────────────────────────────┬─────────────────────────┘
                                                ▼
   trusted ──▶ watch ──▶ restricted ──▶ review ──▶ banned
                         (soft points stop here ⤴)     ▲
                                          fatal signal ─┘
```

Four things to know:

1. **A signal is one observation.** "Failed login #7", "impossible step count".
   It carries some **points** and a **decay** rule (how fast those points fade).
2. **The score is recomputed, not stored.** At any moment it's the sum of every
   signal's *remaining* (decayed) points. Good behaviour over time → score
   drops → the subject recovers on its own.
3. **Thresholds turn the score into a state.** You define the boundaries.
4. **Soft vs. hard is the safety rule.** Soft signals (heuristics) can climb to
   `review` at most — a human decides. Only a **fatal** hard signal (something
   physically impossible) bans automatically.

A **track** is an independent copy of all of this. The same user can have a
separate `default` (anti-cheat) track and a `behavior` (chat conduct) track —
different points, thresholds, detectors and bans, scored separately.

---

## Install

```bash
composer require masq/guardian
php artisan vendor:publish --tag=guardian-config   # optional: gives you config/guardian.php
php artisan migrate                                 # migrations auto-load
```

Add the `Guardable` trait to the model you want to score:

```php
use Masq\Guardian\Concerns\Guardable;

class User extends Authenticatable
{
    use Guardable;

    // Optional hooks Guardian calls when a state is entered:
    public function guardianRestrict(\Masq\Guardian\Enums\TrustState $state, array $ctx = []): void
    {
        // soft restriction — e.g. freeze rewards
    }

    public function guardianBan(array $ctx = []): void
    {
        $this->tokens()->delete(); // your definition of "banned"
    }
}
```

---

## Quick start

```php
use Masq\Guardian\ValueObjects\Signal;

// 1. Record an observation (points fade with the default decay)
$user->raiseSuspicion(Signal::soft('login_velocity', 15, ['ip' => $ip]));

// 2. Read where the subject stands (served from cache, not the DB)
$user->trustState();      // TrustState enum: Trusted / Watch / Restricted / Review / Banned
$user->suspicionScore();  // int — current, decayed score
$user->isBanned();
$user->isFlagged();       // worse than Trusted
$user->needsReview();     // has an open moderator case

// 3. Protect a route
Route::post('/play', ...)->middleware('guardian:banned');
```

That's the whole loop: raise signals, read state, gate behaviour.

---

## Core concepts

### Signals

A signal is what a check emits. Three factory methods set the safety level:

```php
Signal::soft('login_velocity', 15, $evidence);   // heuristic — only accumulates
Signal::hard('rate_limit', 40, $evidence);       // serious; big, slow-fading points
Signal::fatal('clock_skew', $evidence);          // impossible -> bans immediately
```

`detector`, `decay` and `reason` accept a **string or an enum** (a backed enum
becomes its value, a pure enum its name).

### Score & decay

Every signal becomes a row; the score is the live sum of their *remaining*
points. How fast points fade is the decay strategy:

| strategy     | behaviour                              | use for                |
| ------------ | -------------------------------------- | ---------------------- |
| `none`       | never fades                            | hard, permanent faults |
| `linear`     | reaches zero over *N* days             | ordinary heuristics    |
| `half_life`  | halves every *N* days (never quite 0)  | default                |

Run the maintenance job daily so subjects recover as points fade:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;
use Masq\Guardian\Jobs\ReevaluateTrust;

Schedule::job(new ReevaluateTrust)->daily();
```

### States, thresholds & the safety clamp

You map a minimum score to each state. The highest boundary the score reaches
wins:

```php
'thresholds' => [
    0   => TrustState::Trusted,
    20  => TrustState::Watch,
    50  => TrustState::Restricted,
    80  => TrustState::Review,     // opens a moderator case
    120 => TrustState::Banned,
],
```

The **clamp**: accumulated *soft* points can never push past `soft_max_state`
(default `review`). Only a `Signal::fatal()` reaches `banned` automatically, and
a ban stays even as the score decays. Set `soft_max_state => null` to remove the
clamp (riskier — soft points can then ban).

#### Custom states (your own ladder)

The five states are the **default** ladder. To add or rename rungs, define your
own enum implementing `Masq\Guardian\Contracts\TrustStateContract` and point
`state_enum` at it — your cases are then used everywhere (thresholds, actions,
middleware, reads), with full type-safety:

```php
use Masq\Guardian\Contracts\TrustStateContract;

enum TrustState: string implements TrustStateContract
{
    case Trusted = 'trusted';
    case Watch = 'watch';
    case Probation = 'probation';   // your extra rung
    case Review = 'review';
    case Banned = 'banned';

    public function key(): string { return $this->value; }
    public function level(): int  { /* order: lower = more trusted */ }

    public static function base(): self     { return self::Trusted; } // baseline
    public static function terminal(): self { return self::Banned; }  // ban target
    public static function fromKey(string $k): self  { return self::from($k); }
    public static function tryFromKey(?string $k): ?self { return $k === null ? null : self::tryFrom($k); }
    public static function all(): array { return self::cases(); }
}
```

```php
// config/guardian.php
'state_enum' => App\Trust\TrustState::class,
```

Now `$user->trustState()` returns your enum, `guardian:probation` works as
middleware, and your thresholds/actions reference your cases.

### Detectors

A detector is a reusable check. It implements one method and reads its own
config options:

```php
use Masq\Guardian\Detectors\AbstractDetector;
use Masq\Guardian\ValueObjects\Signal;

final class RateLimitDetector extends AbstractDetector
{
    public function inspect(object $subject, array $context = []): ?Signal
    {
        $hits = $context['hits'] ?? 0;

        return $hits > $this->option('limit', 100)
            ? Signal::hard($this->key(), 60, ['hits' => $hits])
            : null;          // null = nothing to report
    }
}
```

Register it in config (the array keys after `class`/`enabled` arrive as
`$options`, read via `$this->option('limit', 100)`):

```php
'detectors' => [
    'rate_limit' => [
        'class'   => App\Guardian\Detectors\RateLimitDetector::class,
        'enabled' => true,
        'limit'   => 100,
    ],
],
```

Then run checks:

```php
Guardian::inspect($user, ['hits' => $hits]);            // every enabled detector
Guardian::run('rate_limit', $user, ['hits' => $hits]);  // one by key
Guardian::register($adHocDetector);                      // runtime only
```

`$context` is just the data your detectors need — you decide its shape.

### Actions

When a subject *enters* a worse state, Guardian runs the action classes you
mapped to it. Use the **list form** to pass the enum case directly (PHP can't
use an enum as an array key), or a **keyed map** with string keys (the state's
value *or* name) — both are accepted:

```php
// list form — enum case, no ->value
'actions' => [
    ['state' => TrustState::Restricted, 'actions' => [FreezeAction::class]],
    ['state' => TrustState::Review,     'actions' => [QueueForReviewAction::class]],
    ['state' => TrustState::Banned,     'actions' => [QueueForReviewAction::class, BanAction::class]],
],

// keyed map — string keys also work ('restricted' value or 'Restricted' name)
'actions' => [
    'restricted' => [FreezeAction::class],
    'review'     => [QueueForReviewAction::class],
],
```

Shipped actions: `FreezeAction` (calls your `guardianRestrict()`),
`QueueForReviewAction` (opens a deduplicated `ModeratorReview` with an evidence
snapshot), `BanAction` (calls your `guardianBan()` + fires `SubjectBanned`).
Write your own by implementing `Masq\Guardian\Contracts\Action`.

### Tracks

Independent tracks for the same subject. Each track is fully defined under
`tracks.<name>`; an undefined track inherits the default track's rules.

```php
Guardian::track('behavior')->run('chat_filter', $user, ['message' => $text]);
Guardian::track('behavior')->ban($user, 'harassment');

$user->isBanned('behavior');   // independent of the default (anti-cheat) track
$user->isBanned();             // default track
```

Every read helper and every `Guardian` method takes an optional track, so you
can also write `Guardian::report($user, $signal, [], 'behavior')`.

### Caching

Trust standing (score / state / banned) and throttle counters live in the
cache, so `isBanned()` in middleware never hits the database. The engine
refreshes the cache on every change; if you edit the DB directly, call
`Guardian::reassess($user)` or clear the cache. Configure the store under
`cache` (see reference).

---

## Recipes

### Built-in: brute-force / throttle scoring

Guardian ships a `ThrottleHitDetector`. Feed it wherever you detect abuse — a
failed login, a 429, a rejected rate-limiter:

```php
Guardian::recordThrottleHit($user, 'login');
```

It keeps a rolling per-subject counter in the cache (one per label) and scores
once the count passes the allowance. Soft by design — it escalates toward
`review`, never an automatic ban. Tune it in config:

```php
'throttle_hits' => [
    'class'                => Masq\Guardian\Detectors\ThrottleHitDetector::class,
    'enabled'              => true,
    'allowed_hits'         => 5,    // free hits inside the window
    'window_seconds'       => 900,  // counter window (used to size the cache TTL)
    'base_points'          => 12,   // points once the allowance is exceeded
    'points_per_extra_hit' => 6,    // + per hit beyond the allowance
    'max_points'           => 100,  // cap for one signal
    'decay'                => 'half_life',
],
```

### Route middleware

The package registers the `guardian` alias. It blocks (`403`) when the subject's
state is at or worse than the given one, in the given track:

```php
Route::post('/play', ...)->middleware('guardian:banned');           // default track
Route::post('/chat', ...)->middleware('guardian:banned,behavior');  // behavior track
Route::get('/forum', ...)->middleware('guardian:review,behavior');  // review and worse
```

Format: `guardian:<state>[,<track>]` — state defaults to `banned`, track to the
default track.

### Moderation

```php
Guardian::ban($user, 'cheating confirmed'); // confirm -> permanent ban
Guardian::ban($user, BanReason::Cheating);  // reason accepts an enum too
Guardian::clear($user);                      // false positive -> forgive + unban
```

`clear()` wipes the subject's events for that track and resets it to `trusted`
(also lifts a ban). Open cases live in the `moderator_reviews` table
(`Masq\Guardian\Models\ModeratorReview`) with an evidence snapshot — list and
resolve them from your admin UI.

### Events

Hook listeners onto any of: `SuspicionRaised`, `ThresholdCrossed`,
`SentToReview`, `SubjectBanned`.

```php
class AlertModerators
{
    public function handle(\Masq\Guardian\Events\SentToReview $event): void
    {
        // $event->subject, $event->review->evidence
    }
}
```

---

## Configuration reference

`config/guardian.php` — per-track rules live in `tracks`, everything else is
shared:

```php
return [
    'default_track' => 'default',

    // Independent tracks. Each is self-contained; an undefined track name
    // inherits the default track's rules.
    'tracks' => [
        'default' => [
            'thresholds'      => [/* score => TrustState */],
            'soft_max_state'  => TrustState::Review,   // null = no clamp
            'actions'         => [/* TrustState->value => [Action::class] */],
            'throttle_detector' => 'throttle_hits',    // key recordThrottleHit() drives
            'detectors'       => [/* key => ['class' => ..., 'enabled' => true, ...options] */],
        ],
        // 'behavior' => [ ...own thresholds + detectors... ],
    ],

    // Shared by all tracks:
    'decay' => [
        'default'    => 'half_life',
        'strategies' => [
            'none'      => ['class' => NoDecay::class],
            'linear'    => ['class' => LinearDecay::class, 'days' => 30],
            'half_life' => ['class' => HalfLifeDecay::class, 'days' => 14],
        ],
    ],
    'cache'            => ['store' => env('GUARDIAN_CACHE_STORE'), 'ttl' => 86400, 'prefix' => 'guardian'],
    'ban_method'       => 'guardianBan',  // subject method BanAction calls
    'tables'           => ['events' => 'suspicion_events', 'profiles' => 'trust_profiles', 'reviews' => 'moderator_reviews'],
    'prune_after_days' => 180,            // null = keep the full audit log
];
```

---

## API cheat-sheet

Facade `Masq\Guardian\Facades\Guardian` (every method takes an optional final
`track`; or bind one with `Guardian::track('x')->...`):

| call | does |
| ---- | ---- |
| `report($subject, $signal\|$signals, $ctx = [])` | record signal(s), re-evaluate |
| `inspect($subject, $ctx = [])` | run all enabled detectors for the track |
| `run($key, $subject, $ctx = [])` | run one detector by key |
| `recordThrottleHit($subject, $limiter = 'default')` | bump throttle counter + score |
| `reassess($subject)` | recompute decayed score (no new signals) |
| `ban($subject, $reason = null)` | manual permanent ban |
| `clear($subject)` | forgive / unban (wipe events, reset) |
| `register($detector)` / `registry($track = null)->disable($key)` | runtime detector control |

Trait helpers on the subject (each takes an optional `$track`):
`trustState()`, `suspicionScore()`, `isBanned()`, `isFlagged()`, `needsReview()`,
`raiseSuspicion($signals, $ctx = [])`, `ban($reason = null)`, `unban()`.

```php
$user->ban('cheating confirmed');   // permanent ban (this track)
$user->unban();                     // lift ban / forgive
$user->ban('harassment', track: 'behavior');
```

---

## Testing

```bash
composer install
composer test     # pest
composer lint     # pint
```

## License

MIT © Masq Systems.
