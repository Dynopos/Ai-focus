<?php

namespace App\Models;

use App\Support\Phone;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'business_id', 'role', 'phone', 'phone_verified_at', 'whatsapp_opt_in_at'])]
#[Hidden(['password', 'remember_token', 'phone_verification_hash'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_CEO = 'ceo';

    public const ROLE_MANAGER = 'manager';

    public const ROLE_STAFF = 'staff';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'whatsapp_opt_in_at' => 'datetime',
            'whatsapp_last_inbound_at' => 'datetime',
            'phone_verification_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function isCeo(): bool
    {
        return $this->role === self::ROLE_CEO;
    }

    /** A CEO whose phone is verified may issue privileged WhatsApp commands. */
    public function canCommandViaWhatsApp(): bool
    {
        return $this->isCeo() && $this->phone && $this->phone_verified_at !== null;
    }

    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = Phone::normalize($value);
    }
}
