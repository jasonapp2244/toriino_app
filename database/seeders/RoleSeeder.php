<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Auth
            'assign-role',

            // Mentor
            'view-mentor-dashboard',
            'manage-sessions',
            'manage-availability',
            'manage-mentor-profile',
            'view-earnings',
            'request-withdrawal',
            'manage-subscription',

            // Teacher
            'view-teacher-dashboard',
            'manage-courses',
            'manage-lessons',
            'manage-teacher-profile',

            // Student
            'view-student-dashboard',
            'book-sessions',
            'enroll-courses',
            'manage-student-profile',

            // Common
            'use-ai-chat',
            'submit-review',
            'view-notifications',
            'submit-support-ticket',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Mentor role
        $mentor = Role::firstOrCreate(['name' => 'mentor', 'guard_name' => 'web']);
        $mentor->syncPermissions([
            'assign-role',
            'view-mentor-dashboard',
            'manage-sessions',
            'manage-availability',
            'manage-mentor-profile',
            'view-earnings',
            'request-withdrawal',
            'manage-subscription',
            'use-ai-chat',
            'submit-review',
            'view-notifications',
            'submit-support-ticket',
        ]);

        // Teacher role
        $teacher = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'web']);
        $teacher->syncPermissions([
            'assign-role',
            'view-teacher-dashboard',
            'manage-courses',
            'manage-lessons',
            'manage-teacher-profile',
            'view-earnings',
            'request-withdrawal',
            'use-ai-chat',
            'submit-review',
            'view-notifications',
            'submit-support-ticket',
        ]);

        // Student role
        $student = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $student->syncPermissions([
            'assign-role',
            'view-student-dashboard',
            'book-sessions',
            'enroll-courses',
            'manage-student-profile',
            'use-ai-chat',
            'submit-review',
            'view-notifications',
            'submit-support-ticket',
        ]);
    }
}
