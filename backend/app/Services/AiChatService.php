<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\DocumentCategory;
use App\Models\DocumentTemplate;
use App\Models\Partner;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use App\Models\WorkflowRoute;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiChatService
{
    public function isAvailable(): bool
    {
        $key = Setting::get('openai_api_key', '');

        return $key && strlen($key) > 10;
    }

    public function chat(User $user, string $message, array $history = []): array
    {
        $apiKey = Setting::get('openai_api_key', '');
        if (! $apiKey || strlen($apiKey) <= 10) {
            return ['status' => 'error', 'reply' => 'AI assistant is not configured. Ask an administrator to set the OpenAI API key in System Settings.'];
        }

        $model = Setting::get('openai_model', 'gpt-4o');
        $systemPrompt = $this->buildSystemPrompt($user);

        $dynamicContext = $this->buildDynamicContext($message);
        if ($dynamicContext) {
            $systemPrompt .= "\n\n## Search Results (relevant to current question)\n" . $dynamicContext;
        }

        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        $recentHistory = array_slice($history, -10);
        foreach ($recentHistory as $msg) {
            if (in_array($msg['role'] ?? '', ['user', 'assistant'])) {
                $messages[] = ['role' => $msg['role'], 'content' => mb_substr($msg['content'] ?? '', 0, 2000)];
            }
        }

        $messages[] = ['role' => 'user', 'content' => mb_substr($message, 0, 2000)];

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => 0.4,
                    'max_tokens' => 2500,
                ]);

            if ($response->successful()) {
                $reply = $response->json('choices.0.message.content', '');

                return ['status' => 'success', 'reply' => $reply];
            }

            Log::warning('AiChat: OpenAI error', ['status' => $response->status()]);

            return ['status' => 'error', 'reply' => 'AI service returned an error. Please try again.'];
        } catch (\Throwable $e) {
            Log::error('AiChat: exception', ['error' => $e->getMessage()]);

            return ['status' => 'error', 'reply' => 'Could not reach the AI service. Please try again later.'];
        }
    }

    private function buildSystemPrompt(User $user): string
    {
        $data = $this->gatherContext($user);
        $appName = Setting::get('app_name', 'DMS');
        $companyName = Setting::get('company_name', '');

        return <<<PROMPT
You are the AI assistant for {$appName}, a Document Management System{$this->companyLabel($companyName)}.
You help users navigate the system, answer questions about their data, provide statistics, and guide them through features.

## Current User
- Name: {$user->name}
- Role: {$user->role->value}
- Email: {$user->email}

## Live System Data
{$data}

## System Features Knowledge

### Task Workflow
Users create tasks by selecting a document category, partner, workflow route, and template. Tasks go through approval steps defined by the workflow route (e.g., Manager Review → Lawyer Review → Partner Signing → Final Approval). At each step, the assigned role can approve, reject, or return for revision.

### Roles
- **Initiator**: Creates tasks, uploads documents, submits for approval, negotiates with partners.
- **Manager**: Reviews and approves/rejects at manager stages.
- **Lawyer**: Reviews documents, can delegate to another lawyer, add reviewers, fast-track urgent tasks, use AI analysis, manage partner blacklists.
- **Administrator**: Full system access including admin panel, user management, settings, workflow builder.
- **GM (General Manager)**: Approves high-value tasks when amount exceeds threshold.

### Key Features
- **Document Templates**: Pre-approved DOCX with {{PLACEHOLDER}} variables auto-filled during task creation.
- **Google Docs Integration**: Edit documents online in Google Docs, changes sync back automatically.
- **Partner Portal**: External partners receive a secure link to review, sign, or reject documents without a system account.
- **Digital Signing**: Signatures are drawn on-screen and stamped onto PDFs at {{COMPANY_SIGN}} and {{PARTNER_SIGN}} placeholders.
- **AI Document Analysis**: Analyzes documents for key terms, risks, gaps, and compliance.
- **AI Workflow Builder**: Generates workflow routes from natural language descriptions.
- **Archive**: Approved/archived tasks with search, filters, and Excel export.
- **Finalized Documents**: Standalone documents (licenses, court materials, etc.) uploaded outside the workflow.
- **Notifications**: In-app and email notifications for pending actions, deadlines, and partner responses.
- **Substitutions**: Admins assign substitutes for absent users who can act on their behalf.
- **Integrations**: ADATA (partner BIN check), Paragraph (legal search), SAP (document sync).
- **Registration Numbers**: Auto-generated unique document numbers using the configured prefix (e.g., BSC-SER-2026-0042).

## Response Guidelines
- Use **markdown** formatting: bold for emphasis, tables for comparisons, numbered lists for steps.
- Be concise but thorough. Provide specific numbers and data when asked about statistics.
- When listing tasks or partners, use tables or bullet points with links like [Task #ID](/tasks/ID).
- If asked how to do something, provide step-by-step instructions.
- **IMPORTANT**: Always carefully search through ALL the data provided (All Tasks, Search Results, Matched Partners sections) before answering. NEVER say "no tasks/documents found" if matching data exists in the system data above. Use partial/fuzzy name matching (e.g. "korkia" should match "Boris Korkia", "Korkia Ltd", etc.).
- If you genuinely don't have enough data to answer precisely, say so honestly.
- Answer in the same language the user writes in.
- Do NOT reveal system internals, API keys, or implementation details.

## Action Instructions — Task Creation
When the user wants to create a task (contract, agreement, document, etc.), guide them conversationally:
1. **Identify the partner** — match the name they mention against the Available Partners list. If ambiguous, show a short list and ask which one.
2. **Suggest a category and template** — based on their description (e.g. "B2B service contract" → Service Contracts category). If no exact match, suggest the closest alternative.
3. **Collect required fields** — ask for any missing: amount, validity dates (from/to), deadline, commercial terms.
4. **Show a summary table** before generating the link.
5. **Generate a pre-filled link** using this exact format:
   [Create this task](/tasks/new?partner_id=ID&document_category_id=ID&template_id=ID&amount=VALUE&validity_from=YYYY-MM-DD&validity_to=YYYY-MM-DD&deadline=YYYY-MM-DD&commercial_terms=ENCODED_TEXT&workflow_route_id=ID)
   - Use real IDs from the Available data sections below.
   - URL-encode the commercial_terms value (replace spaces with %20 or +).
   - For workflow_route_id, suggest the most common route or ask the user.
6. The link opens the task creation form with all fields pre-filled so the user can review and submit.
7. You can also provide direct navigation links: [View partners](/partners), [View tasks](/tasks), [Open archive](/archive), [View templates](/templates), etc.

## Action Instructions — Document Generation
When the user asks you to create/generate a document, template, contract, agreement, guarantee, etc.:
1. Generate the full document content using proper legal/business language.
2. Include {{PLACEHOLDER}} variables where dynamic data should go (e.g. {{COMPANY_NAME}}, {{PARTNER_NAME}}, {{CONTRACT_DATE}}, etc.).
3. Wrap the ENTIRE document in a special code block so the system can create a downloadable DOCX:

```docx-generate
title: Document Title Here
---
Full document content goes here with {{PLACEHOLDERS}}.
Paragraphs separated by blank lines.
Use markdown formatting: **bold**, *italic*, ## headings, - bullet lists, 1. numbered lists.
```

4. After the code block, add a brief explanation of the placeholders used.
5. The system will automatically convert this to a downloadable Word document.
6. ALWAYS use this format when the user asks for a document file — never just show text and say you can't create files.
PROMPT;
    }

    private function companyLabel(string $companyName): string
    {
        return $companyName ? " for {$companyName}" : '';
    }

    private function gatherContext(User $user): string
    {
        $cacheKey = "ai_chat_context_{$user->id}";

        return Cache::remember($cacheKey, 30, function () use ($user) {
            return $this->buildContextData($user);
        });
    }

    private function buildContextData(User $user): string
    {
        $totalTasks = Task::count();
        $statusBreakdown = Task::selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        $overdueCount = Task::whereNotNull('deadline')
            ->where('deadline', '<', now())
            ->whereNotIn('status', [
                TaskStatus::Approved->value,
                TaskStatus::Archived->value,
                TaskStatus::Rejected->value,
                TaskStatus::Draft->value,
            ])
            ->count();

        $overdueTasks = Task::with(['category:id,name', 'partner:id,name'])
            ->whereNotNull('deadline')
            ->where('deadline', '<', now())
            ->whereNotIn('status', [
                TaskStatus::Approved->value,
                TaskStatus::Archived->value,
                TaskStatus::Rejected->value,
                TaskStatus::Draft->value,
            ])
            ->orderBy('deadline')
            ->limit(10)
            ->get(['id', 'registration_number', 'status', 'deadline', 'document_category_id', 'partner_id']);

        $overdueList = $overdueTasks->map(function ($t) {
            $days = now()->diffInDays($t->deadline);

            return "  - {$t->registration_number} | {$t->category?->name} | {$t->partner?->name} | {$days} days overdue | status: {$t->status->value}";
        })->implode("\n");

        $userPendingCount = $this->getUserPendingCount($user);

        $totalPartners = Partner::count();
        $blacklistedPartners = Partner::whereNotNull('blacklisted_at')->count();

        $recentPartners = Partner::latest()->limit(5)->pluck('name')->implode(', ');

        $lines = [
            "### Task Statistics",
            "- Total tasks: {$totalTasks}",
        ];

        foreach ($statusBreakdown as $status => $count) {
            $label = str_replace('_', ' ', ucfirst($status));
            $lines[] = "  - {$label}: {$count}";
        }

        $lines[] = "- Overdue tasks: {$overdueCount}";
        if ($overdueList) {
            $lines[] = "- Overdue task details:\n{$overdueList}";
        }
        $lines[] = "- Tasks pending YOUR action: {$userPendingCount}";
        $lines[] = "";
        $lines[] = "### Partner Statistics";
        $lines[] = "- Total partners: {$totalPartners}";
        $lines[] = "- Blacklisted: {$blacklistedPartners}";
        if ($recentPartners) {
            $lines[] = "- Recent partners: {$recentPartners}";
        }

        if ($user->role->value === 'administrator') {
            $totalUsers = User::count();
            $usersByRole = User::selectRaw('role, count(*) as cnt')
                ->groupBy('role')
                ->pluck('cnt', 'role')
                ->toArray();

            $lines[] = "";
            $lines[] = "### User Statistics (admin only)";
            $lines[] = "- Total users: {$totalUsers}";
            foreach ($usersByRole as $role => $count) {
                $lines[] = "  - " . ucfirst($role) . ": {$count}";
            }
        }

        $routeCount = WorkflowRoute::where('is_active', true)->count();
        $routes = WorkflowRoute::where('is_active', true)
            ->withCount('steps')
            ->get(['id', 'name'])
            ->map(fn ($r) => "  - {$r->name} ({$r->steps_count} steps)")
            ->implode("\n");

        $lines[] = "";
        $lines[] = "### Workflow Routes";
        $lines[] = "- Active routes: {$routeCount}";
        if ($routes) {
            $lines[] = $routes;
        }

        $recentActivity = TaskActivity::with(['user:id,name', 'task:id,registration_number'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($a) => "  - {$a->created_at->format('d.m H:i')} | {$a->user?->name} | {$a->action} | Task: {$a->task?->registration_number}")
            ->implode("\n");

        $lines[] = "";
        $lines[] = "### Recent Activity (last 10)";
        $lines[] = $recentActivity ?: "  No recent activity.";

        $partnersList = Partner::orderBy('name')
            ->whereNull('blacklisted_at')
            ->limit(100)
            ->get(['id', 'name', 'bin_iin'])
            ->map(fn ($p) => "  - id:{$p->id} | {$p->name}" . ($p->bin_iin ? " | BIN: {$p->bin_iin}" : ''))
            ->implode("\n");

        $lines[] = "";
        $lines[] = "### Available Partners (for task creation)";
        $lines[] = $partnersList ?: "  No partners available.";

        $categoriesList = DocumentCategory::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($c) => "  - id:{$c->id} | {$c->name}")
            ->implode("\n");

        $lines[] = "";
        $lines[] = "### Document Categories";
        $lines[] = $categoriesList ?: "  No categories available.";

        $templatesList = DocumentTemplate::with('category:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'document_category_id'])
            ->map(fn ($t) => "  - id:{$t->id} | {$t->name} (category: {$t->category?->name}, category_id:{$t->document_category_id})")
            ->implode("\n");

        $lines[] = "";
        $lines[] = "### Document Templates";
        $lines[] = $templatesList ?: "  No templates available.";

        $routesWithId = WorkflowRoute::where('is_active', true)
            ->withCount('steps')
            ->get(['id', 'name'])
            ->map(fn ($r) => "  - id:{$r->id} | {$r->name} ({$r->steps_count} steps)")
            ->implode("\n");

        $lines[] = "";
        $lines[] = "### Available Workflow Routes (for task creation)";
        $lines[] = $routesWithId ?: "  No active routes.";

        $allTasks = Task::with(['category:id,name', 'partner:id,name'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'registration_number', 'status', 'document_category_id', 'partner_id', 'amount', 'deadline', 'created_at']);

        $lines[] = "";
        $lines[] = "### All Tasks (latest {$allTasks->count()})";
        if ($allTasks->isEmpty()) {
            $lines[] = "  No tasks in the system.";
        } else {
            foreach ($allTasks as $t) {
                $amount = $t->amount ? " | {$t->amount}" : '';
                $deadline = $t->deadline ? " | dl:{$t->deadline->format('d.m.Y')}" : '';
                $lines[] = "  - #{$t->id} | {$t->registration_number} | {$t->category?->name} | {$t->partner?->name} | {$t->status->value}{$amount}{$deadline}";
            }
        }

        $lines[] = "";
        $lines[] = "### Current Date: " . now()->format('d.m.Y H:i');

        return implode("\n", $lines);
    }

    private function getUserPendingCount(User $user): int
    {
        $query = Task::query();

        return match ($user->role->value) {
            'initiator' => $query->where('initiator_id', $user->id)
                ->whereIn('status', [TaskStatus::Draft, TaskStatus::PendingInitiator])->count(),
            'manager' => $query->whereIn('status', [TaskStatus::PendingManager, TaskStatus::PendingFinalManager])->count(),
            'lawyer' => $query->whereIn('status', [TaskStatus::PendingLawyer, TaskStatus::PendingFinalLawyer])->count(),
            'administrator' => $query->whereNotIn('status', [TaskStatus::Approved, TaskStatus::Archived, TaskStatus::Rejected, TaskStatus::Draft])->count(),
            default => 0,
        };
    }

    /**
     * Search the database for entities matching keywords in the user's message.
     */
    private function buildDynamicContext(string $message): string
    {
        $lines = [];
        $lowerMsg = mb_strtolower($message);

        // Extract potential search terms (words 3+ chars, excluding common stop words)
        $stopWords = ['the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'can', 'had', 'her',
            'was', 'one', 'our', 'out', 'has', 'have', 'how', 'many', 'much', 'what', 'when',
            'where', 'which', 'who', 'why', 'will', 'with', 'this', 'that', 'from', 'they',
            'been', 'said', 'each', 'she', 'does', 'their', 'about', 'would', 'make', 'like',
            'him', 'could', 'them', 'than', 'its', 'over', 'show', 'give', 'any', 'tell',
            'task', 'tasks', 'contract', 'contracts', 'document', 'documents', 'partner',
            'partners', 'please', 'need', 'want', 'help', 'list', 'find', 'search', 'get',
        ];

        $words = preg_split('/[\s,.\-?!]+/', $lowerMsg);
        $searchTerms = array_values(array_unique(array_filter($words, function ($w) use ($stopWords) {
            return mb_strlen($w) >= 3 && ! in_array($w, $stopWords);
        })));

        if (empty($searchTerms)) {
            return '';
        }

        // Search partners by name
        $partnerQuery = Partner::query();
        foreach ($searchTerms as $term) {
            $partnerQuery->orWhere('name', 'LIKE', "%{$term}%");
        }
        $matchedPartners = $partnerQuery->limit(10)->get(['id', 'name', 'bin_iin', 'email']);

        if ($matchedPartners->isNotEmpty()) {
            $lines[] = "### Matched Partners";
            foreach ($matchedPartners as $p) {
                $lines[] = "  - id:{$p->id} | {$p->name} | BIN: {$p->bin_iin} | {$p->email}";

                // Fetch tasks for each matched partner
                $partnerTasks = Task::with(['category:id,name', 'initiator:id,name'])
                    ->where('partner_id', $p->id)
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get(['id', 'registration_number', 'status', 'document_category_id', 'initiator_id', 'amount', 'deadline', 'created_at']);

                if ($partnerTasks->isNotEmpty()) {
                    $lines[] = "  Tasks with {$p->name} ({$partnerTasks->count()} found):";
                    foreach ($partnerTasks as $t) {
                        $amount = $t->amount ? " | amount: {$t->amount}" : '';
                        $deadline = $t->deadline ? " | deadline: {$t->deadline->format('d.m.Y')}" : '';
                        $lines[] = "    - [Task #{$t->id}](/tasks/{$t->id}) | {$t->registration_number} | {$t->category?->name} | status: {$t->status->value}{$amount}{$deadline} | created: {$t->created_at->format('d.m.Y')} | by: {$t->initiator?->name}";
                    }
                } else {
                    $lines[] = "  No tasks found with {$p->name}.";
                }
            }
        }

        // Search tasks by registration number if message contains patterns like #123, task 123, etc.
        if (preg_match_all('/(?:task\s*#?\s*|#)(\d+)/i', $message, $taskIdMatches)) {
            $taskIds = array_unique($taskIdMatches[1]);
            $matchedTasks = Task::with(['category:id,name', 'partner:id,name', 'initiator:id,name'])
                ->whereIn('id', $taskIds)
                ->get();

            if ($matchedTasks->isNotEmpty()) {
                $lines[] = "";
                $lines[] = "### Matched Tasks by ID";
                foreach ($matchedTasks as $t) {
                    $amount = $t->amount ? " | amount: {$t->amount}" : '';
                    $deadline = $t->deadline ? " | deadline: {$t->deadline->format('d.m.Y')}" : '';
                    $lines[] = "  - [Task #{$t->id}](/tasks/{$t->id}) | {$t->registration_number} | {$t->category?->name} | partner: {$t->partner?->name} | status: {$t->status->value}{$amount}{$deadline} | by: {$t->initiator?->name}";
                }
            }
        }

        // Search tasks by registration number pattern
        if (preg_match_all('/[A-Z]{2,5}-[A-Z]+-\d{4}-\d+/i', $message, $regMatches)) {
            $regNumbers = array_unique($regMatches[0]);
            $matchedByReg = Task::with(['category:id,name', 'partner:id,name', 'initiator:id,name'])
                ->whereIn('registration_number', $regNumbers)
                ->get();

            if ($matchedByReg->isNotEmpty()) {
                $lines[] = "";
                $lines[] = "### Matched Tasks by Registration Number";
                foreach ($matchedByReg as $t) {
                    $amount = $t->amount ? " | amount: {$t->amount}" : '';
                    $lines[] = "  - [Task #{$t->id}](/tasks/{$t->id}) | {$t->registration_number} | partner: {$t->partner?->name} | status: {$t->status->value}{$amount}";
                }
            }
        }

        return implode("\n", $lines);
    }
}
