import { useEffect, useRef, type ReactNode } from 'react'
import { Sparkles, User, AlertCircle, RotateCcw } from 'lucide-react'
import ReactMarkdown, { type Components } from 'react-markdown'
import { useChatStore, type ChatMessage } from '../stores/chat'
import { useChatActions } from '../hooks/useAIStream'

interface ChatMessagesProps {
  onRetry?: (message: string) => void
}

// Custom markdown components for styling
const markdownComponents: Components = {
  // Headers
  h1: ({ children }) => (
    <h1 className="text-xl font-bold mt-4 mb-2 first:mt-0">{children}</h1>
  ),
  h2: ({ children }) => (
    <h2 className="text-lg font-bold mt-3 mb-2 first:mt-0">{children}</h2>
  ),
  h3: ({ children }) => (
    <h3 className="text-base font-semibold mt-3 mb-1 first:mt-0">{children}</h3>
  ),
  h4: ({ children }) => (
    <h4 className="text-sm font-semibold mt-2 mb-1 first:mt-0">{children}</h4>
  ),

  // Paragraphs
  p: ({ children }) => <p className="mb-2 last:mb-0">{children}</p>,

  // Bold and italic
  strong: ({ children }) => <strong className="font-semibold">{children}</strong>,
  em: ({ children }) => <em className="italic">{children}</em>,

  // Lists
  ul: ({ children }) => (
    <ul className="ml-1 mb-2 space-y-1 last:mb-0">{children}</ul>
  ),
  ol: ({ children }) => (
    <ol className="ml-1 mb-2 space-y-1 last:mb-0 counter-reset-item">{children}</ol>
  ),
  li: ({ children, ...props }) => {
    // Determine if this is an ordered list item by checking the parent
    const node = props.node as any
    const isOrdered = node?.parent?.tagName === 'ol' || 
      (node?.position && node?.parent?.ordered === true)
    
    // Get the index for ordered lists
    const siblings = node?.parent?.children?.filter((c: any) => c.type === 'element' && c.tagName === 'li') || []
    const index = siblings.indexOf(node) + 1

    return (
      <li className="flex items-start gap-2">
        <span className="text-primary flex-shrink-0 min-w-[1rem]">
          {isOrdered ? `${index}.` : '•'}
        </span>
        <span className="flex-1">{children}</span>
      </li>
    )
  },

  // Code blocks
  code: ({ className, children, ...props }) => {
    // Check if it's inline code or a code block
    const isInline = !className && !String(children).includes('\n')
    
    if (isInline) {
      return (
        <code className="bg-base-100/50 px-1.5 py-0.5 rounded text-sm font-mono">
          {children}
        </code>
      )
    }

    // Extract language from className (e.g., "language-javascript")
    const match = /language-(\w+)/.exec(className || '')
    const language = match ? match[1] : null

    return (
      <div className="my-2">
        {language && (
          <div className="text-xs text-base-content/50 mb-1">{language}</div>
        )}
        <pre className="p-3 bg-base-100/50 rounded-lg overflow-x-auto text-sm">
          <code className="font-mono">{children}</code>
        </pre>
      </div>
    )
  },

  // Preformatted text wrapper
  pre: ({ children }) => <>{children}</>,

  // Links
  a: ({ href, children }) => (
    <a
      href={href}
      target="_blank"
      rel="noopener noreferrer"
      className="text-primary hover:underline"
    >
      {children}
    </a>
  ),

  // Blockquotes
  blockquote: ({ children }) => (
    <blockquote className="border-l-2 border-primary pl-3 my-2 italic text-base-content/80">
      {children}
    </blockquote>
  ),

  // Horizontal rule
  hr: () => <hr className="my-3 border-base-content/20" />,
}

export default function ChatMessages({ onRetry }: ChatMessagesProps) {
  const { messages, isStreaming, error } = useChatStore()
  const { send } = useChatActions()
  const messagesEndRef = useRef<HTMLDivElement>(null)
  const containerRef = useRef<HTMLDivElement>(null)

  // Auto-scroll to bottom when new messages arrive
  useEffect(() => {
    if (messagesEndRef.current) {
      messagesEndRef.current.scrollIntoView({ behavior: 'smooth' })
    }
  }, [messages])

  const handleRetry = (message: ChatMessage) => {
    // Find the last user message before this AI message
    const messageIndex = messages.findIndex((m) => m.id === message.id)
    if (messageIndex > 0) {
      const userMessage = messages[messageIndex - 1]
      if (userMessage.role === 'user') {
        if (onRetry) {
          onRetry(userMessage.content)
        } else {
          send(userMessage.content)
        }
      }
    }
  }

  const formatTime = (dateStr: string) => {
    const date = new Date(dateStr)
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  }

  // Render content with markdown support
  const renderContent = (content: string, isUserMessage: boolean) => {
    // For user messages, just render plain text
    if (isUserMessage) {
      return <span>{content}</span>
    }

    // For AI messages, render markdown
    return (
      <ReactMarkdown components={markdownComponents}>
        {content}
      </ReactMarkdown>
    )
  }

  if (messages.length === 0) {
    return null
  }

  return (
    <div ref={containerRef} className="flex flex-col gap-4">
      {messages.map((message) => (
        <div
          key={message.id}
          className={`flex gap-3 ${
            message.role === 'user' ? 'flex-row-reverse' : 'flex-row'
          }`}
        >
          {/* Avatar */}
          <div
            className={`flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center ${
              message.role === 'user'
                ? 'bg-primary text-primary-content'
                : 'bg-gradient-to-br from-primary to-accent text-primary-content'
            }`}
          >
            {message.role === 'user' ? (
              <User className="w-4 h-4" />
            ) : (
              <Sparkles className="w-4 h-4" />
            )}
          </div>

          {/* Message bubble */}
          <div
            className={`flex-1 max-w-[85%] ${
              message.role === 'user' ? 'text-right' : 'text-left'
            }`}
          >
            <div
              className={`inline-block px-4 py-3 rounded-2xl ${
                message.role === 'user'
                  ? 'bg-primary text-primary-content rounded-tr-md'
                  : message.error
                  ? 'bg-error/20 text-error rounded-tl-md'
                  : 'bg-base-300 text-base-content rounded-tl-md'
              }`}
            >
              {/* Content */}
              <div className="text-sm break-words">
                {message.error ? (
                  <div className="flex items-center gap-2">
                    <AlertCircle className="w-4 h-4 flex-shrink-0" />
                    <span>{message.content}</span>
                  </div>
                ) : (
                  renderContent(message.content, message.role === 'user')
                )}

                {/* Streaming cursor */}
                {message.isStreaming && (
                  <span className="inline-block w-2 h-4 ml-1 bg-current animate-pulse" />
                )}
              </div>

              {/* Error retry button */}
              {message.error && (
                <button
                  onClick={() => handleRetry(message)}
                  className="mt-2 flex items-center gap-1 text-xs text-error hover:text-error/80 transition-colors"
                >
                  <RotateCcw className="w-3 h-3" />
                  Retry
                </button>
              )}
            </div>

            {/* Timestamp */}
            {!message.isStreaming && (
              <div
                className={`text-xs text-base-content/40 mt-1 ${
                  message.role === 'user' ? 'text-right' : 'text-left'
                }`}
              >
                {formatTime(message.createdAt)}
              </div>
            )}
          </div>
        </div>
      ))}

      {/* Scroll anchor */}
      <div ref={messagesEndRef} />
    </div>
  )
}
