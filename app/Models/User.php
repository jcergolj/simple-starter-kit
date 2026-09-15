<?php

namespace App\Models;

use App\DataTransferObjects\UserSettings;
use App\Enums\RoleEnum;
use App\ValueObjects\EmailAddress;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function isAdmin(): bool
    {
        return $this->role === RoleEnum::Admin || $this->role === RoleEnum::Superadmin;
    }

    public function isSuperadmin(): bool
    {
        return $this->role === RoleEnum::Superadmin;
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function markEmailAsVerified(): bool
    {
        if ($this->pending_email !== null) {
            $this->email = $this->pending_email;
            $this->pending_email = null;
        }

        return parent::markEmailAsVerified();
    }

    public function getEmailForVerification(): string
    {
        return $this->pending_email ?? $this->email;
    }

    public function routeNotificationForMail(?Notification $notification = null): string
    {
        if ($notification instanceof VerifyEmail && $this->pending_email !== null) {
            return $this->pending_email;
        }

        return $this->email;
    }

    public function initials(): string
    {
        return Str::of($this->name)->substr(0, 2)->upper();
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: function (string $value): string {
                return EmailAddress::from($value)->toString();
            },
        );
    }

    protected function settings(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                return UserSettings::fromArray(json_decode($value ?? '{}', true));
            },
            set: function (UserSettings|array|null $value) {
                return ['settings' => json_encode($value instanceof UserSettings ? $value->toArray() : ($value ?? ['lang' => 'en']))];
            },
        );
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'pending_email' => 'string',
            'password' => 'hashed',
            'role' => RoleEnum::class,
            'blocked_at' => 'datetime',
        ];
    }
}
