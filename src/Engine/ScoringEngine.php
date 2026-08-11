<?php

declare(strict_types=1);

namespace Guardian\Engine;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Guardian\Contracts\Action;
use Guardian\Contracts\TrustStateContract;
use Guardian\Decay\DecayManager;
use Guardian\Events\SuspicionRaised;
use Guardian\Events\ThresholdCrossed;
use Guardian\Models\SuspicionEvent;
use Guardian\Models\TrustProfile;
use Guardian\Registry\TrackManager;
use Guardian\Support\EnumValue;
use Guardian\Support\States;
use Guardian\Support\TrustCache;
use Guardian\ValueObjects\Signal;

final class ScoringEngine
{
    public function __construct(
        private readonly Container $container,
        private readonly TrackManager $tracks,
        private readonly TrustCache $cache,
        private readonly States $states,
    ) {}

    /**
     * @param  Signal|array<array-key, Signal>  $signals
     * @param  array<string, mixed>  $context
     */
    public function report(object $subject, Signal|array $signals, array $context, string $track): TrustProfile
    {
        $signals = is_array($signals) ? $signals : [$signals];
        $now = CarbonImmutable::now();
        $decay = $this->tracks->decay($track);
        $fatal = false;

        foreach ($signals as $signal) {
            $event = $this->persist($subject, $signal, $now, $track, $decay);
            SuspicionRaised::dispatch($subject, $event);
            $fatal = $fatal || $signal->fatal;
        }

        return $this->evaluate($subject, $context, $fatal, $track);
    }

    public function reassess(object $subject, string $track): TrustProfile
    {
        return $this->evaluate($subject, [], false, $track);
    }

    public function clear(object $subject, string $track): TrustProfile
    {
        $subject->suspicionEvents()->where('track', $track)->delete();

        $profile = $subject->trustProfiles()->firstOrNew(['track' => $track]);
        $previous = $profile->exists ? $this->states->fromKey((string) $profile->state) : $this->states->base();

        $profile->track = $track;
        $profile->score = 0;
        $profile->state = $this->states->base()->key();
        $profile->flagged_at = null;
        $profile->banned_at = null;
        $profile->evaluated_at = CarbonImmutable::now();

        DB::transaction(fn () => $subject->trustProfiles()->save($profile));
        $this->cache->refresh($subject, $profile, $track);

        if (! $this->states->isBase($previous)) {
            ThresholdCrossed::dispatch($subject, $previous, $this->states->base(), 0, $track);
        }

        return $profile;
    }

    private function persist(object $subject, Signal $signal, CarbonImmutable $now, string $track, DecayManager $decay): SuspicionEvent
    {
        $decayKey = $signal->decay ?? $decay->defaultKey();
        $strategy = $decay->resolve($decayKey);

        $event = $subject->suspicionEvents()->create([
            'track' => $track,
            'detector' => $signal->detector,
            'points' => $signal->points,
            'severity' => $signal->severity,
            'fatal' => $signal->fatal,
            'decay' => $decayKey,
            'evidence' => $signal->evidence,
            'reason' => $signal->reason,
            'expires_at' => $strategy->expiresAt($now),
            'created_at' => $now,
        ]);

        return $event;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function evaluate(object $subject, array $context, bool $forceBan, string $track): TrustProfile
    {
        $now = CarbonImmutable::now();
        $config = $this->tracks->config($track);
        $score = $this->liveScore($subject, $now, $track);

        $profile = $subject->trustProfiles()->firstOrNew(['track' => $track]);
        $previous = $profile->exists ? $this->states->fromKey((string) $profile->state) : $this->states->base();

        if ($profile->banned_at !== null) {
            $forceBan = true;
        }

        $target = $forceBan
            ? $this->states->terminal()
            : $this->states->atMost($this->stateForScore($score, $config), $this->softMaxState($config));

        $profile->track = $track;
        $profile->score = $score;
        $profile->state = $target->key();
        $profile->evaluated_at = $now;

        if ($target->level() > $this->states->base()->level() && $profile->flagged_at === null) {
            $profile->flagged_at = $now;
        }

        if ($this->states->isTerminal($target) && $profile->banned_at === null) {
            $profile->banned_at = $now;
        }

        DB::transaction(fn () => $subject->trustProfiles()->save($profile));
        $this->cache->refresh($subject, $profile, $track);

        if ($target->key() !== $previous->key()) {
            ThresholdCrossed::dispatch($subject, $previous, $target, $score, $track);

            if ($target->level() > $previous->level()) {
                $this->runActions($subject, $target, ['track' => $track] + $context, $config);
            }
        }

        return $profile;
    }

    private function liveScore(object $subject, CarbonImmutable $now, string $track): int
    {
        $total = 0;
        $decay = $this->tracks->decay($track);

        $subject->suspicionEvents()
            ->where('track', $track)
            ->where(function ($q) use ($now): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->get()
            ->each(function (SuspicionEvent $event) use (&$total, $now, $decay): void {
                $total += $decay->resolve($event->decay)->remaining(
                    $event->points,
                    CarbonImmutable::parse($event->created_at),
                    $now,
                );
            });

        return max(0, $total);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function stateForScore(int $score, array $config): TrustStateContract
    {

        $thresholds = $config['thresholds'] ?? [0 => $this->states->base()];
        if (! is_array($thresholds)) {
            $thresholds = [0 => $this->states->base()];
        }
        ksort($thresholds);

        $state = $this->states->base();
        foreach ($thresholds as $boundary => $candidate) {
            if (is_numeric($boundary) && $score >= $boundary && $candidate instanceof TrustStateContract) {
                $state = $candidate;
            }
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function softMaxState(array $config): ?TrustStateContract
    {
        if (! array_key_exists('soft_max_state', $config)) {
            return $this->states->highestBelowTerminal();
        }

        $value = $config['soft_max_state'];

        return $value instanceof TrustStateContract ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $config
     */
    private function runActions(object $subject, TrustStateContract $state, array $context, array $config): void
    {
        foreach ($this->actionsFor($state, $config) as $actionClass) {
            $action = $this->container->make($actionClass);

            if ($action instanceof Action) {
                $action->handle($subject, $state, $context);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function actionsFor(TrustStateContract $state, array $config): array
    {

        $resolved = [];

        $entries = is_array($config['actions'] ?? null) ? $config['actions'] : [];

        foreach ($entries as $key => $value) {
            if (is_int($key) && is_array($value) && array_key_exists('state', $value)) {
                $matches = EnumValue::toString($this->stateLike($value['state']));
                $actions = is_array($value['actions'] ?? null) ? $value['actions'] : [];
            } else {
                $matches = (string) $key;
                $actions = is_array($value) ? $value : [];
            }

            $isMatch = $matches === $state->key()
                || ($state instanceof \UnitEnum && $matches === $state->name);

            if ($isMatch) {
                foreach ($actions as $action) {
                    if (is_string($action)) {

                        $resolved[] = $action;
                    }
                }
            }
        }

        return $resolved;
    }

    private function stateLike(mixed $value): string|\BackedEnum|\UnitEnum|null
    {
        return $value instanceof \BackedEnum || $value instanceof \UnitEnum || is_string($value) || $value === null
            ? $value
            : null;
    }
}
