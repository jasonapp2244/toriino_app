<?php

namespace App\Http\Controllers\Common;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return ApiResponse::success($tickets);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $ticket = SupportTicket::create([
            'user_id' => $request->user()->id,
            'subject' => $request->subject,
            'message' => $request->message,
        ]);

        return ApiResponse::created($ticket, 'Support ticket submitted');
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $ticket = SupportTicket::where('user_id', $request->user()->id)->find($id);

        if (!$ticket) {
            return ApiResponse::notFound('Ticket not found');
        }

        return ApiResponse::success($ticket);
    }

    public function close(int $id, Request $request): JsonResponse
    {
        $ticket = SupportTicket::where('user_id', $request->user()->id)->find($id);

        if (!$ticket) {
            return ApiResponse::notFound('Ticket not found');
        }

        if ($ticket->status === 'closed') {
            return ApiResponse::error('Ticket is already closed.');
        }

        $ticket->update(['status' => 'closed']);

        return ApiResponse::success($ticket, 'Ticket closed successfully.');
    }
}
