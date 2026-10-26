<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A set of channel detail changes made by an owner; diff is {field: {old, new}}. */
class ProviderChange extends Model
{
    protected $fillable = ['provider_id', 'provider_owner_id', 'diff'];

    protected $casts = [
        'diff' => 'array',
    ];

    /** Field labels for the admin change log */
    public const LABELS = [
        'service_type' => 'نوع الخدمة', 'pricing' => 'التسعير', 'bio' => 'النبذة', 'avatar' => 'الصورة',
        'telegram_url' => 'رابط التيليجرام', 'telegram_members' => 'عدد المشتركين', 'website_url' => 'الموقع',
        'twitter_url' => 'X / تويتر', 'instagram_url' => 'إنستغرام', 'youtube_url' => 'يوتيوب',
        'tiktok_url' => 'تيك توك', 'facebook_url' => 'فيسبوك', 'extra_links' => 'روابط رسمية إضافية',
        'markets' => 'الأسواق', 'translations' => 'الترجمة الإنجليزية',
    ];

    /** Links whose change deserves extra attention (a common sign of a hijacked account) */
    public const SENSITIVE = ['telegram_url', 'website_url', 'twitter_url', 'instagram_url', 'youtube_url', 'tiktok_url', 'facebook_url', 'extra_links'];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(ProviderOwner::class, 'provider_owner_id');
    }

    public function touchesSensitiveFields(): bool
    {
        return (bool) array_intersect(array_keys($this->diff ?? []), self::SENSITIVE);
    }
}
