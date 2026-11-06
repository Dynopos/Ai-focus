<?php

namespace App\Enums;

enum TaskSource: string
{
    case Dashboard = 'dashboard';
    case Email = 'email';
    case WhatsApp = 'whatsapp';
    case Ai = 'ai';

    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::Ai => 'AI',
            default => ucfirst($this->value),
        };
    }
}
