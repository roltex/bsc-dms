<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiWorkflowService
{
    private const VALID_ROLES = ['manager', 'lawyer', 'initiator', 'partner', 'gm'];

    private const VALID_ACTION_TYPES = ['review', 'approve', 'sign', 'submit', 'upload_document', 'confirm', 'create_final'];

    private const VALID_CONDITIONS = ['approved', 'rejected', 'needs_revision', 'amount_gte', 'amount_lt', 'has_document', 'is_signed', 'requires_gm'];

    public function isAvailable(): bool
    {
        $key = Setting::get('openai_api_key', '');

        return $key && strlen($key) > 10;
    }

    public function generateWorkflow(string $description): array
    {
        $apiKey = Setting::get('openai_api_key', '');
        if (! $apiKey || strlen($apiKey) <= 10) {
            return ['status' => 'error', 'message' => 'OpenAI API key not configured.'];
        }

        $model = Setting::get('openai_model', 'gpt-4o');

        $systemPrompt = $this->buildSystemPrompt();

        try {
            $response = Http::timeout(90)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => 'Create a workflow for: '.$description],
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 3000,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if (! $response->successful()) {
                Log::warning('AiWorkflow: OpenAI error', ['status' => $response->status(), 'body' => $response->body()]);

                return ['status' => 'error', 'message' => 'AI service returned status '.$response->status()];
            }

            $content = $response->json('choices.0.message.content', '{}');
            $parsed = json_decode($content, true);

            if (! $parsed || ! is_array($parsed)) {
                return ['status' => 'error', 'message' => 'AI returned invalid JSON.'];
            }

            $validated = $this->validateAndNormalize($parsed);
            if ($validated['status'] === 'error') {
                return $validated;
            }

            return ['status' => 'success', 'workflow' => $validated['workflow']];
        } catch (\Throwable $e) {
            Log::error('AiWorkflow exception', ['error' => $e->getMessage()]);

            return ['status' => 'error', 'message' => 'AI service error: '.$e->getMessage()];
        }
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a workflow designer for a corporate Document Management System (DMS). You create approval workflows for contracts and documents.

SYSTEM RULES:
- Each workflow has ordered steps. Each step has: name, role, action_type.
- Steps are connected by transitions with conditions.

VALID ROLES (use exactly these values):
- "manager" — Department/line manager
- "lawyer" — Legal department
- "initiator" — The person who created the task
- "partner" — External partner/counterparty (signs documents)
- "gm" — General Manager (high-level approval)

VALID ACTION TYPES (use exactly these values):
- "review" — Review and approve/reject the document
- "approve" — Formal approval decision
- "sign" — Electronic signature on the document
- "confirm" — Confirm/acknowledge (e.g. initiator confirms final version)
- "create_final" — Create the final version of the document (usually lawyer)
- "submit" — Submit for processing
- "upload_document" — Upload a document

TRANSITION CONDITIONS (use exactly these values for condition.type):
- "approved" — When step is approved, go to next step
- "rejected" — When step is rejected
- "needs_revision" — When document needs revision (usually goes back to an earlier step)
- "amount_gte" — Amount >= threshold (requires condition.value as number)
- "amount_lt" — Amount < threshold (requires condition.value as number)
- "requires_gm" — Requires GM approval based on system threshold

BEST PRACTICES:
1. Number each step (e.g. "1. Manager Review", "2. Legal Review")
2. Always include at least one "needs_revision" transition going back to an earlier step for revision loops
3. Partner steps should use action_type "sign" when the partner needs to sign
4. The final verification step (if any) should use action_type "approve"
5. Include a "create_final" step for lawyer if the flow involves document finalization
6. For "initiator" steps: use "confirm" when they just confirm, "sign" when they sign
7. Forward transitions use condition type "approved", backward revision transitions use "needs_revision"
8. Rejected transitions use condition type "rejected" and typically go to a revision/initiator step
9. Generate a descriptive slug using only lowercase letters, numbers, and hyphens

RESPONSE FORMAT — respond with exactly this JSON structure:
{
  "name": "Descriptive Name (N-step)",
  "slug": "descriptive-slug",
  "description": "Brief description of this workflow",
  "steps": [
    { "name": "1. Step Name", "role": "manager", "action_type": "review" }
  ],
  "transitions": [
    { "from_step": 0, "to_step": 1, "condition": { "type": "approved" }, "priority": 0 }
  ]
}

IMPORTANT:
- "from_step" and "to_step" are zero-based indices into the steps array
- Every step (except the last) needs at least one forward "approved" transition
- Include "needs_revision" and "rejected" transitions where appropriate
- Priority: lower number = higher priority. Use 0 for approved, 5 for needs_revision, 10 for rejected
- Generate between 3 and 15 steps depending on complexity
- The LAST step's approved outcome will automatically complete the workflow (no transition needed for it)
PROMPT;
    }

    private function validateAndNormalize(array $data): array
    {
        if (empty($data['steps']) || ! is_array($data['steps'])) {
            return ['status' => 'error', 'message' => 'AI did not return any workflow steps.'];
        }

        if (count($data['steps']) < 2) {
            return ['status' => 'error', 'message' => 'Workflow must have at least 2 steps.'];
        }

        $steps = [];
        foreach ($data['steps'] as $i => $step) {
            $role = $step['role'] ?? 'manager';
            $actionType = $step['action_type'] ?? 'review';

            if (! in_array($role, self::VALID_ROLES)) {
                $role = 'manager';
            }
            if (! in_array($actionType, self::VALID_ACTION_TYPES)) {
                $actionType = 'review';
            }

            $steps[] = [
                'name' => $step['name'] ?? (($i + 1).'. Step '.($i + 1)),
                'role' => $role,
                'action_type' => $actionType,
            ];
        }

        $transitions = [];
        $stepCount = count($steps);

        if (! empty($data['transitions']) && is_array($data['transitions'])) {
            foreach ($data['transitions'] as $t) {
                $from = (int) ($t['from_step'] ?? -1);
                $to = (int) ($t['to_step'] ?? -1);

                if ($from < 0 || $from >= $stepCount || $to < 0 || $to >= $stepCount) {
                    continue;
                }
                if ($from === $to) {
                    continue;
                }

                $condition = null;
                if (! empty($t['condition']['type'])) {
                    $condType = $t['condition']['type'];
                    if (in_array($condType, self::VALID_CONDITIONS)) {
                        $condition = ['type' => $condType];
                        if (in_array($condType, ['amount_gte', 'amount_lt']) && isset($t['condition']['value'])) {
                            $condition['value'] = (float) $t['condition']['value'];
                        }
                    }
                }

                $transitions[] = [
                    'from_step' => $from,
                    'to_step' => $to,
                    'condition' => $condition,
                    'priority' => (int) ($t['priority'] ?? 0),
                ];
            }
        }

        if (empty($transitions)) {
            for ($i = 0; $i < $stepCount - 1; $i++) {
                $transitions[] = [
                    'from_step' => $i,
                    'to_step' => $i + 1,
                    'condition' => ['type' => 'approved'],
                    'priority' => 0,
                ];
            }
        }

        $name = $data['name'] ?? 'AI Generated Flow';
        $slug = $data['slug'] ?? 'ai-flow-'.time();
        $description = $data['description'] ?? '';

        $slug = preg_replace('/[^a-z0-9\-]/', '-', strtolower($slug));
        $slug = preg_replace('/-+/', '-', trim($slug, '-'));

        return [
            'status' => 'success',
            'workflow' => [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'steps' => $steps,
                'transitions' => $transitions,
            ],
        ];
    }
}
