import { useState, useRef, useEffect, ReactNode } from 'react'
import { useNavigate, useLocation } from 'react-router-dom'
import {
  Home,
  Users,
  FileText,
  Calendar,
  Settings,
  ChevronLeft,
  ChevronRight,
  ChevronDown,
  Send,
  Paperclip,
  Image,
  File,
  Mic,
  LogOut,
  UserCircle,
  Menu,
  X,
  CheckSquare,
  MessageCircle,
  Sparkles,
  Zap,
  RotateCcw,
  Square,
  Minimize2,
  Maximize2,
  FileSignature,
  Receipt,
} from 'lucide-react'
import { useAuthStore } from '../stores/auth'
import { useChatStore } from '../stores/chat'
import { useChatActions } from '../hooks/useAIStream'
import AISuggestions from './AISuggestions'
import ChatMessages from './ChatMessages'
import CompanySwitcher from './CompanySwitcher'

// Cookie utility functions
const CHAT_SIDEBAR_COOKIE_NAME = 'ai_chat_sidebar_open'
const WIDGET_MODE_COOKIE_NAME = 'ai_widget_mode'
const WIDGET_POSITION_COOKIE_NAME = 'ai_widget_position'

function getCookie(name: string): string | null {
  const matches = document.cookie.match(new RegExp(
    '(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'
  ))
  return matches ? decodeURIComponent(matches[1]) : null
}

function setCookie(name: string, value: string, days: number = 365) {
  const date = new Date()
  date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000))
  document.cookie = `${name}=${encodeURIComponent(value)}; expires=${date.toUTCString()}; path=/; SameSite=Lax`
}

function getInitialChatSidebarState(): boolean {
  const cookieValue = getCookie(CHAT_SIDEBAR_COOKIE_NAME)
  // If cookie exists, parse it; otherwise default to true (open)
  if (cookieValue !== null) {
    return cookieValue === 'true'
  }
  return true // Default to open for first-time users
}

function getInitialWidgetModeState(): boolean {
  const cookieValue = getCookie(WIDGET_MODE_COOKIE_NAME)
  if (cookieValue !== null) {
    return cookieValue === 'true'
  }
  return false // Default to sidebar mode
}

function getInitialWidgetPosition(): { x: number; y: number } {
  const cookieValue = getCookie(WIDGET_POSITION_COOKIE_NAME)
  if (cookieValue !== null) {
    try {
      return JSON.parse(cookieValue)
    } catch {
      // If parsing fails, return default
    }
  }
  // Default to bottom-right corner
  return { x: window.innerWidth - 384 - 20, y: window.innerHeight - (window.innerHeight * 0.5) - 20 }
}

// Sidebar menu items
const menuItems = [
  { id: 'home', label: 'Home', icon: Home, path: '/dashboard' },
  { id: 'projects', label: 'Projects', icon: CheckSquare, path: '/projects' },
  { id: 'tasks', label: 'My Tasks', icon: Zap, path: '/tasks' },
  { id: 'crm', label: 'CRM', icon: Users, path: '/crm' },
  { 
    id: 'documents', 
    label: 'Documents', 
    icon: FileText, 
    path: '/documents',
    subItems: [
      { id: 'contracts', label: 'Contracts', icon: FileSignature, path: '/documents/contracts' },
      { id: 'invoices', label: 'Invoices', icon: Receipt, path: '/documents/invoices' },
    ]
  },
  { id: 'calendar', label: 'Calendar', icon: Calendar, path: '/calendar' },
  { id: 'settings', label: 'Settings', icon: Settings, path: '/settings' },
]

// Animated placeholder sentences
const placeholderSentences = [
  'Give me the projects list...',
  'Who is working on project A...',
  'How many projects does John have...',
  'Show me overdue projects...',
  'What are the priorities for today...',
]

interface LayoutProps {
  children: ReactNode
}

export default function Layout({ children }: LayoutProps) {
  const navigate = useNavigate()
  const location = useLocation()
  const { user, company, signOut, isLoading } = useAuthStore()
  
  // Check if user is owner of current company
  const isOwner = company?.pivot?.role_in_company === 'owner'
  
  // Chat store and actions
  const { messages, isStreaming } = useChatStore()
  const { send, stop, clear, isOnTemplateBuilder, isGeneratingContract } = useChatActions()
  
  const [sidebarExpanded, setSidebarExpanded] = useState(false)
  const [documentsExpanded, setDocumentsExpanded] = useState(false)
  const [chatMessage, setChatMessage] = useState('')
  const [showAttachMenu, setShowAttachMenu] = useState(false)
  const [showUserMenu, setShowUserMenu] = useState(false)
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)
  const [animatedPlaceholder, setAnimatedPlaceholder] = useState('')
  const [isInputFocused, setIsInputFocused] = useState(false)
  const [chatSidebarOpen, setChatSidebarOpen] = useState(() => getInitialChatSidebarState())

  // Widget mode states
  const [isWidgetMode, setIsWidgetMode] = useState(() => getInitialWidgetModeState())
  const [widgetPosition, setWidgetPosition] = useState(() => getInitialWidgetPosition())
  const [isDragging, setIsDragging] = useState(false)
  const [dragOffset, setDragOffset] = useState({ x: 0, y: 0 })

  // State for AI action feedback
  const [aiActionMessage, setAiActionMessage] = useState<string | null>(null)
  
  // Determine if we should show chat messages or suggestions
  const showChatMessages = messages.length > 0

  // Clear AI action message after delay
  useEffect(() => {
    if (aiActionMessage) {
      const timer = setTimeout(() => setAiActionMessage(null), 3000)
      return () => clearTimeout(timer)
    }
  }, [aiActionMessage])

  // Save chat sidebar state to cookie when it changes
  useEffect(() => {
    setCookie(CHAT_SIDEBAR_COOKIE_NAME, String(chatSidebarOpen))
  }, [chatSidebarOpen])

  // Save widget mode state to cookie when it changes
  useEffect(() => {
    setCookie(WIDGET_MODE_COOKIE_NAME, String(isWidgetMode))
  }, [isWidgetMode])

  // Save widget position to cookie when it changes
  useEffect(() => {
    setCookie(WIDGET_POSITION_COOKIE_NAME, JSON.stringify(widgetPosition))
  }, [widgetPosition])

  const attachMenuRef = useRef<HTMLDivElement>(null)
  const userMenuRef = useRef<HTMLDivElement>(null)
  const textareaRef = useRef<HTMLTextAreaElement>(null)

  // Get active menu from current path
  // Use startsWith to match nested routes (e.g., /crm/contacts/123 should highlight CRM)
  const activeMenu = menuItems.find((item) => 
    item.path === '/dashboard' 
      ? location.pathname === '/dashboard' 
      : location.pathname.startsWith(item.path)
  )?.id || 'home'

  // Get user initials
  const userInitials = user
    ? `${user.first_name?.charAt(0) || ''}${user.last_name?.charAt(0) || ''}`.toUpperCase() || 'U'
    : 'U'

  // Close dropdowns when clicking outside
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (attachMenuRef.current && !attachMenuRef.current.contains(event.target as Node)) {
        setShowAttachMenu(false)
      }
      if (userMenuRef.current && !userMenuRef.current.contains(event.target as Node)) {
        setShowUserMenu(false)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  // Animated typing placeholder effect
  useEffect(() => {
    // Don't animate when input is focused or has content
    if (isInputFocused || chatMessage) {
      return
    }

    let currentSentenceIndex = Math.floor(Math.random() * placeholderSentences.length)
    let currentCharIndex = 0
    let isTyping = true
    let timeoutId: ReturnType<typeof setTimeout>

    const typeNextChar = () => {
      const currentSentence = placeholderSentences[currentSentenceIndex]

      if (isTyping) {
        // Typing phase
        if (currentCharIndex <= currentSentence.length) {
          setAnimatedPlaceholder(currentSentence.slice(0, currentCharIndex))
          currentCharIndex++
          timeoutId = setTimeout(typeNextChar, 50 + Math.random() * 50) // Random typing speed
        } else {
          // Finished typing, pause before erasing
          isTyping = false
          timeoutId = setTimeout(typeNextChar, 2000) // Pause at full sentence
        }
      } else {
        // Erasing phase
        if (currentCharIndex > 0) {
          currentCharIndex--
          setAnimatedPlaceholder(currentSentence.slice(0, currentCharIndex))
          timeoutId = setTimeout(typeNextChar, 30) // Faster erasing
        } else {
          // Finished erasing, pick new random sentence
          isTyping = true
          let newIndex = Math.floor(Math.random() * placeholderSentences.length)
          // Avoid repeating the same sentence
          while (newIndex === currentSentenceIndex && placeholderSentences.length > 1) {
            newIndex = Math.floor(Math.random() * placeholderSentences.length)
          }
          currentSentenceIndex = newIndex
          timeoutId = setTimeout(typeNextChar, 500) // Pause before typing new sentence
        }
      }
    }

    typeNextChar()

    return () => {
      clearTimeout(timeoutId)
    }
  }, [isInputFocused, chatMessage])

  const handleSendMessage = async () => {
    if (chatMessage.trim() && !isStreaming) {
      const message = chatMessage.trim()
      setChatMessage('')
      // Reset textarea height after sending
      if (textareaRef.current) {
        textareaRef.current.style.height = 'auto'
      }
      // Send to AI
      await send(message)
    }
  }

  const handleStopStreaming = () => {
    stop()
  }

  const handleNewChat = () => {
    clear()
  }

  const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault()
      handleSendMessage()
    }
  }

  const handleTextareaChange = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
    setChatMessage(e.target.value)
    // Auto-resize textarea
    const textarea = e.target
    textarea.style.height = 'auto'
    textarea.style.height = `${Math.min(textarea.scrollHeight, 150)}px` // Max height 150px
  }

  const handleMenuClick = (path: string) => {
    navigate(path)
    setMobileMenuOpen(false)
  }

  const handleSignOut = async () => {
    setShowUserMenu(false)
    try {
      await signOut()
      navigate('/login')
    } catch (error) {
      console.error('Sign out failed:', error)
    }
  }

  // Widget drag handlers
  const handleWidgetMouseDown = (e: React.MouseEvent<HTMLDivElement>) => {
    setIsDragging(true)
    setDragOffset({
      x: e.clientX - widgetPosition.x,
      y: e.clientY - widgetPosition.y,
    })
  }

  useEffect(() => {
    const handleMouseMove = (e: MouseEvent) => {
      if (isDragging) {
        setWidgetPosition({
          x: e.clientX - dragOffset.x,
          y: e.clientY - dragOffset.y,
        })
      }
    }

    const handleMouseUp = () => {
      setIsDragging(false)
    }

    if (isDragging) {
      document.addEventListener('mousemove', handleMouseMove)
      document.addEventListener('mouseup', handleMouseUp)
    }

    return () => {
      document.removeEventListener('mousemove', handleMouseMove)
      document.removeEventListener('mouseup', handleMouseUp)
    }
  }, [isDragging, dragOffset])

  const handleToggleWidgetMode = () => {
    if (!isWidgetMode) {
      // Switching to widget mode - calculate initial position
      setWidgetPosition({
        x: window.innerWidth - 384 - 20, // 384px is w-96, 20px padding
        y: window.innerHeight - (window.innerHeight * 0.5) - 20,
      })
    }
    setIsWidgetMode(!isWidgetMode)
  }

  return (
    <div className="fixed inset-0 bg-base-100 flex">
      {/* Sidebar - Desktop */}
      <aside
        className={`hidden lg:flex flex-col bg-base-200 transition-all duration-300 ease-in-out ${
          sidebarExpanded ? 'w-64' : 'w-20'
        }`}
        style={{
          margin: '10px 0 10px 10px',
          borderRadius: '24px',
          height: 'calc(100% - 20px)',
        }}
      >
        {/* Logo */}
        <div className="p-4 flex items-center justify-center border-b border-base-300">
          {sidebarExpanded ? (
            <img
              src="/brand/logo-white.webp"
              alt="Beetasky"
              className="h-8 w-auto"
            />
          ) : (
            <img
              src="/brand/logo-icon.png"
              alt="Beetasky"
              className="h-8 w-8"
            />
          )}
        </div>

        {/* Menu Items */}
        <nav className="flex-1 py-4 px-2 space-y-1 overflow-y-auto">
          {menuItems.map((item) => {
            const Icon = item.icon
            const isActive = activeMenu === item.id
            const hasSubItems = item.subItems && item.subItems.length > 0
            const isExpanded = item.id === 'documents' && documentsExpanded
            
            return (
              <div key={item.id}>
                <button
                  onClick={() => {
                    if (hasSubItems && sidebarExpanded) {
                      setDocumentsExpanded(!documentsExpanded)
                    } else {
                      handleMenuClick(item.path)
                    }
                  }}
                  className={`w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 ${
                    isActive
                      ? 'bg-primary text-primary-content'
                      : 'hover:bg-base-300 text-base-content/70 hover:text-base-content'
                  } ${!sidebarExpanded ? 'justify-center' : ''}`}
                >
                  <Icon className="w-5 h-5 flex-shrink-0" />
                  {sidebarExpanded && (
                    <>
                      <span className="font-medium truncate flex-1 text-left">{item.label}</span>
                      {hasSubItems && (
                        <ChevronDown
                          className={`w-4 h-4 transition-transform ${
                            isExpanded ? 'rotate-180' : ''
                          }`}
                        />
                      )}
                    </>
                  )}
                </button>
                
                {/* Sub-items */}
                {hasSubItems && sidebarExpanded && isExpanded && (
                  <div className="ml-4 mt-1 space-y-1">
                    {item.subItems.map((subItem) => {
                      const SubIcon = subItem.icon
                      const isSubActive = location.pathname === subItem.path
                      return (
                        <button
                          key={subItem.id}
                          onClick={() => handleMenuClick(subItem.path)}
                          className={`w-full flex items-center gap-3 px-4 py-2 rounded-xl transition-all duration-200 text-sm ${
                            isSubActive
                              ? 'bg-primary/20 text-primary font-medium'
                              : 'hover:bg-base-300 text-base-content/60 hover:text-base-content'
                          }`}
                        >
                          <SubIcon className="w-4 h-4 flex-shrink-0" />
                          <span className="truncate">{subItem.label}</span>
                        </button>
                      )
                    })}
                  </div>
                )}
              </div>
            )
          })}
        </nav>

        {/* Toggle Button */}
        <div className="p-4 border-t border-base-300">
          <button
            onClick={() => setSidebarExpanded(!sidebarExpanded)}
            className="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-xl hover:bg-base-300 transition-colors text-base-content/70 hover:text-base-content"
          >
            {sidebarExpanded ? (
              <>
                <ChevronLeft className="w-5 h-5" />
                <span className="font-medium">Collapse</span>
              </>
            ) : (
              <ChevronRight className="w-5 h-5" />
            )}
          </button>
        </div>
      </aside>

      {/* Mobile Sidebar Overlay */}
      {mobileMenuOpen && (
        <div
          className="lg:hidden fixed inset-0 bg-black/50 z-40"
          onClick={() => setMobileMenuOpen(false)}
        />
      )}

      {/* Mobile Sidebar */}
      <aside
        className={`lg:hidden fixed top-0 left-0 h-full w-64 bg-base-200 z-50 transform transition-transform duration-300 ease-in-out ${
          mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
        style={{
          borderRadius: '0 24px 24px 0',
        }}
      >
        {/* Close Button */}
        <div className="p-4 flex items-center justify-between border-b border-base-300">
          <img
            src="/brand/logo-white.webp"
            alt="Beetasky"
            className="h-8 w-auto"
          />
          <button
            onClick={() => setMobileMenuOpen(false)}
            className="p-2 rounded-lg hover:bg-base-300 transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Menu Items */}
        <nav className="py-4 px-2 space-y-1">
          {menuItems.map((item) => {
            const Icon = item.icon
            const isActive = activeMenu === item.id
            const hasSubItems = item.subItems && item.subItems.length > 0
            const isExpanded = item.id === 'documents' && documentsExpanded
            
            return (
              <div key={item.id}>
                <button
                  onClick={() => {
                    if (hasSubItems) {
                      setDocumentsExpanded(!documentsExpanded)
                    } else {
                      handleMenuClick(item.path)
                    }
                  }}
                  className={`w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-200 ${
                    isActive
                      ? 'bg-primary text-primary-content'
                      : 'hover:bg-base-300 text-base-content/70 hover:text-base-content'
                  }`}
                >
                  <Icon className="w-5 h-5 flex-shrink-0" />
                  <span className="font-medium flex-1 text-left">{item.label}</span>
                  {hasSubItems && (
                    <ChevronDown
                      className={`w-4 h-4 transition-transform ${
                        isExpanded ? 'rotate-180' : ''
                      }`}
                    />
                  )}
                </button>
                
                {/* Sub-items */}
                {hasSubItems && isExpanded && (
                  <div className="ml-4 mt-1 space-y-1">
                    {item.subItems.map((subItem) => {
                      const SubIcon = subItem.icon
                      const isSubActive = location.pathname === subItem.path
                      return (
                        <button
                          key={subItem.id}
                          onClick={() => handleMenuClick(subItem.path)}
                          className={`w-full flex items-center gap-3 px-4 py-2 rounded-xl transition-all duration-200 text-sm ${
                            isSubActive
                              ? 'bg-primary/20 text-primary font-medium'
                              : 'hover:bg-base-300 text-base-content/60 hover:text-base-content'
                          }`}
                        >
                          <SubIcon className="w-4 h-4 flex-shrink-0" />
                          <span className="truncate">{subItem.label}</span>
                        </button>
                      )
                    })}
                  </div>
                )}
              </div>
            )
          })}
        </nav>
      </aside>

      {/* Main Content Area */}
      <div className="flex-1 flex flex-col min-w-0 relative">
        {/* Top Bar */}
        <header className="h-16 flex items-center justify-between px-4 lg:px-6">
          {/* Mobile Menu Button */}
          <button
            onClick={() => setMobileMenuOpen(true)}
            className="lg:hidden p-2 rounded-lg hover:bg-base-200 transition-colors"
          >
            <Menu className="w-6 h-6" />
          </button>

          {/* Company Switcher */}
          <div className="hidden lg:block">
            <CompanySwitcher />
          </div>

          {/* Company Switcher for mobile */}
          <div className="lg:hidden flex-1 flex justify-center">
            <CompanySwitcher />
          </div>

          {/* Right Side Actions */}
          <div className="flex items-center gap-3">
            {/* AI Chat Toggle Button - Only show when chat is closed */}
            {!chatSidebarOpen && (
              <button
                onClick={() => setChatSidebarOpen(true)}
                className="w-10 h-10 rounded-full bg-gradient-to-br from-primary to-accent flex items-center justify-center text-primary-content hover:ring-2 hover:ring-primary/50 transition-all"
                title="Open AI Assistant"
              >
                <Sparkles className="w-5 h-5" />
              </button>
            )}

            {/* User Avatar Dropdown */}
            <div className="relative" ref={userMenuRef}>
              <button
                onClick={() => setShowUserMenu(!showUserMenu)}
                className="w-10 h-10 rounded-full bg-gradient-to-br from-primary to-secondary flex items-center justify-center text-primary-content font-semibold hover:ring-2 hover:ring-primary/50 transition-all overflow-hidden"
              >
                {user?.avatar_url ? (
                  <img src={user.avatar_url} alt={user.full_name} className="w-full h-full object-cover" />
                ) : (
                  userInitials
                )}
              </button>

              {/* User Dropdown Menu */}
              {showUserMenu && (
                <div className="absolute right-0 top-12 w-48 bg-base-200 rounded-xl shadow-xl border border-base-300 py-2 z-50 animate-in fade-in slide-in-from-top-2 duration-200">
                  <div className="px-4 py-2 border-b border-base-300">
                    <p className="font-medium text-base-content">{user?.full_name || 'User'}</p>
                    <p className="text-sm text-base-content/60 truncate">{user?.email}</p>
                  </div>
                  <button
                    onClick={() => {
                      setShowUserMenu(false)
                      navigate('/account')
                    }}
                    className="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-base-300 transition-colors text-base-content/80 hover:text-base-content"
                  >
                    <UserCircle className="w-4 h-4" />
                    <span>My Account</span>
                  </button>
                  <button
                    onClick={() => {
                      setShowUserMenu(false)
                      navigate('/settings')
                    }}
                    className="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-base-300 transition-colors text-base-content/80 hover:text-base-content"
                  >
                    <Settings className="w-4 h-4" />
                    <span>Settings</span>
                  </button>
                  <button
                    onClick={handleSignOut}
                    disabled={isLoading}
                    className="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-base-300 transition-colors text-error"
                  >
                    {isLoading ? (
                      <span className="loading loading-spinner loading-xs"></span>
                    ) : (
                      <LogOut className="w-4 h-4" />
                    )}
                    <span>Logout</span>
                  </button>
                </div>
              )}
            </div>
          </div>
        </header>

        {/* Main Content */}
        <main className="flex-1 overflow-y-auto px-4 lg:px-6 pb-6">
          {children}
        </main>
      </div>

      {/* AI Chat Sidebar - Right Side */}
      <aside
        className={`hidden lg:flex flex-col bg-base-200 transition-all duration-300 ease-in-out ${
          chatSidebarOpen && !isWidgetMode ? 'w-96' : 'w-0 opacity-0 overflow-hidden'
        }`}
        style={{
          margin: '10px 10px 10px 0',
          borderRadius: '24px',
          height: 'calc(100% - 20px)',
        }}
      >
        {chatSidebarOpen && !isWidgetMode && (
          <>
            {/* Chat Header */}
            <div className="p-4 flex items-center justify-between border-b border-base-300">
              <div className="flex items-center gap-3">
                <div className="w-8 h-8 rounded-full bg-gradient-to-br from-primary to-accent flex items-center justify-center">
                  <Sparkles className="w-4 h-4 text-primary-content" />
                </div>
                <div>
                  <h3 className="font-semibold text-base-content">AI Assistant</h3>
                  <p className="text-xs text-base-content/60">
                    {isStreaming ? 'Thinking...' : 'Always ready to help'}
                  </p>
                </div>
              </div>
              <div className="flex items-center gap-1">
                {/* New Chat Button - only show when there are messages */}
                {showChatMessages && (
                  <button
                    onClick={handleNewChat}
                    className="p-2 rounded-lg hover:bg-base-300 transition-colors text-base-content/70 hover:text-base-content"
                    title="New Chat"
                  >
                    <RotateCcw className="w-4 h-4" />
                  </button>
                )}
                {/* AI Skills Settings Button - only show for owners */}
                {isOwner && (
                  <button
                    onClick={() => navigate('/skills')}
                    className="p-2 rounded-lg hover:bg-base-300 transition-colors text-base-content/70 hover:text-base-content"
                    title="AI Skills Settings"
                  >
                    <Settings className="w-4 h-4" />
                  </button>
                )}
                {/* Widget Mode Toggle Button */}
                <button
                  onClick={handleToggleWidgetMode}
                  className="p-2 rounded-lg hover:bg-base-300 transition-colors text-base-content/70 hover:text-base-content"
                  title={isWidgetMode ? "Dock to sidebar" : "Pop out to widget"}
                >
                  {isWidgetMode ? (
                    <Maximize2 className="w-4 h-4" />
                  ) : (
                    <Minimize2 className="w-4 h-4" />
                  )}
                </button>
                <button
                  onClick={() => setChatSidebarOpen(false)}
                  className="p-2 rounded-lg hover:bg-base-300 transition-colors text-base-content/70 hover:text-base-content"
                  title="Close AI Assistant"
                >
                  <X className="w-5 h-5" />
                </button>
              </div>
            </div>

            {/* Chat Messages or AI Suggestions Area */}
            <div className="flex-1 overflow-y-auto p-4">
              {/* Action feedback toast */}
              {aiActionMessage && (
                <div className="mb-4 p-3 bg-success/20 border border-success/30 rounded-xl">
                  <p className="text-sm text-success">{aiActionMessage}</p>
                </div>
              )}

              {/* Contract Generation Hint - shows on template builder page */}
              {isOnTemplateBuilder && !showChatMessages && (
                <div className="bg-secondary/10 border border-secondary/30 rounded-lg p-3 mx-3 mb-3">
                  <div className="flex items-start gap-2">
                    <Sparkles className="w-5 h-5 text-secondary flex-shrink-0 mt-0.5" />
                    <div>
                      <p className="text-sm font-medium text-secondary">Contract Generation Mode</p>
                      <p className="text-xs text-base-content/70 mt-1">
                        Type "Generate a contract for..." to create contract sections automatically.
                      </p>
                    </div>
                  </div>
                </div>
              )}

              {/* Show generating indicator */}
              {isGeneratingContract && (
                <div className="flex items-center justify-center p-4">
                  <span className="loading loading-spinner loading-md text-secondary"></span>
                  <span className="ml-2 text-sm text-base-content/70">Generating contract...</span>
                </div>
              )}

              {/* Show Chat Messages or AI Suggestions */}
              {showChatMessages ? (
                <ChatMessages />
              ) : (
                <AISuggestions onActionComplete={setAiActionMessage} />
              )}
            </div>

            {/* Chat Input Area */}
            <div className="p-4 border-t border-base-300">
              <div className="bg-base-300 rounded-2xl p-2 flex items-end gap-2">
                {/* Attach Button with Dropup */}
                <div className="relative" ref={attachMenuRef}>
                  <button
                    onClick={() => setShowAttachMenu(!showAttachMenu)}
                    className={`p-2 rounded-full transition-colors ${
                      showAttachMenu
                        ? 'bg-primary text-primary-content'
                        : 'hover:bg-base-200 text-base-content/70 hover:text-base-content'
                    }`}
                  >
                    <Paperclip className="w-4 h-4" />
                  </button>

                  {/* Attach Dropup Menu */}
                  {showAttachMenu && (
                    <div className="absolute bottom-12 left-0 w-48 bg-base-200 rounded-xl shadow-xl border border-base-300 py-2 z-50 animate-in fade-in slide-in-from-bottom-2 duration-200">
                      <button className="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-base-300 transition-colors text-base-content/80 hover:text-base-content">
                        <Image className="w-4 h-4" />
                        <span>Upload Image</span>
                      </button>
                      <button className="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-base-300 transition-colors text-base-content/80 hover:text-base-content">
                        <File className="w-4 h-4" />
                        <span>Attach File</span>
                      </button>
                      <button className="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-base-300 transition-colors text-base-content/80 hover:text-base-content">
                        <Mic className="w-4 h-4" />
                        <span>Voice Message</span>
                      </button>
                    </div>
                  )}
                </div>

                {/* Chat Input */}
                <textarea
                  ref={textareaRef}
                  placeholder={animatedPlaceholder}
                  className="flex-1 bg-transparent border-none outline-none focus:outline-none focus:ring-0 focus:ring-offset-0 focus:border-none focus-visible:ring-0 focus-visible:ring-offset-0 focus-visible:outline-none shadow-none text-base-content placeholder:text-base-content/40 text-sm resize-none overflow-y-auto py-2"
                  value={chatMessage}
                  onChange={handleTextareaChange}
                  onKeyDown={handleKeyDown}
                  onFocus={() => setIsInputFocused(true)}
                  onBlur={() => setIsInputFocused(false)}
                  rows={1}
                  style={{ maxHeight: '120px' }}
                />

                {/* Send or Stop Button */}
                {isStreaming ? (
                  <button
                    onClick={handleStopStreaming}
                    className="p-2 rounded-full flex items-center justify-center transition-all bg-error text-error-content hover:bg-error/90"
                    title="Stop generating"
                  >
                    <Square className="w-4 h-4" />
                  </button>
                ) : (
                  <button
                    onClick={handleSendMessage}
                    disabled={!chatMessage.trim()}
                    className={`p-2 rounded-full flex items-center justify-center transition-all ${
                      chatMessage.trim()
                        ? 'bg-primary text-primary-content hover:bg-primary/90'
                        : 'text-base-content/40 cursor-not-allowed'
                    }`}
                  >
                    <Send className="w-4 h-4" />
                  </button>
                )}
              </div>
              
              {/* Keyboard hint */}
              <p className="text-xs text-base-content/40 mt-2 text-center">
                {isStreaming ? 'AI is responding...' : 'Press Enter to send, Shift + Enter for new line'}
              </p>
            </div>
          </>
        )}
      </aside>

      {/* AI Chat Widget - Floating Mode */}
      {chatSidebarOpen && isWidgetMode && (
        <div
          className="hidden lg:flex flex-col bg-base-200 shadow-2xl z-50"
          style={{
            position: 'fixed',
            left: `${widgetPosition.x}px`,
            top: `${widgetPosition.y}px`,
            width: '384px',
            height: '50vh',
            borderRadius: '24px',
            pointerEvents: 'auto',
          }}
        >
          {/* Chat Header - Draggable */}
          <div
            className={`p-4 flex items-center justify-between border-b border-base-300 ${
              isDragging ? 'cursor-grabbing' : 'cursor-grab'
            }`}
            onMouseDown={handleWidgetMouseDown}
          >
            <div className="flex items-center gap-3">
              <div className="w-8 h-8 rounded-full bg-gradient-to-br from-primary to-accent flex items-center justify-center">
                <Sparkles className="w-4 h-4 text-primary-content" />
              </div>
              <div>
                <h3 className="font-semibold text-base-content">AI Assistant</h3>
                <p className="text-xs text-base-content/60">
                  {isStreaming ? 'Thinking...' : 'Always ready to help'}
                </p>
              </div>
            </div>
            <div className="flex items-center gap-1">
              {/* New Chat Button - only show when there are messages */}
              {showChatMessages && (
                <button
                  onClick={handleNewChat}
                  className="p-2 rounded-lg hover:bg-base-300 transition-colors text-base-content/70 hover:text-base-content"
                  title="New Chat"
                  onMouseDown={(e) => e.stopPropagation()}
                >
                  <RotateCcw className="w-4 h-4" />
                </button>
              )}
              {/* AI Skills Settings Button - only show for owners */}
              {isOwner && (
                <button
                  onClick={() => navigate('/skills')}
                  className="p-2 rounded-lg hover:bg-base-300 transition-colors text-base-content/70 hover:text-base-content"
                  title="AI Skills Settings"
                  onMouseDown={(e) => e.stopPropagation()}
                >
                  <Settings className="w-4 h-4" />
                </button>
              )}
              {/* Widget Mode Toggle Button */}
              <button
                onClick={handleToggleWidgetMode}
                className="p-2 rounded-lg hover:bg-base-300 transition-colors text-base-content/70 hover:text-base-content"
                title="Dock to sidebar"
                onMouseDown={(e) => e.stopPropagation()}
              >
                <Maximize2 className="w-4 h-4" />
              </button>
              <button
                onClick={() => setChatSidebarOpen(false)}
                className="p-2 rounded-lg hover:bg-base-300 transition-colors text-base-content/70 hover:text-base-content"
                title="Close AI Assistant"
                onMouseDown={(e) => e.stopPropagation()}
              >
                <X className="w-5 h-5" />
              </button>
            </div>
          </div>

          {/* Chat Messages or AI Suggestions Area */}
          <div className="flex-1 overflow-y-auto p-4">
            {/* Action feedback toast */}
            {aiActionMessage && (
              <div className="mb-4 p-3 bg-success/20 border border-success/30 rounded-xl">
                <p className="text-sm text-success">{aiActionMessage}</p>
              </div>
            )}

            {/* Contract Generation Hint */}
            {isOnTemplateBuilder && !showChatMessages && (
              <div className="bg-secondary/10 border border-secondary/30 rounded-lg p-3 mb-3">
                <div className="flex items-start gap-2">
                  <Sparkles className="w-5 h-5 text-secondary flex-shrink-0 mt-0.5" />
                  <div>
                    <p className="text-sm font-medium text-secondary">Contract Generation Mode</p>
                    <p className="text-xs text-base-content/70 mt-1">
                      Type "Generate a contract for..." to create contract sections.
                    </p>
                  </div>
                </div>
              </div>
            )}

            {/* Show generating indicator */}
            {isGeneratingContract && (
              <div className="flex items-center justify-center p-4">
                <span className="loading loading-spinner loading-md text-secondary"></span>
                <span className="ml-2 text-sm text-base-content/70">Generating contract...</span>
              </div>
            )}

            {/* Show Chat Messages or AI Suggestions */}
            {showChatMessages ? (
              <ChatMessages />
            ) : (
              <AISuggestions onActionComplete={setAiActionMessage} />
            )}
          </div>

          {/* Chat Input Area */}
          <div className="p-4 border-t border-base-300">
            <div className="bg-base-300 rounded-2xl p-2 flex items-end gap-2">
              {/* Attach Button with Dropup */}
              <div className="relative" ref={attachMenuRef}>
                <button
                  onClick={() => setShowAttachMenu(!showAttachMenu)}
                  className={`p-2 rounded-full transition-colors ${
                    showAttachMenu
                      ? 'bg-primary text-primary-content'
                      : 'hover:bg-base-200 text-base-content/70 hover:text-base-content'
                  }`}
                >
                  <Paperclip className="w-4 h-4" />
                </button>

                {/* Attach Dropup Menu */}
                {showAttachMenu && (
                  <div className="absolute bottom-12 left-0 w-48 bg-base-200 rounded-xl shadow-xl border border-base-300 py-2 z-50 animate-in fade-in slide-in-from-bottom-2 duration-200">
                    <button className="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-base-300 transition-colors text-base-content/80 hover:text-base-content">
                      <Image className="w-4 h-4" />
                      <span>Upload Image</span>
                    </button>
                    <button className="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-base-300 transition-colors text-base-content/80 hover:text-base-content">
                      <File className="w-4 h-4" />
                      <span>Attach File</span>
                    </button>
                    <button className="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-base-300 transition-colors text-base-content/80 hover:text-base-content">
                      <Mic className="w-4 h-4" />
                      <span>Voice Message</span>
                    </button>
                  </div>
                )}
              </div>

              {/* Chat Input */}
              <textarea
                ref={textareaRef}
                placeholder={animatedPlaceholder}
                className="flex-1 bg-transparent border-none outline-none focus:outline-none focus:ring-0 focus:ring-offset-0 focus:border-none focus-visible:ring-0 focus-visible:ring-offset-0 focus-visible:outline-none shadow-none text-base-content placeholder:text-base-content/40 text-sm resize-none overflow-y-auto py-2"
                value={chatMessage}
                onChange={handleTextareaChange}
                onKeyDown={handleKeyDown}
                onFocus={() => setIsInputFocused(true)}
                onBlur={() => setIsInputFocused(false)}
                rows={1}
                style={{ maxHeight: '120px' }}
              />

              {/* Send or Stop Button */}
              {isStreaming ? (
                <button
                  onClick={handleStopStreaming}
                  className="p-2 rounded-full flex items-center justify-center transition-all bg-error text-error-content hover:bg-error/90"
                  title="Stop generating"
                >
                  <Square className="w-4 h-4" />
                </button>
              ) : (
                <button
                  onClick={handleSendMessage}
                  disabled={!chatMessage.trim()}
                  className={`p-2 rounded-full flex items-center justify-center transition-all ${
                    chatMessage.trim()
                      ? 'bg-primary text-primary-content hover:bg-primary/90'
                      : 'text-base-content/40 cursor-not-allowed'
                  }`}
                >
                  <Send className="w-4 h-4" />
                </button>
              )}
            </div>
            
            {/* Keyboard hint */}
            <p className="text-xs text-base-content/40 mt-2 text-center">
              {isStreaming ? 'AI is responding...' : 'Press Enter to send, Shift + Enter for new line'}
            </p>
          </div>
        </div>
      )}

      {/* Mobile Chat Panel - Slide from bottom */}
      {chatSidebarOpen && (
        <>
          {/* Mobile Overlay */}
          <div
            className="lg:hidden fixed inset-0 bg-black/50 z-40"
            onClick={() => setChatSidebarOpen(false)}
          />
          
          {/* Mobile Chat Panel */}
          <div
            className="lg:hidden fixed bottom-0 left-0 right-0 bg-base-200 z-50 flex flex-col animate-in slide-in-from-bottom duration-300"
            style={{
              borderRadius: '24px 24px 0 0',
              height: '85vh',
              maxHeight: '85vh',
            }}
          >
            {/* Mobile Chat Header */}
            <div className="p-4 flex items-center justify-between border-b border-base-300">
              <div className="flex items-center gap-3">
                <div className="w-8 h-8 rounded-full bg-gradient-to-br from-primary to-accent flex items-center justify-center">
                  <Sparkles className="w-4 h-4 text-primary-content" />
                </div>
                <div>
                  <h3 className="font-semibold text-base-content">AI Assistant</h3>
                  <p className="text-xs text-base-content/60">
                    {isStreaming ? 'Thinking...' : 'Always ready to help'}
                  </p>
                </div>
              </div>
              <div className="flex items-center gap-1">
                {showChatMessages && (
                  <button
                    onClick={handleNewChat}
                    className="p-2 rounded-lg hover:bg-base-300 transition-colors text-base-content/70 hover:text-base-content"
                    title="New Chat"
                  >
                    <RotateCcw className="w-4 h-4" />
                  </button>
                )}
                {/* AI Skills Settings Button - only show for owners */}
                {isOwner && (
                  <button
                    onClick={() => {
                      setChatSidebarOpen(false)
                      navigate('/skills')
                    }}
                    className="p-2 rounded-lg hover:bg-base-300 transition-colors text-base-content/70 hover:text-base-content"
                    title="AI Skills Settings"
                  >
                    <Settings className="w-4 h-4" />
                  </button>
                )}
                <button
                  onClick={() => setChatSidebarOpen(false)}
                  className="p-2 rounded-lg hover:bg-base-300 transition-colors text-base-content/70 hover:text-base-content"
                >
                  <X className="w-5 h-5" />
                </button>
              </div>
            </div>

            {/* Mobile Chat Messages or AI Suggestions Area */}
            <div className="flex-1 overflow-y-auto p-4">
              {/* Action feedback toast */}
              {aiActionMessage && (
                <div className="mb-4 p-3 bg-success/20 border border-success/30 rounded-xl">
                  <p className="text-sm text-success">{aiActionMessage}</p>
                </div>
              )}

              {/* Show Chat Messages or AI Suggestions */}
              {showChatMessages ? (
                <ChatMessages />
              ) : (
                <AISuggestions onActionComplete={setAiActionMessage} />
              )}
            </div>

            {/* Mobile Chat Input Area */}
            <div className="p-4 border-t border-base-300">
              <div className="bg-base-300 rounded-2xl p-2 flex items-end gap-2">
                {/* Attach Button */}
                <button
                  onClick={() => setShowAttachMenu(!showAttachMenu)}
                  className={`p-2 rounded-full transition-colors ${
                    showAttachMenu
                      ? 'bg-primary text-primary-content'
                      : 'hover:bg-base-200 text-base-content/70 hover:text-base-content'
                  }`}
                >
                  <Paperclip className="w-4 h-4" />
                </button>

                {/* Chat Input */}
                <textarea
                  placeholder={animatedPlaceholder}
                  className="flex-1 bg-transparent border-none outline-none focus:outline-none focus:ring-0 text-base-content placeholder:text-base-content/40 text-sm resize-none overflow-y-auto py-2"
                  value={chatMessage}
                  onChange={handleTextareaChange}
                  onKeyDown={handleKeyDown}
                  onFocus={() => setIsInputFocused(true)}
                  onBlur={() => setIsInputFocused(false)}
                  rows={1}
                  style={{ maxHeight: '120px' }}
                />

                {/* Send or Stop Button */}
                {isStreaming ? (
                  <button
                    onClick={handleStopStreaming}
                    className="p-2 rounded-full flex items-center justify-center transition-all bg-error text-error-content hover:bg-error/90"
                    title="Stop generating"
                  >
                    <Square className="w-4 h-4" />
                  </button>
                ) : (
                  <button
                    onClick={handleSendMessage}
                    disabled={!chatMessage.trim()}
                    className={`p-2 rounded-full flex items-center justify-center transition-all ${
                      chatMessage.trim()
                        ? 'bg-primary text-primary-content hover:bg-primary/90'
                        : 'text-base-content/40 cursor-not-allowed'
                    }`}
                  >
                    <Send className="w-4 h-4" />
                  </button>
                )}
              </div>
            </div>
          </div>
        </>
      )}

      {/* Mobile Chat Toggle Button - Bottom Right */}
      {!chatSidebarOpen && (
        <button
          onClick={() => setChatSidebarOpen(true)}
          className="lg:hidden fixed bottom-6 right-6 w-14 h-14 rounded-full bg-gradient-to-br from-primary to-accent flex items-center justify-center text-primary-content shadow-lg hover:shadow-xl hover:scale-105 transition-all z-50"
          title="Open AI Assistant"
        >
          <MessageCircle className="w-6 h-6" />
        </button>
      )}
    </div>
  )
}
