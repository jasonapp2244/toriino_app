<?php

namespace App\Http\Controllers\Mentor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Availability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MentorAvailabilityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $availability = Availability::where('mentor_id', $request->user()->id)
            ->orderByRaw("FIELD(day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')")
            ->get();

        return ApiResponse::success($availability);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'slots'              => 'required|array|min:1',
            'slots.*.day'        => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'slots.*.start_time' => 'required|date_format:H:i',
            'slots.*.end_time'   => 'required|date_format:H:i|after:slots.*.start_time',
        ]);

        $mentorId = $request->user()->id;

        foreach ($request->slots as $slot) {
            Availability::updateOrCreate(
                ['mentor_id' => $mentorId, 'day' => $slot['day']],
                [
                    'start_time'   => $slot['start_time'],
                    'end_time'     => $slot['end_time'],
                    'is_available' => true,
                ]
            );
        }

        return ApiResponse::success(
            Availability::where('mentor_id', $mentorId)->get(),
            'Availability updated'
        );
    }

    public function update(int $id, Request $request): JsonResponse
    {
        $slot = Availability::where('mentor_id', $request->user()->id)->find($id);

        if (!$slot) {
            return ApiResponse::notFound('Availability slot not found');
        }

        $request->validate([
            'start_time'   => 'sometimes|date_format:H:i',
            'end_time'     => 'sometimes|date_format:H:i',
            'is_available' => 'sometimes|boolean',
        ]);

        $slot->update($request->only(['start_time', 'end_time', 'is_available']));

        return ApiResponse::success($slot, 'Slot updated');
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $slot = Availability::where('mentor_id', $request->user()->id)->find($id);

        if (!$slot) {
            return ApiResponse::notFound('Availability slot not found');
        }

        $slot->delete();

        return ApiResponse::success(null, 'Slot deleted');
    }

    public function publicAvailability(int $mentorId): JsonResponse
    {
        $slots = Availability::where('mentor_id', $mentorId)
            ->where('is_available', true)
            ->orderByRaw("FIELD(day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')")
            ->get();

        return ApiResponse::success($slots);
    }
}
