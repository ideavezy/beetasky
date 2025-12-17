import { useState, useEffect } from 'react'
import {
  User,
  Lock,
  Shield,
  Mail,
  Phone,
  Save,
  Eye,
  EyeOff,
  Smartphone,
  Copy,
  Check,
  AlertCircle,
  CheckCircle,
  Loader2,
} from 'lucide-react'
import Layout from '../components/Layout'
import { useAuthStore } from '../stores/auth'

type TabId = 'account' | 'password' | '2fa'

const tabs: { id: TabId; label: string; icon: React.ElementType }[] = [
  { id: 'account', label: 'Account', icon: User },
  { id: 'password', label: 'Password', icon: Lock },
  { id: '2fa', label: '2FA', icon: Shield },
]

export default function MyAccountPage() {
  const { 
    user, 
    isLoading, 
    error, 
    successMessage,
    isTwoFactorEnabled,
    twoFactorEnrollment,
    updateProfile,
    updatePassword,
    check2FAStatus,
    enroll2FA,
    verify2FA,
    unenroll2FA,
    clearError,
    clearSuccessMessage,
  } = useAuthStore()
  
  const [activeTab, setActiveTab] = useState<TabId>('account')

  // Account form state
  const [accountForm, setAccountForm] = useState({
    email: '',
    firstName: '',
    lastName: '',
    phone: '',
  })
  const [accountSaving, setAccountSaving] = useState(false)

  // Password form state
  const [passwordForm, setPasswordForm] = useState({
    newPassword: '',
    confirmPassword: '',
  })
  const [showNewPassword, setShowNewPassword] = useState(false)
  const [showConfirmPassword, setShowConfirmPassword] = useState(false)
  const [passwordSaving, setPasswordSaving] = useState(false)
  const [passwordError, setPasswordError] = useState<string | null>(null)
  const [passwordSuccess, setPasswordSuccess] = useState<string | null>(null)

  // 2FA state
  const [verificationCode, setVerificationCode] = useState('')
  const [copied, setCopied] = useState(false)
  const [factorId, setFactorId] = useState<string | null>(null)

  // Sync form with user data
  useEffect(() => {
    if (user) {
      setAccountForm({
        email: user.email || '',
        firstName: user.first_name || '',
        lastName: user.last_name || '',
        phone: user.phone || '',
      })
    }
  }, [user])

  // Check 2FA status on mount (only once)
  useEffect(() => {
    let mounted = true
    const checkStatus = async () => {
      if (mounted) {
        await check2FAStatus()
      }
    }
    checkStatus()
    return () => {
      mounted = false
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // Clear messages when switching tabs
  useEffect(() => {
    clearError()
    clearSuccessMessage()
    setPasswordError(null)
    setPasswordSuccess(null)
  }, [activeTab])

  const handleAccountSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setAccountSaving(true)
    clearError()
    clearSuccessMessage()
    
    await updateProfile({
      first_name: accountForm.firstName,
      last_name: accountForm.lastName,
      email: accountForm.email,
      phone: accountForm.phone,
    })
    
    setAccountSaving(false)
  }

  const handlePasswordSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setPasswordError(null)
    setPasswordSuccess(null)
    
    // Validation
    if (passwordForm.newPassword.length < 8) {
      setPasswordError('Password must be at least 8 characters long')
      return
    }
    
    if (passwordForm.newPassword !== passwordForm.confirmPassword) {
      setPasswordError('Passwords do not match')
      return
    }
    
    setPasswordSaving(true)
    
    const success = await updatePassword(passwordForm.newPassword)
    
    if (success) {
      setPasswordSuccess('Password updated successfully!')
      setPasswordForm({ newPassword: '', confirmPassword: '' })
    }
    
    setPasswordSaving(false)
  }

  const handleEnable2FA = async () => {
    const enrollment = await enroll2FA()
    if (enrollment) {
      setFactorId(enrollment.id)
    }
  }

  const handleVerify2FA = async (e: React.FormEvent) => {
    e.preventDefault()
    
    if (!factorId) return
    
    const success = await verify2FA(factorId, verificationCode)
    
    if (success) {
      setVerificationCode('')
      setFactorId(null)
    }
  }

  const handleDisable2FA = async () => {
    // Get the current factor ID
    const { data } = await import('../lib/supabase').then(m => m.supabase.auth.mfa.listFactors())
    const verifiedFactor = data?.totp.find(f => f.status === 'verified')
    
    if (verifiedFactor) {
      await unenroll2FA(verifiedFactor.id)
    }
  }

  const handleCancel2FASetup = () => {
    setFactorId(null)
    setVerificationCode('')
    clearError()
  }

  const copySecretKey = () => {
    if (twoFactorEnrollment?.totp.secret) {
      navigator.clipboard.writeText(twoFactorEnrollment.totp.secret)
      setCopied(true)
      setTimeout(() => setCopied(false), 2000)
    }
  }

  const renderAccountTab = () => (
    <form onSubmit={handleAccountSubmit} className="space-y-6">
      {/* Success/Error Messages */}
      {successMessage && (
        <div className="alert alert-success">
          <CheckCircle className="w-5 h-5" />
          <span>{successMessage}</span>
        </div>
      )}
      {error && (
        <div className="alert alert-error">
          <AlertCircle className="w-5 h-5" />
          <span>{error}</span>
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* First Name */}
        <div className="form-control">
          <label className="label">
            <span className="label-text font-medium">First Name</span>
          </label>
          <div className="relative">
            <User className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-base-content/40" />
            <input
              type="text"
              value={accountForm.firstName}
              onChange={(e) => setAccountForm({ ...accountForm, firstName: e.target.value })}
              className="input input-bordered w-full pl-11 bg-base-100 focus:border-primary focus:outline-none"
              placeholder="Enter first name"
              required
            />
          </div>
        </div>

        {/* Last Name */}
        <div className="form-control">
          <label className="label">
            <span className="label-text font-medium">Last Name</span>
          </label>
          <div className="relative">
            <User className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-base-content/40" />
            <input
              type="text"
              value={accountForm.lastName}
              onChange={(e) => setAccountForm({ ...accountForm, lastName: e.target.value })}
              className="input input-bordered w-full pl-11 bg-base-100 focus:border-primary focus:outline-none"
              placeholder="Enter last name"
            />
          </div>
        </div>
      </div>

      {/* Email */}
      <div className="form-control">
        <label className="label">
          <span className="label-text font-medium">Email Address</span>
        </label>
        <div className="relative">
          <Mail className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-base-content/40" />
          <input
            type="email"
            value={accountForm.email}
            onChange={(e) => setAccountForm({ ...accountForm, email: e.target.value })}
            className="input input-bordered w-full pl-11 bg-base-100 focus:border-primary focus:outline-none"
            placeholder="Enter email address"
            required
          />
        </div>
        <label className="label">
          <span className="label-text-alt text-base-content/60">
            Changing your email will require confirmation via the new email address
          </span>
        </label>
      </div>

      {/* Phone */}
      <div className="form-control">
        <label className="label">
          <span className="label-text font-medium">Phone Number</span>
        </label>
        <div className="relative">
          <Phone className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-base-content/40" />
          <input
            type="tel"
            value={accountForm.phone}
            onChange={(e) => setAccountForm({ ...accountForm, phone: e.target.value })}
            className="input input-bordered w-full pl-11 bg-base-100 focus:border-primary focus:outline-none"
            placeholder="Enter phone number"
          />
        </div>
      </div>

      {/* Save Button */}
      <div className="flex justify-end pt-4">
        <button 
          type="submit" 
          className="btn btn-primary gap-2"
          disabled={accountSaving || isLoading}
        >
          {accountSaving ? (
            <Loader2 className="w-4 h-4 animate-spin" />
          ) : (
            <Save className="w-4 h-4" />
          )}
          {accountSaving ? 'Saving...' : 'Save Changes'}
        </button>
      </div>
    </form>
  )

  const renderPasswordTab = () => (
    <form onSubmit={handlePasswordSubmit} className="space-y-6">
      {/* Success/Error Messages */}
      {passwordSuccess && (
        <div className="alert alert-success">
          <CheckCircle className="w-5 h-5" />
          <span>{passwordSuccess}</span>
        </div>
      )}
      {(passwordError || error) && (
        <div className="alert alert-error">
          <AlertCircle className="w-5 h-5" />
          <span>{passwordError || error}</span>
        </div>
      )}

      {/* New Password */}
      <div className="form-control">
        <label className="label">
          <span className="label-text font-medium">New Password</span>
        </label>
        <div className="relative">
          <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-base-content/40" />
          <input
            type={showNewPassword ? 'text' : 'password'}
            value={passwordForm.newPassword}
            onChange={(e) => setPasswordForm({ ...passwordForm, newPassword: e.target.value })}
            className="input input-bordered w-full pl-11 pr-11 bg-base-100 focus:border-primary focus:outline-none"
            placeholder="Enter new password"
            required
            minLength={8}
          />
          <button
            type="button"
            onClick={() => setShowNewPassword(!showNewPassword)}
            className="absolute right-3 top-1/2 -translate-y-1/2 text-base-content/40 hover:text-base-content transition-colors"
          >
            {showNewPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
          </button>
        </div>
        <label className="label">
          <span className="label-text-alt text-base-content/60">
            Password must be at least 8 characters
          </span>
        </label>
      </div>

      {/* Confirm New Password */}
      <div className="form-control">
        <label className="label">
          <span className="label-text font-medium">Confirm New Password</span>
        </label>
        <div className="relative">
          <Lock className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-base-content/40" />
          <input
            type={showConfirmPassword ? 'text' : 'password'}
            value={passwordForm.confirmPassword}
            onChange={(e) => setPasswordForm({ ...passwordForm, confirmPassword: e.target.value })}
            className="input input-bordered w-full pl-11 pr-11 bg-base-100 focus:border-primary focus:outline-none"
            placeholder="Confirm new password"
            required
          />
          <button
            type="button"
            onClick={() => setShowConfirmPassword(!showConfirmPassword)}
            className="absolute right-3 top-1/2 -translate-y-1/2 text-base-content/40 hover:text-base-content transition-colors"
          >
            {showConfirmPassword ? <EyeOff className="w-5 h-5" /> : <Eye className="w-5 h-5" />}
          </button>
        </div>
      </div>

      {/* Update Password Button */}
      <div className="flex justify-end pt-4">
        <button 
          type="submit" 
          className="btn btn-primary gap-2"
          disabled={passwordSaving || isLoading}
        >
          {passwordSaving ? (
            <Loader2 className="w-4 h-4 animate-spin" />
          ) : (
            <Lock className="w-4 h-4" />
          )}
          {passwordSaving ? 'Updating...' : 'Update Password'}
        </button>
      </div>
    </form>
  )

  const render2FATab = () => (
    <div className="space-y-6">
      {/* Success/Error Messages */}
      {successMessage && (
        <div className="alert alert-success">
          <CheckCircle className="w-5 h-5" />
          <span>{successMessage}</span>
        </div>
      )}
      {error && (
        <div className="alert alert-error">
          <AlertCircle className="w-5 h-5" />
          <span>{error}</span>
        </div>
      )}

      {/* 2FA Status Card */}
      <div className={`p-6 rounded-2xl border ${isTwoFactorEnabled ? 'bg-success/10 border-success/30' : 'bg-base-100 border-base-300'}`}>
        <div className="flex items-start gap-4">
          <div className={`w-12 h-12 rounded-xl flex items-center justify-center ${isTwoFactorEnabled ? 'bg-success/20 text-success' : 'bg-base-200 text-base-content/60'}`}>
            <Shield className="w-6 h-6" />
          </div>
          <div className="flex-1">
            <h3 className="font-semibold text-lg">Two-Factor Authentication</h3>
            <p className="text-base-content/60 mt-1">
              {isTwoFactorEnabled
                ? 'Your account is protected with two-factor authentication using an authenticator app.'
                : 'Add an extra layer of security to your account by enabling two-factor authentication.'}
            </p>
            <div className="mt-4">
              {isTwoFactorEnabled ? (
                <button
                  onClick={handleDisable2FA}
                  className="btn btn-outline btn-error btn-sm"
                  disabled={isLoading}
                >
                  {isLoading ? <Loader2 className="w-4 h-4 animate-spin" /> : 'Disable 2FA'}
                </button>
              ) : !factorId ? (
                <button
                  onClick={handleEnable2FA}
                  className="btn btn-primary btn-sm gap-2"
                  disabled={isLoading}
                >
                  {isLoading ? (
                    <Loader2 className="w-4 h-4 animate-spin" />
                  ) : (
                    <Smartphone className="w-4 h-4" />
                  )}
                  Enable 2FA
                </button>
              ) : null}
            </div>
          </div>
          <div className={`badge ${isTwoFactorEnabled ? 'badge-success' : 'badge-ghost'} badge-lg`}>
            {isTwoFactorEnabled ? 'Enabled' : 'Disabled'}
          </div>
        </div>
      </div>

      {/* QR Code Setup Section */}
      {factorId && twoFactorEnrollment && !isTwoFactorEnabled && (
        <div className="p-6 rounded-2xl bg-base-100 border border-base-300 space-y-6">
          <div>
            <h3 className="font-semibold text-lg mb-2">Set Up Authenticator App</h3>
            <p className="text-base-content/60 text-sm">
              Scan the QR code below with your authenticator app (Google Authenticator, Authy, 1Password, etc.), or manually enter the secret key.
            </p>
          </div>

          {/* QR Code */}
          <div className="flex flex-col items-center gap-4">
            <div className="w-48 h-48 bg-white p-2 rounded-xl flex items-center justify-center">
              <img 
                src={twoFactorEnrollment.totp.qr_code} 
                alt="2FA QR Code"
                className="w-full h-full"
              />
            </div>

            {/* Secret Key */}
            <div className="w-full max-w-sm">
              <label className="label">
                <span className="label-text text-sm font-medium">Or enter this key manually:</span>
              </label>
              <div className="flex gap-2">
                <input
                  type="text"
                  value={twoFactorEnrollment.totp.secret}
                  readOnly
                  className="input input-bordered flex-1 bg-base-200 font-mono text-sm"
                />
                <button
                  onClick={copySecretKey}
                  className="btn btn-square btn-outline"
                  type="button"
                >
                  {copied ? <Check className="w-4 h-4 text-success" /> : <Copy className="w-4 h-4" />}
                </button>
              </div>
            </div>
          </div>

          {/* Verification Form */}
          <form onSubmit={handleVerify2FA} className="space-y-4">
            <div className="form-control">
              <label className="label">
                <span className="label-text font-medium">Enter Verification Code</span>
              </label>
              <input
                type="text"
                value={verificationCode}
                onChange={(e) => setVerificationCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                className="input input-bordered w-full max-w-sm bg-base-100 focus:border-primary focus:outline-none text-center text-2xl tracking-widest font-mono"
                placeholder="000000"
                maxLength={6}
                autoComplete="one-time-code"
              />
              <label className="label">
                <span className="label-text-alt text-base-content/60">
                  Enter the 6-digit code from your authenticator app
                </span>
              </label>
            </div>

            <div className="flex gap-3">
              <button
                type="button"
                onClick={handleCancel2FASetup}
                className="btn btn-ghost"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={verificationCode.length !== 6 || isLoading}
                className="btn btn-primary"
              >
                {isLoading ? (
                  <Loader2 className="w-4 h-4 animate-spin" />
                ) : (
                  'Verify & Enable'
                )}
              </button>
            </div>
          </form>
        </div>
      )}

      {/* Recovery Codes Info */}
      {isTwoFactorEnabled && (
        <div className="p-6 rounded-2xl bg-base-100 border border-base-300">
          <div className="flex items-start gap-4">
            <div className="w-10 h-10 rounded-lg bg-warning/20 flex items-center justify-center text-warning">
              <Lock className="w-5 h-5" />
            </div>
            <div>
              <h4 className="font-medium">Security Tip</h4>
              <p className="text-base-content/60 text-sm mt-1">
                Make sure to keep your authenticator app backed up. If you lose access to your authenticator, you may lose access to your account.
              </p>
            </div>
          </div>
        </div>
      )}
    </div>
  )

  const renderTabContent = () => {
    switch (activeTab) {
      case 'account':
        return renderAccountTab()
      case 'password':
        return renderPasswordTab()
      case '2fa':
        return render2FATab()
      default:
        return null
    }
  }

  return (
    <Layout>
      <div className="max-w-4xl mx-auto py-2">
        {/* Tabs */}
        <div className="flex gap-1 p-1 bg-base-200 rounded-2xl mb-8 overflow-x-auto">
          {tabs.map((tab) => {
            const Icon = tab.icon
            const isActive = activeTab === tab.id
            return (
              <button
                key={tab.id}
                onClick={() => setActiveTab(tab.id)}
                className={`flex-1 min-w-[100px] flex items-center justify-center gap-2 px-4 py-3 rounded-xl font-medium transition-all ${
                  isActive
                    ? 'bg-base-100 text-base-content shadow-sm'
                    : 'text-base-content/60 hover:text-base-content'
                }`}
              >
                <Icon className="w-4 h-4" />
                <span className="hidden sm:inline">{tab.label}</span>
              </button>
            )
          })}
        </div>

        {/* Tab Content */}
        <div className="bg-base-200 rounded-2xl p-6 lg:p-8">
          {renderTabContent()}
        </div>
      </div>
    </Layout>
  )
}
