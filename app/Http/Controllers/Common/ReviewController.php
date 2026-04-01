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
            'to_user_id' => 'required_without_all:session_id,course_id|nullable|exists:users,id',
            'session_id' => 'required_without_all:to_user_id,course_id|nullable|exists:mentor_sessions,id',
            'course_id'  => 'required_without_all:to_user_id,session_id|nullable|exists:courses,id',
            'rating'     => 'required|numeric|min:1|max:5',
            'comment'    => 'nullable|string|max:500',
        ]);

        $existing = Review::where('from_user_id', $request->user()->id)
            ->when($request->session_id, fn($q) => $q->where('session_id', $request->session_id))
            ->when($request->course_id,  fn($q) => $q->where('course_id',  $request->course_id))
            ->when($request->to_user_id, fn($q) => $q->where('to_user_id', $request->to_user_id))
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

        // update aggregate ratings based on target user's role
        if ($request->to_user_id) {
            $avg = Review::where('to_user_id', $request->to_user_id)->avg('rating');
            $cnt = Review::where('to_user_id', $request->to_user_id)->count();
            $target = User::find($request->to_user_id);
            if ($target) {
                if ($target->hasRole('mentor')) {
                    $target->mentorProfile()?->update(['rating' => $avg, 'total_reviews' => $cnt]);
                }
                if ($target->hasRole('teacher')) {
                    $target->teacherProfile()?->update(['rating' => $avg, 'total_reviews' => $cnt]);
                }
            }
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
            ->with('fromUser')
            ->orderByDesc('created_at')
            ->paginate(10);

        $allRatings = Review::where('to_user_id', $userId)->pluck('rating');

        $ratingAverage = $allRatings->isNotEmpty()
            ? round($allRatings->avg(), 1)
            : 0;

        $distribution = [];
        for ($star = 5; $star >= 1; $star--) {
            $distribution[$star] = $allRatings->filter(fn($r) => (int) round($r) === $star)->count();
        }

        $reviewsData = $reviews->through(fn($r) => [
            'id'         => $r->id,
            'rating'     => $r->rating,
            'comment'    => $r->comment,
            'created_at' => $r->created_at,
            'reviewer'   => [
                'id'        => $r->fromUser?->id,
                'name'      => $r->fromUser?->full_name ?? $r->fromUser?->name,
                'photo_url' => $r->fromUser?->photo_url,
            ],
        ]);

        return ApiResponse::success([
            'rating_average'      => $ratingAverage,
            'total_reviews'       => $allRatings->count(),
            'rating_distribution' => $distribution,
            'reviews'             => $reviewsData,
        ]);
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
