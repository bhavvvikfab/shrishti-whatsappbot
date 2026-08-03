@extends('layouts.masters')

@section('page_title', 'Masters - Agents')

@section('masters_content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h5 mb-0 text-muted fw-semibold">Manage Agents</h2>
    <a href="{{ route('masters.agents.create') }}" class="btn btn-primary btn-sm">Add Agent</a>
</div>

@if (session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
@endif

<div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Location</th>
                            <th>Type</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($agents as $agent)
                            <tr>
                                <td>{{ $agent->name }}</td>
                                <td>{{ $agent->code }}</td>
                                <td>{{ $agent->city?->name ?? 'N/A' }}, {{ $agent->country?->name ?? 'N/A' }}</td>
                                <td>{{ $agent->type }}</td>
                                <td>{{ $agent->email }}</td>
                                <td>{{ $agent->phone }}</td>
                                <td>
                                    @if($agent->is_active)
                                        <span class="badge crm-status-pill rounded-pill bg-success">Active</span>
                                    @else
                                        <span class="badge crm-status-pill rounded-pill bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('masters.agents.edit', $agent) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form action="{{ route('masters.agents.destroy', $agent) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this agent?')">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted small">No agents added yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
</div>
<div class="mt-3">
    {{ $agents->links() }}
</div>
@endsection
