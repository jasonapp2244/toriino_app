<?php

namespace App\Http\Controllers\Mentor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Availability;
use App\Models\MentorSession;
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

    /**
     * Set weekly availability.
     * Supports "use same timing for all days" — frontend sends same start/end for all selected days.
     *
     * Body:
     * {
     *   "slots": [
     *     { "day": "Monday", "start_time": "10:00", "end_time": "18:00", "min_students": 1, "max_students": 5 }
     *   ]
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'slots'                  => 'required|array|min:1',
            'slots.*.day'            => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'slots.*.start_time'     => 'required|date_format:H:i',
            'slots.*.end_time'       => 'required|date_format:H:i|after:slots.*.start_time',
            'slots.*.min_students'   => 'sometimes|integer|min:1|max:5',
            'slots.*.max_students'   => 'sometimes|integer|min:1|max:' . MentorSession::GROUP_MAX_SEATS,
        ]);

        $mentorId = $request->user()->id;

        foreach ($request->slots as $slot) {
            $minStudents = $slot['min_students'] ?? 1;
            $maxStudents = min($slot['max_students'] ?? MentorSession::GROUP_MAX_SEATS, MentorSession::GROUP_MAX_SEATS);

            Availability::updateOrCreate(
                ['mentor_id' => $mentorId, 'day' => $slot['day']],
                [
                    'start_time'   => $slot['start_time'],
                    'end_time'     => $slot['end_time'],
                    'min_students' => $minStudents,
                    'max_students' => $maxStudents,
                    'is_available' => true,
                ]
            );
        }

        $saved = Availability::where('mentor_id', $mentorId)
            ->orderByRaw("FIELD(day,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')")
            ->get();

        return ApiResponse::success($saved, 'Availability updated');
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
            'min_students' => 'sometimes|integer|min:1|max:5',
            'max_students' => 'sometimes|integer|min:1|max:' . MentorSession::GROUP_MAX_SEATS,
        ]);

        $data = $request->only(['start_time', 'end_time', 'is_available', 'min_students', 'max_students']);

        if (isset($data['max_students'])) {
            $data['max_students'] = min($data['max_students'], MentorSession::GROUP_MAX_SEATS);
        }

        $slot->update($data);

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
