<?php

namespace App\Http\Controllers\Common;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AppLanguage;
use App\Models\CourseCategory;
use App\Models\CourseLevel;
use App\Models\Industry;
use Illuminate\Http\JsonResponse;

class CourseDropdownController extends Controller
{
    // ─── Course dropdowns ─────────────────────────────────────────

    public function categories(): JsonResponse
    {
        $categories = CourseCategory::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return ApiResponse::success($categories);
    }

    public function levels(): JsonResponse
    {
        $levels = CourseLevel::where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name', 'slug']);

        return ApiResponse::success($levels);
    }

    // ─── Mentor profile dropdowns ─────────────────────────────────

    /**
     * Industry list for mentor profile setup.
     * GET /industries
     */
    public function industries(): JsonResponse
    {
        $industries = Industry::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return ApiResponse::success($industries);
    }

    /**
     * Language list for mentor/teacher profile multi-select.
     * GET /languages
     */
    public function languages(): JsonResponse
    {
        $languages = AppLanguage::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return ApiResponse::success($languages);
    }

    /**
     * Preferred student level options for mentor profile.
     * Reuses course_levels — same data (Beginner, Intermediate, Advanced, All Levels).
     * GET /mentor-student-levels
     */
    public function mentorStudentLevels(): JsonResponse
    {
        $levels = CourseLevel::where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name', 'slug']);

        return ApiResponse::success($levels);
    }
}
