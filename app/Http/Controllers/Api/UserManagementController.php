<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserManagementController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService = null)
    {
        // Make NotificationService optional to prevent errors if it doesn't exist
        $this->notificationService = $notificationService;
    }
   

    public function index(Request $request)
    {
        try {
            $query = User::with(['assignedTasks']);
            
            // Apply filters
            if ($request->has('role') && $request->role !== 'all') {
                $query->where('role', $request->role);
            }
            
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }
            
            // Search
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('department', 'like', "%{$search}%");
                });
            }
            
            // Exclude current admin from regular listing
            if ($request->get('exclude_admins', true)) {
                $query->where('role', '!=', 'admin');
            }
            
            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);
            
            // Pagination
            $perPage = $request->get('per_page', 15);
            $users = $query->paginate($perPage);
            
            // Transform the data
            $users->getCollection()->transform(function ($user) {
                return $this->formatUserResponse($user);
            });
            
            return response()->json([
                'success' => true,
                'data' => $users,
                'filters' => [
                    'roles' => User::getRoles(),
                    'statuses' => User::getStatuses()
                ]
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