import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { Lock, ArrowRight, Eye, EyeOff, CheckCircle } from 'lucide-react'
import { useAuthStore } from '../stores/auth'

export default function ResetPasswordPage() {
  const navigate = useNavigate()
  const { updatePassword, isLoading, error, clearError } = useAuthStore()
  
  const [password, setPassword] = useState('')
  const [confirmPassword, setConfirmPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [showConfirmPassword, setShowConfirmPassword] = useState(false)
  const [localError, setLocalError] = useState<string | null>(null)
  const [isSuccess, setIsSuccess] = useState(false)

  // Clear errors on mount
  useEffect(() => {
    clearError()
  }, [clearError])

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    e.stopPropagation()
    setLocalError(null)
    clearError()

    // Validate passwords match
    if (password !== confirmPassword) {
      setLocalError('Passwords do not match')
      return
    }

    // Validate password strength
    if (password.length < 6) {
      setLocalError('Password must be at least 6 characters')
      return
    }

    const success = await updatePassword(password)
    if (success) {
      setIsSuccess(true)
      // Redirect to login after 3 seconds
      setTimeout(() => {
        navigate('/login', { replace: true })
      }, 3000)
    }
  }

  const displayError = localError || error

  // Success state
  if (isSuccess) {
    return (
      <div 
        className="flex bg-base-100 items-center justify-center"
        style={{ height: '100vh', width: '100vw', position: 'fixed', top: 0, left: 0 }}
      >
        <div className="w-full max-w-md p-8 text-center">
          <div className="mb-6 flex justify-center">
            <div className="w-20 h-20 rounded-full bg-success/20 flex items-center justify-center">
              <CheckCircle className="w-10 h-10 text-success" />
            </div>
          </div>
          <h2 className="text-2xl font-semibold mb-3">Password Updated!</h2>
          <p className="text-base-content/60 mb-6">
            Your password has been successfully updated. You will be redirected to the login page shortly.
          </p>
          <button
            onClick={() => navigate('/login', { replace: true })}
            className="btn btn-primary gap-2"
          >
            Go to Sign In
            <ArrowRight className="w-4 h-4" />
          </button>
        </div>
      </div>
    )
  }

  return (
    <div 
      className="flex bg-base-100 items-center justify-center"
      style={{ height: '100vh', width: '100vw', position: 'fixed', top: 0, left: 0 }}
    >
      <div className="w-full max-w-md p-8">
        {/* Logo */}
        <div className="mb-8 flex justify-center">
          <img 
            src="/brand/logo-white.webp" 
            alt="Beetasky" 
            className="h-12 w-auto"
          />
        </div>

        {/* Header */}
        <div className="mb-8 text-center">
          <h2 className="text-3xl font-semibold mb-2">Reset Password</h2>
          <p className="text-base-content/60">Enter your new password below</p>
        </div>

        {/* Error Alert */}
        {displayError && (
          <div className="alert alert-error mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" className="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{displayError}</span>
          </div>
        )}

        {/* Form */}
        <form onSubmit={handleSubmit} className="space-y-6">
          {/* New Password */}
          <div className="form-control">
            <label className="label">
              <span className="label-text font-medium">New Password</span>
            </label>
            <div className="relative">
              <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <Lock className="w-5 h-5 text-base-content/40" />
              </div>
              <input
                type={showPassword ? 'text' : 'password'}
                placeholder="••••••••"
                className="input input-bordered w-full pl-12 pr-12 bg-base-200 border-base-300 focus:border-primary"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                autoComplete="new-password"
                required
                disabled={isLoading}
                autoFocus
              />
              <button
                type="button"
                className="absolute inset-y-0 right-0 pr-4 flex items-center"
                onClick={() => setShowPassword(!showPassword)}
                disabled={isLoading}
              >
                {showPassword ? (
                  <EyeOff className="w-5 h-5 text-base-content/40 hover:text-base-content transition-colors" />
                ) : (
                  <Eye className="w-5 h-5 text-base-content/40 hover:text-base-content transition-colors" />
                )}
              </button>
            </div>
            <label className="label">
              <span className="label-text-alt text-base-content/50">At least 6 characters</span>
            </label>
          </div>

          {/* Confirm Password */}
          <div className="form-control">
            <label className="label">
              <span className="label-text font-medium">Confirm New Password</span>
            </label>
            <div className="relative">
              <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <Lock className="w-5 h-5 text-base-content/40" />
              </div>
              <input
                type={showConfirmPassword ? 'text' : 'password'}
                placeholder="••••••••"
                className="input input-bordered w-full pl-12 pr-12 bg-base-200 border-base-300 focus:border-primary"
                value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
                autoComplete="new-password"
                required
                disabled={isLoading}
              />
              <button
                type="button"
                className="absolute inset-y-0 right-0 pr-4 flex items-center"
                onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                disabled={isLoading}
              >
                {showConfirmPassword ? (
                  <EyeOff className="w-5 h-5 text-base-content/40 hover:text-base-content transition-colors" />
                ) : (
                  <Eye className="w-5 h-5 text-base-content/40 hover:text-base-content transition-colors" />
                )}
              </button>
            </div>
          </div>

          {/* Submit */}
          <button 
            type="submit" 
            className="btn btn-primary w-full gap-2 group"
            disabled={isLoading}
          >
            {isLoading ? (
              <span className="loading loading-spinner loading-sm"></span>
            ) : (
              <>
                Update Password
                <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
              </>
            )}
          </button>
        </form>

        {/* Back to login link */}
        <p className="mt-8 text-center text-base-content/60">
          Remember your password?{' '}
          <button
            type="button"
            onClick={() => navigate('/login')}
            className="text-primary font-medium hover:text-primary/80 transition-colors"
            disabled={isLoading}
          >
            Sign in
          </button>
        </p>
      </div>
    </div>
  )
}

