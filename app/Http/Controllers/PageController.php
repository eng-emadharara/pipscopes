<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Provider;
use App\Models\Review;

class PageController extends Controller
{
    public function show(Page $page)
    {
        abort_unless($page->is_published || auth()->user()?->is_admin, 404);

        return view('pages.show', [
            'page' => $page,
            'content' => $page->sections(),
            // Live platform numbers shown at the bottom of the page
            'stats' => [
                'providers' => Provider::active()->count(),
                'reviews' => Review::where('status', Review::STATUS_APPROVED)->count(),
                'categories' => Category::where('is_active', true)->count(),
            ],
        ]);
    }
}
