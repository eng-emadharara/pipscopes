<?php

namespace App\Notifications;

use App\Models\ScamReport;
use App\Models\User;

/** The team looked at a member's scam report: confirmed (and joined to its case) or not. */
class ScamReportReviewed extends MemberNotification
{
    public const TYPE = 'scam_reports';

    public function __construct(public ScamReport $report)
    {
    }

    /** Skipped if the decision changed before the queue got to it */
    public function shouldSend(User $user, string $channel): bool
    {
        return $this->report->fresh()?->status === $this->report->status;
    }

    protected function confirmed(): bool
    {
        return $this->report->status === ScamReport::STATUS_CONFIRMED;
    }

    public function subject(): string
    {
        return $this->confirmed() ? __('راجعنا بلاغك وأكدناه') : __('راجعنا بلاغك');
    }

    public function lines(): array
    {
        if (! $this->confirmed()) {
            return array_values(array_filter([
                __('لم نستطع تأكيد بلاغك بالمعلومات الحالية، لذلك لن يُضاف إلى ملف جماعي الآن.'),
                $this->report->admin_note ? __('ملاحظة الفريق: :note', ['note' => $this->report->admin_note]) : null,
                __('يمكنك إرسال بلاغ جديد بإثباتات أوضح، والخطوات العاجلة في صفحة المساعدة ما زالت تنطبق عليك.'),
            ]));
        }

        $case = $this->report->scamCase;

        return array_values(array_filter([
            $case ? trans_choice('انضم بلاغك إلى ملف :target، وفيه الآن :count بلاغ مؤكد.', $case->reports_count, [
                'target' => $case->displayName(), 'count' => $case->reports_count,
            ]) : null,
            __('كلما زادت البلاغات صار تحركنا أقوى. سنخبرك بكل خطوة نقوم بها في هذا الملف.'),
        ]));
    }

    public function action(): ?array
    {
        return [__('متابعة بلاغاتي'), route('scam-reports.index')];
    }
}
