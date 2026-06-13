<?php

declare(strict_types=1);

namespace Masq\Guardian\Registry;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Masq\Guardian\Contracts\Detector;
use Masq\Guardian\Support\EnumValue;

final class DetectorRegistry
{
    /** @var array<string, Detector>|null */
    private ?array $resolved = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly Container $container,
        private array $config = [],
    ) {}

    /**
     * @param  array<string, mixed>|string  $definition
     */
    public function define(string|\BackedEnum|\UnitEnum $key, array|string $definition): self
    {
        $this->config[(string) EnumValue::toString($key)] = $definition;
        $this->resolved = null;

        return $this;
    }

    public function disable(string|\BackedEnum|\UnitEnum $key): self
    {
        $key = (string) EnumValue::toString($key);

        if (isset($this->config[$key]) && is_array($this->config[$key])) {
            $this->config[$key]['enabled'] = false;
            $this->resolved = null;
        }

        return $this;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    /**
     * @return array<string, Detector>
     */
    public function all(): array
    {
        return $this->resolved ??= $this->build();
    }

    /**
     * @return list<Detector>
     */
    public function enabled(): array
    {
        return array_values($this->all());
    }

    public function get(string|\BackedEnum|\UnitEnum $key): ?Detector
    {
        return $this->all()[(string) EnumValue::toString($key)] ?? null;
    }

    /**
     * @return array<string, Detector>
     */
    private function build(): array
    {
        $detectors = [];

        foreach ($this->config as $key => $definition) {

            if (is_string($definition)) {
                $definition = ['class' => $definition];
            }

            if (! is_array($definition)) {
                throw new InvalidArgumentException("Guardian detector [{$key}] definition must be an array or class-string.");
            }

            if (($definition['enabled'] ?? true) === false) {
                continue;
            }

            $class = $definition['class'] ?? null;
            if (! is_string($class) || ! is_a($class, Detector::class, true)) {
                throw new InvalidArgumentException("Guardian detector [{$key}] has no valid 'class'.");
            }

            $options = $definition;
            unset($options['class'], $options['enabled']);

            $detector = $this->container->make($class, [
                'key' => (string) $key,
                'options' => $options,
            ]);

            $detectors[(string) $key] = $detector;
        }

        return $detectors;
    }
}
