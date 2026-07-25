<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // withTrashed: archived accounts stay listed (with a Restore action) instead of vanishing.
        $query = User::withTrashed()->with(['roles', 'department'])->orderBy('name');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $request->role));
        }

        $users = $query->paginate(20)->withQueryString();
        $roles = $this->assignableRoles();

        return view('admin.users.index', compact('users', 'roles'));
    }

    public function create()
    {
        $roles = $this->assignableRoles();
        $departments = $this->assignableDepartments();

        return view('admin.users.create', compact('roles', 'departments'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            // Only ACTIVE accounts block the address. Archived (soft-deleted) users
            // keep their row + the DB unique constraint, so we ignore them here and
            // revive the account below instead of colliding.
            'email' => ['required', 'email', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        // Re-adding an email that belongs to an archived account restores and
        // updates that account (a fresh insert would hit the unique constraint).
        $user = User::onlyTrashed()->where('email', $validated['email'])->first();

        if ($user) {
            $user->restore();
            $user->update([
                'name' => $validated['name'],
                'password' => Hash::make($validated['password']),
                'is_active' => true,
                'department_id' => $validated['department_id'] ?? null,
            ]);
            $message = "Archived account for {$user->name} was restored and updated.";
        } else {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'is_active' => true,
                'department_id' => $validated['department_id'] ?? null,
            ]);
            $message = "User {$user->name} created successfully.";
        }

        $user->syncRoles([$validated['role']]);

        return redirect()->route('admin.users.index')
            ->with('success', $message);
    }

    public function edit(User $user)
    {
        $roles = $this->assignableRoles();
        $departments = $this->assignableDepartments();

        return view('admin.users.edit', compact('user', 'roles', 'departments'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)->whereNull('deleted_at')],
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|string',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'department_id' => $validated['department_id'] ?? null,
        ]);

        if (! empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        $user->syncRoles([$validated['role']]);

        return redirect()->route('admin.users.index')
            ->with('success', "User {$user->name} updated successfully.");
    }

    public function toggleActive(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        $action = $user->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Account for {$user->name} has been {$action}.");
    }

    public function archive(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot archive your own account.');
        }

        $user->delete();

        return back()->with('success', "Account for {$user->name} has been archived.");
    }

    /**
     * Apply one action to several accounts at once. The current user is always
     * excluded from the selection so an admin can't lock themselves out.
     */
    public function bulk(Request $request)
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['activate', 'deactivate', 'archive'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', Rule::exists('users', 'id')],
        ]);

        $ids = collect($validated['ids'])
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === auth()->id())
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return back()->with('error', 'No eligible accounts were selected (your own account is always excluded).');
        }

        $users = User::whereIn('id', $ids)->get();

        $affected = match ($validated['action']) {
            'activate' => tap($users->where('is_active', false), fn ($set) => User::whereIn('id', $set->pluck('id'))->update(['is_active' => true]))->count(),
            'deactivate' => tap($users->where('is_active', true), fn ($set) => User::whereIn('id', $set->pluck('id'))->update(['is_active' => false]))->count(),
            'archive' => $users->each->delete()->count(),
        };

        if ($affected === 0) {
            return back()->with('error', 'Nothing changed — the selected accounts were already in that state.');
        }

        $verb = ['activate' => 'activated', 'deactivate' => 'deactivated', 'archive' => 'archived'][$validated['action']];

        return back()->with('success', "{$affected} ".($affected === 1 ? 'account' : 'accounts')." {$verb}.");
    }

    public function restore(User $user)
    {
        $user->restore();

        return back()->with('success', "Account for {$user->name} has been restored.");
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        try {
            $user->forceDelete();
        } catch (QueryException) {
            // documents.created_by / document_scans.scanned_by restrict deletion
            return back()->with('error', "{$user->name} has document activity and cannot be permanently deleted. Archive the account instead.");
        }

        return back()->with('success', "Account for {$user->name} has been permanently deleted.");
    }

    private function assignableRoles()
    {
        return Role::orderBy('name')->get();
    }

    private function assignableDepartments()
    {
        return Department::active()->orderBy('name')->get();
    }
}
