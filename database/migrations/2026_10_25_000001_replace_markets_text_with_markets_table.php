<?php

use App\Support\SearchText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Markets were free text ("فوركس، ذهب"); they become a managed list chosen by multiple choice,
 * so they can drive filters. Existing text is matched to the defaults, and any value that
 * doesn't match becomes a market of its own, so nothing is lost.
 */
return new class extends Migration
{
    /** slug => [Arabic name, English name, icon, spellings used to match the old text] */
    protected const DEFAULTS = [
        'forex' => ['فوركس', 'Forex', '💱', ['فوركس', 'فوريكس', 'العملات', 'عملات', 'forex', 'fx']],
        'gold' => ['ذهب', 'Gold', '🥇', ['ذهب', 'الذهب', 'gold', 'xauusd']],
        'crypto' => ['كريبتو', 'Crypto', '🪙', ['كريبتو', 'كريبتو', 'عملات رقمية', 'العملات الرقمية', 'عملات مشفرة', 'crypto', 'bitcoin', 'بيتكوين']],
        'stocks' => ['أسهم', 'Stocks', '📊', ['أسهم', 'الأسهم', 'stocks', 'shares']],
        'indices' => ['مؤشرات', 'Indices', '📈', ['مؤشرات', 'المؤشرات', 'indices', 'nasdaq', 'ناسداك']],
        'commodities' => ['سلع ونفط', 'Commodities & oil', '🛢️', ['سلع', 'نفط', 'النفط', 'سلع ونفط', 'oil', 'commodities']],
    ];

    public function up(): void
    {
        Schema::create('markets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->json('translations')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('market_provider', function (Blueprint $table) {
            $table->foreignId('market_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->primary(['market_id', 'provider_id']);
        });

        Schema::table('provider_submissions', fn (Blueprint $table) => $table->json('market_ids')->nullable()->after('category_ids'));

        $aliases = [];
        $order = 0;
        foreach (self::DEFAULTS as $slug => [$ar, $en, $icon, $spellings]) {
            $id = DB::table('markets')->insertGetId([
                'name' => $ar, 'slug' => $slug, 'icon' => $icon, 'sort_order' => $order++,
                'translations' => json_encode(['en' => ['name' => $en]], JSON_UNESCAPED_UNICODE),
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ([$ar, $en, ...$spellings] as $spelling) {
                $aliases[SearchText::normalize($spelling)] = $id;
            }
        }

        // Matches one free-text value to market ids, creating a market for unknown values
        $toIds = function (?string $text) use (&$aliases, &$order): array {
            $ids = [];
            foreach (preg_split('/\s*(?:[،,؛;\/|]|\s+و\s*|\s+and\s+)\s*/u', (string) $text) as $token) {
                $token = trim($token, " \t\n\r\0\x0B.-");
                if ($token === '') {
                    continue;
                }
                $key = SearchText::normalize($token);
                if (! isset($aliases[$key])) {
                    $aliases[$key] = DB::table('markets')->insertGetId([
                        'name' => $token, 'slug' => (Str::slug($token) ?: 'market').'-'.Str::lower(Str::random(4)),
                        'sort_order' => $order++, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $ids[] = $aliases[$key];
            }

            return array_values(array_unique($ids));
        };

        foreach (DB::table('providers')->get(['id', 'markets', 'translations']) as $provider) {
            foreach ($toIds($provider->markets) as $marketId) {
                DB::table('market_provider')->insert(['market_id' => $marketId, 'provider_id' => $provider->id]);
            }

            // The English free-text translation of markets is no longer used
            $translations = json_decode((string) $provider->translations, true) ?: [];
            foreach ($translations as $locale => $fields) {
                unset($translations[$locale]['markets']);
            }
            DB::table('providers')->where('id', $provider->id)
                ->update(['translations' => $translations ? json_encode($translations, JSON_UNESCAPED_UNICODE) : null]);
        }

        foreach (DB::table('provider_submissions')->whereNotNull('markets')->get(['id', 'markets']) as $submission) {
            DB::table('provider_submissions')->where('id', $submission->id)
                ->update(['market_ids' => json_encode($toIds($submission->markets))]);
        }

        Schema::table('providers', fn (Blueprint $table) => $table->dropColumn('markets'));
        Schema::table('provider_submissions', fn (Blueprint $table) => $table->dropColumn('markets'));

        Artisan::call('providers:reindex');
    }

    public function down(): void
    {
        Schema::table('providers', fn (Blueprint $table) => $table->string('markets')->nullable()->after('service_type'));
        Schema::table('provider_submissions', fn (Blueprint $table) => $table->string('markets')->nullable()->after('service_type'));

        $names = DB::table('markets')->pluck('name', 'id');

        DB::table('market_provider')->get()->groupBy('provider_id')->each(function ($rows, $providerId) use ($names) {
            DB::table('providers')->where('id', $providerId)
                ->update(['markets' => $rows->map(fn ($r) => $names[$r->market_id])->join('، ')]);
        });

        foreach (DB::table('provider_submissions')->whereNotNull('market_ids')->get(['id', 'market_ids']) as $submission) {
            DB::table('provider_submissions')->where('id', $submission->id)->update([
                'markets' => collect(json_decode($submission->market_ids, true))->map(fn ($id) => $names[$id] ?? null)->filter()->join('، ') ?: null,
            ]);
        }

        Schema::table('provider_submissions', fn (Blueprint $table) => $table->dropColumn('market_ids'));
        Schema::dropIfExists('market_provider');
        Schema::dropIfExists('markets');
    }
};
