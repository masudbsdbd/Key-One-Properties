<?php

namespace App\Http\Controllers;

use App\Support\ApprovalQueue;
use App\Support\DashboardStats;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardStats $stats, ApprovalQueue $approvals): View
    {
        return view('dashboard', [
            'stats' => $stats->for($request->user()),
            'approvals' => $approvals->for($request->user()),
            'greeting' => $this->greeting(),
        ]);
    }

    private function greeting(): string
    {
        $hour = (int) now(config('app.display_timezone'))->format('G');

        return match (true) {
            $hour < 12 => 'Good Morning',
            $hour < 17 => 'Good Afternoon',
            default => 'Good Evening',
        };
    }
}
