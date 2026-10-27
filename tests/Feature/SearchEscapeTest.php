<?php

namespace Tests\Feature;

use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Search must build SQL that MySQL accepts too: ESCAPE '\' worked on SQLite but is a syntax
 * error on MySQL (the backslash escapes the closing quote), so every search failed with a 500 live.
 */
class SearchEscapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_sql_never_uses_a_backslash_escape(): void
    {
        Provider::create(['name' => 'Gold Pro', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/gold_pro', 'is_active' => true]);

        DB::enableQueryLog();
        $this->get(route('providers.index', ['q' => 'سشيسش']))->assertOk();
        $this->get(route('search.suggest', ['q' => 'gold']))->assertOk();
        $this->get(route('account-check', ['account' => '@someone']))->assertOk();

        $sql = implode("\n", array_column(DB::getQueryLog(), 'query'));
        $this->assertStringContainsString("LIKE ? ESCAPE '!'", $sql);
        $this->assertStringNotContainsString("ESCAPE '\\'", $sql);
    }

    public function test_wildcard_characters_in_a_search_match_literally(): void
    {
        Provider::create(['name' => 'Gold_Pro', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/gold_pro', 'is_active' => true]);
        Provider::create(['name' => 'GoldXPro', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/goldxpro', 'is_active' => true]);
        Provider::create(['name' => 'Win 100% Now!', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/win100', 'is_active' => true]);

        // "_" is not "any character": only the exact handle matches
        $this->get(route('providers.index', ['q' => 'gold_pro']))->assertSee('Gold_Pro')->assertDontSee('GoldXPro');
        $this->get(route('providers.index', ['q' => '100%']))->assertSee('Win 100% Now!');
        $this->get(route('providers.index', ['q' => 'now!']))->assertSee('Win 100% Now!');
    }
}
