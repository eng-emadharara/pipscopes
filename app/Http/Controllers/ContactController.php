<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    public function create(Request $request)
    {
        // Intro text comes from the "contact" page in the admin panel, when it exists
        $page = Page::published()->where('slug', 'contact')->first();

        return view('contact', [
            'page' => $page,
            'content' => $page?->sections(),
            'subject' => in_array($request->query('subject'), ContactMessage::SUBJECTS, true) ? $request->query('subject') : null,
        ]);
    }

    public function store(Request $request)
    {
        // Honeypot: bots fill every field
        if (filled($request->input('website_confirm'))) {
            return redirect()->route('contact')->with('success', __('تم إرسال رسالتك.'));
        }

        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', Rule::in(ContactMessage::SUBJECTS)],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ], [], [
            'name' => __('الاسم'),
            'email' => __('البريد الإلكتروني'),
            'subject' => __('الموضوع'),
            'message' => __('الرسالة'),
        ]);

        ContactMessage::create($data + [
            'user_id' => $request->user()?->id,
            'ip_address' => $request->ip(),
        ]);

        $success = $data['subject'] === 'account_deletion'
            ? __('تم استلام طلب حذف الحساب. سنتحقق من الطلب وننفذه خلال 30 يوماً كحد أقصى، ونبلغك عبر بريدك الإلكتروني.')
            : __('شكراً لتواصلك معنا! تم استلام رسالتك وسنرد عليك في أقرب وقت.');

        return redirect()->route('contact')->with('success', $success);
    }
}
