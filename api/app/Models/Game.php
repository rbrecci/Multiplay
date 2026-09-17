<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'console_id',
        'igdb_id',
        'name',
        'cover_url',
        'first_release_year',
    ];

    public function console(): BelongsTo
    {
        return $this->belongsTo(Console::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['status', 'notes', 'rating', 'favorite'])->withTimestamps();
    }
}
