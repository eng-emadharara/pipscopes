<?php

namespace Tests\Unit;

use App\Support\PostKind;
use App\Support\TradingChannel;
use Tests\TestCase;

/** Telling a trading channel from any other channel someone checks. */
class TradingChannelTest extends TestCase
{
    protected function posts(array $texts): array
    {
        return array_map(fn ($t) => ['text' => $t], $texts);
    }

    protected function kinds(array $texts): array
    {
        return collect($texts)->map(fn ($t) => PostKind::of($t))->countBy()->all();
    }

    protected function verdict(string $title, ?string $description, array $texts): string
    {
        return TradingChannel::verdict($title, $description, $this->posts($texts), $this->kinds($texts));
    }

    public function test_the_name_or_description_is_enough(): void
    {
        $this->assertSame(TradingChannel::TRADING, $this->verdict('Bageera Forex', null, []));
        $this->assertSame(TradingChannel::TRADING, $this->verdict('Qamar Alali - Richmama', 'قناة توصيات للذهب', []));
        $this->assertSame(TradingChannel::TRADING, $this->verdict('الباشا', 'تحليل الذهب XAUUSD', []));
    }

    public function test_the_posts_decide_when_the_name_says_nothing(): void
    {
        $this->assertSame(TradingChannel::TRADING, $this->verdict('Millioner', null, [
            'Xauusd buy 5010 Tp 5030 Sl 4990', 'SELL GOLD NOW 2814 Tp 2800 Sl 2830', 'صباح الخير',
        ]));
        $this->assertSame(TradingChannel::TRADING, $this->verdict('Mustafa', null, ['80نقطه لذهب من جديد', 'صفقات اليوم على الذهب', 'السلام عليكم']));
    }

    public function test_news_and_personal_channels_are_not_trading(): void
    {
        // Real posts: "gold" medals and a crypto wallet are not trading
        $this->assertSame(TradingChannel::NOT_TRADING, $this->verdict('Pavel Durov', 'Founder of Telegram', [
            '🏆 The International Olympiad in Informatics ends ✅ 4 gold medals',
            '🌍 This summer will see the largest rollout of a non-custodial crypto wallet',
            '700K+ self-custodial wallets created on Telegram ✅',
            'A high-school student just made $20,000 from a Telegram Gift.',
        ]));
        $this->assertSame(TradingChannel::NOT_TRADING, $this->verdict('الجزيرة', 'أخبار عاجلة', [
            'عاجل: ارتفاع أسعار النفط والدولار', 'مباراة اليوم في الدوري', 'الطقس غداً',
        ]));
    }

    public function test_without_posts_to_read_it_stays_unsure(): void
    {
        $this->assertSame(TradingChannel::UNSURE, $this->verdict('Some Channel', null, []));
    }
}
