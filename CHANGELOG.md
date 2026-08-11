# Changelog

All notable changes to `rolandverner/guardian` will be documented in this file.

## [Unreleased]

### Fixed
- **Laravel Octane: per-request state no longer bleeds across requests.** The
  `Guardian` and `TrackManager` singletons live for the whole worker, so
  ad-hoc detectors from `Guardian::register()` (documented "this request only")
  and any runtime `DetectorRegistry::define()/disable()` could leak into the
  next request on the same worker. The provider now resets that state on
  Octane's `RequestReceived` event via the new `Guardian::flushRequestState()`
  and `TrackManager::flush()`. No-op (and zero new dependency) when Octane
  isn't installed. Config-file detector setup is unaffected.

### Added
- **Record-level invalid marks (`Flaggable`).** A new polymorphic
  `guardian_flags` table + `Models\GuardianFlag` + `Concerns\Flaggable` trait
  let any model mark individual *records* invalid (e.g. one day of activity),
  with no per-table column. Where `Guardable` scores a *subject* over time,
  `Flaggable` marks a single record as not-to-be-counted — the data is never
  deleted, only marked, and the mark is reversible (`flagged` → moderator
  `cleared`/`confirmed`). Helpers: `$record->flagAsInvalid(reason, evidence)`,
  `clearAutoFlags()`, `isGuardianFlagged()`, and query scopes
  `guardianValid()` / `guardianFlagged()` (track-aware). Migration auto-loads.
- Trait convenience methods `$user->ban($reason = null)` and `$user->unban()`
  (delegate to `Guardian::ban()` / `Guardian::clear()`, track-aware).
- **Pluggable state ladder**: states are defined by an enum implementing
  `Contracts\TrustStateContract`. Apps can supply their own (extra/renamed
  rungs) via `state_enum` in config — used everywhere with full type-safety.
  `Support\States` centralises resolution; `TrustProfile.state` is stored as a
  string key (no fixed enum cast).
- `actions` config accepts a list form `['state' => TrustState::Restricted,
  'actions' => [...]]` (pass the enum case directly), and keyed-map string keys
  now match by state value OR name. `->value` is no longer required.
- **Tracks** — independent trust tracks per subject (e.g. `default` /
  `behavior`), each with its own score, thresholds, detectors, state and ban.
  `TrackManager`, `Guardian::track('x')->...`, a track arg on every method and
  read helper (`$user->isBanned('behavior')`). Config `default_track` + `tracks`.
  `suspicion_events` / `trust_profiles` / `moderator_reviews` carry a `track`
  column (trust_profiles unique is per-track).
- **Route middleware** `guardian` alias: `->middleware('guardian:banned')` /
  `'guardian:review,behavior'` — blocks (403) by state + track.

- Enum support everywhere an identifier/label is accepted (backed enum -> value,
  pure enum -> name), via `Support\EnumValue`: `Signal` `detector` / `decay` /
  `reason`, `Guardian::ban()` reason, `Guardian::run()` & registry keys,
  `Guardian::recordThrottleHit()` limiter. New `Enums\Decay` convenience enum.
- `ModeratorReview::status` is now cast to the `Enums\ReviewStatus` enum
  (string `STATUS_*` constants kept for queries).
- Moderator actions: `Guardian::ban()` (manual permanent ban) and
  `Guardian::clear()` (forgive false positive / unban — wipes events, resets to trusted).
- Config-driven **detector registry**: keyed entries with `enabled` toggle and
  per-detector options; add your own checks or disable shipped ones from config
  (`DetectorRegistry`, `AbstractDetector`).
- Default **`ThrottleHitDetector`** + `Guardian::recordThrottleHit()` for scoring
  brute-force / rate-limit abuse via a cache-backed rolling counter.
- **Cache layer** (`TrustCache`): trust standing and throttle counters served
  from the cache; `isBanned()` / `trustState()` no longer hit the DB.
- `Guardian::run('key', ...)` to run a single named detector; `Guardian::registry()`.
- `Guardable` trait (primary). `Suspectable` retained as a deprecated alias.

### Initial release
- Suspicion scoring engine with soft/hard signals and fatal auto-ban.
- Trust state machine: trusted → watch → restricted → review → banned.
- Soft-ceiling safety clamp (`soft_max_state`): accumulated soft points can
  never auto-ban; only fatal hard signals do.
- Pluggable decay strategies: `none`, `linear`, `half_life`, plus `DecayManager`.
- `Suspectable` trait, `TrustProfile` / `SuspicionEvent` / `ModeratorReview` models.
- Pluggable detectors and actions (`FreezeAction`, `QueueForReviewAction`, `BanAction`).
- Events: `SuspicionRaised`, `ThresholdCrossed`, `SentToReview`, `SubjectBanned`.
- `ReevaluateTrust` scheduled job (decay recovery + pruning).
- Pest test suite on Testbench, GitHub Actions CI.
