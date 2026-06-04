<?php

declare(strict_types=1);

namespace Masq\Guardian\Registry;

use Illuminate\Contracts\Container\Container;
use Masq\Guardian\Decay\DecayManager;

/**
 * Resolves per-track configuration. A "track" is an independent trust track
 * (e.g. "default"/anti-cheat and "behavior") — each with its own thresholds,
 * detectors, actions and state, evaluated separately for the same subject.
 *
 * Config shape: top-level keys are SHARED (decay, cache, tables, ban_method,
 * prune_after_days); per-track rules (thresholds, soft_max_state, actions,
 * detectors, throttle_detector) live under `tracks.<name>`. An unknown track
 * falls back to the default track's rules.
 */
final class TrackManager
{
    /** @var array<string, array{config: array<string, mixed>, decay: DecayManager, registry: DetectorRegistry}> */
    private array $built = [];

    /**
     * @param  array<string, mixed>  $root  the full guardian config
     */
    /**
     * @param  array<string, mixed>  $root  the full guardian config
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $root,
    ) {}

    public function defaultTrack(): string
    {
        return is_string($this->root['default_track'] ?? null) ? $this->root['default_track'] : 'default';
    }

    /** @return array<int, string> */
    public function names(): array
    {
        return array_values(array_unique([
            $this->defaultTrack(),
            ...array_keys((array) ($this->root['tracks'] ?? [])),
        ]));
    }

    /** @return array<string, mixed> */
    public function config(string $track): array
    {
        return $this->resolve($track)['config'];
    }

    public function decay(string $track): DecayManager
    {
        return $this->resolve($track)['decay'];
    }

    public function registry(string $track): DetectorRegistry
    {
        return $this->resolve($track)['registry'];
    }

    /**
     * @return array{config: array<string, mixed>, decay: DecayManager, registry: DetectorRegistry}
     */
    private function resolve(string $track): array
    {
        if (isset($this->built[$track])) {
            return $this->built[$track];
        }

        $tracks = (array) ($this->root['tracks'] ?? []);
        $defaultRules = (array) ($tracks[$this->defaultTrack()] ?? []);
        // A named track inherits the default track's rules for any key it omits;
        // an unknown track is exactly the default track.
        $rules = array_replace($defaultRules, (array) ($tracks[$track] ?? []));

        $config = array_replace($this->root, $rules);
        unset($config['tracks']);

        return $this->built[$track] = [
            'config' => $config,
            'decay' => new DecayManager($this->stringKeyed($config['decay'] ?? [])),
            'registry' => new DetectorRegistry($this->container, $this->stringKeyed($config['detectors'] ?? [])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stringKeyed(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $key => $item) {
            $out[(string) $key] = $item;
        }

        return $out;
    }
}
