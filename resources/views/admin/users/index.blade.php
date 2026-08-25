@extends('layouts.admin')

@section('title', 'Users')

@section('content')
<!-- Page Header -->
<div class="admin-page-header">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 class="admin-page-title">User Management</h1>
            <p class="admin-page-description">Manage team members and user accounts</p>
        </div>
        <x-button tag="a" href="/admin/users/create" variant="primary">
            <svg class="icon" fill="currentColor" viewBox="0 0 24 24">
                <path fill-rule="evenodd" d="M12 3.75a.75.75 0 0 1 .75.75v6.75h6.75a.75.75 0 0 1 0 1.5h-6.75V20.25a.75.75 0 0 1-1.5 0v-6.75H4.5a.75.75 0 0 1 0-1.5h6.75V4.5a.75.75 0 0 1 .75-.75Z" clip-rule="evenodd" />
            </svg>
            <span>Add User</span>
        </x-button>
    </div>
</div>

<!-- Search & Filter -->
<x-card style="margin-bottom: var(--space-6);">
    <div class="card-body">
        <form method="GET" action="/admin/users" style="display: flex; gap: var(--space-4); align-items: flex-end;">
            <div style="flex: 1;">
                <x-input
                    name="search"
                    label="Search Users"
                    placeholder="Name, email, or role..."
                    value="{{ request('search') }}"
                />
            </div>

            <div>
                <label for="role" style="display: block; font-size: var(--font-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-2);">Role</label>
                <select name="role" id="role" class="input-md" style="min-width: 150px;">
                    <option value="">All Roles</option>
                    <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                    <option value="concierge" {{ request('role') === 'concierge' ? 'selected' : '' }}>Concierge</option>
                    <option value="customer" {{ request('role') === 'customer' ? 'selected' : '' }}>Customer</option>
                </select>
            </div>

            <div style="display: flex; gap: var(--space-2);">
                <x-button type="submit" variant="primary">Filter</x-button>
                <x-button tag="a" href="/admin/users" variant="secondary">Reset</x-button>
            </div>
        </form>
    </div>
</x-card>

<!-- Users Table -->
<x-card>
    <table class="admin-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Joined</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                <tr>
                    <td>
                        <strong>{{ $user->name }}</strong>
                    </td>
                    <td>{{ $user->email }}</td>
                    <td>
                        <x-badge variant="primary" size="sm">{{ ucfirst($user->role) }}</x-badge>
                    </td>
                    <td>
                        @if ($user->email_verified_at)
                            <x-badge variant="success" size="sm" dot>Active</x-badge>
                        @else
                            <x-badge variant="warning" size="sm" dot>Pending</x-badge>
                        @endif
                    </td>
                    <td>{{ $user->created_at->format('M d, Y') }}</td>
                    <td style="text-align: right;">
                        <div style="display: flex; gap: var(--space-2); justify-content: flex-end;">
                            <x-button tag="a" href="/admin/users/{{ $user->id }}/edit" variant="ghost" size="sm" title="Edit">
                                <svg class="icon" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 9l-6.455 6.456M9 9l6 6" />
                                </svg>
                            </x-button>

                            @if ($user->id !== auth()->id())
                                <form method="POST" action="/admin/users/{{ $user->id }}" onsubmit="return confirm('Are you sure?');" style="display: inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-ghost btn-sm" title="Delete" aria-label="Delete user">
                                        <svg class="icon" fill="currentColor" viewBox="0 0 24 24">
                                            <path fill-rule="evenodd" d="M16.5 4.478v.227a48.816 48.816 0 013.878.512.75.75 0 11-.256 1.478m-3.622-13.5a3 3 0 00-3.022 3.022v2.205h2.118a.75.75 0 010 1.5H2.883V15a3 3 0 003 3h15.75A3 3 0 0023.883 15V6.575a3 3 0 00-2.978-3.025h-3.253v-2.205a3 3 0 00-3.022-3.022zm-1.68 4.478a.75.75 0 00-1.5 0v6a.75.75 0 001.5 0v-6zm3 .75a.75.75 0 001.5 0v6a.75.75 0 00-1.5 0v-6z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="admin-table-empty">
                        <p>No users found matching your criteria.</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if ($users->hasPages())
        <div class="card-footer" style="justify-content: center;">
            {{ $users->links() }}
        </div>
    @endif
</x-card>
@endsection