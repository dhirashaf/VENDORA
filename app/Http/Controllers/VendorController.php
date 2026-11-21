<?php

namespace App\Http\Controllers;

use App\Enums\EvaluationFrequency;
use App\Enums\VendorCategory;
use App\Enums\VendorStatus;
use App\Enums\VendorType;
use App\Models\Vendor;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Master data vendor (FR-01, Batasan 7: ID vendor unik yang dapat dipetakan ke SAP).
 */
class VendorController extends Controller
{
    public function index(Request $request): View
    {
        $vendors = Vendor::query()
            ->withCount('evaluationCycles')
            ->when($request->string('search')->toString(), fn (Builder $query, string $search) => $query->where(fn (Builder $match) => $match
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('vendor_code', 'like', '%'.$search.'%')
                ->orWhere('sap_vendor_id', 'like', '%'.$search.'%')))
            ->when($request->string('vendor_type')->toString(), fn (Builder $query, string $type) => $query->where('vendor_type', $type))
            ->when($request->string('status')->toString(), fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy('vendor_code')
            ->paginate(20)
            ->withQueryString();

        return view('vendors.index', [
            'vendors' => $vendors,
            'filters' => $request->only(['search', 'vendor_type', 'status']),
        ]);
    }

    public function create(): View
    {
        return view('vendors.form', ['vendor' => new Vendor([
            'vendor_code' => $this->suggestCode(),
            'is_strategic' => true,
            'evaluation_frequency' => EvaluationFrequency::Annual,
            'status' => VendorStatus::Active,
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $vendor = Vendor::query()->create($this->validated($request));
        AuditLogger::record(AuditLogger::CREATE, $vendor, null, $vendor->only(['vendor_code', 'name', 'vendor_type', 'vendor_category']));

        return redirect()->route('vendors.index')->with('success', "Vendor {$vendor->name} berhasil ditambahkan.");
    }

    public function edit(Vendor $vendor): View
    {
        return view('vendors.form', ['vendor' => $vendor]);
    }

    public function update(Request $request, Vendor $vendor): RedirectResponse
    {
        $vendor->update($this->validated($request, $vendor));
        AuditLogger::recordChanges($vendor);

        return redirect()->route('vendors.index')->with('success', "Data vendor {$vendor->name} diperbarui.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Vendor $vendor = null): array
    {
        $data = $request->validate([
            'vendor_code' => ['required', 'string', 'max:30', Rule::unique('vendors', 'vendor_code')->ignore($vendor?->id)],
            'sap_vendor_id' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:200'],
            'vendor_type' => ['required', Rule::enum(VendorType::class)],
            'vendor_category' => ['required', Rule::enum(VendorCategory::class)],
            'is_strategic' => ['boolean'],
            'evaluation_frequency' => ['required', Rule::enum(EvaluationFrequency::class)],
            'contract_end_date' => ['nullable', 'date'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'status' => ['required', Rule::enum(VendorStatus::class)],
        ], [
            'vendor_code.unique' => 'Kode vendor sudah terdaftar. Gunakan kode vendor yang unik.',
        ]);

        return $data + ['is_strategic' => $request->boolean('is_strategic')];
    }

    private function suggestCode(): string
    {
        $last = Vendor::query()->where('vendor_code', 'like', 'VND-%')->orderByDesc('vendor_code')->value('vendor_code');

        return 'VND-'.str_pad((string) (((int) substr((string) $last, 4)) + 1), 4, '0', STR_PAD_LEFT);
    }
}
