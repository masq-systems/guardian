<?php

declare(strict_types=1);

namespace Masq\Guardian\Support;

use Masq\Guardian\Contracts\TrustStateContract;
use Masq\Guardian\Enums\TrustState;

final class States
{
    private string $class;

    public function __construct(?string $class = null)
    {
        $this->class = $class !== null && is_a($class, TrustStateContract::class, true)
            ? $class
            : TrustState::class;
    }

    public function base(): TrustStateContract
    {
        return ($this->class)::base();
    }

    public function terminal(): TrustStateContract
    {
        return ($this->class)::terminal();
    }

    public function fromKey(string $key): TrustStateContract
    {
        return ($this->class)::fromKey($key);
    }

    public function tryFromKey(?string $key): ?TrustStateContract
    {
        return ($this->class)::tryFromKey($key);
    }

    /**
     * @return list<TrustStateContract>
     */
    public function all(): array
    {
        return ($this->class)::all();
    }

    public function worseThan(TrustStateContract $a, TrustStateContract $b): bool
    {
        return $a->level() > $b->level();
    }

    public function atMost(TrustStateContract $state, ?TrustStateContract $ceiling): TrustStateContract
    {
        return $ceiling !== null && $state->level() > $ceiling->level() ? $ceiling : $state;
    }

    public function isBase(TrustStateContract $state): bool
    {
        return $state->key() === $this->base()->key();
    }

    public function isTerminal(TrustStateContract $state): bool
    {
        return $state->key() === $this->terminal()->key();
    }

    public function baseKey(): string
    {
        return $this->base()->key();
    }

    public function highestBelowTerminal(): TrustStateContract
    {
        $terminal = $this->terminal();
        $best = $this->base();

        foreach ($this->all() as $state) {
            if ($state->key() !== $terminal->key() && $state->level() > $best->level()) {
                $best = $state;
            }
        }

        return $best;
    }
}
