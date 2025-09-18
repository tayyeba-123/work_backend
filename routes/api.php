<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserManagementController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\TaskCommentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Authentication routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('refresh', [AuthController::class, 'refresh'])->middleware('auth:sanctum');
    Route::get('user', [AuthController::class, 'user'])->middleware('auth:sanctum');
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    
    // Admin Dashboard routes
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('dashboard', [AdminDashboardController::class, 'dashboard']);
        Route::get('analytics', [AdminDashboardController::class, 'analytics']);
    });
    
    // Task Management routes
    Route::prefix('tasks')->group(function () {
        Route::get('/', [TaskController::class, 'index']);
        Route::post('/', [TaskController::class, 'store']);
        Route::get('my-tasks', [TaskController::class, 'myTasks']);
        Route::get('{task}', [TaskController::class, 'show']);
        Route::put('{task}', [TaskController::class, 'update']);
        Route::delete('{task}', [TaskController::class, 'destroy']);
        
        // Task comments (if needed later)
        Route::prefix('{task}/comments')->group(function () {
            Route::get('/', [TaskCommentController::class, 'index']);
            Route::post('/', [TaskCommentController::class, 'store']);
            Route::put('{comment}', [TaskCommentController::class, 'update']);
            Route::delete('{comment}', [TaskCommentController::class, 'destroy']);
        });
    });
    
    // User Management routes
    Route::prefix('users')->group(function () {
        // Profile routes (available to all authenticated users)
        Route::get('profile', [UserManagementController::class, 'profile']);
        Route::put('profile', [UserManagementController::class, 'updateProfile']);
        Route::get('team-members', [UserManagementController::class, 'getTeamMembers']);
        
        // Admin-only user management routes
        Route::middleware('admin')->group(function () {
            Route::get('/', [UserManagementController::class, 'index']);
            Route::post('/', [UserManagementController::class, 'store']);
            Route::get('{user}', [UserManagementController::class, 'show']);
            Route::put('{user}', [UserManagementController::class, 'update']);
            Route::delete('{user}', [UserManagementController::class, 'destroy']);
        });
    });
    
    // Notification routes
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('unread-count', [NotificationController::class, 'getUnreadCount']);
        Route::post('mark-as-read', [NotificationController::class, 'markAsRead']);
        Route::post('mark-as-unread', [NotificationController::class, 'markAsUnread']);
        Route::post('mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/', [NotificationController::class, 'delete']);
        
        // Admin notification routes
        Route::middleware('admin')->group(function () {
            Route::get('admin', [NotificationController::class, 'adminNotifications']);
        });
    });
    
    // Additional utility routes
    Route::prefix('utils')->group(function () {
        // Get system statistics (admin only)
        Route::get('system-stats', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_users' => \App\Models\User::count(),
                    'total_tasks' => \App\Models\Task::count(),
                    'active_users' => \App\Models\User::where('status', 'active')->count(),
                    'pending_tasks' => \App\Models\Task::whereNotIn('status', ['Completed'])->count(),
                    'overdue_tasks' => \App\Models\Task::overdue()->count(),
                ]
            ]);
        })->middleware('admin');
        
        // Get available task statuses
        Route::get('task-statuses', function () {
            return response()->json([
                'success' => true,
                'data' => \App\Models\Task::getStatuses()
            ]);
        });
        
        // Get available user roles
        Route::get('user-roles', function () {
            return response()->json([
                'success' => true,
                'data' => \App\Models\User::getRoles()
            ]);
        });
    });
});

// Health check route
Route::get('health', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is healthy',
        'timestamp' => now()->toDateTimeString(),
        'version' => '1.0.0'
    ]);
});

// Fallback route for undefined API endpoints
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'API endpoint not found'
    ], 404);
});