<?php

namespace App\Notifications;

use App\Models\Review;
use App\Models\User;

/** The review was rejected at review time, or taken down after being published. The admin's note stays internal. */
class ReviewNotPublished extends MemberNotification
{
    public const TYPE = 'review_status';

    public function __construct(public Review $review, public bool $removed = false)
    {
    }

    public function shouldSend(User $user, string $channel): bool
    {
        return $this->review->fresh()?->status === Review::STATUS_REJECTED;
    }

    public function subject(): string
    {
        $name = $this->review->provider->name;

        return $this->removed
            ? __('تمت إزالة تقييمك لـ :name', ['name' => $name])
            : __('لم يتم نشر تقييمك لـ :name', ['name' => $name]);
    }

    public function lines(): array
    {
        return [
            $this->removed
                ? __('بعد مراجعة تقييمك وجدنا أنه لا يتوافق مع شروط النشر، فتمت إزالته من الموقع.')
                : __('راجع فريقنا تقييمك ووجد أنه لا يتوافق مع شروط النشر، لذلك لم يتم نشره.'),
            __('اطّلع على شروط النشر، ويمكنك كتابة تقييم جديد يلتزم بها. إن رأيت أن هناك خطأ، تواصل معنا.'),
        ];
    }

    public function action(): ?array
    {
        return [__('شروط النشر'), route('pages.show', 'terms')];
    }
}
