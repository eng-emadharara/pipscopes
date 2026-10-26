<?php

namespace App\Http\Controllers;

use App\Support\AccountCheck;
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

        if ($request->ajax()) {
            return view('home._account-check-result', compact('account', 'result'));
        }

        return view('account-check', compact('account', 'result'));
    }
}
