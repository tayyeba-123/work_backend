<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Task;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        try {
            $users = User::with(['createdTasks', 'assignedTasks'])->get();
            
            $formattedUsers = $users->map(function ($user) {
                // Get active tasks (not completed)
                $activeTasks = Task::whereHas('assignees', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                })->whereNotIn('status', ['Completed'])->count();
                
                $completedTasks = Task::whereHas('assignees', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                })->where('status', 'Completed')->count();
                
                // Check if user is a pair programmer on any active tasks
                $pairTasks = Task::where('pair_programmer_id', $user->id)
                    ->whereNotIn('status', ['Completed'])
                    ->with(['assignees'])
                    ->get();
                
                $pairedWith = null;
                if ($pairTasks->count() > 0) {
                    // Get the names of people this user is paired with
                    $pairedNames = [];
                    foreach ($pairTasks as $task) {
                        foreach ($task->assignees as $assignee) {
                            if ($assignee->id !== $user->id) {
                                $pairedNames[] = $assignee->name;
                            }
                        }
                    }
                    $pairedWith = implode(', ', array_unique($pairedNames));
                }
                
                // Also check if user is assigned to tasks where they're not the pair programmer
                $assignedPairTasks = Task::whereHas('assignees', function($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->whereNotNull('pair_programmer_id')
                ->where('pair_programmer_id', '!=', $user->id)
                ->whereNotIn('status', ['Completed'])
                ->with(['pairProgrammer'])
                ->get();
                
                if ($assignedPairTasks->count() > 0 && !$pairedWith) {
                    $pairProgrammers = $assignedPairTasks->pluck('pairProgrammer.name')->unique()->toArray();
                    $pairedWith = implode(', ', $pairProgrammers);
                }
                
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ?? 'user',
                    'department' => $user->department,
                    'active_tasks_count' => $activeTasks,
                    'completed_tasks_count' => $completedTasks,
                    'pair_tasks_count' => $pairTasks->count() + $assignedPairTasks->count(),
                    'paired_with' => $pairedWith,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $formattedUsers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch users',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
  
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(User::getRoles())],
            'department' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20'
        ]);

        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role,
                'department' => $request->department,
                'phone' => $request->phone,
                'status' => User::STATUS_ACTIVE
            ]);

            // Send notification to all admins about new user
            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                $this->notificationService->newUserRegistered($user, $admin);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => $this->formatUserResponse($user)
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(User $user)
    {
        try {
            $user->load(['assignedTasks', 'createdTasks']);
            
            return response()->json([
                'success' => true,
                'data' => $this->formatUserResponse($user, true)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id)
            ],
            'role' => ['sometimes', 'required', Rule::in(User::getRoles())],
            'status' => ['sometimes', 'required', Rule::in(User::getStatuses())],
            'department' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8'
        ]);

        try {
            DB::beginTransaction();

            $updateData = $request->only([
                'name', 'email', 'role', 'status', 'department', 'phone'
            ]);

            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => $this->formatUserResponse($user)
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(User $user)
    {
        try {
            // Prevent deletion of admin users
            if ($user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete admin users'
                ], 403);
            }

            // Prevent self-deletion
            if ($user->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot delete your own account'
                ], 403);
            }

            // Check if user has active tasks
            $activeTasks = $user->assignedTasks()
                              ->whereNotIn('status', ['Completed'])
                              ->count();

            if ($activeTasks > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete user with active tasks. Please reassign or complete their tasks first.',
                    'active_tasks' => $activeTasks
                ], 400);
            }

            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function profile()
    {
        try {
            $user = auth()->user();
            $user->load(['assignedTasks', 'createdTasks']);
            
            return response()->json([
                'success' => true,
                'data' => $this->formatUserResponse($user, true)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id)
            ],
            'phone' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:255',
            'current_password' => 'required_with:password|string',
            'password' => 'nullable|string|min:8|confirmed'
        ]);

        try {
            DB::beginTransaction();

            // Verify current password if trying to change password
            if ($request->filled('password')) {
                if (!Hash::check($request->current_password, $user->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Current password is incorrect'
                    ], 400);
                }
            }

            $updateData = $request->only(['name', 'email', 'phone', 'department']);

            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $this->formatUserResponse($user)
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getTeamMembers()
    {
        try {
            $members = User::where('role', '!=', 'admin')
                          ->where('status', User::STATUS_ACTIVE)
                          ->orderBy('name')
                          ->get(['id', 'name', 'role', 'department']);
            
            return response()->json([
                'success' => true,
                'data' => $members
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch team members',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function formatUserResponse($user, $includeDetails = false)
    {
        $response = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
            'department' => $user->department,
            'phone' => $user->phone,
            'initials' => $user->initials,
            'member_status' => $user->getMemberStatus(),
            'active_tasks_count' => $user->active_tasks_count,
            'completed_tasks_count' => $user->completed_tasks_count,
            'total_tasks_count' => $user->assignedTasks->count(),
            'unread_notifications_count' => $user->unread_notifications_count,
            'created_at' => $user->created_at->format('M j, Y'),
            'updated_at' => $user->updated_at->diffForHumans()
        ];

        if ($includeDetails) {
            $response['assigned_tasks'] = $user->assignedTasks->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status,
                    'due_date' => $task->due_date ? $task->due_date->format('M j, Y') : null,
                    'is_overdue' => $task->is_overdue
                ];
            });

            if ($user->role === 'admin' || $user->id === auth()->id()) {
                $response['created_tasks'] = $user->createdTasks->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'status' => $task->status,
                        'assignees_count' => $task->assignees->count()
                    ];
                });
            }
        }

        return $response;
    }
    
}