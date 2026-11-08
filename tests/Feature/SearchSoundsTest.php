<?php

namespace Tests\Feature;

use App\Models\Provider;
use App\Support\SearchText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Searching in Arabic finds Latin names and back: "قمر" finds "Qamar Alali - Richmama". */
class SearchSoundsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_word_sounds_the_same_in_both_alphabets(): void
    {
        foreach ([
            ['قمر', 'Qamar'],
            ['ريتشماما', 'Richmama'],
            ['أحمد', 'Ahmed'],
            ['خالد', 'Khaled'],
            ['شريف', 'Sherif'],
            ['ذهب', 'Dahab'],
            ['جولد', 'Gold'],
            ['فوركس', 'Forex'],
        ] as [$arabic, $latin]) {
            $this->assertSame(SearchText::soundKey($arabic), SearchText::soundKey($latin), "$arabic / $latin");
        }

        $this->assertNotSame(SearchText::soundKey('قمر'), SearchText::soundKey('Gold'));
    }

    protected function names(string $query): array
    {
        return Provider::active()->search($query)->pluck('name')->all();
    }

    public function test_arabic_searches_find_latin_names_and_back(): void
    {
        Provider::create(['name' => 'Qamar Alali - Richmama', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/richmama_signals', 'is_active' => true]);
        Provider::create(['name' => 'قناة صقر الفوركس', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/saqr_fx', 'is_active' => true]);
        Provider::create(['name' => 'Gold Signals Pro', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/goldpro', 'is_active' => true]);

        $this->assertSame(['Qamar Alali - Richmama'], $this->names('قمر'));
        $this->assertSame(['Qamar Alali - Richmama'], $this->names('ريتشماما'));
        $this->assertSame(['Qamar Alali - Richmama'], $this->names('قم'), 'the start of a word is enough');
        $this->assertSame(['قناة صقر الفوركس'], $this->names('saqr'));
        $this->assertSame(['Gold Signals Pro'], $this->names('جولد'));

        // Typing the way it's written still works as before
        $this->assertSame(['Qamar Alali - Richmama'], $this->names('qamar'));

        // The live suggestions use the same search
        $this->getJson(route('search.suggest', ['q' => 'قمر']))->assertOk()->assertJsonPath('providers.0.name', 'Qamar Alali - Richmama');
    }

    public function test_short_or_unrelated_words_do_not_match_everything(): void
    {
        Provider::create(['name' => 'Qamar Alali - Richmama', 'service_type' => 'Signals', 'telegram_url' => 'https://t.me/richmama_signals', 'is_active' => true]);

        $this->assertSame([], $this->names('سمير'));
        $this->assertSame([], $this->names('ر'));
    }

    public function test_renaming_a_channel_updates_how_it_sounds(): void
    {
        $channel = Provider::create(['name' => 'Old Name', 'service_type' => 'Signals', 'is_active' => true]);
        $channel->update(['name' => 'Qamar Alali']);

        $this->assertSame(['Qamar Alali'], $this->names('قمر'));
    }
}
