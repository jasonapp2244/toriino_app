<?php

namespace App\Http\Controllers\Teacher;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Earning;
use App\Models\Withdrawal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherEarningController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $thisMonth      = Earning::where('user_id', $user->id)->whereMonth('created_at', now()->month)->sum('amount');
        $totalWithdrawn = Withdrawal::where('user_id', $user->id)->where('status', 'approved')->sum('amount');
        $totalEarned    = Earning::where('user_id', $user->id)->sum('amount');

        $earnings = Earning::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        return ApiResponse::success([
            'summary' => [
                'this_month'      => round($thisMonth, 2),
                'total_withdrawn' => round($totalWithdrawn, 2),
                'balance'         => round($totalEarned - $totalWithdrawn, 2),
            ],
            'transactions' => $earnings,
        ]);
    }

    public function withdraw(Request $request): JsonResponse
    {
        $request->validate([
            'amount'       => 'required|numeric|min:1',
            'bank_account' => 'required|string',
        ]);

        $user           = $request->user();
        $totalEarned    = Earning::where('user_id', $user->id)->sum('amount');
        $totalWithdrawn = Withdrawal::where('user_id', $user->id)->where('status', 'approved')->sum('amount');
        $balance        = $totalEarned - $totalWithdrawn;

        if ($request->amount > $balance) {
            return ApiResponse::error('Insufficient balance');
        }

        $withdrawal = Withdrawal::create([
            'user_id'      => $user->id,
            'amount'       => $request->amount,
            'bank_account' => $request->bank_account,
        ]);

        return ApiResponse::created($withdrawal, 'Withdrawal request submitted');
    }
}
