<?php

namespace App\Http\Controllers;

use App\Support\AccountCheck;
use App\Support\Analytics;
use App\Support\HomeSections;
use Illuminate\Http\Request;

class AccountCheckController extends Controller
{
    /** The homepage tool fetches just the result; without JS the form lands on a page of its own. */
    public function __invoke(Request $request)
    {
        abort_unless(HomeSections::enabled('account_check'), 404);

        $account = trim((string) $request->query('account', ''));
        $result = $account === '' ? null : AccountCheck::run(mb_substr($account, 0, 255));

        // Which way the check went (official / impersonator / unknown): the tool's value in one number
        $checked = $result ? ['result' => $result['status'], 'platform' => $result['query']['platform'] ?? 'any'] : null;

        if ($request->ajax()) {
            // The homepage script reads this and sends the event itself
            return response()->view('home._account-check-result', compact('account', 'result'))
                ->header('X-Analytics-Event', $checked ? json_encode(['name' => 'account_check', 'params' => $checked]) : '');
        }

        if ($checked) {
            Analytics::event('account_check', $checked);
        }

        return view('account-check', compact('account', 'result'));
    }
}
