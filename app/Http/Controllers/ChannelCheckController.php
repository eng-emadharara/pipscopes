<?php

namespace App\Http\Controllers;

use App\Support\Analytics;
use App\Support\ChannelReport;
use Illuminate\Http\Request;

/** "Check a channel": paste any Telegram channel link, get a report (App\Support\ChannelReport). The link is shareable. */
class ChannelCheckController extends Controller
{
    public function __invoke(Request $request)
    {
        $input = trim((string) $request->query('channel', ''));
        $report = $input === '' ? null : ChannelReport::for(mb_substr($input, 0, 255));

        if ($report) {
            Analytics::event('channel_check', array_filter([
                'result' => $report['status'] === 'report' ? ($report['provider'] ? 'listed' : 'not_listed') : $report['status'],
                'preview' => isset($report['preview']) ? ($report['preview'] ? 'yes' : 'no') : null,
            ]));
        }

        return view('channel-check', ['input' => $input, 'report' => $report]);
    }
}
