<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Support\DepartmentScope;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    // Roles a Department Admin is allowed to assign
    private const DEPT_ADMIN_ASSIGNABLE_ROLES = ['staff', 'receiving_staff'];

    public function index(Request $request)
    {
        // withTrashed: archived accounts stay listed (with a Restore action) instead of vanishing.
        $query = User::withTrashed()->with(['roles', 'department'])->orderBy('name');

        if ($this->isDeptAdmin()) {
            $query->where('department_id', $this->authDeptId())
                ->whereDoesntHave('roles', fn ($r) => $r->whereIn('name', ['super_admin', 'department_admin']));
        }

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

        // For the Add User modal rendered on this page
        $departments = $this->assignableDepartments();
        $deptLocked = $this->isDeptAdmin();

        return view('admin.users.index', compact('users', 'roles', 'departments', 'deptLocked'));
    }

    public function create()
    {
        $roles = $this->assignableRoles();
        $departments = $this->assignableDepartments();
        $deptLocked = $this->isDeptAdmin();

        return view('admin.users.create', compact('roles', 'departments', 'deptLocked'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $this->authorizeRole($validated['role']);

        $deptId = $this->isDeptAdmin()
            ? $this->authDeptId()
            : ($validated['department_id'] ?? null);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'department_id' => $deptId,
            'is_active' => true,
        ]);

        $user->assignRole($validated['role']);

        return redirect()->route('admin.users.index')
            ->with('success', "User {$user->name} created successfully.");
    }

    public function edit(User $user)
    {
        $this->authorizeDeptAccess($user);

        $roles = $this->assignableRoles();
        $departments = $this->assignableDepartments();
        $deptLocked = $this->isDeptAdmin();

        return view('admin.users.edit', compact('user', 'roles', 'departments', 'deptLocked'));
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeDeptAccess($user);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|string',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $this->authorizeRole($validated['role']);

        $deptId = $this->isDeptAdmin()
            ? $this->authDeptId()
            : ($validated['department_id'] ?? null);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'department_id' => $deptId,
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
        $this->authorizeDeptAccess($user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        $action = $user->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Account for {$user->name} has been {$action}.");
    }

    public function archive(User $user)
    {
        $this->authorizeDeptAccess($user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot archive your own account.');
        }

        $user->delete();

        return back()->with('success', "Account for {$user->name} has been archived.");
    }

    public function restore(User $user)
    {
        $this->authorizeDeptAccess($user);

        $user->restore();

        return back()->with('success', "Account for {$user->name} has been restored.");
    }

    public function destroy(User $user)
    {
        $this->authorizeDeptAccess($user);

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

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Department-scoped user manager (not org-wide super admin). */
    private function isDeptAdmin(): bool
    {
        $user = auth()->user();

        return $user->can('manage users') && ! DepartmentScope::isOrgWide($user);
    }

    private function authDeptId(): ?int
    {
        return auth()->user()->department_id;
    }

    private function assignableRoles()
    {
        if ($this->isDeptAdmin()) {
            return Role::whereIn('name', self::DEPT_ADMIN_ASSIGNABLE_ROLES)->orderBy('name')->get();
        }

        return Role::orderBy('name')->get();
    }

    private function assignableDepartments()
    {
        if ($this->isDeptAdmin()) {
            return Department::where('id', $this->authDeptId())->get();
        }

        return Department::orderBy('name')->get();
    }

    private function authorizeRole(string $role): void
    {
        if ($this->isDeptAdmin() && ! in_array($role, self::DEPT_ADMIN_ASSIGNABLE_ROLES)) {
            abort(403, 'You cannot assign this role.');
        }
    }

    private function authorizeDeptAccess(User $user): void
    {
        if ($this->isDeptAdmin() && $user->department_id !== $this->authDeptId()) {
            abort(403, 'You can only manage users in your own department.');
        }
    }
}
