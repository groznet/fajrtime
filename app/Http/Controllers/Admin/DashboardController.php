<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAction;
use App\Models\Challenge;
use App\Models\Withdrawal;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.index', [
            'pendingWithdrawals' => Withdrawal::where('status', 'requested')->count(),
            'challenges' => Challenge::whereIn('status', Challenge::OPEN)->with('group')->orderBy('start_date')->get(),
            'actions' => AdminAction::with('admin:id,name')->latest('id')->limit(20)->get(),
        ]);
    }
}
