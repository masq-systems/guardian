<?php

declare(strict_types=1);

namespace Masq\Guardian;

use Illuminate\Contracts\Container\Container;
use Masq\Guardian\Contracts\Detector;
use Masq\Guardian\Engine\ScoringEngine;
use Masq\Guardian\Models\TrustProfile;
use Masq\Guardian\Registry\DetectorRegistry;
use Masq\Guardian\Registry\TrackManager;
use Masq\Guardian\Support\EnumValue;
use Masq\Guardian\Support\TrustCache;
use Masq\Guardian\ValueObjects\Signal;

/**
 * Public entry point. Resolve via the Guardian facade or the container.
 *
 * Every operation runs in a *track* — an independent trust track (e.g.
 * "anticheat", "behavior"). Omit it to use the configured `default_track`, or
 * use the fluent `Guardian::track('behavior')->inspect(...)`.
 */
final class Guardian
{
    /** @var array<string, array<int, Detector>> Runtime detectors, keyed by track. */
    private array $runtime = [];

    public function __construct(
        private readonly Container $container,
        private readonly ScoringEngine $engine,
        private readonly TrackManager $tracks,
    ) {}

    public function defaultTrack(): string
    {
        return $this->tracks->defaultTrack();
    }

    /** Fluent: bind every following call to a track. */
    public function track(string|\BackedEnum|\UnitEnum $track): TrackedGuardian
    {
        return new TrackedGuardian($this, (string) EnumValue::toString($track));
    }

    public function registry(?string $track = null): DetectorRegistry
    {
        return $this->tracks->registry($track ?? $this->defaultTrack());
    }

    /** Register an ad-hoc detector for a track (runtime, this request only). */
    public function register(Detector|string $detector, ?string $track = null): self
    {
        $track ??= $this->defaultTrack();
        $this->runtime[$track][] = is_string($detector) ? $this->container->make($detector) : $detector;

        return $this;
    }

    /**
     * Drop all ad-hoc detectors registered via register() for the current
     * request. This singleton lives for the whole worker under Laravel Octane,
     * so register()'s "this request only" contract requires that $runtime be
     * cleared between requests — the GuardianServiceProvider wires this to
     * Octane's RequestReceived event. A no-op (and harmless) without Octane.
     */
    public function flushRequestState(): void
    {
        $this->runtime = [];
    }

    /** @return array<int, Detector> All active detectors for a track (config + runtime). */
    public function detectors(?string $track = null): array
    {
        $track ??= $this->defaultTrack();

        return array_merge($this->registry($track)->enabled(), $this->runtime[$track] ?? []);
    }

    /**
     * Run a track's detectors against a subject, collect signals, report them.
     *
     * @param  array<string, mixed>  $context
     * @param  array<int, Detector>|null  $detectors
     */
    public function inspect(object $subject, array $context = [], ?array $detectors = null, ?string $track = null): TrustProfile
    {
        $track ??= $this->defaultTrack();
        $detectors ??= $this->detectors($track);
        $signals = $this->collect($detectors, $subject, $context);

        return $signals === []
            ? $subject->trustProfiles()->firstOrCreate(['track' => $track])
            : $this->engine->report($subject, $signals, $context, $track);
    }

    /**
     * Run a single named detector (config or runtime) by key in a track.
     *
     * @param  array<string, mixed>  $context
     */
    public function run(string|\BackedEnum|\UnitEnum $key, object $subject, array $context = [], ?string $track = null): TrustProfile
    {
        $track ??= $this->defaultTrack();
        $key = (string) EnumValue::toString($key);
        $detector = $this->registry($track)->get($key) ?? $this->runtimeByKey($key, $track);

        if ($detector === null) {
            return $subject->trustProfiles()->firstOrCreate(['track' => $track]);
        }

        return $this->inspect($subject, $context, [$detector], $track);
    }

    /**
     * Record a throttle / rate-limit hit and let the throttle detector score it.
     *
     * @param  array<string, mixed>  $context
     */
    public function recordThrottleHit(object $subject, string|\BackedEnum|\UnitEnum $limiter = 'default', array $context = [], ?string $track = null): TrustProfile
    {
        $track ??= $this->defaultTrack();
        $limiter = (string) EnumValue::toString($limiter);
        $config = $this->tracks->config($track);

        $key = is_string($config['throttle_detector'] ?? null) ? $config['throttle_detector'] : 'throttle_hits';

        $detectors = is_array($config['detectors'] ?? null) ? $config['detectors'] : [];
        $entry = is_array($detectors[$key] ?? null) ? $detectors[$key] : [];
        $window = is_numeric($entry['window_seconds'] ?? null) ? (int) $entry['window_seconds'] : 900;

        $hits = $this->container->make(TrustCache::class)
            ->recordHit($subject, $limiter, $window, $track);

        return $this->run($key, $subject, ['hits' => $hits, 'limiter' => $limiter] + $context, $track);
    }

    /**
     * Report pre-built signal(s) directly, bypassing detectors.
     *
     * @param  Signal|array<int, Signal>  $signals
     * @param  array<string, mixed>  $context
     */
    public function report(object $subject, Signal|array $signals, array $context = [], ?string $track = null): TrustProfile
    {
        return $this->engine->report($subject, $signals, $context, $track ?? $this->defaultTrack());
    }

    /** Recompute a track's decayed score and adjust state. */
    public function reassess(object $subject, ?string $track = null): TrustProfile
    {
        return $this->engine->reassess($subject, $track ?? $this->defaultTrack());
    }

    /**
     * Manually ban a subject in a track (moderator decision). `$reason` accepts
     * a string or an enum.
     *
     * @param  array<string, mixed>  $evidence
     */
    public function ban(object $subject, string|\BackedEnum|\UnitEnum|null $reason = null, array $evidence = [], ?string $track = null): TrustProfile
    {
        return $this->report($subject, Signal::fatal('manual_ban', $evidence, $reason ?? 'Banned by moderator'), [], $track);
    }

    /** Forgive a subject in a track (false positive / unban). */
    public function clear(object $subject, ?string $track = null): TrustProfile
    {
        return $this->engine->clear($subject, $track ?? $this->defaultTrack());
    }

    public function engine(): ScoringEngine
    {
        return $this->engine;
    }

    /**
     * @param  array<int, Detector>  $detectors
     * @param  array<string, mixed>  $context
     * @return array<int, Signal>
     */
    private function collect(array $detectors, object $subject, array $context): array
    {
        $signals = [];

        foreach ($detectors as $detector) {
            $result = $detector->inspect($subject, $context);

            if ($result instanceof Signal) {
                $signals[] = $result;
            } elseif (is_array($result)) {
                foreach ($result as $signal) {
                    if ($signal instanceof Signal) {
                        $signals[] = $signal;
                    }
                }
            }
        }

        return $signals;
    }

    private function runtimeByKey(string $key, string $track): ?Detector
    {
        foreach ($this->runtime[$track] ?? [] as $detector) {
            if ($detector->key() === $key) {
                return $detector;
            }
        }

        return null;
    }
}
