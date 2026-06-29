import { useState } from 'react'
import { Card, CardBody } from '../components/ui/Card'
import { useBranding } from '../contexts/BrandingContext'

interface FaqItem {
  question: string
  answer: string
}

interface Section {
  title: string
  icon: string
  items: FaqItem[]
}

const sections: Section[] = [
  {
    title: 'Getting Started',
    icon: '🚀',
    items: [
      {
        question: 'What is this system?',
        answer:
          'This is a Document Management System (DMS) that automates document creation, review, approval, and signing workflows. It connects initiators, managers, lawyers, and external partners in a structured workflow to ensure every document goes through the correct approval chain before being finalized.',
      },
      {
        question: 'How do I log in?',
        answer:
          'Go to the login page and enter your email and password. After successful login you will be redirected to the Dashboard. Your role (Administrator, Manager, Lawyer, or Initiator) determines which actions and pages are available to you.',
      },
      {
        question: 'What does the Dashboard show?',
        answer:
          'The Dashboard gives you a quick overview: tasks pending your action, overdue tasks, recent activity, task status breakdown, and totals for partners and archived documents. Each card links to the relevant page for quick access.',
      },
      {
        question: 'How do I switch between light and dark mode?',
        answer:
          'Click the sun/moon icon in the top-right corner of the navigation bar. Your preference is saved and persists across sessions. The system also respects your browser/OS preference on first visit.',
      },
    ],
  },
  {
    title: 'Roles & Permissions',
    icon: '👥',
    items: [
      {
        question: 'What can an Initiator do?',
        answer:
          'Initiators create new tasks, select document templates, fill in variables, upload documents, submit tasks for approval, negotiate with counterparties during the workflow, and re-upload revised documents when requested. They can also edit documents in Google Docs and view the PDF preview.',
      },
      {
        question: 'What can a Manager do?',
        answer:
          'Managers review and approve or reject tasks at manager review stages. They can add comments, request revisions, and view all task documents. The final manager approval is the last internal step before a document is finalized.',
      },
      {
        question: 'What can a Lawyer do?',
        answer:
          'Lawyers have advanced capabilities: review and approve/reject documents, delegate tasks to another lawyer, add additional reviewers, fast-track urgent tasks (approve immediately without further routing), use AI-powered document analysis, search legal references via the Paragraph integration, and manage partner blacklists.',
      },
      {
        question: 'What can an Administrator do?',
        answer:
          'Administrators have full system access. Through the Admin Panel they can manage users, document categories, templates, workflow routes (including the visual Workflow Builder and AI Flow Builder), substitutions, system settings (branding, integrations, notifications), import data, and oversee all tasks and partner access tokens.',
      },
      {
        question: 'What is the GM (General Manager) role?',
        answer:
          'The GM is a special approval role for high-value tasks. When a task amount exceeds the configured threshold (set in Admin Settings), the workflow automatically routes to the GM for additional approval. The GM user is configured in the system settings.',
      },
    ],
  },
  {
    title: 'Creating & Managing Tasks',
    icon: '📋',
    items: [
      {
        question: 'How do I create a new task?',
        answer:
          'Go to Tasks and click "New Task". Select a document category, choose a partner, pick a workflow route (the step preview shows you the full approval chain), fill in commercial terms, amount, validity dates, and deadline. Then select a document template — the system will show a live PDF preview with your variables filled in. Click "Create Task" to save.',
      },
      {
        question: 'What are document templates?',
        answer:
          'Templates are pre-approved Word documents managed by administrators. They contain placeholders like {{CONTRACTOR_NAME}}, {{COMPANY_NAME}}, {{TASK_NUMBER}}, etc. When you create a task, these placeholders are automatically replaced with real values. The {{TASK_NUMBER}} placeholder is replaced with a unique registration number (e.g. BSC-SER-2026-0042) generated from the Registration Number Prefix in settings.',
      },
      {
        question: 'What template variables are available?',
        answer:
          'Partner variables: {{CONTRACTOR_NAME}}, {{CONTRACTOR_BIN_IIN}}, {{CONTRACTOR_BANK_DETAILS}}, {{CONTRACTOR_EMAIL}}. Company variables: {{COMPANY_NAME}}, {{COMPANY_APP_NAME}}. Task variables: {{TASK_NUMBER}}, {{TASK_CATEGORY}}, {{COMMERCIAL_TERMS}}, {{VALIDITY_FROM}}, {{VALIDITY_TO}}, {{DEADLINE}}, {{CURRENT_DATE}}, {{INITIATOR_NAME}}. Signature placeholders: {{COMPANY_SIGN}}, {{PARTNER_SIGN}} — these remain in the document until the actual signing step.',
      },
      {
        question: 'How do I submit a task for approval?',
        answer:
          'Open a draft task and click "Submit for Approval". The task moves to the first step of the selected workflow route and the appropriate person is notified. You can track the progress via the step indicator at the top of the task detail page.',
      },
      {
        question: 'How do I approve or reject a task?',
        answer:
          'When a task is at your step, open it and you will see action buttons (Approve, Reject, Return for Revision, etc.) depending on the workflow configuration. You can add comments before taking action. Rejected tasks go back to the initiator; tasks returned for revision go to the previous step.',
      },
      {
        question: 'What is fast-tracking?',
        answer:
          'Lawyers can fast-track a task to approve it immediately, bypassing remaining workflow steps. This is useful for urgent documents that need immediate processing. The task goes directly to the "Approved" state.',
      },
      {
        question: 'How does delegation work?',
        answer:
          'Lawyers can delegate a task to another lawyer. The delegated lawyer receives a notification and becomes responsible for the review. This is helpful when a specific legal expertise is needed or for workload balancing.',
      },
      {
        question: 'How do I upload a final version?',
        answer:
          'At certain workflow steps (like "Create Final Version"), you can upload a finalized document. Go to the task detail page and use the "Upload Final Version" option. Supported formats are DOC, DOCX, and PDF.',
      },
    ],
  },
  {
    title: 'Document Editing & Google Docs',
    icon: '📝',
    items: [
      {
        question: 'How do I edit a document in Google Docs?',
        answer:
          'If Google Docs integration is enabled (configured in Settings), you will see an "Edit in Google Docs" button on the task creation page and task detail page. Clicking it opens the document in Google Docs in a new tab. Edit freely, then return to the app — the changes are synced back automatically when you save/create the task.',
      },
      {
        question: 'How do I set up Google Docs integration?',
        answer:
          'Go to Settings > Google Docs Integration. You need a Google Cloud project with the Google Drive API enabled. Create an OAuth 2.0 Client ID (Web application type), add the redirect URI shown on the settings page, then enter the Client ID and Client Secret. Click "Authorize with Google" to complete the connection.',
      },
      {
        question: 'Can I preview documents before creating a task?',
        answer:
          'Yes. After selecting a template and filling in variables on the task creation page, a live PDF preview is generated automatically. You can see exactly how the final document will look with all variables replaced before creating the task.',
      },
    ],
  },
  {
    title: 'Workflow Routes',
    icon: '🔄',
    items: [
      {
        question: 'What are workflow routes?',
        answer:
          'Workflow routes define the approval chain a task must follow. Each route consists of ordered steps (e.g. Manager Review → Lawyer Review → Partner Signing → Final Approval), with transitions that determine how the task moves between steps based on outcomes like "approved", "rejected", or "needs revision".',
      },
      {
        question: 'How do I choose a workflow route?',
        answer:
          'When creating a task, select a workflow route from the dropdown. The step preview below shows all the steps in that route so you can see the full approval chain before creating the task.',
      },
      {
        question: 'Can workflows be customized?',
        answer:
          'Yes. Administrators can create and edit workflow routes in the Admin Panel using the visual Workflow Builder. Each step can be assigned a role (manager, lawyer, initiator, partner, GM), an action type (review, approve, sign, upload, etc.), and conditional transitions (e.g. route to GM if amount exceeds threshold).',
      },
      {
        question: 'What is the AI Flow Builder?',
        answer:
          'In the Admin Panel Workflow Builder, there is an "AI Flow Builder" button. Describe the workflow you need in plain language (e.g. "I need a 5-step flow for service contracts with manager review, lawyer check, partner signing, and final approval") and the AI will generate the complete workflow with steps, transitions, and conditions following best practices.',
      },
    ],
  },
  {
    title: 'Partner Portal & Signing',
    icon: '✍️',
    items: [
      {
        question: 'How do partners access documents?',
        answer:
          'When the workflow reaches a partner step, the system automatically generates a secure link and sends it to the partner via email. The partner can review the document, download the PDF, and either approve, reject, or sign it — all without needing a system account.',
      },
      {
        question: 'How does digital signing work?',
        answer:
          'Partners sign documents using a built-in signature pad on their access page. The signature is drawn on screen, then stamped onto the PDF at the {{PARTNER_SIGN}} placeholder position. The signed PDF becomes a new document version attached to the task.',
      },
      {
        question: 'How does internal (company) signing work?',
        answer:
          'At the appropriate workflow step, authorized users can sign documents using the signature pad on the task detail page. The signature is placed at the {{COMPANY_SIGN}} placeholder position in the PDF.',
      },
      {
        question: 'What is the partner access link?',
        answer:
          'On the task detail page, you can copy the partner access URL. This is a unique, time-limited link (7 days by default) that the partner uses to view and act on the document. The link is also sent automatically via email notification.',
      },
    ],
  },
  {
    title: 'Partners',
    icon: '🏢',
    items: [
      {
        question: 'How do I add a new partner?',
        answer:
          'Go to Partners and click "New Partner". Fill in the company name, BIN/IIN, bank details, email, and other information. You can also check the partner\'s BIN/IIN against the ADATA reliability service if it\'s configured.',
      },
      {
        question: 'What are partner documents?',
        answer:
          'Each partner can have static documents attached to their profile (licenses, certificates, etc.). Go to the partner detail page to upload, view, or delete partner documents. These are separate from task workflow documents.',
      },
      {
        question: 'How does the blacklist work?',
        answer:
          'Lawyers and administrators can blacklist a partner from the partner detail page. A reason must be provided. Blacklisted partners are flagged in the system. Only lawyers and administrators can remove a partner from the blacklist.',
      },
    ],
  },
  {
    title: 'AI Features',
    icon: '🤖',
    items: [
      {
        question: 'What AI features are available?',
        answer:
          'The system integrates with OpenAI (ChatGPT) for several features: Document Analysis (summarizes key terms, risks, gaps, and compliance notes), Document Comparison (compares two document versions highlighting changes), Document Validation (checks completeness against template requirements), and AI Workflow Builder (generates workflow routes from natural language descriptions).',
      },
      {
        question: 'How do I analyze a document with AI?',
        answer:
          'On the task detail page, click the "AI Analyze" button next to a document. The AI will review the document and provide a summary including key terms, potential risks, missing information, financial notes, and compliance observations.',
      },
      {
        question: 'How do I enable AI features?',
        answer:
          'An administrator must configure the OpenAI API key in the Admin Panel under System Settings > AI & Integrations. Once the API key is set and AI comparison is enabled, the AI features become available throughout the system.',
      },
    ],
  },
  {
    title: 'Archive & Finalized Documents',
    icon: '🗄️',
    items: [
      {
        question: 'What is the Archive?',
        answer:
          'The Archive contains all tasks that have been fully approved or archived. You can search by registration number, partner, category, or initiator, filter by year and status, and sort results. An Excel export option lets you download the archive data for reporting.',
      },
      {
        question: 'What are Finalized Documents?',
        answer:
          'Finalized Documents are standalone documents that do not go through the approval workflow — company licenses, court materials, corporate documents, government inspection records, and other important files. They are uploaded directly and organized by category for easy access.',
      },
      {
        question: 'How do I upload a finalized document?',
        answer:
          'Go to Finalized Docs, click "Upload", select the document category, add a title, and upload the file. Office documents (DOC/DOCX) are automatically converted to PDF for viewing.',
      },
    ],
  },
  {
    title: 'Notifications & Deadlines',
    icon: '🔔',
    items: [
      {
        question: 'How do notifications work?',
        answer:
          'The system sends notifications when tasks require your action, when tasks are approved/rejected, when deadlines approach, and when partners respond. Notifications appear as a bell icon badge in the navigation bar. Click the bell to see recent notifications, or go to the Notifications page for the full list.',
      },
      {
        question: 'What happens when a task is overdue?',
        answer:
          'Overdue tasks are highlighted with a red badge in the task list and dashboard. The system sends email reminders for tasks approaching and past their deadlines. Notification settings can be configured by an administrator in the Admin Panel.',
      },
      {
        question: 'Can I mark notifications as read?',
        answer:
          'Yes. You can mark individual notifications as read, mark all as read, or delete notifications. Use the bell icon dropdown for quick actions or the Notifications page for bulk management.',
      },
    ],
  },
  {
    title: 'Substitutions',
    icon: '🔀',
    items: [
      {
        question: 'What are substitutions?',
        answer:
          'When a user is absent (vacation, sick leave, etc.), an administrator can assign a substitute. The substitute receives the absent user\'s approval notifications and can act on their behalf during the substitution period.',
      },
      {
        question: 'How do I see my active substitutions?',
        answer:
          'Go to the Substitutions page to see all active substitutions where you are assigned as the substitute. This shows who you are covering for and the date range.',
      },
      {
        question: 'How are substitutions created?',
        answer:
          'Only administrators can create substitutions through the Admin Panel. They specify the original user, the substitute user, and the date range for the substitution.',
      },
    ],
  },
  {
    title: 'Integrations',
    icon: '🔗',
    items: [
      {
        question: 'What is the ADATA BIN check?',
        answer:
          'When creating or editing a partner, you can check their BIN/IIN against the ADATA reliability service. This returns company information, registration date, and activity status to help verify the partner\'s legitimacy.',
      },
      {
        question: 'What is the Paragraph legal search?',
        answer:
          'On the task detail page, you can search the Paragraph legal database for relevant laws, regulations, and legal references related to your document. This helps lawyers ensure compliance during review.',
      },
      {
        question: 'What is the SAP integration?',
        answer:
          'When enabled, approved tasks are automatically synchronized to SAP. The document registration number, vendor ID, category, and amount are sent to the SAP system. This is configured in the Admin Panel under System Settings.',
      },
      {
        question: 'What are the PDF naming conventions?',
        answer:
          'Downloaded and previewed PDFs are named using the format: partner-category-date.pdf (e.g. bsc-service-contracts-23-03-2026.pdf). The name is built from the partner name, document category, and the task deadline or current date.',
      },
    ],
  },
  {
    title: 'Admin Panel',
    icon: '⚙️',
    items: [
      {
        question: 'How do I access the Admin Panel?',
        answer:
          'Administrators see an "Admin" link in the navigation bar. Click it to open the Filament Admin Panel at /admin. Here you can manage all system entities and settings.',
      },
      {
        question: 'What can I configure in System Settings?',
        answer:
          'System Settings includes: General (Application Name, Company Name, Default Deadline, Registration Number Prefix), Notifications (email toggles, deadline reminders), Workflow (GM threshold, amount limits), and Integrations (OpenAI API key, Google Docs, ADATA, Paragraph, NCA Layer, SAP connection details).',
      },
      {
        question: 'How do I manage document templates?',
        answer:
          'In the Admin Panel, go to Document Templates. You can create, edit, and delete templates. Upload a DOCX file with {{PLACEHOLDER}} variables. Assign each template to a document category. Templates become available to users when creating tasks in that category.',
      },
      {
        question: 'How do I manage users?',
        answer:
          'In the Admin Panel, go to Users. You can create new users, edit existing ones, change their roles, and reset passwords. Each user must have a unique email and an assigned role (Administrator, Manager, Lawyer, or Initiator).',
      },
      {
        question: 'How do I import data from DocLogix?',
        answer:
          'In the Admin Panel, there is a DocLogix Import page where you can upload a CSV file to bulk-import partners or documents into the system.',
      },
    ],
  },
]

function Accordion({ item }: { item: FaqItem }) {
  const [open, setOpen] = useState(false)

  return (
    <div className="border-b border-slate-200 dark:border-slate-700 last:border-b-0">
      <button
        type="button"
        onClick={() => setOpen(!open)}
        className="flex items-center justify-between w-full text-left px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
      >
        <span className="text-sm font-medium text-slate-900 dark:text-white">{item.question}</span>
        <svg
          className={`h-4 w-4 flex-shrink-0 text-slate-400 transition-transform ${open ? 'rotate-180' : ''}`}
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
        </svg>
      </button>
      {open && (
        <div className="px-4 pb-4 text-sm text-slate-600 dark:text-slate-300 leading-relaxed whitespace-pre-line">
          {item.answer}
        </div>
      )}
    </div>
  )
}

export default function HelpPage() {
  const { appName } = useBranding()
  const [search, setSearch] = useState('')

  const filtered = search.trim()
    ? sections
        .map((s) => ({
          ...s,
          items: s.items.filter(
            (i) =>
              i.question.toLowerCase().includes(search.toLowerCase()) ||
              i.answer.toLowerCase().includes(search.toLowerCase()),
          ),
        }))
        .filter((s) => s.items.length > 0)
    : sections

  return (
    <div>
      <div className="mb-8">
        <h1 className="text-2xl font-semibold text-slate-900 dark:text-white">Help & User Manual</h1>
        <p className="text-slate-500 dark:text-slate-400 mt-1">
          Complete guide for using {appName}. Search or browse by topic.
        </p>
      </div>

      <div className="max-w-3xl">
        <div className="mb-6">
          <div className="relative">
            <svg
              className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search help topics..."
              className="w-full pl-10 pr-4 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
            {search && (
              <button
                type="button"
                onClick={() => setSearch('')}
                className="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
              >
                <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            )}
          </div>
          {search && (
            <p className="text-xs text-slate-400 mt-2">
              {filtered.reduce((acc, s) => acc + s.items.length, 0)} result(s) found
            </p>
          )}
        </div>

        <div className="space-y-6">
          {filtered.map((section) => (
            <div key={section.title}>
              <h2 className="text-lg font-semibold text-slate-900 dark:text-white mb-3 flex items-center gap-2">
                <span>{section.icon}</span>
                <span>{section.title}</span>
              </h2>
              <Card>
                <CardBody className="p-0">
                  {section.items.map((item, idx) => (
                    <Accordion key={idx} item={item} />
                  ))}
                </CardBody>
              </Card>
            </div>
          ))}

          {filtered.length === 0 && (
            <div className="text-center py-12 text-slate-400 dark:text-slate-500">
              <svg className="h-12 w-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <p>No results found for "{search}"</p>
              <button
                type="button"
                onClick={() => setSearch('')}
                className="mt-2 text-blue-500 hover:text-blue-600 text-sm"
              >
                Clear search
              </button>
            </div>
          )}
        </div>

        <Card className="mt-8">
          <CardBody>
            <h2 className="text-lg font-semibold text-slate-900 dark:text-white mb-3 flex items-center gap-2">
              <span>📌</span>
              <span>Quick Reference: Template Variables</span>
            </h2>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-slate-200 dark:border-slate-700">
                    <th className="text-left py-2 text-slate-500 dark:text-slate-400 font-medium">Variable</th>
                    <th className="text-left py-2 text-slate-500 dark:text-slate-400 font-medium">Description</th>
                  </tr>
                </thead>
                <tbody>
                  {[
                    ['{{CONTRACTOR_NAME}}', 'Partner company name'],
                    ['{{CONTRACTOR_BIN_IIN}}', 'Partner BIN/IIN number'],
                    ['{{CONTRACTOR_BANK_DETAILS}}', 'Partner bank details'],
                    ['{{CONTRACTOR_EMAIL}}', 'Partner email address'],
                    ['{{COMPANY_NAME}}', 'Your company legal name (from settings)'],
                    ['{{COMPANY_APP_NAME}}', 'Application name (from settings)'],
                    ['{{TASK_NUMBER}}', 'Unique registration number (auto-generated)'],
                    ['{{TASK_CATEGORY}}', 'Document category name'],
                    ['{{COMMERCIAL_TERMS}}', 'Commercial terms text'],
                    ['{{VALIDITY_FROM}}', 'Contract start date'],
                    ['{{VALIDITY_TO}}', 'Contract end date'],
                    ['{{DEADLINE}}', 'Task deadline date'],
                    ['{{CURRENT_DATE}}', "Today's date"],
                    ['{{INITIATOR_NAME}}', 'Name of the person who created the task'],
                    ['{{COMPANY_SIGN}}', 'Company signature placeholder (filled when signed)'],
                    ['{{PARTNER_SIGN}}', 'Partner signature placeholder (filled when signed)'],
                  ].map(([variable, desc]) => (
                    <tr key={variable} className="border-b border-slate-100 dark:border-slate-700/50">
                      <td className="py-2 font-mono text-xs text-blue-600 dark:text-blue-400">{variable}</td>
                      <td className="py-2 text-slate-600 dark:text-slate-300">{desc}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardBody>
        </Card>

        <Card className="mt-6">
          <CardBody>
            <h2 className="text-lg font-semibold text-slate-900 dark:text-white mb-3 flex items-center gap-2">
              <span>🔑</span>
              <span>Keyboard Shortcuts</span>
            </h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
              {[
                ['Ctrl + K', 'Global search (Admin Panel)'],
                ['Ctrl + Shift + R', 'Hard refresh (clear browser cache)'],
              ].map(([keys, desc]) => (
                <div key={keys} className="flex items-center gap-3">
                  <kbd className="px-2 py-1 rounded bg-slate-100 dark:bg-slate-700 text-xs font-mono text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-600">
                    {keys}
                  </kbd>
                  <span className="text-slate-600 dark:text-slate-300">{desc}</span>
                </div>
              ))}
            </div>
          </CardBody>
        </Card>
      </div>
    </div>
  )
}
