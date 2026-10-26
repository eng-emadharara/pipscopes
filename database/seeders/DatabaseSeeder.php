<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Market;
use App\Models\Provider;
use App\Models\ProviderSubmission;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([PageSeeder::class, SiteSettingsSeeder::class]);

        // Local admin account. Override with ADMIN_EMAIL / ADMIN_PASSWORD in .env before seeding production.
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@trusttrader.test')],
            ['name' => 'مدير المنصة', 'password' => env('ADMIN_PASSWORD', 'TrustAdmin#2026'), 'is_admin' => true, 'email_verified_at' => now()]
        );

        $categories = collect([
            ['name' => 'قنوات توصيات', 'slug' => 'signals', 'icon' => '📈', 'description' => 'قنوات تيليجرام تقدم توصيات فوركس وذهب وأسهم.',
                'translations' => ['en' => ['name' => 'Signal channels', 'description' => 'Telegram channels sharing forex, gold and stock signals.']]],
            ['name' => 'نسخ الصفقات', 'slug' => 'copy-trading', 'icon' => '🔁', 'description' => 'خدمات نسخ الصفقات التلقائي وإدارة الحسابات.',
                'translations' => ['en' => ['name' => 'Copy trading', 'description' => 'Automatic trade copying and account management services.']]],
            ['name' => 'توصيات كريبتو', 'slug' => 'crypto', 'icon' => '🪙', 'description' => 'قنوات وخدمات العملات الرقمية.',
                'translations' => ['en' => ['name' => 'Crypto signals', 'description' => 'Cryptocurrency channels and services.']]],
            ['name' => 'تعليم وتدريب', 'slug' => 'education', 'icon' => '🎓', 'description' => 'دورات وأكاديميات تعليم التداول.',
                'translations' => ['en' => ['name' => 'Education', 'description' => 'Trading courses and academies.']]],
            ['name' => 'إدارة محافظ', 'slug' => 'portfolio', 'icon' => '💼', 'description' => 'مقدمو خدمات إدارة المحافظ والحسابات.',
                'translations' => ['en' => ['name' => 'Portfolio management', 'description' => 'Portfolio and account management providers.']]],
            ['name' => 'أدوات وبوتات', 'slug' => 'tools', 'icon' => '🤖', 'description' => 'روبوتات تداول ومؤشرات وأدوات تحليل.',
                'translations' => ['en' => ['name' => 'Tools & bots', 'description' => 'Trading robots, indicators and analysis tools.']]],
        ])->map(fn ($c, $i) => Category::updateOrCreate(['slug' => $c['slug']], $c + ['sort_order' => $i]));

        if (Provider::exists()) {
            return;
        }

        $users = User::factory(25)->create();

        $providers = [
            // [name, category slugs, service type, market slugs, pricing, target rating, verified, members, service type (en)]
            ['Gold Signals Pro', 'signals', 'توصيات ذهب يومية', 'gold,forex', 'freemium', 4.6, true, 48200, 'Daily gold signals'],
            ['قناة صقر الفوركس', 'signals', 'توصيات فوركس سكالبينج', 'forex', 'paid', 3.4, false, 15300, 'Forex scalping signals'],
            ['CopyMaster FX', 'copy-trading,signals', 'نسخ صفقات تلقائي عبر MT4/MT5', 'forex,indices', 'commission', 4.2, true, 9800, 'Automatic copy trading via MT4/MT5'],
            ['Crypto Whales AR', 'crypto,signals', 'توصيات عملات رقمية سبوت وفيوتشرز', 'crypto', 'freemium', 2.1, false, 120500, 'Crypto spot and futures signals'],
            ['أكاديمية المتداول الذكي', 'education,signals', 'دورات تحليل فني وإدارة مخاطر', 'forex,stocks', 'paid', 4.8, true, 31000, 'Technical analysis and risk management courses'],
            ['Smart Portfolio', 'portfolio', 'إدارة حسابات بنسبة من الأرباح', 'forex,stocks,crypto', 'commission', 3.0, false, 4200, 'Account management for a profit share'],
            ['قناة نجم الأسهم', 'signals', 'توصيات أسهم أمريكية وسعودية', 'stocks', 'free', 3.9, false, 22700, 'US and Saudi stock signals'],
            ['AlgoBot X', 'tools,copy-trading', 'روبوت تداول آلي للذهب', 'gold,commodities', 'paid', 2.7, false, 6100, 'Automated gold trading robot'],
        ];

        // Default markets are created by the markets migration
        $marketIds = Market::pluck('id', 'slug');

        foreach ($providers as $i => [$name, $cat, $service, $markets, $pricing, $target, $verified, $members, $serviceEn]) {
            $handle = 'demo_channel_'.($i + 1);
            $provider = Provider::create([
                'name' => $name,
                'slug' => 'provider-'.($i + 1),
                'service_type' => $service,
                'pricing' => $pricing,
                'bio' => "{$name} يقدم {$service}. هذه بيانات تجريبية لأغراض العرض فقط.",
                'translations' => ['en' => [
                    'service_type' => $serviceEn,
                    'bio' => "{$name} offers ".lcfirst($serviceEn).'. Demo data for display purposes only.',
                ]],
                'telegram_url' => "https://t.me/{$handle}",
                'telegram_members' => $members,
                'twitter_url' => $i % 2 ? "https://x.com/{$handle}" : null,
                'instagram_url' => $i % 3 === 0 ? "https://instagram.com/{$handle}" : null,
                'youtube_url' => $i % 4 === 0 ? "https://youtube.com/@{$handle}" : null,
                'website_url' => $verified ? "https://example.com/{$handle}" : null,
                'is_verified' => $verified,
                'is_featured' => $i < 3,
                'is_active' => true,
                'created_at' => now()->subMonths(rand(2, 18)),
            ]);

            // Comma-separated slugs: some demo providers belong to more than one category
            $provider->categories()->attach($categories->whereIn('slug', explode(',', $cat))->pluck('id'));
            $provider->markets()->attach($marketIds->only(explode(',', $markets))->values());
            $provider->refreshSearchIndex();

            $this->seedReviews($provider, $users->shuffle()->take(rand(6, 18)), $target);
        }

        ProviderSubmission::create([
            'submitter_name' => 'أبو خالد',
            'submitter_contact' => '@demo_owner',
            'relation' => 'owner',
            'category_ids' => [$categories->first()->id, $categories->firstWhere('slug', 'crypto')->id],
            'market_ids' => $marketIds->only(['gold', 'commodities'])->values()->all(),
            'name' => 'قناة النخبة للتوصيات',
            'service_type' => 'توصيات ذهب ونفط',
            'pricing' => 'paid',
            'description' => 'طلب تجريبي لإضافة قناة.',
            'telegram_url' => 'https://t.me/demo_elite',
        ]);
    }

    protected function seedReviews(Provider $provider, $users, float $target): void
    {
        $positive = [
            ['تجربة ممتازة', 'التزام واضح بالتوصيات ووقف الخسارة موجود دائماً، والنتائج موثقة يومياً. أنصح بها للمبتدئين والمحترفين.'],
            ['أفضل قناة جربتها', 'الدقة عالية والدعم يرد بسرعة على أي استفسار. حققت أرباحاً جيدة خلال الأشهر الماضية.'],
            ['خدمة محترمة', 'إدارة مخاطر ممتازة ولا يوجد ضغط للاشتراك، المحتوى التعليمي أيضاً مفيد جداً.'],
        ];
        $negative = [
            ['لا أنصح بها', 'خسائر متكررة وعند الخسارة يتم حذف الصفقات من القناة. الدعم لا يرد بعد الاشتراك.'],
            ['مبالغة في النتائج', 'النتائج المنشورة لا تعكس الواقع، والسعر مرتفع جداً مقارنة بالجودة المقدمة.'],
            ['تجربة سيئة', 'توصيات بدون وقف خسارة وضغط مستمر للإيداع لدى وسيط معين. احذروا.'],
        ];
        $mixed = [
            ['متوسطة', 'بعض التوصيات جيدة وبعضها خاسر، تحتاج لإدارة مخاطر أفضل لكن الدعم جيد.'],
        ];

        foreach ($users as $user) {
            $rating = (int) max(1, min(5, round($target + (mt_rand(-150, 150) / 100))));
            $pool = $rating >= 4 ? $positive : ($rating <= 2 ? $negative : $mixed);
            [$title, $body] = $pool[array_rand($pool)];
            $crit = fn () => max(1, min(5, $rating + rand(-1, 1)));

            Review::create([
                'provider_id' => $provider->id,
                'user_id' => $user->id,
                'rating' => $rating,
                'title' => $title,
                'body' => $body,
                'show_name' => (bool) rand(0, 3),
                'rating_accuracy' => $crit(),
                'rating_transparency' => $crit(),
                'rating_support' => $crit(),
                'rating_value' => $crit(),
                'pros' => $rating >= 3 ? collect(array_keys(__('trust.pros')))->random(rand(1, 3))->values()->all() : [],
                'cons' => $rating <= 3 ? collect(array_keys(__('trust.cons')))->random(rand(1, 3))->values()->all() : [],
                'experience_duration' => collect(array_keys(__('trust.experience_durations')))->random(),
                'status' => rand(0, 9) ? Review::STATUS_APPROVED : Review::STATUS_PENDING,
                'created_at' => now()->subDays(rand(1, 300)),
            ]);
        }
    }
}
