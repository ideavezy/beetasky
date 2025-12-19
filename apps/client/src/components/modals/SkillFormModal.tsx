import { useState, useEffect } from 'react'
import {
  XMarkIcon,
  PlusIcon,
  TrashIcon,
  BoltIcon,
  BeakerIcon,
} from '@heroicons/react/24/outline'
import { useSkillsStore, type Skill, type CreateSkillData, type ApiConfig, type InputSchema } from '../../stores/skills'

interface SkillFormModalProps {
  skill?: Skill | null
  isOpen: boolean
  onClose: () => void
}

const CATEGORIES = [
  { value: 'integration', label: 'Integrations' },
  { value: 'custom', label: 'Custom' },
]

const HTTP_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as const

const PARAM_TYPES = [
  { value: 'string', label: 'String' },
  { value: 'number', label: 'Number' },
  { value: 'boolean', label: 'Boolean' },
]

interface Header {
  key: string
  value: string
}

interface Parameter {
  name: string
  type: string
  description: string
  required: boolean
}

export default function SkillFormModal({ skill, isOpen, onClose }: SkillFormModalProps) {
  const { createSkill, updateSkill } = useSkillsStore()
  const isEditing = !!skill && !skill.is_system

  const [isLoading, setIsLoading] = useState(false)
  const [isTesting, setIsTesting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [testResult, setTestResult] = useState<{ success: boolean; message: string } | null>(null)

  // Form state
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [category, setCategory] = useState('integration')
  const [skillType, setSkillType] = useState<'api_call' | 'webhook'>('api_call')

  // API Config
  const [method, setMethod] = useState<typeof HTTP_METHODS[number]>('GET')
  const [url, setUrl] = useState('')
  const [headers, setHeaders] = useState<Header[]>([{ key: '', value: '' }])
  const [bodyTemplate, setBodyTemplate] = useState('')

  // Parameters
  const [parameters, setParameters] = useState<Parameter[]>([])

  // Secret fields
  const [secretFields, setSecretFields] = useState<string[]>([])

  // Initialize form with skill data when editing
  useEffect(() => {
    if (skill && isEditing) {
      setName(skill.name)
      setDescription(skill.description || '')
      setCategory(skill.category)
      setSkillType(skill.skill_type === 'webhook' ? 'webhook' : 'api_call')

      if (skill.api_config) {
        setMethod(skill.api_config.method || 'GET')
        setUrl(skill.api_config.url || '')
        
        const headerEntries = Object.entries(skill.api_config.headers || {})
        setHeaders(headerEntries.length > 0 
          ? headerEntries.map(([key, value]) => ({ key, value }))
          : [{ key: '', value: '' }]
        )
        
        setBodyTemplate(skill.api_config.body_template 
          ? JSON.stringify(skill.api_config.body_template, null, 2)
          : ''
        )
      }

      if (skill.input_schema?.properties) {
        const params = Object.entries(skill.input_schema.properties).map(([name, prop]) => ({
          name,
          type: prop.type || 'string',
          description: prop.description || '',
          required: skill.input_schema?.required?.includes(name) || false,
        }))
        setParameters(params)
      }

      setSecretFields(skill.secret_fields || [])
    } else {
      // Reset form for new skill
      setName('')
      setDescription('')
      setCategory('integration')
      setSkillType('api_call')
      setMethod('GET')
      setUrl('')
      setHeaders([{ key: '', value: '' }])
      setBodyTemplate('')
      setParameters([])
      setSecretFields([])
    }
    setError(null)
    setTestResult(null)
  }, [skill, isEditing])

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

  // Header management
  const addHeader = () => {
    setHeaders([...headers, { key: '', value: '' }])
  }

  const updateHeader = (index: number, field: 'key' | 'value', value: string) => {
    const newHeaders = [...headers]
    newHeaders[index][field] = value
    setHeaders(newHeaders)
  }

  const removeHeader = (index: number) => {
    if (headers.length > 1) {
      setHeaders(headers.filter((_, i) => i !== index))
    }
  }

  // Parameter management
  const addParameter = () => {
    setParameters([...parameters, { name: '', type: 'string', description: '', required: false }])
  }

  const updateParameter = (index: number, field: keyof Parameter, value: string | boolean) => {
    const newParams = [...parameters]
    ;(newParams[index] as any)[field] = value
    setParameters(newParams)
  }

  const removeParameter = (index: number) => {
    setParameters(parameters.filter((_, i) => i !== index))
  }

  const toggleSecretField = (paramName: string) => {
    if (secretFields.includes(paramName)) {
      setSecretFields(secretFields.filter((f) => f !== paramName))
    } else {
      setSecretFields([...secretFields, paramName])
    }
  }

  // Build form data for submission
  const buildFormData = (): CreateSkillData => {
    // Build headers object
    const headersObj: Record<string, string> = {}
    headers.forEach((h) => {
      if (h.key.trim()) {
        headersObj[h.key.trim()] = h.value
      }
    })

    // Build body template
    let bodyTemplateObj: Record<string, any> = {}
    if (bodyTemplate.trim()) {
      try {
        bodyTemplateObj = JSON.parse(bodyTemplate)
      } catch {
        // Invalid JSON, keep as string
        bodyTemplateObj = { data: bodyTemplate }
      }
    }

    // Build API config
    const apiConfig: ApiConfig = {
      method,
      url,
      headers: headersObj,
      body_template: ['POST', 'PUT', 'PATCH'].includes(method) ? bodyTemplateObj : undefined,
    }

    // Build input schema
    const properties: Record<string, any> = {}
    const required: string[] = []
    parameters.forEach((p) => {
      if (p.name.trim()) {
        properties[p.name.trim()] = {
          type: p.type,
          description: p.description || undefined,
        }
        if (p.required) {
          required.push(p.name.trim())
        }
      }
    })

    const inputSchema: InputSchema = {
      type: 'object',
      properties,
      required,
    }

    return {
      name,
      description: description || undefined,
      category,
      skill_type: skillType,
      api_config: apiConfig,
      input_schema: inputSchema,
      secret_fields: secretFields.filter((f) => parameters.some((p) => p.name === f)),
    }
  }

  const validateForm = (): string | null => {
    if (!name.trim()) return 'Name is required'
    if (!url.trim()) return 'URL is required'
    
    try {
      new URL(url.replace(/\{\{.*?\}\}/g, 'placeholder'))
    } catch {
      return 'Invalid URL format'
    }

    if (bodyTemplate.trim()) {
      try {
        JSON.parse(bodyTemplate)
      } catch {
        return 'Body template must be valid JSON'
      }
    }

    return null
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()

    const validationError = validateForm()
    if (validationError) {
      setError(validationError)
      return
    }

    setIsLoading(true)
    setError(null)

    try {
      const data = buildFormData()

      if (isEditing && skill) {
        const result = await updateSkill(skill.id, data)
        if (!result.success) {
          setError(result.error || 'Failed to update skill')
          return
        }
      } else {
        const result = await createSkill(data)
        if (!result.success) {
          setError(result.error || 'Failed to create skill')
          return
        }
      }

      onClose()
    } catch (err: any) {
      setError(err.message || 'An error occurred')
    } finally {
      setIsLoading(false)
    }
  }

  const handleTest = async () => {
    const validationError = validateForm()
    if (validationError) {
      setError(validationError)
      return
    }

    setIsTesting(true)
    setTestResult(null)

    // Simulate test for now - in real implementation, call a test endpoint
    setTimeout(() => {
      setTestResult({
        success: true,
        message: 'Configuration looks valid. Save the skill and configure your secrets to test the actual API call.',
      })
      setIsTesting(false)
    }, 1000)
  }

  if (!isOpen) return null

  return (
    <div className="modal modal-open">
      <div className="modal-box max-w-4xl max-h-[90vh] overflow-y-auto">
        {/* Header */}
        <div className="flex items-center justify-between mb-6">
          <div className="flex items-center gap-3">
            <div className="p-2 rounded-lg bg-primary/10">
              <BoltIcon className="w-6 h-6 text-primary" />
            </div>
            <div>
              <h3 className="text-lg font-bold">
                {isEditing ? 'Edit Skill' : 'Create Custom Skill'}
              </h3>
              <p className="text-sm text-base-content/60">
                Configure an API endpoint the AI can call
              </p>
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

        {/* Error */}
        {error && (
          <div className="alert alert-error mb-4">
            <span>{error}</span>
          </div>
        )}

        {/* Test Result */}
        {testResult && (
          <div className={`alert ${testResult.success ? 'alert-success' : 'alert-error'} mb-4`}>
            <span>{testResult.message}</span>
          </div>
        )}

        <form onSubmit={handleSubmit}>
          {/* Basic Info */}
          <div className="card bg-base-200 mb-4">
            <div className="card-body p-4">
              <h4 className="font-medium mb-3">Basic Information</h4>
              
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="form-control">
                  <label className="label">
                    <span className="label-text">Name *</span>
                  </label>
                  <input
                    type="text"
                    className="input input-bordered"
                    placeholder="e.g., Send Slack Message"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    required
                  />
                </div>

                <div className="form-control">
                  <label className="label">
                    <span className="label-text">Category</span>
                  </label>
                  <select
                    className="select select-bordered"
                    value={category}
                    onChange={(e) => setCategory(e.target.value)}
                  >
                    {CATEGORIES.map((cat) => (
                      <option key={cat.value} value={cat.value}>
                        {cat.label}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="form-control mt-2">
                <label className="label">
                  <span className="label-text">Description</span>
                </label>
                <textarea
                  className="textarea textarea-bordered"
                  placeholder="Describe what this skill does..."
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  rows={2}
                />
              </div>

              <div className="form-control mt-2">
                <label className="label">
                  <span className="label-text">Skill Type</span>
                </label>
                <div className="flex gap-4">
                  <label className="label cursor-pointer gap-2">
                    <input
                      type="radio"
                      className="radio radio-primary"
                      checked={skillType === 'api_call'}
                      onChange={() => setSkillType('api_call')}
                    />
                    <span>API Call</span>
                  </label>
                  <label className="label cursor-pointer gap-2">
                    <input
                      type="radio"
                      className="radio radio-primary"
                      checked={skillType === 'webhook'}
                      onChange={() => setSkillType('webhook')}
                    />
                    <span>Webhook</span>
                  </label>
                </div>
              </div>
            </div>
          </div>

          {/* API Configuration */}
          <div className="card bg-base-200 mb-4">
            <div className="card-body p-4">
              <h4 className="font-medium mb-3">API Configuration</h4>

              <div className="flex gap-4 mb-4">
                <div className="form-control w-32">
                  <label className="label">
                    <span className="label-text">Method</span>
                  </label>
                  <select
                    className="select select-bordered"
                    value={method}
                    onChange={(e) => setMethod(e.target.value as typeof method)}
                  >
                    {HTTP_METHODS.map((m) => (
                      <option key={m} value={m}>{m}</option>
                    ))}
                  </select>
                </div>

                <div className="form-control flex-1">
                  <label className="label">
                    <span className="label-text">URL *</span>
                  </label>
                  <input
                    type="text"
                    className="input input-bordered font-mono text-sm"
                    placeholder="https://api.example.com/endpoint/{{id}}"
                    value={url}
                    onChange={(e) => setUrl(e.target.value)}
                    required
                  />
                  <label className="label">
                    <span className="label-text-alt text-base-content/50">
                      Use {'{{param}}'} for dynamic values
                    </span>
                  </label>
                </div>
              </div>

              {/* Headers */}
              <div className="mb-4">
                <div className="flex items-center justify-between mb-2">
                  <label className="label-text">Headers</label>
                  <button
                    type="button"
                    className="btn btn-ghost btn-xs gap-1"
                    onClick={addHeader}
                  >
                    <PlusIcon className="w-4 h-4" />
                    Add Header
                  </button>
                </div>

                {headers.map((header, index) => (
                  <div key={index} className="flex gap-2 mb-2">
                    <input
                      type="text"
                      className="input input-bordered input-sm flex-1"
                      placeholder="Header name"
                      value={header.key}
                      onChange={(e) => updateHeader(index, 'key', e.target.value)}
                    />
                    <input
                      type="text"
                      className="input input-bordered input-sm flex-1 font-mono text-xs"
                      placeholder="Value (use {{secret}} for secrets)"
                      value={header.value}
                      onChange={(e) => updateHeader(index, 'value', e.target.value)}
                    />
                    <button
                      type="button"
                      className="btn btn-ghost btn-sm btn-square"
                      onClick={() => removeHeader(index)}
                      disabled={headers.length <= 1}
                    >
                      <TrashIcon className="w-4 h-4" />
                    </button>
                  </div>
                ))}
              </div>

              {/* Body Template (for POST/PUT/PATCH) */}
              {['POST', 'PUT', 'PATCH'].includes(method) && (
                <div className="form-control">
                  <label className="label">
                    <span className="label-text">Body Template (JSON)</span>
                  </label>
                  <textarea
                    className="textarea textarea-bordered font-mono text-sm"
                    placeholder={'{\n  "message": "{{message}}",\n  "channel": "{{channel}}"\n}'}
                    value={bodyTemplate}
                    onChange={(e) => setBodyTemplate(e.target.value)}
                    rows={5}
                  />
                </div>
              )}
            </div>
          </div>

          {/* Parameters */}
          <div className="card bg-base-200 mb-4">
            <div className="card-body p-4">
              <div className="flex items-center justify-between mb-3">
                <h4 className="font-medium">Parameters</h4>
                <button
                  type="button"
                  className="btn btn-ghost btn-xs gap-1"
                  onClick={addParameter}
                >
                  <PlusIcon className="w-4 h-4" />
                  Add Parameter
                </button>
              </div>

              {parameters.length === 0 ? (
                <p className="text-sm text-base-content/50 text-center py-4">
                  No parameters defined. Add parameters that the AI should provide.
                </p>
              ) : (
                <div className="overflow-x-auto">
                  <table className="table table-sm">
                    <thead>
                      <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Required</th>
                        <th>Secret</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      {parameters.map((param, index) => (
                        <tr key={index}>
                          <td>
                            <input
                              type="text"
                              className="input input-bordered input-xs w-24"
                              placeholder="name"
                              value={param.name}
                              onChange={(e) => updateParameter(index, 'name', e.target.value)}
                            />
                          </td>
                          <td>
                            <select
                              className="select select-bordered select-xs"
                              value={param.type}
                              onChange={(e) => updateParameter(index, 'type', e.target.value)}
                            >
                              {PARAM_TYPES.map((t) => (
                                <option key={t.value} value={t.value}>{t.label}</option>
                              ))}
                            </select>
                          </td>
                          <td>
                            <input
                              type="text"
                              className="input input-bordered input-xs w-40"
                              placeholder="Description"
                              value={param.description}
                              onChange={(e) => updateParameter(index, 'description', e.target.value)}
                            />
                          </td>
                          <td>
                            <input
                              type="checkbox"
                              className="checkbox checkbox-sm"
                              checked={param.required}
                              onChange={(e) => updateParameter(index, 'required', e.target.checked)}
                            />
                          </td>
                          <td>
                            <input
                              type="checkbox"
                              className="checkbox checkbox-sm checkbox-warning"
                              checked={secretFields.includes(param.name)}
                              onChange={() => toggleSecretField(param.name)}
                              disabled={!param.name.trim()}
                            />
                          </td>
                          <td>
                            <button
                              type="button"
                              className="btn btn-ghost btn-xs btn-square"
                              onClick={() => removeParameter(index)}
                            >
                              <TrashIcon className="w-4 h-4" />
                            </button>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}

              <p className="text-xs text-base-content/50 mt-2">
                Mark parameters as &quot;Secret&quot; if they contain sensitive data like API keys.
                Users will configure these separately.
              </p>
            </div>
          </div>

          {/* Actions */}
          <div className="flex justify-between items-center mt-6">
            <button
              type="button"
              className="btn btn-outline gap-2"
              onClick={handleTest}
              disabled={isTesting || isLoading}
            >
              {isTesting ? (
                <span className="loading loading-spinner loading-sm"></span>
              ) : (
                <BeakerIcon className="w-5 h-5" />
              )}
              Test Configuration
            </button>

            <div className="flex gap-2">
              <button
                type="button"
                className="btn btn-ghost"
                onClick={onClose}
                disabled={isLoading}
              >
                Cancel
              </button>
              <button
                type="submit"
                className="btn btn-primary"
                disabled={isLoading}
              >
                {isLoading ? (
                  <span className="loading loading-spinner loading-sm"></span>
                ) : isEditing ? (
                  'Update Skill'
                ) : (
                  'Create Skill'
                )}
              </button>
            </div>
          </div>
        </form>
      </div>
      <div className="modal-backdrop bg-black/50" onClick={onClose}></div>
    </div>
  )
}

