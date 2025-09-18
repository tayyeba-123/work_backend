<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{
    // NO CONSTRUCTOR - This is the key fix!
    // Remove any constructor that calls $this->middleware()
    
    public function index(Request $request)
    {
        try {
            $query = Task::query();
            
            // Add relationships if Task model has them
            if (method_exists(Task::class, 'creator')) {
                $query->with(['creator']);
            }
            if (method_exists(Task::class, 'assignees')) {
                $query->with(['assignees']);
            }
            
            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }
            
            if ($request->has('assignee') && $request->assignee !== 'all') {
                if (method_exists(Task::class, 'assignees')) {
                    $query->whereHas('assignees', function($q) use ($request) {
                        $q->where('user_id', $request->assignee);
                    });
                }
            }
            
            if ($request->has('search') && !empty($request->search)) {
                $query->where(function($q) use ($request) {
                    $q->where('title', 'like', '%' . $request->search . '%');
                    if (method_exists(Task::class, 'description')) {
                        $q->orWhere('description', 'like', '%' . $request->search . '%');
                    }
                });
            }
            
            $tasks = $query->orderBy('created_at', 'desc')
                          ->paginate($request->get('per_page', 10));
            
            $formattedTasks = $tasks->map(function ($task) {
                $taskData = [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status ?? 'new',
                    'created_at' => $task->created_at ? $task->created_at->format('M j, Y') : null,
                    'updated_at' => $task->updated_at ? $task->updated_at->diffForHumans() : null
                ];
                
                // Add optional fields if they exist
                if (isset($task->description)) {
                    $taskData['description'] = $task->description;
                }
                if (isset($task->priority)) {
                    $taskData['priority'] = $task->priority;
                }
                if (isset($task->due_date)) {
                    $taskData['due_date'] = $task->due_date ? $task->due_date->format('Y-m-d') : null;
                }
                if (isset($task->is_overdue)) {
                    $taskData['is_overdue'] = $task->is_overdue;
                }
                
                // Add creator info if relationship exists
                if ($task->relationLoaded('creator') && $task->creator) {
                    $taskData['created_by'] = $task->creator->name;
                } else {
                    $taskData['created_by'] = 'Unknown';
                }
                
                // Add assignees if relationship exists
                if ($task->relationLoaded('assignees')) {
                    $taskData['assignees'] = $task->assignees->map(function($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email ?? ''
                        ];
                    });
                } else {
                    $taskData['assignees'] = [];
                }
                
                return $taskData;
            });
            
            return response()->json([
                'success' => true,
                'data' => $formattedTasks,
                'pagination' => [
                    'current_page' => $tasks->currentPage(),
                    'total_pages' => $tasks->lastPage(),
                    'per_page' => $tasks->perPage(),
                    'total' => $tasks->total()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tasks',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'due_date' => 'nullable|date',
                'priority' => 'nullable|string',
                'assignees' => 'nullable|array',
                'assignees.*' => 'integer'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Create task with basic required fields
            $taskData = [
                'title' => $request->title,
                'status' => 'new'
            ];
            
            // Add optional fields if provided
            if ($request->has('description')) {
                $taskData['description'] = $request->description;
            }
            if ($request->has('due_date')) {
                $taskData['due_date'] = $request->due_date;
            }
            if ($request->has('priority')) {
                $taskData['priority'] = $request->priority;
            }
            if (auth()->check()) {
                $taskData['created_by'] = auth()->id();
            }
            
            $task = Task::create($taskData);
            
            // Attach assignees if provided and relationship exists
            if ($request->has('assignees') && is_array($request->assignees) && method_exists($task, 'assignees')) {
                $task->assignees()->attach($request->assignees);
            }
            
            // Load relationships for response
            if (method_exists($task, 'creator')) {
                $task->load('creator');
            }
            if (method_exists($task, 'assignees')) {
                $task->load('assignees');
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Task created successfully',
                'data' => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status,
                    'created_at' => $task->created_at->format('M j, Y')
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create task',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function show($id)
    {
        try {
            $query = Task::where('id', $id);
            
            // Add relationships if they exist
            if (method_exists(Task::class, 'creator')) {
                $query->with('creator');
            }
            if (method_exists(Task::class, 'assignees')) {
                $query->with('assignees');
            }
            
            $task = $query->firstOrFail();
            
            $taskData = [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status,
                'created_at' => $task->created_at,
                'updated_at' => $task->updated_at
            ];
            
            // Add optional fields
            if (isset($task->description)) $taskData['description'] = $task->description;
            if (isset($task->priority)) $taskData['priority'] = $task->priority;
            if (isset($task->due_date)) $taskData['due_date'] = $task->due_date;
            if (isset($task->is_overdue)) $taskData['is_overdue'] = $task->is_overdue;
            
            // Add creator if loaded
            if ($task->relationLoaded('creator') && $task->creator) {
                $taskData['created_by'] = $task->creator->name;
            }
            
            // Add assignees if loaded
            if ($task->relationLoaded('assignees')) {
                $taskData['assignees'] = $task->assignees->map(function($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email ?? ''
                    ];
                });
            }
            
            return response()->json([
                'success' => true,
                'data' => $taskData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }
    
    public function update(Request $request, $id)
    {
        try {
            $task = Task::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'sometimes|required|string',
                'due_date' => 'nullable|date',
                'priority' => 'nullable|string',
                'assignees' => 'nullable|array',
                'assignees.*' => 'integer'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Update only provided fields
            $updateData = [];
            if ($request->has('title')) $updateData['title'] = $request->title;
            if ($request->has('description')) $updateData['description'] = $request->description;
            if ($request->has('status')) $updateData['status'] = $request->status;
            if ($request->has('due_date')) $updateData['due_date'] = $request->due_date;
            if ($request->has('priority')) $updateData['priority'] = $request->priority;
            
            if (!empty($updateData)) {
                $task->update($updateData);
            }
            
            // Update assignees if provided and relationship exists
            if ($request->has('assignees') && method_exists($task, 'assignees')) {
                $task->assignees()->sync($request->assignees);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Task updated successfully',
                'data' => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status,
                    'updated_at' => $task->updated_at->diffForHumans()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update task',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function destroy($id)
    {
        try {
            $task = Task::findOrFail($id);
            $task->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'Task deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete task',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function myTasks(Request $request)
{
    try {
        $user = auth()->user();
        
        $query = Task::query();
        
        // Filter tasks for the current user
        $query->where(function($q) use ($user) {
            $q->where('created_by', $user->id)
              ->orWhereHas('assignees', function($subQ) use ($user) {
                  $subQ->where('user_id', $user->id);
              });
        });
        
        // Add relationships if they exist
        if (method_exists(Task::class, 'creator')) {
            $query->with(['creator']);
        }
        if (method_exists(Task::class, 'assignees')) {
            $query->with(['assignees']);
        }
        
        $tasks = $query->orderBy('created_at', 'desc')->get();
        
        $formattedTasks = $tasks->map(function ($task) {
            $taskData = [
                'id' => $task->id,
                'title' => $task->title,
                'status' => $task->status ?? 'new',
                'created_at' => $task->created_at ? $task->created_at->format('M j, Y') : null,
                'updated_at' => $task->updated_at ? $task->updated_at->diffForHumans() : null
            ];
            
            // Add optional fields if they exist
            if (isset($task->description)) {
                $taskData['description'] = $task->description;
            }
            if (isset($task->priority)) {
                $taskData['priority'] = $task->priority;
            }
            if (isset($task->due_date)) {
                $taskData['due_date'] = $task->due_date ? $task->due_date->format('Y-m-d') : null;
            }
            if (isset($task->is_overdue)) {
                $taskData['is_overdue'] = $task->is_overdue;
            }
            
            // Add assignees if relationship exists
            if ($task->relationLoaded('assignees')) {
                $taskData['assignees'] = $task->assignees->map(function($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email ?? ''
                    ];
                });
            } else {
                $taskData['assignees'] = [];
            }
            
            return $taskData;
        });
        
        return response()->json([
            'success' => true,
            'data' => $formattedTasks
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch my tasks',
            'error' => $e->getMessage()
        ], 500);
    }
}
}