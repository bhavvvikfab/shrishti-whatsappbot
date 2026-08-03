@extends('layouts.app')

@section('page_title', 'Follow Ups - Create')

@section('content')
    <div class="container-fluid p-0">

        <div class="card shadow-sm border-0 followup-form-card">
            <div class="card-header bg-white border-bottom py-3 px-3 px-md-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                    <div>
                        <h1 class="h4 mb-1 fw-semibold">Schedule Follow Up</h1>
                        <p class="text-muted small mb-0">Create a new follow up record for a lead or customer.</p>
                    </div>
                    <a href="{{ route('followups.index') }}" class="btn btn-dark-blue">
                        <i class="fa-solid fa-angle-left"></i>
                        <span>Back</span>
                    </a>
                </div>
            </div>

            <div class="card-body p-3 p-md-4">
                <form method="POST" action="/api/follow-ups"
                    class="needs-validation ajax-followup-form followup-create-form" novalidate>
                    @csrf

                    <div class="row g-3 g-md-4">

                        <!-- Lead Name -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Lead Name <span class="text-danger">*</span></label>
                            <select name="lead_id" id="lead_id" class="form-select select2" required>
                                <option value="">Select Lead</option>
                                @foreach ($leads as $lead)
                                    <option value="{{ $lead->id }}" @selected(old('lead_id') == $lead->id)>
                                        {{ $lead->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback d-block" id="lead_id-error"></div>
                        </div>

                        <!-- Assigned To -->
                        <div class="col-12 col-md-6">
                            @include('crm.partials.assigned-user-field', ['users' => $users, 'searchPlaceholder' => 'Select Staff'])
                        </div>

                        <!-- Purpose -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Purpose <span class="text-danger">*</span></label>
                            <input name="purpose" id="purpose" value="{{ old('purpose') }}" class="form-control"
                                placeholder="Enter purpose of follow up" required>
                            <div class="invalid-feedback d-block" id="purpose-error"></div>
                        </div>

                        <!-- Comment -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Comment</label>
                            <textarea name="comment" id="comment" rows="3" class="form-control" placeholder="Add any additional notes...">{{ old('comment') }}</textarea>
                        </div>

                        <!-- Priority -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Priority <span class="text-danger">*</span></label>
                            <select name="priority" id="priority" class="form-select" required>
                                <option value="" @selected(old('priority') === null)>Select Priority</option>
                                <option value="low" @selected(old('priority') == 'low')>Low</option>
                                <option value="medium" @selected(old('priority') == 'medium')>Medium</option>
                                <option value="high" @selected(old('priority') == 'high')>High</option>
                            </select>
                            <div class="invalid-feedback d-block" id="priority-error"></div>
                        </div>

                        <!-- Status -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Status <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="" @selected(old('status') === null)>Select Status</option>
                                <option value="pending" @selected(old('status') == 'pending')>Pending</option>
                                <option value="resheduled" @selected(old('status') == 'resheduled')>Rescheduled</option>
                                <option value="completed" @selected(old('status') == 'completed')>Completed</option>
                                <option value="cancelled" @selected(old('status') == 'cancelled')>Cancelled</option>
                            </select>
                            <div class="invalid-feedback d-block" id="status-error"></div>
                        </div>

                        <!-- Follow Up Date -->
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-medium">Follow Up Date & Time <span
                                    class="text-danger">*</span></label>
                            <input type="datetime-local" name="follow_up_at" id="follow_up_at"
                                value="{{ old('follow_up_at') }}" min="{{ now()->timezone('Asia/Kolkata')->format('Y-m-d\TH:i') }}" class="form-control" required>
                            <div class="invalid-feedback d-block" id="follow_up_at-error"></div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="mt-4 pt-4 border-top d-flex flex-sm-row justify-content-end gap-2 followup-form-actions">
                        <a href="{{ route('followups.index') }}" class="btn btn-outline-dark-blue">Cancel</a>
                        <button type="submit" class="btn btn-dark-blue">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap-5-theme@1.5.2/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <style>

        .followup-create-form .select2-container,
        .followup-create-form .ts-wrapper {
            width: 100% !important;
        }

        @media (max-width: 767.98px) {
            .followup-form-card {
                border-radius: 18px;
                overflow: hidden;
            }

            .followup-create-form .row {
                --bs-gutter-x: 0.85rem;
                --bs-gutter-y: 1rem;
            }

            .followup-form-actions .btn {
                width: 100%;
            }

            .followup-form-actions {
                padding-top: 1rem !important;
                margin-top: 1.5rem !important;
            }
        }

        @media (max-width: 575.98px) {

            .followup-form-card .card-header,
            .followup-form-card .card-body {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'js/followup.js') }}"></script>
@endpush
