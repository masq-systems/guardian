<?php

declare(strict_types=1);

namespace Masq\Guardian\Detectors;

use Masq\Guardian\Contracts\Detector;

/**
 * Convenience base for detectors. Carries the config key + per-detector
 * options array so each check is self-contained and tunable from config.
 */
abstract class AbstractDetector implements Detector
{
    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        protected readonly string $key = 'detector',
        protected readonly array $options = [],
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    /** Read a config option with a fallback default. */
    protected function option(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    protected function intOption(string $name, int $default): int
    {
        $value = $this->options[$name] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }

    protected function stringOption(string $name, string $default): string
    {
        $value = $this->options[$name] ?? null;

        return is_string($value) ? $value : $default;
    }
}
