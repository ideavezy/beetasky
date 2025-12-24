import { useState, useRef, DragEvent, ChangeEvent } from 'react'
import { Paperclip, X, Loader2, Image as ImageIcon, FileText, FileSpreadsheet, Archive, File as FileIcon, CheckCircle, AlertCircle } from 'lucide-react'
import { useFileUpload, UploadedFile } from '../hooks/useFileUpload'

interface FileUploadButtonProps {
  entityType: string
  entityId?: string
  multiple?: boolean
  accept?: string
  maxSize?: number // in bytes
  onUploadComplete?: (files: UploadedFile[]) => void
  className?: string
  disabled?: boolean
}

interface FilePreview {
  file: File
  preview?: string
  status: 'pending' | 'uploading' | 'completed' | 'error'
  progress: number
  error?: string
  result?: UploadedFile
}

export default function FileUploadButton({
  entityType,
  entityId,
  multiple = true,
  accept,
  maxSize = 26214400, // 25 MB default
  onUploadComplete,
  className = '',
  disabled = false,
}: FileUploadButtonProps) {
  const [isDragging, setIsDragging] = useState(false)
  const [files, setFiles] = useState<FilePreview[]>([])
  const [showDropZone, setShowDropZone] = useState(false)
  const fileInputRef = useRef<HTMLInputElement>(null)

  const { uploadFile } = useFileUpload({
    entityType,
    entityId,
    onProgress: (file, progress) => {
      updateFileStatus(file, 'uploading', progress)
    },
    onSuccess: (file, result) => {
      updateFileStatus(file, 'completed', 100, undefined, result)
      
      // Check if all files are completed
      setFiles(prev => {
        const allCompleted = prev.every(f => f.status === 'completed')
        if (allCompleted && onUploadComplete) {
          const results = prev.map(f => f.result).filter(Boolean) as UploadedFile[]
          onUploadComplete(results)
        }
        return prev
      })
    },
    onError: (file, error) => {
      updateFileStatus(file, 'error', 0, error.message)
    },
  })

  const updateFileStatus = (
    file: File,
    status: FilePreview['status'],
    progress: number,
    error?: string,
    result?: UploadedFile
  ) => {
    setFiles(prev =>
      prev.map(f =>
        f.file === file
          ? { ...f, status, progress, error, result }
          : f
      )
    )
  }

  const handleFileSelect = (selectedFiles: FileList | null) => {
    if (!selectedFiles || selectedFiles.length === 0) return

    const newFiles: FilePreview[] = []

    Array.from(selectedFiles).forEach(file => {
      // Validate file size
      if (file.size > maxSize) {
        alert(`File "${file.name}" exceeds maximum size of ${formatFileSize(maxSize)}`)
        return
      }

      // Create preview for images
      const preview = file.type.startsWith('image/')
        ? URL.createObjectURL(file)
        : undefined

      newFiles.push({
        file,
        preview,
        status: 'pending',
        progress: 0,
      })
    })

    if (newFiles.length === 0) return

    setFiles(prev => [...prev, ...newFiles])

    // Start uploading
    newFiles.forEach(({ file }) => {
      uploadFile(file).catch(console.error)
    })
  }

  const handleDragOver = (e: DragEvent) => {
    e.preventDefault()
    setIsDragging(true)
  }

  const handleDragLeave = () => {
    setIsDragging(false)
  }

  const handleDrop = (e: DragEvent) => {
    e.preventDefault()
    setIsDragging(false)
    handleFileSelect(e.dataTransfer.files)
  }

  const handleInputChange = (e: ChangeEvent<HTMLInputElement>) => {
    handleFileSelect(e.target.files)
    if (fileInputRef.current) {
      fileInputRef.current.value = ''
    }
  }

  const handleRemoveFile = (file: File) => {
    setFiles(prev => prev.filter(f => f.file !== file))
    if (files.length === 0 && fileInputRef.current) {
      fileInputRef.current.value = ''
    }
  }

  const getFileIcon = (mimeType: string) => {
    if (mimeType.startsWith('image/')) {
      return <ImageIcon className="w-4 h-4" />
    }
    if (mimeType === 'application/pdf') {
      return <FileText className="w-4 h-4" />
    }
    if (
      mimeType.includes('spreadsheet') ||
      mimeType.includes('excel')
    ) {
      return <FileSpreadsheet className="w-4 h-4" />
    }
    if (
      mimeType.includes('zip') ||
      mimeType.includes('rar') ||
      mimeType.includes('7z')
    ) {
      return <Archive className="w-4 h-4" />
    }
    return <FileIcon className="w-4 h-4" />
  }

  const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return bytes + ' B'
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB'
    return (bytes / 1048576).toFixed(1) + ' MB'
  }

  const hasFiles = files.length > 0
  const isUploading = files.some(f => f.status === 'uploading' || f.status === 'pending')

  return (
    <div className={className}>
      {/* Upload Button/Drop Zone */}
      {showDropZone ? (
        <div
          onDragOver={handleDragOver}
          onDragLeave={handleDragLeave}
          onDrop={handleDrop}
          className={`
            relative border-2 border-dashed rounded-lg p-6 transition-all
            ${isDragging 
              ? 'border-primary bg-primary/10' 
              : 'border-base-300 bg-base-200/50 hover:border-primary/50'
            }
            ${disabled || isUploading ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}
          `}
          onClick={() => !disabled && !isUploading && fileInputRef.current?.click()}
        >
          <input
            ref={fileInputRef}
            type="file"
            multiple={multiple}
            accept={accept}
            onChange={handleInputChange}
            disabled={disabled || isUploading}
            className="hidden"
          />
          
          <div className="text-center">
            <Paperclip className="w-8 h-8 mx-auto mb-2 text-base-content/60" />
            <p className="text-sm font-medium mb-1">
              {isDragging ? 'Drop files here' : 'Drag & drop files here'}
            </p>
            <p className="text-xs text-base-content/60">
              or click to browse
            </p>
            <p className="text-xs text-base-content/40 mt-2">
              Max {formatFileSize(maxSize)} per file
            </p>
          </div>
          
          {/* Close button */}
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation()
              setShowDropZone(false)
            }}
            className="absolute top-2 right-2 btn btn-ghost btn-xs btn-circle"
          >
            <X className="w-3 h-3" />
          </button>
        </div>
      ) : (
        <button
          type="button"
          onClick={() => setShowDropZone(true)}
          disabled={disabled || isUploading}
          className={`
            btn btn-ghost btn-sm gap-1
            ${disabled || isUploading ? 'opacity-50 cursor-not-allowed' : ''}
          `}
          title="Attach files"
        >
          {isUploading ? (
            <Loader2 className="w-4 h-4 animate-spin" />
          ) : (
            <Paperclip className="w-4 h-4" />
          )}
        </button>
      )}

      {/* File Previews */}
      {hasFiles && (
        <div className="space-y-2">
          {files.map(({ file, preview, status, progress, error }) => (
            <div
              key={`${file.name}-${file.size}`}
              className="flex items-center gap-3 p-2 bg-base-200 rounded-lg"
            >
              {/* Icon or Image Preview */}
              <div className="flex-shrink-0">
                {preview ? (
                  <img
                    src={preview}
                    alt={file.name}
                    className="w-10 h-10 object-cover rounded"
                  />
                ) : (
                  <div className="w-10 h-10 flex items-center justify-center bg-base-300 rounded">
                    {getFileIcon(file.type)}
                  </div>
                )}
              </div>

              {/* File Info */}
              <div className="flex-1 min-w-0">
                <div className="text-sm font-medium truncate">
                  {file.name}
                </div>
                <div className="text-xs text-base-content/60">
                  {formatFileSize(file.size)}
                </div>

                {/* Progress Bar */}
                {(status === 'uploading' || status === 'pending') && (
                  <div className="mt-1">
                    <progress
                      className="progress progress-primary w-full h-1"
                      value={progress}
                      max="100"
                    />
                  </div>
                )}

                {/* Error Message */}
                {status === 'error' && error && (
                  <div className="text-xs text-error mt-1">
                    {error}
                  </div>
                )}
              </div>

              {/* Status Icon */}
              <div className="flex-shrink-0">
                {status === 'uploading' && (
                  <Loader2 className="w-4 h-4 animate-spin text-primary" />
                )}
                {status === 'completed' && (
                  <CheckCircle className="w-4 h-4 text-success" />
                )}
                {status === 'error' && (
                  <AlertCircle className="w-4 h-4 text-error" />
                )}
                {status === 'pending' && (
                  <Loader2 className="w-4 h-4 animate-spin text-base-content/30" />
                )}
              </div>

              {/* Remove Button */}
              {(status === 'error' || status === 'completed') && (
                <button
                  type="button"
                  onClick={() => handleRemoveFile(file)}
                  className="btn btn-ghost btn-xs btn-circle"
                >
                  <X className="w-3 h-3" />
                </button>
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

