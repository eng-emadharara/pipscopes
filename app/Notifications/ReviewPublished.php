<?php

namespace App\Notifications;

use App\Models\Review;
use App\Models\User;

class ReviewPublished extends MemberNotification
{
    public const TYPE = 'review_status';

    public function __construct(public Review $review)
    {
    }

    /** Skipped if the review was taken down again before the queue got to it */
    public function shouldSend(User $user, string $channel): bool
    {
        return $this->review->fresh()?->status === Review::STATUS_APPROVED;
    }

    public function subject(): string
    {
        return __('تم نشر تقييمك لـ :name', ['name' => $this->review->provider->name]);
    }

    public function lines(): array
    {
        return [
            __('شكراً لمشاركتك تجربتك. تقييمك الآن ظاهر للجميع ويساعد متداولين آخرين على الاختيار بثقة.'),
            __('شارك الرابط مع أصدقائك، أو عُد لاحقاً لتحديث تقييمك إن تغيّرت تجربتك.'),
        ];
    }

    public function action(): ?array
    {
        return [__('عرض تقييمك'), $this->review->shareUrl()];
    }
}
