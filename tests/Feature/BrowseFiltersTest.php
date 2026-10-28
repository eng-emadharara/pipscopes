<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The browse page's filters: "what does it offer" chips, the result count and active-filter chips. */
class BrowseFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $signals = Category::create(['name' => 'قنوات توصيات', 'slug' => 'signals', 'sort_order' => 0]);
        $copy = Category::create(['name' => 'نسخ الصفقات', 'slug' => 'copy-trading', 'sort_order' => 1]);
        Category::create(['name' => 'تعليم', 'slug' => 'education', 'sort_order' => 2]);

        foreach ([['Signal One', $signals], ['Signal Two', $signals], ['Copy One', $copy]] as [$name, $category]) {
            Provider::create(['name' => $name, 'service_type' => 'x', 'telegram_url' => 'https://t.me/'.str_replace(' ', '_', $name), 'is_active' => true])
                ->categories()->attach($category->id);
        }
    }

    public function test_what_it_offers_filter_takes_several_categories(): void
    {
        $this->get(route('providers.index', ['categories' => ['signals']]))
            ->assertSee('Signal One')->assertSee('Signal Two')->assertDontSee('Copy One')
            ->assertSee(trans_choice(':count نتيجة متوفرة', 2, ['count' => 2]));

        $this->get(route('providers.index', ['categories' => ['signals', 'copy-trading']]))
            ->assertSee('Copy One')->assertSee('Signal One')
            ->assertSee(trans_choice(':count نتيجة متوفرة', 3, ['count' => 3]));

        $this->get(route('providers.index', ['categories' => ['education']]))
            ->assertSee(__('لم نجد نتائج مطابقة.'));
    }

    public function test_category_link_from_the_homepage_arrives_already_filtered(): void
    {
        $this->get(route('categories.show', 'signals'))
            ->assertOk()
            ->assertSee('<h1 class="text-2xl font-bold md:text-3xl">', false)
            ->assertSee('قنوات توصيات')
            ->assertSee('type="radio" name="categories[]" value="signals" form="provider-filters" class="sr-only" checked', false)
            ->assertDontSee('Copy One');

        // The older ?category= link still works
        $this->get(route('providers.index', ['category' => 'copy-trading']))->assertSee('Copy One')->assertDontSee('Signal One');
    }

    public function test_active_filters_show_as_removable_chips(): void
    {
        $this->get(route('providers.index', ['categories' => ['signals'], 'verified' => 1]))
            ->assertSee('data-clear-filter data-name="categories[]" data-value="signals"', false)
            ->assertSee('data-clear-filter data-name="verified" data-value="1"', false);

        $this->get(route('providers.index'))->assertDontSee('data-clear-filter', false);
    }

    public function test_each_option_shows_how_many_results_it_would_give(): void
    {
        $html = $this->get(route('providers.index', ['categories' => ['signals']]))->getContent();

        $count = fn (int $n) => preg_quote(trans_choice(':count نتيجة متوفرة', $n, ['count' => $n]), '/');

        // Category tabs carry their count in the tooltip, ignoring the category filter itself:
        // 3 in all, 2 signals, 1 copy trading, 0 education
        $this->assertMatchesRegularExpression('/title="'.$count(3).'">\s*<input type="radio" name="categories\[\]" value=""/u', $html);
        $this->assertMatchesRegularExpression('/title="'.$count(2).'">\s*<input type="radio" name="categories\[\]" value="signals"[^>]*checked/u', $html);
        $this->assertMatchesRegularExpression('/title="'.$count(1).'">\s*<input type="radio" name="categories\[\]" value="copy-trading"/u', $html);
        // A tab that would give nothing is dimmed and can't be picked
        $this->assertMatchesRegularExpression('/opacity-35" title="'.$count(0).'">\s*<input type="radio" name="categories\[\]" value="education"[^>]*disabled/u', $html);

        // Market options show their count beside the name
        $this->assertMatchesRegularExpression('/name="markets\[\]" value="gold"[^>]*>\s*\S+ ذهب\s*<span class="text-xs text-gray-400">0</u', $html);
    }

    public function test_minimum_reviews_hides_ratings_built_on_too_few_reviews(): void
    {
        Provider::where('name', 'Signal One')->update(['reviews_count' => 30, 'rating_avg' => 4.2]);
        Provider::where('name', 'Signal Two')->update(['reviews_count' => 1, 'rating_avg' => 5]);

        $this->get(route('providers.index', ['min_reviews' => 20]))
            ->assertSee('Signal One')
            ->assertDontSee('Signal Two')
            ->assertSee('data-clear-filter data-name="min_reviews" data-value="20"', false);

        // Together with a minimum score: the lone 5.0 doesn't make it
        $this->get(route('providers.index', ['min_rating' => 4.5, 'min_reviews' => 5]))
            ->assertSee(__('لم نجد نتائج مطابقة.'));

        // Values outside the offered choices are ignored
        $this->get(route('providers.index', ['min_reviews' => 7]))->assertSee('Signal Two');
    }

    public function test_results_are_cards_with_the_sort_beside_the_count(): void
    {
        $html = $this->get(route('providers.index', ['sort' => 'newest']))
            ->assertSee('class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" data-results-grid', false)
            ->assertDontSee('data-view=', false)
            ->getContent();

        // One sort control, in the results line, tied to the filters form
        $this->assertSame(1, substr_count($html, 'name="sort"'));
        $this->assertMatchesRegularExpression('/<select id="sort" name="sort" form="provider-filters"[^>]*>.*<option value="newest" selected>/s', $html);
    }

    public function test_phones_get_a_menu_and_a_filters_sheet(): void
    {
        $this->get(route('providers.index'))
            ->assertSee('id="mobile-menu"', false)
            ->assertSee('data-open-filters', false)
            ->assertSee(trans_choice('عرض :count نتيجة', 3, ['count' => 3]));
    }
}
