# One Page App CRM System - Architecture Plan (AI-First)

## Tech Stack Summary

| Layer | Technology |
|-------|------------|
| **Monorepo** | Turborepo + PNPM Workspaces |
| **Backend** | Laravel 12 |
| **Marketing Site** | Next.js 15 (App Router) |
| **Portal & Client** | React 18 + Vite |
| **Shared Packages** | @beemud/ui, @beemud/hooks, @beemud/types |
| **CSS Framework** | Tailwind CSS + DaisyUI which we must make Daisy work and use Daisy UI as standard |
| **Database** | Supabase (PostgreSQL + pgvector) |
| **Auth** | Supabase Auth (Email/Password, OAuth, Magic Link) |
| **Realtime Chat** | Laravel Broadcasting + Soketi (Pusher-compatible) |
| **Live Collaboration** | Supabase Realtime |
| **Storage** | Bunny CDN + Bunny Stream |
| **AI/LLM** | OpenAI API (or compatible) |
| **Embeddings** | pgvector for RAG |

---

## Related Documentation

| Document | Purpose |
|----------|---------|
| [ui-requirements.md](./ui-requirements.md) | Design philosophy, colors, typography, component guidelines |
| [design-specification.md](./design-specification.md) | Sidebar layout structure, theme config, shared components |
| [deployment-config.md](./deployment-config.md) | Deployment configurations |
| [environment-setup.md](./environment-setup.md) | Local development setup |

---

## SPA Layout Concept

The main application uses a split-panel layout with AI as the primary interface:

```
+------------------------------------------+
|              Header / Nav                |
+------------------------+-----------------+
|                        |                 |
|   AI Presentation      |   Chat Widget   |
|   Panel (Left)         |   (Right)       |
|                        |                 |
|   - Metric cards       |   - Messages    |
|   - Timeline cards     |   - AI replies  |
|   - Recommendations    |   - Commands    |
|   - Risk alerts        |   - Actions     |
|   - Notes/annotations  |                 |
|                        |                 |
+------------------------+-----------------+
```

- **Left Panel**: AI-generated presentation content (structured cards, metrics, recommendations) that updates based on chat context
- **Right Panel**: Chat widget supporting internal team, customer-facing, and AI conversations
- **AI Co-worker**: User types natural language commands, AI executes tools and updates cards

---

## Database Schema Design (Supabase/PostgreSQL)

### Design Principles

1. **TIMESTAMPTZ everywhere** - Timezone-aware timestamps for multi-tenant SaaS
2. **Explicit JSONB defaults** - Use `'{}'::jsonb` and `'[]'::jsonb`
3. **Soft deletes** - `deleted_at` on key tables for AI history access
4. **Polymorphic sender/actor** - Track who did what (user, contact, AI, system)
5. **AI-native messaging** - LLM roles, message types, AI run tracing

### Extensions

```sql
-- Enable required extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "vector";  -- pgvector for RAG
```

---

### Core Tables

> **Role & Contact Architecture**
> 
> The schema follows a flexible identity model:
> - **One global identity per human** (users table with `global_role`)
> - **Per-company roles** (company_user pivot: owner/manager/staff/agent)
> - **Per-company contact relationships** (company_contacts pivot: lead/customer/prospect/vendor)
> 
> This allows: Same person = lead of Company A, customer of Company B, and staff of Company C.

#### 1. `users` (Global login identity)
```sql
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) UNIQUE NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255),
    avatar_url VARCHAR(500),
    -- Global role: platform-level access
    -- 'admin' = Beemud platform administrators (super admin)
    -- 'user' = Regular users (company roles in company_user)
    global_role VARCHAR(20) CHECK (global_role IN ('admin', 'user')) DEFAULT 'user',
    password VARCHAR(255),
    remember_token VARCHAR(100),
    two_factor_secret TEXT,
    two_factor_recovery_codes TEXT,
    two_factor_confirmed_at TIMESTAMPTZ,
    email_verified_at TIMESTAMPTZ,
    deleted_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_global_role ON users(global_role);
CREATE INDEX idx_users_deleted ON users(deleted_at) WHERE deleted_at IS NULL;
```

#### 2. `social_accounts` (OAuth providers - Google, etc.)
```sql
CREATE TABLE social_accounts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID REFERENCES users(id) ON DELETE CASCADE NOT NULL,
    provider VARCHAR(50) NOT NULL,
    provider_user_id VARCHAR(255) NOT NULL,
    provider_email VARCHAR(255),
    access_token TEXT,
    refresh_token TEXT,
    token_expires_at TIMESTAMPTZ,
    avatar_url VARCHAR(500),
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE(provider, provider_user_id)
);

CREATE INDEX idx_social_accounts_user ON social_accounts(user_id);
CREATE INDEX idx_social_accounts_provider ON social_accounts(provider, provider_user_id);
```

#### 3. `companies` (Tenants / Client businesses)
```sql
CREATE TABLE companies (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    owner_id UUID REFERENCES users(id) ON DELETE SET NULL,
    logo_url VARCHAR(500),
    billing_status VARCHAR(20) CHECK (billing_status IN ('trial', 'active', 'suspended', 'cancelled')) DEFAULT 'trial',
    billing_cycle VARCHAR(20) CHECK (billing_cycle IN ('monthly', 'yearly')) DEFAULT 'monthly',
    settings JSONB NOT NULL DEFAULT '{
        "ai": {
            "default_agent": "crm_assistant",
            "tone": "professional",
            "language": "en",
            "blocked_actions": []
        }
    }'::jsonb,
    deleted_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_companies_slug ON companies(slug);
CREATE INDEX idx_companies_owner ON companies(owner_id);
CREATE INDEX idx_companies_deleted ON companies(deleted_at) WHERE deleted_at IS NULL;
```

#### 4. `company_user` (Company members: owners & staff)
```sql
-- Represents who works in each company
-- One user can be owner of Company A, manager of Company B, staff of Company C
CREATE TABLE company_user (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID REFERENCES companies(id) ON DELETE CASCADE NOT NULL,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE NOT NULL,
    -- Company-level roles
    role_in_company VARCHAR(20) CHECK (role_in_company IN ('owner', 'manager', 'staff', 'agent')) DEFAULT 'staff',
    permissions JSONB NOT NULL DEFAULT '{}'::jsonb,
    is_active BOOLEAN DEFAULT true,
    joined_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE(company_id, user_id)
);

CREATE INDEX idx_company_user_company ON company_user(company_id);
CREATE INDEX idx_company_user_user ON company_user(user_id);
CREATE INDEX idx_company_user_active ON company_user(company_id, is_active);
```

#### 5. `contacts` (Global contact identities)
```sql
-- Any person you interact with (can be lead, customer, vendor across companies)
-- NOT company-scoped - one row per person globally
CREATE TABLE contacts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    address JSONB,
    -- Optional link to user account (for customer portal login)
    auth_user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    -- Contact's own organization
    organization VARCHAR(255),
    job_title VARCHAR(255),
    avatar_url VARCHAR(500),
    custom_fields JSONB NOT NULL DEFAULT '{}'::jsonb,
    tags JSONB NOT NULL DEFAULT '[]'::jsonb,
    deleted_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_contacts_email ON contacts(email);
CREATE INDEX idx_contacts_auth_user ON contacts(auth_user_id);
CREATE INDEX idx_contacts_deleted ON contacts(deleted_at) WHERE deleted_at IS NULL;
-- Unique email for non-deleted contacts
CREATE UNIQUE INDEX idx_contacts_email_unique ON contacts(email) WHERE email IS NOT NULL AND deleted_at IS NULL;
```

#### 5b. `company_contacts` (Company ↔ Contact relationships)
```sql
-- The KEY table for flexibility:
-- Same contact can be: lead of Company A, customer of Company B, vendor of Company C
CREATE TABLE company_contacts (
    id BIGSERIAL PRIMARY KEY,
    company_id UUID REFERENCES companies(id) ON DELETE CASCADE NOT NULL,
    contact_id UUID REFERENCES contacts(id) ON DELETE CASCADE NOT NULL,
    -- Relationship type with this company
    relation_type VARCHAR(30) CHECK (relation_type IN ('lead', 'customer', 'prospect', 'vendor', 'partner')) DEFAULT 'lead',
    status VARCHAR(50) DEFAULT 'active',
    source VARCHAR(100),  -- e.g., 'web_form', 'import', 'manual'
    assigned_to UUID REFERENCES users(id) ON DELETE SET NULL,
    converted_at TIMESTAMPTZ,
    first_seen_at TIMESTAMPTZ DEFAULT NOW(),
    last_activity_at TIMESTAMPTZ,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    -- A contact can have one relationship type per company
    UNIQUE(company_id, contact_id, relation_type)
);

CREATE INDEX idx_company_contacts_company ON company_contacts(company_id);
CREATE INDEX idx_company_contacts_contact ON company_contacts(contact_id);
CREATE INDEX idx_company_contacts_type ON company_contacts(company_id, relation_type);
CREATE INDEX idx_company_contacts_status ON company_contacts(company_id, status);
CREATE INDEX idx_company_contacts_assigned ON company_contacts(assigned_to);
```

#### 6. `projects`
```sql
CREATE TABLE projects (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID REFERENCES companies(id) ON DELETE CASCADE NOT NULL,
    contact_id UUID REFERENCES contacts(id) ON DELETE SET NULL,
    code VARCHAR(50),  -- Short code for URLs & AI references (e.g., "PROJ-001")
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status VARCHAR(20) CHECK (status IN ('planning', 'active', 'on_hold', 'completed', 'cancelled')) DEFAULT 'planning',
    start_date DATE,
    due_date DATE,
    budget DECIMAL(12, 2),
    tags JSONB NOT NULL DEFAULT '[]'::jsonb,
    deleted_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_projects_company ON projects(company_id);
CREATE INDEX idx_projects_contact ON projects(contact_id);
CREATE UNIQUE INDEX idx_projects_company_code ON projects(company_id, code) WHERE code IS NOT NULL;
CREATE INDEX idx_projects_deleted ON projects(deleted_at) WHERE deleted_at IS NULL;
```

#### 7. `tasks`
```sql
CREATE TABLE tasks (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    project_id UUID REFERENCES projects(id) ON DELETE CASCADE,
    company_id UUID REFERENCES companies(id) ON DELETE CASCADE NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status VARCHAR(20) CHECK (status IN ('todo', 'in_progress', 'review', 'done')) DEFAULT 'todo',
    priority VARCHAR(20) CHECK (priority IN ('low', 'medium', 'high', 'urgent')) DEFAULT 'medium',
    assigned_to UUID REFERENCES users(id) ON DELETE SET NULL,
    due_date TIMESTAMPTZ,
    completed_at TIMESTAMPTZ,
    "order" INTEGER DEFAULT 0,
    tags JSONB NOT NULL DEFAULT '[]'::jsonb,
    deleted_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_tasks_project ON tasks(project_id);
CREATE INDEX idx_tasks_company ON tasks(company_id);
CREATE INDEX idx_tasks_assigned ON tasks(assigned_to);
CREATE INDEX idx_tasks_company_status_order ON tasks(company_id, status, "order");
CREATE INDEX idx_tasks_deleted ON tasks(deleted_at) WHERE deleted_at IS NULL;
```

---

### AI-Native Messaging Tables

#### 8. `conversations` (AI-enhanced)
```sql
CREATE TABLE conversations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID REFERENCES companies(id) ON DELETE CASCADE NOT NULL,
    type VARCHAR(20) CHECK (type IN ('internal', 'customer', 'ai_chat')) NOT NULL DEFAULT 'internal',
    status VARCHAR(20) CHECK (status IN ('open', 'pending', 'closed', 'archived')) DEFAULT 'open',
    channel VARCHAR(20) CHECK (channel IN ('internal', 'widget', 'email', 'whatsapp', 'other')) DEFAULT 'widget',
    contact_id UUID REFERENCES contacts(id) ON DELETE SET NULL,
    name VARCHAR(255),
    last_message_at TIMESTAMPTZ,
    last_message_preview TEXT,
    deleted_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_conversations_company ON conversations(company_id);
CREATE INDEX idx_conversations_contact ON conversations(contact_id);
CREATE INDEX idx_conversations_status ON conversations(company_id, status);
CREATE INDEX idx_conversations_last_message ON conversations(company_id, last_message_at DESC);
CREATE INDEX idx_conversations_deleted ON conversations(deleted_at) WHERE deleted_at IS NULL;
```

#### 9. `conversation_participants` (polymorphic)
```sql
CREATE TABLE conversation_participants (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    conversation_id UUID REFERENCES conversations(id) ON DELETE CASCADE NOT NULL,
    participant_type VARCHAR(20) NOT NULL 
        CHECK (participant_type IN ('user', 'contact', 'ai_agent', 'visitor')),
    participant_id UUID NOT NULL,
    last_read_at TIMESTAMPTZ,
    joined_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE(conversation_id, participant_type, participant_id)
);

CREATE INDEX idx_conv_participants_conversation ON conversation_participants(conversation_id);
CREATE INDEX idx_conv_participants_participant ON conversation_participants(participant_type, participant_id);
```

#### 10. `messages` (AI-native)
```sql
CREATE TABLE messages (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    conversation_id UUID REFERENCES conversations(id) ON DELETE CASCADE NOT NULL,
    
    -- Who sent it (polymorphic)
    sender_type VARCHAR(20) NOT NULL 
        CHECK (sender_type IN ('user', 'contact', 'ai_agent', 'system', 'tool')),
    sender_id UUID,  -- Nullable for pure system messages
    
    -- LLM role mapping
    role VARCHAR(20) NOT NULL 
        CHECK (role IN ('user', 'assistant', 'system', 'tool')) DEFAULT 'user',
    
    -- Message classification
    message_type VARCHAR(20) NOT NULL 
        CHECK (message_type IN ('chat', 'note', 'event', 'command', 'error')) DEFAULT 'chat',
    
    content TEXT NOT NULL,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,  -- Tool name, refs, UI actions
    attachments JSONB,
    read_by JSONB NOT NULL DEFAULT '[]'::jsonb,
    
    -- Threading support
    reply_to_message_id UUID REFERENCES messages(id) ON DELETE SET NULL,
    
    -- AI tracing
    ai_run_id UUID,  -- FK to ai_runs (see below)
    
    deleted_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_messages_conversation_created ON messages(conversation_id, created_at);
CREATE INDEX idx_messages_ai_run ON messages(ai_run_id);
CREATE INDEX idx_messages_sender ON messages(sender_type, sender_id);
CREATE INDEX idx_messages_deleted ON messages(deleted_at) WHERE deleted_at IS NULL;
```

---

### AI Infrastructure Tables

#### 11. `ai_agents` (bots as first-class citizens)
```sql
CREATE TABLE ai_agents (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID REFERENCES companies(id) ON DELETE CASCADE,  -- NULL for global agents
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    model VARCHAR(100) NOT NULL,  -- e.g., 'gpt-4', 'claude-3', 'custom'
    temperature NUMERIC(3,2) DEFAULT 0.3,
    system_prompt TEXT,
    settings JSONB NOT NULL DEFAULT '{}'::jsonb,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_ai_agents_company ON ai_agents(company_id);
CREATE INDEX idx_ai_agents_slug ON ai_agents(slug);
CREATE INDEX idx_ai_agents_active ON ai_agents(is_active) WHERE is_active = TRUE;
```

#### 12. `ai_runs` (log each LLM call for auditing/debugging/cost)
```sql
CREATE TABLE ai_runs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID REFERENCES companies(id) ON DELETE CASCADE,
    ai_agent_id UUID REFERENCES ai_agents(id) ON DELETE SET NULL,
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,  -- Who triggered
    conversation_id UUID REFERENCES conversations(id) ON DELETE SET NULL,
    
    -- Run classification
    run_type VARCHAR(30) NOT NULL 
        CHECK (run_type IN ('chat_reply', 'summary', 'automation', 'analysis', 'tool_call', 'embedding')),
    
    -- Token usage & cost
    input_tokens INT,
    output_tokens INT,
    cost_usd NUMERIC(10, 4),
    latency_ms INT,
    
    -- Request/Response (trimmed if needed)
    request_payload JSONB,
    response_payload JSONB,
    
    -- Status
    status VARCHAR(20) NOT NULL 
        CHECK (status IN ('success', 'error', 'timeout')) DEFAULT 'success',
    error_message TEXT,
    
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_ai_runs_company_created ON ai_runs(company_id, created_at DESC);
CREATE INDEX idx_ai_runs_conversation ON ai_runs(conversation_id);
CREATE INDEX idx_ai_runs_agent ON ai_runs(ai_agent_id);
CREATE INDEX idx_ai_runs_status ON ai_runs(status);
```

#### 13. `ai_tools` (tool definitions for function calling)
```sql
CREATE TABLE ai_tools (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    schema JSONB NOT NULL,  -- JSON Schema for arguments
    handler VARCHAR(255),   -- Backend handler class/method
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_ai_tools_active ON ai_tools(is_active) WHERE is_active = TRUE;
```

#### 14. `ai_tool_logs` (log tool executions)
```sql
CREATE TABLE ai_tool_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    ai_run_id UUID REFERENCES ai_runs(id) ON DELETE CASCADE NOT NULL,
    ai_tool_id UUID REFERENCES ai_tools(id) ON DELETE SET NULL,
    arguments JSONB,
    result JSONB,
    status VARCHAR(20) CHECK (status IN ('success', 'error')) DEFAULT 'success',
    error_message TEXT,
    latency_ms INT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_ai_tool_logs_run ON ai_tool_logs(ai_run_id);
CREATE INDEX idx_ai_tool_logs_tool ON ai_tool_logs(ai_tool_id);
```

---

### Knowledge / RAG Layer

#### 15. `knowledge_documents` (for RAG)
```sql
CREATE TABLE knowledge_documents (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID REFERENCES companies(id) ON DELETE CASCADE NOT NULL,
    source_type VARCHAR(30) NOT NULL 
        CHECK (source_type IN ('contact', 'project', 'task', 'conversation', 'file', 'manual', 'other')),
    source_id UUID,  -- Optional reference to source entity
    title VARCHAR(255),
    raw_text TEXT NOT NULL,
    chunk_index INT DEFAULT 0,  -- For split documents
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,  -- Tags, entity refs, URL
    embedding vector(1536),  -- Adjust dimension to your model
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_knowledge_docs_company ON knowledge_documents(company_id);
CREATE INDEX idx_knowledge_docs_source ON knowledge_documents(source_type, source_id);
CREATE INDEX idx_knowledge_docs_embedding ON knowledge_documents 
    USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100);
```

---

### Activity / Event Stream

#### 16. `events` (append-only activity log for AI summaries)
```sql
CREATE TABLE events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID REFERENCES companies(id) ON DELETE CASCADE NOT NULL,
    entity_type VARCHAR(50) NOT NULL,  -- 'contact', 'project', 'task', 'conversation', 'company'
    entity_id UUID NOT NULL,
    
    -- Who did it
    actor_type VARCHAR(20) 
        CHECK (actor_type IN ('user', 'contact', 'ai_agent', 'system')) DEFAULT 'system',
    actor_id UUID,
    
    -- What happened
    event_type VARCHAR(50) NOT NULL,  -- 'task_created', 'status_changed', 'note_added', etc.
    data JSONB NOT NULL DEFAULT '{}'::jsonb,  -- Diffs, values, context
    
    occurred_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_events_company_time ON events(company_id, occurred_at DESC);
CREATE INDEX idx_events_entity ON events(entity_type, entity_id, occurred_at DESC);
CREATE INDEX idx_events_actor ON events(actor_type, actor_id);
CREATE INDEX idx_events_type ON events(company_id, event_type);
```

---

### AI Presentation Layer

#### 17. `ai_presentations` (container)
```sql
CREATE TABLE ai_presentations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID REFERENCES companies(id) ON DELETE CASCADE NOT NULL,
    conversation_id UUID REFERENCES conversations(id) ON DELETE SET NULL,
    entity_type VARCHAR(50),
    entity_id UUID,
    layout VARCHAR(50) DEFAULT 'board',  -- 'board', 'timeline', 'insights', 'dashboard'
    created_by UUID REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_ai_presentations_company ON ai_presentations(company_id);
CREATE INDEX idx_ai_presentations_conversation ON ai_presentations(conversation_id);
CREATE INDEX idx_ai_presentations_entity ON ai_presentations(entity_type, entity_id);
```

#### 18. `ai_presentation_cards` (individual cards)
```sql
CREATE TABLE ai_presentation_cards (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    presentation_id UUID REFERENCES ai_presentations(id) ON DELETE CASCADE NOT NULL,
    card_type VARCHAR(50) NOT NULL,  -- 'metric', 'timeline', 'note', 'recommendation', 'risk', 'action'
    title VARCHAR(255),
    body JSONB NOT NULL DEFAULT '{}'::jsonb,  -- Text, bullets, metrics, actions
    "order" INT NOT NULL DEFAULT 0,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    ai_run_id UUID REFERENCES ai_runs(id) ON DELETE SET NULL,  -- Which AI run generated this
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_ai_presentation_cards_presentation_order 
    ON ai_presentation_cards(presentation_id, "order");
CREATE INDEX idx_ai_presentation_cards_type ON ai_presentation_cards(card_type);
```

---

### Live Collaboration Tables

#### 19. `live_sessions` (Supabase Realtime)
```sql
CREATE TABLE live_sessions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID REFERENCES companies(id) ON DELETE CASCADE NOT NULL,
    session_type VARCHAR(30),  -- 'project_board', 'meeting', 'document', 'presentation'
    entity_type VARCHAR(50) NOT NULL,
    entity_id UUID NOT NULL,
    created_by UUID REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_live_sessions_company ON live_sessions(company_id);
CREATE INDEX idx_live_sessions_entity ON live_sessions(entity_type, entity_id);
```

#### 20. `live_notes` (collaborative annotations)
```sql
CREATE TABLE live_notes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    session_id UUID REFERENCES live_sessions(id) ON DELETE CASCADE NOT NULL,
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    content JSONB NOT NULL DEFAULT '{}'::jsonb,  -- Y.js or CRDT format
    position JSONB,
    color VARCHAR(20),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_live_notes_session ON live_notes(session_id);
```

---

### Storage Tables

#### 21. `media` (Bunny CDN references)
```sql
CREATE TABLE media (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    company_id UUID REFERENCES companies(id) ON DELETE CASCADE NOT NULL,
    uploaded_by UUID REFERENCES users(id) ON DELETE SET NULL,
    entity_type VARCHAR(50),
    entity_id UUID,
    filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    size BIGINT NOT NULL,
    bunny_url VARCHAR(500) NOT NULL,
    bunny_stream_id VARCHAR(100),  -- For videos
    deleted_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_media_company ON media(company_id);
CREATE INDEX idx_media_entity ON media(entity_type, entity_id);
CREATE INDEX idx_media_deleted ON media(deleted_at) WHERE deleted_at IS NULL;
```

---

## Key Architecture Decisions

### 1. Authentication Flow
- Supabase Auth handles all authentication (email/password, OAuth, magic link)
- Google OAuth configured via Supabase Auth providers
- Supabase JWT tokens for API authentication
- Laravel validates Supabase JWTs for protected API routes
- Frontend uses Supabase client for auth state management

### 2. Role & Contact Architecture

**Core Design Principle**: One global identity per human with per-company roles and relationships.

| Role Type | Table | Values | Description |
|-----------|-------|--------|-------------|
| **Global Role** | `users.global_role` | `admin`, `user` | Platform-level access |
| **Company Role** | `company_user.role_in_company` | `owner`, `manager`, `staff`, `agent` | Per-company staff membership |
| **Contact Relation** | `company_contacts.relation_type` | `lead`, `customer`, `prospect`, `vendor`, `partner` | Per-company contact relationship |

**Flexibility Examples**:
- Same person = lead of Company A, customer of Company B, staff of Company C
- Contact with login access: `contacts.auth_user_id` links to `users.id`
- Convert lead → customer: Update `company_contacts.relation_type`

**Mapping to User Types**:
| User Type | Implementation |
|-----------|----------------|
| Super Admin | `users.global_role = 'admin'` |
| Owner | `company_user.role_in_company = 'owner'` |
| Staff | `company_user.role_in_company IN ('manager', 'staff', 'agent')` |
| Customer | `company_contacts.relation_type = 'customer'` |
| Lead | `company_contacts.relation_type = 'lead'` |

### 3. Multi-tenancy Strategy
- Company-scoped data with `company_id` on all tenant tables
- Contacts are global but relationships are company-scoped via `company_contacts`
- Laravel middleware for automatic company scoping
- Admin users (super admin) bypass company scope
- Soft deletes preserve AI history access

### 4. AI Architecture
- **AI Agents**: Multiple specialized bots per company
- **AI Runs**: Full audit trail of LLM calls with cost tracking
- **AI Tools**: Function calling definitions with execution logs
- **Knowledge/RAG**: pgvector embeddings for semantic search
- **Events**: Activity stream for AI summarization

### 5. Realtime Architecture
- **Chat**: Laravel Broadcasting with Reverb for WebSocket
- **Live Collaboration**: Supabase Realtime for presence + live notes
- **Sync Strategy**: Laravel as source of truth, Supabase for realtime sync

### 6. Storage Strategy
- Bunny CDN for static files (images, documents)
- Bunny Stream for video content
- Signed URLs for private content access

### 7. Message Design for AI
- `sender_type` distinguishes user/contact/AI/system/tool
- `role` maps to LLM roles (user/assistant/system/tool)
- `message_type` classifies chat/note/event/command/error
- `ai_run_id` links to AI traces for debugging

---

## Event Types Reference

Common event types for the `events` table:

```
-- Contacts
contact_created, contact_updated, contact_deleted, contact_converted
status_changed, assigned_changed, note_added

-- Projects
project_created, project_updated, project_deleted
status_changed, budget_changed

-- Tasks
task_created, task_updated, task_deleted, task_completed
status_changed, assigned_changed, due_date_changed

-- Conversations
conversation_started, conversation_closed, conversation_archived
participant_joined, participant_left

-- AI
ai_reply_generated, ai_summary_created, ai_tool_executed
ai_recommendation_made
```

---

## Folder Structure

```
/beemud9
├── /plan                        # Plans and documentation
│   ├── architecture-plan.md
│   ├── ui-requirements.md
│   ├── design-specification.md  # Sidebar layout & styling spec
│   ├── deployment-config.md
│   └── environment-setup.md
├── /apps                        # Frontend applications
│   ├── /marketing               # Next.js - Marketing/landing site
│   │   ├── next.config.ts
│   │   └── /src
│   │       └── /app             # Next.js App Router
│   ├── /portal                  # React + Vite - Admin portal (Staff/Admin)
│   │   ├── /src
│   │   │   ├── /layouts         # AdminLayout, AuthLayout
│   │   │   ├── /pages           # Dashboard, Companies, Contacts, etc.
│   │   │   ├── /components
│   │   │   │   ├── /chat
│   │   │   │   ├── /cards
│   │   │   │   └── /presentation
│   │   │   ├── /hooks
│   │   │   ├── /stores
│   │   │   └── /services
│   │   └── vite.config.ts
│   └── /client                  # React + Vite - Client portal (Customers)
│       ├── /src
│       │   ├── /layouts         # ClientLayout, AuthLayout
│       │   ├── /pages           # Dashboard, Projects, Messages, etc.
│       │   ├── /components
│       │   ├── /hooks
│       │   ├── /stores
│       │   └── /services
│       └── vite.config.ts
├── /backend                     # Laravel 12 API
│   ├── /app
│   │   ├── /Http/Controllers
│   │   ├── /Models
│   │   ├── /Services
│   │   │   ├── SupabaseService.php
│   │   │   ├── AIService.php
│   │   │   └── KnowledgeService.php
│   │   ├── /AI
│   │   │   ├── /Agents
│   │   │   └── /Tools
│   │   └── /Broadcasting
│   ├── /database/migrations
│   └── /routes
├── /packages                    # Shared monorepo packages
│   ├── /hooks                   # Shared React hooks (@beemud/hooks)
│   │   └── /src
│   ├── /types                   # Shared TypeScript types (@beemud/types)
│   │   └── /src
│   └── /ui                      # Shared UI components (@beemud/ui)
│       └── /src                 # Tailwind + DaisyUI components
├── pnpm-workspace.yaml          # PNPM workspace configuration
├── turbo.json                   # Turborepo build orchestration
└── package.json                 # Root package.json
```

---

## Application Overview

### Apps

| App | Tech Stack | Purpose | Target Users |
|-----|------------|---------|--------------|
| `marketing` | Next.js 15 | Public website, landing pages, SEO-optimized content | Public visitors |
| `portal` | React 18 + Vite | Admin dashboard, CRM management, AI co-worker interface | Staff, Managers, Admins |
| `client` | React 18 + Vite | Customer portal, project tracking, messaging | Customers, Contacts |
| `backend` | Laravel 12 | REST API, authentication, business logic, AI orchestration | All frontend apps |

### Shared Packages

| Package | Purpose | Used By |
|---------|---------|---------|
| `@beemud/ui` | Reusable UI components (Tailwind + DaisyUI) | portal, client |
| `@beemud/hooks` | Shared React hooks (auth, API, realtime) | portal, client |
| `@beemud/types` | TypeScript type definitions | All apps |

### App Details

#### Marketing (`apps/marketing`)
- **Framework**: Next.js 15 with App Router
- **Purpose**: Public-facing marketing site
- **Features**: Landing pages, pricing, features showcase, blog
- **Auth**: None (public site)
- **SEO**: Server-side rendering for optimal search indexing

#### Portal (`apps/portal`)
- **Framework**: React 18 + Vite
- **Purpose**: Internal admin dashboard for staff
- **Features**: 
  - AI presentation panel (left) + Chat widget (right) layout
  - Company/Contact/Project/Task management
  - Team collaboration and internal chat
  - AI co-worker interface for natural language commands
  - Analytics and reporting dashboards
- **Auth**: Staff login via Supabase Auth (email/password, Google OAuth)
- **Multi-tenancy**: Company-scoped data access

#### Client (`apps/client`)
- **Framework**: React 18 + Vite
- **Purpose**: Customer-facing portal
- **Features**:
  - Project status and timeline view
  - Document/file access
  - Messaging with support team
  - Invoice and payment history
- **Auth**: Customer login via Supabase Auth (email/password, Google OAuth)
- **Access**: Scoped to their contact/company data only

---

## Implementation Phases

### Phase 1: Foundation (Current)
- [x] Create plan folder with architecture docs
- [x] Set up Laravel with Supabase JWT authentication
- [x] Configure Supabase connection
- [x] Create React SPA scaffold with Vite
- [x] Install Tailwind CSS + DaisyUI
- [ ] Implement shared sidebar layout (see [design-specification.md](./design-specification.md))
- [ ] Create shared UI components in @beemud/ui

### Phase 2: Core CRM
- Users, Companies, Contacts CRUD
- Multi-tenancy middleware with soft deletes
- Staff invitation system
- Events logging

### Phase 3: Task Management
- Projects and Tasks CRUD
- Kanban board UI
- Task assignment and notifications
- Activity timeline

### Phase 4: Chat System
- Laravel Broadcasting setup
- AI-native messages (polymorphic senders)
- Internal team chat
- Customer-facing widget
- Message threading

### Phase 5: AI Infrastructure
- AI agents table & management
- AI runs logging & cost tracking
- AI tools & function calling
- Basic AI chat responses

### Phase 6: Knowledge / RAG
- pgvector embeddings setup
- Knowledge document ingestion
- RAG-based AI responses
- Auto-summarization

### Phase 7: AI Presentation View
- Split-panel SPA layout
- AI presentation cards
- Real-time card updates
- Different layouts (board, timeline, insights)

### Phase 8: Live Collaboration
- Supabase Realtime integration
- Presence indicators
- Collaborative notes/annotations
- Live cursor tracking

### Phase 9: Polish
- Bunny Stream video integration
- Advanced permissions
- Performance optimization
- AI cost dashboards
