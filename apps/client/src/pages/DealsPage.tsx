import { useState, useEffect, useCallback } from 'react'
import {
  DollarSign,
  TrendingUp,
  Plus,
  Search,
  MoreHorizontal,
  User,
  Calendar,
  XCircle,
  RefreshCw,
  Loader2,
  ArrowRight,
  Trophy,
  Target,
} from 'lucide-react'
import Layout from '../components/Layout'
import { useModalStore, MODAL_NAMES } from '../stores/modal'
import { useAuthStore } from '../stores/auth'
import { api } from '../lib/api'

// Types
interface Deal {
  id: string
  title: string
  description: string | null
  value: number | null
  currency: string
  stage: string
  probability: number
  expected_close_date: string | null
  contact: {
    id: string
    name: string
    email: string | null
  } | null
  assignee: {
    id: string
    name: string
    avatar: string | null
  } | null
  is_closed: boolean
  created_at: string
}

interface PipelineStats {
  total_value: number
  weighted_value: number
  deals_count: number
  closing_soon: number
  by_stage: {
    [key: string]: {
      name: string
      count: number
      value: number
    }
  }
}

interface Stage {
  key: string
  name: string
  color: string
  bgColor: string
  probability: number
}

const STAGES: Stage[] = [
  { key: 'qualification', name: 'Qualification', color: 'text-info', bgColor: 'bg-info/10', probability: 10 },
  { key: 'proposal', name: 'Proposal', color: 'text-warning', bgColor: 'bg-warning/10', probability: 30 },
  { key: 'negotiation', name: 'Negotiation', color: 'text-secondary', bgColor: 'bg-secondary/10', probability: 60 },
  { key: 'closed_won', name: 'Closed Won', color: 'text-success', bgColor: 'bg-success/10', probability: 100 },
  { key: 'closed_lost', name: 'Closed Lost', color: 'text-error', bgColor: 'bg-error/10', probability: 0 },
]

export default function DealsPage() {
  const [deals, setDeals] = useState<Deal[]>([])
  const [stats, setStats] = useState<PipelineStats | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isLoadingStats, setIsLoadingStats] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [searchQuery, setSearchQuery] = useState('')
  const [showClosedDeals, setShowClosedDeals] = useState(false)
  const [movingDeal, setMovingDeal] = useState<string | null>(null)
  
  const { openModal } = useModalStore()
  const { company } = useAuthStore()

  // Fetch deals
  const fetchDeals = useCallback(async () => {
    if (!company?.id) return
    
    setIsLoading(true)
    setError(null)
    
    try {
      const response = await api.get('/api/v1/deals', {
        params: {
          open_only: !showClosedDeals,
          search: searchQuery || undefined,
          limit: 100,
        }
      })
      setDeals(response.data.data || [])
    } catch (err: unknown) {
      console.error('Failed to fetch deals:', err)
      setError('Failed to load deals')
    } finally {
      setIsLoading(false)
    }
  }, [company?.id, showClosedDeals, searchQuery])

  // Fetch pipeline stats
  const fetchStats = useCallback(async () => {
    if (!company?.id) return
    
    setIsLoadingStats(true)
    
    try {
      const response = await api.get('/api/v1/deals/stats')
      setStats(response.data)
    } catch (err: unknown) {
      console.error('Failed to fetch pipeline stats:', err)
    } finally {
      setIsLoadingStats(false)
    }
  }, [company?.id])

  useEffect(() => {
    fetchDeals()
    fetchStats()
  }, [fetchDeals, fetchStats])

  // Move deal to new stage
  const moveDealToStage = async (dealId: string, newStage: string) => {
    setMovingDeal(dealId)
    
    try {
      await api.patch(`/api/v1/deals/${dealId}`, {
        stage: newStage,
      })
      
      // Refresh deals
      await fetchDeals()
      await fetchStats()
    } catch (err: unknown) {
      console.error('Failed to move deal:', err)
      setError('Failed to move deal')
    } finally {
      setMovingDeal(null)
    }
  }

  // Format currency
  const formatCurrency = (value: number | null, currency: string = 'USD') => {
    if (value === null) return '-'
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: currency,
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(value)
  }

  // Group deals by stage
  const dealsByStage = STAGES.reduce((acc, stage) => {
    acc[stage.key] = deals.filter(deal => deal.stage === stage.key)
    return acc
  }, {} as Record<string, Deal[]>)

  // Calculate stage totals
  const getStageTotals = (stageDeals: Deal[]) => {
    const total = stageDeals.reduce((sum, d) => sum + (d.value || 0), 0)
    return { count: stageDeals.length, value: total }
  }

  return (
    <Layout>
      <div className="p-6 space-y-6">
        {/* Header */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-semibold text-base-content">Sales Pipeline</h1>
            <p className="text-base-content/60 mt-1">Track and manage your sales opportunities</p>
          </div>
          <button
            onClick={() => openModal(MODAL_NAMES.CREATE_DEAL)}
            className="btn btn-primary gap-2"
          >
            <Plus className="w-4 h-4" />
            New Deal
          </button>
        </div>

        {/* Stats Cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <div className="card bg-base-200 p-4">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                <Target className="w-5 h-5 text-primary" />
              </div>
              <div>
                <p className="text-xs text-base-content/60 uppercase tracking-wide">Open Deals</p>
                <p className="text-xl font-semibold text-base-content">
                  {isLoadingStats ? <span className="loading loading-dots loading-sm"></span> : stats?.deals_count || 0}
                </p>
              </div>
            </div>
          </div>

          <div className="card bg-base-200 p-4">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-lg bg-success/10 flex items-center justify-center">
                <DollarSign className="w-5 h-5 text-success" />
              </div>
              <div>
                <p className="text-xs text-base-content/60 uppercase tracking-wide">Pipeline Value</p>
                <p className="text-xl font-semibold text-base-content">
                  {isLoadingStats ? <span className="loading loading-dots loading-sm"></span> : formatCurrency(stats?.total_value || 0)}
                </p>
              </div>
            </div>
          </div>

          <div className="card bg-base-200 p-4">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-lg bg-warning/10 flex items-center justify-center">
                <TrendingUp className="w-5 h-5 text-warning" />
              </div>
              <div>
                <p className="text-xs text-base-content/60 uppercase tracking-wide">Weighted Value</p>
                <p className="text-xl font-semibold text-base-content">
                  {isLoadingStats ? <span className="loading loading-dots loading-sm"></span> : formatCurrency(stats?.weighted_value || 0)}
                </p>
              </div>
            </div>
          </div>

          <div className="card bg-base-200 p-4">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-lg bg-error/10 flex items-center justify-center">
                <Calendar className="w-5 h-5 text-error" />
              </div>
              <div>
                <p className="text-xs text-base-content/60 uppercase tracking-wide">Closing Soon</p>
                <p className="text-xl font-semibold text-base-content">
                  {isLoadingStats ? <span className="loading loading-dots loading-sm"></span> : stats?.closing_soon || 0}
                </p>
              </div>
            </div>
          </div>
        </div>

        {/* Filters */}
        <div className="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
          <div className="flex items-center gap-4">
            <div className="relative">
              <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-base-content/40" />
              <input
                type="text"
                placeholder="Search deals..."
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="input input-bordered pl-10 w-64"
              />
            </div>
            
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={showClosedDeals}
                onChange={(e) => setShowClosedDeals(e.target.checked)}
                className="checkbox checkbox-sm"
              />
              <span className="text-sm text-base-content/70">Show closed deals</span>
            </label>
          </div>

          <button
            onClick={() => { fetchDeals(); fetchStats(); }}
            className="btn btn-ghost btn-sm gap-2"
          >
            <RefreshCw className="w-4 h-4" />
            Refresh
          </button>
        </div>

        {/* Error state */}
        {error && (
          <div className="alert alert-error">
            <XCircle className="w-5 h-5" />
            <span>{error}</span>
          </div>
        )}

        {/* Loading state */}
        {isLoading && (
          <div className="flex items-center justify-center py-12">
            <Loader2 className="w-8 h-8 animate-spin text-primary" />
          </div>
        )}

        {/* Pipeline Kanban */}
        {!isLoading && (
          <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
            {STAGES.filter(stage => showClosedDeals || !['closed_won', 'closed_lost'].includes(stage.key)).map((stage) => {
              const stageDeals = dealsByStage[stage.key] || []
              const totals = getStageTotals(stageDeals)

              return (
                <div
                  key={stage.key}
                  className="flex flex-col bg-base-200 rounded-lg min-h-[400px]"
                >
                  {/* Stage Header */}
                  <div className={`p-3 border-b border-base-300 ${stage.bgColor}`}>
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        {stage.key === 'closed_won' && <Trophy className={`w-4 h-4 ${stage.color}`} />}
                        {stage.key === 'closed_lost' && <XCircle className={`w-4 h-4 ${stage.color}`} />}
                        <h3 className={`font-medium ${stage.color}`}>{stage.name}</h3>
                      </div>
                      <span className="badge badge-ghost badge-sm">{totals.count}</span>
                    </div>
                    <p className="text-xs text-base-content/60 mt-1">
                      {formatCurrency(totals.value)} • {stage.probability}%
                    </p>
                  </div>

                  {/* Deals List */}
                  <div className="flex-1 p-2 space-y-2 overflow-y-auto">
                    {stageDeals.length === 0 ? (
                      <p className="text-center text-base-content/40 py-8 text-sm">
                        No deals
                      </p>
                    ) : (
                      stageDeals.map((deal) => (
                        <DealCard
                          key={deal.id}
                          deal={deal}
                          stages={STAGES}
                          currentStage={stage}
                          onMoveToStage={moveDealToStage}
                          isMoving={movingDeal === deal.id}
                          formatCurrency={formatCurrency}
                        />
                      ))
                    )}
                  </div>
                </div>
              )
            })}
          </div>
        )}

        {/* Empty state */}
        {!isLoading && deals.length === 0 && (
          <div className="text-center py-12">
            <Target className="w-12 h-12 mx-auto text-base-content/30 mb-4" />
            <h3 className="text-lg font-medium text-base-content mb-2">No deals yet</h3>
            <p className="text-base-content/60 mb-4">Create your first deal to start tracking your sales pipeline</p>
            <button
              onClick={() => openModal(MODAL_NAMES.CREATE_DEAL)}
              className="btn btn-primary gap-2"
            >
              <Plus className="w-4 h-4" />
              Create Deal
            </button>
          </div>
        )}
      </div>
    </Layout>
  )
}

// Deal Card Component
interface DealCardProps {
  deal: Deal
  stages: Stage[]
  currentStage: Stage
  onMoveToStage: (dealId: string, stage: string) => void
  isMoving: boolean
  formatCurrency: (value: number | null, currency?: string) => string
}

function DealCard({ deal, stages, currentStage, onMoveToStage, isMoving, formatCurrency }: DealCardProps) {
  const [showActions, setShowActions] = useState(false)
  
  const availableStages = stages.filter(s => s.key !== currentStage.key)

  return (
    <div className="card bg-base-100 shadow-sm border border-base-300 hover:border-primary/30 transition-colors">
      <div className="p-3">
        <div className="flex items-start justify-between gap-2">
          <h4 className="font-medium text-sm text-base-content line-clamp-2">{deal.title}</h4>
          
          <div className="dropdown dropdown-end">
            <button
              tabIndex={0}
              className="btn btn-ghost btn-xs btn-square"
              onClick={() => setShowActions(!showActions)}
            >
              {isMoving ? (
                <Loader2 className="w-3 h-3 animate-spin" />
              ) : (
                <MoreHorizontal className="w-3 h-3" />
              )}
            </button>
            <ul tabIndex={0} className="dropdown-content z-10 menu menu-sm p-2 shadow bg-base-200 rounded-box w-48">
              <li className="menu-title text-xs">Move to</li>
              {availableStages.map((stage) => (
                <li key={stage.key}>
                  <button
                    onClick={() => onMoveToStage(deal.id, stage.key)}
                    className={`text-sm ${stage.color}`}
                  >
                    <ArrowRight className="w-3 h-3" />
                    {stage.name}
                  </button>
                </li>
              ))}
              <div className="divider my-1"></div>
              <li>
                <button className="text-sm">
                  <User className="w-3 h-3" />
                  View Details
                </button>
              </li>
            </ul>
          </div>
        </div>

        {/* Value */}
        {deal.value && (
          <p className="text-primary font-semibold text-sm mt-2">
            {formatCurrency(deal.value, deal.currency)}
          </p>
        )}

        {/* Meta */}
        <div className="flex items-center gap-2 mt-2 text-xs text-base-content/60">
          {deal.contact && (
            <span className="flex items-center gap-1">
              <User className="w-3 h-3" />
              {deal.contact.name}
            </span>
          )}
          {deal.expected_close_date && (
            <span className="flex items-center gap-1">
              <Calendar className="w-3 h-3" />
              {new Date(deal.expected_close_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}
            </span>
          )}
        </div>

        {/* Probability bar */}
        <div className="mt-2">
          <div className="flex items-center justify-between text-xs mb-1">
            <span className="text-base-content/50">Probability</span>
            <span className={currentStage.color}>{deal.probability}%</span>
          </div>
          <div className="w-full bg-base-300 rounded-full h-1">
            <div
              className={`h-1 rounded-full ${currentStage.key === 'closed_won' ? 'bg-success' : currentStage.key === 'closed_lost' ? 'bg-error' : 'bg-primary'}`}
              style={{ width: `${deal.probability}%` }}
            />
          </div>
        </div>
      </div>
    </div>
  )
}

