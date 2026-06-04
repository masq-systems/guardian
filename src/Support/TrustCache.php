<?php

declare(strict_types=1);

namespace Masq\Guardian\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\Eloquent\Model;
use Masq\Guardian\Models\TrustProfile;

/**
 * Caches a subject's trust standing per track so hot reads (isBanned() in
 * middleware, trustState() in views) never touch the database. The engine
 * refreshes the cache on every evaluation; reads fall back to the DB and
 * re-warm on a miss.
 *
 * @phpstan-type Standing array{score: int, state: string, banned: bool}
 */
final class TrustCache
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly int $ttl = 86400,
        private readonly string $prefix = 'guardian',
        private readonly string $baseStateKey = 'trusted',
    ) {}

    /**
     * @return Standing
     */
    public function standing(object $subject, string $track): array
    {
        $key = $this->profileKey($subject, $track);

        /** @var Standing|null $cached */
        $cached = $this->cache->get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $profile = $subject instanceof Model
            ? $subject->trustProfiles()->where('track', $track)->first()
            : null;

        $standing = $this->toStanding($profile);
        $this->cache->put($key, $standing, $this->ttl);

        return $standing;
    }

    public function refresh(object $subject, TrustProfile $profile, string $track): void
    {
        $this->cache->put($this->profileKey($subject, $track), $this->toStanding($profile), $this->ttl);
    }

    public function forget(object $subject, string $track): void
    {
        $this->cache->forget($this->profileKey($subject, $track));
    }

    /**
     * Increment a rolling hit counter (for ThrottleHitDetector) and return the
     * new count. The window resets the counter once it elapses.
     */
    public function recordHit(object $subject, string $limiter, int $window, string $track): int
    {
        $key = $this->hitKey($subject, $limiter, $track);

        $this->cache->add($key, 0, $window);

        /** @var int $count */
        $count = $this->cache->increment($key);

        return $count;
    }

    public function hits(object $subject, string $limiter, string $track): int
    {
        $value = $this->cache->get($this->hitKey($subject, $limiter, $track), 0);

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @return Standing
     */
    private function toStanding(?TrustProfile $profile): array
    {
        return [
            'score' => (int) ($profile->score ?? 0),
            'state' => (string) ($profile->state ?? $this->baseStateKey),
            'banned' => $profile?->banned_at !== null,
        ];
    }

    private function profileKey(object $subject, string $track): string
    {
        return "{$this->prefix}:profile:{$track}:{$this->subjectId($subject)}";
    }

    private function hitKey(object $subject, string $limiter, string $track): string
    {
        return "{$this->prefix}:hits:{$track}:{$limiter}:{$this->subjectId($subject)}";
    }

    private function subjectId(object $subject): string
    {
        if ($subject instanceof Model) {
            $id = $subject->getKey();

            return $subject->getMorphClass().':'.(is_scalar($id) ? (string) $id : '');
        }

        return $subject::class.':'.spl_object_id($subject);
    }
}
