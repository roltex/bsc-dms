<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed order matters — each seeder depends on the ones above it.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,              // users (no dependencies)
            DocumentCategorySeeder::class,  // document_categories → users (default_lawyer_id)
            DocumentTemplateSeeder::class,  // document_templates → document_categories
            PartnerSeeder::class,           // partners → users; partner_documents → partners
            WorkflowRouteSeeder::class,     // workflow_routes, workflow_steps, workflow_transitions
            TaskSeeder::class,              // tasks → users, partners, categories; task_documents, task_activities, task_reviewers
            NotificationSeeder::class,      // notifications → users, tasks
            SubstitutionSeeder::class,      // substitutions → users
            FinalizedDocumentSeeder::class, // finalized_documents → users
            SettingSeeder::class,           // settings (no dependencies)
            InventoryItemSeeder::class,     // inventory_items (no dependencies)
            PlaceholderVariableSeeder::class, // placeholder_variables (no dependencies)
        ]);
    }
}
