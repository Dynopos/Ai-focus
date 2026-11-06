<?php

namespace App\Enums;

enum TaskPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function weight(): int
    {
        return match ($this) {
            self::Low => 10,
            self::Medium => 25,
            self::High => 45,
            self::Critical => 70,
        };
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
