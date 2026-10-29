<?php

namespace App\Notifications;

use App\Models\Review;
use App\Models\User;

class OwnerReplied extends MemberNotification
{
    public const TYPE = 'owner_reply';

    public function __construct(public Review $review)
    {
    }

    /** Skipped if the reply was removed, or the review hidden, before the queue got to it */
    public function shouldSend(User $user, string $channel): bool
    {
        $review = $this->review->fresh();

        return $review && filled($review->provider_reply) && $review->status === Review::STATUS_APPROVED;
    }

    public function subject(): string
    {
        return __(':name ردّ على تقييمك', ['name' => $this->review->provider->name]);
    }

    public function lines(): array
    {
        return [__('كتب صاحب القناة رداً على تقييمك:')];
    }

    public function quote(): ?string
    {
        return $this->review->fresh()?->provider_reply ?? $this->review->provider_reply;
    }

    public function action(): ?array
    {
        return [__('عرض الرد'), $this->review->shareUrl()];
    }
}
