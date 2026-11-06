<?php

namespace App\Support;

use App\Models\User;

/** Who did something, and through which channel. Recorded on every change. */
final class Actor
{
    public function __construct(
        public readonly string $type,      // user | ai | system | webhook
        public readonly string $channel,   // dashboard | whatsapp | email | ai | system
        public readonly ?User $user = null,
        public readonly ?string $ip = null,
    ) {}

    public static function user(User $user, string $channel = 'dashboard', ?string $ip = null): self
    {
        return new self('user', $channel, $user, $ip);
    }

    public static function ai(): self
    {
        return new self('ai', 'ai');
    }

    public static function system(): self
    {
        return new self('system', 'system');
    }
}
