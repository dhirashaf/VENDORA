<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('vendor_code', 30)->unique();
            $table->string('sap_vendor_id', 30)->nullable();
            $table->string('name', 200);
            $table->string('vendor_type', 20);
            $table->string('vendor_category', 20);
            $table->boolean('is_strategic')->default(true);
            $table->string('evaluation_frequency', 20)->default('Annual');
            $table->date('contract_end_date')->nullable();
            $table->string('contact_person', 150)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->string('status', 30)->default('Active');
            $table->timestamps();
        });

        Schema::create('form_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('version', 20);
            $table->date('effective_date')->nullable();
            $table->boolean('is_active')->default(true);
        });

        Schema::create('assessment_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_template_id')->constrained()->cascadeOnDelete();
            $table->char('code', 1);
            $table->string('name', 150);
            $table->integer('sort_order');
            $table->unique(['form_template_id', 'code']);
        });

        Schema::create('assessment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('assessment_categories')->cascadeOnDelete();
            $table->integer('item_no');
            $table->text('description');
            $table->decimal('weight_pct', 5, 2);
            $table->string('assessor_department', 30);
            $table->integer('sort_order');
            $table->boolean('is_active')->default(true);
            $table->unique(['category_id', 'item_no']);
        });

        Schema::create('score_scales', function (Blueprint $table) {
            $table->integer('score')->primary();
            $table->string('label', 50);
            $table->text('definition')->nullable();
        });

        Schema::create('evaluation_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('cycle_code', 40)->unique();
            $table->foreignId('vendor_id')->constrained();
            $table->foreignId('form_template_id')->constrained();
            $table->string('period_label', 30);
            $table->string('cycle_type', 20);
            $table->text('adhoc_reason')->nullable();
            $table->date('start_date');
            $table->integer('sla_days')->default(14);
            $table->date('due_date');
            $table->string('status', 30)->default('Scheduled');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->index(['vendor_id', 'period_label']);
            $table->index('status');
        });

        Schema::create('evaluation_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->constrained('evaluation_cycles')->cascadeOnDelete();
            $table->foreignId('assessor_id')->constrained('users');
            $table->string('department', 30);
            $table->string('status', 30)->default('Not_Started');
            $table->decimal('progress_pct', 5, 2)->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('last_saved_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unique(['cycle_id', 'assessor_id']);
            $table->index('status');
        });

        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cycle_id')->unique()->constrained('evaluation_cycles');
            $table->decimal('total_weighted_score', 6, 2)->nullable();
            $table->decimal('achievement_pct', 5, 2)->nullable();
            $table->string('recommendation', 40)->nullable();
            $table->boolean('is_complete')->default(false);
            $table->foreignId('compiled_by')->nullable()->constrained('users');
            $table->timestamp('compiled_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
        });

        Schema::create('evaluation_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_id')->constrained('evaluation_assignments')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('assessment_items');
            $table->integer('score');
            $table->decimal('weight_pct', 5, 2);
            $table->decimal('weighted_score', 6, 2);
            $table->text('notes')->nullable();
            $table->string('evidence_url', 500)->nullable();
            $table->timestamp('scored_at')->useCurrent();
            $table->unique(['evaluation_id', 'item_id']);
            $table->foreign('score')->references('score')->on('score_scales');
        });

        Schema::create('reminder_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('subject', 200);
            $table->text('body');
            $table->string('channel', 20)->default('Email');
        });

        Schema::create('reminder_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('trigger_type', 20);
            $table->integer('offset_days');
            $table->integer('repeat_every_days')->nullable();
            $table->foreignId('template_id')->constrained('reminder_templates');
            $table->foreignId('escalate_to_role_id')->nullable()->constrained('roles');
            $table->boolean('is_active')->default(true);
        });

        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('evaluation_assignments')->cascadeOnDelete();
            $table->foreignId('rule_id')->constrained('reminder_rules');
            $table->foreignId('recipient_id')->constrained('users');
            $table->string('channel', 20);
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->string('status', 20)->default('Scheduled');
            $table->boolean('is_escalation')->default(false);
            $table->text('error_message')->nullable();
            $table->index(['assignment_id', 'scheduled_at']);
            $table->index('status');
        });

        Schema::create('follow_ups', function (Blueprint $table) {
            $table->id();
            $table->string('followup_no', 40)->unique();
            $table->foreignId('evaluation_id')->constrained('evaluations');
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->string('status', 30)->default('Open');
            $table->foreignId('pic_user_id')->constrained('users');
            $table->date('due_date')->nullable();
            $table->string('nc_category', 30)->nullable();
            $table->text('nc_description')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('requested_corrective_action')->nullable();
            $table->text('preventive_action')->nullable();
            $table->text('impact_assessment')->nullable();
            $table->text('vendor_explanation')->nullable();
            $table->text('vendor_corrective_action')->nullable();
            $table->date('target_completion_date')->nullable();
            $table->text('result_recommendation')->nullable();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();
            $table->index(['vendor_id', 'status']);
        });

        Schema::create('followup_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('followup_id')->constrained('follow_ups')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamp('changed_at')->useCurrent();
            $table->text('notes')->nullable();
        });

        Schema::create('ethics_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('followup_id')->constrained('follow_ups')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->constrained('users');
            $table->boolean('fraud_indication')->default(false);
            $table->boolean('due_diligence_required')->default(false);
            $table->text('findings')->nullable();
            $table->timestamp('reviewed_at')->nullable();
        });

        Schema::create('followup_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('followup_id')->constrained('follow_ups')->cascadeOnDelete();
            $table->timestamp('scheduled_at');
            $table->string('location_or_link', 300)->nullable();
            $table->foreignId('organized_by')->constrained('users');
            $table->text('minutes')->nullable();
            $table->timestamp('held_at')->nullable();
        });

        Schema::create('followup_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('followup_id')->constrained('follow_ups')->cascadeOnDelete();
            $table->string('file_name', 255);
            $table->string('file_url', 500);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamp('uploaded_at')->useCurrent();
        });

        Schema::create('vendor_status_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('followup_id')->unique()->constrained('follow_ups');
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->string('decision', 40);
            $table->text('reason')->nullable();
            $table->foreignId('decided_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->date('effective_date')->nullable();
            $table->timestamp('decided_at')->useCurrent();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('action', 50);
            $table->string('entity_name', 50);
            $table->integer('entity_id')->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_name', 'entity_id']);
            $table->index('created_at');
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('setting_key', 80)->unique();
            $table->string('setting_value', 255);
            $table->text('description')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'system_settings', 'audit_logs', 'vendor_status_decisions', 'followup_attachments',
            'followup_meetings', 'ethics_reviews', 'followup_status_history', 'follow_ups',
            'reminder_logs', 'reminder_rules', 'reminder_templates', 'evaluation_scores',
            'evaluations', 'evaluation_assignments', 'evaluation_cycles', 'score_scales',
            'assessment_items', 'assessment_categories', 'form_templates', 'vendors',
        ];
        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }
};
