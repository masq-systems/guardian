<?php

declare(strict_types=1);

namespace Masq\Guardian\ValueObjects;

use BackedEnum;
use Masq\Guardian\Enums\Severity;
use Masq\Guardian\Support\EnumValue;
use UnitEnum;

/**
 * Immutable result emitted by a detector. Carries the points to accrue,
 * how they decay, evidence for moderators, and (for hard signals) whether
 * the violation is fatal — i.e. eligible for an automatic permanent ban.
 *
 * `$detector`, `$decay` and `$reason` accept a string or an enum (backed enum
 * resolves to its value, a pure enum to its name).
 */
final class Signal
{
    public readonly string $detector;

    public readonly ?string $decay;

    public readonly ?string $reason;

    /**
     * @param  array<string, mixed>  $evidence
     */
    public function __construct(
        string|BackedEnum|UnitEnum $detector,
        public readonly int $points,
        public readonly Severity $severity = Severity::Soft,
        public readonly array $evidence = [],
        string|BackedEnum|UnitEnum|null $decay = null,
        public readonly bool $fatal = false,
        string|BackedEnum|UnitEnum|null $reason = null,
    ) {
        $this->detector = (string) EnumValue::toString($detector);
        $this->decay = EnumValue::toString($decay);
        $this->reason = EnumValue::toString($reason);
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    public static function soft(string|BackedEnum|UnitEnum $detector, int $points, array $evidence = [], string|BackedEnum|UnitEnum|null $reason = null, string|BackedEnum|UnitEnum|null $decay = null): self
    {
        return new self($detector, $points, Severity::Soft, $evidence, $decay, false, $reason);
    }

    /**
     * @param  array<string, mixed>  $evidence
     */
    public static function hard(string|BackedEnum|UnitEnum $detector, int $points, array $evidence = [], string|BackedEnum|UnitEnum|null $reason = null, string|BackedEnum|UnitEnum|null $decay = null): self
    {
        return new self($detector, $points, Severity::Hard, $evidence, $decay ?? 'none', false, $reason);
    }

    /**
     * A certain, physically-impossible violation that should ban immediately.
     *
     * @param  array<string, mixed>  $evidence
     */
    public static function fatal(string|BackedEnum|UnitEnum $detector, array $evidence = [], string|BackedEnum|UnitEnum|null $reason = null, int $points = 1000): self
    {
        return new self($detector, $points, Severity::Hard, $evidence, 'none', true, $reason);
    }

    public function isHard(): bool
    {
        return $this->severity === Severity::Hard;
    }
}
