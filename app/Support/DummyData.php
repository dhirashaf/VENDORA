<?php

namespace App\Support;

class DummyData
{
    public const DEMO_DATE = '2026-11-13';

    public static function aggregate(): array
    {
        return [
            'total_vendors' => 66,
            'trade_vendors' => 39,
            'non_trade_vendors' => 27,
            'evaluations_completed' => 38,
            'evaluation_completion_pct' => 58,
            'incomplete' => 9,
            'overdue' => 7,
        ];
    }

    public static function roles(): array
    {
        return [
            ['id' => 1, 'code' => 'VM_SPECIALIST', 'name' => 'VM Specialist'],
            ['id' => 2, 'code' => 'ASSESSOR', 'name' => 'Penilai'],
            ['id' => 3, 'code' => 'PVM_MANAGER', 'name' => 'PVM Manager'],
            ['id' => 4, 'code' => 'USER', 'name' => 'User'],
        ];
    }

    public static function users(): array
    {
        return [
            ['id' => 1, 'full_name' => 'Ayu Pratama', 'department' => 'Vendor_Management', 'role_code' => 'VM_SPECIALIST'],
            ['id' => 2, 'full_name' => 'Dimas Finance', 'department' => 'Finance', 'role_code' => 'ASSESSOR'],
            ['id' => 3, 'full_name' => 'Rina Engineering', 'department' => 'Engineering', 'role_code' => 'ASSESSOR'],
            ['id' => 4, 'full_name' => 'Budi Procurement', 'department' => 'Procurement', 'role_code' => 'ASSESSOR'],
            ['id' => 5, 'full_name' => 'Satria Manager', 'department' => 'Vendor_Management', 'role_code' => 'PVM_MANAGER'],
            ['id' => 6, 'full_name' => 'Guest User', 'department' => 'Other', 'role_code' => 'USER'],
        ];
    }

    public static function vendors(): array
    {
        return [
            ['id' => 1, 'vendor_code' => 'VND-0001', 'name' => 'PT Bumi Karya Mandiri', 'vendor_type' => 'Trade', 'vendor_category' => 'Production', 'is_strategic' => true, 'evaluation_frequency' => 'Semester', 'contract_end_date' => '2027-04-30', 'contact_person' => 'Andi Saputra', 'contact_email' => 'andi@example.com', 'status' => 'Active', 'due_date' => '2026-11-14', 'remaining_days' => 1, 'aging' => 0, 'score' => 3.40, 'achievement_pct' => 85, 'recommendation' => 'Recommended', 'missing_assessors' => ''],
            ['id' => 2, 'vendor_code' => 'VND-0002', 'name' => 'PT Citra Mesin Nusantara', 'vendor_type' => 'Trade', 'vendor_category' => 'Production', 'is_strategic' => true, 'evaluation_frequency' => 'Annual', 'contract_end_date' => '2026-12-31', 'contact_person' => 'Dewi Laras', 'contact_email' => 'dewi@example.com', 'status' => 'Not_Recommended', 'due_date' => '2026-11-07', 'remaining_days' => -6, 'aging' => 6, 'score' => 2.10, 'achievement_pct' => 53, 'recommendation' => 'Not Recommended', 'missing_assessors' => 'Finance'],
            ['id' => 3, 'vendor_code' => 'VND-0003', 'name' => 'CV Sinar Logistik Prima', 'vendor_type' => 'Non_Trade', 'vendor_category' => 'Service', 'is_strategic' => false, 'evaluation_frequency' => 'Semester', 'contract_end_date' => '2027-02-28', 'contact_person' => 'Hendra', 'contact_email' => 'hendra@example.com', 'status' => 'Active', 'due_date' => '2026-11-20', 'remaining_days' => 7, 'aging' => 0, 'score' => null, 'achievement_pct' => 57, 'recommendation' => 'Cannot Be Calculated', 'missing_assessors' => 'Engineering, Procurement'],
            ['id' => 4, 'vendor_code' => 'VND-0004', 'name' => 'PT Megah Jasa Teknik', 'vendor_type' => 'Trade', 'vendor_category' => 'Service', 'is_strategic' => true, 'evaluation_frequency' => 'Annual', 'contract_end_date' => '2027-06-15', 'contact_person' => 'Raka', 'contact_email' => 'raka@example.com', 'status' => 'Active', 'due_date' => '2026-11-12', 'remaining_days' => -1, 'aging' => 1, 'score' => 2.75, 'achievement_pct' => 69, 'recommendation' => 'Recommended_With_Notes', 'missing_assessors' => ''],
            ['id' => 5, 'vendor_code' => 'VND-0005', 'name' => 'PT Delta Komponen Indo', 'vendor_type' => 'Trade', 'vendor_category' => 'Production', 'is_strategic' => true, 'evaluation_frequency' => 'Semester', 'contract_end_date' => '2027-01-31', 'contact_person' => 'Lina', 'contact_email' => 'lina@example.com', 'status' => 'Active', 'due_date' => '2026-11-18', 'remaining_days' => 5, 'aging' => 0, 'score' => 3.15, 'achievement_pct' => 79, 'recommendation' => 'Recommended', 'missing_assessors' => 'Procurement'],
            ['id' => 6, 'vendor_code' => 'VND-0006', 'name' => 'PT Panca Pelayanan Mandiri', 'vendor_type' => 'Non_Trade', 'vendor_category' => 'General', 'is_strategic' => false, 'evaluation_frequency' => 'Annual', 'contract_end_date' => '2027-08-20', 'contact_person' => 'Toni', 'contact_email' => 'toni@example.com', 'status' => 'Suspended', 'due_date' => '2026-11-09', 'remaining_days' => -4, 'aging' => 4, 'score' => 2.30, 'achievement_pct' => 58, 'recommendation' => 'Not Recommended', 'missing_assessors' => 'Finance'],
            ['id' => 7, 'vendor_code' => 'VND-0007', 'name' => 'CV Sentra Baja Utama', 'vendor_type' => 'Trade', 'vendor_category' => 'Production', 'is_strategic' => false, 'evaluation_frequency' => 'Annual', 'contract_end_date' => '2026-12-12', 'contact_person' => 'Yoga', 'contact_email' => 'yoga@example.com', 'status' => 'Active', 'due_date' => '2026-11-22', 'remaining_days' => 9, 'aging' => 0, 'score' => 3.05, 'achievement_pct' => 76, 'recommendation' => 'Recommended', 'missing_assessors' => ''],
            ['id' => 8, 'vendor_code' => 'VND-0008', 'name' => 'PT Armada Energi Sejahtera', 'vendor_type' => 'Non_Trade', 'vendor_category' => 'Service', 'is_strategic' => true, 'evaluation_frequency' => 'Semester', 'contract_end_date' => '2027-03-10', 'contact_person' => 'Nisa', 'contact_email' => 'nisa@example.com', 'status' => 'Active', 'due_date' => '2026-11-15', 'remaining_days' => 2, 'aging' => 0, 'score' => null, 'achievement_pct' => 42, 'recommendation' => 'Cannot Be Calculated', 'missing_assessors' => 'Engineering'],
            ['id' => 9, 'vendor_code' => 'VND-0009', 'name' => 'PT Solusi Presisi Digital', 'vendor_type' => 'Non_Trade', 'vendor_category' => 'General', 'is_strategic' => false, 'evaluation_frequency' => 'Annual', 'contract_end_date' => '2028-01-01', 'contact_person' => 'Fajar', 'contact_email' => 'fajar@example.com', 'status' => 'Active', 'due_date' => '2026-11-25', 'remaining_days' => 12, 'aging' => 0, 'score' => 3.60, 'achievement_pct' => 90, 'recommendation' => 'Recommended', 'missing_assessors' => ''],
            ['id' => 10, 'vendor_code' => 'VND-0010', 'name' => 'PT Karya Alat Industri', 'vendor_type' => 'Trade', 'vendor_category' => 'Production', 'is_strategic' => true, 'evaluation_frequency' => 'Semester', 'contract_end_date' => '2027-05-05', 'contact_person' => 'Mira', 'contact_email' => 'mira@example.com', 'status' => 'Blocked', 'due_date' => '2026-11-04', 'remaining_days' => -9, 'aging' => 9, 'score' => 2.05, 'achievement_pct' => 51, 'recommendation' => 'Not Recommended', 'missing_assessors' => ''],
            ['id' => 11, 'vendor_code' => 'VND-0011', 'name' => 'CV Mitra Sumber Daya', 'vendor_type' => 'Non_Trade', 'vendor_category' => 'Service', 'is_strategic' => false, 'evaluation_frequency' => 'Annual', 'contract_end_date' => '2027-11-30', 'contact_person' => 'Agus', 'contact_email' => 'agus@example.com', 'status' => 'Active', 'due_date' => '2026-11-30', 'remaining_days' => 17, 'aging' => 0, 'score' => 2.95, 'achievement_pct' => 74, 'recommendation' => 'Recommended_With_Notes', 'missing_assessors' => ''],
            ['id' => 12, 'vendor_code' => 'VND-0012', 'name' => 'PT Nusantara Maintenance', 'vendor_type' => 'Non_Trade', 'vendor_category' => 'Service', 'is_strategic' => true, 'evaluation_frequency' => 'Annual', 'contract_end_date' => '2027-07-19', 'contact_person' => 'Intan', 'contact_email' => 'intan@example.com', 'status' => 'Active', 'due_date' => '2026-11-10', 'remaining_days' => -3, 'aging' => 3, 'score' => null, 'achievement_pct' => 57, 'recommendation' => 'Cannot Be Calculated', 'missing_assessors' => 'Procurement'],
        ];
    }

    public static function formTemplate(): array
    {
        return [
            'id' => 1,
            'name' => 'Vendor Performance Evaluation',
            'version' => 'v1.0',
            'categories' => [
                ['code' => 'A', 'name' => 'Kualitas', 'weight' => 18],
                ['code' => 'B', 'name' => 'Pengiriman', 'weight' => 18],
                ['code' => 'C', 'name' => 'Harga', 'weight' => 14],
                ['code' => 'D', 'name' => 'Layanan', 'weight' => 12],
                ['code' => 'E', 'name' => 'Penanganan Masalah', 'weight' => 14],
                ['code' => 'F', 'name' => 'Kepatuhan', 'weight' => 13],
                ['code' => 'G', 'name' => 'Keberlanjutan', 'weight' => 11],
            ],
        ];
    }

    public static function cycles(): array
    {
        return [
            ['id' => 1, 'cycle_code' => 'EV-2026-S2-001', 'period_label' => '2026-S2', 'cycle_type' => 'Semester', 'start_date' => '2026-10-30', 'due_date' => '2026-11-14', 'sla_days' => 14, 'status' => 'In Progress', 'vendor_name' => 'PT Bumi Karya Mandiri'],
            ['id' => 2, 'cycle_code' => 'EV-2026-S2-002', 'period_label' => '2026-S2', 'cycle_type' => 'Semester', 'start_date' => '2026-10-24', 'due_date' => '2026-11-07', 'sla_days' => 14, 'status' => 'Lewat Tenggat', 'aging' => 6, 'vendor_name' => 'PT Citra Mesin Nusantara'],
            ['id' => 3, 'cycle_code' => 'EV-2026-S2-003', 'period_label' => '2026-S2', 'cycle_type' => 'Ad_hoc', 'adhoc_reason' => 'Perpanjangan kontrak strategis', 'start_date' => '2026-11-01', 'due_date' => '2026-11-15', 'sla_days' => 14, 'status' => 'Menunggu Approval', 'vendor_name' => 'PT Megah Jasa Teknik'],
            ['id' => 4, 'cycle_code' => 'EV-2026-S1-001', 'period_label' => '2026-S1', 'cycle_type' => 'Semester', 'start_date' => '2026-04-01', 'due_date' => '2026-04-15', 'sla_days' => 14, 'status' => 'Approved', 'vendor_name' => 'PT Delta Komponen Indo'],
            ['id' => 5, 'cycle_code' => 'EV-2025-S2-001', 'period_label' => '2025-S2', 'cycle_type' => 'Semester', 'start_date' => '2025-10-01', 'due_date' => '2025-10-15', 'sla_days' => 14, 'status' => 'Approved', 'vendor_name' => 'PT Bumi Karya Mandiri'],
            ['id' => 6, 'cycle_code' => 'EV-2026-S2-004', 'period_label' => '2026-S2', 'cycle_type' => 'Ad_hoc', 'adhoc_reason' => 'Insiden kualitas berulang', 'start_date' => '2026-11-05', 'due_date' => '2026-11-19', 'sla_days' => 14, 'status' => 'Cancelled', 'vendor_name' => 'PT Panca Pelayanan Mandiri'],
        ];
    }

    public static function assignments(): array
    {
        return [
            ['department' => 'Finance', 'assessor' => 'Dimas Finance', 'status' => 'Submitted', 'progress_pct' => 100],
            ['department' => 'Engineering', 'assessor' => 'Rina Engineering', 'status' => 'Draft', 'progress_pct' => 57],
            ['department' => 'Procurement', 'assessor' => 'Budi Procurement', 'status' => 'Overdue', 'progress_pct' => 29],
        ];
    }

    public static function evaluations(): array
    {
        return [
            ['vendor_id' => 1, 'total_weighted_score' => 3.40, 'achievement_pct' => 85, 'recommendation' => 'Recommended', 'is_complete' => true],
            ['vendor_id' => 2, 'total_weighted_score' => 2.10, 'achievement_pct' => 53, 'recommendation' => 'Not Recommended', 'is_complete' => true],
            ['vendor_id' => 3, 'total_weighted_score' => null, 'achievement_pct' => null, 'recommendation' => 'Cannot Be Calculated', 'is_complete' => false],
        ];
    }
}
