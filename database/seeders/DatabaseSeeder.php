<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Task;
use App\Models\Notification;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Admin User
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@taskflow.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'status' => 'active',
            'department' => 'Administration',
            'phone' => '+1-555-0123',
            'email_verified_at' => now(),
        ]);

        // Create Team Members
        $teamMembers = [
            [
                'name' => 'Aniqa Waseem',
                'email' => 'aniqa.waseem@taskflow.com',
                'role' => 'user',
                'department' => 'Development',
                'phone' => '+1-555-0124',
            ],
            [
                'name' => 'M.Yasir',
                'email' => 'm.yasir@taskflow.com',
                'role' => 'user',
                'department' => 'Design',
                'phone' => '+1-555-0125',
            ],
            [
                'name' => 'M.Bilal',
                'email' => 'm.bilal@taskflow.com',
                'role' => 'user',
                'department' => 'Backend Development',
                'phone' => '+1-555-0126',
            ],
            [
                'name' => 'M.Maaz',
                'email' => 'm.maaz@taskflow.com',
                'role' => 'manager',
                'department' => 'Project Management',
                'phone' => '+1-555-0127',
            ],
            [
                'name' => 'Esha Nadeem',
                'email' => 'esha.nadeem@taskflow.com',
                'role' => 'user',
                'department' => 'Frontend Development',
                'phone' => '+1-555-0128',
            ],
            [
                'name' => 'Tayyeba Arif',
                'email' => 'tayyeba.arif@taskflow.com',
                'role' => 'user',
                'department' => 'Documentation',
                'phone' => '+1-555-0129',
            ],
        ];

        $users = [];
        foreach ($teamMembers as $memberData) {
            $users[] = User::create([
                'name' => $memberData['name'],
                'email' => $memberData['email'],
                'password' => Hash::make('password123'),
                'role' => $memberData['role'],
                'status' => 'active',
                'department' => $memberData['department'],
                'phone' => $memberData['phone'],
                'email_verified_at' => now(),
            ]);
        }

        // Create Sample Tasks
        $tasks = [
            [
                'title' => 'Update authentication system',
                'description' => 'Implement new OAuth2 authentication system with improved security features and multi-factor authentication support.',
                'status' => 'In Progress',
                'priority' => 'High',
                'due_date' => Carbon::now()->addDays(7),
                'created_by' => $admin->id,
                'assignees' => [$users[0]->id, $users[1]->id], // Aniqa, Yasir
            ],
            [
                'title' => 'Design new dashboard layout',
                'description' => 'Create a modern, responsive dashboard layout with improved user experience and better data visualization.',
                'status' => 'Open',
                'priority' => 'Medium',
                'due_date' => Carbon::now()->addDays(12),
                'created_by' => $admin->id,
                'assignees' => [$users[3]->id], // Maaz
            ],
            [
                'title' => 'Database optimization',
                'description' => 'Optimize database queries and implement proper indexing to improve application performance.',
                'status' => 'Completed',
                'priority' => 'High',
                'due_date' => Carbon::now()->subDays(2),
                'created_by' => $admin->id,
                'assignees' => [$users[2]->id], // Bilal
            ],
            [
                'title' => 'Mobile app testing',
                'description' => 'Comprehensive testing of the mobile application across different devices and operating systems.',
                'status' => 'In Progress',
                'priority' => 'Medium',
                'due_date' => Carbon::now()->addDays(5),
                'created_by' => $admin->id,
                'assignees' => [$users[4]->id], // Esha
            ],
            [
                'title' => 'API documentation',
                'description' => 'Create comprehensive API documentation with examples and integration guides for external developers.',
                'status' => 'Open',
                'priority' => 'Low',
                'due_date' => Carbon::now()->addDays(15),
                'created_by' => $admin->id,
                'assignees' => [$users[5]->id], // Tayyeba
            ],
            [
                'title' => 'Website redesign proposal',
                'description' => 'Develop a comprehensive proposal for website redesign including wireframes, mockups, and technical specifications.',
                'status' => 'New',
                'priority' => 'Medium',
                'due_date' => Carbon::now()->addDays(10),
                'created_by' => $admin->id,
                'assignees' => [$users[1]->id], // Yasir
            ],
            [
                'title' => 'Security audit',
                'description' => 'Conduct a thorough security audit of the entire system to identify vulnerabilities and implement necessary security improvements.',
                'status' => 'Open',
                'priority' => 'Critical',
                'due_date' => Carbon::now()->addDays(3),
                'created_by' => $admin->id,
                'assignees' => [$users[2]->id, $users[0]->id], // Bilal, Aniqa
            ],
            [
                'title' => 'Performance monitoring setup',
                'description' => 'Implement comprehensive performance monitoring and alerting system for production environment.',
                'status' => 'New',
                'priority' => 'Medium',
                'due_date' => Carbon::now()->addDays(8),
                'created_by' => $admin->id,
                'assignees' => [$users[2]->id], // Bilal
            ],
        ];

        $createdTasks = [];
        foreach ($tasks as $taskData) {
            $assignees = $taskData['assignees'];
            unset($taskData['assignees']);

            $task = Task::create($taskData);
            $task->assignees()->attach($assignees);
            $createdTasks[] = $task;
        }

        // Create sample notifications
        $notifications = [
            [
                'user_id' => $users[0]->id, // Aniqa
                'type' => 'task_assigned',
                'title' => 'New Task Assigned: "Update authentication system"',
                'message' => 'You have been assigned to task "Update authentication system" by Admin User.',
                'data' => [
                    'task_id' => $createdTasks[0]->id,
                    'task_title' => 'Update authentication system',
                    'assigned_by' => 'Admin User',
                ],
                'related_type' => Task::class,
                'related_id' => $createdTasks[0]->id,
                'created_at' => Carbon::now()->subMinutes(30),
                'updated_at' => Carbon::now()->subMinutes(30),
            ],
            [
                'user_id' => $admin->id,
                'type' => 'task_completed',
                'title' => 'Task Completed: "Database optimization"',
                'message' => 'Task "Database optimization" has been completed by M.Bilal.',
                'data' => [
                    'task_id' => $createdTasks[2]->id,
                    'task_title' => 'Database optimization',
                    'completed_by' => ['M.Bilal'],
                ],
                'related_type' => Task::class,
                'related_id' => $createdTasks[2]->id,
                'created_at' => Carbon::now()->subHours(2),
                'updated_at' => Carbon::now()->subHours(2),
            ],
            [
                'user_id' => $admin->id,
                'type' => 'new_user',
                'title' => 'New User Registered: Tayyeba Arif',
                'message' => 'Tayyeba Arif has joined the team as a User.',
                'data' => [
                    'new_user_id' => $users[5]->id,
                    'new_user_name' => 'Tayyeba Arif',
                    'new_user_role' => 'user',
                ],
                'related_type' => User::class,
                'related_id' => $users[5]->id,
                'created_at' => Carbon::now()->subDays(1),
                'updated_at' => Carbon::now()->subDays(1),
            ],
            [
                'user_id' => $users[2]->id, // Bilal
                'type' => 'task_overdue',
                'title' => 'Task Overdue: "Security audit"',
                'message' => 'Task "Security audit" is now overdue. Due date was ' . Carbon::now()->addDays(3)->format('M j, Y') . '.',
                'data' => [
                    'task_id' => $createdTasks[6]->id,
                    'task_title' => 'Security audit',
                    'days_overdue' => 0,
                ],
                'related_type' => Task::class,
                'related_id' => $createdTasks[6]->id,
                'created_at' => Carbon::now()->subHours(6),
                'updated_at' => Carbon::now()->subHours(6),
            ],
            [
                'user_id' => $users[1]->id, // Yasir
                'type' => 'task_updated',
                'title' => 'Task Status Updated: "Update authentication system"',
                'message' => 'Task "Update authentication system" status changed from "Open" to "In Progress".',
                'data' => [
                    'task_id' => $createdTasks[0]->id,
                    'task_title' => 'Update authentication system',
                    'old_status' => 'Open',
                    'new_status' => 'In Progress',
                ],
                'related_type' => Task::class,
                'related_id' => $createdTasks[0]->id,
                'created_at' => Carbon::now()->subMinutes(45),
                'updated_at' => Carbon::now()->subMinutes(45),
            ],
        ];

        foreach ($notifications as $notificationData) {
            Notification::create($notificationData);
        }

        $this->command->info('Database seeded successfully!');
        $this->command->info('Admin Login: admin@taskflow.com / password123');
        $this->command->info('User Login: aniqa.waseem@taskflow.com / password123');
    }
}