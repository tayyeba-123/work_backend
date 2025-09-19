<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'status',
        'due_date',
        'created_by',
        'priority',
        'time_estimate',        // Added
        'pair_programmer_id'    // Added - this is the key missing field
    ];

    protected $casts = [
        'due_date' => 'date',
        'time_estimate' => 'decimal:2'  // Added
    ];

    const STATUS_NEW = 'New';
    const STATUS_OPEN = 'Open';
    const STATUS_IN_PROGRESS = 'In Progress';
    const STATUS_COMPLETED = 'Completed';

    public static function getStatuses()
    {
        return [
            self::STATUS_NEW,
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
        ];
    }

    // Relationships
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class, 'task_assignees', 'task_id', 'user_id');
    }

    // Added this relationship - this is what was missing!
    public function pairProgrammer()
    {
        return $this->belongsTo(User::class, 'pair_programmer_id');
    }

    public function comments()
    {
        return $this->hasMany(TaskComment::class);
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
                    ->whereNotIn('status', [self::STATUS_COMPLETED]);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->whereHas('assignees', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });
    }

    // Added scope for pair programming tasks
    public function scopeForPairProgrammer($query, $userId)
    {
        return $query->where('pair_programmer_id', $userId);
    }

    // Accessors
    public function getIsOverdueAttribute()
    {
        return $this->due_date && 
               $this->due_date->isPast() && 
               $this->status !== self::STATUS_COMPLETED;
    }

    public function getAssigneeNamesAttribute()
    {
        return $this->assignees->pluck('name')->toArray();
    }

    // Added accessor for pair programmer name
    public function getPairProgrammerNameAttribute()
    {
        return $this->pairProgrammer ? $this->pairProgrammer->name : null;
    }
}