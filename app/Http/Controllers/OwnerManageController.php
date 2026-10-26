<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Models\Review;
use App\Services\OwnerChannelUpdate;
use App\Support\OwnerPortal;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** What a channel owner does from their channel's page: reply to reviews and edit details. */
class OwnerManageController extends Controller
{
    public function reply(Request $request, Provider $provider, Review $review)
    {
        $this->authorizeReview($provider, $review);

        $data = $request->validateWithBag("reply{$review->id}", [
            'provider_reply' => ['required', 'string', 'min:5', 'max:2000'],
        ], [], ['provider_reply' => __('الرد')]);

        $review->update([
            'provider_reply' => trim($data['provider_reply']),
            'provider_reply_owner_id' => OwnerPortal::owner()->id,
        ]);

        return redirect()->to(route('providers.show', [$provider, 'page' => $request->input('page')]).'#review-'.$review->id)
            ->with('success', __('تم نشر ردك.'));
    }

    public function deleteReply(Request $request, Provider $provider, Review $review)
    {
        $this->authorizeReview($provider, $review);

        $review->update(['provider_reply' => null]);

        return redirect()->to(route('providers.show', [$provider, 'page' => $request->input('page')]).'#review-'.$review->id)
            ->with('success', __('تم حذف ردك.'));
    }

    public function update(Request $request, Provider $provider, OwnerChannelUpdate $updater)
    {
        abort_unless(OwnerPortal::manages($provider), 403);

        $url = ['nullable', 'url', 'max:255'];
        $data = $request->validateWithBag('ownerEdit', [
            'service_type' => ['required', 'string', 'max:150'],
            'bio' => ['nullable', 'string', 'max:3000'],
            'translations' => ['nullable', 'array'],
            'translations.*.service_type' => ['nullable', 'string', 'max:150'],
            'translations.*.bio' => ['nullable', 'string', 'max:3000'],
            'pricing' => ['nullable', Rule::in(array_keys(__('trust.pricing_options')))],
            'market_ids' => ['nullable', 'array'],
            'market_ids.*' => ['integer', Rule::exists('markets', 'id')->where('is_active', true)],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
            'telegram_url' => ['required', 'url', 'max:255'],
            'telegram_members' => ['nullable', 'integer', 'min:0'],
            'website_url' => $url, 'twitter_url' => $url, 'instagram_url' => $url,
            'youtube_url' => $url, 'tiktok_url' => $url, 'facebook_url' => $url,
            'extra_links' => ['nullable', 'array', 'max:5'],
            'extra_links.*.label' => ['nullable', 'string', 'max:60'],
            'extra_links.*.url' => ['nullable', 'url', 'max:255'],
        ], [], [
            'service_type' => __('نوع الخدمة'),
            'telegram_url' => __('رابط قناة التيليجرام'),
            'avatar' => __('الشعار'),
        ]);

        // Only configured extra languages, and only rows of extra links that have a URL
        $data['translations'] = collect($data['translations'] ?? [])
            ->only(array_slice(array_keys(config('trust.locales')), 1))
            ->map(fn ($fields) => collect($fields)->only(['service_type', 'bio'])->all())
            ->all();
        $data['extra_links'] = collect($data['extra_links'] ?? [])->filter(fn ($l) => filled($l['url'] ?? null))->values()->all();
        $data['market_ids'] = $data['market_ids'] ?? [];

        $changes = $updater->apply(
            $provider, OwnerPortal::owner(), $data,
            $request->file('avatar'), $request->boolean('remove_avatar'),
        );

        return redirect()->route('providers.show', $provider)
            ->with('success', $changes ? __('تم حفظ بيانات قناتك.') : __('لا توجد تغييرات للحفظ.'));
    }

    protected function authorizeReview(Provider $provider, Review $review): void
    {
        abort_unless(OwnerPortal::manages($provider), 403);
        abort_unless($review->provider_id === $provider->id && $review->status === Review::STATUS_APPROVED, 404);
    }
}
