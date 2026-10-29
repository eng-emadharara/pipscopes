<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Models\Review;
use App\Support\Analytics;
use App\Support\SignupGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function create(Request $request, Provider $provider)
    {
        abort_unless($provider->is_active, 404);

        Analytics::context(Analytics::channel($provider));
        Analytics::event('review_start');

        return view('reviews.form', [
            'provider' => $provider,
            'review' => $provider->reviews()->where('user_id', $request->user()->id)->first() ?? new Review(['show_name' => true]),
        ]);
    }

    public function store(Request $request, Provider $provider)
    {
        abort_unless($provider->is_active, 404);

        $criteria = array_keys(__('trust.criteria'));
        $rules = [
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'min:20', 'max:3000'],
            'show_name' => ['nullable', 'boolean'],
            'pros' => ['nullable', 'array'],
            'pros.*' => [Rule::in(array_keys(__('trust.pros')))],
            'cons' => ['nullable', 'array'],
            'cons.*' => [Rule::in(array_keys(__('trust.cons')))],
            'experience_duration' => ['nullable', Rule::in(array_keys(__('trust.experience_durations')))],
        ];
        foreach ($criteria as $field) {
            $rules[$field] = ['nullable', 'integer', 'between:1,5'];
        }

        $data = $request->validate($rules, [], [
            'rating' => __('التقييم العام'),
            'body' => __('تفاصيل التجربة'),
        ]);

        $data['show_name'] = $request->boolean('show_name');
        $data['pros'] = $data['pros'] ?? [];
        $data['cons'] = $data['cons'] ?? [];
        $data['status'] = config('trust.reviews_require_approval') ? Review::STATUS_PENDING : Review::STATUS_APPROVED;
        $data['ip'] = $request->ip();
        $data['hold_reason'] = null;

        // New accounts and a second account on the same device wait for an admin before they count
        if ($data['status'] === Review::STATUS_APPROVED && ($reason = SignupGuard::holdReason($request->user(), $provider, $request->ip()))) {
            $data['status'] = Review::STATUS_PENDING;
            $data['hold_reason'] = $reason;
        }

        $review = $provider->reviews()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data
        );

        Analytics::flash('review_submit', [
            ...Analytics::channel($provider),
            'rating' => $review->rating,
            'anonymous' => $review->show_name ? 'no' : 'yes',
            'review_type' => $review->wasRecentlyCreated ? 'new' : 'edit',
        ]);

        return redirect()->route('reviews.thanks', $provider)->with('review_edited', ! $review->wasRecentlyCreated);
    }

    /** After publishing: what happens next with the review, sharing it, and other channels to review */
    public function thanks(Request $request, Provider $provider)
    {
        $review = $provider->reviews()->where('user_id', $request->user()->id)->first();
        if (! $review) {
            return redirect()->route('providers.show', $provider);
        }

        $reviewed = $request->user()->reviews()->pluck('provider_id');

        return view('reviews.thanks', [
            'provider' => $provider,
            'review' => $review,
            // The quick details the member skipped, offered once more now the review is safely saved
            'missing' => array_keys(array_filter([
                'reasons' => ! $review->pros && ! $review->cons,
                'duration' => ! $review->experience_duration,
            ])),
            'edited' => (bool) session('review_edited'),
            'suggestions' => Provider::active()->whereNotIn('id', $reviewed)
                ->orderByDesc('reviews_count')->orderByDesc('telegram_members')->take(4)->get(),
        ]);
    }

    /** Adds the quick details from the thank-you page; the text, stars and moderation status stay as they are */
    public function details(Request $request, Provider $provider)
    {
        $review = $provider->reviews()->where('user_id', $request->user()->id)->firstOrFail();

        $data = $request->validate([
            'pros' => ['nullable', 'array'],
            'pros.*' => [Rule::in(array_keys(__('trust.pros')))],
            'cons' => ['nullable', 'array'],
            'cons.*' => [Rule::in(array_keys(__('trust.cons')))],
            'experience_duration' => ['nullable', Rule::in(array_keys(__('trust.experience_durations')))],
        ]);

        $review->update(array_filter([
            'pros' => $data['pros'] ?? null,
            'cons' => $data['cons'] ?? null,
            'experience_duration' => $data['experience_duration'] ?? null,
        ]));

        return redirect()->route('reviews.thanks', $provider)->with('success', __('شكراً، أضفنا التفاصيل إلى تقييمك.'));
    }

    public function destroy(Request $request, Provider $provider)
    {
        $provider->reviews()->where('user_id', $request->user()->id)->first()?->delete();

        return redirect()->route('providers.show', $provider)->with('success', __('تم حذف تقييمك.'));
    }
}
