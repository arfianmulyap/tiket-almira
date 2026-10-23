<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Services\DashboardMetrics;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the admin dashboard (PRD FR-11).
     *
     * All figures come from DashboardMetrics, which reuses the same scopes as
     * the ordering and verification flows so the dashboard cannot drift from
     * the system's own status definitions.
     */
    public function index(DashboardMetrics $metrics): View
    {
        return view('admin.dashboard', [
            'summary' => $metrics->summary(),
            'ordersByStatus' => $metrics->ordersByStatus(),
            'approvedRevenue' => $metrics->approvedRevenue(),
            'eventRows' => $metrics->eventBreakdown(),
            'soldOutEvents' => $metrics->soldOutPublishedEvents(),
            'hasAnyData' => $metrics->hasAnyData(),
            'statuses' => OrderStatus::cases(),
        ]);
    }
}
