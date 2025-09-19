<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Task::query();
            
            // Load all relationships including pair programmer
            $query->with(['creator', 'assignees', 'pairProgrammer']);
            
            // Apply filters
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }
            
            if ($request->has('assignee') && $request->assignee !== 'all') {
                $query->whereHas('assignees', function($q) use ($request) {
                    $q->where('user_id', $request->assignee);
                });
            }
            
            if ($request->has('search') && !empty($request->search)) {
                $query->where(function($q) use ($request) {
                    $q->where('title', 'like', '%' . $request->search . '%')
                      ->orWhere('description', 'like', '%' . $request->search . '%');
                });
            }
            
            $tasks = $query->orderBy('created_at', 'desc')->get();
            
            $formattedTasks = $tasks->map(function ($task) {
                $taskData = [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'due_date' => $task->due_date ? $task->due_date->format('Y-m-d') : null,
                    'time_estimate' => $task->time_estimate,
                    'pair_programmer_id' => $task->pair_programmer_id,
                    'created_at' => $task->created_at,
                    'updated_at' => $task->updated_at,
                    'is_overdue' => $task->due_date && $task->due_date->isPast() && $task->status !== 'Completed'
                ];
                
                // Add creator info
                if ($task->creator) {
                    $taskData['created_by'] = $task->creator->name;
                }
                
                // Add assignees
                $taskData['assignees'] = $task->assignees->map(function($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email
                    ];
                });
                
                // Add pair programmer info
                if ($task->pairProgrammer) {
                    $taskData['pair_programmer'] = [
                        'id' => $task->pairProgrammer->id,
                        'name' => $task->pairProgrammer->name,
                        'email' => $task->pairProgrammer->email
                    ];
                } else {
                    $taskData['pair_programmer'] = null;
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
                'priority' => 'nullable|in:Low,Medium,High,Critical',
                'time_estimate' => 'nullable|numeric|min:0.5',
                'assignees' => 'nullable|array',
                'assignees.*' => 'integer|exists:users,id',
                'pair_programmer_id' => 'nullable|integer|exists:users,id'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Create task
            $taskData = [
                'title' => $request->title,
                'description' => $request->description,
                'status' => 'New',
                'priority' => $request->priority ?? 'Medium',
                'due_date' => $request->due_date,
                'time_estimate' => $request->time_estimate,
                'pair_programmer_id' => $request->pair_programmer_id, // This is the key field!
                'created_by' => auth()->id()
            ];
            
            $task = Task::create($taskData);
            
            // Attach assignees if provided
            if ($request->has('assignees') && is_array($request->assignees)) {
                $task->assignees()->attach($request->assignees);
            }
            
            // Load relationships for response
            $task->load(['creator', 'assignees', 'pairProgrammer']);
            
            return response()->json([
                'success' => true,
                'message' => 'Task created successfully',
                'data' => $task
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
            $task = Task::with(['creator', 'assignees', 'pairProgrammer'])->findOrFail($id);
            
            $taskData = [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_date' => $task->due_date ? $task->due_date->format('Y-m-d') : null,
                'time_estimate' => $task->time_estimate,
                'pair_programmer_id' => $task->pair_programmer_id,
                'created_at' => $task->created_at,
                'updated_at' => $task->updated_at
            ];
            
            // Add assignees
            $taskData['assignees'] = $task->assignees->map(function($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ];
            });
            
            // Add pair programmer
            if ($task->pairProgrammer) {
                $taskData['pair_programmer'] = [
                    'id' => $task->pairProgrammer->id,
                    'name' => $task->pairProgrammer->name,
                    'email' => $task->pairProgrammer->email
                ];
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
                'status' => 'sometimes|required|in:New,Open,In Progress,Completed',
                'priority' => 'nullable|in:Low,Medium,High,Critical',
                'due_date' => 'nullable|date',
                'time_estimate' => 'nullable|numeric|min:0.5',
                'assignees' => 'nullable|array',
                'assignees.*' => 'integer|exists:users,id',
                'pair_programmer_id' => 'nullable|integer|exists:users,id'
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            // Update task fields
            $updateData = $request->only([
                'title', 'description', 'status', 'priority', 
                'due_date', 'time_estimate', 'pair_programmer_id'
            ]);
            
            $task->update($updateData);
            
            // Update assignees if provided
            if ($request->has('assignees')) {
                $task->assignees()->sync($request->assignees);
            }
            
            $task->load(['creator', 'assignees', 'pairProgrammer']);
            
            return response()->json([
                'success' => true,
                'message' => 'Task updated successfully',
                'data' => $task
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