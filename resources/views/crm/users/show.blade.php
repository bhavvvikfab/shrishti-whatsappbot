@extends('layouts.app')

@section('page_title', 'Staff Details')

@push('styles')
    <link rel="stylesheet" href="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'css/users-show.css') }}?v={{ filemtime(public_path('css/users-show.css')) }}">
@endpush

@section('content')
    <div class="container-fluid p-0">
        <div class="row g-4">
            <div class="col-lg-12">
                <div class="card shadow-sm border-0 rounded-4 overflow-hidden detail-view-card">
                    <div class="card-header bg-white border-bottom py-3 px-3 px-md-4">
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                            <div class="flex-grow-1 w-100">
                                <h1 class="h4 mb-1 fw-semibold">Staff Details</h1>
                                <p class="text-muted small mb-0">Complete information about this staff member</p>
                            </div>
                            <div class="d-flex flex-wrap gap-2 w-100 w-md-auto justify-content-lg-end justify-content-md-end">
                                @if(auth()->user()?->isMainAdmin())
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-dark-blue flex-grow-1 flex-md-grow-0">
                                        <i class="bi bi-pencil me-1"></i>Edit
                                    </a>
                                @endif
                                <a href="{{ route('users.index') }}" class="btn btn-dark-blue flex-grow-1 flex-md-grow-0">
                                    <i class="fa-solid fa-angle-left pe-1"></i>
                                    <span>Back</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-3 p-md-4">
                        <div class="detail-view-block">
                            <div class="row g-4 align-items-start">

                                <!-- Responsive Profile Image Section -->
                                <div class="col-md-4 col-lg-3 text-center text-md-start">
                                    {{-- <div class="staff-profile-wrap mx-auto mx-md-0"> --}}
                                        <div class="staff-profile-media bg-light rounded-4 d-inline-flex align-items-center justify-content-center shadow-sm overflow-hidden">
                                            @php
                                                $hasValidAvatar = $user->avatar_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->avatar_path);
                                            @endphp

                                            @if($hasValidAvatar)
                                                <img src="{{ route('users.image', $user) }}?v={{ optional($user->updated_at)->timestamp }}"
                                                     alt="{{ $user->name }}"
                                                     class="staff-profile-img img-fluid shadow-sm"
                                                     loading="lazy">
                                            @else
                                                <span class="staff-profile-placeholder d-flex align-items-center justify-content-center">
                                                    <i class="bi bi-person-circle"></i>
                                                </span>
                                            @endif
                                        </div>
                                    {{-- </div> --}}
                                </div>

                                <!-- Staff Information -->
                                <div class="col-md-8 col-lg-9">
                                    <div class="detail-view-title mb-3">{{ $user->name ?? 'N/A' }}</div>

                                    <div class="row g-0 detail-view-grid">

                                        <!-- Created Info -->
                                        <div class="col-md-6 detail-view-row">
                                            <span class="detail-view-label pe-2"><i class="fas fa-user-tie text-muted me-2"></i>Created By:</span>
                                            <span class="detail-view-value">Admin</span>
                                        </div>
                                        <div class="col-md-6 detail-view-row">
                                            <span class="detail-view-label pe-2"><i class="fas fa-calendar-plus text-muted me-2"></i>Created At:</span>
                                            <span class="detail-view-value">
                                                {{ $user->created_at?->format('d M, Y h:i A') ?? 'N/A' }}
                                            </span>
                                        </div>

                                        <!-- Email -->
                                        <div class="col-md-6 detail-view-row">
                                            <span class="detail-view-label pe-2"><i class="fas fa-envelope text-muted me-2"></i>Email:</span>
                                            <span class="detail-view-value">
                                                @if($user->email)
                                                    <a href="mailto:{{ $user->email }}" class="text-decoration-none link-hover">
                                                        {{ $user->email }}
                                                    </a>
                                                @else
                                                    N/A
                                                @endif
                                            </span>
                                        </div>

                                        <!-- Phone -->
                                        <div class="col-md-6 detail-view-row">
                                            <span class="detail-view-label pe-2"><i class="fas fa-phone-alt text-muted me-2"></i>Phone Number:</span>
                                            <span class="detail-view-value">
                                                @if($user->phone)
                                                    <a href="tel:{{ $user->phone }}" class="text-decoration-none link-hover">
                                                        {{ $user->phone }}
                                                    </a>
                                                @else
                                                    N/A
                                                @endif
                                            </span>
                                        </div>

                                        <!-- WhatsApp -->
                                        <div class="col-md-6 detail-view-row">
                                            <span class="detail-view-label pe-2"><i class="fab fa-whatsapp text-muted me-2"></i>WhatsApp Number:</span>
                                            <span class="detail-view-value">
                                                @if($user->whatsapp)
                                                    <a href="https://wa.me/{{ $user->whatsapp }}" 
                                                       target="_blank" 
                                                       class="text-decoration-none link-hover">
                                                        {{ $user->whatsapp }}
                                                    </a>
                                                @else
                                                    N/A
                                                @endif
                                            </span>
                                        </div>

                                        <!-- Role -->
                                        <div class="col-md-6 detail-view-row">
                                            <span class="detail-view-label pe-2"><i class="fas fa-user-tag text-muted me-2"></i>Role:</span>
                                            <span class="detail-view-value">
                                                {{ $user->roles->pluck('name')->map(fn($role) => ucfirst($role))->implode(', ') ?: 'Staff' }}
                                            </span>
                                        </div>

                                        <!-- Address -->
                                        <div class="col-md-12 detail-view-row">
                                            <span class="detail-view-label pe-2"><i class="fas fa-map-marker-alt text-muted me-2"></i>Address:</span>
                                            <span class="detail-view-value">{{ $user->address ?: 'N/A' }}</span>
                                        </div>

                                        <!-- Status -->
                                        <div class="col-md-6 detail-view-row">
                                            <span class="detail-view-label pe-2"><i class="fas fa-toggle-on text-muted me-2"></i>Status:</span>
                                            <span class="detail-view-value">
                                                <span class="badge {{ ($user->is_active ?? true) ? 'bg-success' : 'bg-secondary' }} rounded-pill px-3">
                                                    {{ ($user->is_active ?? true) ? 'Active' : 'Inactive' }}
                                                </span>
                                            </span>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection