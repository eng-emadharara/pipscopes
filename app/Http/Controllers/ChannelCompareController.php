<?php

namespace App\Http\Controllers;

use App\Support\Analytics;
use App\Support\ChannelReport;
use Illuminate\Http\Request;

/** Two channels side by side, indicator by indicator (each one an App\Support\ChannelReport). The link is shareable. */
class ChannelCompareController extends Controller
{
    public function __invoke(Request $request)
    {
        $a = trim((string) $request->query('a', ''));
        $b = trim((string) $request->query('b', ''));

        $reports = $a !== '' && $b !== ''
            ? [ChannelReport::for(mb_substr($a, 0, 255)), ChannelReport::for(mb_substr($b, 0, 255))]
            : null;

        if ($reports) {
            Analytics::event('channel_compare', ['result' => collect($reports)->every(fn ($r) => $r['status'] === 'report') ? 'ok' : 'partial']);
        }

        // Every indicator either of them has, in the usual order
        $keys = $reports ? array_values(array_filter(ChannelReport::ORDER,
            fn ($key) => collect($reports)->contains(fn ($r) => isset($r['cards'][$key])))) : [];

        return view('channel-compare', compact('a', 'b', 'reports', 'keys'));
    }
}
