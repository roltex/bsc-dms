<?php

namespace Database\Seeders;

use App\Models\WorkflowRoute;
use App\Models\WorkflowStep;
use App\Models\WorkflowTransition;
use Illuminate\Database\Seeder;

class WorkflowRouteSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedStandardRoute();
        $this->seedSimplifiedRoute();
        $this->backfillExistingTasks();
    }

    private function seedStandardRoute(): void
    {
        $route = WorkflowRoute::firstOrCreate(
            ['slug' => 'standard'],
            [
                'name' => 'Standard (6-step)',
                'description' => 'Full approval workflow: Manager → Lawyer → Initiator negotiation → Final Lawyer → Final Manager',
                'is_active' => true,
                'is_default' => true,
                'canvas_data' => $this->standardCanvasData(),
            ]
        );

        if ($route->steps()->count() > 0) {
            return;
        }

        $steps = [
            ['name' => 'Manager Review', 'role' => 'manager', 'action_type' => 'review', 'sort_order' => 1],
            ['name' => 'Lawyer Review', 'role' => 'lawyer', 'action_type' => 'review', 'sort_order' => 2],
            ['name' => 'Initiator Negotiation', 'role' => 'initiator', 'action_type' => 'sign', 'sort_order' => 3],
            ['name' => 'Final Lawyer Review', 'role' => 'lawyer', 'action_type' => 'review', 'sort_order' => 4],
            ['name' => 'Final Manager Approval', 'role' => 'manager', 'action_type' => 'approve', 'sort_order' => 5],
        ];

        $stepModels = [];
        foreach ($steps as $step) {
            $stepModels[] = WorkflowStep::create([...$step, 'workflow_route_id' => $route->id]);
        }

        for ($i = 0; $i < count($stepModels) - 1; $i++) {
            WorkflowTransition::create([
                'workflow_route_id' => $route->id,
                'from_step_id' => $stepModels[$i]->id,
                'to_step_id' => $stepModels[$i + 1]->id,
            ]);
        }
    }

    private function seedSimplifiedRoute(): void
    {
        $route = WorkflowRoute::firstOrCreate(
            ['slug' => 'simplified'],
            [
                'name' => 'Simplified (2-step)',
                'description' => 'Quick approval: Manager only',
                'is_active' => true,
                'is_default' => false,
                'canvas_data' => $this->simplifiedCanvasData(),
            ]
        );

        if ($route->steps()->count() > 0) {
            return;
        }

        WorkflowStep::create([
            'workflow_route_id' => $route->id,
            'name' => 'Manager Approval',
            'role' => 'manager',
            'action_type' => 'approve',
            'sort_order' => 1,
        ]);
    }

    private function backfillExistingTasks(): void
    {
        $standard = WorkflowRoute::where('slug', 'standard')->first();
        $simplified = WorkflowRoute::where('slug', 'simplified')->first();

        if ($standard) {
            \DB::table('tasks')
                ->where('route_type', 'standard')
                ->whereNull('workflow_route_id')
                ->update(['workflow_route_id' => $standard->id]);
        }

        if ($simplified) {
            \DB::table('tasks')
                ->where('route_type', 'simplified')
                ->whereNull('workflow_route_id')
                ->update(['workflow_route_id' => $simplified->id]);
        }
    }

    private function standardCanvasData(): array
    {
        return [
            'nodes' => [
                ['id' => 'start', 'type' => 'start', 'x' => 50, 'y' => 200, 'label' => 'Start'],
                ['id' => 'step-1', 'type' => 'step', 'x' => 220, 'y' => 200, 'label' => 'Manager Review'],
                ['id' => 'step-2', 'type' => 'step', 'x' => 420, 'y' => 200, 'label' => 'Lawyer Review'],
                ['id' => 'step-3', 'type' => 'step', 'x' => 620, 'y' => 200, 'label' => 'Initiator Negotiation'],
                ['id' => 'step-4', 'type' => 'step', 'x' => 820, 'y' => 200, 'label' => 'Final Lawyer Review'],
                ['id' => 'step-5', 'type' => 'step', 'x' => 1020, 'y' => 200, 'label' => 'Final Manager Approval'],
                ['id' => 'end', 'type' => 'end', 'x' => 1220, 'y' => 200, 'label' => 'Approved'],
            ],
            'edges' => [
                ['from' => 'start', 'to' => 'step-1'],
                ['from' => 'step-1', 'to' => 'step-2'],
                ['from' => 'step-2', 'to' => 'step-3'],
                ['from' => 'step-3', 'to' => 'step-4'],
                ['from' => 'step-4', 'to' => 'step-5'],
                ['from' => 'step-5', 'to' => 'end'],
            ],
        ];
    }

    private function simplifiedCanvasData(): array
    {
        return [
            'nodes' => [
                ['id' => 'start', 'type' => 'start', 'x' => 50, 'y' => 200, 'label' => 'Start'],
                ['id' => 'step-1', 'type' => 'step', 'x' => 250, 'y' => 200, 'label' => 'Manager Approval'],
                ['id' => 'end', 'type' => 'end', 'x' => 450, 'y' => 200, 'label' => 'Approved'],
            ],
            'edges' => [
                ['from' => 'start', 'to' => 'step-1'],
                ['from' => 'step-1', 'to' => 'end'],
            ],
        ];
    }
}
