<?php

namespace App\Http\Controllers;

use App\Enums\Department;
use App\Models\Role;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Pengelolaan akun dan peran pengguna (FR-22). Data pribadi dibatasi pada nama,
 * fungsi/jabatan, dan email kerja (UU PDP, NFR-08).
 */
class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::query()->with('role')->orderBy('role_id')->orderBy('full_name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('users.form', ['user' => new User(['is_active' => true]), 'roles' => Role::query()->orderBy('id')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $user = User::query()->create($data);
        AuditLogger::record(AuditLogger::CREATE, $user, null, $user->only(['full_name', 'work_email', 'role_id', 'department']));

        return redirect()->route('users.index')->with('success', "Akun {$user->full_name} berhasil dibuat.");
    }

    public function edit(User $user): View
    {
        return view('users.form', ['user' => $user, 'roles' => Role::query()->orderBy('id')->get()]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        if ($user->is($request->user()) && ! $data['is_active']) {
            return back()->withErrors(['is_active' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
        }

        $user->update($data);
        AuditLogger::record(AuditLogger::UPDATE, $user, null, collect($user->getChanges())->except(['password_hash', 'updated_at'])->all());

        return redirect()->route('users.index')->with('success', "Akun {$user->full_name} diperbarui.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'work_email' => ['required', 'email', 'max:150', Rule::unique('users', 'work_email')->ignore($user?->id)],
            'role_id' => ['required', 'exists:roles,id'],
            'department' => ['required', Rule::enum(Department::class)],
            'job_title' => ['nullable', 'string', 'max:100'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ]);

        $attributes = collect($data)->except('password')->all() + ['is_active' => $request->boolean('is_active')];

        if (filled($data['password'] ?? null)) {
            $attributes['password_hash'] = Hash::make($data['password']);
        }

        return $attributes;
    }
}
