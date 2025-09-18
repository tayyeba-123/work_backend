<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Task;
use App\Services\NotificationService;
use Carbon\Carbon;

class CheckOverdueTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:check-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for overdue tasks and send notifications';

    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        parent::__construct();
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for overdue tasks...');

        $overdueTasks = Task::overdue()
                           ->with(['assignees'])
                           ->get();

        if ($overdueTasks->isEmpty()) {
            $this->info('No overdue tasks found.');
            return 0;
        }

        $notificationsSent = 0;

        foreach ($overdueTasks as $task) {
            // Check if we've already sent overdue notification today
            $existingNotification = \App\Models\Notification::where('related_type', Task::class)
                                                          ->where('related_id', $task->id)
                                                          ->where('type', \App\Models\Notification::TYPE_TASK_OVERDUE)
                                                          ->whereDate('created_at', Carbon::today())
                                                          ->exists();

            if (!$existingNotification) {
                $this->notificationService->taskOverdue($task);
                $notificationsSent++;
                
                $this->info("Sent overdue notification for task: {$task->title}");
            }
        }

        $this->info("Processed {$overdueTasks->count()} overdue tasks.");
        $this->info("Sent {$notificationsSent} new notifications.");

        return 0;
    }
}