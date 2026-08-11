<?php

declare(strict_types=1);

namespace Guardian\Decay;

use InvalidArgumentException;
use Guardian\Contracts\DecayStrategy;

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

        $class = $definition['class'];
        $args = $definition;
        unset($args['class']);

        $instance = $args === []
            ? new $class
            : new $class(...array_values($args));

        if (! $instance instanceof DecayStrategy) {
            throw new InvalidArgumentException("Guardian decay strategy [{$key}] must implement ".DecayStrategy::class.'.');
        }

        return $this->resolved[$key] = $instance;
    }
}
