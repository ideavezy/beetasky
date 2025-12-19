import { useEffect } from 'react'
import { X, AlertTriangle, CheckCircle, Info, XCircle } from 'lucide-react'

export type AlertType = 'info' | 'success' | 'warning' | 'error'

export interface AlertModalProps {
  title: string
  message: string
  type?: AlertType
  confirmText?: string
  cancelText?: string
  showCancel?: boolean
  onConfirm?: () => void
  onClose: () => void
}

const iconMap = {
  info: Info,
  success: CheckCircle,
  warning: AlertTriangle,
  error: XCircle,
}

const colorMap = {
  info: 'text-info bg-info/20',
  success: 'text-success bg-success/20',
  warning: 'text-warning bg-warning/20',
  error: 'text-error bg-error/20',
}

const buttonColorMap = {
  info: 'btn-info',
  success: 'btn-success',
  warning: 'btn-warning',
  error: 'btn-error',
}

export default function AlertModal({
  title,
  message,
  type = 'info',
  confirmText = 'OK',
  cancelText = 'Cancel',
  showCancel = false,
  onConfirm,
  onClose,
}: AlertModalProps) {
  const Icon = iconMap[type]

  // Handle escape key
  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        onClose()
      }
    }
    document.addEventListener('keydown', handleEscape)
    return () => document.removeEventListener('keydown', handleEscape)
  }, [onClose])

  const handleConfirm = () => {
    if (onConfirm) {
      onConfirm()
    }
    onClose()
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center">
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-black/60 backdrop-blur-sm"
        onClick={onClose}
      />

      {/* Modal */}
      <div className="relative bg-base-200 rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        {/* Close button */}
        <button
          onClick={onClose}
          className="absolute top-4 right-4 p-2 rounded-lg hover:bg-base-300 transition-colors text-base-content/60 hover:text-base-content"
        >
          <X className="w-5 h-5" />
        </button>

        {/* Content */}
        <div className="p-6 text-center">
          {/* Icon */}
          <div className={`w-16 h-16 rounded-full ${colorMap[type]} flex items-center justify-center mx-auto mb-4`}>
            <Icon className="w-8 h-8" />
          </div>

          {/* Title */}
          <h2 className="text-xl font-semibold text-base-content mb-2">
            {title}
          </h2>

          {/* Message */}
          <p className="text-base-content/70 mb-6 whitespace-pre-wrap">
            {message}
          </p>

          {/* Actions */}
          <div className="flex gap-3 justify-center">
            {showCancel && (
              <button
                onClick={onClose}
                className="btn btn-ghost min-w-[100px]"
              >
                {cancelText}
              </button>
            )}
            <button
              onClick={handleConfirm}
              className={`btn ${buttonColorMap[type]} min-w-[100px]`}
              autoFocus
            >
              {confirmText}
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}

