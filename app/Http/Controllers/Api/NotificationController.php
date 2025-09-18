<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
   
    public function index(Request $request)
    {
        try {
            $query = Notification::where('user_id', auth()->id())
                                ->with(['related']);
            
            // Apply filters
            if ($request->has('type') && $request->type !== 'all') {
                $query->where('type', $request->type);
            }
            
            if ($request->has('read_status')) {
                if ($request->read_status === 'unread') {
                    $query->unread();
                } elseif ($request->read_status === 'read') {
                    $query->read();
                }
            }
            
            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);
            
            // Pagination
            $perPage = $request->get('per_page', 20);
            $notifications = $query->paginate($perPage);
            
            // Transform the data
            $notifications->getCollection()->transform(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'data' => $notification->data,
                    'is_read' => $notification->is_read,
                    'read_at' => $notification->read_at ? $notification->read_at->diffForHumans() : null,
                    'created_at' => $notification->created_at->diffForHumans(),
                    'related_type' => $notification->related_type,
                    'related_id' => $notification->related_id
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $notifications,
                'unread_count' => auth()->user()->unread_notifications_count
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function adminNotifications(Request $request)
    {
        try {
            // Only admins can access this
            if (!auth()->user()->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }

            $query = Notification::with(['user', 'related'])
                                ->whereHas('user', function ($q) {
                                    $q->where('role', 'admin');
                                });
            
            // Apply filters
            if ($request->has('type') && $request->type !== 'all') {
                $query->where('type', $request->type);
            }
            
            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);
            
            // Pagination
            $perPage = $request->get('per_page', 20);
            $notifications = $query->paginate($perPage);
            
            // Transform the data
            $notifications->getCollection()->transform(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'data' => $notification->data,
                    'is_read' => $notification->is_read,
                    'user' => [
                        'id' => $notification->user->id,
                        'name' => $notification->user->name
                    ],
                    'created_at' => $notification->created_at->diffForHumans(),
                    'formatted_date' => $notification->created_at->format('M j, Y g:i A')
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $notifications
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch admin notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function markAsRead(Request $request)
    {
        $request->validate([
            'notification_ids' => 'required|array',
            'notification_ids.*' => 'exists:notifications,id'
        ]);

        try {
            $notifications = Notification::where('user_id', auth()->id())
                                       ->whereIn('id', $request->notification_ids)
                                       ->get();

            foreach ($notifications as $notification) {
                $notification->markAsRead();
            }

            return response()->json([
                'success' => true,
                'message' => 'Notifications marked as read',
                'marked_count' => $notifications->count(),
                'unread_count' => auth()->user()->unread_notifications_count
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notifications as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function markAsUnread(Request $request)
    {
        $request->validate([
            'notification_ids' => 'required|array',
            'notification_ids.*' => 'exists:notifications,id'
        ]);

        try {
            $notifications = Notification::where('user_id', auth()->id())
                                       ->whereIn('id', $request->notification_ids)
                                       ->get();

            foreach ($notifications as $notification) {
                $notification->markAsUnread();
            }

            return response()->json([
                'success' => true,
                'message' => 'Notifications marked as unread',
                'marked_count' => $notifications->count(),
                'unread_count' => auth()->user()->unread_notifications_count
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark notifications as unread',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function markAllAsRead()
    {
        try {
            $updated = Notification::where('user_id', auth()->id())
                                 ->whereNull('read_at')
                                 ->update(['read_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read',
                'marked_count' => $updated,
                'unread_count' => 0
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function delete(Request $request)
    {
        $request->validate([
            'notification_ids' => 'required|array',
            'notification_ids.*' => 'exists:notifications,id'
        ]);

        try {
            $deleted = Notification::where('user_id', auth()->id())
                                 ->whereIn('id', $request->notification_ids)
                                 ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notifications deleted successfully',
                'deleted_count' => $deleted,
                'unread_count' => auth()->user()->unread_notifications_count
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notifications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUnreadCount()
    {
        try {
            return response()->json([
                'success' => true,
                'unread_count' => auth()->user()->unread_notifications_count
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get unread count',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}