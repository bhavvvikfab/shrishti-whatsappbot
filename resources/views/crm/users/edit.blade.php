@extends('layouts.app')

@section('page_title', 'Edit Staff')

@push('styles')
    <link rel="stylesheet" href="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'css/users-form.css') }}?v={{ filemtime(public_path('css/users-form.css')) }}">
@endpush

@section('content')
    <div class="container-fluid p-0">
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden staff-form-card">

            <!-- Header -->
            <div class="card-header bg-white border-bottom py-3 px-3 px-md-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <h1 class="h4 mb-1 fw-semibold">Edit Staff</h1>
                        <p class="text-muted small mb-0">Update staff details and module access.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('users.show', $user->id) }}" class="btn btn-outline-dark-blue">
                            <i class="bi bi-eye me-1"></i>View
                        </a>
                        <a href="{{ route('users.index') }}" class="btn btn-dark-blue">
                            <i class="fa-solid fa-angle-left pe-1"></i>Back
                        </a>
                    </div>
                </div>
            </div>

            <!-- Form Body -->
            <div class="card-body p-3 p-md-4">
                <form action="{{ url('/api/users/' . $user->id) }}" method="POST" enctype="multipart/form-data" id="staffEditForm" class="ajax-user-form">

                    @csrf
                    @method('PUT')

                    <!-- Anti password manager tricks -->
                    <input type="text" name="fake_username" autocomplete="username" class="d-none" tabindex="-1">
                    <input type="password" name="fake_password" autocomplete="new-password" class="d-none" tabindex="-1">

                    <div id="step-1">
                        <div class="row g-3">
                            <!-- Name -->
                        <div class="col-12">
                            <label class="form-label d-flex align-items-center gap-2" for="name">
                                <i class="bi bi-person-fill"></i> Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}">
                            @error('name')
                                <div class="staff-validation">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Email (Readonly) -->
                        <div class="col-md-6">
                            <label class="form-label d-flex align-items-center gap-2" for="email">
                                <i class="bi bi-envelope-fill"></i> Email <span class="text-danger">*</span>
                            </label>
                            <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" readonly>
                            @error('email')
                                <div class="staff-validation">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Password -->
                        <div class="col-md-6">
                            <label class="form-label d-flex align-items-center gap-2" for="password">
                                <i class="bi bi-key-fill"></i> New Password <span class="text-danger">*</span>
                                <small class="text-muted">(leave blank to keep current)</small>
                            </label>
                            <div class="password-field-wrap position-relative">
                                <input type="password" id="password" name="password" class="form-control pe-5 @error('password') is-invalid @enderror" placeholder="Leave blank to keep current"autocomplete="new-password">
                                <button type="button" class="password-toggle-btn position-absolute top-50 end-0 translate-middle-y me-3" id="toggleEditPassword" aria-label="Toggle password visibility">
                                    <i class="bi bi-eye-slash-fill"></i>
                                </button>
                            </div>
                            @error('password')
                                <div class="staff-validation">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Phone -->
                        <div class="col-md-6">
                            <label class="form-label d-flex align-items-center gap-2" for="phone">
                                <i class="bi bi-telephone-fill"></i> Phone no. <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone', $user->phone) }}"maxlength="10">
                            @error('phone')
                                <div class="staff-validation">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- WhatsApp -->
                        <div class="col-md-6">
                            <label class="form-label d-flex align-items-center gap-2" for="whatsapp">
                                <i class="bi bi-whatsapp"></i> WhatsApp no.
                            </label>
                            <input type="text" id="whatsapp" name="whatsapp" class="form-control @error('whatsapp') is-invalid @enderror" value="{{ old('whatsapp', $user->whatsapp) }}"maxlength="10">
                            @error('whatsapp')
                                <div class="staff-validation">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Image -->
                        <div class="col-md-12">
                            <label class="form-label d-flex align-items-center gap-2" for="image">
                                <i class="bi bi-person-circle"></i> Profile Image
                            </label>
                            <input type="file" id="image" name="image" class="form-control @error('image') is-invalid @enderror" accept=".avif,.webp,.jpg,.jpeg,.png,.gif,.bmp,.svg">
                            @error('image')
                                <div class="staff-validation">{{ $message }}</div>
                            @enderror

                            <!-- Current Image Preview -->
                            <div class="staff-avatar-preview mt-2" id="editImagePreviewWrap" style="display: inline-flex;">
                                @php
                                    $hasValidAvatar = $user->avatar_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->avatar_path);
                                @endphp

                                @if($hasValidAvatar)
                                    <img src="{{ route('users.image', $user) . '?v=' . optional($user->updated_at)->timestamp }}" alt="{{ $user->name }}" id="editImagePreview" class="img-thumbnail" style="max-width: 120px; max-height: 120px;">
                                @else
                                    <div id="editImagePlaceholder" class="d-flex align-items-center justify-content-center bg-light border rounded" style="width: 120px; height: 120px;">
                                        <i class="bi bi-person-circle text-secondary" style="font-size: 3rem;"></i>
                                    </div>
                                    <img src="" id="editImagePreview" class="img-thumbnail d-none" style="max-width: 120px; max-height: 120px;">
                                @endif
                            </div>
                        </div>

                        <!-- Address -->
                        <div class="col-12">
                            <label class="form-label d-flex align-items-center gap-2" for="address">
                                <i class="bi bi-geo-alt-fill"></i> Address <span class="text-danger">*</span>
                            </label>
                            <textarea id="address" name="address" class="form-control @error('address') is-invalid @enderror" rows="3">{{ old('address', $user->address) }}</textarea>
                            @error('address')
                                <div class="staff-validation">{{ $message }}</div>
                            @enderror
                            </div>
                        </div>

                        <!-- Step 1 Actions -->
                        <div class="mt-4 pt-4 border-top d-flex flex-sm-row justify-content-end gap-2 form-actions">
                            <a href="{{ route('users.index') }}" class="btn btn-outline-dark-blue">Cancel</a>
                            <button type="button" class="btn btn-dark-blue" id="btnNextStep">Next <i class="fa-solid fa-angle-right ms-1"></i></button>
                        </div>
                    </div>

                    <div id="step-2" style="display: none;">

                    <!-- Permissions -->
                    {{-- @include('crm.users.partials.permission', [
                        'permissionMatrix' => $permissionMatrix ?? [],
                        'permissionActions' => $permissionActions ?? [],
                        'selectedPermissions' => old('permissions', $userPermissions ?? []),
                    ]) --}}

                    @include('crm.users.partials.whatsapp-bot', [
                        'whatsappBotMode' => $whatsappBotMode ?? 'none',
                        'staffWhatsappConfig' => $staffWhatsappConfig ?? null,
                    ])

                        <!-- Step 2 Actions -->
                        <div class="mt-4 pt-4 border-top d-flex flex-sm-row justify-content-end gap-2 form-actions">
                            <button type="button" class="btn btn-outline-dark-blue" id="btnPrevStep"><i class="fa-solid fa-angle-left me-1"></i> Back</button>
                            <button type="submit" class="btn btn-dark-blue">Update</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'js/users.js') }}?v={{ filemtime(public_path('js/users.js')) }}"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('staffEditForm');
        const passwordInput = document.getElementById('password');
        const passwordToggle = document.getElementById('toggleEditPassword');
        const imageInput = document.getElementById('image');
        const imagePreviewWrap = document.getElementById('editImagePreviewWrap');
        const imagePreview = document.getElementById('editImagePreview');

        // Clear password field on load
        if (passwordInput) passwordInput.value = '';

        // Real-time validation
        function validateField(field) {
            if (!field) return;

            const value = field.value.trim();
            let isValid = true;

            switch (field.name) {
                case 'name':
                case 'address':
                    isValid = value.length > 0;
                    break;
                case 'password':
                    isValid = value === '' || value.length >= 8;
                    break;
                case 'phone':
                    isValid = /^\d{10}$/.test(value);
                    break;
                case 'whatsapp':
                    isValid = value === '' || /^\d{10}$/.test(value);
                    break;
            }

            field.classList.toggle('is-invalid', !isValid);

            const errorDiv = field.parentElement.querySelector('.staff-validation');
            if (errorDiv) {
                errorDiv.style.display = isValid ? 'none' : 'block';
            }
        }

        // Password Toggle
        if (passwordToggle && passwordInput) {
            passwordToggle.addEventListener('click', function () {
                const isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                
                this.innerHTML = isPassword 
                    ? '<i class="bi bi-eye-fill"></i>' 
                    : '<i class="bi bi-eye-slash-fill"></i>';
            });
        }

        // Image Preview (New upload overrides current image or placeholder)
        if (imageInput && imagePreviewWrap && imagePreview) {
            imageInput.addEventListener('change', function () {
                const file = this.files[0];

                if (file) {
                    imagePreview.src = URL.createObjectURL(file);
                    imagePreview.classList.remove('d-none');
                    
                    const placeholder = document.getElementById('editImagePlaceholder');
                    if (placeholder) {
                        placeholder.classList.add('d-none');
                    }
                    
                    imagePreviewWrap.style.display = 'inline-flex';
                } 
            });
        }

        // Attach validation listeners
        if (form) {
            form.querySelectorAll('input, textarea').forEach(field => {
                field.addEventListener('input', () => validateField(field));
                field.addEventListener('blur', () => validateField(field));
            });
        }

        // Step navigation logic
        const btnNextStep = document.getElementById('btnNextStep');
        const btnPrevStep = document.getElementById('btnPrevStep');
        const step1 = document.getElementById('step-1');
        const step2 = document.getElementById('step-2');

        if (btnNextStep && btnPrevStep) {
            btnNextStep.addEventListener('click', function() {
                // Validate required fields in Step 1
                let isValid = true;
                const requiredFields = ['name', 'email', 'phone', 'address']; // Password optional on edit
                
                requiredFields.forEach(fieldName => {
                    const field = document.getElementById(fieldName);
                    if (field) {
                        validateField(field);
                        if (field.classList.contains('is-invalid')) {
                            isValid = false;
                        }
                    }
                });

                if (isValid) {
                    step1.style.display = 'none';
                    step2.style.display = 'block';
                } else {
                    // Scroll to first error
                    const firstError = document.querySelector('.is-invalid');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            });

            btnPrevStep.addEventListener('click', function() {
                step2.style.display = 'none';
                step1.style.display = 'block';
            });
        }
    });
    </script>
@endpush