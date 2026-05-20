<x-app-layout>
    <x-slot name="header">
        <h1 class="text-3xl font-bold tracking-tight text-emerald-950 sm:text-4xl">Audit Log</h1>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6">

        {{-- Filters --}}
        <form method="GET" class="flex flex-wrap gap-3">
            <select name="user" class="rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-400 focus:outline-none">
                <option value="">All Users</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected(request('user') == $u->id)>
                        {{ $u->name }}
                    </option>
                @endforeach
            </select>
            <select name="log" class="rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-400 focus:outline-none">
                <option value="">All Events</option>
                <option value="auth" @selected(request('log') === 'auth')>Auth (Login / Logout)</option>
                <option value="default" @selected(request('log') === 'default')>Document Changes</option>
            </select>
            <input type="date" name="date" value="{{ request('date') }}"
                   class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-emerald-400 focus:outline-none">
            <button type="submit" class="rounded-xl bg-gray-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-900">
                Filter
            </button>
            @if(request()->hasAny(['user', 'log', 'date']))
                <a href="{{ route('admin.audit-log.index') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">
                    Clear
                </a>
            @endif
        </form>

        <div class="overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-md">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Date & Time</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">User</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Event</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Details</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">IP Address</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse($logs as $log)
                            @php
                                $props   = $log->properties ?? collect();
                                $ip      = $props->get('ip', '—');
                                $isAuth  = $log->log_name === 'auth';
                                $isLogin = str_contains(strtolower($log->description), 'logged in');
                            @endphp
                            <tr class="transition hover:bg-gray-50/60">
                                <td class="whitespace-nowrap px-4 py-3.5 text-sm text-gray-700">
                                    <div>{{ $log->created_at->format('M d, Y') }}</div>
                                    <div class="text-xs text-gray-400">{{ $log->created_at->format('h:i:s A') }}</div>
                                </td>
                                <td class="px-4 py-3.5">
                                    @if($log->causer)
                                        <div class="text-sm font-medium text-gray-800">{{ $log->causer->name }}</div>
                                        <div class="text-xs text-gray-400">{{ $props->get('email', $log->causer->email) }}</div>
                                    @else
                                        <span class="text-sm text-gray-400">System</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5">
                                    @if($isAuth)
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold
                                            {{ $isLogin ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $isLogin ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                                            {{ $isLogin ? 'Logged In' : 'Logged Out' }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700">
                                            {{ ucfirst($log->event ?? 'change') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 text-sm text-gray-600">
                                    {{ $log->description }}
                                    @if($log->subject_type && $log->subject_type !== \App\Models\User::class)
                                        <span class="ml-1 text-xs text-gray-400">({{ class_basename($log->subject_type) }} #{{ $log->subject_id }})</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5 font-mono text-xs text-gray-500">
                                    {{ $ip }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-16 text-center text-sm text-gray-400">
                                    No activity logs found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($logs->hasPages())
                <div class="border-t border-gray-100 px-4 py-3">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>

    </div>
</x-app-layout>
