<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
  
   public function dashboard(Request $request)
{
    try {
        $stats = $this->getDashboardStats();
        $recentTasks = $this->getRecentTasks();
        $recentActivity = $this->getRecentActivity();

        return response()->json([
            'success' => true,
            'user' => $request->user(), // 👈 Add this
            'data' => [
                'stats' => $stats,
                'recent_tasks' => $recentTasks,
                'recent_activity' => $recentActivity
            ]
        ]);
    } catch (\Exception $e) {
        \Log::error('Dashboard error: '.$e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'status' => 500,
            'message' => 'Failed to fetch dashboard data',
            'error'   => $e->getMessage()
        ], 500);
    }
}

    public function analytics()
    {
        try {
            $tasksByStatus = $this->getTasksByStatus();
            $tasksByUser = $this->getTasksByUser();
            $completionRates = $this->getCompletionRates();
            $overdueAnalysis = $this->getOverdueAnalysis();
            $monthlyProgress = $this->getMonthlyProgress();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'tasks_by_status' => $tasksByStatus,
                    'tasks_by_user' => $tasksByUser,
                    'completion_rates' => $completionRates,
                    'overdue_analysis' => $overdueAnalysis,
                    'monthly_progress' => $monthlyProgress
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getDashboardStats()
    {
        $totalTasks = Task::count();
        $totalMembers = User::where('role', '!=', 'admin')->count();
        $completedTasks = Task::where('status', Task::STATUS_COMPLETED)->count();
        $overdueTasks = Task::overdue()->count();
        
        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;
        
        return [
            'total_tasks' => $totalTasks,
            'total_members' => $totalMembers,
            'completion_rate' => $completionRate,
            'overdue_tasks' => $overdueTasks,
            'active_tasks' => Task::whereNotIn('status', [Task::STATUS_COMPLETED])->count(),
            'new_tasks' => Task::where('status', Task::STATUS_NEW)->count()
        ];
    }

    private function getRecentTasks($limit = 5)
    {
        return Task::with(['creator', 'assignees'])
                   ->orderBy('created_at', 'desc')
                   ->limit($limit)
                   ->get()
                   ->map(function ($task) {
                       return [
                           'id' => $task->id,
                           'title' => $task->title,
                           'status' => $task->status,
                           'created_by' => $task->creator->name,
                           'assignees' => $task->assignees->pluck('name')->toArray(),
                           'due_date' => $task->due_date ? $task->due_date->format('M j, Y') : null,
                           'is_overdue' => $task->is_overdue,
                           'created_at' => $task->created_at->diffForHumans()
                       ];
                   });
    }

    private function getRecentActivity($limit = 10)
    {
        $activities = collect();
        
        // Recent task submissions
        $recentTasks = Task::with('creator')
                          ->orderBy('created_at', 'desc')
                          ->limit(5)
                          ->get();
        
        foreach ($recentTasks as $task) {
            $activities->push([
                'type' => 'task_created',
                'title' => 'New Task Submitted: "' . $task->title . '"',
                'description' => 'Submitted by ' . $task->creator->name . '. Status: ' . $task->status . '.',
                'timestamp' => $task->created_at,
                'time_ago' => $task->created_at->diffForHumans()
            ]);
        }
        
        // Recent task updates
        $recentUpdates = Task::where('updated_at', '>', Carbon::now()->subHours(24))
                           ->with(['creator', 'assignees'])
                           ->orderBy('updated_at', 'desc')
                           ->limit(5)
                           ->get();
        
        foreach ($recentUpdates as $task) {
            if ($task->created_at->diffInMinutes($task->updated_at) > 5) {
                $activities->push([
                    'type' => 'task_updated',
                    'title' => 'Task Status Updated: "#' . $task->id . '"',
                    'description' => 'Task "' . $task->title . '" status changed to "' . $task->status . '".',
                    'timestamp' => $task->updated_at,
                    'time_ago' => $task->updated_at->diffForHumans()
                ]);
            }
        }
        
        // Recent user registrations
        $recentUsers = User::where('created_at', '>', Carbon::now()->subDays(7))
                          ->orderBy('created_at', 'desc')
                          ->limit(3)
                          ->get();
        
        foreach ($recentUsers as $user) {
            $activities->push([
                'type' => 'user_registered',
                'title' => 'User Registered: ' . $user->name,
                'description' => $user->name . ' has joined the team as a ' . ucfirst($user->role) . '.',
                'timestamp' => $user->created_at,
                'time_ago' => $user->created_at->diffForHumans()
            ]);
        }
        
        return $activities->sortByDesc('timestamp')->take($limit)->values();
    }

    private function getTasksByStatus()
    {
        return Task::select('status', DB::raw('count(*) as count'))
                   ->groupBy('status')
                   ->get()
                   ->mapWithKeys(function ($item) {
                       return [$item->status => $item->count];
                   });
    }

    private function getTasksByUser()
    {
        return User::with(['assignedTasks'])
                   ->where('role', '!=', 'admin')
                   ->get()
                   ->map(function ($user) {
                       return [
                           'name' => $user->name,
                           'total_tasks' => $user->assignedTasks->count(),
                           'active_tasks' => $user->active_tasks_count,
                           'completed_tasks' => $user->completed_tasks_count,
                           'status' => $user->getMemberStatus()
                       ];
                   });
    }

    private function getCompletionRates()
    {
        $users = User::with(['assignedTasks'])
                    ->where('role', '!=', 'admin')
                    ->get();
        
        return $users->map(function ($user) {
            $totalTasks = $user->assignedTasks->count();
            $completedTasks = $user->assignedTasks->where('status', Task::STATUS_COMPLETED)->count();
            
            return [
                'user' => $user->name,
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0
            ];
        });
    }

    private function getOverdueAnalysis()
    {
        $overdueTasks = Task::overdue()->with(['assignees'])->get();
        
        return [
            'total_overdue' => $overdueTasks->count(),
            'overdue_by_user' => $overdueTasks->flatMap(function ($task) {
                return $task->assignees->pluck('name');
            })->countBy()->toArray(),
            'average_overdue_days' => $overdueTasks->avg(function ($task) {
                return $task->due_date ? $task->due_date->diffInDays(now()) : 0;
            })
        ];
    }

    private function getMonthlyProgress()
    {
        $last6Months = collect();
        
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();
            
            $created = Task::whereBetween('created_at', [$monthStart, $monthEnd])->count();
            $completed = Task::where('status', Task::STATUS_COMPLETED)
                           ->whereBetween('updated_at', [$monthStart, $monthEnd])
                           ->count();
            
            $last6Months->push([
                'month' => $month->format('M Y'),
                'created' => $created,
                'completed' => $completed
            ]);
        }
        
        return $last6Months;
    }
}