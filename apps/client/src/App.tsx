import { BrowserRouter, Routes, Route } from 'react-router-dom'
import { AuthProvider } from './components/AuthProvider'
import { AuthGuard, GuestGuard } from './components/AuthGuard'
import ModalManager from './components/ModalManager'
import AuthPage from './pages/AuthPage'
import ResetPasswordPage from './pages/ResetPasswordPage'
import DashboardPage from './pages/DashboardPage'
import ProjectsPage from './pages/ProjectsPage'
import ProjectDetailPage from './pages/ProjectDetailPage'
import WorkExecutionPage from './pages/WorkExecutionPage'
import MyAccountPage from './pages/MyAccountPage'

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
                <DashboardPage />
              </AuthGuard>
            }
          />
          <Route
            path="/documents"
            element={
              <AuthGuard>
                <DashboardPage />
              </AuthGuard>
            }
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
      </AuthProvider>
    </BrowserRouter>
  )
}

export default App
