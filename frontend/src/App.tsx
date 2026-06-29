import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import ErrorBoundary from './components/ErrorBoundary'
import AppLayout from './components/AppLayout'
import ProtectedRoute from './components/ProtectedRoute'
import LoginPage from './pages/LoginPage'
import DashboardPage from './pages/DashboardPage'
import PartnersPage from './pages/PartnersPage'
import PartnerDetailPage from './pages/PartnerDetailPage'
import PartnerFormPage from './pages/PartnerFormPage'
import TasksPage from './pages/TasksPage'
import CreateTaskPage from './pages/CreateTaskPage'
import TaskDetailPage from './pages/TaskDetailPage'
import ArchivePage from './pages/ArchivePage'
import TemplatesPage from './pages/TemplatesPage'
import SubstitutionsPage from './pages/SubstitutionsPage'
import NotificationsPage from './pages/NotificationsPage'
import FinalizedDocsPage from './pages/FinalizedDocsPage'
import InventoryPage from './pages/InventoryPage'
import InventoryDetailPage from './pages/InventoryDetailPage'
import InventoryFormPage from './pages/InventoryFormPage'
import HelpPage from './pages/HelpPage'
import SettingsPage from './pages/SettingsPage'
import PartnerTaskPage from './pages/PartnerTaskPage'
import NotFoundPage from './pages/NotFoundPage'

export default function App() {
  return (
    <BrowserRouter>
      <ErrorBoundary>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/partner/:token" element={<PartnerTaskPage />} />

          <Route element={<ProtectedRoute />}>
            <Route element={<AppLayout />}>
              <Route index element={<Navigate to="/dashboard" replace />} />
              <Route path="dashboard" element={<DashboardPage />} />

              <Route path="partners" element={<PartnersPage />} />
              <Route path="partners/new" element={<PartnerFormPage />} />
              <Route path="partners/:id" element={<PartnerDetailPage />} />
              <Route path="partners/:id/edit" element={<PartnerFormPage />} />

              <Route path="inventory" element={<InventoryPage />} />
              <Route path="inventory/new" element={<InventoryFormPage />} />
              <Route path="inventory/:id" element={<InventoryDetailPage />} />
              <Route path="inventory/:id/edit" element={<InventoryFormPage />} />

              <Route path="tasks" element={<TasksPage />} />
              <Route path="tasks/new" element={<CreateTaskPage />} />
              <Route path="tasks/:id" element={<TaskDetailPage />} />

              <Route path="archive" element={<ArchivePage />} />
              <Route path="templates" element={<TemplatesPage />} />
              <Route path="substitutions" element={<SubstitutionsPage />} />
              <Route path="notifications" element={<NotificationsPage />} />
              <Route path="finalized-docs" element={<FinalizedDocsPage />} />
              <Route path="settings" element={<SettingsPage />} />
              <Route path="help" element={<HelpPage />} />

              <Route path="*" element={<NotFoundPage />} />
            </Route>
          </Route>
        </Routes>
      </ErrorBoundary>
    </BrowserRouter>
  )
}
