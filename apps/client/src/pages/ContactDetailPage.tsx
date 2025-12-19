import { useState, useEffect, useCallback, useRef } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import {
  ArrowLeft,
  Mail,
  Phone,
  Building2,
  Briefcase,
  Calendar,
  Clock,
  MessageSquare,
  FileText,
  FolderOpen,
  Plus,
  Send,
  Pencil,
  Trash2,
  MoreHorizontal,
  User,
  Tag,
  Pin,
  PinOff,
  X,
  Loader2,
  UserPlus,
  CheckCircle,
  RefreshCw,
  Link2,
  Unlink,
  Activity,
  CheckSquare,
} from 'lucide-react'
import Layout from '../components/Layout'
import { api } from '../lib/api'
import { useModalStore, MODAL_NAMES } from '../stores/modal'

// Types
interface ContactDetail {
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
  updated_at: string
  address: any
  custom_fields: any
  tags: string[]
  metadata: any
  has_portal_access?: boolean
}

interface Note {
  id: string
  content: string
  is_pinned: boolean
  created_by: {
    id: string
    name: string
    avatar: string | null
  } | null
  created_at: string
  updated_at: string
}

interface Project {
  id: string
  name: string
  status: string
  tasks_count: number
  completion_percentage: number
}

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

type TabType = 'overview' | 'notes' | 'messages' | 'projects'

export default function ContactDetailPage() {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const { openModal } = useModalStore()

  const [contact, setContact] = useState<ContactDetail | null>(null)
  const [notes, setNotes] = useState<Note[]>([])
  const [projects, setProjects] = useState<Project[]>([])
  const [activeTab, setActiveTab] = useState<TabType>('overview')
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  // Note form
  const [newNote, setNewNote] = useState('')
  const [isAddingNote, setIsAddingNote] = useState(false)
  const [isLoadingNotes, setIsLoadingNotes] = useState(false)
  const [hasLoadedNotes, setHasLoadedNotes] = useState(false)
  const [isDeletingNote, setIsDeletingNote] = useState<string | null>(null)

  // Projects
  const [isLoadingProjects, setIsLoadingProjects] = useState(false)
  const [hasLoadedProjects, setHasLoadedProjects] = useState(false)
  const [isUnlinkingProject, setIsUnlinkingProject] = useState<string | null>(null)

  // Activities
  const [activities, setActivities] = useState<ActivityItem[]>([])
  const [isLoadingActivities, setIsLoadingActivities] = useState(false)

  // Tags
  const [isAddingTag, setIsAddingTag] = useState(false)
  const [newTag, setNewTag] = useState('')
  const [isUpdatingTags, setIsUpdatingTags] = useState(false)
  const tagInputRef = useRef<HTMLInputElement>(null)

  // Portal Invitation
  const [isInviting, setIsInviting] = useState(false)

  // Fetch contact details
  const fetchContact = useCallback(async () => {
    if (!id) return

    setIsLoading(true)
    setError(null)

    try {
      const response = await api.get(`/api/v1/contacts/${id}`)

      if (response.data.success) {
        setContact(response.data.data)
      } else {
        setError(response.data.message || 'Failed to load contact')
      }
    } catch (err: any) {
      console.error('Failed to fetch contact:', err)
      if (err.response?.status === 404) {
        setError('Contact not found')
      } else {
        setError(err.response?.data?.message || 'Failed to load contact')
      }
    } finally {
      setIsLoading(false)
    }
  }, [id])

  // Fetch notes
  const fetchNotes = useCallback(async () => {
    if (!id) return

    setIsLoadingNotes(true)

    try {
      const response = await api.get(`/api/v1/contacts/${id}/notes`)

      if (response.data.success) {
        setNotes(response.data.data)
      }
    } catch (err: any) {
      console.error('Failed to fetch notes:', err)
    } finally {
      setIsLoadingNotes(false)
      setHasLoadedNotes(true)
    }
  }, [id])

  // Fetch projects
  const fetchProjects = useCallback(async () => {
    if (!id) return

    setIsLoadingProjects(true)

    try {
      const response = await api.get(`/api/v1/contacts/${id}/projects`)

      if (response.data.success) {
        setProjects(response.data.data || [])
      }
    } catch (err: any) {
      console.error('Failed to fetch projects:', err)
    } finally {
      setIsLoadingProjects(false)
      setHasLoadedProjects(true)
    }
  }, [id])

  // Fetch activities for this contact
  const fetchActivities = useCallback(async () => {
    if (!id) return

    setIsLoadingActivities(true)

    try {
      // Fetch notes as activities for now
      const response = await api.get(`/api/v1/contacts/${id}/notes`)

      if (response.data.success) {
        // Transform notes into activity format
        const activityData: ActivityItem[] = response.data.data.map((note: Note) => ({
          id: note.id,
          type: 'note' as const,
          content: note.content,
          is_pinned: note.is_pinned,
          contact: {
            id: id,
            name: '',
            email: null,
          },
          created_by: note.created_by,
          created_at: note.created_at,
        }))
        setActivities(activityData.slice(0, 5)) // Show only last 5
      }
    } catch (err: any) {
      console.error('Failed to fetch activities:', err)
    } finally {
      setIsLoadingActivities(false)
    }
  }, [id])

  useEffect(() => {
    fetchContact()
  }, [fetchContact])

  useEffect(() => {
    fetchActivities()
  }, [fetchActivities])

  useEffect(() => {
    if (activeTab === 'notes' && !hasLoadedNotes && !isLoadingNotes) {
      fetchNotes()
    }
  }, [activeTab, fetchNotes, hasLoadedNotes, isLoadingNotes])

  useEffect(() => {
    if (activeTab === 'projects' && !hasLoadedProjects && !isLoadingProjects) {
      fetchProjects()
    }
  }, [activeTab, fetchProjects, hasLoadedProjects, isLoadingProjects])

  const handleEdit = () => {
    if (!contact) return
    openModal(MODAL_NAMES.EDIT_CONTACT, {
      contact,
      onSuccess: () => {
        fetchContact()
      },
    })
  }

  const handleAddActivity = () => {
    if (!contact) return
    openModal(MODAL_NAMES.ADD_ACTIVITY, {
      preSelectedContactId: contact.id,
      onSuccess: () => {
        fetchActivities()
        fetchNotes() // Also refresh notes tab
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

  const handleDelete = async () => {
    if (!contact) return

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
            navigate('/crm')
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

  const handleAddNote = async () => {
    if (!newNote.trim() || !contact) return

    setIsAddingNote(true)

    try {
      const response = await api.post(`/api/v1/contacts/${contact.id}/notes`, {
        content: newNote.trim(),
      })

      if (response.data.success) {
        setNotes((prev) => [response.data.data, ...prev])
        setNewNote('')
      }
    } catch (err: any) {
      console.error('Failed to add note:', err)
      openModal(MODAL_NAMES.ALERT, {
        title: 'Error',
        message: err.response?.data?.message || 'Failed to add note',
        type: 'error',
      })
    } finally {
      setIsAddingNote(false)
    }
  }

  const handleDeleteNote = async (noteId: string) => {
    if (!contact) return

    openModal(MODAL_NAMES.ALERT, {
      title: 'Delete Note',
      message: 'Are you sure you want to delete this note?',
      type: 'warning',
      confirmText: 'Delete',
      showCancel: true,
      onConfirm: async () => {
        setIsDeletingNote(noteId)
        try {
          const response = await api.delete(`/api/v1/contacts/${contact.id}/notes/${noteId}`)
          if (response.data.success) {
            setNotes((prev) => prev.filter((n) => n.id !== noteId))
          }
        } catch (err: any) {
          console.error('Failed to delete note:', err)
          openModal(MODAL_NAMES.ALERT, {
            title: 'Error',
            message: err.response?.data?.message || 'Failed to delete note',
            type: 'error',
          })
        } finally {
          setIsDeletingNote(null)
        }
      },
    })
  }

  const handleTogglePinNote = async (noteId: string, currentPinned: boolean) => {
    if (!contact) return

    try {
      await api.put(`/api/v1/contacts/${contact.id}/notes/${noteId}`, {
        is_pinned: !currentPinned,
      })

      setNotes((prev) =>
        prev.map((n) => (n.id === noteId ? { ...n, is_pinned: !currentPinned } : n))
      )
    } catch (err: any) {
      console.error('Failed to update note:', err)
    }
  }

  const handleAddTag = async () => {
    if (!newTag.trim() || !contact) return

    const tagToAdd = newTag.trim().toLowerCase()

    // Check if tag already exists
    if (contact.tags?.includes(tagToAdd)) {
      setNewTag('')
      setIsAddingTag(false)
      return
    }

    setIsUpdatingTags(true)

    try {
      const updatedTags = [...(contact.tags || []), tagToAdd]
      const response = await api.put(`/api/v1/contacts/${contact.id}`, {
        tags: updatedTags,
      })

      if (response.data.success) {
        setContact((prev) => (prev ? { ...prev, tags: updatedTags } : prev))
        setNewTag('')
        setIsAddingTag(false)
      }
    } catch (err: any) {
      console.error('Failed to add tag:', err)
      openModal(MODAL_NAMES.ALERT, {
        title: 'Error',
        message: err.response?.data?.message || 'Failed to add tag',
        type: 'error',
      })
    } finally {
      setIsUpdatingTags(false)
    }
  }

  const handleRemoveTag = async (tagToRemove: string) => {
    if (!contact) return

    setIsUpdatingTags(true)

    try {
      const updatedTags = (contact.tags || []).filter((t) => t !== tagToRemove)
      const response = await api.put(`/api/v1/contacts/${contact.id}`, {
        tags: updatedTags,
      })

      if (response.data.success) {
        setContact((prev) => (prev ? { ...prev, tags: updatedTags } : prev))
      }
    } catch (err: any) {
      console.error('Failed to remove tag:', err)
      openModal(MODAL_NAMES.ALERT, {
        title: 'Error',
        message: err.response?.data?.message || 'Failed to remove tag',
        type: 'error',
      })
    } finally {
      setIsUpdatingTags(false)
    }
  }

  const handleTagInputKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter') {
      e.preventDefault()
      handleAddTag()
    } else if (e.key === 'Escape') {
      setNewTag('')
      setIsAddingTag(false)
    }
  }

  const handleInviteToPortal = async () => {
    if (!contact) return

    if (!contact.email) {
      openModal(MODAL_NAMES.ALERT, {
        title: 'Email Required',
        message: 'Contact must have an email address to be invited. Please add an email address first.',
        type: 'warning',
        confirmText: 'Add Email',
        showCancel: true,
        onConfirm: () => {
          handleEdit()
        },
      })
      return
    }

    setIsInviting(true)

    try {
      const response = await api.post(`/api/v1/contacts/${contact.id}/invite`)

      if (response.data.success) {
        setContact((prev) => (prev ? { ...prev, has_portal_access: true } : prev))
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
      setIsInviting(false)
    }
  }

  const handleResendInvite = async () => {
    if (!contact) return

    setIsInviting(true)

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
      setIsInviting(false)
    }
  }

  const handleLinkProject = () => {
    if (!contact) return

    // Check if contact has email
    if (!contact.email) {
      openModal(MODAL_NAMES.ALERT, {
        title: 'Email Required',
        message: 'This contact needs an email address before they can be assigned to projects. Please add an email address first.',
        type: 'warning',
        confirmText: 'Add Email',
        showCancel: true,
        onConfirm: () => {
          handleEdit()
        },
      })
      return
    }

    // Show link project modal
    openModal(MODAL_NAMES.LINK_PROJECT, {
      contactId: contact.id,
      contactName: contact.full_name,
      onSuccess: () => {
        fetchProjects()
      },
    })
  }

  const handleUnlinkProject = async (projectId: string, projectName: string) => {
    if (!contact) return

    openModal(MODAL_NAMES.ALERT, {
      title: 'Unlink Project',
      message: `Are you sure you want to remove ${contact.full_name} from "${projectName}"?`,
      type: 'warning',
      confirmText: 'Unlink',
      showCancel: true,
      onConfirm: async () => {
        setIsUnlinkingProject(projectId)
        try {
          await api.delete(`/api/v1/contacts/${contact.id}/projects/${projectId}`)
          setProjects((prev) => prev.filter((p) => p.id !== projectId))
        } catch (err: any) {
          console.error('Failed to unlink project:', err)
          openModal(MODAL_NAMES.ALERT, {
            title: 'Error',
            message: err.response?.data?.message || 'Failed to unlink project',
            type: 'error',
          })
        } finally {
          setIsUnlinkingProject(null)
        }
      },
    })
  }

  const getInitials = (name: string) => {
    return name
      .split(' ')
      .map((n) => n[0])
      .join('')
      .toUpperCase()
      .slice(0, 2)
  }

  const formatDate = (dateString: string | null) => {
    if (!dateString) return 'Never'
    const date = new Date(dateString)
    return date.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    })
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

    return formatDate(dateString)
  }

  const getStatusBadge = (relationType: string) => {
    switch (relationType) {
      case 'customer':
        return <span className="badge badge-success">Customer</span>
      case 'lead':
        return <span className="badge badge-info">Lead</span>
      case 'prospect':
        return <span className="badge badge-warning">Prospect</span>
      case 'vendor':
        return <span className="badge badge-secondary">Vendor</span>
      case 'partner':
        return <span className="badge badge-accent">Partner</span>
      default:
        return <span className="badge badge-ghost">{relationType}</span>
    }
  }

  const getContactStatusBadge = (status: string) => {
    switch (status) {
      case 'active':
        return <span className="badge badge-success badge-sm">Active</span>
      case 'inactive':
        return <span className="badge badge-ghost badge-sm">Inactive</span>
      case 'converted':
        return <span className="badge badge-info badge-sm">Converted</span>
      case 'lost':
        return <span className="badge badge-error badge-sm">Lost</span>
      default:
        return <span className="badge badge-ghost badge-sm">{status}</span>
    }
  }

  if (isLoading) {
    return (
      <Layout>
        <div className="flex items-center justify-center h-96">
          <span className="loading loading-spinner loading-lg"></span>
        </div>
      </Layout>
    )
  }

  if (error || !contact) {
    return (
      <Layout>
        <div className="p-6">
          <button onClick={() => navigate('/crm')} className="btn btn-ghost gap-2 mb-6">
            <ArrowLeft className="w-4 h-4" />
            Back to CRM
          </button>
          <div className="text-center py-12">
            <User className="w-16 h-16 mx-auto mb-4 text-base-content/30" />
            <h3 className="text-lg font-medium mb-2">{error || 'Contact not found'}</h3>
            <button onClick={() => navigate('/crm')} className="btn btn-primary mt-4">
              Go to CRM
            </button>
          </div>
        </div>
      </Layout>
    )
  }

  return (
    <Layout>
      <div className="p-6 space-y-6">
        {/* Back Button & Actions */}
        <div className="flex items-center justify-between">
          <button onClick={() => navigate('/crm')} className="btn btn-ghost gap-2">
            <ArrowLeft className="w-4 h-4" />
            Back to CRM
          </button>
          <div className="flex items-center gap-2">
            <button onClick={handleEdit} className="btn btn-ghost btn-sm gap-2">
              <Pencil className="w-4 h-4" />
              Edit
            </button>
            <div className="dropdown dropdown-end">
              <label tabIndex={0} className="btn btn-ghost btn-sm btn-square">
                <MoreHorizontal className="w-4 h-4" />
              </label>
              <ul tabIndex={0} className="dropdown-content z-[1] menu p-2 shadow-lg bg-base-300 rounded-box w-40">
                <li>
                  <button onClick={handleDelete} className="text-error">
                    <Trash2 className="w-4 h-4" />
                    Delete Contact
                  </button>
                </li>
              </ul>
            </div>
          </div>
        </div>

        {/* Contact Header */}
        <div className="card bg-base-200">
          <div className="card-body">
            <div className="flex flex-col md:flex-row md:items-start gap-6">
              {/* Avatar */}
              <div className="avatar placeholder">
                <div className="bg-primary/20 text-primary rounded-full w-24 h-24">
                  <span className="text-3xl font-semibold">{getInitials(contact.full_name)}</span>
                </div>
              </div>

              {/* Info */}
              <div className="flex-1">
                <div className="flex flex-wrap items-center gap-3 mb-2">
                  <h1 className="text-2xl font-semibold">{contact.full_name}</h1>
                  {getStatusBadge(contact.relation_type)}
                  {getContactStatusBadge(contact.status)}
                </div>

                {contact.job_title && (
                  <p className="text-base-content/60 mb-4">
                    {contact.job_title}
                    {contact.organization && ` at ${contact.organization}`}
                  </p>
                )}

                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-4">
                  {contact.email && (
                    <a
                      href={`mailto:${contact.email}`}
                      className="flex items-center gap-2 text-sm hover:text-primary transition-colors"
                    >
                      <Mail className="w-4 h-4 text-base-content/50" />
                      <span className="truncate">{contact.email}</span>
                    </a>
                  )}
                  {contact.phone && (
                    <a
                      href={`tel:${contact.phone}`}
                      className="flex items-center gap-2 text-sm hover:text-primary transition-colors"
                    >
                      <Phone className="w-4 h-4 text-base-content/50" />
                      <span>{contact.phone}</span>
                    </a>
                  )}
                  {contact.organization && (
                    <div className="flex items-center gap-2 text-sm">
                      <Building2 className="w-4 h-4 text-base-content/50" />
                      <span>{contact.organization}</span>
                    </div>
                  )}
                  <div className="flex items-center gap-2 text-sm text-base-content/60">
                    <Calendar className="w-4 h-4" />
                    <span>Added {formatDate(contact.first_seen_at)}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Tabs */}
        <div className="tabs tabs-boxed bg-base-200 p-1">
          <button
            className={`tab gap-2 ${activeTab === 'overview' ? 'tab-active' : ''}`}
            onClick={() => setActiveTab('overview')}
          >
            <User className="w-4 h-4" />
            Overview
          </button>
          <button
            className={`tab gap-2 ${activeTab === 'notes' ? 'tab-active' : ''}`}
            onClick={() => setActiveTab('notes')}
          >
            <FileText className="w-4 h-4" />
            Notes
            {notes.length > 0 && <span className="badge badge-sm">{notes.length}</span>}
          </button>
          <button
            className={`tab gap-2 ${activeTab === 'messages' ? 'tab-active' : ''}`}
            onClick={() => setActiveTab('messages')}
          >
            <MessageSquare className="w-4 h-4" />
            Messages
          </button>
          <button
            className={`tab gap-2 ${activeTab === 'projects' ? 'tab-active' : ''}`}
            onClick={() => setActiveTab('projects')}
          >
            <FolderOpen className="w-4 h-4" />
            Projects
            {projects.length > 0 && <span className="badge badge-sm">{projects.length}</span>}
          </button>
        </div>

        {/* Tab Content */}
        <div className="min-h-[400px]">
          {/* Overview Tab */}
          {activeTab === 'overview' && (
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              {/* Contact Details */}
              <div className="lg:col-span-2 space-y-6">
                <div className="card bg-base-200">
                  <div className="card-body">
                    <h3 className="card-title text-lg mb-4">Contact Details</h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <div>
                        <label className="text-sm text-base-content/60">Full Name</label>
                        <p className="font-medium">{contact.full_name}</p>
                      </div>
                      <div>
                        <label className="text-sm text-base-content/60">Email</label>
                        <p className="font-medium">{contact.email || '—'}</p>
                      </div>
                      <div>
                        <label className="text-sm text-base-content/60">Phone</label>
                        <p className="font-medium">{contact.phone || '—'}</p>
                      </div>
                      <div>
                        <label className="text-sm text-base-content/60">Organization</label>
                        <p className="font-medium">{contact.organization || '—'}</p>
                      </div>
                      <div>
                        <label className="text-sm text-base-content/60">Job Title</label>
                        <p className="font-medium">{contact.job_title || '—'}</p>
                      </div>
                      <div>
                        <label className="text-sm text-base-content/60">Source</label>
                        <p className="font-medium capitalize">{contact.source || '—'}</p>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Recent Activity */}
                <div className="card bg-base-200">
                  <div className="card-body">
                    <div className="flex items-center justify-between mb-4">
                      <h3 className="card-title text-lg">
                        <Activity className="w-5 h-5" />
                        Recent Activity
                      </h3>
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
                      <div className="text-center py-8 text-base-content/50">
                        <Activity className="w-12 h-12 mx-auto mb-2 opacity-50" />
                        <p className="mb-3">No activity recorded yet</p>
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
                            className="flex gap-3 p-3 bg-base-100 rounded-lg"
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
                        {notes.length > 5 && (
                          <button
                            onClick={() => setActiveTab('notes')}
                            className="btn btn-ghost btn-sm w-full"
                          >
                            View all {notes.length} notes
                          </button>
                        )}
                      </div>
                    )}
                  </div>
                </div>
              </div>

              {/* Sidebar */}
              <div className="space-y-6">
                {/* Quick Stats */}
                <div className="card bg-base-200">
                  <div className="card-body">
                    <h3 className="card-title text-lg mb-4">Quick Info</h3>
                    <div className="space-y-3">
                      <div className="flex justify-between">
                        <span className="text-base-content/60">Type</span>
                        {getStatusBadge(contact.relation_type)}
                      </div>
                      <div className="flex justify-between">
                        <span className="text-base-content/60">Status</span>
                        {getContactStatusBadge(contact.status)}
                      </div>
                      <div className="flex justify-between">
                        <span className="text-base-content/60">First Contact</span>
                        <span className="text-sm">{formatDate(contact.first_seen_at)}</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-base-content/60">Last Activity</span>
                        <span className="text-sm">{formatTimeAgo(contact.last_activity_at)}</span>
                      </div>
                      {contact.assigned_to && (
                        <div className="flex justify-between items-center">
                          <span className="text-base-content/60">Assigned To</span>
                          <div className="flex items-center gap-2">
                            <div className="avatar placeholder">
                              <div className="bg-primary/20 text-primary rounded-full w-6">
                                <span className="text-xs">{getInitials(contact.assigned_to.name)}</span>
                              </div>
                            </div>
                            <span className="text-sm">{contact.assigned_to.name}</span>
                          </div>
                        </div>
                      )}
                    </div>

                    {/* Portal Access */}
                    <div className="divider my-3"></div>
                    <div className="space-y-3">
                      <div className="flex justify-between items-center">
                        <span className="text-base-content/60">Portal Access</span>
                        {contact.has_portal_access ? (
                          <span className="badge badge-success badge-sm gap-1">
                            <CheckCircle className="w-3 h-3" />
                            Invited
                          </span>
                        ) : (
                          <span className="badge badge-ghost badge-sm">Not invited</span>
                        )}
                      </div>

                      {contact.email ? (
                        contact.has_portal_access ? (
                          <button
                            onClick={handleResendInvite}
                            disabled={isInviting}
                            className="btn btn-ghost btn-sm w-full gap-2"
                          >
                            {isInviting ? (
                              <Loader2 className="w-4 h-4 animate-spin" />
                            ) : (
                              <RefreshCw className="w-4 h-4" />
                            )}
                            Resend Invitation
                          </button>
                        ) : (
                          <button
                            onClick={handleInviteToPortal}
                            disabled={isInviting}
                            className="btn btn-primary btn-sm w-full gap-2"
                          >
                            {isInviting ? (
                              <Loader2 className="w-4 h-4 animate-spin" />
                            ) : (
                              <UserPlus className="w-4 h-4" />
                            )}
                            Invite to Portal
                          </button>
                        )
                      ) : (
                        <p className="text-xs text-base-content/50 text-center">
                          Add an email address to invite this contact
                        </p>
                      )}
                    </div>
                  </div>
                </div>

                {/* Tags */}
                <div className="card bg-base-200">
                  <div className="card-body">
                    <div className="flex items-center justify-between mb-4">
                      <h3 className="card-title text-lg">
                        <Tag className="w-5 h-5" />
                        Tags
                      </h3>
                      {!isAddingTag && (
                        <button
                          onClick={() => {
                            setIsAddingTag(true)
                            setTimeout(() => tagInputRef.current?.focus(), 100)
                          }}
                          className="btn btn-ghost btn-xs btn-square"
                          disabled={isUpdatingTags}
                        >
                          <Plus className="w-4 h-4" />
                        </button>
                      )}
                    </div>

                    {/* Tag Input */}
                    {isAddingTag && (
                      <div className="flex gap-2 mb-3">
                        <input
                          ref={tagInputRef}
                          type="text"
                          value={newTag}
                          onChange={(e) => setNewTag(e.target.value)}
                          onKeyDown={handleTagInputKeyDown}
                          placeholder="Enter tag name..."
                          className="input input-bordered input-sm flex-1"
                          disabled={isUpdatingTags}
                          maxLength={50}
                        />
                        <button
                          onClick={handleAddTag}
                          disabled={!newTag.trim() || isUpdatingTags}
                          className="btn btn-primary btn-sm btn-square"
                        >
                          {isUpdatingTags ? (
                            <Loader2 className="w-4 h-4 animate-spin" />
                          ) : (
                            <Plus className="w-4 h-4" />
                          )}
                        </button>
                        <button
                          onClick={() => {
                            setNewTag('')
                            setIsAddingTag(false)
                          }}
                          className="btn btn-ghost btn-sm btn-square"
                          disabled={isUpdatingTags}
                        >
                          <X className="w-4 h-4" />
                        </button>
                      </div>
                    )}

                    {/* Tags Display */}
                    {contact.tags && contact.tags.length > 0 ? (
                      <div className="flex flex-wrap gap-2">
                        {contact.tags.map((tag, index) => (
                          <span
                            key={index}
                            className="badge badge-outline gap-1 pr-1 group hover:badge-primary transition-colors"
                          >
                            {tag}
                            <button
                              onClick={() => handleRemoveTag(tag)}
                              className="btn btn-ghost btn-xs btn-circle opacity-50 group-hover:opacity-100 transition-opacity -mr-1"
                              disabled={isUpdatingTags}
                            >
                              <X className="w-3 h-3" />
                            </button>
                          </span>
                        ))}
                      </div>
                    ) : (
                      !isAddingTag && (
                        <p className="text-sm text-base-content/50">No tags added</p>
                      )
                    )}
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* Notes Tab */}
          {activeTab === 'notes' && (
            <div className="space-y-4">
              {/* Add Note */}
              <div className="card bg-base-200">
                <div className="card-body">
                  <h3 className="card-title text-lg mb-4">Add Note</h3>
                  <div className="flex gap-3">
                    <textarea
                      value={newNote}
                      onChange={(e) => setNewNote(e.target.value)}
                      placeholder="Write a note about this contact..."
                      className="textarea textarea-bordered flex-1 min-h-[100px]"
                      disabled={isAddingNote}
                    />
                  </div>
                  <div className="flex justify-end mt-3">
                    <button
                      onClick={handleAddNote}
                      disabled={!newNote.trim() || isAddingNote}
                      className="btn btn-primary gap-2"
                    >
                      {isAddingNote ? (
                        <span className="loading loading-spinner loading-sm"></span>
                      ) : (
                        <Send className="w-4 h-4" />
                      )}
                      Add Note
                    </button>
                  </div>
                </div>
              </div>

              {/* Notes List */}
              {isLoadingNotes ? (
                <div className="card bg-base-200">
                  <div className="card-body text-center py-12">
                    <span className="loading loading-spinner loading-lg"></span>
                    <p className="text-base-content/60 mt-4">Loading notes...</p>
                  </div>
                </div>
              ) : notes.length === 0 ? (
                <div className="card bg-base-200">
                  <div className="card-body text-center py-12">
                    <FileText className="w-16 h-16 mx-auto mb-4 text-base-content/30" />
                    <h3 className="text-lg font-medium mb-2">No notes yet</h3>
                    <p className="text-base-content/60">Add your first note about this contact</p>
                  </div>
                </div>
              ) : (
                <div className="space-y-3">
                  {/* Sort by pinned first, then by created_at */}
                  {[...notes]
                    .sort((a, b) => {
                      if (a.is_pinned && !b.is_pinned) return -1
                      if (!a.is_pinned && b.is_pinned) return 1
                      return new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
                    })
                    .map((note) => (
                      <div
                        key={note.id}
                        className={`card bg-base-200 ${note.is_pinned ? 'border border-primary/30' : ''}`}
                      >
                        <div className="card-body">
                          <div className="flex items-start justify-between gap-4">
                            <div className="flex items-start gap-3 flex-1">
                              <div className="avatar placeholder">
                                <div className="bg-primary/20 text-primary rounded-full w-8">
                                  <span className="text-xs">
                                    {note.created_by ? getInitials(note.created_by.name) : '?'}
                                  </span>
                                </div>
                              </div>
                              <div className="flex-1">
                                <div className="flex items-center gap-2 flex-wrap">
                                  <span className="font-medium text-sm">
                                    {note.created_by?.name || 'Unknown'}
                                  </span>
                                  <span className="text-xs text-base-content/50">
                                    {formatTimeAgo(note.created_at)}
                                  </span>
                                  {note.is_pinned && (
                                    <span className="badge badge-primary badge-xs gap-1">
                                      <Pin className="w-2.5 h-2.5" />
                                      Pinned
                                    </span>
                                  )}
                                </div>
                                <p className="mt-1 text-base-content/80 whitespace-pre-wrap">
                                  {note.content}
                                </p>
                              </div>
                            </div>
                            <div className="flex items-center gap-1">
                              <button
                                onClick={() => handleTogglePinNote(note.id, note.is_pinned)}
                                className="btn btn-ghost btn-xs btn-square"
                                title={note.is_pinned ? 'Unpin note' : 'Pin note'}
                              >
                                {note.is_pinned ? (
                                  <PinOff className="w-3.5 h-3.5" />
                                ) : (
                                  <Pin className="w-3.5 h-3.5" />
                                )}
                              </button>
                              <button
                                onClick={() => handleDeleteNote(note.id)}
                                className="btn btn-ghost btn-xs btn-square text-error"
                                disabled={isDeletingNote === note.id}
                                title="Delete note"
                              >
                                {isDeletingNote === note.id ? (
                                  <span className="loading loading-spinner loading-xs"></span>
                                ) : (
                                  <Trash2 className="w-3.5 h-3.5" />
                                )}
                              </button>
                            </div>
                          </div>
                        </div>
                      </div>
                    ))}
                </div>
              )}
            </div>
          )}

          {/* Messages Tab */}
          {activeTab === 'messages' && (
            <div className="card bg-base-200">
              <div className="card-body text-center py-12">
                <MessageSquare className="w-16 h-16 mx-auto mb-4 text-base-content/30" />
                <h3 className="text-lg font-medium mb-2">Messages coming soon</h3>
                <p className="text-base-content/60">
                  Chat with this contact directly from here. This feature is under development.
                </p>
              </div>
            </div>
          )}

          {/* Projects Tab */}
          {activeTab === 'projects' && (
            <div className="card bg-base-200">
              <div className="card-body">
                <div className="flex items-center justify-between mb-4">
                  <h3 className="card-title text-lg">
                    <FolderOpen className="w-5 h-5" />
                    Assigned Projects
                  </h3>
                  <button 
                    onClick={handleLinkProject}
                    className="btn btn-primary btn-sm gap-2"
                  >
                    <Link2 className="w-4 h-4" />
                    Link Project
                  </button>
                </div>

                {isLoadingProjects ? (
                  <div className="flex flex-col items-center justify-center py-12">
                    <Loader2 className="w-8 h-8 animate-spin text-primary mb-4" />
                    <p className="text-base-content/60">Loading projects...</p>
                  </div>
                ) : projects.length === 0 ? (
                  <div className="text-center py-12">
                    <FolderOpen className="w-16 h-16 mx-auto mb-4 text-base-content/30" />
                    <h3 className="text-lg font-medium mb-2">No projects linked</h3>
                    <p className="text-base-content/60 mb-4">
                      Link projects to track work related to this contact
                    </p>
                    <button
                      onClick={handleLinkProject}
                      className="btn btn-primary gap-2"
                    >
                      <Link2 className="w-4 h-4" />
                      Link Project
                    </button>
                  </div>
                ) : (
                  <div className="space-y-3">
                    {projects.map((project) => (
                      <div
                        key={project.id}
                        className="flex items-center justify-between p-4 bg-base-100 rounded-xl hover:bg-base-300/50 transition-colors group"
                      >
                        <div className="flex-1 min-w-0">
                          <p className="font-medium truncate">{project.name}</p>
                          <p className="text-sm text-base-content/60">
                            {project.tasks_count} task{project.tasks_count !== 1 ? 's' : ''} • {project.completion_percentage}% complete
                          </p>
                        </div>
                        <div className="flex items-center gap-2">
                          <span
                            className={`badge badge-sm ${
                              project.status === 'active'
                                ? 'badge-success'
                                : project.status === 'completed'
                                  ? 'badge-info'
                                  : project.status === 'on_hold'
                                    ? 'badge-warning'
                                    : 'badge-ghost'
                            }`}
                          >
                            {project.status}
                          </span>
                          <button
                            onClick={() => handleUnlinkProject(project.id, project.name)}
                            disabled={isUnlinkingProject === project.id}
                            className="btn btn-ghost btn-xs btn-square opacity-0 group-hover:opacity-100 transition-opacity text-error"
                            title="Unlink project"
                          >
                            {isUnlinkingProject === project.id ? (
                              <Loader2 className="w-3.5 h-3.5 animate-spin" />
                            ) : (
                              <Unlink className="w-3.5 h-3.5" />
                            )}
                          </button>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      </div>
    </Layout>
  )
}

