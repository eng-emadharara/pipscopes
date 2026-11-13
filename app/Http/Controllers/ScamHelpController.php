<?php

namespace App\Http\Controllers;

use App\Models\ScamCase;
use App\Models\ScamReport;
use App\Support\AdminAlerts;
use App\Support\ScamHelp;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * "I was scammed": the guide (open to everyone, nothing stored), the report a member sends with
 * private proofs, their own reports and the case each joined, and the public page of a published case.
 */
class ScamHelpController extends Controller
{
    /** Reports one member can send in a day */
    public const DAILY_LIMIT = 5;

    public function guide(Request $request)
    {
        $type = ScamHelp::validType($request->query('type'));
        $payment = ScamHelp::validPayment($request->query('pay'));
        $paidAt = self::date($request->query('paid_at'));

        return view('scam-help.guide', [
            'type' => $type,
            'payment' => $payment,
            'paidAt' => $paidAt,
            'advice' => $type || $payment ? ScamHelp::advice($type, $payment, $paidAt) : null,
        ]);
    }

    public function create(Request $request)
    {
        return view('scam-help.report', [
            'type' => ScamHelp::validType($request->query('type')),
            'payment' => ScamHelp::validPayment($request->query('pay')),
            'paidAt' => self::date($request->query('paid_at')),
            'account' => mb_substr((string) $request->query('account'), 0, 255),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'scam_type' => ['required', Rule::in(ScamHelp::TYPES)],
            'payment_method' => ['required', Rule::in(ScamHelp::PAYMENTS)],
            'paid_at' => ['nullable', 'date', 'after:2000-01-01', 'before_or_equal:today'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'currency' => ['nullable', 'required_with:amount', Rule::in(array_keys(ScamHelp::CURRENCIES))],
            'account' => ['nullable', 'string', 'max:255'],
            'wallet' => ['nullable', 'string', 'max:120'],
            'broker' => ['nullable', 'string', 'max:120'],
            'story' => ['required', 'string', 'min:30', 'max:5000'],
            'proofs' => ['nullable', 'array', 'max:'.ScamReport::MAX_PROOFS],
            'proofs.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'contact' => ['nullable', 'string', 'max:255'],
            'consent_true' => ['accepted'],
            'consent_share' => ['nullable', 'boolean'],
            'consent_contact' => ['nullable', 'boolean'],
        ], [
            'consent_true.accepted' => __('أكّد أن المعلومات صحيحة وأنك أخفيت البيانات الحساسة.'),
            'story.min' => __('اكتب ما حدث بتفصيل أكثر قليلاً (30 حرفاً على الأقل).'),
        ]);

        $target = ScamHelp::target($data['account'] ?? null, $data['wallet'] ?? null, $data['broker'] ?? null);
        if (! $target) {
            return back()->withInput()->withErrors(['account' => __('أضف رابط الحساب أو القناة، أو عنوان المحفظة، أو اسم الوسيط، حتى نجمع بلاغك مع البلاغات المشابهة.')]);
        }

        $user = $request->user();
        if (ScamReport::where('user_id', $user->id)->where('created_at', '>=', now()->subDay())->count() >= self::DAILY_LIMIT) {
            return back()->withInput()->withErrors(['story' => __('أرسلت عدة بلاغات اليوم. حاول مرة أخرى غداً، أو راسلنا من صفحة التواصل.')]);
        }

        $proofs = collect($request->file('proofs', []))
            ->map(fn ($file) => $file->store('scam-proofs/'.now()->format('Y/m'), ScamReport::DISK))
            ->filter()->values()->all();

        $report = ScamReport::create([
            'user_id' => $user->id,
            'scam_case_id' => ScamCase::forTarget($target)->id,
            'scam_type' => $data['scam_type'],
            'payment_method' => $data['payment_method'],
            'paid_at' => $data['paid_at'] ?? null,
            'amount' => $data['amount'] ?? null,
            'currency' => isset($data['amount']) ? $data['currency'] : null,
            'account' => $data['account'] ?? null,
            'wallet' => ScamHelp::wallet($data['wallet'] ?? null) ?? ($data['wallet'] ?? null),
            'broker' => $data['broker'] ?? null,
            'story' => $data['story'],
            'proofs' => $proofs,
            'contact' => $data['contact'] ?? null,
            'consent_share' => $request->boolean('consent_share'),
            'consent_contact' => $request->boolean('consent_contact'),
            'ip' => $request->ip(),
        ]);

        AdminAlerts::scamReport($report);

        return redirect()->route('scam-reports.index')->with('scam_reported', $report->id);
    }

    public function index(Request $request)
    {
        $reports = ScamReport::where('user_id', $request->user()->id)
            ->with(['scamCase.actions'])
            ->latest()->get();

        return view('scam-help.my-reports', ['reports' => $reports]);
    }

    /** The joint file (no names or stories of others) to print or save as PDF: for a reporter of the case, or the team */
    public function file(Request $request, ScamReport $report)
    {
        $user = $request->user();
        abort_unless($report->user_id === $user->id || $user->is_admin, 404);
        abort_unless($report->status === ScamReport::STATUS_CONFIRMED && $report->scamCase, 404);

        $case = $report->scamCase;

        return view('scam-help.file', [
            'report' => $report,
            'case' => $case,
            'reports' => $case->confirmedReports()->orderBy('paid_at')->orderBy('id')->get(),
            'actions' => $case->actions,
        ]);
    }

    /** A proof image: the team only */
    public function proof(Request $request, ScamReport $report, int $index)
    {
        abort_unless($request->user()?->is_admin, 404);
        $path = $report->proofs[$index] ?? null;
        abort_unless($path && Storage::disk(ScamReport::DISK)->exists($path), 404);

        return Storage::disk(ScamReport::DISK)->response($path, null, ['Cache-Control' => 'private, max-age=600']);
    }

    public function showCase(ScamCase $scamCase)
    {
        abort_unless($scamCase->is_public && $scamCase->reports_count > 0, 404);

        return view('scam-help.case', [
            'case' => $scamCase->load(['provider', 'actions']),
            'types' => $scamCase->typeCounts(),
            'range' => $scamCase->confirmedReports()->toBase()->selectRaw('min(paid_at) as first, max(paid_at) as last')->first(),
        ]);
    }

    protected static function date(?string $value): ?Carbon
    {
        if (! $value || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        return $date->isFuture() || $date->year < 2000 ? null : $date;
    }
}
