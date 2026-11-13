<?php

namespace App\Notifications;

use App\Models\ScamCaseAction;

/** The team did something about a case the member reported to. */
class ScamCaseUpdated extends MemberNotification
{
    public const TYPE = 'scam_reports';

    public function __construct(public ScamCaseAction $caseAction)
    {
    }

    public function subject(): string
    {
        return __('تحديث على ملف :target', ['target' => $this->caseAction->scamCase->displayName()]);
    }

    public function lines(): array
    {
        return [
            $this->caseAction->kindLabel().': '.$this->caseAction->note,
            __('شكراً لأنك بلّغت. بلاغك جزء من هذا الملف.'),
        ];
    }

    public function action(): ?array
    {
        return [__('متابعة بلاغاتي'), route('scam-reports.index')];
    }
}
