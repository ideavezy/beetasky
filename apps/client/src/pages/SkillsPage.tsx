import { useEffect, useState } from 'react'
import {
  BoltIcon,
  Cog6ToothIcon,
  PlusIcon,
  MagnifyingGlassIcon,
  FolderIcon,
  UsersIcon,
  LinkIcon,
  WrenchScrewdriverIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
} from '@heroicons/react/24/outline'
import { useSkillsStore, type Skill } from '../stores/skills'
import SkillSettingsModal from '../components/modals/SkillSettingsModal'
import SkillFormModal from '../components/modals/SkillFormModal'
import Layout from '../components/Layout'

// Category icons and colors
const categoryConfig: Record<string, { icon: typeof BoltIcon; color: string; bgColor: string }> = {
  project_management: {
    icon: FolderIcon,
    color: 'text-blue-400',
    bgColor: 'bg-blue-500/10',
  },
  crm: {
    icon: UsersIcon,
    color: 'text-green-400',
    bgColor: 'bg-green-500/10',
  },
  integration: {
    icon: LinkIcon,
    color: 'text-purple-400',
    bgColor: 'bg-purple-500/10',
  },
  custom: {
    icon: WrenchScrewdriverIcon,
    color: 'text-amber-400',
    bgColor: 'bg-amber-500/10',
  },
}

// Skill type badges
const skillTypeBadge: Record<string, { label: string; className: string }> = {
  mcp_tool: { label: 'MCP Tool', className: 'badge-info' },
  api_call: { label: 'API', className: 'badge-primary' },
  webhook: { label: 'Webhook', className: 'badge-secondary' },
  composite: { label: 'Composite', className: 'badge-accent' },
}

function SkillCard({ skill, onConfigure, onToggle }: {
  skill: Skill
  onConfigure: () => void
  onToggle: (enabled: boolean) => void
}) {
  const [isToggling, setIsToggling] = useState(false)
  const typeBadge = skillTypeBadge[skill.skill_type] || skillTypeBadge.api_call

  const handleToggle = async () => {
    setIsToggling(true)
    try {
      await onToggle(!skill.is_enabled)
    } finally {
      setIsToggling(false)
    }
  }

  const needsConfiguration = skill.secret_fields.length > 0 && !skill.has_secrets_configured

  return (
    <div className={`card bg-base-200 shadow-sm hover:shadow-md transition-shadow ${!skill.is_enabled ? 'opacity-60' : ''}`}>
      <div className="card-body p-4">
        <div className="flex items-start justify-between gap-3">
          <div className="flex-1 min-w-0">
            <div className="flex items-center gap-2 mb-1">
              <h3 className="font-medium text-base-content truncate">{skill.name}</h3>
              <span className={`badge badge-xs ${typeBadge.className}`}>
                {typeBadge.label}
              </span>
              {skill.is_system && (
                <span className="badge badge-xs badge-ghost">System</span>
              )}
            </div>
            <p className="text-sm text-base-content/60 line-clamp-2">
              {skill.description || 'No description'}
            </p>
          </div>

          <div className="flex items-center gap-2">
            {/* Configuration status indicator */}
            {needsConfiguration && skill.is_enabled && (
              <div className="tooltip tooltip-left" data-tip="Needs configuration">
                <ExclamationCircleIcon className="w-5 h-5 text-warning" />
              </div>
            )}
            
            {!needsConfiguration && skill.is_enabled && (
              <div className="tooltip tooltip-left" data-tip="Ready to use">
                <CheckCircleIcon className="w-5 h-5 text-success" />
              </div>
            )}

            {/* Toggle */}
            <input
              type="checkbox"
              className="toggle toggle-primary toggle-sm"
              checked={skill.is_enabled}
              onChange={handleToggle}
              disabled={isToggling}
            />
          </div>
        </div>

        {/* Footer with configure button and stats */}
        <div className="flex items-center justify-between mt-3 pt-3 border-t border-base-300">
          <div className="text-xs text-base-content/50">
            {skill.usage_count > 0 ? (
              <span>Used {skill.usage_count} times</span>
            ) : (
              <span>Never used</span>
            )}
          </div>

          <button
            className="btn btn-ghost btn-xs gap-1"
            onClick={onConfigure}
          >
            <Cog6ToothIcon className="w-4 h-4" />
            Configure
          </button>
        </div>
      </div>
    </div>
  )
}

function CategorySection({ category, categoryName, skills, onConfigureSkill, onToggleSkill }: {
  category: string
  categoryName: string
  skills: Skill[]
  onConfigureSkill: (skill: Skill) => void
  onToggleSkill: (slug: string, enabled: boolean) => void
}) {
  const config = categoryConfig[category] || categoryConfig.custom
  const CategoryIcon = config.icon

  return (
    <div className="mb-8">
      <div className="flex items-center gap-3 mb-4">
        <div className={`p-2 rounded-lg ${config.bgColor}`}>
          <CategoryIcon className={`w-5 h-5 ${config.color}`} />
        </div>
        <h2 className="text-lg font-semibold">{categoryName}</h2>
        <span className="badge badge-ghost badge-sm">{skills.length}</span>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {skills.map((skill) => (
          <SkillCard
            key={skill.id}
            skill={skill}
            onConfigure={() => onConfigureSkill(skill)}
            onToggle={(enabled) => onToggleSkill(skill.slug, enabled)}
          />
        ))}
      </div>
    </div>
  )
}

export default function SkillsPage() {
  const {
    skills,
    categories,
    isLoading,
    error,
    fetchSkills,
    toggleSkill,
    openSettingsModal,
    openFormModal,
    isSettingsModalOpen,
    isFormModalOpen,
    closeSettingsModal,
    closeFormModal,
    selectedSkill,
  } = useSkillsStore()

  const [searchQuery, setSearchQuery] = useState('')
  const [activeTab, setActiveTab] = useState<'all' | 'enabled' | 'custom'>('all')

  useEffect(() => {
    fetchSkills()
  }, [fetchSkills])

  // Filter skills based on search and tab
  const filteredSkills = skills.filter((skill) => {
    // Search filter
    if (searchQuery) {
      const query = searchQuery.toLowerCase()
      const matchesSearch =
        skill.name.toLowerCase().includes(query) ||
        skill.description?.toLowerCase().includes(query) ||
        skill.slug.toLowerCase().includes(query)
      if (!matchesSearch) return false
    }

    // Tab filter
    if (activeTab === 'enabled' && !skill.is_enabled) return false
    if (activeTab === 'custom' && skill.is_system) return false

    return true
  })

  // Group filtered skills by category
  const groupedSkills = filteredSkills.reduce((acc, skill) => {
    const category = skill.category
    if (!acc[category]) {
      acc[category] = []
    }
    acc[category].push(skill)
    return acc
  }, {} as Record<string, Skill[]>)

  const handleConfigureSkill = (skill: Skill) => {
    openSettingsModal(skill)
  }

  const handleToggleSkill = async (slug: string, enabled: boolean) => {
    try {
      await toggleSkill(slug, enabled)
    } catch (error) {
      console.error('Failed to toggle skill:', error)
    }
  }

  return (
    <Layout>
      <div className="py-6 max-w-7xl">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
          <div>
            <h1 className="text-2xl font-bold flex items-center gap-2">
              <BoltIcon className="w-7 h-7 text-primary" />
              AI Skills
            </h1>
            <p className="text-base-content/60 mt-1">
              Configure skills that the AI can use to perform actions
            </p>
          </div>

          <button
            className="btn btn-primary gap-2"
            onClick={() => openFormModal()}
          >
            <PlusIcon className="w-5 h-5" />
            Create Custom Skill
          </button>
        </div>

        {/* Search and filters */}
        <div className="flex flex-col sm:flex-row gap-4 mb-6">
          <div className="flex-1 relative">
            <MagnifyingGlassIcon className="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-base-content/40" />
            <input
              type="text"
              placeholder="Search skills..."
              className="input input-bordered w-full pl-10"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
            />
          </div>

          <div className="tabs tabs-boxed">
            <button
              className={`tab ${activeTab === 'all' ? 'tab-active' : ''}`}
              onClick={() => setActiveTab('all')}
            >
              All ({skills.length})
            </button>
            <button
              className={`tab ${activeTab === 'enabled' ? 'tab-active' : ''}`}
              onClick={() => setActiveTab('enabled')}
            >
              Enabled ({skills.filter((s) => s.is_enabled).length})
            </button>
            <button
              className={`tab ${activeTab === 'custom' ? 'tab-active' : ''}`}
              onClick={() => setActiveTab('custom')}
            >
              Custom ({skills.filter((s) => !s.is_system).length})
            </button>
          </div>
        </div>

        {/* Loading state */}
        {isLoading && (
          <div className="flex justify-center py-12">
            <span className="loading loading-spinner loading-lg text-primary"></span>
          </div>
        )}

        {/* Error state */}
        {error && (
          <div className="alert alert-error mb-6">
            <ExclamationCircleIcon className="w-5 h-5" />
            <span>{error}</span>
            <button className="btn btn-ghost btn-sm" onClick={fetchSkills}>
              Retry
            </button>
          </div>
        )}

        {/* Skills list */}
        {!isLoading && !error && (
          <>
            {Object.entries(groupedSkills).length === 0 ? (
              <div className="text-center py-12">
                <BoltIcon className="w-12 h-12 mx-auto text-base-content/30 mb-4" />
                <h3 className="text-lg font-medium text-base-content/60">No skills found</h3>
                <p className="text-base-content/40 mt-1">
                  {searchQuery
                    ? 'Try a different search term'
                    : activeTab === 'custom'
                    ? 'Create your first custom skill'
                    : 'No skills available'}
                </p>
              </div>
            ) : (
              Object.entries(groupedSkills).map(([category, categorySkills]) => (
                <CategorySection
                  key={category}
                  category={category}
                  categoryName={categories[category] || category}
                  skills={categorySkills}
                  onConfigureSkill={handleConfigureSkill}
                  onToggleSkill={handleToggleSkill}
                />
              ))
            )}
          </>
        )}

        {/* Modals */}
        {isSettingsModalOpen && selectedSkill && (
          <SkillSettingsModal
            skill={selectedSkill}
            isOpen={isSettingsModalOpen}
            onClose={closeSettingsModal}
          />
        )}

        {isFormModalOpen && (
          <SkillFormModal
            skill={selectedSkill}
            isOpen={isFormModalOpen}
            onClose={closeFormModal}
          />
        )}
      </div>
    </Layout>
  )
}

