import { useState } from 'react'
import { useNavigate, useLocation } from 'react-router-dom'
import { Mail, Lock, User, ArrowRight, Eye, EyeOff } from 'lucide-react'
import { useAuthStore } from '../stores/auth'

interface AuthPageProps {
  initialMode?: 'signin' | 'signup'
}

export default function AuthPage({ initialMode = 'signin' }: AuthPageProps) {
  const navigate = useNavigate()
  const location = useLocation()
  const { signIn, signUp, signInWithGoogle, resetPassword, isLoading, error, clearError } = useAuthStore()
  
  const [mode, setMode] = useState<'signin' | 'signup'>(initialMode)
  const [isTransitioning, setIsTransitioning] = useState(false)
  const [showPassword, setShowPassword] = useState(false)
  const [showConfirmPassword, setShowConfirmPassword] = useState(false)
  const [localError, setLocalError] = useState<string | null>(null)
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [resetEmailSent, setResetEmailSent] = useState(false)
  const [isResettingPassword, setIsResettingPassword] = useState(false)
  
  // Form states
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [confirmPassword, setConfirmPassword] = useState('')
  const [firstName, setFirstName] = useState('')
  const [lastName, setLastName] = useState('')

  // Get the redirect path from location state, or default to dashboard
  const from = (location.state as { from?: { pathname: string } })?.from?.pathname || '/dashboard'

  const handleModeSwitch = (newMode: 'signin' | 'signup') => {
    if (mode === newMode || isTransitioning) return
    
    setIsTransitioning(true)
    clearError()
    setLocalError(null)
    setSuccessMessage(null)
    setResetEmailSent(false)
    
    setTimeout(() => {
      setMode(newMode)
      setEmail('')
      setPassword('')
      setConfirmPassword('')
      setFirstName('')
      setLastName('')
      setShowPassword(false)
      setShowConfirmPassword(false)
      
      setTimeout(() => {
        setIsTransitioning(false)
      }, 50)
    }, 300)
  }

  const handleSignIn = async (e: React.FormEvent) => {
    e.preventDefault()
    e.stopPropagation()
    setLocalError(null)
    clearError()
    
    const success = await signIn(email, password)
    if (success) {
      navigate(from, { replace: true })
    }
    // If not successful, the error is already set in the store and will be displayed
  }

  const handleSignUp = async (e: React.FormEvent) => {
    e.preventDefault()
    e.stopPropagation()
    setLocalError(null)
    clearError()
    setSuccessMessage(null)
    
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
    
    const success = await signUp(email, password, { first_name: firstName, last_name: lastName })
    if (success) {
      navigate('/dashboard', { replace: true })
    } else {
      // Check if the error is about email confirmation
      const currentError = useAuthStore.getState().error
      if (currentError?.includes('check your email')) {
        setSuccessMessage(currentError)
        clearError()
      }
    }
  }

  const handleGoogleSignIn = async () => {
    setLocalError(null)
    clearError()
    
    try {
      await signInWithGoogle()
      // User will be redirected to Google
    } catch (err) {
      console.error('Google sign in failed:', err)
    }
  }

  const handleForgotPassword = async () => {
    setLocalError(null)
    clearError()
    setResetEmailSent(false)
    
    // Validate email
    if (!email || !email.trim()) {
      setLocalError('Please enter your email address first')
      return
    }
    
    // Basic email format validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    if (!emailRegex.test(email)) {
      setLocalError('Please enter a valid email address')
      return
    }
    
    setIsResettingPassword(true)
    const success = await resetPassword(email)
    setIsResettingPassword(false)
    
    if (success) {
      setResetEmailSent(true)
      setSuccessMessage('Password reset link sent! Check your email inbox.')
    }
  }

  const displayError = localError || error
  const isSignIn = mode === 'signin'

  return (
    <div 
      className="flex bg-base-100 overflow-hidden"
      style={{ height: '100vh', width: '100vw', position: 'fixed', top: 0, left: 0 }}
    >
      {/* Left Side - Contains Sign Up Form (visible when signup mode) */}
      <div 
        className="hidden lg:flex w-1/2 h-full items-center justify-center bg-base-100 p-16"
      >
        <div 
          className={`w-full max-w-md transition-all duration-700 ease-in-out ${
            isSignIn 
              ? 'opacity-0 -translate-x-8' 
              : 'opacity-100 translate-x-0'
          }`}
          style={{ transitionDelay: isSignIn ? '0ms' : '300ms' }}
        >
          <SignUpForm
            email={email}
            setEmail={setEmail}
            password={password}
            setPassword={setPassword}
            confirmPassword={confirmPassword}
            setConfirmPassword={setConfirmPassword}
            firstName={firstName}
            setFirstName={setFirstName}
            lastName={lastName}
            setLastName={setLastName}
            showPassword={showPassword}
            setShowPassword={setShowPassword}
            showConfirmPassword={showConfirmPassword}
            setShowConfirmPassword={setShowConfirmPassword}
            onSubmit={handleSignUp}
            onGoogleSignIn={handleGoogleSignIn}
            onSwitchMode={() => handleModeSwitch('signin')}
            isLoading={isLoading}
            error={displayError}
            successMessage={successMessage}
          />
        </div>
      </div>

      {/* Right Side - Contains Sign In Form (visible when signin mode) */}
      <div 
        className="hidden lg:flex w-1/2 h-full items-center justify-center bg-base-100 p-16"
      >
        <div 
          className={`w-full max-w-md transition-all duration-700 ease-in-out ${
            isSignIn 
              ? 'opacity-100 translate-x-0' 
              : 'opacity-0 translate-x-8'
          }`}
          style={{ transitionDelay: isSignIn ? '300ms' : '0ms' }}
        >
          <SignInForm
            email={email}
            setEmail={setEmail}
            password={password}
            setPassword={setPassword}
            showPassword={showPassword}
            setShowPassword={setShowPassword}
            onSubmit={handleSignIn}
            onGoogleSignIn={handleGoogleSignIn}
            onForgotPassword={handleForgotPassword}
            onSwitchMode={() => handleModeSwitch('signup')}
            isLoading={isLoading}
            isResettingPassword={isResettingPassword}
            error={displayError}
            successMessage={mode === 'signin' ? successMessage : null}
          />
        </div>
      </div>

      {/* Sliding Image Panel - Overlays and slides between left/right */}
      <div 
        className={`hidden lg:flex absolute top-0 h-full w-1/2 transition-all duration-700 ease-in-out z-20 ${
          isSignIn 
            ? 'left-0' 
            : 'left-1/2'
        }`}
      >
        <AuthImagePanel isSignIn={isSignIn} />
      </div>

      {/* Mobile View - Full screen form with fade transition */}
      <div className="lg:hidden w-full h-full flex items-center justify-center p-8 bg-base-100 overflow-y-auto">
        <div 
          className={`w-full max-w-md transition-opacity duration-300 ${
            isTransitioning ? 'opacity-0' : 'opacity-100'
          }`}
        >
          {isSignIn ? (
            <SignInForm
              email={email}
              setEmail={setEmail}
              password={password}
              setPassword={setPassword}
              showPassword={showPassword}
              setShowPassword={setShowPassword}
              onSubmit={handleSignIn}
              onGoogleSignIn={handleGoogleSignIn}
              onForgotPassword={handleForgotPassword}
              onSwitchMode={() => handleModeSwitch('signup')}
              isLoading={isLoading}
              isResettingPassword={isResettingPassword}
              error={displayError}
              successMessage={mode === 'signin' ? successMessage : null}
            />
          ) : (
            <SignUpForm
              email={email}
              setEmail={setEmail}
              password={password}
              setPassword={setPassword}
              confirmPassword={confirmPassword}
              setConfirmPassword={setConfirmPassword}
              firstName={firstName}
              setFirstName={setFirstName}
              lastName={lastName}
              setLastName={setLastName}
              showPassword={showPassword}
              setShowPassword={setShowPassword}
              showConfirmPassword={showConfirmPassword}
              setShowConfirmPassword={setShowConfirmPassword}
              onSubmit={handleSignUp}
              onGoogleSignIn={handleGoogleSignIn}
              onSwitchMode={() => handleModeSwitch('signin')}
              isLoading={isLoading}
              error={displayError}
              successMessage={successMessage}
            />
          )}
        </div>
      </div>
    </div>
  )
}

// Auth Image Panel - The sliding overlay
function AuthImagePanel({ isSignIn }: { isSignIn: boolean }) {
  return (
    <div className="h-full w-full relative bg-base-200 overflow-hidden">
      {/* Decorative background */}
      <div className="absolute inset-0 bg-gradient-to-br from-base-300 via-base-200 to-base-300">
        {/* Geometric patterns */}
        <div className="absolute inset-0 opacity-20">
          <svg className="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
            <defs>
              <pattern id="grid-pattern" width="10" height="10" patternUnits="userSpaceOnUse">
                <path d="M 10 0 L 0 0 0 10" fill="none" stroke="currentColor" strokeWidth="0.5" className="text-primary/30"/>
              </pattern>
            </defs>
            <rect width="100" height="100" fill="url(#grid-pattern)"/>
          </svg>
        </div>
        
        {/* Accent circles */}
        <div className="absolute top-1/4 left-1/4 w-64 h-64 rounded-full bg-primary/10 blur-3xl" />
        <div className="absolute bottom-1/4 right-1/4 w-48 h-48 rounded-full bg-accent/10 blur-3xl" />
      </div>
      
      {/* Content */}
      <div className="relative h-full flex flex-col items-center justify-center p-12 text-center">
        {/* Logo */}
        <div className="mb-8">
          <img 
            src="/brand/logo-white.webp" 
            alt="Beetasky" 
            className="h-16 w-auto"
          />
        </div>
        
        {/* Dynamic Text based on mode */}
        <p className="text-lg text-base-content/70 max-w-xs transition-all duration-500">
          {isSignIn 
            ? 'Your intelligent CRM companion. Streamline your business with AI-powered insights.'
            : 'Join us today and transform your business with smart automation.'
          }
        </p>
        
        {/* Decorative elements */}
        <div className="mt-12 flex gap-2">
          <div className={`w-2 h-2 rounded-full transition-all duration-500 ${isSignIn ? 'bg-primary' : 'bg-primary/30'}`} />
          <div className="w-2 h-2 rounded-full bg-primary/60" />
          <div className={`w-2 h-2 rounded-full transition-all duration-500 ${isSignIn ? 'bg-primary/30' : 'bg-primary'}`} />
        </div>
      </div>
    </div>
  )
}

// Sign In Form Component
interface SignInFormProps {
  email: string
  setEmail: (v: string) => void
  password: string
  setPassword: (v: string) => void
  showPassword: boolean
  setShowPassword: (v: boolean) => void
  onSubmit: (e: React.FormEvent) => void
  onGoogleSignIn: () => void
  onForgotPassword: () => void
  onSwitchMode: () => void
  isLoading: boolean
  isResettingPassword: boolean
  error: string | null
  successMessage: string | null
}

function SignInForm({
  email,
  setEmail,
  password,
  setPassword,
  showPassword,
  setShowPassword,
  onSubmit,
  onGoogleSignIn,
  onForgotPassword,
  onSwitchMode,
  isLoading,
  isResettingPassword,
  error,
  successMessage,
}: SignInFormProps) {
  return (
    <div>
      {/* Header */}
      <div className="mb-10">
        <h2 className="text-3xl font-semibold mb-2">Welcome back</h2>
        <p className="text-base-content/60">Sign in to access your dashboard</p>
      </div>

      {/* Success Alert */}
      {successMessage && (
        <div className="alert alert-success mb-6">
          <svg xmlns="http://www.w3.org/2000/svg" className="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>{successMessage}</span>
        </div>
      )}

      {/* Error Alert */}
      {error && !successMessage && (
        <div className="alert alert-error mb-6">
          <svg xmlns="http://www.w3.org/2000/svg" className="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>{error}</span>
        </div>
      )}

      {/* Form */}
      <form onSubmit={onSubmit} className="space-y-6">
        {/* Email */}
        <div className="form-control">
          <label className="label">
            <span className="label-text font-medium">Email</span>
          </label>
          <div className="relative">
            <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
              <Mail className="w-5 h-5 text-base-content/40" />
            </div>
            <input
              type="email"
              placeholder="you@example.com"
              className="input input-bordered w-full pl-12 bg-base-200 border-base-300 focus:border-primary"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              autoComplete="email"
              required
              disabled={isLoading}
            />
          </div>
        </div>

        {/* Password */}
        <div className="form-control">
          <label className="label">
            <span className="label-text font-medium">Password</span>
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
              autoComplete="current-password"
              required
              disabled={isLoading}
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
            <span className="label-text-alt"></span>
            <button
              type="button"
              onClick={onForgotPassword}
              disabled={isLoading || isResettingPassword}
              className="label-text-alt text-primary hover:text-primary/80 transition-colors inline-flex items-center gap-1"
            >
              {isResettingPassword ? (
                <>
                  <span className="loading loading-spinner loading-xs"></span>
                  Sending...
                </>
              ) : (
                'Forgot password?'
              )}
            </button>
          </label>
        </div>

        {/* Submit */}
        <button 
          type="submit" 
          className="btn btn-primary w-full gap-2 group"
          disabled={isLoading || isResettingPassword}
        >
          {isLoading ? (
            <span className="loading loading-spinner loading-sm"></span>
          ) : (
            <>
              Sign in
              <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
            </>
          )}
        </button>
      </form>

      {/* Divider */}
      <div className="divider my-8 text-base-content/40">or continue with</div>

      {/* Social logins */}
      <div className="grid grid-cols-1 gap-4">
        <button 
          type="button"
          onClick={onGoogleSignIn}
          className="btn btn-outline border-base-300 hover:bg-base-200 hover:border-primary hover:text-primary gap-2 transition-colors"
          disabled={isLoading}
        >
          <svg className="w-5 h-5" viewBox="0 0 24 24">
            <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
            <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
          </svg>
          Continue with Google
        </button>
      </div>

      {/* Sign up link */}
      <p className="mt-10 text-center text-base-content/60">
        Don't have an account?{' '}
        <button
          type="button"
          onClick={onSwitchMode}
          className="text-primary font-medium hover:text-primary/80 transition-colors"
          disabled={isLoading}
        >
          Sign up
        </button>
      </p>
    </div>
  )
}

// Sign Up Form Component
interface SignUpFormProps {
  email: string
  setEmail: (v: string) => void
  password: string
  setPassword: (v: string) => void
  confirmPassword: string
  setConfirmPassword: (v: string) => void
  firstName: string
  setFirstName: (v: string) => void
  lastName: string
  setLastName: (v: string) => void
  showPassword: boolean
  setShowPassword: (v: boolean) => void
  showConfirmPassword: boolean
  setShowConfirmPassword: (v: boolean) => void
  onSubmit: (e: React.FormEvent) => void
  onGoogleSignIn: () => void
  onSwitchMode: () => void
  isLoading: boolean
  error: string | null
  successMessage: string | null
}

function SignUpForm({
  email,
  setEmail,
  password,
  setPassword,
  confirmPassword,
  setConfirmPassword,
  firstName,
  setFirstName,
  lastName,
  setLastName,
  showPassword,
  setShowPassword,
  showConfirmPassword,
  setShowConfirmPassword,
  onSubmit,
  onGoogleSignIn,
  onSwitchMode,
  isLoading,
  error,
  successMessage,
}: SignUpFormProps) {
  return (
    <div>
      {/* Header */}
      <div className="mb-8">
        <h2 className="text-3xl font-semibold mb-2">Create account</h2>
        <p className="text-base-content/60">Start your journey with us today</p>
      </div>

      {/* Success Alert */}
      {successMessage && (
        <div className="alert alert-success mb-6">
          <svg xmlns="http://www.w3.org/2000/svg" className="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>{successMessage}</span>
        </div>
      )}

      {/* Error Alert */}
      {error && !successMessage && (
        <div className="alert alert-error mb-6">
          <svg xmlns="http://www.w3.org/2000/svg" className="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span>{error}</span>
        </div>
      )}

      {/* Form */}
      <form onSubmit={onSubmit} className="space-y-5">
        {/* First Name & Last Name - Same Row */}
        <div className="grid grid-cols-2 gap-4">
          <div className="form-control">
            <label className="label">
              <span className="label-text font-medium">First Name</span>
            </label>
            <div className="relative">
              <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <User className="w-5 h-5 text-base-content/40" />
              </div>
              <input
                type="text"
                placeholder="John"
                className="input input-bordered w-full pl-12 bg-base-200 border-base-300 focus:border-primary"
                value={firstName}
                onChange={(e) => setFirstName(e.target.value)}
                autoComplete="given-name"
                required
                disabled={isLoading}
              />
            </div>
          </div>
          <div className="form-control">
            <label className="label">
              <span className="label-text font-medium">Last Name</span>
            </label>
            <div className="relative">
              <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                <User className="w-5 h-5 text-base-content/40" />
              </div>
              <input
                type="text"
                placeholder="Doe"
                className="input input-bordered w-full pl-12 bg-base-200 border-base-300 focus:border-primary"
                value={lastName}
                onChange={(e) => setLastName(e.target.value)}
                autoComplete="family-name"
                disabled={isLoading}
              />
            </div>
          </div>
        </div>

        {/* Email */}
        <div className="form-control">
          <label className="label">
            <span className="label-text font-medium">Email</span>
          </label>
          <div className="relative">
            <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
              <Mail className="w-5 h-5 text-base-content/40" />
            </div>
            <input
              type="email"
              placeholder="you@example.com"
              className="input input-bordered w-full pl-12 bg-base-200 border-base-300 focus:border-primary"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              autoComplete="email"
              required
              disabled={isLoading}
            />
          </div>
        </div>

        {/* Password */}
        <div className="form-control">
          <label className="label">
            <span className="label-text font-medium">Password</span>
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
        </div>

        {/* Confirm Password */}
        <div className="form-control">
          <label className="label">
            <span className="label-text font-medium">Confirm Password</span>
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

        {/* Terms */}
        <div className="form-control">
          <label className="label cursor-pointer justify-start gap-3">
            <input type="checkbox" className="checkbox checkbox-primary checkbox-sm" required disabled={isLoading} />
            <span className="label-text text-base-content/70">
              I agree to the{' '}
              <a href="#" className="text-primary hover:text-primary/80">Terms of Service</a>
              {' '}and{' '}
              <a href="#" className="text-primary hover:text-primary/80">Privacy Policy</a>
            </span>
          </label>
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
              Create account
              <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
            </>
          )}
        </button>
      </form>

      {/* Divider */}
      <div className="divider my-6 text-base-content/40">or sign up with</div>

      {/* Social logins */}
      <div className="grid grid-cols-1 gap-4">
        <button 
          type="button"
          onClick={onGoogleSignIn}
          className="btn btn-outline border-base-300 hover:bg-base-200 hover:border-primary hover:text-primary gap-2 transition-colors"
          disabled={isLoading}
        >
          <svg className="w-5 h-5" viewBox="0 0 24 24">
            <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
            <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
            <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
            <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
          </svg>
          Continue with Google
        </button>
      </div>

      {/* Sign in link */}
      <p className="mt-6 text-center text-base-content/60">
        Already have an account?{' '}
        <button
          type="button"
          onClick={onSwitchMode}
          className="text-primary font-medium hover:text-primary/80 transition-colors"
          disabled={isLoading}
        >
          Sign in
        </button>
      </p>
    </div>
  )
}
