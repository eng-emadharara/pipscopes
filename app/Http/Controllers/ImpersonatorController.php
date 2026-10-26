<?php

namespace App\Http\Controllers;

use App\Models\Impersonator;
use App\Models\Provider;
use App\Models\Review;
use App\Support\AccountHandle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ImpersonatorController extends Controller
{
    /** A visitor reports an account pretending to be this provider. Shown publicly only after admin confirmation. */
    public function report(Request $request, Provider $provider)
    {
        abort_unless($provider->is_active, 404);

        $data = $request->validateWithBag('impersonator', [
            'platform' => ['required', Rule::in(AccountHandle::PLATFORMS)],
            'account' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:2000'],
            'contact' => ['nullable', 'string', 'max:150'],
        ], [], [
            'platform' => __('المنصة'),
            'account' => __('رابط الحساب أو اسم المستخدم'),
        ]);

        $back = redirect()->to(route('providers.show', $provider).'#impersonators');
        $parsed = AccountHandle::parse($data['account'], $data['platform']);

        if (! $parsed) {
            return $back->withErrors(['account' => __('أدخل رابط الحساب أو اسم المستخدم بشكل صحيح.')], 'impersonator')->withInput();
        }

        if (in_array($parsed['key'], $provider->officialAccountKeys(), true)) {
            return $back->withErrors(['account' => __('هذا أحد الحسابات الرسمية لمقدم الخدمة وليس حساباً منتحلاً.')], 'impersonator')->withInput();
        }

        $impersonator = Impersonator::firstOrCreate(
            ['provider_id' => $provider->id, 'account_key' => $parsed['key']],
            ['platform' => $parsed['platform'], 'handle' => $parsed['display'], 'status' => 'pending'],
        );

        // New reports on a previously rejected account send it back for review
        if ($impersonator->status === 'rejected') {
            $impersonator->update(['status' => 'pending']);
        }

        try {
            $impersonator->reports()->create([
                'user_id' => $request->user()?->id,
                'voter_key' => Review::voterKey($request),
                'details' => $data['details'] ?? null,
                'contact' => $data['contact'] ?? null,
                'ip_address' => $request->ip(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $back->with('impersonator_success', __('سبق أن أبلغت عن هذا الحساب، شكراً لك. فريقنا يراجعه.'));
        }

        return $back->with('impersonator_success', $impersonator->status === 'confirmed'
            ? __('شكراً لك! هذا الحساب مسجّل لدينا كمنتحل، وتمت إضافة بلاغك إليه.')
            : __('شكراً لك! سيراجع فريقنا البلاغ، وإذا تأكدنا منه سيظهر الحساب في قائمة المنتحلين.'));
    }
}
