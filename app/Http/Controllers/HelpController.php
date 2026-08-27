<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use Illuminate\View\View;

class HelpController extends Controller
{
    public function index(): View
    {
        $faqs = Faq::query()
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get(['id', 'question', 'answer', 'category']);

        return view('help.index', compact('faqs'));
    }
}
