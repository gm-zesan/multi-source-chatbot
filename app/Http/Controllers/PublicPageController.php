<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PublicPageController extends Controller
{
    public function home(): View
    {
        return view('public.home');
    }

    public function platform(): View
    {
        return view('public.platform');
    }

    public function knowledgeEngine(): View
    {
        return view('public.knowledge-engine');
    }

    public function unifiedInbox(): View
    {
        return view('public.unified-inbox');
    }

    public function businessIntelligence(): View
    {
        return view('public.business-intelligence');
    }

    public function automation(): View
    {
        return view('public.automation');
    }

    public function integrations(): View
    {
        return view('public.integrations');
    }

    public function solutions(): View
    {
        return view('public.solutions');
    }

    public function pricing(): View
    {
        return view('public.pricing');
    }

    public function about(): View
    {
        return view('public.about');
    }

    public function contact(): View
    {
        return view('public.contact');
    }

    public function resources(): View
    {
        return view('public.resources');
    }

    public function faq(): View
    {
        return view('public.faq');
    }

    public function privacy(): View
    {
        return view('public.privacy');
    }

    public function terms(): View
    {
        return view('public.terms');
    }

    public function cookies(): View
    {
        return view('public.cookies');
    }
}
