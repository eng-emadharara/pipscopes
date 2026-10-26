<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\ReviewReport;
use App\Models\ReviewVote;
use App\Support\OwnerPortal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** "Helpful" votes and reports on published reviews, open to members and guests. */
class ReviewFeedbackController extends Controller
{
    /** Toggles the visitor's helpful vote. */
    public function helpful(Request $request, Review $review)
    {
        $this->ensureCanGiveFeedback($request, $review);

        // Channel owners don't vote on reviews, of their own channel or anyone else's
        abort_if((bool) OwnerPortal::owner(), 403, __('لا يمكن لأصحاب القنوات التصويت على التقييمات.'));

        $key = Review::voterKey($request);
        $vote = $review->votes()->where('voter_key', $key)->first();

        if ($vote) {
            $vote->delete();
            $voted = false;
        } else {
            try {
                $review->votes()->create(['user_id' => $request->user()?->id, 'voter_key' => $key]);
            } catch (UniqueConstraintViolationException) {
                // Double click: the first request already recorded the vote
            }
            $voted = true;
        }

        $count = $review->refresh()->helpful_count;

        return $request->expectsJson()
            ? response()->json(['voted' => $voted, 'count' => $count])
            : back();
    }

    public function report(Request $request, Review $review)
    {
        $this->ensureCanGiveFeedback($request, $review);

        // Owners may flag fake reviews on their own channel, but not pile reports on competitors
        $owner = OwnerPortal::owner();
        abort_if($owner && $owner->provider_id !== $review->provider_id, 403, __('يمكن لأصحاب القنوات الإبلاغ عن تقييمات قناتهم فقط.'));

        $data = $request->validate([
            'reason' => ['required', Rule::in(ReviewReport::REASONS)],
            'details' => ['nullable', 'string', 'max:1000'],
        ], [], [
            'reason' => __('سبب البلاغ'),
        ]);

        // A second report from the same visitor updates the first instead of piling up
        ReviewReport::updateOrCreate(
            ['review_id' => $review->id, 'voter_key' => Review::voterKey($request)],
            $data + ['user_id' => $request->user()?->id, 'status' => 'open'],
        );

        $message = __('شكراً لك، تم استلام البلاغ وسيراجعه فريقنا.');

        return $request->expectsJson()
            ? response()->json(['message' => $message])
            : back()->with('success', $message);
    }

    protected function ensureCanGiveFeedback(Request $request, Review $review): void
    {
        abort_unless($review->status === Review::STATUS_APPROVED && $review->provider?->is_active, 404);
        abort_if($request->user() && $review->user_id === $request->user()->id, 403, __('لا يمكنك التصويت أو الإبلاغ على تقييمك.'));
    }
}
