# Coding Guidelines & Common Pitfalls

This document contains important coding patterns, common pitfalls, and their solutions specific to the BeetaSky project. **Read this before implementing new features.**

---

## Table of Contents

1. [PostgreSQL Boolean Handling](#postgresql-boolean-handling)
2. [Zustand Persist Middleware](#zustand-persist-middleware)
3. [API Response Handling](#api-response-handling)
4. [TypeScript Safety Patterns](#typescript-safety-patterns)

---

## PostgreSQL Boolean Handling

### ⚠️ Critical Issue

Our PostgreSQL configuration uses `PDO::ATTR_EMULATE_PREPARES => true` for Supabase/PgBouncer compatibility. This causes a **type mismatch** when querying boolean columns.

### The Problem

```php
// ❌ WRONG - This will fail with PostgreSQL
$query->where('completed', true);
$query->where('completed', false);

// Error: "operator does not exist: boolean = integer"
// Because PHP true/false becomes 1/0 with emulated prepares
```

When using emulated prepares:
- PHP `true` → String `"1"` → PostgreSQL sees `completed = 1`
- PHP `false` → String `"0"` → PostgreSQL sees `completed = 0`
- PostgreSQL boolean columns don't accept integers, they need `true`/`false` literals

### The Solution

**Always use `whereRaw()` for boolean comparisons in queries:**

```php
// ✅ CORRECT - Use whereRaw for boolean columns
$query->whereRaw('completed = true');
$query->whereRaw('completed = false');

// ✅ CORRECT - In model scopes
public function scopeCompleted($query)
{
    return $query->whereRaw('completed = true');
}

public function scopeIncomplete($query)
{
    return $query->whereRaw('completed = false');
}
```

### Affected Columns

Any boolean column in the database is affected. Common ones include:
- `tasks.completed`
- `tasks.is_locked`
- `tasks.ai_generated`
- `projects.ai_enabled`
- `topics.is_locked`
- `users.is_active`
- `project_members.is_customer`

### Model Attribute Updates Are Fine

Note: Model attribute updates work correctly because Laravel's casting handles the conversion before the query builder:

```php
// ✅ This works fine - model casting handles it
$task->update([
    'completed' => true,
    'is_locked' => false,
]);
```

The issue is **only with `where()` clauses** in queries.

### Quick Reference

| Operation | Correct Pattern |
|-----------|-----------------|
| Query for true | `->whereRaw('column = true')` |
| Query for false | `->whereRaw('column = false')` |
| Model update | `->update(['column' => true])` ✅ |
| Model scope | Use `whereRaw()` inside |
| withCount condition | Use `whereRaw()` in closure |

---

## Zustand Persist Middleware

### ⚠️ Critical Issue

When using Zustand's `persist` middleware with `partialize` (to save only specific state), the restored state can have **missing properties** that cause runtime errors.

### The Problem

```typescript
// ❌ WRONG - Only saving partial filters
persist(
  (set, get) => ({ /* store */ }),
  {
    name: 'my-storage',
    partialize: (state) => ({
      filters: {
        // Only saving 2 of 5 filter properties
        excludeCompleted: state.filters.excludeCompleted,
        quickFilter: state.filters.quickFilter,
      },
    }),
  }
)

// When restored, filters.status is UNDEFINED!
// Error: "Cannot read properties of undefined (reading 'length')"
```

### The Solution

**Always include a `merge` function** when using `partialize`:

```typescript
// ✅ CORRECT - Include merge function
persist(
  (set, get) => ({
    filters: {
      search: '',
      projectId: null,
      status: [],        // ← These need to exist
      priority: [],      // ← after restore
      quickFilter: 'all',
      excludeCompleted: false,
    },
    // ... rest of store
  }),
  {
    name: 'my-storage',
    partialize: (state) => ({
      // Only persist what you need
      filters: {
        excludeCompleted: state.filters.excludeCompleted,
        quickFilter: state.filters.quickFilter,
      },
    }),
    // ✅ REQUIRED: Merge persisted state with defaults
    merge: (persistedState, currentState) => {
      const persisted = persistedState as Partial<MyStoreState>
      return {
        ...currentState,
        filters: {
          ...currentState.filters,    // Defaults first
          ...persisted.filters,       // Then persisted values
        },
      }
    },
  }
)
```

### Also Add Defensive Checks

Even with proper merge, add defensive checks for arrays:

```typescript
// ✅ CORRECT - Defensive array access
if (filters.status && filters.status.length > 0) {
  filters.status.forEach((s) => params.append('status[]', s))
}

// ❌ WRONG - Assumes array exists
if (filters.status.length > 0) {
  // TypeError if status is undefined
}
```

### Quick Reference

| Scenario | Pattern |
|----------|---------|
| Partial persist | Always add `merge` function |
| Array properties | Check `array && array.length` |
| Object properties | Use `?.` optional chaining |
| New store properties | Add to defaults + merge handles backwards compat |

---

## API Response Handling

### Always Check for Success

API responses should always be checked for the `success` property:

```typescript
// ✅ CORRECT
const response = await api.get('/api/v1/tasks')
if (response.data.success) {
  setTasks(response.data.data)
} else {
  setError(response.data.message)
}

// ❌ WRONG - Assumes success
const response = await api.get('/api/v1/tasks')
setTasks(response.data.data) // May be undefined on error
```

### Handle Missing Data

```typescript
// ✅ CORRECT - Default to empty array
setTasks(response.data.data ?? [])
setPagination(response.data.pagination ?? null)

// ✅ CORRECT - Check nested properties
const projectName = project?.name ?? 'Unknown Project'
```

---

## TypeScript Safety Patterns

### Nullable Arrays

```typescript
// ✅ Define arrays as non-nullable with defaults
interface FilterState {
  status: string[]      // Not string[] | undefined
  priority: string[]    // Initialize as []
}

// ✅ Initialize with empty arrays
const defaultFilters: FilterState = {
  status: [],
  priority: [],
}
```

### Optional Chaining for API Data

```typescript
// ✅ CORRECT - API data may be incomplete
const assigneeName = task.assignees?.[0]?.name ?? 'Unassigned'
const projectCode = task.project?.code ?? ''
const topicColor = task.topic?.color ?? '#6b7280'
```

### Type Guards

```typescript
// ✅ Use type guards for runtime safety
function isValidTask(task: unknown): task is Task {
  return (
    typeof task === 'object' &&
    task !== null &&
    'id' in task &&
    'title' in task
  )
}
```

---

## Checklist Before Submitting Code

### Backend (Laravel/PHP)

- [ ] Boolean `where()` clauses use `whereRaw('column = true/false')`
- [ ] All new columns are added to model `$fillable`
- [ ] Boolean columns have proper cast in model `casts()` array
- [ ] API responses follow `{ success: bool, data: any, message?: string }` format

### Frontend (React/TypeScript)

- [ ] Zustand stores with `persist` + `partialize` have `merge` function
- [ ] Arrays are initialized with `[]` not `undefined`
- [ ] API response `success` is checked before using `data`
- [ ] Optional chaining used for potentially missing nested properties
- [ ] Defensive `array && array.length` checks for array operations

---

## Related Files

- `backend/config/database.php` - PostgreSQL config with emulated prepares
- `backend/app/Casts/PostgresBoolean.php` - Custom boolean cast (for model attributes only)
- `apps/client/src/stores/` - All Zustand stores

---

*Last updated: December 2024*
*Issues documented: PostgreSQL boolean handling, Zustand persist merge patterns*

