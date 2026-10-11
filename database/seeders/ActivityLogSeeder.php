<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Audit-trail filler matching the action names the app really writes. */
class ActivityLogSeeder extends Seeder
{
    public function run(): void
    {
        if (ActivityLog::query()->exists()) {
            return;
        }

        $frontdesk = User::query()->where('username', 'frontdesk')->first();
        $admin = User::query()->where('username', 'admin')->first();

        $entries = [
            // [days ago, hour, user, action, description]
            [1, 8, $frontdesk, 'login', 'Signed in to the system'],
            [1, 8, $frontdesk, 'dtr.clock-in', '558675 Andrian Christian Hernadez clocked in'],
            [1, 9, $frontdesk, 'bay.allocate', 'B3 allocated - SES allocated for Miguel Delacruz'],
            [1, 12, $frontdesk, 'session.extend', 'SES extended by 20 min'],
            [1, 14, $frontdesk, 'checkout', 'SES settled for RC receipt - cash 450.00'],
            [1, 17, $frontdesk, 'dtr.clock-out', '835242 Charlie Menedez clocked out after 545 min'],
            [1, 22, $frontdesk, 'logout', 'Signed out of the system'],
            [2, 9, $admin, 'login', 'Signed in to the system'],
            [2, 9, $admin, 'settings.update', 'Facility settings updated: expiring_soon_minutes'],
            [2, 10, $admin, 'bay.maintenance', 'B12 marked as out of order'],
            [2, 16, $frontdesk, 'bay.queue', 'Ryan Bautista queued for B5'],
            [2, 16, $frontdesk, 'bay.queue.cancel', 'Queue entry for B5 cancelled'],
            [3, 8, $frontdesk, 'login', 'Signed in to the system'],
            [3, 11, $frontdesk, 'checkout', 'SES settled for RC receipt - gcash 620.00'],
            [4, 9, $admin, 'login', 'Signed in to the system'],
            [4, 15, $frontdesk, 'checkout', 'SES settled for RC receipt - maya 480.00'],
            [5, 8, $frontdesk, 'login', 'Signed in to the system'],
            [5, 13, $frontdesk, 'session.extend', 'SES extended by 15 min'],
            [6, 10, $admin, 'login', 'Signed in to the system'],
            [7, 8, $frontdesk, 'login', 'Signed in to the system'],
            [7, 19, $frontdesk, 'bay.maintenance', 'B12 marked as back in service'],
        ];

        foreach ($entries as [$daysAgo, $hour, $user, $action, $description]) {
            ActivityLog::query()->create([
                'user_id' => $user?->id,
                'action' => $action,
                'description' => $description,
                'ip_address' => '127.0.0.1',
                'created_at' => now()->subDays($daysAgo)->setTime($hour, 12),
            ]);
        }
    }
}
