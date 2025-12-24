<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Converts old task status values to new unified status system:
     * - 'new' => 'todo'
     * - 'working' => 'in_progress'
     * - 'question' => 'on_hold' (old "Blocked" becomes "On Hold")
     * - 'on_hold' => 'backlog' (old "On Hold" was displayed as "Backlog" in Kanban)
     * - 'in_review' => 'in_review' (unchanged)
     * - 'done' => 'done' (unchanged)
     * - 'canceled' => removed (not in new system)
     */
    public function up(): void
    {
        // Important: Order matters! We need to handle 'on_hold' before 'question'
        // because we're changing 'question' to 'on_hold'
        
        // First, convert 'on_hold' to 'backlog' (old on_hold was displayed as Backlog)
        DB::table('tasks')
            ->where('status', 'on_hold')
            ->update(['status' => 'backlog']);
        
        // Convert 'new' to 'todo'
        DB::table('tasks')
            ->where('status', 'new')
            ->update(['status' => 'todo']);
        
        // Convert 'working' to 'in_progress'
        DB::table('tasks')
            ->where('status', 'working')
            ->update(['status' => 'in_progress']);
        
        // Convert 'question' to 'on_hold' (Blocked becomes On Hold)
        DB::table('tasks')
            ->where('status', 'question')
            ->update(['status' => 'on_hold']);
        
        // Convert 'canceled' to 'backlog' (move canceled tasks to backlog)
        DB::table('tasks')
            ->where('status', 'canceled')
            ->update(['status' => 'backlog']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert back to old status values
        
        // First, convert 'on_hold' back to 'question'
        DB::table('tasks')
            ->where('status', 'on_hold')
            ->update(['status' => 'question']);
        
        // Convert 'backlog' back to 'on_hold'
        DB::table('tasks')
            ->where('status', 'backlog')
            ->update(['status' => 'on_hold']);
        
        // Convert 'todo' back to 'new'
        DB::table('tasks')
            ->where('status', 'todo')
            ->update(['status' => 'new']);
        
        // Convert 'in_progress' back to 'working'
        DB::table('tasks')
            ->where('status', 'in_progress')
            ->update(['status' => 'working']);
    }
};

