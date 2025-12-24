import { useState } from 'react'
import axios from 'axios'
import { api } from '../lib/api'

export interface UploadedFile {
  path: string
  public_url: string
  filename: string
  mime_type: string
  size: number
  verified: boolean
}

export interface UploadProgress {
  file: File
  progress: number
  status: 'preparing' | 'uploading' | 'confirming' | 'completed' | 'error'
  error?: string
  result?: UploadedFile
}

export interface UseFileUploadOptions {
  entityType: string
  entityId?: string
  onProgress?: (file: File, progress: number) => void
  onSuccess?: (file: File, result: UploadedFile) => void
  onError?: (file: File, error: Error) => void
}

/**
 * Reusable hook for uploading files to Bunny.net CDN
 * Works with any entity type across the application
 * 
 * @example
 * // Task comments
 * const { uploadFile } = useFileUpload({ 
 *   entityType: 'task_comment', 
 *   entityId: taskId 
 * })
 * 
 * @example
 * // User avatar
 * const { uploadFile } = useFileUpload({ 
 *   entityType: 'user_avatar', 
 *   entityId: userId 
 * })
 */
export function useFileUpload(options: UseFileUploadOptions) {
  const [uploading, setUploading] = useState(false)
  const [progress, setProgress] = useState<Record<string, UploadProgress>>({})

  /**
   * Upload a single file
   */
  const uploadFile = async (file: File): Promise<UploadedFile> => {
    const fileKey = `${file.name}-${file.size}`
    
    try {
      // Update progress: preparing
      updateProgress(fileKey, file, 0, 'preparing')
      
      // Step 1: Prepare upload - Get signed URL from Laravel
      const prepareResponse = await api.post('/api/v1/upload/prepare', {
        filename: file.name,
        mime_type: file.type,
        size: file.size,
        entity_type: options.entityType,
        entity_id: options.entityId || null,
      })
      
      if (!prepareResponse.data.success) {
        throw new Error(prepareResponse.data.message || 'Failed to prepare upload')
      }
      
      const { upload_url, path, access_key } = prepareResponse.data
      
      // Update progress: uploading
      updateProgress(fileKey, file, 0, 'uploading')
      
      // Step 2: Upload file directly to Bunny CDN with AccessKey header
      await axios.put(upload_url, file, {
        headers: {
          'Content-Type': file.type,
          'AccessKey': access_key,
        },
        onUploadProgress: (progressEvent) => {
          if (progressEvent.total) {
            const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total)
            updateProgress(fileKey, file, percentCompleted, 'uploading')
            options.onProgress?.(file, percentCompleted)
          }
        },
      })
      
      // Update progress: confirming
      updateProgress(fileKey, file, 100, 'confirming')
      
      // Step 3: Confirm upload with Laravel
      const confirmResponse = await api.post('/api/v1/upload/confirm', {
        path,
        filename: file.name,
        mime_type: file.type,
        size: file.size,
      })
      
      if (!confirmResponse.data.success) {
        throw new Error(confirmResponse.data.message || 'Failed to confirm upload')
      }
      
      const result: UploadedFile = {
        path: confirmResponse.data.path,
        public_url: confirmResponse.data.public_url,
        filename: confirmResponse.data.filename,
        mime_type: confirmResponse.data.mime_type,
        size: confirmResponse.data.size,
        verified: confirmResponse.data.verified,
      }
      
      // Update progress: completed
      updateProgress(fileKey, file, 100, 'completed', undefined, result)
      options.onSuccess?.(file, result)
      
      return result
    } catch (error) {
      const errorMessage = error instanceof Error ? error.message : 'Upload failed'
      updateProgress(fileKey, file, 0, 'error', errorMessage)
      options.onError?.(file, error instanceof Error ? error : new Error(errorMessage))
      throw error
    }
  }

  /**
   * Upload multiple files
   */
  const uploadMultiple = async (files: File[]): Promise<UploadedFile[]> => {
    setUploading(true)
    
    try {
      const results = await Promise.all(
        files.map(file => uploadFile(file))
      )
      return results
    } finally {
      setUploading(false)
    }
  }

  /**
   * Update progress state for a file
   */
  const updateProgress = (
    fileKey: string,
    file: File,
    progressPercent: number,
    status: UploadProgress['status'],
    error?: string,
    result?: UploadedFile
  ) => {
    setProgress(prev => ({
      ...prev,
      [fileKey]: {
        file,
        progress: progressPercent,
        status,
        error,
        result,
      },
    }))
  }

  /**
   * Clear progress for a file
   */
  const clearProgress = (file: File) => {
    const fileKey = `${file.name}-${file.size}`
    setProgress(prev => {
      const newProgress = { ...prev }
      delete newProgress[fileKey]
      return newProgress
    })
  }

  /**
   * Clear all progress
   */
  const clearAllProgress = () => {
    setProgress({})
  }

  return {
    uploadFile,
    uploadMultiple,
    uploading,
    progress,
    clearProgress,
    clearAllProgress,
  }
}

