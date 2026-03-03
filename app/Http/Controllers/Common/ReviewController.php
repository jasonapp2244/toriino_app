<?php

namespace App\Http\Controllers\Common;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\User;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'to_user_id' => 'nullable|exists:users,id',
            'session_id' => 'nullable|exists:mentor_sessions,id',
            'course_id'  => 'nullable|exists:courses,id',
            'rating'     => 'required|numeric|min:1|max:5',
            'comment'    => 'nullable|string|max:500',
        ]);

        $existing = Review::where('from_user_id', $request->user()->id)
            ->when($request->session_id, fn($q) => $q->where('session_id', $request->session_id))
            ->when($request->course_id,  fn($q) => $q->where('course_id',  $request->course_id))
            ->first();

        if ($existing) {
            return ApiResponse::error('You have already submitted a review');
        }

        $review = Review::create([
            'from_user_id' => $request->user()->id,
            'to_user_id'   => $request->to_user_id,
            'session_id'   => $request->session_id,
            'course_id'    => $request->course_id,
            'rating'       => $request->rating,
            'comment'      => $request->comment,
        ]);

        // update aggregate ratings
        if ($request->to_user_id) {
            $avg = Review::where('to_user_id', $request->to_user_id)->avg('rating');
            $cnt = Review::where('to_user_id', $request->to_user_id)->count();
            $target = User::find($request->to_user_id);
            $target?->mentorProfile()?->update(['rating' => $avg, 'total_reviews' => $cnt]);
            $target?->teacherProfile()?->update(['rating' => $avg, 'total_reviews' => $cnt]);
        }

        if ($request->course_id) {
            $avg = Review::where('course_id', $request->course_id)->avg('rating');
            Course::where('id', $request->course_id)->update(['rating' => $avg]);
        }

        return ApiResponse::created($review->load('fromUser.profile'), 'Review submitted');
    }

    public function indexForUser(int $userId): JsonResponse
    {
        $reviews = Review::where('to_user_id', $userId)
            ->with('fromUser.profile')
            ->orderByDesc('created_at')
            ->paginate(10);

        return ApiResponse::success($reviews);
    }

    public function indexForCourse(int $courseId): JsonResponse
    {
        $reviews = Review::where('course_id', $courseId)
            ->with('fromUser.profile')
            ->orderByDesc('created_at')
            ->paginate(10);

        return ApiResponse::success($reviews);
    }
}
