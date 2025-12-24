import { BrowserRouter, Routes, Route } from 'react-router-dom'
import { AuthProvider } from './components/AuthProvider'
import { AuthGuard, GuestGuard, OwnerGuard } from './components/AuthGuard'
import ModalManager from './components/ModalManager'
import FlowManager from './components/FlowManager'
import AuthPage from './pages/AuthPage'
import ResetPasswordPage from './pages/ResetPasswordPage'
import DashboardPage from './pages/DashboardPage'
import ProjectsPage from './pages/ProjectsPage'
import ProjectDetailPage from './pages/ProjectDetailPage'
import WorkExecutionPage from './pages/WorkExecutionPage'
import MyAccountPage from './pages/MyAccountPage'
import CRMPage from './pages/CRMPage'
import ContactDetailPage from './pages/ContactDetailPage'
import DealsPage from './pages/DealsPage'
import SkillsPage from './pages/SkillsPage'
import { 
  DocumentsLandingPage,
  ContractsPage, 
  ContractTemplatesPage,
  ContractTemplateBuilderPage,
  InvoicesPage, 
  CreateContractPage, 
  CreateInvoicePage,
  ContractDetailPage,
  InvoiceDetailPage,
  DocumentSettingsPage 
} from './pages/documents'
import { PublicContractPage, PublicInvoicePage } from './pages/public'

function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          {/* Public routes (redirect to dashboard if authenticated) */}
          <Route
            path="/"
            element={
              <GuestGuard>
                <AuthPage />
              </GuestGuard>
            }
          />
          <Route
            path="/login"
            element={
              <GuestGuard>
                <AuthPage />
              </GuestGuard>
            }
          />
          <Route
            path="/signup"
            element={
              <GuestGuard>
                <AuthPage initialMode="signup" />
              </GuestGuard>
            }
          />
          <Route
            path="/reset-password"
            element={<ResetPasswordPage />}
          />

          {/* Protected routes (require authentication) */}
          <Route
            path="/dashboard"
            element={
              <AuthGuard>
                <DashboardPage />
              </AuthGuard>
            }
          />
          <Route
            path="/account"
            element={
              <AuthGuard>
                <MyAccountPage />
              </AuthGuard>
            }
          />
          <Route
            path="/projects"
            element={
              <AuthGuard>
                <ProjectsPage />
              </AuthGuard>
            }
          />
          <Route
            path="/projects/:id"
            element={
              <AuthGuard>
                <ProjectDetailPage />
              </AuthGuard>
            }
          />

          {/* Additional protected routes */}
          <Route
            path="/tasks"
            element={
              <AuthGuard>
                <WorkExecutionPage />
              </AuthGuard>
            }
          />
          <Route
            path="/crm"
            element={
              <AuthGuard>
                <CRMPage />
              </AuthGuard>
            }
          />
          <Route
            path="/crm/contacts/:id"
            element={
              <AuthGuard>
                <ContactDetailPage />
              </AuthGuard>
            }
          />
          <Route
            path="/deals"
            element={
              <AuthGuard>
                <DealsPage />
              </AuthGuard>
            }
          />
          <Route
            path="/skills"
            element={
              <OwnerGuard>
                <SkillsPage />
              </OwnerGuard>
            }
          />
          <Route
            path="/documents"
            element={
              <AuthGuard>
                <DocumentsLandingPage />
              </AuthGuard>
            }
          />
          <Route
            path="/documents/contracts"
            element={
              <AuthGuard>
                <ContractsPage />
              </AuthGuard>
            }
          />
          <Route
            path="/documents/contracts/create"
            element={
              <AuthGuard>
                <CreateContractPage />
              </AuthGuard>
            }
          />
          <Route
            path="/documents/contracts/templates"
            element={
              <AuthGuard>
                <ContractTemplatesPage />
              </AuthGuard>
            }
          />
          <Route
            path="/documents/contracts/templates/:id"
            element={
              <AuthGuard>
                <ContractTemplateBuilderPage />
              </AuthGuard>
            }
          />
          <Route
            path="/documents/contracts/:id"
            element={
              <AuthGuard>
                <ContractDetailPage />
              </AuthGuard>
            }
          />
          <Route
            path="/documents/invoices"
            element={
              <AuthGuard>
                <InvoicesPage />
              </AuthGuard>
            }
          />
          <Route
            path="/documents/invoices/create"
            element={
              <AuthGuard>
                <CreateInvoicePage />
              </AuthGuard>
            }
          />
          <Route
            path="/documents/invoices/:id"
            element={
              <AuthGuard>
                <InvoiceDetailPage />
              </AuthGuard>
            }
          />
          <Route
            path="/documents/settings"
            element={
              <AuthGuard>
                <DocumentSettingsPage />
              </AuthGuard>
            }
          />
          
          {/* Public routes for contracts and invoices (token-based auth) */}
          <Route
            path="/public/contracts/:token"
            element={<PublicContractPage />}
          />
          <Route
            path="/public/invoices/:token"
            element={<PublicInvoicePage />}
          />
          <Route
            path="/calendar"
            element={
              <AuthGuard>
                <DashboardPage />
              </AuthGuard>
            }
          />
          <Route
            path="/settings"
            element={
              <AuthGuard>
                <DashboardPage />
              </AuthGuard>
            }
          />
        </Routes>
        
        {/* Global Modal Manager - renders modals at app root level */}
        <ModalManager />
        
        {/* Global Flow Manager - renders flow prompt modals */}
        <FlowManager />
      </AuthProvider>
    </BrowserRouter>
  )
}

export default App
