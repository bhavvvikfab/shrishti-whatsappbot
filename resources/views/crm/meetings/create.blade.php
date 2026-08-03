@extends('layouts.app')

@section('page_title', 'Add Meeting')

@section('content')
    <div class="container-fluid p-0">
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden meeting-form-card">
            <div class="card-header bg-white border-bottom py-3 px-3 px-md-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <h1 class="h4 mb-1 fw-semibold">Add Meeting</h1>
                        <p class="text-muted small mb-0">Schedule a new meeting with a customer.</p>
                    </div>
                    <a href="{{ route('meetings.index') }}" class="btn btn-dark-blue meeting-back-btn">
                        <i class="fa-solid fa-angle-left pe-1"></i>
                        <span>Back</span>
                    </a>
                </div>
            </div>
            <div class="card-body p-3 p-md-4">

                <form method="POST" action="/api/meetings" id="meetingForm" class="needs-validation" novalidate>
                    @csrf

                    <div class="row g-4">
                        <!-- Customer -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" id="customer_id" class="form-select"
                                data-search-url="{{ route('api.customers.search') }}" data-search-type="customer"
                                data-search-placeholder="-- Search Customer --" required>
                                <option value="">-- Search Customer --</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}" data-email="{{ $customer->email }}"
                                        data-phone="{{ $customer->phone }}">
                                        {{ $customer->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback" id="customer_id-error"></div>
                        </div>

                        <!-- Assigned To -->
                        <div class="col-md-6">
                            @include('crm.partials.assigned-user-field', ['users' => $users])
                        </div>

                        <!-- Meeting Title -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Meeting Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="title" class="form-control" required>
                            <div class="invalid-feedback" id="title-error"></div>
                        </div>

                        <!-- Scheduled On -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Scheduled On <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="scheduled_at" id="scheduled_at" class="form-control"
                                value="{{ old('scheduled_at') }}" min="{{ now()->timezone('Asia/Kolkata')->format('Y-m-d\TH:i') }}" required>
                            <div class="invalid-feedback" id="scheduled_at-error"></div>
                        </div>

                        <!-- Meeting Type -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Meeting Type <span class="text-danger">*</span></label>
                            <select name="meeting_type" id="meeting_type" class="form-select" required>
                                <option value="">Select Meeting Type</option>
                                <option value="online">Online</option>
                                <option value="offline">Offline</option>
                                <option value="phone">Phone Call</option>
                                <option value="video">Video Conference</option>
                            </select>
                            <div class="invalid-feedback" id="meeting_type-error"></div>
                        </div>

                        <!-- Status -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="scheduled" selected>Scheduled</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <div class="invalid-feedback" id="status-error"></div>
                        </div>

                        <!-- Agenda -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Agenda <span class="text-danger">*</span></label>
                            <textarea name="agenda" id="agenda" rows="4" class="form-control"
                                placeholder="Enter meeting agenda..." required></textarea>
                            <div class="invalid-feedback" id="agenda-error"></div>
                        </div>

                        <!-- Address -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Address</label>
                            <input type="text" name="address" id="address" class="form-control"
                                placeholder="Enter meeting address/location">
                            <div class="invalid-feedback" id="address-error"></div>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-top d-flex flex-sm-row justify-content-end gap-2 form-actions">
                        <a href="{{ route('meetings.index') }}" class="btn btn-outline-dark-blue">Cancel</a>
                        <button type="submit" id="submitBtn" class="btn btn-dark-blue">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script src="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'js/meeting.js') }}"></script>
@endpush
