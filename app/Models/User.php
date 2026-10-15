<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_available' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role->isAdmin();
    }

    public function isAgent(): bool
    {
        return $this->role->isAgent();
    }

    public function isCustomer(): bool
    {
        return $this->role->isCustomer();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'agent_category');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function isAvailable(): bool
    {
        return $this->is_available;
    }

    public function getMaxActiveTickets(): int
    {
        return $this->max_active_tickets;
    }
}
