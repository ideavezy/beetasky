# Project Management System Documentation

## Overview

The Project Management system in Beemud9 is a comprehensive task management solution ported from beemud7, adapted for a pure React + Laravel REST API architecture with Supabase authentication and AI-first design.

### Key Features

- **Projects** - Company-scoped project containers with AI settings
- **Topics** - Collapsible sections/columns within projects for organizing tasks
- **Tasks** - Individual work items with status, priority, assignments, and time tracking
- **Comments** - Rich text comments with file attachments on tasks
- **Activity Logs** - Complete audit trail of all actions
- **Real-time Updates** - WebSocket-based live collaboration
- **Smart Import** - AI-powered document parsing to create tasks
- **Project Members & Invitations** - Team collaboration with role-based access

---

## Database Schema

### Entity Relationship Diagram

```
companies
    │
    ├── projects (1:N)
    │       ├── topics (1:N)
    │       │       └── tasks (1:N)
    │       │               ├── task_comments (1:N)
    │       │               ├── task_attachments (1:N)
    │       │               ├── task_assignments (M:N with users)
    │       │               └── task_activity_logs (1:N)
    │       │
    │       ├── project_members (M:N with users)
    │       ├── project_invitations (1:N)
    │       ├── project_notification_settings (1:N)
    │       ├── ai_task_suggestions (1:N)
    │       └── smart_import_jobs (1:N)
    │
    └── users (1:N)
```

### Core Tables

#### 1. Projects Table (Enhanced)

Location: `backend/database/migrations/2024_01_01_000004_create_projects_table.php`

```sql
CREATE TABLE projects (
    id UUID PRIMARY KEY,
    company_id UUID NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    contact_id UUID REFERENCES contacts(id) ON DELETE SET NULL,
    created_by UUID REFERENCES users(id) ON DELETE SET NULL,
    code VARCHAR(255),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status VARCHAR(50) DEFAULT 'new',
    start_date TIMESTAMP WITH TIME ZONE,
    due_date TIMESTAMP WITH TIME ZONE,
    budget DECIMAL(15,2),
    tags JSONB,
    ai_enabled BOOLEAN DEFAULT false,
    ai_settings JSONB,
    settings JSONB,
    deleted_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE,
    updated_at TIMESTAMP WITH TIME ZONE
);
```

**Key Fields:**
- `ai_enabled` - Toggle AI features for the project
- `ai_settings` - Configuration for AI behavior (e.g., auto-suggestions, summarization)
- `settings` - General project settings (notifications, visibility, etc.)
- `created_by` - User who created the project

**Status Values:** `new`, `working`, `on_hold`, `done`, `canceled`

#### 2. Topics Table

Location: `backend/database/migrations/2024_01_01_000020_create_topics_table.php`

```sql
CREATE TABLE topics (
    id UUID PRIMARY KEY,
    project_id UUID NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    company_id UUID NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    position INTEGER DEFAULT 0,
    color VARCHAR(50),
    is_locked BOOLEAN DEFAULT false,
    locked_by UUID REFERENCES users(id) ON DELETE SET NULL,
    locked_at TIMESTAMP WITH TIME ZONE,
    deleted_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE,
    updated_at TIMESTAMP WITH TIME ZONE
);
```

**Purpose:** Topics are collapsible sections within a project for grouping related tasks (e.g., "Design Phase", "Development", "Testing").

#### 3. Tasks Table (Enhanced)

Location: `backend/database/migrations/2024_01_01_000005_create_tasks_table.php`

```sql
CREATE TABLE tasks (
    id UUID PRIMARY KEY,
    company_id UUID NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    project_id UUID NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    topic_id UUID REFERENCES topics(id) ON DELETE CASCADE,
    assigned_to UUID REFERENCES users(id) ON DELETE SET NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    content TEXT,
    status VARCHAR(50) DEFAULT 'new',
    priority INTEGER DEFAULT 0,
    "order" INTEGER DEFAULT 0,
    due_date TIMESTAMP WITH TIME ZONE,
    completed BOOLEAN DEFAULT false,
    completed_by UUID REFERENCES users(id) ON DELETE SET NULL,
    completed_at TIMESTAMP WITH TIME ZONE,
    ai_generated BOOLEAN DEFAULT false,
    is_locked BOOLEAN DEFAULT false,
    locked_by UUID REFERENCES users(id) ON DELETE SET NULL,
    locked_at TIMESTAMP WITH TIME ZONE,
    deleted_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE,
    updated_at TIMESTAMP WITH TIME ZONE
);
```

**Key Fields:**
- `topic_id` - Links task to a topic (section) within the project
- `content` - Rich text content for detailed task descriptions
- `priority` - 0 = Low, 1 = Medium, 2 = High
- `completed` / `completed_by` / `completed_at` - Completion tracking
- `ai_generated` - Flag for AI-created tasks
- `is_locked` / `locked_by` / `locked_at` - Collaborative editing locks

**Status Values:** `new`, `working`, `question`, `on_hold`, `in_review`, `done`, `canceled`

#### 4. Task Comments Table

Location: `backend/database/migrations/2024_01_01_000021_create_task_comments_table.php`

```sql
CREATE TABLE task_comments (
    id UUID PRIMARY KEY,
    task_id UUID NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    company_contact_id UUID REFERENCES company_contacts(id) ON DELETE SET NULL,
    content TEXT NOT NULL,
    is_internal BOOLEAN DEFAULT false,
    deleted_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE,
    updated_at TIMESTAMP WITH TIME ZONE
);
```

**Purpose:** Comments on tasks with support for both internal users and external company contacts.

#### 5. Task Attachments Table

Location: `backend/database/migrations/2024_01_01_000022_create_task_attachments_table.php`

```sql
CREATE TABLE task_attachments (
    id UUID PRIMARY KEY,
    task_id UUID REFERENCES tasks(id) ON DELETE CASCADE,
    comment_id UUID REFERENCES task_comments(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    name VARCHAR(255) NOT NULL,
    path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100),
    size BIGINT,
    storage_provider VARCHAR(50) DEFAULT 'bunny',
    created_at TIMESTAMP WITH TIME ZONE,
    updated_at TIMESTAMP WITH TIME ZONE
);
```

**Purpose:** File attachments linked to tasks or comments, stored via Bunny CDN.

#### 6. Task Assignments Table

Location: `backend/database/migrations/2024_01_01_000023_create_task_assignments_table.php`

```sql
CREATE TABLE task_assignments (
    id UUID PRIMARY KEY,
    task_id UUID NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    assigned_by UUID REFERENCES users(id) ON DELETE SET NULL,
    assigned_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP WITH TIME ZONE,
    updated_at TIMESTAMP WITH TIME ZONE,
    UNIQUE(task_id, user_id)
);
```

**Purpose:** Many-to-many relationship for assigning multiple users to a single task.

#### 7. Topic Assignments Table

Location: `backend/database/migrations/2024_01_01_000024_create_topic_assignments_table.php`

```sql
CREATE TABLE topic_assignments (
    id UUID PRIMARY KEY,
    topic_id UUID NOT NULL REFERENCES topics(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    assigned_by UUID REFERENCES users(id) ON DELETE SET NULL,
    assigned_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP WITH TIME ZONE,
    updated_at TIMESTAMP WITH TIME ZONE,
    UNIQUE(topic_id, user_id)
);
```

**Purpose:** Assign users to entire topics for default task assignments.

#### 8. Project Members Table

Location: `backend/database/migrations/2024_01_01_000025_create_project_members_table.php`

```sql
CREATE TABLE project_members (
    id UUID PRIMARY KEY,
    project_id UUID NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role VARCHAR(50) DEFAULT 'member',
    added_by UUID REFERENCES users(id) ON DELETE SET NULL,
    added_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP WITH TIME ZONE,
    updated_at TIMESTAMP WITH TIME ZONE,
    UNIQUE(project_id, user_id)
);
```

**Roles:** `owner`, `admin`, `member`

#### 9. Project Invitations Table

Location: `backend/database/migrations/2024_01_01_000026_create_project_invitations_table.php`

```sql
CREATE TABLE project_invitations (
    id UUID PRIMARY KEY,
    project_id UUID NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    email VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'member',
    token VARCHAR(100) NOT NULL UNIQUE,
    invited_by UUID REFERENCES users(id) ON DELETE SET NULL,
    accepted_at TIMESTAMP WITH TIME ZONE,
    accepted_by UUID REFERENCES users(id) ON DELETE SET NULL,
    expires_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE,
    updated_at TIMESTAMP WITH TIME ZONE
);
```

**Purpose:** Email-based invitations for project collaboration.

#### 10. Task Activity Logs Table

Location: `backend/database/migrations/2024_01_01_000027_create_task_activity_logs_table.php`

```sql
CREATE TABLE task_activity_logs (
    id UUID PRIMARY KEY,
    project_id UUID NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    task_id UUID REFERENCES tasks(id) ON DELETE CASCADE,
    topic_id UUID REFERENCES topics(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    old_values JSONB,
    new_values JSONB,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP WITH TIME ZONE
);
```

**Actions:** `create`, `update`, `delete`, `complete`, `assign`, `comment`, `status_change`, `move`

#### 11. Task Notification Events Table

Location: `backend/database/migrations/2024_01_01_000028_create_task_notification_events_table.php`

```sql
CREATE TABLE task_notification_events (
    id UUID PRIMARY KEY,
    project_id UUID NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    task_id UUID REFERENCES tasks(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(100) NOT NULL,
    data JSONB,
    read_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE
);
```

**Purpose:** Notification queue for task-related events.

#### 12. Project Notification Settings Table

Location: `backend/database/migrations/2024_01_01_000029_create_project_notification_settings_table.php`

```sql
CREATE TABLE project_notification_settings (
    id UUID PRIMARY KEY,
    project_id UUID NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    email_enabled BOOLEAN DEFAULT true,
    push_enabled BOOLEAN DEFAULT true,
    task_assigned BOOLEAN DEFAULT true,
    task_completed BOOLEAN DEFAULT true,
    task_commented BOOLEAN DEFAULT true,
    task_due_soon BOOLEAN DEFAULT true,
    daily_digest BOOLEAN DEFAULT false,
    created_at TIMESTAMP WITH TIME ZONE,
    updated_at TIMESTAMP WITH TIME ZONE,
    UNIQUE(project_id, user_id)
);
```

**Purpose:** Per-user notification preferences for each project.

#### 13. AI Task Suggestions Table

Location: `backend/database/migrations/2024_01_01_000030_create_ai_task_suggestions_table.php`

```sql
CREATE TABLE ai_task_suggestions (
    id UUID PRIMARY KEY,
    project_id UUID NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    topic_id UUID REFERENCES topics(id) ON DELETE CASCADE,
    ai_run_id UUID REFERENCES ai_runs(id) ON DELETE SET NULL,
    suggested_title VARCHAR(255) NOT NULL,
    suggested_description TEXT,
    suggested_priority INTEGER DEFAULT 0,
    confidence_score DECIMAL(5,4),
    status VARCHAR(50) DEFAULT 'pending',
    accepted_at TIMESTAMP WITH TIME ZONE,
    rejected_at TIMESTAMP WITH TIME ZONE,
    task_id UUID REFERENCES tasks(id) ON DELETE SET NULL,
    created_at TIMESTAMP WITH TIME ZONE,
    updated_at TIMESTAMP WITH TIME ZONE
);
```

**Purpose:** AI-generated task suggestions for project planning.

#### 14. Smart Import Jobs Table

Location: `backend/database/migrations/2024_01_01_000031_create_smart_import_jobs_table.php`

```sql
CREATE TABLE smart_import_jobs (
    id UUID PRIMARY KEY,
    project_id UUID NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    ai_run_id UUID REFERENCES ai_runs(id) ON DELETE SET NULL,
    source_type VARCHAR(50) NOT NULL,
    source_content TEXT,
    source_file_path VARCHAR(500),
    status VARCHAR(50) DEFAULT 'pending',
    result JSONB,
    error_message TEXT,
    tasks_created INTEGER DEFAULT 0,
    topics_created INTEGER DEFAULT 0,
    started_at TIMESTAMP WITH TIME ZONE,
    completed_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE,
    updated_at TIMESTAMP WITH TIME ZONE
);
```

**Purpose:** Track AI-powered document parsing jobs.

---

## Backend Implementation

### Laravel Models

Location: `backend/app/Models/`

| Model | File | Purpose |
|-------|------|---------|
| `Project` | `Project.php` | Main project entity with AI settings |
| `Topic` | `Topic.php` | Collapsible sections within projects |
| `Task` | `Task.php` | Individual work items |
| `TaskComment` | `TaskComment.php` | Comments on tasks |
| `TaskAttachment` | `TaskAttachment.php` | File attachments |
| `TaskAssignment` | `TaskAssignment.php` | Task-user assignments |
| `TopicAssignment` | `TopicAssignment.php` | Topic-user assignments |
| `ProjectMember` | `ProjectMember.php` | Project membership |
| `ProjectInvitation` | `ProjectInvitation.php` | Email invitations |
| `TaskActivityLog` | `TaskActivityLog.php` | Activity audit trail |
| `TaskNotificationEvent` | `TaskNotificationEvent.php` | Notification events |
| `ProjectNotificationSetting` | `ProjectNotificationSetting.php` | Notification preferences |
| `AiTaskSuggestion` | `AiTaskSuggestion.php` | AI suggestions |
| `SmartImportJob` | `SmartImportJob.php` | Import job tracking |

### API Controllers

Location: `backend/app/Http/Controllers/Api/`

| Controller | Endpoints |
|------------|-----------|
| `ProjectController` | Projects CRUD, members, settings |
| `TopicController` | Topics CRUD, ordering, assignments |
| `TaskController` | Tasks CRUD, status, ordering, assignments |
| `TaskCommentController` | Comments with attachments |
| `TaskActivityController` | Activity logs |
| `ProjectInvitationController` | Invite, accept, resend |
| `SmartImportController` | AI document processing |
| `TaskNotificationController` | Notification preferences |

### API Routes

Location: `backend/routes/api.php`

```php
// Project Management Routes (protected by supabase.auth middleware)
Route::middleware(['supabase.auth', 'company.scope'])->prefix('v1')->group(function () {
    // Projects
    Route::apiResource('projects', ProjectController::class);
    Route::get('projects/{project}/topics', [ProjectController::class, 'getTopics']);
    Route::get('projects/{project}/members', [ProjectController::class, 'getMembers']);
    Route::post('projects/{project}/members', [ProjectController::class, 'addMember']);
    Route::delete('projects/{project}/members/{member}', [ProjectController::class, 'removeMember']);
    Route::get('projects/{project}/activity', [TaskActivityController::class, 'projectActivity']);
    Route::put('projects/{project}/settings', [ProjectController::class, 'updateSettings']);

    // Project Invitations
    Route::get('projects/{project}/invitations', [ProjectInvitationController::class, 'index']);
    Route::post('projects/{project}/invitations', [ProjectInvitationController::class, 'store']);
    Route::post('projects/{project}/invitations/{invitation}/resend', [ProjectInvitationController::class, 'resend']);
    Route::post('projects/{project}/invitations/{invitation}/cancel', [ProjectInvitationController::class, 'cancel']);

    // Topics
    Route::apiResource('topics', TopicController::class);
    Route::put('topics/reorder', [TopicController::class, 'reorder']);
    Route::post('topics/{topic}/tasks', [TaskController::class, 'store']);
    Route::post('topics/{topic}/assign', [TopicController::class, 'assignUser']);
    Route::delete('topics/{topic}/assign/{user}', [TopicController::class, 'unassignUser']);

    // Tasks
    Route::apiResource('tasks', TaskController::class);
    Route::put('tasks/reorder', [TaskController::class, 'reorder']);
    Route::get('tasks/{task}/comments', [TaskCommentController::class, 'index']);
    Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store']);
    Route::post('tasks/{task}/assign', [TaskController::class, 'assignUser']);
    Route::delete('tasks/{task}/assign/{user}', [TaskController::class, 'unassignUser']);
    Route::get('tasks/{task}/activity', [TaskActivityController::class, 'taskActivity']);
    Route::get('dashboard/tasks', [TaskController::class, 'dashboardTasks']);

    // Smart Import
    Route::post('smart-import', [SmartImportController::class, 'store']);
    Route::get('smart-import/{job}', [SmartImportController::class, 'show']);

    // Notifications
    Route::get('notifications/projects/{project}', [TaskNotificationController::class, 'getProjectSettings']);
    Route::put('notifications/projects/{project}', [TaskNotificationController::class, 'updateProjectSettings']);
});

// Public route for accepting invitations
Route::post('project-invitations/{token}/accept', [ProjectInvitationController::class, 'acceptPublic']);
```

### Laravel Events (Real-time)

Location: `backend/app/Events/`

| Event | Channel | Purpose |
|-------|---------|---------|
| `TaskCreated` | `project.{projectId}` | New task created |
| `TaskUpdated` | `project.{projectId}` | Task modified |
| `TaskDeleted` | `project.{projectId}` | Task removed |
| `TaskCommentAdded` | `project.{projectId}` | New comment |
| `TopicUpdated` | `project.{projectId}` | Topic modified |

### Broadcast Channels

Location: `backend/routes/channels.php`

```php
// Private project channel
Broadcast::channel('project.{projectId}', function (User $user, string $projectId) {
    $project = Project::find($projectId);
    return $project && $user->companies()->where('companies.id', $project->company_id)->exists();
});

// Presence channel for online users
Broadcast::channel('presence.project.{projectId}', function (User $user, string $projectId) {
    $project = Project::find($projectId);
    if ($project && $user->companies()->where('companies.id', $project->company_id)->exists()) {
        return ['id' => $user->id, 'name' => $user->full_name, 'avatar' => $user->avatar_url];
    }
    return false;
});
```

---

## Frontend Implementation

### Shared UI Components

Location: `packages/ui/src/components/`

| Component | File | Purpose |
|-----------|------|---------|
| `ProjectCard` | `ProjectCard.tsx` | Project card with progress, members, drag handle |
| `NewProjectCard` | `ProjectCard.tsx` | Create new project card |
| `TopicColumn` | `TopicColumn.tsx` | Collapsible topic with inline task input |
| `TaskItem` | `TaskItem.tsx` | Task row with inline edit, status badge |
| `TaskCard` | `TaskCard.tsx` | Task card for Kanban views |
| `TaskDetailDrawer` | `TaskDetailDrawer.tsx` | Right drawer with tabs |
| `TaskStatusBadge` | `TaskStatusBadge.tsx` | Editable status dropdown |
| `PriorityCircle` | `PriorityCircle.tsx` | Priority indicator |
| `ActivityFeed` | `ActivityFeed.tsx` | Activity timeline |
| `FilterModal` | `FilterModal.tsx` | Task filtering options |
| `AssignUserModal` | `AssignUserModal.tsx` | User assignment |
| `InviteMemberModal` | `InviteMemberModal.tsx` | Email invitations |
| `SmartImportModal` | `SmartImportModal.tsx` | AI import wizard |

### Component Exports

Location: `packages/ui/src/components/index.ts`

```typescript
// Project Management Components
export { ProjectCard, NewProjectCard } from './ProjectCard'
export { TaskCard } from './TaskCard'
export { TaskItem } from './TaskItem'
export { TaskStatusBadge, TASK_STATUSES, TASK_PRIORITIES, getStatusLabel, getStatusClassName } from './TaskStatusBadge'
export { PriorityCircle, getPriorityLabel, getPriorityValue } from './PriorityCircle'
export { TopicColumn } from './TopicColumn'
export { ActivityFeed } from './ActivityFeed'
export { TaskDetailDrawer } from './TaskDetailDrawer'
export { FilterModal, defaultFilterOptions, applyTaskFilters, canTaskBeDragged } from './FilterModal'
export { InviteMemberModal } from './InviteMemberModal'
export { AssignUserModal } from './AssignUserModal'
export { SmartImportModal } from './SmartImportModal'
```

### Portal App Pages

Location: `apps/portal/src/pages/projects/`

| Page | Route | Purpose |
|------|-------|---------|
| `ProjectsPage.tsx` | `/projects` | Projects list with filters |
| `ProjectDetailPage.tsx` | `/projects/:id` | Project view with topics and tasks |
| `ProjectSettingsPage.tsx` | `/projects/:id/settings` | Project settings and members |
| `ProjectActivityPage.tsx` | `/projects/:id/activity` | Activity history |

### Client App Pages

Location: `apps/client/src/pages/projects/`

| Page | Route | Purpose |
|------|-------|---------|
| `ClientProjectsPage.tsx` | `/projects` | Customer view of projects |
| `ClientProjectDetailPage.tsx` | `/projects/:id` | Read/comment access for clients |

### Real-time Hook

Location: `packages/hooks/src/project.ts`

```typescript
import { useEffect } from 'react'
import { getEcho } from './echo'
import { useAuthStore } from './auth'

interface ProjectRealtimeHandlers {
  onTaskCreated?: (task: any) => void
  onTaskUpdated?: (task: any) => void
  onTaskDeleted?: (taskId: string) => void
  onTopicUpdated?: (topic: any) => void
  onTaskCommentAdded?: (comment: any) => void
}

export function useProjectRealtime(projectId: string, handlers: ProjectRealtimeHandlers) {
  const { session } = useAuthStore()

  useEffect(() => {
    if (!session?.access_token || !projectId) return

    const echo = getEcho()
    const channel = echo.private(`project.${projectId}`)

    if (handlers.onTaskCreated) {
      channel.listen('TaskCreated', (e: { task: any }) => handlers.onTaskCreated?.(e.task))
    }
    // ... other listeners

    return () => {
      echo.leave(`project.${projectId}`)
    }
  }, [projectId, session?.access_token, handlers])
}
```

---

## Status Workflow

### Task Statuses

| Status | Display | Color | Description |
|--------|---------|-------|-------------|
| `new` | New | Warning (yellow) | Just created |
| `working` | Working on | Info (blue) | In progress |
| `question` | Question | Purple | Needs clarification |
| `on_hold` | On Hold | Neutral | Paused |
| `in_review` | In Review | Blue | Awaiting review |
| `done` | Done | Success (green) | Completed |
| `canceled` | Canceled | Neutral | No longer needed |

### Priority Levels

| Value | Label | Color |
|-------|-------|-------|
| 0 | Low | Success (green) |
| 1 | Medium | Warning (yellow) |
| 2 | High | Error (red) |

### Project Statuses

| Status | Display |
|--------|---------|
| `new` | New |
| `working` | Working |
| `on_hold` | On Hold |
| `done` | Done |
| `canceled` | Canceled |

---

## AI Integration Points

### Current AI Features

1. **Smart Import** - Parse documents/notes into structured tasks
   - Detects headings as topics
   - Detects bullets/checkboxes as tasks
   - Identifies priority keywords ("urgent", "high")

2. **AI Task Suggestions** - Stored in `ai_task_suggestions` table
   - Links to `ai_runs` for audit trail
   - Confidence scoring
   - Accept/reject workflow

### Future AI Chat Integration

The system is designed for AI chat integration:

1. **Context-Aware Actions** - Chat commands like "Create task in Topic X"
2. **Project Summarization** - AI summaries of activity logs
3. **Smart Notifications** - AI-prioritized notifications
4. **RAG Integration** - Tasks feed into knowledge base via pgvector

### AI Settings Schema

```json
{
  "auto_suggest_enabled": true,
  "suggestion_frequency": "daily",
  "summarization_enabled": true,
  "priority_detection": true,
  "due_date_inference": true,
  "context_window_days": 30
}
```

---

## Icons & Styling

### Icon Library

All icons use **Heroicons** (`@heroicons/react/24/outline`):

- NO emoji icons allowed
- Use vector icons for all UI elements
- Import from `@heroicons/react/24/outline`

### Common Icons Used

```typescript
import {
  // Navigation
  ChevronLeftIcon, ChevronDownIcon, ChevronUpIcon,
  // Actions
  PlusIcon, TrashIcon, EyeIcon, CogIcon,
  // Status
  CheckIcon, XMarkIcon,
  // Objects
  FolderIcon, UserIcon, UsersIcon, CalendarIcon,
  // Features
  SparklesIcon, FunnelIcon, Bars2Icon, Bars3Icon,
  // Communication
  ChatBubbleLeftRightIcon, DocumentIcon,
} from '@heroicons/react/24/outline'
```

### Styling Framework

- **DaisyUI** - Component library built on Tailwind
- **Tailwind CSS v4** - Utility-first CSS
- **Framer Motion** - Animations (optional)
- **@dnd-kit** - Drag and drop (optional)

---

## Company Scoping

All project management data is company-scoped:

1. Every table has a `company_id` foreign key
2. The `company.scope` middleware filters data automatically
3. Users can only access projects from companies they belong to

### Middleware Chain

```
supabase.auth → company.scope → controller
```

---

## File Structure Summary

```
beemud9/
├── backend/
│   ├── app/
│   │   ├── Http/Controllers/Api/
│   │   │   ├── ProjectController.php
│   │   │   ├── TopicController.php
│   │   │   ├── TaskController.php
│   │   │   ├── TaskCommentController.php
│   │   │   ├── TaskActivityController.php
│   │   │   ├── ProjectInvitationController.php
│   │   │   ├── SmartImportController.php
│   │   │   └── TaskNotificationController.php
│   │   ├── Models/
│   │   │   ├── Project.php
│   │   │   ├── Topic.php
│   │   │   ├── Task.php
│   │   │   ├── TaskComment.php
│   │   │   ├── TaskAttachment.php
│   │   │   ├── TaskAssignment.php
│   │   │   ├── TopicAssignment.php
│   │   │   ├── ProjectMember.php
│   │   │   ├── ProjectInvitation.php
│   │   │   ├── TaskActivityLog.php
│   │   │   ├── TaskNotificationEvent.php
│   │   │   ├── ProjectNotificationSetting.php
│   │   │   ├── AiTaskSuggestion.php
│   │   │   └── SmartImportJob.php
│   │   └── Events/
│   │       ├── TaskCreated.php
│   │       ├── TaskUpdated.php
│   │       ├── TaskDeleted.php
│   │       ├── TaskCommentAdded.php
│   │       └── TopicUpdated.php
│   ├── database/migrations/
│   │   ├── 2024_01_01_000020_create_topics_table.php
│   │   ├── 2024_01_01_000021_create_task_comments_table.php
│   │   ├── 2024_01_01_000022_create_task_attachments_table.php
│   │   ├── 2024_01_01_000023_create_task_assignments_table.php
│   │   ├── 2024_01_01_000024_create_topic_assignments_table.php
│   │   ├── 2024_01_01_000025_create_project_members_table.php
│   │   ├── 2024_01_01_000026_create_project_invitations_table.php
│   │   ├── 2024_01_01_000027_create_task_activity_logs_table.php
│   │   ├── 2024_01_01_000028_create_task_notification_events_table.php
│   │   ├── 2024_01_01_000029_create_project_notification_settings_table.php
│   │   ├── 2024_01_01_000030_create_ai_task_suggestions_table.php
│   │   ├── 2024_01_01_000031_create_smart_import_jobs_table.php
│   │   ├── 2024_01_01_000032_modify_projects_table_add_task_management.php
│   │   └── 2024_01_01_000033_modify_tasks_table_add_task_management.php
│   └── routes/
│       ├── api.php
│       └── channels.php
├── packages/
│   ├── ui/src/components/
│   │   ├── ProjectCard.tsx
│   │   ├── TopicColumn.tsx
│   │   ├── TaskItem.tsx
│   │   ├── TaskCard.tsx
│   │   ├── TaskDetailDrawer.tsx
│   │   ├── TaskStatusBadge.tsx
│   │   ├── PriorityCircle.tsx
│   │   ├── ActivityFeed.tsx
│   │   ├── FilterModal.tsx
│   │   ├── AssignUserModal.tsx
│   │   ├── InviteMemberModal.tsx
│   │   └── SmartImportModal.tsx
│   └── hooks/src/
│       └── project.ts
├── apps/
│   ├── portal/src/pages/projects/
│   │   ├── ProjectsPage.tsx
│   │   ├── ProjectDetailPage.tsx
│   │   ├── ProjectSettingsPage.tsx
│   │   └── ProjectActivityPage.tsx
│   └── client/src/pages/projects/
│       ├── ClientProjectsPage.tsx
│       └── ClientProjectDetailPage.tsx
└── plan/
    └── project-management.md (this file)
```

---

## Related Documentation

- [Architecture Plan](./architecture-plan.md) - Overall system architecture
- [UI Requirements](./ui-requirements.md) - Design specifications
- [Environment Setup](./environment-setup.md) - Development environment
- [Deployment Config](./deployment-config.md) - Production deployment

