import { useState, useRef, useEffect } from 'react'
import { ChevronDown, Check, Plus, Building2 } from 'lucide-react'
import { useAuthStore, Company } from '../stores/auth'
import { useModalStore, MODAL_NAMES } from '../stores/modal'

export default function CompanySwitcher() {
  const { user, company, setActiveCompany } = useAuthStore()
  const { openModal } = useModalStore()
  const [isOpen, setIsOpen] = useState(false)
  const dropdownRef = useRef<HTMLDivElement>(null)

  // Get all companies from user
  const companies = user?.companies || []

  // Close dropdown when clicking outside
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  const handleCompanySelect = (selectedCompany: Company) => {
    setActiveCompany(selectedCompany, true)
    setIsOpen(false)
  }

  const handleCreateCompany = () => {
    setIsOpen(false)
    openModal(MODAL_NAMES.CREATE_COMPANY, {
      onSuccess: (newCompany: Company) => {
        // Automatically switch to the newly created company
        setActiveCompany(newCompany, true)
      }
    })
  }

  // If no company is selected, show a prompt
  if (!company) {
    return (
      <button
        onClick={handleCreateCompany}
        className="flex items-center gap-2 px-3 py-2 rounded-xl hover:bg-base-200 transition-colors"
      >
        <Building2 className="w-5 h-5 text-primary" />
        <span className="font-semibold text-base-content">Create Company</span>
      </button>
    )
  }

  return (
    <div className="relative" ref={dropdownRef}>
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center gap-2 px-3 py-2 rounded-xl hover:bg-base-200 transition-colors group"
      >
        {/* Company Logo/Avatar */}
        <div className="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center overflow-hidden flex-shrink-0">
          {company.logo_url ? (
            <img
              src={company.logo_url}
              alt={company.name}
              className="w-full h-full object-cover"
            />
          ) : (
            <span className="text-sm font-semibold text-primary">
              {company.name.charAt(0).toUpperCase()}
            </span>
          )}
        </div>

        {/* Company Name */}
        <span className="font-semibold text-base-content max-w-[200px] truncate">
          {company.name}
        </span>

        {/* Dropdown Arrow */}
        <ChevronDown
          className={`w-4 h-4 text-base-content/60 transition-transform duration-200 ${
            isOpen ? 'rotate-180' : ''
          }`}
        />
      </button>

      {/* Dropdown Menu */}
      {isOpen && (
        <div className="absolute left-0 top-full mt-2 w-72 bg-base-200 rounded-xl shadow-xl border border-base-300 py-2 z-50 animate-in fade-in slide-in-from-top-2 duration-200">
          {/* Companies List */}
          <div className="max-h-64 overflow-y-auto">
            {companies.length > 0 ? (
              companies.map((c) => {
                const isActive = c.id === company.id
                return (
                  <button
                    key={c.id}
                    onClick={() => handleCompanySelect(c)}
                    className={`w-full flex items-center gap-3 px-4 py-3 hover:bg-base-300 transition-colors ${
                      isActive ? 'bg-base-300/50' : ''
                    }`}
                  >
                    {/* Company Logo/Avatar */}
                    <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center overflow-hidden flex-shrink-0">
                      {c.logo_url ? (
                        <img
                          src={c.logo_url}
                          alt={c.name}
                          className="w-full h-full object-cover"
                        />
                      ) : (
                        <span className="text-base font-semibold text-primary">
                          {c.name.charAt(0).toUpperCase()}
                        </span>
                      )}
                    </div>

                    {/* Company Info */}
                    <div className="flex-1 text-left min-w-0">
                      <p className="font-medium text-base-content truncate">{c.name}</p>
                      <div className="flex items-center gap-2 mt-0.5">
                        <span className="text-xs text-base-content/50">/{c.slug}</span>
                        {c.pivot?.role_in_company && (
                          <span className="text-xs px-1.5 py-0.5 rounded bg-base-100 text-base-content/60">
                            {c.pivot.role_in_company}
                          </span>
                        )}
                      </div>
                    </div>

                    {/* Active Indicator */}
                    {isActive && (
                      <Check className="w-4 h-4 text-primary flex-shrink-0" />
                    )}
                  </button>
                )
              })
            ) : (
              <div className="px-4 py-3 text-center text-base-content/50">
                <p className="text-sm">No other companies</p>
              </div>
            )}
          </div>

          {/* Divider */}
          <div className="border-t border-base-300 my-2" />

          {/* Create New Company */}
          <button
            onClick={handleCreateCompany}
            className="w-full flex items-center gap-3 px-4 py-3 hover:bg-base-300 transition-colors text-primary"
          >
            <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0">
              <Plus className="w-5 h-5" />
            </div>
            <span className="font-medium">Create new company</span>
          </button>
        </div>
      )}
    </div>
  )
}

