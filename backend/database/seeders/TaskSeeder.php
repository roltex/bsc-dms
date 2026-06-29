<?php

namespace Database\Seeders;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\DocumentCategory;
use App\Models\Partner;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskDocument;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    private array $initiators;
    private array $managers;
    private array $lawyers;
    private array $categories;
    private array $partners;

    public function run(): void
    {
        $this->initiators = User::where('role', UserRole::Initiator)->pluck('id')->all();
        $this->managers = User::where('role', UserRole::Manager)->pluck('id')->all();
        $this->lawyers = User::where('role', UserRole::Lawyer)->pluck('id')->all();
        $this->categories = DocumentCategory::pluck('id', 'code')->all();
        $this->partners = Partner::whereNull('blacklisted_at')->pluck('id')->all();

        $this->seedDraftTasks();
        $this->seedPendingManagerTasks();
        $this->seedPendingLawyerTasks();
        $this->seedPendingInitiatorTasks();
        $this->seedPendingFinalLawyerTasks();
        $this->seedPendingFinalManagerTasks();
        $this->seedApprovedTasks();
        $this->seedRejectedTasks();
        $this->seedArchivedTasks();
        $this->seedSimplifiedRouteTasks();
    }

    private function seedDraftTasks(): void
    {
        $scenarios = [
            ['category' => 'supply', 'terms' => 'Annual hops supply for Q3-Q4 2026. Price per ton: 850,000 KZT. Minimum order: 20 tons.'],
            ['category' => 'services', 'terms' => 'IT infrastructure maintenance for Almaty brewery. Monthly retainer: 1,200,000 KZT.'],
            ['category' => 'marketing', 'terms' => 'Summer festival sponsorship package. Budget: 5,000,000 KZT. Duration: June-August 2026.'],
        ];

        foreach ($scenarios as $i => $s) {
            $task = $this->createTask($s['category'], [
                'status' => TaskStatus::Draft,
                'current_step' => 0,
                'commercial_terms' => $s['terms'],
                'deadline' => now()->addDays(rand(10, 30)),
                'created_at' => now()->subDays(rand(1, 3)),
            ]);

            $this->addActivity($task, $task->initiator_id, 'created', 'Task created as draft');
            $this->addDocument($task, 1);
        }
    }

    private function seedPendingManagerTasks(): void
    {
        $scenarios = [
            ['category' => 'supply', 'terms' => 'Barley supply agreement. 500 tons at 420,000 KZT/ton. Delivery: Karaganda warehouse.'],
            ['category' => 'services', 'terms' => 'Legal consulting for cross-border logistics. Fixed fee: 3,500,000 KZT.'],
        ];

        foreach ($scenarios as $s) {
            $task = $this->createTask($s['category'], [
                'status' => TaskStatus::PendingManager,
                'current_step' => 1,
                'commercial_terms' => $s['terms'],
                'deadline' => now()->addDays(rand(7, 21)),
                'created_at' => now()->subDays(rand(3, 7)),
            ]);

            $this->addActivity($task, $task->initiator_id, 'created', 'Task created');
            $this->addDocument($task, 1);
            $this->addActivity($task, $task->initiator_id, 'submitted', 'Submitted for manager approval');
        }
    }

    private function seedPendingLawyerTasks(): void
    {
        $scenarios = [
            ['category' => 'supply', 'terms' => 'Glass bottle supply. 2M units per quarter. Price negotiated at 185 KZT/unit.'],
            ['category' => 'bank_guarantees', 'terms' => 'Performance guarantee for distribution contract. Amount: 25,000,000 KZT.'],
        ];

        foreach ($scenarios as $s) {
            $task = $this->createTask($s['category'], [
                'status' => TaskStatus::PendingLawyer,
                'current_step' => 2,
                'assigned_lawyer_id' => fake()->randomElement($this->lawyers),
                'commercial_terms' => $s['terms'],
                'deadline' => now()->addDays(rand(5, 15)),
                'created_at' => now()->subDays(rand(5, 10)),
            ]);

            $this->addActivity($task, $task->initiator_id, 'created', 'Task created');
            $this->addDocument($task, 1);
            $this->addActivity($task, $task->initiator_id, 'submitted', 'Submitted for review');
            $this->addActivity($task, fake()->randomElement($this->managers), 'approved', 'Approved by manager. Forwarded to legal.');
        }
    }

    private function seedPendingInitiatorTasks(): void
    {
        $task = $this->createTask('services', [
            'status' => TaskStatus::PendingInitiator,
            'current_step' => 3,
            'assigned_lawyer_id' => fake()->randomElement($this->lawyers),
            'commercial_terms' => 'Equipment calibration services. Annual contract: 4,800,000 KZT. Quarterly payments.',
            'deadline' => now()->addDays(10),
            'created_at' => now()->subDays(12),
        ]);

        $this->addActivity($task, $task->initiator_id, 'created', 'Task created');
        $this->addDocument($task, 1);
        $this->addActivity($task, $task->initiator_id, 'submitted', 'Submitted for review');
        $this->addActivity($task, fake()->randomElement($this->managers), 'approved', 'Manager approved');
        $this->addActivity($task, $task->assigned_lawyer_id, 'approved', 'Legal review complete. Clause 4.2 amended per company policy. Please review changes and upload signed version.', ['changes' => 'Modified liability cap in clause 4.2']);
        $this->addDocument($task, 2);
    }

    private function seedPendingFinalLawyerTasks(): void
    {
        $task = $this->createTask('supply', [
            'status' => TaskStatus::PendingFinalLawyer,
            'current_step' => 4,
            'assigned_lawyer_id' => fake()->randomElement($this->lawyers),
            'commercial_terms' => 'Malt extract supply. 100 tons monthly. 12-month framework agreement.',
            'deadline' => now()->addDays(5),
            'created_at' => now()->subDays(15),
        ]);

        $this->addActivity($task, $task->initiator_id, 'created', 'Task created');
        $this->addDocument($task, 1);
        $this->addActivity($task, $task->initiator_id, 'submitted', 'Submitted');
        $this->addActivity($task, fake()->randomElement($this->managers), 'approved', 'Manager approved');
        $this->addActivity($task, $task->assigned_lawyer_id, 'approved', 'Legal approved with amendments');
        $this->addDocument($task, 2);
        $this->addActivity($task, $task->initiator_id, 'approved', 'Signed document uploaded');
        $this->addDocument($task, 3, true);
    }

    private function seedPendingFinalManagerTasks(): void
    {
        $task = $this->createTask('marketing', [
            'status' => TaskStatus::PendingFinalManager,
            'current_step' => 5,
            'assigned_lawyer_id' => fake()->randomElement($this->lawyers),
            'commercial_terms' => 'Nauryz campaign sponsorship. Total budget: 8,500,000 KZT. Coverage: national TV + digital.',
            'deadline' => now()->addDays(3),
            'created_at' => now()->subDays(18),
        ]);

        $this->addActivity($task, $task->initiator_id, 'created', 'Task created');
        $this->addDocument($task, 1);
        $this->addActivity($task, $task->initiator_id, 'submitted', 'Submitted');
        $this->addActivity($task, fake()->randomElement($this->managers), 'approved', 'Manager approved');
        $this->addActivity($task, $task->assigned_lawyer_id, 'approved', 'Legal approved');
        $this->addDocument($task, 2);
        $this->addActivity($task, $task->initiator_id, 'approved', 'Signed version uploaded');
        $this->addDocument($task, 3, true);
        $this->addActivity($task, $task->assigned_lawyer_id, 'approved', 'Final legal sign-off complete');
    }

    private function seedApprovedTasks(): void
    {
        $scenarios = [
            [
                'category' => 'supply',
                'terms' => 'Yeast supply agreement. 50 tons annually. Supplier: АО "КазПивТрейд".',
                'reg' => 'SUP-2026-0001',
            ],
            [
                'category' => 'services',
                'terms' => 'Cleaning services for production facility. Monthly: 450,000 KZT.',
                'reg' => 'SVC-2026-0002',
            ],
            [
                'category' => 'powers_of_attorney',
                'terms' => 'Power of Attorney for tax representation. Valid until 31 December 2026.',
                'reg' => 'POA-2026-0003',
            ],
            [
                'category' => 'labels',
                'terms' => 'New label design for "Efes Pilsener" 0.5L can. Print run: 10M units.',
                'reg' => 'LBL-2026-0004',
            ],
        ];

        foreach ($scenarios as $s) {
            $task = $this->createTask($s['category'], [
                'status' => TaskStatus::Approved,
                'current_step' => 6,
                'assigned_lawyer_id' => fake()->randomElement($this->lawyers),
                'commercial_terms' => $s['terms'],
                'registration_number' => $s['reg'],
                'deadline' => now()->subDays(rand(1, 5)),
                'validity_from' => now()->subDays(rand(1, 5)),
                'validity_to' => now()->addYear(),
                'created_at' => now()->subDays(rand(20, 40)),
            ]);

            $this->addActivity($task, $task->initiator_id, 'created', 'Task created');
            $this->addDocument($task, 1);
            $this->addActivity($task, $task->initiator_id, 'submitted', 'Submitted');
            $this->addActivity($task, fake()->randomElement($this->managers), 'approved', 'Manager approved');
            $this->addActivity($task, $task->assigned_lawyer_id, 'approved', 'Legal approved');
            $this->addDocument($task, 2);
            $this->addActivity($task, $task->initiator_id, 'approved', 'Signed document uploaded');
            $this->addDocument($task, 3, true);
            $this->addActivity($task, $task->assigned_lawyer_id, 'approved', 'Final legal sign-off');
            $this->addActivity($task, fake()->randomElement($this->managers), 'approved', 'Final manager approval. Registration number assigned: '.$s['reg']);
        }
    }

    private function seedRejectedTasks(): void
    {
        $scenarios = [
            [
                'category' => 'supply',
                'terms' => 'Aluminum can supply. Pricing above market by 40%.',
                'reason' => 'Commercial terms unacceptable. Unit price exceeds market rate by 40%. Please renegotiate with supplier.',
                'step' => 1,
                'rejector_role' => 'manager',
            ],
            [
                'category' => 'services',
                'terms' => 'Consulting agreement with undefined scope of work.',
                'reason' => 'Legal cannot approve: scope of work is vague, indemnification clause missing, governing law not specified. Please revise.',
                'step' => 2,
                'rejector_role' => 'lawyer',
            ],
        ];

        foreach ($scenarios as $s) {
            $rejectorId = $s['rejector_role'] === 'manager'
                ? fake()->randomElement($this->managers)
                : fake()->randomElement($this->lawyers);

            $task = $this->createTask($s['category'], [
                'status' => TaskStatus::Rejected,
                'current_step' => $s['step'],
                'assigned_lawyer_id' => $s['rejector_role'] === 'lawyer' ? $rejectorId : null,
                'commercial_terms' => $s['terms'],
                'deadline' => now()->subDays(5),
                'created_at' => now()->subDays(rand(10, 20)),
            ]);

            $this->addActivity($task, $task->initiator_id, 'created', 'Task created');
            $this->addDocument($task, 1);
            $this->addActivity($task, $task->initiator_id, 'submitted', 'Submitted for review');

            if ($s['step'] >= 2) {
                $this->addActivity($task, fake()->randomElement($this->managers), 'approved', 'Manager approved');
            }

            $this->addActivity($task, $rejectorId, 'rejected', $s['reason']);
        }
    }

    private function seedArchivedTasks(): void
    {
        $scenarios = [
            ['category' => 'supply', 'terms' => 'Expired grain supply agreement from 2025.', 'reg' => 'SUP-2025-0088'],
            ['category' => 'services', 'terms' => 'One-time equipment installation. Completed Nov 2025.', 'reg' => 'SVC-2025-0055'],
        ];

        foreach ($scenarios as $s) {
            $task = $this->createTask($s['category'], [
                'status' => TaskStatus::Archived,
                'current_step' => 6,
                'assigned_lawyer_id' => fake()->randomElement($this->lawyers),
                'commercial_terms' => $s['terms'],
                'registration_number' => $s['reg'],
                'validity_from' => now()->subYear(),
                'validity_to' => now()->subMonths(2),
                'created_at' => now()->subMonths(rand(6, 12)),
            ]);

            $this->addActivity($task, $task->initiator_id, 'created', 'Task created');
            $this->addDocument($task, 1);
            $this->addActivity($task, $task->initiator_id, 'submitted', 'Submitted');
            $this->addActivity($task, fake()->randomElement($this->managers), 'approved', 'Manager approved');
            $this->addActivity($task, $task->assigned_lawyer_id, 'approved', 'Legal approved');
            $this->addDocument($task, 2);
            $this->addActivity($task, $task->initiator_id, 'approved', 'Signed');
            $this->addDocument($task, 3, true);
            $this->addActivity($task, $task->assigned_lawyer_id, 'approved', 'Final legal');
            $this->addActivity($task, fake()->randomElement($this->managers), 'approved', 'Approved');
            $this->addActivity($task, fake()->randomElement($this->managers), 'archived', 'Contract expired. Moved to archive.');
        }
    }

    private function seedSimplifiedRouteTasks(): void
    {
        // Simplified: Initiator → Manager → Approved (2 steps)
        $task1 = $this->createTask('powers_of_attorney', [
            'route_type' => 'simplified',
            'status' => TaskStatus::PendingManager,
            'current_step' => 1,
            'commercial_terms' => 'Power of Attorney for courier service contract signing.',
            'deadline' => now()->addDays(5),
            'created_at' => now()->subDays(2),
        ]);

        $this->addActivity($task1, $task1->initiator_id, 'created', 'Task created (simplified route)');
        $this->addDocument($task1, 1);
        $this->addActivity($task1, $task1->initiator_id, 'submitted', 'Submitted for manager approval');

        $task2 = $this->createTask('other', [
            'route_type' => 'simplified',
            'status' => TaskStatus::Approved,
            'current_step' => 2,
            'commercial_terms' => 'NDA with potential distributor. Mutual confidentiality for 3 years.',
            'registration_number' => 'OTH-2026-0010',
            'validity_from' => now()->subDays(3),
            'validity_to' => now()->addYears(3),
            'created_at' => now()->subDays(8),
        ]);

        $this->addActivity($task2, $task2->initiator_id, 'created', 'Task created (simplified route)');
        $this->addDocument($task2, 1);
        $this->addActivity($task2, $task2->initiator_id, 'submitted', 'Submitted');
        $this->addActivity($task2, fake()->randomElement($this->managers), 'approved', 'Approved by manager. Registration: OTH-2026-0010');

        // Fast-tracked task
        $task3 = $this->createTask('services', [
            'status' => TaskStatus::Approved,
            'current_step' => 6,
            'assigned_lawyer_id' => fake()->randomElement($this->lawyers),
            'fast_tracked' => true,
            'commercial_terms' => 'Emergency pest control services. Urgent due to production deadline.',
            'registration_number' => 'SVC-2026-0099',
            'validity_from' => now()->subDays(1),
            'validity_to' => now()->addMonths(1),
            'created_at' => now()->subDays(5),
        ]);

        $this->addActivity($task3, $task3->initiator_id, 'created', 'Task created');
        $this->addDocument($task3, 1);
        $this->addActivity($task3, $task3->initiator_id, 'submitted', 'Submitted — marked urgent');
        $this->addActivity($task3, fake()->randomElement($this->managers), 'approved', 'Manager approved');
        $this->addActivity($task3, $task3->assigned_lawyer_id, 'fast_tracked', 'Fast-tracked due to production emergency. Approved with standard terms.');
        $this->addDocument($task3, 2, true);

        // Task with reviewer
        $task4 = $this->createTask('bank_guarantees', [
            'status' => TaskStatus::PendingLawyer,
            'current_step' => 2,
            'assigned_lawyer_id' => $this->lawyers[0],
            'commercial_terms' => 'Bank guarantee for customs clearance. Amount: 15,000,000 KZT.',
            'deadline' => now()->addDays(7),
            'created_at' => now()->subDays(6),
        ]);

        $this->addActivity($task4, $task4->initiator_id, 'created', 'Task created');
        $this->addDocument($task4, 1);
        $this->addActivity($task4, $task4->initiator_id, 'submitted', 'Submitted');
        $this->addActivity($task4, fake()->randomElement($this->managers), 'approved', 'Manager approved');

        if (count($this->lawyers) > 1) {
            $task4->reviewers()->attach($this->lawyers[1]);
            $this->addActivity($task4, $this->lawyers[0], 'reviewer_added', 'Added reviewer for second opinion on guarantee terms', ['reviewer_id' => $this->lawyers[1]]);
        }
    }

    private function createTask(string $categoryCode, array $overrides = []): Task
    {
        $defaults = [
            'document_category_id' => $this->categories[$categoryCode] ?? $this->categories['other'],
            'partner_id' => fake()->randomElement($this->partners),
            'initiator_id' => fake()->randomElement($this->initiators),
            'route_type' => 'standard',
        ];

        return Task::create(array_merge($defaults, $overrides));
    }

    private function addActivity(Task $task, ?int $userId, string $action, ?string $comment = null, ?array $meta = null): TaskActivity
    {
        return TaskActivity::create([
            'task_id' => $task->id,
            'user_id' => $userId ?? $task->initiator_id,
            'action' => $action,
            'comment' => $comment,
            'meta' => $meta,
        ]);
    }

    private function addDocument(Task $task, int $version, bool $isSigned = false): TaskDocument
    {
        $ext = $isSigned ? 'signed.pdf' : 'v'.$version.'.docx';

        return TaskDocument::create([
            'task_id' => $task->id,
            'path' => 'task-documents/'.$task->id.'/document-'.$ext,
            'mime_type' => $isSigned ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'version' => $version,
            'is_signed' => $isSigned,
            'approved_at' => $isSigned ? now() : null,
        ]);
    }
}
