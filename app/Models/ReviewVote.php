<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A "helpful" vote on a review. */
class ReviewVote extends Model
{
    protected $fillable = ['review_id', 'user_id', 'voter_key'];

    protected static function booted(): void
    {
        static::created(fn (ReviewVote $vote) => $vote->review?->refreshCounters());
        static::deleted(fn (ReviewVote $vote) => $vote->review?->refreshCounters());
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
