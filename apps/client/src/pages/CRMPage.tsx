import { useState, useEffect, useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Users,
  UserPlus,
  TrendingUp,
  DollarSign,
  Search,
  Filter,
  MoreHorizontal,
  Phone,
  Mail,
  Building2,
  RefreshCw,
  Pencil,
  Trash2,
  Send,
  CheckCircle,
  Loader2,
  Activity,
  Plus,
  MessageSquare,
  Calendar,
  CheckSquare,
} from 'lucide-react'
import Layout from '../components/Layout'
import { useModalStore, MODAL_NAMES } from '../stores/modal'
import { useAuthStore } from '../stores/auth'
import { api } from '../lib/api'

// Activity interface
interface ActivityItem {
  id: string
  type: 'note' | 'call' | 'email' | 'meeting' | 'task'
  content: string
  is_pinned: boolean
  contact: {
    id: string
    name: string
    email: string | null
  }
  created_by: {
    id: string
    name: string
    avatar: string | null
  } | null
  created_at: string
}

// Types
interface Contact {
  id: string
  company_contact_id: number
  full_name: string
  email: string | null
  phone: string | null
  organization: string | null
  job_title: string | null
  avatar_url: string | null
  relation_type: string
  status: string
  source: string | null
  assigned_to: {
    id: string
    name: string
    avatar: string | null
  } | null
  first_seen_at: string
  last_activity_at: string | null
  created_at: string
  has_portal_access?: boolean
}

interface ContactStats {
  total: number
  leads: number
  customers: number
  prospects: number
  vendors: number
  partners: number
  active: number
}

interface Pagination {
  current_page: number
  last_page: number
  per_page: number
  total: number
  has_more: boolean
}

export default function CRMPage() {
  const [searchQuery, setSearchQuery] = useState('')
  const [activeFilter, setActiveFilter] = useState<string>('all')
  const [contacts, setContacts] = useState<Contact[]>([])
  const [stats, setStats] = useState<ContactStats | null>(null)
  const [pagination, setPagination] = useState<Pagination | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isLoadingStats, setIsLoadingStats] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [invitingContactId, setInvitingContactId] = useState<string | null>(null)

  // Activity state
  const [activities, setActivities] = useState<ActivityItem[]>([])
  const [isLoadingActivities, setIsLoadingActivities] = useState(true)

  const { openModal } = useModalStore()
  const { company } = useAuthStore()
  const navigate = useNavigate()

  // Fetch contacts from API
  const fetchContacts = useCallback(async () => {
    if (!company?.id) return

    setIsLoading(true)
    setError(null)

    try {
      const params: Record<string, string> = {
        per_page: '10',
      }

      if (activeFilter !== 'all') {
        params.type = activeFilter
      }

      if (searchQuery.trim()) {
        params.search = searchQuery.trim()
      }

      const response = await api.get('/api/v1/contacts', { params })

      if (response.data.success) {
        setContacts(response.data.data)
        setPagination(response.data.pagination)
      } else {
        setError(response.data.message || 'Failed to load contacts')
      }
    } catch (err: any) {
      console.error('Failed to fetch contacts:', err)
      setError(err.response?.data?.message || 'Failed to load contacts')
    } finally {
      setIsLoading(false)
    }
  }, [company?.id, activeFilter, searchQuery])

  // Fetch stats from API
  const fetchStats = useCallback(async () => {
    if (!company?.id) return

    setIsLoadingStats(true)

    try {
      const response = await api.get('/api/v1/contacts/stats')

      if (response.data.success) {
        setStats(response.data.data)
      }
    } catch (err: any) {
      console.error('Failed to fetch stats:', err)
    } finally {
      setIsLoadingStats(false)
    }
  }, [company?.id])

  // Fetch activities from API
  const fetchActivities = useCallback(async () => {
    if (!company?.id) return

    setIsLoadingActivities(true)

    try {
      const response = await api.get('/api/v1/activities', { params: { limit: 10 } })

      if (response.data.success) {
        setActivities(response.data.data)
      }
    } catch (err: any) {
      console.error('Failed to fetch activities:', err)
    } finally {
      setIsLoadingActivities(false)
    }
  }, [company?.id])

  // Fetch data on mount and when filters change
  useEffect(() => {
    fetchContacts()
  }, [fetchContacts])

  useEffect(() => {
    fetchStats()
  }, [fetchStats])

  useEffect(() => {
    fetchActivities()
  }, [fetchActivities])

  // Debounced search
  useEffect(() => {
    const timer = setTimeout(() => {
      fetchContacts()
    }, 300)

    return () => clearTimeout(timer)
  }, [searchQuery])

  const handleAddContact = () => {
    openModal(MODAL_NAMES.CREATE_CONTACT, {
      companyId: company?.id,
      onSuccess: () => {
        // Refresh contacts and stats after adding
        fetchContacts()
        fetchStats()
      },
    })
  }

  const handleAddActivity = () => {
    openModal(MODAL_NAMES.ADD_ACTIVITY, {
      companyId: company?.id,
      onSuccess: () => {
        // Refresh activities after adding
        fetchActivities()
      },
    })
  }

  const getActivityIcon = (type: string) => {
    switch (type) {
      case 'call':
        return <Phone className="w-4 h-4" />
      case 'email':
        return <Mail className="w-4 h-4" />
      case 'meeting':
        return <Calendar className="w-4 h-4" />
      case 'task':
        return <CheckSquare className="w-4 h-4" />
      default:
        return <MessageSquare className="w-4 h-4" />
    }
  }

  const getActivityTypeColor = (type: string) => {
    switch (type) {
      case 'call':
        return 'text-success bg-success/10'
      case 'email':
        return 'text-info bg-info/10'
      case 'meeting':
        return 'text-warning bg-warning/10'
      case 'task':
        return 'text-primary bg-primary/10'
      default:
        return 'text-base-content/70 bg-base-300'
    }
  }

  const handleFilterChange = (filter: string) => {
    setActiveFilter(filter)
  }

  const handleEditContact = (contact: Contact, e: React.MouseEvent) => {
    e.stopPropagation()
    openModal(MODAL_NAMES.EDIT_CONTACT, {
      contact,
      onSuccess: () => {
        fetchContacts()
        fetchStats()
      },
    })
  }

  const handleDeleteContact = async (contact: Contact, e: React.MouseEvent) => {
    e.stopPropagation()
    
    openModal(MODAL_NAMES.ALERT, {
      title: 'Delete Contact',
      message: `Are you sure you want to delete ${contact.full_name}? This action cannot be undone.`,
      type: 'error',
      confirmText: 'Delete',
      showCancel: true,
      onConfirm: async () => {
        try {
          const response = await api.delete(`/api/v1/contacts/${contact.id}`)
          if (response.data.success) {
            fetchContacts()
            fetchStats()
          }
        } catch (err: any) {
          console.error('Failed to delete contact:', err)
          openModal(MODAL_NAMES.ALERT, {
            title: 'Error',
            message: err.response?.data?.message || 'Failed to delete contact',
            type: 'error',
          })
        }
      },
    })
  }

  const handleInviteContact = async (contact: Contact, e: React.MouseEvent) => {
    e.stopPropagation()

    if (!contact.email) {
      openModal(MODAL_NAMES.ALERT, {
        title: 'Email Required',
        message: 'Contact must have an email address to be invited.',
        type: 'warning',
      })
      return
    }

    if (contact.has_portal_access) {
      // Resend invitation
      setInvitingContactId(contact.id)
      try {
        const response = await api.post(`/api/v1/contacts/${contact.id}/resend-invite`)
        if (response.data.success) {
          openModal(MODAL_NAMES.ALERT, {
            title: 'Success',
            message: 'Invitation resent successfully!',
            type: 'success',
          })
        } else {
          openModal(MODAL_NAMES.ALERT, {
            title: 'Error',
            message: response.data.message || 'Failed to resend invitation',
            type: 'error',
          })
        }
      } catch (err: any) {
        console.error('Failed to resend invitation:', err)
        openModal(MODAL_NAMES.ALERT, {
          title: 'Error',
          message: err.response?.data?.message || 'Failed to resend invitation',
          type: 'error',
        })
      } finally {
        setInvitingContactId(null)
      }
      return
    }

    // Send new invitation
    setInvitingContactId(contact.id)
    try {
      const response = await api.post(`/api/v1/contacts/${contact.id}/invite`)
      if (response.data.success) {
        // Update local state to show invited
        setContacts((prev) =>
          prev.map((c) => (c.id === contact.id ? { ...c, has_portal_access: true } : c))
        )
        openModal(MODAL_NAMES.ALERT, {
          title: 'Success',
          message: 'Invitation sent successfully!',
          type: 'success',
        })
      } else {
        openModal(MODAL_NAMES.ALERT, {
          title: 'Error',
          message: response.data.message || 'Failed to send invitation',
          type: 'error',
        })
      }
    } catch (err: any) {
      console.error('Failed to invite contact:', err)
      openModal(MODAL_NAMES.ALERT, {
        title: 'Error',
        message: err.response?.data?.message || 'Failed to send invitation',
        type: 'error',
      })
    } finally {
      setInvitingContactId(null)
    }
  }

  const handleContactClick = (contact: Contact) => {
    navigate(`/crm/contacts/${contact.id}`)
  }

  const getStatusBadge = (relationType: string) => {
    switch (relationType) {
      case 'customer':
        return <span className="badge badge-success badge-sm">Customer</span>
      case 'lead':
        return <span className="badge badge-info badge-sm">Lead</span>
      case 'prospect':
        return <span className="badge badge-warning badge-sm">Prospect</span>
      case 'vendor':
        return <span className="badge badge-secondary badge-sm">Vendor</span>
      case 'partner':
        return <span className="badge badge-accent badge-sm">Partner</span>
      default:
        return <span className="badge badge-ghost badge-sm">{relationType}</span>
    }
  }

  const getInitials = (name: string) => {
    return name
      .split(' ')
      .map((n) => n[0])
      .join('')
      .toUpperCase()
      .slice(0, 2)
  }

  const formatTimeAgo = (dateString: string | null) => {
    if (!dateString) return 'Never'

    const date = new Date(dateString)
    const now = new Date()
    const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000)

    if (diffInSeconds < 60) return 'Just now'
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} min ago`
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hours ago`
    if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)} days ago`
    if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 604800)} weeks ago`

    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
  }

  return (
    <Layout>
      <div className="p-6 space-y-6">
        {/* Page Header */}
        <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-semibold">CRM Dashboard</h1>
            <p className="text-base-content/60 mt-1">Manage your contacts, leads, and customer relationships</p>
          </div>
          <button onClick={handleAddContact} className="btn btn-primary gap-2 w-full lg:w-auto">
            <UserPlus className="w-4 h-4" />
          </button>
        </div>

        {/* Stats Cards */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          {/* Total Contacts */}
          <div className="stat bg-base-200 rounded-box">
            <div className="stat-figure text-primary">
              {isLoadingStats ? (
                <span className="loading loading-spinner loading-md"></span>
              ) : (
                <Users className="w-8 h-8" />
              )}
            </div>
            <div className="stat-title">Total Contacts</div>
            <div className="stat-value text-primary">
              {isLoadingStats ? '-' : stats?.total.toLocaleString() || 0}
            </div>
            <div className="stat-desc">{stats?.active || 0} active</div>
          </div>

          {/* Leads */}
          <div className="stat bg-base-200 rounded-box">
            <div className="stat-figure text-info">
              {isLoadingStats ? (
                <span className="loading loading-spinner loading-md"></span>
              ) : (
                <UserPlus className="w-8 h-8" />
              )}
            </div>
            <div className="stat-title">Leads</div>
            <div className="stat-value text-info">{isLoadingStats ? '-' : stats?.leads || 0}</div>
            <div className="stat-desc">Potential customers</div>
          </div>

          {/* Customers */}
          <div className="stat bg-base-200 rounded-box">
            <div className="stat-figure text-success">
              {isLoadingStats ? (
                <span className="loading loading-spinner loading-md"></span>
              ) : (
                <TrendingUp className="w-8 h-8" />
              )}
            </div>
            <div className="stat-title">Customers</div>
            <div className="stat-value text-success">{isLoadingStats ? '-' : stats?.customers || 0}</div>
            <div className="stat-desc">Active customers</div>
          </div>

          {/* Prospects */}
          <div className="stat bg-base-200 rounded-box">
            <div className="stat-figure text-warning">
              {isLoadingStats ? (
                <span className="loading loading-spinner loading-md"></span>
              ) : (
                <DollarSign className="w-8 h-8" />
              )}
            </div>
            <div className="stat-title">Prospects</div>
            <div className="stat-value text-warning">{isLoadingStats ? '-' : stats?.prospects || 0}</div>
            <div className="stat-desc">Qualified leads</div>
          </div>
        </div>

        {/* Main Content Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Contacts List - Takes 2 columns */}
          <div className="lg:col-span-2 card bg-base-200">
          <div className="card-body">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
              <h2 className="card-title text-lg">
                <Users className="w-5 h-5" />
                Contacts
                {pagination && (
                  <span className="text-sm font-normal text-base-content/60">({pagination.total} total)</span>
                )}
              </h2>
              <div className="flex items-center gap-2">
                {/* Search */}
                <div className="relative flex-1 sm:flex-none">
                  <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-base-content/50" />
                  <input
                    type="text"
                    placeholder="Search contacts..."
                    className="input input-bordered input-sm pl-9 w-full sm:w-64"
                    value={searchQuery}
                    onChange={(e) => setSearchQuery(e.target.value)}
                  />
                </div>
                {/* Filter */}
                <div className="dropdown dropdown-end">
                  <label tabIndex={0} className="btn btn-ghost btn-sm btn-square">
                    <Filter className="w-4 h-4" />
                  </label>
                  <ul tabIndex={0} className="dropdown-content z-[1] menu p-2 shadow-lg bg-base-300 rounded-box w-40">
                    <li>
                      <button
                        className={activeFilter === 'all' ? 'active' : ''}
                        onClick={() => handleFilterChange('all')}
                      >
                        All
                      </button>
                    </li>
                    <li>
                      <button
                        className={activeFilter === 'lead' ? 'active' : ''}
                        onClick={() => handleFilterChange('lead')}
                      >
                        Leads
                      </button>
                    </li>
                    <li>
                      <button
                        className={activeFilter === 'prospect' ? 'active' : ''}
                        onClick={() => handleFilterChange('prospect')}
                      >
                        Prospects
                      </button>
                    </li>
                    <li>
                      <button
                        className={activeFilter === 'customer' ? 'active' : ''}
                        onClick={() => handleFilterChange('customer')}
                      >
                        Customers
                      </button>
                    </li>
                    <li>
                      <button
                        className={activeFilter === 'vendor' ? 'active' : ''}
                        onClick={() => handleFilterChange('vendor')}
                      >
                        Vendors
                      </button>
                    </li>
                    <li>
                      <button
                        className={activeFilter === 'partner' ? 'active' : ''}
                        onClick={() => handleFilterChange('partner')}
                      >
                        Partners
                      </button>
                    </li>
                  </ul>
                </div>
                {/* Refresh */}
                <button onClick={fetchContacts} className="btn btn-ghost btn-sm btn-square" disabled={isLoading}>
                  <RefreshCw className={`w-4 h-4 ${isLoading ? 'animate-spin' : ''}`} />
                </button>
              </div>
            </div>

            {/* Error State */}
            {error && (
              <div className="alert alert-error mb-4">
                <span>{error}</span>
                <button onClick={fetchContacts} className="btn btn-sm btn-ghost">
                  Retry
                </button>
              </div>
            )}

            {/* Loading State */}
            {isLoading ? (
              <div className="flex items-center justify-center py-12">
                <span className="loading loading-spinner loading-lg"></span>
              </div>
            ) : contacts.length === 0 ? (
              /* Empty State */
              <div className="text-center py-12">
                <Users className="w-16 h-16 mx-auto mb-4 text-base-content/30" />
                <h3 className="text-lg font-medium mb-2">No contacts found</h3>
                <p className="text-base-content/60 mb-4">
                  {searchQuery || activeFilter !== 'all'
                    ? 'Try adjusting your search or filter'
                    : 'Get started by adding your first contact'}
                </p>
                {!searchQuery && activeFilter === 'all' && (
                  <button onClick={handleAddContact} className="btn btn-primary gap-2">
                    <UserPlus className="w-4 h-4" />
                  </button>
                )}
              </div>
            ) : (
              /* Contacts Table */
              <>
                <div className="overflow-visible">
                  <table className="table table-sm">
                    <thead>
                      <tr>
                        <th>Contact</th>
                        <th className="hidden md:table-cell">Organization</th>
                        <th className="hidden sm:table-cell">Type</th>
                        <th className="hidden lg:table-cell">Last Activity</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      {contacts.map((contact) => (
                        <tr
                          key={contact.id}
                          className="hover:bg-base-100 cursor-pointer"
                          onClick={() => handleContactClick(contact)}
                        >
                          <td>
                            <div className="flex items-center gap-3">
                              <div className="avatar placeholder">
                                <div className="bg-primary/20 text-primary rounded-full w-10">
                                  <span className="text-sm font-medium">{getInitials(contact.full_name)}</span>
                                </div>
                              </div>
                              <div>
                                <div className="font-medium">{contact.full_name}</div>
                                <div className="text-sm text-base-content/60">
                                  {contact.email || contact.phone || 'No contact info'}
                                </div>
                              </div>
                            </div>
                          </td>
                          <td className="hidden md:table-cell">
                            {contact.organization ? (
                              <div className="flex items-center gap-2">
                                <Building2 className="w-4 h-4 text-base-content/50" />
                                <span>{contact.organization}</span>
                              </div>
                            ) : (
                              <span className="text-base-content/40">—</span>
                            )}
                          </td>
                          <td className="hidden sm:table-cell">
                            <div className="flex items-center gap-2">
                              {getStatusBadge(contact.relation_type)}
                              {contact.has_portal_access && (
                                <span className="tooltip tooltip-left" data-tip="Has portal access">
                                  <CheckCircle className="w-3.5 h-3.5 text-success" />
                                </span>
                              )}
                            </div>
                          </td>
                          <td className="hidden lg:table-cell">
                            <span className="text-sm text-base-content/60">
                              {formatTimeAgo(contact.last_activity_at || contact.created_at)}
                            </span>
                          </td>
                          <td>
                            <div className="flex items-center gap-1">
                              {contact.phone && (
                                <a
                                  href={`tel:${contact.phone}`}
                                  className="btn btn-ghost btn-xs btn-square"
                                  title="Call"
                                  onClick={(e) => e.stopPropagation()}
                                >
                                  <Phone className="w-3.5 h-3.5" />
                                </a>
                              )}
                              {contact.email && (
                                <a
                                  href={`mailto:${contact.email}`}
                                  className="btn btn-ghost btn-xs btn-square"
                                  title="Email"
                                  onClick={(e) => e.stopPropagation()}
                                >
                                  <Mail className="w-3.5 h-3.5" />
                                </a>
                              )}
                              {/* Actions Dropdown */}
                              <div className="dropdown dropdown-end dropdown-left" onClick={(e) => e.stopPropagation()}>
                                <label tabIndex={0} className="btn btn-ghost btn-xs btn-square">
                                  <MoreHorizontal className="w-3.5 h-3.5" />
                                </label>
                                <ul
                                  tabIndex={0}
                                  className="dropdown-content z-50 menu p-2 shadow-lg bg-base-300 rounded-box w-44"
                                >
                                  <li>
                                    <button
                                      onClick={(e) => handleEditContact(contact, e)}
                                      className="flex items-center gap-2"
                                    >
                                      <Pencil className="w-4 h-4" />
                                      Edit
                                    </button>
                                  </li>
                                  {contact.email && (
                                    <li>
                                      <button
                                        onClick={(e) => handleInviteContact(contact, e)}
                                        disabled={invitingContactId === contact.id}
                                        className="flex items-center gap-2"
                                      >
                                        {invitingContactId === contact.id ? (
                                          <Loader2 className="w-4 h-4 animate-spin" />
                                        ) : contact.has_portal_access ? (
                                          <RefreshCw className="w-4 h-4" />
                                        ) : (
                                          <Send className="w-4 h-4" />
                                        )}
                                        {contact.has_portal_access ? 'Resend Invite' : 'Invite to Portal'}
                                      </button>
                                    </li>
                                  )}
                                  <li>
                                    <button
                                      onClick={(e) => handleDeleteContact(contact, e)}
                                      className="flex items-center gap-2 text-error"
                                    >
                                      <Trash2 className="w-4 h-4" />
                                      Delete
                                    </button>
                                  </li>
                                </ul>
                              </div>
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>

                {/* Pagination */}
                {pagination && pagination.last_page > 1 && (
                  <div className="mt-4 flex justify-center">
                    <div className="join">
                      <button className="join-item btn btn-sm" disabled={pagination.current_page === 1}>
                        Previous
                      </button>
                      <button className="join-item btn btn-sm">
                        Page {pagination.current_page} of {pagination.last_page}
                      </button>
                      <button className="join-item btn btn-sm" disabled={!pagination.has_more}>
                        Next
                      </button>
                    </div>
                  </div>
                )}
              </>
            )}
          </div>
        </div>

          {/* Recent Activity Widget */}
          <div className="card bg-base-200">
            <div className="card-body">
              <div className="flex items-center justify-between mb-4">
                <h2 className="card-title text-lg">
                  <Activity className="w-5 h-5" />
                  Recent Activity
                </h2>
                <button
                  onClick={handleAddActivity}
                  className="btn btn-ghost btn-sm btn-circle"
                  title="Add Activity"
                >
                  <Plus className="w-4 h-4" />
                </button>
              </div>

              {isLoadingActivities ? (
                <div className="flex items-center justify-center py-8">
                  <span className="loading loading-spinner loading-md"></span>
                </div>
              ) : activities.length === 0 ? (
                <div className="text-center py-8">
                  <Activity className="w-12 h-12 mx-auto mb-3 text-base-content/30" />
                  <p className="text-base-content/60 text-sm mb-3">No activities yet</p>
                  <button onClick={handleAddActivity} className="btn btn-primary btn-sm gap-2">
                    <Plus className="w-4 h-4" />
                    Add Activity
                  </button>
                </div>
              ) : (
                <div className="space-y-3">
                  {activities.map((activity) => (
                    <div
                      key={activity.id}
                      className="flex gap-3 p-3 bg-base-100 rounded-lg hover:bg-base-100/80 transition-colors cursor-pointer"
                      onClick={() => navigate(`/crm/contacts/${activity.contact.id}`)}
                    >
                      <div
                        className={`flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center ${getActivityTypeColor(
                          activity.type
                        )}`}
                      >
                        {getActivityIcon(activity.type)}
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 mb-1">
                          <span className="font-medium text-sm truncate">{activity.contact.name}</span>
                          <span className="badge badge-ghost badge-xs capitalize">{activity.type}</span>
                        </div>
                        <p className="text-sm text-base-content/70 line-clamp-2">{activity.content}</p>
                        <div className="flex items-center gap-2 mt-1">
                          {activity.created_by && (
                            <span className="text-xs text-base-content/50">{activity.created_by.name}</span>
                          )}
                          <span className="text-xs text-base-content/40">
                            {formatTimeAgo(activity.created_at)}
                          </span>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </Layout>
  )
}
