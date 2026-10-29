<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/** A message the admin sends to members (Admin → الإعلانات), see App\Support\AnnouncementSender. */
class Announcement extends Model
{
    use HasTranslations;

    public const TRANSLATABLE = ['title', 'body', 'button_label'];

    public const STATUSES = [
        'draft' => 'مسودة',
        'scheduled' => 'مجدول',
        'sending' => 'جارٍ الإرسال',
        'sent' => 'أُرسل',
    ];

    protected $fillable = [
        'title', 'body', 'button_label', 'button_url', 'image', 'audience',
        'status', 'scheduled_at', 'sent_at', 'recipients_count', 'created_by',
    ];

    protected $casts = [
        'audience' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'scheduled'], true);
    }

    public function imageUrl(): ?string
    {
        return $this->image && Storage::disk('public')->exists($this->image) ? asset('storage/'.$this->image) : null;
    }

    /** The button's link through our counter, tagged so Google Analytics shows where visits came from */
    public function trackedUrl(string $channel): ?string
    {
        return $this->button_url ? route('announcements.click', [$this, $channel]) : null;
    }

    public function destinationUrl(string $channel): string
    {
        $utm = http_build_query([
            'utm_source' => $channel === 'telegram' ? 'telegram' : 'email',
            'utm_medium' => 'announcement',
            'utm_campaign' => 'announcement-'.$this->id,
        ]);

        return $this->button_url.(str_contains($this->button_url, '?') ? '&' : '?').$utm;
    }
}
