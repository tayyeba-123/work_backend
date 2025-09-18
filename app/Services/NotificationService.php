<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Task;
use App\Models\User;

class NotificationService
{
    /**
     * Notify user about task assignment
     */
    public function taskAssigned(Task $task, User $user)
    {
        return $this->createNotification([
            'user_id' => $user->id,
            'type' => Notification::TYPE_TASK_ASSIGNED,
            'title' => 'New Task Assigned: "' . $task->title . '"',
            'message' => 'You have been assigned to task "' . $task->title . '" by ' . $task->creator->name . '.',
            'data' => [
                'task_id' => $task->id,
                'task_title' => $task->title,
                'assigned_by' => $task->creator->name,
                'due_date' => $task->due_date ? $task->due_date->format('Y-m-d') : null
            ],
            'related_type' => Task::class,
            'related_id' => $task->id
        ]);
    }

    /**
     * Notify about task status change
     */
    public function taskStatusChanged(Task $task, string $oldStatus, string $newStatus)
    {
        $notifications = [];

        // Notify all assignees
        foreach ($task->assignees as $assignee) {
            $notifications[] = $this->createNotification([
                'user_id' => $assignee->id,
                'type' => Notification::TYPE_TASK_UPDATED,
                'title' => 'Task Status Updated: "' . $task->title . '"',
                'message' => 'Task "' . $task->title . '" status changed from "' . $oldStatus . '" to "' . $newStatus . '".',
                'data' => [
                    'task_id' => $task->id,
                    'task_title' => $task->title,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'updated_by' => auth()->user()->name ?? 'System'
                ],
                'related_type' => Task::class,
                'related_id' => $task->id
            ]);
        }

        // Notify task creator if they're not the one updating
        if ($task->creator && $task->creator->id !== auth()->id() && !$task->assignees->contains('id', $task->creator->id)) {
            $notifications[] = $this->createNotification([
                'user_id' => $task->creator->id,
                'type' => Notification::TYPE_TASK_UPDATED,
                'title' => 'Task Status Updated: "' . $task->title . '"',
                'message' => 'Your task "' . $task->title . '" status changed from "' . $oldStatus . '" to "' . $newStatus . '".',
                'data' => [
                    'task_id' => $task->id,
                    'task_title' => $task->title,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'updated_by' => auth()->user()->name ?? 'System'
                ],
                'related_type' => Task::class,
                'related_id' => $task->id
            ]);
        }

        // Notify admins if task is completed
        if ($newStatus === Task::STATUS_COMPLETED) {
            $this->taskCompleted($task);
        }

        return $notifications;
    }

    /**
     * Notify about task completion
     */
    public function taskCompleted(Task $task)
    {
        $notifications = [];
        $admins = User::where('role', 'admin')->get();

        foreach ($admins as $admin) {
            $notifications[] = $this->createNotification([
                'user_id' => $admin->id,
                'type' => Notification::TYPE_TASK_COMPLETED,
                'title' => 'Task Completed: "' . $task->title . '"',
                'message' => 'Task "' . $task->title . '" has been completed by ' . $task->assignees->pluck('name')->join(', ') . '.',
                'data' => [
                    'task_id' => $task->id,
                    'task_title' => $task->title,
                    'completed_by' => $task->assignees->pluck('name')->toArray(),
                    'completion_date' => now()->format('Y-m-d H:i:s')
                ],
                'related_type' => Task::class,
                'related_id' => $task->id
            ]);
        }

        return $notifications;
    }

    /**
     * Notify about overdue tasks
     */
    public function taskOverdue(Task $task)
    {
        $notifications = [];

        // Notify assignees
        foreach ($task->assignees as $assignee) {
            $notifications[] = $this->createNotification([
                'user_id' => $assignee->id,
                'type' => Notification::TYPE_TASK_OVERDUE,
                'title' => 'Task Overdue: "' . $task->title . '"',
                'message' => 'Task "' . $task->title . '" is now overdue. Due date was ' . $task->due_date->format('M j, Y') . '.',
                'data' => [
                    'task_id' => $task->id,
                    'task_title' => $task->title,
                    'due_date' => $task->due_date->format('Y-m-d'),
                    'days_overdue' => $task->due_date->diffInDays(now())
                ],
                'related_type' => Task::class,
                'related_id' => $task->id
            ]);
        }

        // Notify admins
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $notifications[] = $this->createNotification([
                'user_id' => $admin->id,
                'type' => Notification::TYPE_TASK_OVERDUE,
                'title' => 'Overdue Task Alert: "' . $task->title . '"',
                'message' => 'Task "' . $task->title . '" assigned to ' . $task->assignees->pluck('name')->join(', ') . ' is now overdue.',
                'data' => [
                    'task_id' => $task->id,
                    'task_title' => $task->title,
                    'assignees' => $task->assignees->pluck('name')->toArray(),
                    'due_date' => $task->due_date->format('Y-m-d'),
                    'days_overdue' => $task->due_date->diffInDays(now())
                ],
                'related_type' => Task::class,
                'related_id' => $task->id
            ]);
        }

        return $notifications;
    }

    /**
     * Notify about new user registration
     */
    public function newUserRegistered(User $newUser, User $admin)
    {
        return $this->createNotification([
            'user_id' => $admin->id,
            'type' => Notification::TYPE_NEW_USER,
            'title' => 'New User Registered: ' . $newUser->name,
            'message' => $newUser->name . ' has joined the team as a ' . ucfirst($newUser->role) . '.',
            'data' => [
                'new_user_id' => $newUser->id,
                'new_user_name' => $newUser->name,
                'new_user_role' => $newUser->role,
                'new_user_email' => $newUser->email,
                'registration_date' => $newUser->created_at->format('Y-m-d H:i:s')
            ],
            'related_type' => User::class,
            'related_id' => $newUser->id
        ]);
    }

    /**
     * Notify about user removal
     */
    public function userRemoved(User $removedUser, User $admin)
    {
        $admins = User::where('role', 'admin')->where('id', '!=', auth()->id())->get();
        $notifications = [];

        foreach ($admins as $adminUser) {
            $notifications[] = $this->createNotification([
                'user_id' => $adminUser->id,
                'type' => Notification::TYPE_USER_REMOVED,
                'title' => 'User Removed: ' . $removedUser->name,
                'message' => $removedUser->name . ' (' . ucfirst($removedUser->role) . ') has been removed from the team by ' . $admin->name . '.',
                'data' => [
                    'removed_user_name' => $removedUser->name,
                    'removed_user_role' => $removedUser->role,
                    'removed_by' => $admin->name,
                    'removal_date' => now()->format('Y-m-d H:i:s')
                ]
            ]);
        }

        return $notifications;
    }

    /**
     * Send system notification
     */
    public function systemNotification(string $title, string $message, array $userData = [], array $notificationData = [])
    {
        $notifications = [];
        $users = User::whereIn('id', $userData['user_ids'] ?? [])->get();

        if ($users->isEmpty() && isset($userData['role'])) {
            $users = User::where('role', $userData['role'])->get();
        }

        if ($users->isEmpty()) {
            $users = User::all(); // Send to all users if no specific targeting
        }

        foreach ($users as $user) {
            $notifications[] = $this->createNotification([
                'user_id' => $user->id,
                'type' => Notification::TYPE_SYSTEM,
                'title' => $title,
                'message' => $message,
                'data' => $notificationData
            ]);
        }

        return $notifications;
    }

    /**
     * Create notification record
     */
    private function createNotification(array $data)
    {
        try {
            return Notification::create($data);
        } catch (\Exception $e) {
            // Log error but don't throw to avoid breaking main functionality
            \Log::error('Failed to create notification: ' . $e->getMessage(), $data);
            return null;
        }
    }

    /**
     * Bulk create notifications
     */
    public function bulkNotify(array $notifications)
    {
        try {
            return Notification::insert($notifications);
        } catch (\Exception $e) {
            \Log::error('Failed to create bulk notifications: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get notification statistics
     */
    public function getNotificationStats(User $user)
    {
        $notifications = $user->notifications();

        return [
            'total' => $notifications->count(),
            'unread' => $notifications->unread()->count(),
            'read' => $notifications->read()->count(),
            'by_type' => $notifications->select('type', \DB::raw('count(*) as count'))
                                    ->groupBy('type')
                                    ->pluck('count', 'type')
                                    ->toArray()
        ];
    }
}