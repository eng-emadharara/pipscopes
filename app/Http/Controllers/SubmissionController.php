<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ProviderSubmission;
use App\Support\Launch;
use App\Support\SubmissionLinks;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubmissionController extends Controller
{
    /** Optional links besides Telegram */
    protected const OTHER_LINKS = ['website_url', 'twitter_url', 'instagram_url', 'youtube_url', 'tiktok_url', 'facebook_url'];

    public function create()
    {
        // Categories and markets for the form come from the view composer in AppServiceProvider
        return view('submissions.create');
    }

    /**
     * Called by the form as soon as the Telegram link is typed: is the channel already here
     * (or waiting for review)? If not, suggest its name from its public Telegram page.
     */
    public function check(Request $request)
    {
        $url = SubmissionLinks::telegram($request->query('telegram'));
        if (! SubmissionLinks::isTelegram($url)) {
            return response()->json(['status' => 'invalid']);
        }

        if ($provider = SubmissionLinks::existingProvider($url)) {
            return response()->json([
                'status' => 'exists',
                'name' => $provider->name,
                // Channel pages are closed before launch
                'url' => $provider->is_active && ! Launch::appliesToVisitor() ? route('providers.show', $provider) : null,
            ]);
        }

        if (SubmissionLinks::pendingSubmission($url)) {
            return response()->json(['status' => 'pending']);
        }

        return response()->json(['status' => 'new', 'url' => $url, 'title' => SubmissionLinks::telegramTitle($url)]);
    }

    public function store(Request $request)
    {
        // Honeypot: bots fill every field
        if (filled($request->input('website_confirm'))) {
            return redirect()->to(url()->previous().'#add-provider')->with('success', __('تم استلام طلبك.'));
        }

        // Accept "@channel", "t.me/channel" and links without "https://"
        $request->merge(['telegram_url' => SubmissionLinks::telegram($request->input('telegram_url'))]
            + collect(self::OTHER_LINKS)->mapWithKeys(fn ($field) => [$field => SubmissionLinks::url($request->input($field))])->all());

        $url = ['nullable', 'url', 'max:255'];

        $data = $request->validate([
            'submitter_name' => ['required', 'string', 'max:100'],
            'submitter_contact' => ['required', 'string', 'max:150'],
            'relation' => ['required', Rule::in(array_keys(ProviderSubmission::RELATIONS))],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'name' => ['required', 'string', 'max:150'],
            'service_type' => ['nullable', 'string', 'max:150'],
            'market_ids' => ['required', 'array', 'min:1'],
            'market_ids.*' => ['integer', Rule::exists('markets', 'id')->where('is_active', true)],
            'pricing' => ['nullable', Rule::in(array_keys(__('trust.pricing_options')))],
            'description' => ['nullable', 'string', 'max:3000'],
            'telegram_url' => ['required', 'url', 'max:255', function ($attribute, $value, $fail) {
                if (! SubmissionLinks::isTelegram($value)) {
                    $fail(__('أدخل رابط قناة تيليجرام صحيحاً، مثل t.me/channel أو @channel.'));
                } elseif ($provider = SubmissionLinks::existingProvider($value)) {
                    $fail(__('هذه القناة موجودة على المنصة باسم «:name».', ['name' => $provider->name]));
                } elseif (SubmissionLinks::pendingSubmission($value)) {
                    $fail(__('أرسل أحدهم هذه القناة مسبقاً وهي قيد المراجعة الآن.'));
                }
            }],
            ...array_fill_keys(self::OTHER_LINKS, $url),
        ], [], [
            'submitter_name' => __('اسمك'),
            'submitter_contact' => __('وسيلة التواصل'),
            'category_ids' => __('التصنيف'),
            'category_ids.*' => __('التصنيف'),
            'market_ids' => __('الأسواق'),
            'market_ids.*' => __('الأسواق'),
            'name' => __('اسم مقدم الخدمة'),
            'service_type' => __('نوع الخدمة'),
            'telegram_url' => __('رابط قناة التيليجرام'),
        ]);

        $data['category_ids'] = array_values(array_unique(array_map('intval', $data['category_ids'])));
        $data['market_ids'] = array_values(array_unique(array_map('intval', $data['market_ids'])));

        // Optional now; the admin sees the chosen categories instead and can refine it when approving
        $data['service_type'] = filled($data['service_type'] ?? null)
            ? $data['service_type']
            : Category::whereIn('id', $data['category_ids'])->orderBy('sort_order')->pluck('name')->implode(' / ');

        ProviderSubmission::create($data + ['status' => 'new']);

        // Back to wherever the form was (launch page or /submit)
        return redirect()->to(url()->previous().'#add-provider')
            ->with('success', __('تم استلام طلبك بنجاح! سيقوم فريقنا بمراجعة البيانات وإضافتها قريباً.'));
    }
}
