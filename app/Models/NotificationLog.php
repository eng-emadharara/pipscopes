<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One email or Telegram message sent to a member (Admin → سجل الإشعارات). */
class NotificationLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'announcement_id', 'type', 'channel', 'status', 'subject', 'error'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
