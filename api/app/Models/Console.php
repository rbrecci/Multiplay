<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Console extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'manufacturer',
        'release_year',
        'sort_order',
        'igdb_platform_id',
    ];

    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Globais (user_id null) sao de todos; custom so existe pro dono.
     */
    public function scopeVisibleTo(Builder $query, ?int $userId): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNull('user_id')->orWhere('user_id', $userId));
    }

    /**
     * Toda rota /consoles/{console} passa por aqui, entao console custom de
     * outro usuario vira 404 sem precisar de if em cada action.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->visibleTo(auth()->id())
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }
}
