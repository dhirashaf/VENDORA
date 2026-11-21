<?php

namespace Database\Seeders;

use App\Enums\Channel;
use App\Enums\Department;
use App\Enums\ReminderTrigger;
use App\Enums\RoleCode;
use App\Models\FormTemplate;
use App\Models\ReminderRule;
use App\Models\ReminderTemplate;
use App\Models\Role;
use App\Models\ScoreScale;
use App\Support\ReminderDispatcher;
use App\Support\Settings;
use Illuminate\Database\Seeder;

/**
 * Data referensi sistem: peran (RBAC), form Vendor Performance Evaluation (7 kategori / 14 item),
 * skala penilaian, template & aturan reminder, serta pengaturan sistem.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedRoles();
        $this->seedFormTemplate();
        $this->seedScoreScales();
        $this->seedReminders();
        $this->seedSettings();
    }

    private function seedRoles(): void
    {
        $descriptions = [
            RoleCode::VmSpecialist->value => 'Menyusun/compiler evaluasi sekaligus pengelola proses dan master data',
            RoleCode::Assessor->value => 'Penilai Finance/Engineering/Procurement, mengisi item sesuai fungsinya',
            RoleCode::PvmManager->value => 'Procurement & Vendor Management Manager, menyetujui hasil evaluasi dan keputusan vendor',
            RoleCode::User->value => 'Pihak yang dikonsultasikan/diinformasikan (RACI SOP)',
        ];

        foreach (RoleCode::cases() as $role) {
            Role::query()->updateOrCreate(['code' => $role->value], ['name' => $role->label(), 'description' => $descriptions[$role->value]]);
        }
    }

    private function seedFormTemplate(): void
    {
        $formTemplate = FormTemplate::query()->updateOrCreate(
            ['name' => 'Vendor Performance Evaluation', 'version' => 'v1.0'],
            ['effective_date' => '2026-01-01', 'is_active' => true],
        );

        $structure = [
            'A' => ['Kualitas Produk/Jasa', [
                [1, 'Ketepatan spesifikasi barang/jasa', 8, Department::Engineering],
                [2, 'Konsistensi mutu dan tingkat reject', 10, Department::Engineering],
            ]],
            'B' => ['Pengiriman & Ketepatan Waktu', [
                [3, 'Ketepatan waktu pengiriman', 10, Department::Procurement],
                [4, 'Kesesuaian jumlah dan dokumen pengiriman', 8, Department::Procurement],
            ]],
            'C' => ['Harga & Ketentuan Pembayaran', [
                [5, 'Daya saing harga', 8, Department::Finance],
                [6, 'Kepatuhan pada ketentuan pembayaran', 6, Department::Finance],
            ]],
            'D' => ['Layanan & Komunikasi', [
                [7, 'Responsivitas dan kecepatan tanggapan', 6, Department::Procurement],
                [8, 'Kualitas komunikasi dan koordinasi', 6, Department::Procurement],
            ]],
            'E' => ['Penanganan Masalah', [
                [9, 'Kecepatan penanganan keluhan/klaim', 7, Department::Engineering],
                [10, 'Efektivitas tindakan perbaikan', 7, Department::Engineering],
            ]],
            'F' => ['Kepatuhan & Legalitas', [
                [11, 'Kelengkapan dokumen legal dan perizinan', 7, Department::Finance],
                [12, 'Kepatuhan terhadap kontrak dan regulasi', 6, Department::Finance],
            ]],
            'G' => ['Keberlanjutan & Tanggung Jawab Sosial', [
                [13, 'Penerapan K3 dan pengelolaan lingkungan', 6, Department::Engineering],
                [14, 'Tanggung jawab sosial dan etika bisnis', 5, Department::Procurement],
            ]],
        ];

        $sortOrder = 0;

        foreach ($structure as $code => [$name, $items]) {
            $category = $formTemplate->categories()->updateOrCreate(['code' => $code], ['name' => $name, 'sort_order' => ord($code) - 64]);

            foreach ($items as [$itemNo, $description, $weight, $department]) {
                $category->items()->updateOrCreate(['item_no' => $itemNo], [
                    'description' => $description,
                    'weight_pct' => $weight,
                    'assessor_department' => $department,
                    'sort_order' => ++$sortOrder,
                    'is_active' => true,
                ]);
            }
        }
    }

    private function seedScoreScales(): void
    {
        $scales = [
            4 => ['Sangat Baik', 'Melampaui persyaratan secara konsisten, tanpa temuan.'],
            3 => ['Baik', 'Memenuhi persyaratan dengan temuan minor yang segera diperbaiki.'],
            2 => ['Kurang Baik', 'Belum memenuhi sebagian persyaratan, temuan berulang.'],
            1 => ['Tidak Memuaskan', 'Tidak memenuhi persyaratan, berdampak pada operasional.'],
        ];

        foreach ($scales as $score => [$label, $definition]) {
            ScoreScale::query()->updateOrCreate(['score' => $score], ['label' => $label, 'definition' => $definition]);
        }
    }

    private function seedReminders(): void
    {
        $footer = "\n\nBuka form: {form_url}\n\nTerima kasih,\nVendor Management PT Chakra Jawara";
        $templates = [
            ReminderDispatcher::TEMPLATE_FORM_ASSIGNED => [
                '[Evaluasi Vendor] Form penilaian {vendor_name} periode {period}',
                "Yth. {recipient_name},\n\nAnda ditugaskan sebagai penilai fungsi {department} untuk evaluasi kinerja {vendor_name} periode {period} ({cycle_code}).\nMohon lengkapi seluruh item penilaian paling lambat {due_date} ({days_left} hari lagi).".$footer,
            ],
            'REMINDER_BEFORE_DUE' => [
                '[Pengingat] Evaluasi {vendor_name} jatuh tempo {due_date}',
                "Yth. {recipient_name},\n\nForm evaluasi {vendor_name} periode {period} untuk fungsi {department} belum disubmit (progres {progress}).\nTenggat pengisian {due_date}, tersisa {days_left} hari.".$footer,
            ],
            'REMINDER_AFTER_DUE' => [
                '[Lewat Tenggat] Evaluasi {vendor_name} terlambat {days_overdue} hari',
                "Yth. {recipient_name},\n\nForm evaluasi {vendor_name} periode {period} untuk fungsi {department} telah melewati tenggat {due_date} ({days_overdue} hari).\nHasil evaluasi belum dapat dihitung sampai seluruh penilai submit. Mohon segera dilengkapi.".$footer,
            ],
            'ESCALATION_VM' => [
                '[Eskalasi] Penilai {department} belum mengisi evaluasi {vendor_name}',
                "Yth. {recipient_name},\n\nPenilai {assessor_name} (fungsi {department}) belum submit evaluasi {vendor_name} periode {period} ({cycle_code}) dan telah terlambat {days_overdue} hari dari tenggat {due_date}.\nMohon tindak lanjut sesuai kebijakan Vendor Management.".$footer,
            ],
            ReminderDispatcher::TEMPLATE_MANUAL => [
                '[Pengingat] Mohon lengkapi evaluasi {vendor_name}',
                "Yth. {recipient_name},\n\nVendor Management mengingatkan form evaluasi {vendor_name} periode {period} untuk fungsi {department} (progres {progress}). Tenggat {due_date}.".$footer,
            ],
        ];

        $templateIds = [];

        foreach ($templates as $code => [$subject, $body]) {
            $templateIds[$code] = ReminderTemplate::query()->updateOrCreate(['code' => $code], [
                'subject' => $subject, 'body' => $body, 'channel' => Channel::Email,
            ])->id;
        }

        $vmRoleId = Role::query()->where('code', RoleCode::VmSpecialist->value)->value('id');
        $rules = [
            ['Reminder H-7', ReminderTrigger::BeforeDue, 7, null, 'REMINDER_BEFORE_DUE', null, true],
            ['Reminder H-3', ReminderTrigger::BeforeDue, 3, null, 'REMINDER_BEFORE_DUE', null, true],
            ['Reminder H-1', ReminderTrigger::BeforeDue, 1, null, 'REMINDER_BEFORE_DUE', null, true],
            ['Reminder lanjutan tiap 2 hari setelah tenggat', ReminderTrigger::AfterDue, 2, 2, 'REMINDER_AFTER_DUE', null, true],
            ['Eskalasi H+3 ke Vendor Management', ReminderTrigger::Escalation, 3, null, 'ESCALATION_VM', $vmRoleId, true],
            ['Pengiriman form evaluasi', ReminderTrigger::BeforeDue, 14, null, ReminderDispatcher::TEMPLATE_FORM_ASSIGNED, null, false],
            ['Reminder manual (tombol Ingatkan)', ReminderTrigger::BeforeDue, 0, null, ReminderDispatcher::TEMPLATE_MANUAL, null, false],
        ];

        foreach ($rules as [$name, $trigger, $offset, $repeat, $templateCode, $escalateRoleId, $isActive]) {
            ReminderRule::query()->updateOrCreate(['name' => $name], [
                'trigger_type' => $trigger,
                'offset_days' => $offset,
                'repeat_every_days' => $repeat,
                'template_id' => $templateIds[$templateCode],
                'escalate_to_role_id' => $escalateRoleId,
                'is_active' => $isActive,
            ]);
        }
    }

    private function seedSettings(): void
    {
        foreach (Settings::definitions() as $key => $definition) {
            Settings::set($key, $definition['value']);
        }
    }
}
