<?php

namespace App\Enums;

enum AuthorityLevel: int
{
    case Autonomous = 1;
    case CeoApproval = 2;
    case CeoOnly = 3;

    public function label(): string
    {
        return match ($this) {
            self::Autonomous => 'Level 1 · Autonomous',
            self::CeoApproval => 'Level 2 · CEO approval required',
            self::CeoOnly => 'Level 3 · CEO only',
        };
    }
}
