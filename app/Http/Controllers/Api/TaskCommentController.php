<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\Request;

class TaskCommentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Task $task)
    {
        try {
            $comments = $task->comments()
                           ->with('user:id,name')
                           ->orderBy('created_at', 'desc')
                           ->get();

            $comments = $comments->map(function ($comment) {
                return [
                    'id' => $comment->id,
                    'comment' => $comment->comment,
                    'user' => [
                        'id' => $comment->user->id,
                        'name' => $comment->user->name,
                        'initials' => $comment->user->initials
                    ],
                    'created_at' => $comment->created_at->diffForHumans(),
                    'formatted_date' => $comment->created_at->format('M j, Y g:i A'),
                    'can_edit' => auth()->id() === $comment->user_id || auth()->user()->isAdmin()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $comments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch comments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request, Task $task)
    {
        $request->validate([
            'comment' => 'required|string|max:1000'
        ]);

        try {
            $comment = TaskComment::create([
                'task_id' => $task->id,
                'user_id' => auth()->id(),
                'comment' => $request->comment
            ]);

            $comment->load('user:id,name');

            return response()->json([
                'success' => true,
                'message' => 'Comment added successfully',
                'data' => [
                    'id' => $comment->id,
                    'comment' => $comment->comment,
                    'user' => [
                        'id' => $comment->user->id,
                        'name' => $comment->user->name,
                        'initials' => $comment->user->initials
                    ],
                    'created_at' => $comment->created_at->diffForHumans(),
                    'formatted_date' => $comment->created_at->format('M j, Y g:i A'),
                    'can_edit' => true
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add comment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Task $task, TaskComment $comment)
    {
        $request->validate([
            'comment' => 'required|string|max:1000'
        ]);

        // Check if user can edit this comment
        if (auth()->id() !== $comment->user_id && !auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only edit your own comments'
            ], 403);
        }

        try {
            $comment->update([
                'comment' => $request->comment
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Comment updated successfully',
                'data' => [
                    'id' => $comment->id,
                    'comment' => $comment->comment,
                    'updated_at' => $comment->updated_at->diffForHumans()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update comment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Task $task, TaskComment $comment)
    {
        // Check if user can delete this comment
        if (auth()->id() !== $comment->user_id && !auth()->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only delete your own comments'
            ], 403);
        }

        try {
            $comment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete comment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}