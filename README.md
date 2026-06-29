# EFES Kazakhstan Document Management System

A production-ready document management system for EFES Kazakhstan, built with React 19 + Vite 7 frontend and Laravel 12 + Filament 4 backend.

## Architecture

- **Frontend**: React 19, TypeScript, Vite 7, Tailwind CSS v4, TanStack Query, React Router v7
- **Backend**: Laravel 12, PHP 8.3+, Sanctum (SPA auth), Filament 4 (admin panel)
- **Database**: SQLite (default), PostgreSQL (recommended for production)
- **Queue**: Database driver (default), Redis (recommended for production)

## Quick Start

### Prerequisites

- PHP 8.3+ with extensions: `pdo_sqlite`, `mbstring`, `openssl`, `tokenizer`, `xml`
- Composer 2.x
- Node.js 20+ and npm 10+

### Backend Setup

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Backend runs at `http://127.0.0.1:8000`

### Frontend Setup

```bash
cd frontend
npm install
cp .env.example .env
npm run dev
```

Frontend runs at `http://localhost:5173`

### Default Test Accounts

| Role          | Email              | Password  |
|---------------|--------------------|-----------|
| Administrator | admin@efes.kz      | password  |
| Initiator     | initiator@efes.kz  | password  |
| Manager       | manager@efes.kz    | password  |
| Lawyer        | lawyer@efes.kz     | password  |

Admin panel: `http://127.0.0.1:8000/admin` (admin role required)

## User Roles

- **Initiator**: Creates tasks, uploads documents, negotiates with counterparties, re-uploads signed versions
- **Manager**: Reviews and approves/rejects tasks at initial and final stages
- **Lawyer / Super User**: Legal review, delegates tasks, adds reviewers, fast-tracks approvals, AI document analysis
- **Administrator**: Full system access, manages users, categories, templates, substitutions

## Workflow

### Standard Route (6 steps)

1. **Initiator** creates and submits task
2. **Manager** reviews and approves (sends to lawyer)
3. **Lawyer** reviews, edits, approves (sends back to initiator)
4. **Initiator** negotiates externally, re-uploads signed document
5. **Lawyer** final review of signed version
6. **Manager** final approval -> Document archived with registration number

### Simplified Route (2 steps)

1. **Initiator** creates and submits task
2. **Manager** approves -> Document archived with registration number

### Lawyer Special Actions

- **Fast-track**: Approve immediately without further routing
- **Delegate**: Reassign to another lawyer
- **Add Reviewer**: Include additional reviewers

## Features

### Partner Management
- Create/edit partner records with BIN/IIN, bank details, email
- BIN/IIN uniqueness validation
- Statutory document upload/download/delete
- ADATA reliability check integration (stub)
- Blacklist/unblacklist with mandatory reason (lawyer only)

### Task & Document Management
- Template-based and non-template document flows
- Document versioning with download links
- Signed document upload for AI comparison
- Workflow step indicator showing progress
- Deadline tracking with overdue warnings
- Commercial terms and validity period tracking

### Archive & Export
- Browse approved/archived documents
- Filter by year, category, partner
- Excel export via Maatwebsite/Excel

### Finalized Documents
- Upload documents without approval workflow (licenses, court materials, corporate docs)
- Categorized browsing and download

### Notifications
- In-app notification bell with unread count
- Full notifications page with mark-read functionality
- Email notifications on workflow transitions
- Deadline reminder scheduled command

### Substitutions
- Acting approver management (admin panel)
- Active substitutions visible to substitute users

### UI/UX
- Dark mode toggle with system preference detection
- Mobile-responsive layout with hamburger menu
- Loading skeletons and toast notifications
- Reusable design system components
- Print-friendly stylesheets
- Help/manual page with FAQ

### Admin Panel (Filament)
- User management with role assignment
- Document categories and templates
- Partner management
- Task overview (read-only)
- Substitution management

## Integration Stubs

The following integrations are stubbed and ready for production implementation:

- **ADATA**: `GET /api/integrations/adata/check/{bin}` - Government reliability check
- **Paragraph**: `GET /api/integrations/paragraph/search` - Legal database search
- **DocLogix**: `POST /api/integrations/doclogix/import` - Document migration
- **E-signature**: `GET /api/integrations/esign/status` - NCALayer integration
- **AI Document Comparison**: `POST /api/documents/compare` - Signed vs. approved analysis

## API Endpoints

### Authentication
- `POST /api/login` - Login (rate limited: 5/min)
- `POST /api/logout` - Logout
- `GET /api/user` - Current user

### Dashboard
- `GET /api/dashboard` - Role-specific stats and pending tasks

### Partners
- `GET|POST /api/partners` - List/create
- `GET|PUT|DELETE /api/partners/{id}` - Show/update/delete
- `GET /api/partners/check-bin-iin` - BIN/IIN uniqueness check
- `POST /api/partners/{id}/blacklist` - Blacklist (lawyer only)
- `POST /api/partners/{id}/unblacklist` - Remove from blacklist
- `GET|POST /api/partners/{id}/documents` - Partner documents
- `DELETE /api/partners/{id}/documents/{doc}` - Delete document

### Tasks
- `GET|POST /api/tasks` - List/create
- `GET|PUT /api/tasks/{id}` - Show/update
- `POST /api/tasks/{id}/submit` - Submit for approval
- `POST /api/tasks/{id}/approve` - Approve
- `POST /api/tasks/{id}/reject` - Reject
- `POST /api/tasks/{id}/delegate` - Delegate to lawyer
- `POST /api/tasks/{id}/fast-track` - Fast-track approve
- `POST /api/tasks/{id}/reviewers` - Add reviewer
- `POST /api/tasks/{id}/documents` - Upload document version
- `POST /api/tasks/{id}/signed-document` - Upload signed document

### Archive
- `GET /api/archive` - List archived tasks
- `GET /api/archive/export` - Excel export

### Templates & Categories
- `GET /api/document-categories` - List categories
- `GET /api/document-templates` - List templates
- `GET /api/document-templates/{id}/download` - Download template

### Finalized Documents
- `GET|POST /api/finalized-documents` - List/upload
- `DELETE /api/finalized-documents/{id}` - Delete
- `GET /api/finalized-documents/{id}/download` - Download
- `GET /api/finalized-documents/categories` - Category list

### Notifications
- `GET /api/notifications` - List notifications
- `POST /api/notifications/{id}/read` - Mark as read
- `POST /api/notifications/read-all` - Mark all as read

### Users
- `GET /api/users` - List users (for delegation/reviewer selection)

## Production Deployment

### Environment Variables

Update `.env` in backend:
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://dms.efes.kz
DB_CONNECTION=pgsql
DB_HOST=...
DB_DATABASE=efes_dms
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SANCTUM_STATEFUL_DOMAINS=dms.efes.kz
MAIL_MAILER=smtp
```

### Scheduled Commands

Add to crontab:
```
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

This runs the daily deadline check at 08:00.

### Build Frontend

```bash
cd frontend
npm run build
```

Deploy the `dist/` directory to your web server or CDN.

### Queue Worker

```bash
php artisan queue:work --daemon
```

## Technology Stack

| Layer        | Technology                        |
|--------------|-----------------------------------|
| Frontend     | React 19, TypeScript, Vite 7      |
| Styling      | Tailwind CSS v4                   |
| State        | TanStack Query v5                 |
| Routing      | React Router v7                   |
| Backend      | Laravel 12, PHP 8.3+              |
| Admin        | Filament 4                        |
| Auth         | Laravel Sanctum (SPA)             |
| Database     | SQLite / PostgreSQL               |
| Export       | Maatwebsite/Excel                 |
| Queue        | Database / Redis                  |
