# AI Flow Queue System Documentation

## Overview

The AI Flow Queue system enables complex multi-step AI workflows that can pause for user input, handle errors gracefully, and execute tool calls in sequence. Unlike simple chat responses, flows can:

- **Break down complex requests** into step-by-step executable plans
- **Pause for user clarification** when multiple matches are found
- **Chain dependent operations** with parameter mapping between steps
- **Auto-recover from errors** with retry logic
- **Track progress** in real-time via WebSocket events

### Key Features

- **AI-Powered Planning** - GPT analyzes user requests and generates execution plans
- **Parameter Validation** - Auto-corrects common AI mistakes in parameter names
- **Multi-Match Handling** - Pauses for user selection when search returns multiple results
- **Entity Resolution** - Automatically extracts IDs from search results for dependent steps
- **Real-time Updates** - WebSocket broadcasts for step completion and user prompts
- **Queue-based Execution** - Background job processing for non-blocking workflows
- **Audit Logging** - Complete history of all flow events and step executions

---

## Architecture

### Technology Stack

| Component | Technology |
|-----------|------------|
| AI Provider | OpenAI API (GPT-4o-mini) |
| Queue System | Laravel Queue (database driver) |
| Real-time | Laravel Broadcasting + Soketi |
| Backend | Laravel 12 |
| Frontend | React 18 + Zustand |
| Storage | PostgreSQL (Supabase) |

### Flow Lifecycle

```
┌─────────────────────────────────────────────────────────────────┐
│                    USER SENDS COMPLEX REQUEST                    │
│  "Find task 'Landing page', add comment 'Done', mark as done"   │
└─────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│  FLOW PLANNING (AiFlowService::planFlow)                        │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ 1. Detect multi-step request                                ││
│  │ 2. Get available skills with full schemas                   ││
│  │ 3. Call OpenAI to generate step-by-step plan                ││
│  │ 4. Validate & normalize parameters against schemas          ││
│  │ 5. Create flow queue + steps in database                    ││
│  │ 6. Dispatch ProcessFlowStep job                             ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│  STEP EXECUTION (ProcessFlowStep Job)                           │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │ For each step:                                              ││
│  │  - tool_call: Execute skill, update context                 ││
│  │  - ai_decision: Analyze results, resolve entities           ││
│  │  - user_prompt: Pause, broadcast FlowUserInputRequired      ││
│  │  - conditional: Evaluate condition, skip/continue           ││
│  │                                                             ││
│  │ On completion: Broadcast FlowStepCompleted                  ││
│  │ On all done: Broadcast FlowCompleted with suggestions       ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
                               │
              ┌────────────────┴────────────────┐
              ▼                                 ▼
┌─────────────────────────┐      ┌─────────────────────────────────┐
│  NEEDS USER INPUT       │      │  FLOW COMPLETED                 │
│  ┌─────────────────────┐│      │  ┌─────────────────────────────┐│
│  │ FlowPromptModal     ││      │  │ Show success notification   ││
│  │ - Multiple choice   ││      │  │ Display AI suggestions:     ││
│  │ - Text input        ││      │  │ "Want to invite team?"      ││
│  │ - Confirmation      ││      │  │ "Want to create a deal?"    ││
│  └─────────────────────┘│      │  └─────────────────────────────┘│
└─────────────────────────┘      └─────────────────────────────────┘
```

---

## Database Schema

### ai_flow_queues

Main flow tracking table:

```sql
CREATE TABLE ai_flow_queues (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID NOT NULL REFERENCES companies(id),
    user_id UUID NOT NULL REFERENCES users(id),
    conversation_id UUID REFERENCES conversations(id),
    
    -- Flow metadata
    title VARCHAR(255) NOT NULL,
    original_request TEXT NOT NULL,          -- User's original message
    
    -- Status tracking
    status VARCHAR(20) DEFAULT 'pending',    -- pending, running, awaiting_user, completed, failed, cancelled
    current_step_id UUID,                    -- Currently executing step
    
    -- Context (accumulated data passed between steps)
    flow_context JSONB DEFAULT '{}'::jsonb,
    
    -- Progress tracking
    total_steps INTEGER DEFAULT 0,
    completed_steps INTEGER DEFAULT 0,
    retry_count INTEGER DEFAULT 0,
    max_retries INTEGER DEFAULT 3,
    
    -- Error handling
    last_error TEXT,
    
    -- AI tracking
    ai_run_id UUID REFERENCES ai_runs(id),
    planning_prompt TEXT,                    -- Prompt used for planning
    
    -- Timestamps
    started_at TIMESTAMPTZ,
    paused_at TIMESTAMPTZ,
    completed_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes
CREATE INDEX idx_flow_queues_user_status ON ai_flow_queues(user_id, status);
CREATE INDEX idx_flow_queues_company ON ai_flow_queues(company_id);
```

### ai_flow_steps

Individual steps within a flow:

```sql
CREATE TABLE ai_flow_steps (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    flow_id UUID NOT NULL REFERENCES ai_flow_queues(id) ON DELETE CASCADE,
    
    -- Step order
    position INTEGER NOT NULL DEFAULT 0,
    parent_step_id UUID REFERENCES ai_flow_steps(id),  -- For nested/branched flows
    
    -- Step definition
    step_type VARCHAR(30) NOT NULL,          -- tool_call, user_prompt, ai_decision, conditional
    skill_slug VARCHAR(100),                 -- Which skill to execute
    title VARCHAR(255),                      -- Human-readable step name
    description TEXT,
    
    -- Execution config
    input_params JSONB DEFAULT '{}'::jsonb,  -- Static parameters
    param_mappings JSONB DEFAULT '{}'::jsonb, -- Dynamic mappings like {{context.task_id}}
    
    -- Status
    status VARCHAR(20) DEFAULT 'pending',    -- pending, running, completed, failed, skipped, cancelled, awaiting_user
    result JSONB,                            -- Step execution result
    error_message TEXT,
    
    -- User interaction (for user_prompt type)
    prompt_type VARCHAR(20),                 -- choice, text, confirm
    prompt_message TEXT,
    prompt_options JSONB,                    -- [{value, label, data}]
    user_response JSONB,
    
    -- Conditional branching
    condition JSONB,                         -- {if: "{{path}}", eq/neq/gt/lt: value}
    on_success_goto INTEGER,                 -- Step position to jump to
    on_fail_goto INTEGER,
    
    -- AI decision config
    ai_decision_prompt TEXT,
    ai_decision_result JSONB,
    
    -- Timestamps
    started_at TIMESTAMPTZ,
    completed_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes
CREATE INDEX idx_flow_steps_flow_position ON ai_flow_steps(flow_id, position);
CREATE INDEX idx_flow_steps_status ON ai_flow_steps(status);
```

### ai_flow_logs

Audit log for all flow events:

```sql
CREATE TABLE ai_flow_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    flow_id UUID NOT NULL REFERENCES ai_flow_queues(id) ON DELETE CASCADE,
    step_id UUID REFERENCES ai_flow_steps(id),
    
    log_type VARCHAR(50) NOT NULL,           -- flow_created, step_started, step_completed, etc.
    message TEXT NOT NULL,
    metadata JSONB DEFAULT '{}'::jsonb,
    
    actor_type VARCHAR(20) DEFAULT 'system', -- system, user, ai
    actor_id UUID,
    
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Index
CREATE INDEX idx_flow_logs_flow_created ON ai_flow_logs(flow_id, created_at);
```

---

## Step Types

### tool_call

Executes a registered skill/tool:

```json
{
  "type": "tool_call",
  "skill": "create_task",
  "title": "Create landing page task",
  "params": {"title": "Create landing page", "priority": "high"},
  "mappings": {"project_id": "{{context.resolved_entities.project_id}}"}
}
```

### ai_decision

Analyzes previous step results and makes decisions:

```json
{
  "type": "ai_decision",
  "title": "Verify contact found",
  "description": "Check if single match or need user clarification"
}
```

The AI decision step:
1. Checks the previous step's result
2. If 0 matches → Requests user clarification
3. If 1 match → Extracts entity info, saves to `context.resolved_entities`
4. If 2+ matches → Presents selection dialog to user

### user_prompt

Pauses flow and asks user for input:

```json
{
  "type": "user_prompt",
  "title": "Select customer",
  "prompt_type": "choice",
  "prompt_message": "Found 3 matches. Please select one:",
  "prompt_options": [
    {"value": "1", "label": "John Doe (john@example.com)", "data": {...}},
    {"value": "2", "label": "John Smith (smith@example.com)", "data": {...}}
  ]
}
```

**Prompt Types:**
- `choice` - Multiple choice selection
- `text` - Free text input
- `confirm` - Yes/No confirmation

### conditional

Branch execution based on conditions:

```json
{
  "type": "conditional",
  "title": "Check if premium customer",
  "condition": {"if": "{{context.customer_type}}", "eq": "premium"},
  "on_success_goto": 5,
  "on_fail_goto": 7
}
```

---

## Parameter Mapping

### Syntax

Parameters can reference values from:

| Pattern | Description |
|---------|-------------|
| `{{steps.N.result.data.0.id}}` | ID from step N's first result |
| `{{context.resolved_entities.task_id}}` | Entity resolved by ai_decision |
| `{{context.created_entities.project_id}}` | Entity created by previous step |
| `{{user_input}}` | Value from user prompt response |

### Flow Context Structure

```json
{
  "original_intent": "Find task 'Landing page' and mark as done",
  "available_skills": ["search_tasks", "update_task", "create_comment"],
  
  "resolved_entities": {
    "task_id": "uuid-of-resolved-task",
    "task_title": "Create landing page",
    "project_id": "uuid-of-project"
  },
  
  "created_entities": {
    "project_id": "uuid-of-new-project",
    "task_ids": ["task-1-uuid", "task-2-uuid"]
  },
  
  "step_results": [
    {"success": true, "data": [{"id": "...", "title": "..."}]},
    {"decision": "single_match", "resolved": {...}}
  ],
  
  "suggestions": [
    {"type": "suggestion", "message": "Want to invite team members?", "action": "invite_member"}
  ]
}
```

---

## Parameter Validation

### Auto-Correction

The `validateAndNormalizeParams` method automatically corrects common AI mistakes:

```php
// Parameter name aliases
$paramAliases = [
    'title' => 'search',      // search_tasks expects 'search', not 'title'
    'query' => 'search',
    'comment' => 'content',   // create_comment expects 'content', not 'comment'
    'message' => 'content',
    'Done' => 'done',         // Status enum values must be lowercase
    'Working' => 'working',
];
```

### Validation Process

1. **Schema Check** - Verify each parameter exists in skill's input_schema
2. **Alias Mapping** - Convert known wrong names to correct ones
3. **Case Normalization** - Match enum values case-insensitively
4. **Partial Matching** - Try prefix/suffix matching for close matches
5. **Logging** - Log all corrections for debugging

---

## Backend Implementation

### Files

| File | Purpose |
|------|---------|
| `app/Services/AiFlowService.php` | Core flow planning and execution logic |
| `app/Jobs/ProcessFlowStep.php` | Queue job for step execution |
| `app/Models/AiFlowQueue.php` | Flow queue Eloquent model |
| `app/Models/AiFlowStep.php` | Flow step Eloquent model |
| `app/Models/AiFlowLog.php` | Flow log Eloquent model |
| `app/Events/FlowStepCompleted.php` | Broadcast event for step completion |
| `app/Events/FlowUserInputRequired.php` | Broadcast event for user prompts |
| `app/Events/FlowCompleted.php` | Broadcast event for flow completion |
| `app/Http/Controllers/Api/AiFlowController.php` | REST API for flows |

### AiFlowService Key Methods

```php
// Check if request needs flow planning
public function shouldCreateFlow(string $message): bool

// Plan and create a new flow
public function planFlow(
    string $userRequest,
    string $userId,
    string $companyId,
    ?string $conversationId = null
): AiFlowQueue

// Execute the next pending step
public function executeNextStep(AiFlowQueue $flow): ?AiFlowStep

// Handle user response to prompts
public function handleUserResponse(
    AiFlowQueue $flow, 
    AiFlowStep $step, 
    mixed $response
): void

// Insert a new step into the flow
public function insertStep(
    AiFlowQueue $flow, 
    int $afterPosition, 
    array $stepData
): AiFlowStep

// Delete a pending step
public function deleteStep(AiFlowQueue $flow, AiFlowStep $step): bool

// Cancel entire flow
public function cancelFlow(AiFlowQueue $flow): void
```

### Planning Prompt Structure

The planning prompt includes:

1. **Full Skill Schemas** - Exact parameter names, types, required/optional
2. **Step Type Definitions** - When to use each type
3. **Parameter Mapping Syntax** - How to reference previous results
4. **Planning Rules** - Best practices for flow structure
5. **Examples** - Complete flow examples with correct parameters

---

## API Endpoints

### Base URL: `/api/v1/ai/flows`

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/` | List user's flows |
| POST | `/` | Create new flow from request |
| GET | `/{id}` | Get flow with steps |
| POST | `/{id}/cancel` | Cancel a running flow |
| POST | `/{id}/retry` | Retry a failed flow |
| GET | `/{id}/logs` | Get flow audit logs |
| POST | `/{id}/steps/{stepId}/respond` | Submit user response |
| POST | `/{id}/steps` | Insert a new step |
| DELETE | `/{id}/steps/{stepId}` | Delete a pending step |

### Create Flow

**POST** `/api/v1/ai/flows`

```json
{
  "message": "Find task 'Landing page', add comment 'Done!', mark as complete"
}
```

**Response:**
```json
{
  "success": true,
  "flow": {
    "id": "uuid",
    "title": "Update Task Status and Comment",
    "status": "pending",
    "total_steps": 4,
    "steps": [...]
  }
}
```

### Submit User Response

**POST** `/api/v1/ai/flows/{id}/steps/{stepId}/respond`

```json
{
  "response": {
    "value": "2",
    "label": "John Smith (smith@example.com)"
  }
}
```

---

## WebSocket Events

### Channel

```
private-ai_flow.{flowId}
```

### Events

| Event | Payload | Description |
|-------|---------|-------------|
| `FlowStepCompleted` | `{flow, step, next_step}` | Step finished successfully |
| `FlowUserInputRequired` | `{flow, step, prompt_type, message, options}` | User action needed |
| `FlowCompleted` | `{flow, suggestions}` | All steps completed |
| `FlowFailed` | `{flow, error}` | Flow failed unrecoverably |

### Frontend Subscription

```typescript
// Subscribe to flow updates
Echo.private(`ai_flow.${flowId}`)
  .listen('FlowStepCompleted', (e) => {
    updateFlowProgress(e.flow, e.step);
  })
  .listen('FlowUserInputRequired', (e) => {
    openPromptModal(e.step);
  })
  .listen('FlowCompleted', (e) => {
    showCompletionNotification(e.flow, e.suggestions);
  });
```

---

## Frontend Implementation

### Files

| File | Purpose |
|------|---------|
| `src/stores/flow.ts` | Zustand store for flow state |
| `src/components/FlowManager.tsx` | WebSocket listener, flow orchestration |
| `src/components/FlowProgressCard.tsx` | Step progress visualization |
| `src/components/modals/FlowPromptModal.tsx` | User input dialogs |

### Flow Store State

```typescript
interface FlowState {
  activeFlow: AiFlow | null
  isProcessing: boolean
  
  // Actions
  setActiveFlow: (flow: AiFlow | null) => void
  updateFlowStep: (stepId: string, data: Partial<FlowStep>) => void
  submitResponse: (flowId: string, stepId: string, response: any) => Promise<void>
  cancelFlow: (flowId: string) => Promise<void>
}
```

---

## Configuration

### Environment Variables

```env
# Queue Configuration
QUEUE_CONNECTION=database    # Use 'sync' for debugging
CACHE_STORE=file             # Don't use Redis if not available

# OpenAI Configuration
OPENAI_API_KEY=sk-your-key-here
OPENAI_MODEL=gpt-4o-mini
```

### Queue Worker

The queue worker must be running to process flows:

```bash
# Development (runs with pnpm dev)
cd backend && php artisan queue:listen --tries=3

# Production
php artisan queue:work --tries=3 --timeout=90
```

### Root package.json

```json
{
  "scripts": {
    "dev": "concurrently \"turbo dev\" \"cd backend && php artisan serve\" \"cd backend && php artisan queue:listen --tries=3\" --names=apps,serve,queue --kill-others"
  }
}
```

---

## Error Handling

### Retry Logic

- Default max retries: 3
- On step failure, step is reset to pending for retry
- After max retries, flow status changes to `failed`

### Common Errors

| Error | Cause | Solution |
|-------|-------|----------|
| "search term is required" | Wrong parameter name | Validation auto-corrects this |
| "task_id is required" | Missing parameter mapping | Check param_mappings syntax |
| "Not null violation: company_id" | Missing company context | Fixed in ProjectManagementService |
| "MCP tool execution failed" | Skill execution error | Check skill implementation |

---

## Future Enhancements

### Planned Features

| Feature | Description |
|---------|-------------|
| **Parallel Steps** | Execute independent steps concurrently |
| **Sub-flows** | Nest flows within steps for complex workflows |
| **Scheduled Flows** | Trigger flows at specific times |
| **Templates** | Save and reuse common flow patterns |
| **Approval Workflows** | Require approval before certain steps |
| **Rollback** | Undo completed steps on failure |
| **Analytics** | Flow execution metrics and optimization |

### Integration Points

| System | Integration |
|--------|-------------|
| **Invoicing** | Create invoice → Send to customer flows |
| **Email** | Multi-step email campaigns |
| **Automation** | Trigger flows from webhooks |
| **Zapier** | External flow triggers |

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Flow created but not processing | Check queue worker is running |
| Steps failing with wrong params | Validation should auto-correct; check logs |
| User prompt not showing | Check WebSocket connection |
| Flow stuck in "running" | Check for failed jobs, restart worker |
| Context not passing between steps | Check param_mappings syntax |

### Debug Commands

```bash
# Check queue jobs
php artisan tinker --execute="DB::table('jobs')->count()"

# View flow status
php artisan tinker --execute="App\Models\AiFlowQueue::with('steps')->latest()->first()"

# Manually dispatch flow processing
php artisan tinker --execute="App\Jobs\ProcessFlowStep::dispatch('flow-uuid')"

# View flow logs
php artisan tinker --execute="App\Models\AiFlowLog::where('flow_id', 'uuid')->get()"
```

---

## Related Documentation

| Document | Relevance |
|----------|-----------|
| [ai-chat.md](./ai-chat.md) | SSE streaming, conversation API |
| [architecture-plan.md](./architecture-plan.md) | Database schema, AI infrastructure |
| [project-management.md](./project-management.md) | Task/Project skills used by flows |

