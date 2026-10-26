<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactMessage extends Model
{
    /** Subject keys; labels are in lang/{locale}/trust.php under contact_subjects. */
    public const SUBJECTS = ['general', 'review_issue', 'provider_listing', 'account_deletion', 'other'];

    public const STATUSES = [
        'new' => 'جديدة',
        'in_progress' => 'قيد المعالجة',
        'done' => 'تم الرد / المعالجة',
    ];

    protected $fillable = ['user_id', 'name', 'email', 'subject', 'message', 'status', 'admin_notes', 'ip_address'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
