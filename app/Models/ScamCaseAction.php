<?php

namespace App\Models;

use App\Notifications\ScamCaseUpdated;
use App\Support\ScamHelp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Something the team did about a case ("we reported it to Telegram"); every reporter of the case is told. */
class ScamCaseAction extends Model
{
    protected $fillable = ['scam_case_id', 'kind', 'note', 'done_at'];

    protected $casts = ['done_at' => 'date'];

    protected static function booted(): void
    {
        static::created(function (ScamCaseAction $action) {
            $action->scamCase->confirmedReports()->with('user')->get()
                ->pluck('user')->filter()->unique('id')
                ->each(fn (User $user) => $user->notify(new ScamCaseUpdated($action)));
        });
    }

    public function scamCase(): BelongsTo
    {
        return $this->belongsTo(ScamCase::class);
    }

    public function kindLabel(): string
    {
        return ScamHelp::actions()[$this->kind] ?? $this->kind;
    }
}
