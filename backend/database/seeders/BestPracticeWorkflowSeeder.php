<?php

namespace Database\Seeders;

use App\Models\WorkflowRoute;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use Illuminate\Database\Seeder;

/**
 * Seeds a comprehensive "EFES Standard (Full)" workflow that covers:
 *
 *  1. Department Manager Review
 *  2. Legal Review (lawyer can add reviewers, delegate, use AI analysis)
 *  3. GM Approval (conditional - only for contracts >= threshold amount)
 *  4. Lawyer Creates Final Version (consolidates comments)
 *  5. Initiator Negotiation with Counterparty
 *  6. Partner Review & Signature (external, via link)
 *  7. Company Signature (internal)
 *  8. Final Lawyer Verification → auto-archive with protected PDF + reg. number
 *
 * Rejection at any stage returns to initiator for revision.
 * The GM step is conditional on amount (requires_gm / amount_gte).
 */
class BestPracticeWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $route = WorkflowRoute::updateOrCreate(
            ['slug' => 'efes-standard-full'],
            [
                'name' => 'EFES Standard (Full Cycle)',
                'description' => 'Complete approval flow: Manager → Lawyer → (GM if high amount) → Final Version → Initiator Negotiation → Partner Sign → Company Sign → Lawyer Verify. Covers all system capabilities.',
                'is_active' => true,
                'is_default' => false,
            ]
        );

        // Remove old steps and transitions for a clean slate
        $route->transitions()->delete();
        $route->steps()->delete();

        // ── Steps ──────────────────────────────────────────────────────────

        $step1 = WorkflowStep::create([
            'workflow_route_id' => $route->id,
            'name' => '1. Manager Review',
            'role' => 'manager',
            'action_type' => 'review',
            'sort_order' => 1,
            'config' => ['description' => 'Department head reviews the contract and either approves or rejects.'],
        ]);

        $step2 = WorkflowStep::create([
            'workflow_route_id' => $route->id,
            'name' => '2. Legal Review',
            'role' => 'lawyer',
            'action_type' => 'review',
            'sort_order' => 2,
            'config' => ['description' => 'Lawyer performs detailed review, may add reviewers, delegate, or use AI document analysis. Can add PDF comments.'],
        ]);

        $step3 = WorkflowStep::create([
            'workflow_route_id' => $route->id,
            'name' => '3. GM Approval',
            'role' => 'gm',
            'action_type' => 'approve',
            'sort_order' => 3,
            'config' => ['description' => 'General Manager approval required for high-value contracts (amount >= threshold).'],
        ]);

        $step4 = WorkflowStep::create([
            'workflow_route_id' => $route->id,
            'name' => '4. Lawyer Final Version',
            'role' => 'lawyer',
            'action_type' => 'create_final',
            'sort_order' => 4,
            'config' => ['description' => 'Lawyer consolidates all comments and creates the final version of the document.'],
        ]);

        $step5 = WorkflowStep::create([
            'workflow_route_id' => $route->id,
            'name' => '5. Initiator Negotiation',
            'role' => 'initiator',
            'action_type' => 'confirm',
            'sort_order' => 5,
            'config' => ['description' => 'Initiator negotiates with the counterparty (outside system) and confirms readiness for signing.'],
        ]);

        $step6 = WorkflowStep::create([
            'workflow_route_id' => $route->id,
            'name' => '6. Partner Review & Sign',
            'role' => 'partner',
            'action_type' => 'sign',
            'sort_order' => 6,
            'config' => ['description' => 'Partner receives a link, reviews the contract, and signs electronically.'],
        ]);

        $step7 = WorkflowStep::create([
            'workflow_route_id' => $route->id,
            'name' => '7. Company Sign',
            'role' => 'manager',
            'action_type' => 'sign',
            'sort_order' => 7,
            'config' => ['description' => 'Company representative signs the contract from internal side.'],
        ]);

        $step8 = WorkflowStep::create([
            'workflow_route_id' => $route->id,
            'name' => '8. Final Lawyer Verification',
            'role' => 'lawyer',
            'action_type' => 'approve',
            'sort_order' => 8,
            'config' => ['description' => 'Lawyer does final check of the signed document. Upon approval, registration number is assigned and document is archived as protected PDF.'],
        ]);

        // Also create a "Revision" step that the initiator lands on when rejected
        $stepRevision = WorkflowStep::create([
            'workflow_route_id' => $route->id,
            'name' => 'Revision (Initiator)',
            'role' => 'initiator',
            'action_type' => 'review',
            'sort_order' => 9,
            'config' => ['description' => 'Initiator revises the document based on rejection feedback and resubmits.'],
        ]);

        // ── Transitions (happy path) ───────────────────────────────────────

        // Step 1 → Step 2 (Manager approves → Lawyer)
        WorkflowTransition::create([
            'workflow_route_id' => $route->id,
            'from_step_id' => $step1->id,
            'to_step_id' => $step2->id,
            'condition' => ['type' => 'approved'],
            'priority' => 1,
        ]);

        // Step 2 → Step 3 (Lawyer approves, HIGH amount → GM)
        WorkflowTransition::create([
            'workflow_route_id' => $route->id,
            'from_step_id' => $step2->id,
            'to_step_id' => $step3->id,
            'condition' => ['type' => 'requires_gm'],
            'priority' => 1,
        ]);

        // Step 2 → Step 4 (Lawyer approves, LOW amount → skip GM, go to Final Version)
        WorkflowTransition::create([
            'workflow_route_id' => $route->id,
            'from_step_id' => $step2->id,
            'to_step_id' => $step4->id,
            'condition' => ['type' => 'approved'],
            'priority' => 2,
        ]);

        // Step 3 → Step 4 (GM approves → Lawyer Final Version)
        WorkflowTransition::create([
            'workflow_route_id' => $route->id,
            'from_step_id' => $step3->id,
            'to_step_id' => $step4->id,
            'condition' => ['type' => 'approved'],
            'priority' => 1,
        ]);

        // Step 4 → Step 5 (Lawyer uploads final version → Initiator Negotiation)
        WorkflowTransition::create([
            'workflow_route_id' => $route->id,
            'from_step_id' => $step4->id,
            'to_step_id' => $step5->id,
            'condition' => ['type' => 'approved'],
            'priority' => 1,
        ]);

        // Step 5 → Step 6 (Initiator confirms → Partner Review & Sign)
        WorkflowTransition::create([
            'workflow_route_id' => $route->id,
            'from_step_id' => $step5->id,
            'to_step_id' => $step6->id,
            'condition' => ['type' => 'approved'],
            'priority' => 1,
        ]);

        // Step 6 → Step 7 (Partner signs → Company Sign)
        WorkflowTransition::create([
            'workflow_route_id' => $route->id,
            'from_step_id' => $step6->id,
            'to_step_id' => $step7->id,
            'condition' => ['type' => 'approved'],
            'priority' => 1,
        ]);

        // Step 7 → Step 8 (Company signs → Final Lawyer Verification)
        WorkflowTransition::create([
            'workflow_route_id' => $route->id,
            'from_step_id' => $step7->id,
            'to_step_id' => $step8->id,
            'condition' => ['type' => 'approved'],
            'priority' => 1,
        ]);

        // Step 8 → TERMINAL (Lawyer verifies → approved, archived, reg number, protected PDF)
        // No outgoing transition = terminal step

        // ── Transitions (rejection / revision paths) ───────────────────────

        // Step 1 (Manager) rejects → Revision
        WorkflowTransition::create([
            'workflow_route_id' => $route->id,
            'from_step_id' => $step1->id,
            'to_step_id' => $stepRevision->id,
            'condition' => ['type' => 'rejected'],
            'priority' => 10,
        ]);

        // Step 2 (Lawyer) rejects → Revision
        WorkflowTransition::create([
            'workflow_route_id' => $route->id,
            'from_step_id' => $step2->id,
            'to_step_id' => $stepRevision->id,
            'condition' => ['type' => 'rejected'],
            'priority' => 10,
        ]);

        // Step 3 (GM) rejects → Revision
        WorkflowTransition::create([
            'workflow_route_id' => $route->id,
            'from_step_id' => $step3->id,
            'to_step_id' => $stepRevision->id,
            'condition' => ['type' => 'rejected'],
            'priority' => 10,
        ]);

        // Step 6 (Partner) rejects → Revision (Initiator revises, re-negotiates)
        WorkflowTransition::create([
            'workflow_route_id' => $route->id,
            'from_step_id' => $step6->id,
            'to_step_id' => $stepRevision->id,
            'condition' => ['type' => 'rejected'],
            'priority' => 10,
        ]);

        // Step 8 (Final Lawyer Verification) rejects → Revision
        WorkflowTransition::create([
            'workflow_route_id' => $route->id,
            'from_step_id' => $step8->id,
            'to_step_id' => $stepRevision->id,
            'condition' => ['type' => 'rejected'],
            'priority' => 10,
        ]);

        // Revision → Step 1 (Initiator revises and resubmits → back to Manager)
        WorkflowTransition::create([
            'workflow_route_id' => $route->id,
            'from_step_id' => $stepRevision->id,
            'to_step_id' => $step1->id,
            'condition' => ['type' => 'approved'],
            'priority' => 1,
        ]);

        // ── Needs Revision paths (return for revision without full reject) ──

        // Step 2 (Lawyer) needs_revision → Revision
        WorkflowTransition::create([
            'workflow_route_id' => $route->id,
            'from_step_id' => $step2->id,
            'to_step_id' => $stepRevision->id,
            'condition' => ['type' => 'needs_revision'],
            'priority' => 10,
        ]);

        // Step 8 (Final Verification) needs_revision → Step 4 (back to Final Version creation)
        WorkflowTransition::create([
            'workflow_route_id' => $route->id,
            'from_step_id' => $step8->id,
            'to_step_id' => $step4->id,
            'condition' => ['type' => 'needs_revision'],
            'priority' => 5,
        ]);

        // ── Store canvas layout for workflow builder visualization ──────────

        $route->update([
            'canvas_data' => [
                'nodes' => [
                    ['id' => (string) $step1->id, 'type' => 'workflowStep', 'position' => ['x' => 400, 'y' => 0], 'data' => ['label' => $step1->name, 'role' => $step1->role, 'action_type' => $step1->action_type, 'stepId' => $step1->id]],
                    ['id' => (string) $step2->id, 'type' => 'workflowStep', 'position' => ['x' => 400, 'y' => 120], 'data' => ['label' => $step2->name, 'role' => $step2->role, 'action_type' => $step2->action_type, 'stepId' => $step2->id]],
                    ['id' => (string) $step3->id, 'type' => 'workflowStep', 'position' => ['x' => 150, 'y' => 240], 'data' => ['label' => $step3->name, 'role' => $step3->role, 'action_type' => $step3->action_type, 'stepId' => $step3->id]],
                    ['id' => (string) $step4->id, 'type' => 'workflowStep', 'position' => ['x' => 400, 'y' => 360], 'data' => ['label' => $step4->name, 'role' => $step4->role, 'action_type' => $step4->action_type, 'stepId' => $step4->id]],
                    ['id' => (string) $step5->id, 'type' => 'workflowStep', 'position' => ['x' => 400, 'y' => 480], 'data' => ['label' => $step5->name, 'role' => $step5->role, 'action_type' => $step5->action_type, 'stepId' => $step5->id]],
                    ['id' => (string) $step6->id, 'type' => 'workflowStep', 'position' => ['x' => 400, 'y' => 600], 'data' => ['label' => $step6->name, 'role' => $step6->role, 'action_type' => $step6->action_type, 'stepId' => $step6->id]],
                    ['id' => (string) $step7->id, 'type' => 'workflowStep', 'position' => ['x' => 400, 'y' => 720], 'data' => ['label' => $step7->name, 'role' => $step7->role, 'action_type' => $step7->action_type, 'stepId' => $step7->id]],
                    ['id' => (string) $step8->id, 'type' => 'workflowStep', 'position' => ['x' => 400, 'y' => 840], 'data' => ['label' => $step8->name, 'role' => $step8->role, 'action_type' => $step8->action_type, 'stepId' => $step8->id]],
                    ['id' => (string) $stepRevision->id, 'type' => 'workflowStep', 'position' => ['x' => 750, 'y' => 240], 'data' => ['label' => $stepRevision->name, 'role' => $stepRevision->role, 'action_type' => $stepRevision->action_type, 'stepId' => $stepRevision->id]],
                ],
                'edges' => $route->transitions()->with('fromStep', 'toStep')->get()->map(function ($t) {
                    return [
                        'id' => 'e' . $t->id,
                        'source' => (string) $t->from_step_id,
                        'target' => (string) $t->to_step_id,
                        'label' => $t->getConditionLabel(),
                        'type' => 'smoothstep',
                        'animated' => $t->isConditional(),
                    ];
                })->values()->toArray(),
            ],
        ]);

        $this->command->info("Created workflow: \"{$route->name}\" with " .
            $route->steps()->count() . ' steps and ' .
            $route->transitions()->count() . ' transitions.');
    }
}
