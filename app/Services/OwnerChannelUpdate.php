<?php

namespace App\Services;

use App\Models\Provider;
use App\Models\ProviderChange;
use App\Models\ProviderOwner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Applies a channel owner's edits to their channel. Only descriptive fields and links are
 * editable (name, categories, verification stay admin-only), and every real change is logged
 * as a ProviderChange for admins to review.
 */
class OwnerChannelUpdate
{
    /** Fields an owner may change */
    public const EDITABLE = [
        'service_type', 'pricing', 'bio', 'avatar', 'telegram_url', 'telegram_members', 'website_url',
        'twitter_url', 'instagram_url', 'youtube_url', 'tiktok_url', 'facebook_url', 'extra_links', 'translations',
    ];

    /**
     * @param  array<string, mixed>  $data  validated input (EDITABLE fields + market_ids)
     * @return array<string, array{old: mixed, new: mixed}> what changed
     */
    public function apply(Provider $provider, ProviderOwner $owner, array $data, ?UploadedFile $avatar = null, bool $removeAvatar = false): array
    {
        $before = $provider->only(self::EDITABLE);
        $marketsBefore = $provider->markets()->pluck('name')->sort()->values()->all();

        $fields = collect($data)->only(self::EDITABLE)->all();

        if ($avatar) {
            $fields['avatar'] = $avatar->store('providers', 'public');
        } elseif ($removeAvatar) {
            $fields['avatar'] = null;
        }

        $provider->fill($fields)->save();

        if (array_key_exists('market_ids', $data)) {
            $provider->markets()->sync($data['market_ids'] ?? []);
        }

        $changes = [];
        foreach ($fields as $field => $value) {
            if (static::normalize($before[$field] ?? null) !== static::normalize($value)) {
                $changes[$field] = ['old' => $before[$field] ?? null, 'new' => $value];
            }
        }

        $marketsAfter = $provider->markets()->pluck('name')->sort()->values()->all();
        if ($marketsBefore !== $marketsAfter) {
            $changes['markets'] = ['old' => $marketsBefore, 'new' => $marketsAfter];
        }

        // The replaced logo file is no longer referenced anywhere
        if (isset($changes['avatar']) && ($old = $changes['avatar']['old']) && ! str_starts_with($old, 'http')) {
            Storage::disk('public')->delete($old);
        }

        if ($changes) {
            ProviderChange::create(['provider_id' => $provider->id, 'provider_owner_id' => $owner->id, 'diff' => $changes]);
            $provider->refreshSearchIndex();
        }

        return $changes;
    }

    /** null, "", [] and nested empty values all count as "empty", so untouched fields aren't logged. */
    protected static function normalize(mixed $value): string
    {
        $clean = function ($v) use (&$clean) {
            if (is_array($v)) {
                $v = array_filter(array_map($clean, $v), fn ($x) => $x !== null);

                return $v === [] ? null : $v;
            }

            return $v === '' ? null : (is_numeric($v) ? (string) $v : $v);
        };

        return json_encode($clean($value), JSON_UNESCAPED_UNICODE);
    }
}
