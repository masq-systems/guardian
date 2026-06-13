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

final class Guardian
{
    /** @var array<string, list<Detector>> */
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

    public function track(string|\BackedEnum|\UnitEnum $track): TrackedGuardian
    {
        return new TrackedGuardian($this, (string) EnumValue::toString($track));
    }

    public function registry(?string $track = null): DetectorRegistry
    {
        return $this->tracks->registry($track ?? $this->defaultTrack());
    }

    public function register(Detector|string $detector, ?string $track = null): self
    {
        $track ??= $this->defaultTrack();
        $this->runtime[$track][] = is_string($detector) ? $this->container->make($detector) : $detector;

        return $this;
    }

    public function flushRequestState(): void
    {
        $this->runtime = [];
    }

    /**
     * @return list<Detector>
     */
    public function detectors(?string $track = null): array
    {
        $track ??= $this->defaultTrack();

        return array_merge($this->registry($track)->enabled(), $this->runtime[$track] ?? []);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  list<Detector>|null  $detectors
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
     * @param  Signal|array<array-key, Signal>  $signals
     * @param  array<string, mixed>  $context
     */
    public function report(object $subject, Signal|array $signals, array $context = [], ?string $track = null): TrustProfile
    {
        return $this->engine->report($subject, $signals, $context, $track ?? $this->defaultTrack());
    }

    public function reassess(object $subject, ?string $track = null): TrustProfile
    {
        return $this->engine->reassess($subject, $track ?? $this->defaultTrack());
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    public function ban(object $subject, string|\BackedEnum|\UnitEnum|null $reason = null, array $evidence = [], ?string $track = null): TrustProfile
    {
        return $this->report($subject, Signal::fatal('manual_ban', $evidence, $reason ?? 'Banned by moderator'), [], $track);
    }

    public function clear(object $subject, ?string $track = null): TrustProfile
    {
        return $this->engine->clear($subject, $track ?? $this->defaultTrack());
    }

    public function engine(): ScoringEngine
    {
        return $this->engine;
    }

    /**
     * @param  list<Detector>  $detectors
     * @param  array<string, mixed>  $context
     * @return list<Signal>
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
