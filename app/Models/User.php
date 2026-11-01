<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;\nuse Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public function conversations(): BelongsToMany\n    {\n        return $this->belongsToMany(Conversation::class)->withPivot(['joined_at', 'last_read_at', 'muted'])->withTimestamps();\n    }\n\n    protected $fillable = [
        'name', 'username', 'email', 'phone', 'password', 'account_type',
        'avatar_url', 'bio', 'country_code', 'email_verified_at',
        'phone_verified_at', 'status',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
