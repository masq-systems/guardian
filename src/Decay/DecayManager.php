<?php

declare(strict_types=1);

namespace Masq\Guardian\Decay;

use InvalidArgumentException;
use Masq\Guardian\Contracts\DecayStrategy;

/** Resolves a decay strategy instance from its config key, caching instances. */
final class DecayManager
{
    /** @var array<string, DecayStrategy> */
    private array $resolved = [];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    public function defaultKey(): string
    {
        return is_string($this->config['default'] ?? null) ? $this->config['default'] : 'none';
    }

    public function resolve(?string $key): DecayStrategy
    {
        $key ??= $this->defaultKey();

        if (isset($this->resolved[$key])) {
            return $this->resolved[$key];
        }

        $strategies = is_array($this->config['strategies'] ?? null) ? $this->config['strategies'] : [];
        $definition = $strategies[$key] ?? null;

        if (! is_array($definition) || ! is_string($definition['class'] ?? null)) {
            throw new InvalidArgumentException("Unknown Guardian decay strategy [{$key}].");
        }

        /** @var class-string<DecayStrategy> $class */
        $class = $definition['class'];
        $args = $definition;
        unset($args['class']);

        return $this->resolved[$key] = $args === []
            ? new $class
            : new $class(...array_values($args));
    }
}
