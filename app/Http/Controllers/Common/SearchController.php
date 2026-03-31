<?php

namespace App\Http\Controllers\Common;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q'      => 'required|string|min:2',
            'type'   => 'nullable|in:mentor,teacher,course,all',
        ]);

        $q    = $request->q;
        $type = $request->type ?? 'all';

        $result = [];

        if (in_array($type, ['mentor', 'all'])) {
            $result['mentors'] = User::with(['profile', 'mentorProfile'])
                ->whereHas('roles', fn($r) => $r->where('name', 'mentor'))
                ->where(fn($u) => $u->where('name', 'like', "%$q%")
                    ->orWhereHas('mentorProfile', fn($mp) =>
                        $mp->where('specialization', 'like', "%$q%")
                    )
                )
                ->take(10)
                ->get();
        }

        if (in_array($type, ['teacher', 'all'])) {
            $result['teachers'] = User::with(['profile', 'teacherProfile'])
                ->whereHas('roles', fn($r) => $r->where('name', 'teacher'))
                ->where(fn($u) => $u->where('name', 'like', "%$q%")
                    ->orWhereHas('teacherProfile', fn($tp) =>
                        $tp->where('subject', 'like', "%$q%")
                    )
                )
                ->take(10)
                ->get();
        }

        if (in_array($type, ['course', 'all'])) {
            $result['courses'] = Course::with('teacher.profile')
                ->where('status', 'published')
                ->where(fn($c) =>
                    $c->where('title', 'like', "%$q%")
                      ->orWhere('description', 'like', "%$q%")
                      ->orWhere('category', 'like', "%$q%")
                )
                ->take(10)
                ->get();
        }

        return ApiResponse::success($result);
    }

    public function mentors(Request $request): JsonResponse
    {
        $mentors = User::with(['profile', 'mentorProfile'])
            ->whereHas('roles', fn($r) => $r->where('name', 'mentor'))
            ->when($request->specialization, fn($q) =>
                $q->whereHas('mentorProfile', fn($mp) => $mp->where('specialization', 'like', '%' . $request->specialization . '%'))
            )
            ->when($request->language, fn($q) =>
                $q->whereHas('mentorProfile', fn($mp) => $mp->where('languages', 'like', '%' . $request->language . '%'))
            )
            ->when($request->verified, fn($q) =>
                $q->whereHas('mentorProfile', fn($mp) => $mp->where('is_verified', true))
            )
            ->orderByDesc('id')
            ->paginate(10);

        return ApiResponse::success($mentors);
    }

    public function teachers(Request $request): JsonResponse
    {
        $teachers = User::with(['profile', 'teacherProfile'])
            ->withCount([
                'courses as available_courses_count' => fn($q) => $q->where('status', 'published'),
            ])
            ->whereHas('roles', fn($r) => $r->where('name', 'teacher'))
            ->whereHas('teacherProfile')
            ->when($request->search, fn($q) =>
                $q->where(fn($u) =>
                    $u->where('name', 'like', '%' . $request->search . '%')
                      ->orWhereHas('teacherProfile', fn($tp) =>
                          $tp->where('subject', 'like', '%' . $request->search . '%')
                             ->orWhere('designation', 'like', '%' . $request->search . '%')
                             ->orWhereJsonContains('expertise_list', $request->search)
                      )
                )
            )
            ->when($request->subject, fn($q) =>
                $q->whereHas('teacherProfile', fn($tp) => $tp->where('subject', 'like', '%' . $request->subject . '%'))
            )
            ->when($request->language, fn($q) =>
                $q->whereHas('teacherProfile', fn($tp) => $tp->where('languages', 'like', '%' . $request->language . '%'))
            )
            ->when($request->verified, fn($q) =>
                $q->whereHas('teacherProfile', fn($tp) => $tp->where('is_verified', true))
            )
            ->orderByDesc(
                \App\Models\TeacherProfile::select('rating')
                    ->whereColumn('user_id', 'users.id')
                    ->limit(1)
            )
            ->paginate(10)
            ->through(fn($t) => [
                'id'               => $t->id,
                'name'             => $t->name,
                'full_name'        => $t->full_name ?? $t->name,
                'photo_url'        => $t->photo_url,
                'designation'      => $t->teacherProfile?->designation,
                'subject'          => $t->teacherProfile?->subject,
                'short_bio'        => $t->teacherProfile?->short_bio,
                'expertise_list'   => $t->teacherProfile?->expertise_list ?? [],
                'languages_list'   => $t->teacherProfile?->languages_list ?? [],
                'rating'           => $t->teacherProfile?->rating,
                'total_reviews'    => $t->teacherProfile?->total_reviews,
                'is_verified'      => $t->teacherProfile?->is_verified,
                'experience_years' => $t->teacherProfile?->experience_years,
                'available_courses'=> $t->available_courses_count ?? 0,
            ]);

        return ApiResponse::success($teachers);
    }

    public function privacyPolicy(): JsonResponse
    {
        $policy = \App\Models\PrivacyPolicy::getActive();

        if (!$policy) {
            return ApiResponse::notFound('Privacy policy not found');
        }

        return ApiResponse::success($policy);
    }
}
