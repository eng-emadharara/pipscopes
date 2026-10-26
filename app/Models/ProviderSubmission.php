<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderSubmission extends Model
{
    public const STATUSES = [
        'new' => 'جديد',
        'approved' => 'تمت الإضافة',
        'rejected' => 'مرفوض',
    ];

    public const RELATIONS = [
        'owner' => 'أنا صاحب الخدمة',
        'user' => 'أنا مستخدم/مشترك وأرغب بإضافتها',
    ];

    protected $fillable = [
        'submitter_name', 'submitter_contact', 'relation', 'category_ids', 'market_ids', 'name', 'service_type',
        'pricing', 'description', 'telegram_url', 'website_url', 'twitter_url',
        'instagram_url', 'youtube_url', 'tiktok_url', 'facebook_url',
        'status', 'admin_notes', 'provider_id',
    ];

    protected $casts = [
        'category_ids' => 'array',
        'market_ids' => 'array',
    ];

    /** @return \Illuminate\Database\Eloquent\Collection<int, Category> */
    public function categories()
    {
        return Category::whereIn('id', $this->category_ids ?? [])->orderBy('sort_order')->get();
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /** Create a provider from this submission and link it back. */
    public function convertToProvider(): Provider
    {
        $provider = Provider::create([
            'name' => $this->name,
            'service_type' => $this->service_type,
            'pricing' => $this->pricing,
            'bio' => $this->description,
            'telegram_url' => $this->telegram_url,
            'website_url' => $this->website_url,
            'twitter_url' => $this->twitter_url,
            'instagram_url' => $this->instagram_url,
            'youtube_url' => $this->youtube_url,
            'tiktok_url' => $this->tiktok_url,
            'facebook_url' => $this->facebook_url,
            'is_active' => true,
        ]);

        // Skip categories/markets that were deleted since the request was sent
        $provider->categories()->sync($this->categories()->pluck('id'));
        $provider->markets()->sync(Market::whereIn('id', $this->market_ids ?? [])->pluck('id'));
        $provider->refreshSearchIndex();

        $this->update(['status' => 'approved', 'provider_id' => $provider->id]);

        return $provider;
    }
}
