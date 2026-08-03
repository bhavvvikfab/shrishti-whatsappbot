@extends('layouts.app')

@section('page_title', 'Add Staff')

@push('styles')
    <link rel="stylesheet" href="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'css/users-form.css') }}?v={{ filemtime(public_path('css/users-form.css')) }}">
@endpush

@section('content')
    <div class="container-fluid p-0">
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden staff-form-card">
            
            <!-- Card Header -->
            <div class="card-header bg-white border-bottom py-3 px-3 px-md-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <h1 class="h4 mb-1 fw-semibold">Add Staff</h1>
                        <p class="text-muted small mb-0">Create a new staff user and assign module access.</p>
                    </div>
                    <a href="{{ route('users.index') }}" class="btn btn-dark-blue staff-back-btn">
                        <i class="fa-solid fa-angle-left pe-1"></i>
                        <span>Back</span>
                    </a>
                </div>
            </div>

            <!-- Card Body -->
            <div class="card-body p-3 p-md-4">
                <form action="{{ url('/api/users') }}" method="POST" enctype="multipart/form-data" id="staffCreateForm" class="ajax-user-form">

                    @csrf

                    <div class="row g-3">
                        <!-- Name -->
                        <div class="col-12">
                            <label class="form-label d-flex align-items-center gap-2" for="name">
                                <i class="bi bi-person-fill"></i> Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}">
                            @error('name')
                                <div class="staff-validation">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Email -->
                        <div class="col-md-6">
                            <label class="form-label d-flex align-items-center gap-2" for="email">
                                <i class="bi bi-envelope-fill"></i> Email <span class="text-danger">*</span>
                            </label>
                            <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" autocomplete="off">
                            @error('email')
                                <div class="staff-validation">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Password -->
                        <div class="col-md-6">
                            <label class="form-label d-flex align-items-center gap-2" for="password">
                                <i class="bi bi-key-fill"></i> Password <span class="text-danger">*</span>
                            </label>
                            <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="off">
                            @error('password')
                                <div class="staff-validation">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Phone -->
                        <div class="col-md-6">
                            <label class="form-label d-flex align-items-center gap-2" for="phone">
                                <i class="bi bi-telephone-fill"></i> Phone no. <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}"maxlength="10">
                            @error('phone')
                                <div class="staff-validation">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- WhatsApp -->
                        <div class="col-md-6">
                            <label class="form-label d-flex align-items-center gap-2" for="whatsapp">
                                <i class="bi bi-whatsapp"></i> WhatsApp no.
                            </label>
                            <input type="text" id="whatsapp" name="whatsapp" class="form-control @error('whatsapp') is-invalid @enderror" value="{{ old('whatsapp') }}"maxlength="10">
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
                            
                            <div class="staff-avatar-preview mt-2" id="createImagePreviewWrap" style="display: none;">
                                <img src="" alt="Staff preview" id="createImagePreview" class="img-thumbnail" style="max-width: 120px; max-height: 120px;">
                            </div>
                        </div>

                        <!-- Address -->
                        <div class="col-12">
                            <label class="form-label d-flex align-items-center gap-2" for="address">
                                <i class="bi bi-geo-alt-fill"></i> Address <span class="text-danger">*</span>
                            </label>
                            <textarea id="address" name="address" class="form-control @error('address') is-invalid @enderror" rows="3">{{ old('address') }}</textarea>
                            @error('address')
                                <div class="staff-validation">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="mt-4 pt-4 border-top d-flex flex-sm-row justify-content-end gap-2 form-actions">
                        <a href="{{ route('users.index') }}" class="btn btn-outline-dark-blue">Cancel</a>
                        <button type="submit" class="btn btn-dark-blue">Submit</button>
                    </div>

                    <!-- Permissions Section -->
                    @include('crm.users.partials.permission', [
                        'permissionMatrix' => $permissionMatrix ?? [],
                        'permissionActions' => $permissionActions ?? [],
                        'selectedPermissions' => old('permissions', []),
                    ])
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'js/users.js') }}?v={{ filemtime(public_path('js/users.js')) }}"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('staffCreateForm');
        const imageInput = document.getElementById('image');
        const imagePreviewWrap = document.getElementById('createImagePreviewWrap');
        const imagePreview = document.getElementById('createImagePreview');

        // Clear email and password fields on page load to ensure they're empty
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        if (emailInput) emailInput.value = '';
        if (passwordInput) passwordInput.value = '';

        // Real-time field validation
        function validateField(field) {
            if (!field) return;

            const value = field.value.trim();
            let isValid = true;

            switch (field.name) {
                case 'name':
                case 'email':
                case 'address':
                    isValid = value.length > 0;
                    break;
                case 'password':
                    isValid = value.length >= 8;
                    break;
                case 'phone':
                    isValid = /^\d{10}$/.test(value);
                    break;
                case 'whatsapp':
                    isValid = value === '' || /^\d{10}$/.test(value);
                    break;
            }

            field.classList.toggle('is-invalid', !isValid);

            // Hide/show custom error message
            const errorDiv = field.parentElement.querySelector('.staff-validation');
            if (errorDiv) {
                errorDiv.style.display = isValid ? 'none' : 'block';
            }
        }

        // Image Preview
        if (imageInput && imagePreview && imagePreviewWrap) {
            imageInput.addEventListener('change', function () {
                const file = this.files[0];
                
                if (file) {
                    imagePreview.src = URL.createObjectURL(file);
                    imagePreviewWrap.style.display = 'block';
                } else {
                    imagePreview.src = '';
                    imagePreviewWrap.style.display = 'none';
                }
            });
        }

        // Attach validation to all inputs and textarea
        if (form) {
            const fields = form.querySelectorAll('input, textarea');
            
            fields.forEach(field => {
                field.addEventListener('input', () => validateField(field));
                field.addEventListener('blur', () => validateField(field));
            });
        }
    });
    </script>
@endpush