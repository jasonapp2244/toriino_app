<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Database\Seeder;

class WithdrawalSeeder extends Seeder
{
    public function run(): void
    {
        $mentor  = User::where('email', 'mentor@turiino.com')->first();
        $teacher = User::where('email', 'teacher@turiino.com')->first();

        if (!$mentor || !$teacher) {
            $this->command->warn('WithdrawalSeeder: required users not found. Run AdminUserSeeder first.');
            return;
        }

        // Mentor approved withdrawal (paid out)
        Withdrawal::firstOrCreate(
            [
                'user_id'      => $mentor->id,
                'notes'        => 'Monthly payout for March sessions.',
            ],
            [
                'amount'       => 85.00,
                'bank_account' => 'IBAN: GB29NWBK60161331926819',
                'status'       => 'approved',
            ]
        );

        // Mentor pending withdrawal request
        Withdrawal::firstOrCreate(
            [
                'user_id' => $mentor->id,
                'notes'   => 'Payout request for April earnings.',
            ],
            [
                'amount'       => 127.50,
                'bank_account' => 'IBAN: GB29NWBK60161331926819',
                'status'       => 'pending',
            ]
        );

        // Teacher pending withdrawal request
        Withdrawal::firstOrCreate(
            [
                'user_id' => $teacher->id,
                'notes'   => 'First course sale payout.',
            ],
            [
                'amount'       => 44.99,
                'bank_account' => 'IBAN: DE89370400440532013000',
                'status'       => 'pending',
            ]
        );

        $this->command->info('✅ WithdrawalSeeder: 3 withdrawal records created (mentor: 1 completed + 1 pending, teacher: 1 pending).');
    }
}
