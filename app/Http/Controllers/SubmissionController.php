<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ProviderSubmission;
use App\Services\TelegramChannel;
use App\Support\Analytics;
use App\Support\Launch;
use App\Support\SubmissionLinks;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * "Add a channel": the visitor gives the Telegram link, markets and categories only; the name,
 * description, photo and stats are read from the channel's public page (App\Services\TelegramChannel).
 * Who sent it is not asked.
 */
class SubmissionController extends Controller
{
    public function create()
    {
        // Categories and markets for the form come from the view composer in AppServiceProvider
        return view('submissions.create');
    }

    /** Called while the link is typed: can this channel be added? Shows a preview when it can. */
    public function check(Request $request)
    {
        $url = SubmissionLinks::telegram($request->query('telegram'));
        if ($problem = $this->duplicate($url)) {
            return response()->json($problem);
        }

        $channel = TelegramChannel::read($url);
        if ($channel['status'] !== TelegramChannel::CHANNEL) {
            return response()->json(['status' => $channel['status'], 'message' => self::message($channel['status'])]);
        }

        return response()->json([
            'status' => 'new',
            'url' => $channel['url'],
            'title' => $channel['title'],
            'photo' => $channel['photo_url'],
            'subscribers' => $channel['subscribers'] ? trans_choice(':count مشترك', $channel['subscribers'], ['count' => number_format($channel['subscribers'])]) : null,
            'verified' => $channel['verified'],
        ]);
    }

    public function store(Request $request)
    {
        // Honeypot: bots fill every field
        if (filled($request->input('website_confirm'))) {
            return redirect()->to(url()->previous().'#add-provider')->with('success', __('تم استلام طلبك.'));
        }

        // Accept "@channel", "t.me/channel" and links without "https://"
        $request->merge(['telegram_url' => SubmissionLinks::telegram($request->input('telegram_url'))]);

        $data = $request->validate([
            'telegram_url' => ['required', 'string', 'max:255'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'market_ids' => ['required', 'array', 'min:1'],
            'market_ids.*' => ['integer', Rule::exists('markets', 'id')->where('is_active', true)],
        ], [], [
            'telegram_url' => __('رابط قناة التيليجرام'),
            'category_ids' => __('التصنيف'),
            'category_ids.*' => __('التصنيف'),
            'market_ids' => __('الأسواق'),
            'market_ids.*' => __('الأسواق'),
        ]);

        $fail = fn (string $message) => throw ValidationException::withMessages(['telegram_url' => $message]);

        if ($problem = $this->duplicate($data['telegram_url'])) {
            $fail($problem['message']);
        }

        $channel = TelegramChannel::read($data['telegram_url']);
        if ($channel['status'] !== TelegramChannel::CHANNEL) {
            $fail(self::message($channel['status']));
        }

        $categoryIds = array_values(array_unique(array_map('intval', $data['category_ids'])));

        ProviderSubmission::create([
            'telegram_url' => $channel['url'],
            'category_ids' => $categoryIds,
            'market_ids' => array_values(array_unique(array_map('intval', $data['market_ids']))),
            'name' => $channel['title'],
            'description' => $channel['description'],
            // The admin can refine it when approving; until then the chosen categories describe it
            'service_type' => Category::whereIn('id', $categoryIds)->orderBy('sort_order')->pluck('name')->implode(' / '),
            // Our own copy of the photo; Telegram's URL only if the download failed
            'avatar' => TelegramChannel::storePhoto($channel['photo_url'], $channel['username']) ?? $channel['photo_url'],
            'telegram_data' => [
                'username' => $channel['username'],
                'subscribers' => $channel['subscribers'],
                'verified' => $channel['verified'],
                ...$channel['stats'],
            ],
            'status' => 'new',
        ]);

        Analytics::flash('channel_submit', [
            'channel_name' => $channel['title'],
            'channel_category' => Category::whereIn('id', $categoryIds)->pluck('slug')->implode(','),
            'channel_subscribers' => $channel['subscribers'],
        ]);

        // Back to wherever the form was (launch page or /submit)
        return redirect()->to(url()->previous().'#add-provider')
            ->with('success', __('تم استلام القناة «:name» بنجاح! سيراجعها فريقنا ويضيفها قريباً.', ['name' => $channel['title']]));
    }

    /** Already on the platform, or already waiting for review? */
    protected function duplicate(?string $url): ?array
    {
        if (! $url) {
            return ['status' => TelegramChannel::INVALID, 'message' => self::message(TelegramChannel::INVALID)];
        }

        if ($provider = SubmissionLinks::existingProvider($url)) {
            return [
                'status' => 'exists',
                'name' => $provider->name,
                'message' => __('هذه القناة موجودة على المنصة باسم «:name».', ['name' => $provider->name]),
                // Channel pages are closed before launch
                'url' => $provider->is_active && ! Launch::appliesToVisitor() ? route('providers.show', $provider) : null,
            ];
        }

        if (SubmissionLinks::pendingSubmission($url)) {
            return ['status' => 'pending', 'message' => __('أرسل أحدهم هذه القناة مسبقاً وهي قيد المراجعة الآن.')];
        }

        return null;
    }

    public static function message(string $status): string
    {
        return match ($status) {
            TelegramChannel::PRIVATE => __('هذه القناة خاصة، ولا يمكن إضافتها. نضيف القنوات العامة فقط.'),
            TelegramChannel::NOT_CHANNEL => __('هذا الرابط لحساب شخصي أو مجموعة أو بوت، وليس لقناة.'),
            TelegramChannel::NOT_FOUND => __('لم نجد قناة بهذا الرابط على تيليجرام. تأكد من كتابته بشكل صحيح.'),
            TelegramChannel::UNREACHABLE => __('تعذّر الوصول إلى تيليجرام الآن. حاول مرة أخرى بعد قليل.'),
            default => __('أدخل رابط قناة تيليجرام صحيحاً، مثل t.me/channel أو @channel.'),
        };
    }
}
