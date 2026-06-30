<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        // withTrashed: archived accounts stay listed (with a Restore action) instead of vanishing.
        $query = User::withTrashed()->with(['roles'])->orderBy('name');

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
        return view('admin.users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|string',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_active' => true,
        ]);

        $user->assignRole($validated['role']);

        return redirect()->route('admin.users.index')
            ->with('success', "User {$user->name} created successfully.");
    }

    public function edit(User $user)
    {
        $roles = $this->assignableRoles();
        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|string',
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
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
}
