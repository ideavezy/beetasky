import { useState, useEffect } from 'react'
import {
  XMarkIcon,
  Cog6ToothIcon,
  EyeIcon,
  EyeSlashIcon,
  ClockIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  PlayIcon,
} from '@heroicons/react/24/outline'
import { useSkillsStore, type Skill, type SkillExecution } from '../../stores/skills'

interface SkillSettingsModalProps {
  skill: Skill
  isOpen: boolean
  onClose: () => void
}

export default function SkillSettingsModal({ skill, isOpen, onClose }: SkillSettingsModalProps) {
  const { updateSkillConfig, toggleSkill, executeSkill, getExecutionHistory, isExecuting } = useSkillsStore()

  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  // Form state for secrets
  const [secretValues, setSecretValues] = useState<Record<string, string>>({})
  const [showSecrets, setShowSecrets] = useState<Record<string, boolean>>({})

  // Test execution
  const [testParams, setTestParams] = useState<Record<string, string>>({})
  const [testResult, setTestResult] = useState<{ success: boolean; data?: any; error?: string } | null>(null)

  // History
  const [history, setHistory] = useState<SkillExecution[]>([])
  const [showHistory, setShowHistory] = useState(false)

  // Local enabled state
  const [isEnabled, setIsEnabled] = useState(skill.is_enabled)

  // Initialize form with existing config (masked)
  useEffect(() => {
    setIsEnabled(skill.is_enabled)
    setSecretValues({})
    setShowSecrets({})
    setTestParams({})
    setTestResult(null)
    setError(null)
    setSuccess(null)
  }, [skill])

  // Load history when toggled
  useEffect(() => {
    if (showHistory) {
      loadHistory()
    }
  }, [showHistory])

  const loadHistory = async () => {
    const executions = await getExecutionHistory(skill.slug)
    setHistory(executions)
  }

  // Handle escape key
  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && !isLoading) {
        onClose()
      }
    }
    document.addEventListener('keydown', handleEscape)
    return () => document.removeEventListener('keydown', handleEscape)
  }, [isLoading, onClose])

  const handleSecretChange = (field: string, value: string) => {
    setSecretValues((prev) => ({ ...prev, [field]: value }))
    setError(null)
    setSuccess(null)
  }

  const toggleShowSecret = (field: string) => {
    setShowSecrets((prev) => ({ ...prev, [field]: !prev[field] }))
  }

  const handleSaveSecrets = async () => {
    // Only save non-empty values
    const toSave: Record<string, string> = {}
    Object.entries(secretValues).forEach(([key, value]) => {
      if (value.trim()) {
        toSave[key] = value.trim()
      }
    })

    if (Object.keys(toSave).length === 0) {
      setError('Please enter at least one secret value')
      return
    }

    setIsLoading(true)
    setError(null)

    try {
      await updateSkillConfig(skill.slug, toSave)
      setSuccess('Configuration saved successfully')
      setSecretValues({}) // Clear form after save
    } catch (err: any) {
      setError(err.message || 'Failed to save configuration')
    } finally {
      setIsLoading(false)
    }
  }

  const handleToggleEnabled = async () => {
    const newValue = !isEnabled
    setIsEnabled(newValue)

    try {
      await toggleSkill(skill.slug, newValue)
    } catch (err) {
      setIsEnabled(!newValue) // Revert on error
    }
  }

  const handleTestParamChange = (param: string, value: string) => {
    setTestParams((prev) => ({ ...prev, [param]: value }))
  }

  const handleTestExecute = async () => {
    setTestResult(null)
    
    const result = await executeSkill(skill.slug, testParams)
    setTestResult(result)
  }

  // Get non-secret parameters for testing
  const testableParams = skill.input_schema?.properties
    ? Object.entries(skill.input_schema.properties).filter(
        ([name]) => !skill.secret_fields.includes(name)
      )
    : []

  if (!isOpen) return null

  return (
    <div className="modal modal-open">
      <div className="modal-box max-w-2xl max-h-[90vh] overflow-y-auto">
        {/* Header */}
        <div className="flex items-center justify-between mb-6">
          <div className="flex items-center gap-3">
            <div className="p-2 rounded-lg bg-base-300">
              <Cog6ToothIcon className="w-6 h-6" />
            </div>
            <div>
              <h3 className="text-lg font-bold">{skill.name}</h3>
              <p className="text-sm text-base-content/60">{skill.description || 'No description'}</p>
            </div>
          </div>
          <button
            className="btn btn-ghost btn-sm btn-circle"
            onClick={onClose}
            disabled={isLoading}
          >
            <XMarkIcon className="w-5 h-5" />
          </button>
        </div>

        {/* Error/Success */}
        {error && (
          <div className="alert alert-error mb-4">
            <span>{error}</span>
          </div>
        )}
        {success && (
          <div className="alert alert-success mb-4">
            <span>{success}</span>
          </div>
        )}

        {/* Enable/Disable Toggle */}
        <div className="card bg-base-200 mb-4">
          <div className="card-body p-4">
            <div className="flex items-center justify-between">
              <div>
                <h4 className="font-medium">Enable Skill</h4>
                <p className="text-sm text-base-content/60">
                  Allow AI to use this skill in conversations
                </p>
              </div>
              <input
                type="checkbox"
                className="toggle toggle-primary"
                checked={isEnabled}
                onChange={handleToggleEnabled}
              />
            </div>
          </div>
        </div>

        {/* Secrets Configuration */}
        {skill.secret_fields.length > 0 && (
          <div className="card bg-base-200 mb-4">
            <div className="card-body p-4">
              <div className="flex items-center justify-between mb-3">
                <h4 className="font-medium">Secrets Configuration</h4>
                {skill.has_secrets_configured && (
                  <span className="badge badge-success badge-sm gap-1">
                    <CheckCircleIcon className="w-3 h-3" />
                    Configured
                  </span>
                )}
              </div>

              {!skill.has_secrets_configured && (
                <div className="alert alert-warning mb-4">
                  <ExclamationTriangleIcon className="w-5 h-5" />
                  <span>This skill requires secrets to be configured before it can be used.</span>
                </div>
              )}

              <div className="space-y-3">
                {skill.secret_fields.map((field) => (
                  <div key={field} className="form-control">
                    <label className="label">
                      <span className="label-text font-mono text-sm">{field}</span>
                      {skill.has_secrets_configured && (
                        <span className="label-text-alt text-base-content/50">
                          Currently set (enter new value to update)
                        </span>
                      )}
                    </label>
                    <div className="relative">
                      <input
                        type={showSecrets[field] ? 'text' : 'password'}
                        className="input input-bordered w-full pr-10 font-mono text-sm"
                        placeholder={skill.has_secrets_configured ? '••••••••' : `Enter ${field}`}
                        value={secretValues[field] || ''}
                        onChange={(e) => handleSecretChange(field, e.target.value)}
                      />
                      <button
                        type="button"
                        className="absolute right-2 top-1/2 -translate-y-1/2 btn btn-ghost btn-xs btn-circle"
                        onClick={() => toggleShowSecret(field)}
                      >
                        {showSecrets[field] ? (
                          <EyeSlashIcon className="w-4 h-4" />
                        ) : (
                          <EyeIcon className="w-4 h-4" />
                        )}
                      </button>
                    </div>
                  </div>
                ))}
              </div>

              <button
                className="btn btn-primary btn-sm mt-4"
                onClick={handleSaveSecrets}
                disabled={isLoading || Object.values(secretValues).every((v) => !v.trim())}
              >
                {isLoading ? (
                  <span className="loading loading-spinner loading-sm"></span>
                ) : (
                  'Save Secrets'
                )}
              </button>
            </div>
          </div>
        )}

        {/* Test Execution */}
        {skill.has_secrets_configured && testableParams.length > 0 && (
          <div className="card bg-base-200 mb-4">
            <div className="card-body p-4">
              <h4 className="font-medium mb-3">Test Skill</h4>

              <div className="space-y-3">
                {testableParams.map(([name, prop]) => (
                  <div key={name} className="form-control">
                    <label className="label">
                      <span className="label-text">
                        {name}
                        {skill.input_schema?.required?.includes(name) && (
                          <span className="text-error ml-1">*</span>
                        )}
                      </span>
                      <span className="label-text-alt text-base-content/50">
                        {prop.description}
                      </span>
                    </label>
                    <input
                      type="text"
                      className="input input-bordered input-sm"
                      placeholder={`Enter ${name}`}
                      value={testParams[name] || ''}
                      onChange={(e) => handleTestParamChange(name, e.target.value)}
                    />
                  </div>
                ))}
              </div>

              <button
                className="btn btn-outline btn-sm gap-2 mt-4"
                onClick={handleTestExecute}
                disabled={isExecuting}
              >
                {isExecuting ? (
                  <span className="loading loading-spinner loading-sm"></span>
                ) : (
                  <PlayIcon className="w-4 h-4" />
                )}
                Execute Test
              </button>

              {testResult && (
                <div className={`alert ${testResult.success ? 'alert-success' : 'alert-error'} mt-4`}>
                  <div className="flex-1">
                    <p className="font-medium">
                      {testResult.success ? 'Success' : 'Error'}
                    </p>
                    {testResult.error && (
                      <p className="text-sm">{testResult.error}</p>
                    )}
                    {testResult.data && (
                      <pre className="text-xs mt-2 overflow-x-auto">
                        {JSON.stringify(testResult.data, null, 2)}
                      </pre>
                    )}
                  </div>
                </div>
              )}
            </div>
          </div>
        )}

        {/* Usage Stats */}
        <div className="card bg-base-200 mb-4">
          <div className="card-body p-4">
            <div className="flex items-center justify-between">
              <h4 className="font-medium">Usage Statistics</h4>
              <button
                className="btn btn-ghost btn-xs"
                onClick={() => setShowHistory(!showHistory)}
              >
                {showHistory ? 'Hide History' : 'Show History'}
              </button>
            </div>

            <div className="grid grid-cols-2 gap-4 mt-3">
              <div className="stat bg-base-300 rounded-lg p-3">
                <div className="stat-title text-xs">Total Executions</div>
                <div className="stat-value text-lg">{skill.usage_count}</div>
              </div>
              <div className="stat bg-base-300 rounded-lg p-3">
                <div className="stat-title text-xs">Last Used</div>
                <div className="stat-value text-lg">
                  {skill.last_used_at
                    ? new Date(skill.last_used_at).toLocaleDateString()
                    : 'Never'}
                </div>
              </div>
            </div>

            {showHistory && (
              <div className="mt-4">
                <h5 className="text-sm font-medium mb-2">Recent Executions</h5>
                {history.length === 0 ? (
                  <p className="text-sm text-base-content/50">No execution history</p>
                ) : (
                  <div className="space-y-2 max-h-48 overflow-y-auto">
                    {history.map((exec) => (
                      <div
                        key={exec.id}
                        className={`flex items-center justify-between p-2 rounded-lg ${
                          exec.status === 'success' ? 'bg-success/10' : 'bg-error/10'
                        }`}
                      >
                        <div className="flex items-center gap-2">
                          {exec.status === 'success' ? (
                            <CheckCircleIcon className="w-4 h-4 text-success" />
                          ) : (
                            <ExclamationTriangleIcon className="w-4 h-4 text-error" />
                          )}
                          <span className="text-sm">
                            {new Date(exec.created_at).toLocaleString()}
                          </span>
                        </div>
                        <div className="flex items-center gap-2 text-sm text-base-content/60">
                          <ClockIcon className="w-4 h-4" />
                          {exec.latency_ms}ms
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}
          </div>
        </div>

        {/* Close button */}
        <div className="flex justify-end mt-6">
          <button className="btn" onClick={onClose}>
            Close
          </button>
        </div>
      </div>
      <div className="modal-backdrop bg-black/50" onClick={onClose}></div>
    </div>
  )
}

