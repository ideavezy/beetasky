import { Download, Trash2, FileText, FileSpreadsheet, Archive, File as FileIcon, Image as ImageIcon } from 'lucide-react'

export interface Attachment {
  id: string
  filename: string
  mime_type: string
  size: number
  path: string
  public_url?: string
}

interface AttachmentListProps {
  attachments: Attachment[]
  onDelete?: (attachmentId: string) => void
  canDelete?: boolean
  compact?: boolean
}

export default function AttachmentList({
  attachments,
  onDelete,
  canDelete = false,
  compact = false,
}: AttachmentListProps) {
  if (!attachments || attachments.length === 0) {
    return null
  }

  const getFileIcon = (mimeType: string) => {
    if (mimeType.startsWith('image/')) {
      return <ImageIcon className="w-5 h-5" />
    }
    if (mimeType === 'application/pdf') {
      return <FileText className="w-5 h-5 text-error" />
    }
    if (
      mimeType.includes('spreadsheet') ||
      mimeType.includes('excel')
    ) {
      return <FileSpreadsheet className="w-5 h-5 text-success" />
    }
    if (
      mimeType.includes('word') ||
      mimeType.includes('document')
    ) {
      return <FileText className="w-5 h-5 text-info" />
    }
    if (
      mimeType.includes('zip') ||
      mimeType.includes('rar') ||
      mimeType.includes('7z')
    ) {
      return <Archive className="w-5 h-5 text-warning" />
    }
    return <FileIcon className="w-5 h-5" />
  }

  const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
    return (bytes / 1048576).toFixed(1) + ' MB'
  }

  const isImage = (mimeType: string) => mimeType.startsWith('image/')

  const handleDownload = (attachment: Attachment) => {
    const url = attachment.public_url || attachment.path
    window.open(url, '_blank')
  }

  if (compact) {
    // Compact horizontal list
    return (
      <div className="flex flex-wrap gap-2">
        {attachments.map((attachment) => (
          <div
            key={attachment.id}
            className="flex items-center gap-2 px-3 py-2 bg-base-200 rounded-lg hover:bg-base-300 transition-colors group"
          >
            <div className="flex-shrink-0">
              {getFileIcon(attachment.mime_type)}
            </div>
            <div className="min-w-0 flex-1">
              <div className="text-sm font-medium truncate max-w-[150px]">
                {attachment.filename}
              </div>
              <div className="text-xs text-base-content/60">
                {formatFileSize(attachment.size)}
              </div>
            </div>
            <div className="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
              <button
                onClick={() => handleDownload(attachment)}
                className="btn btn-ghost btn-xs btn-circle"
                title="Download"
              >
                <Download className="w-3 h-3" />
              </button>
              {canDelete && onDelete && (
                <button
                  onClick={() => onDelete(attachment.id)}
                  className="btn btn-ghost btn-xs btn-circle text-error"
                  title="Delete"
                >
                  <Trash2 className="w-3 h-3" />
                </button>
              )}
            </div>
          </div>
        ))}
      </div>
    )
  }

  // Grid layout for full view
  return (
    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
      {attachments.map((attachment) => (
        <div
          key={attachment.id}
          className="relative group bg-base-200 rounded-lg overflow-hidden hover:ring-2 hover:ring-primary transition-all"
        >
          {/* Preview Area */}
          <div className="aspect-square flex items-center justify-center bg-base-300 relative">
            {isImage(attachment.mime_type) ? (
              <img
                src={attachment.public_url || attachment.path}
                alt={attachment.filename}
                className="w-full h-full object-cover"
              />
            ) : (
              <div className="flex items-center justify-center w-full h-full">
                {getFileIcon(attachment.mime_type)}
              </div>
            )}

            {/* Hover Actions */}
            <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
              <button
                onClick={() => handleDownload(attachment)}
                className="btn btn-circle btn-sm btn-primary"
                title="Download"
              >
                <Download className="w-4 h-4" />
              </button>
              {canDelete && onDelete && (
                <button
                  onClick={() => onDelete(attachment.id)}
                  className="btn btn-circle btn-sm btn-error"
                  title="Delete"
                >
                  <Trash2 className="w-4 h-4" />
                </button>
              )}
            </div>
          </div>

          {/* File Info */}
          <div className="p-2">
            <div className="text-xs font-medium truncate" title={attachment.filename}>
              {attachment.filename}
            </div>
            <div className="text-xs text-base-content/60">
              {formatFileSize(attachment.size)}
            </div>
          </div>
        </div>
      ))}
    </div>
  )
}


