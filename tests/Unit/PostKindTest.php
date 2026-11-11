<?php

namespace Tests\Unit;

use App\Support\PostKind;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Sorting posts by what they are; the texts are real posts from public signals channels. */
class PostKindTest extends TestCase
{
    public static function posts(): array
    {
        return [
            // Trades given before they play out
            ['🔵 EURAUD — BUY NOW 📍 Entry: 1.6254 🎯 TP: 1.6274 ⚠️ HIGH RISK', PostKind::SIGNAL],
            ['SELL US30 NOW 44620✅ TP 44500🌪 TP 44300🌪 TP 44000🌪 SL 44700🧨', PostKind::SIGNAL],
            ["Xauusd buy 5010. & buy 5005\nSl ……\nTp 5030\nTp 5040\nTp open", PostKind::SIGNAL],
            ['تتابع مناطق بيع الذهب 4063 4067 ستوبك اغلاق واضح اعلى ال4080 هدفكك الاول 4034', PostKind::SIGNAL],
            ['ذهب شراء من 2650 الهدف 2660 الوقف 2640', PostKind::SIGNAL],

            // Results announced afterwards
            ['150pips✔️ done', PostKind::CLAIM],
            ['360 PIPS RUNNING ❤️‍🔥🚀', PostKind::CLAIM],
            ['مبروك الهدف الاول لذهب 150نقطه طربيقه', PostKind::CLAIM],
            ['التحليل ده اتحقق بنسبه 100% وصعد من المستوي المذكور', PostKind::CLAIM],
            ['الف نقطة بستوب 1$ 👑👑🔥', PostKind::CLAIM],
            ['مرسي صفر انعكاس', PostKind::CLAIM],

            // Selling something
            ['الي حابين يتداولوا بنفس البروكر الي بتداول في https://one.justmarkets.link/a/7zkl804aw7', PostKind::AD],
            ['🔥 عرض حصري لمتابعيني 🔥 👑 انضم إلى وكالتي واستفد من أقوى المميزات', PostKind::AD],
            ['🚀 للانضمام إلى القناة الخاصة | drtadawl VIP', PostKind::AD],
            ['أخيرًا، كورس DRTADAWL | Trading Masterclass بقى متاح', PostKind::AD],

            // Everything else, market news included
            ['🏛 بنك غولدمان ساكس يتوقع رفع معدلات الفائدة الأميركية بمقدار 25 نقطة أساس إضافية', PostKind::OTHER],
            ['📊 الذهب يرتفع بعد موجة جني أرباح', PostKind::OTHER],
            ['بخصوص الصفقات الي بنزلها ع الذهب اي دخول ستوب من 3 ل 5 والهدف كذلك سكالب', PostKind::OTHER],
            ['صباح الخير ❤️', PostKind::OTHER],
            ['', PostKind::OTHER],
        ];
    }

    #[DataProvider('posts')]
    public function test_posts_are_sorted_by_what_they_are(string $text, string $kind): void
    {
        $this->assertSame($kind, PostKind::of($text), $text);
    }

    public function test_broker_sign_up_links_are_named(): void
    {
        $this->assertSame(['JustMarkets'], PostKind::brokers('سجل هنا https://one.justmarkets.link/a/7zkl804aw7'));
        $this->assertSame(['Infinox'], PostKind::brokers('https://myaccount.infinox.com/ar/links/go/9935269'));
        $this->assertSame([], PostKind::brokers('https://www.youtube.com/watch?v=LXIPgnLI9u0'));
    }
}
