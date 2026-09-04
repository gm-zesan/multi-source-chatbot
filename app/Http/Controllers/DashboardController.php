<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Support\Facades\Artisan;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    public function dashboard()
    {
        return view('admin.dashboard', [
            'metrics' => $this->dashboard->metrics(),
        ]);
    }

    public function cacheClear(){
        Artisan::call('optimize:clear');
        Artisan::call('view:clear');
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('route:clear');
        return back()->with('success', 'Cache Cleared Successfully');
    }

    public function changePassword()
    {
        return view('profile.change-password');
    }
}
