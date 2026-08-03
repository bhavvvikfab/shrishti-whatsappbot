@extends('layouts.app')

@section('page_title', 'Tasks - Edit')

@section('content')
    <div class="container-fluid p-0">
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden task-form-card">
            <div class="card-body p-0">
                {{-- Header Section --}}
                <div class="p-4 border-bottom bg-light bg-opacity-50">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 w-100">
                        <div class="flex-grow-1 w-100">
                            <h1 class="h4 mb-1 fw-bold">Edit Task</h1>
                            <p class="text-muted small mb-0">Update task details for the team.</p>
                        </div>
                        <div class="d-flex flex-wrap gap-2 w-100 w-md-auto justify-content-lg-end justify-content-md-end">
                            @can('tasks.view')
                                <a href="{{ route('tasks.show', $task) }}" class="btn btn-outline-dark-blue flex-grow-1 flex-md-grow-0">
                                    <i class="bi bi-eye me-1"></i>View
                                </a>
                            @endcan
                            <a href="{{ route('tasks.index') }}" class="btn btn-dark-blue flex-grow-1 flex-md-grow-0">
                                <i class="fa-solid fa-angle-left pe-2"></i>Back
                            </a>
                        </div>
                    </div>
                </div>

                <div class="p-4">
                <form method="POST" action="/api/tasks/{{ $task->id }}" id="taskForm"
                    class="needs-validation ajax-task-form js-status-comment-form" novalidate>
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Project <span class="text-danger">*</span></label>
                            <select name="project_id" id="project_id"
                                class="form-select @error('project_id') is-invalid @enderror" required>
                                <option value="">Select Project</option>
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}" @selected(old('project_id', $task->project_id) == $project->id)>{{ $project->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback" id="project_id-error">{{ $errors->first('project_id') }}</div>
                        </div>

                        <div class="col-md-6">
                            @include('crm.partials.assigned-user-field', ['users' => $users, 'selected' => $task->assigned_user_id])
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Assigned For <span class="text-danger">*</span></label>
                            <select name="related_id" id="related_id"
                                class="form-select @error('related_id') is-invalid @enderror"
                                data-search-url="{{ route('api.customers.search') }}" data-search-type="customer"
                                data-search-placeholder="-- Search Customer --">
                                <option value="">-- Search Customer --</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}" data-email="{{ $customer->email }}"
                                        data-phone="{{ $customer->phone }}" @selected(old('related_id', $task->related_type === 'customer' ? $task->related_id : null) == $customer->id)>
                                        {{ $customer->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback" id="related_id-error">{{ $errors->first('related_id') }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Task Title <span class="text-danger">*</span></label>
                            <input name="title" id="title" value="{{ old('title', $task->title) }}"
                                class="form-control @error('title') is-invalid @enderror" required>
                            <div class="invalid-feedback" id="title-error">{{ $errors->first('title') }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Description <span class="text-danger">*</span></label>
                            <textarea name="description" id="description" rows="2"
                                class="form-control @error('description') is-invalid @enderror"
                                required>{{ old('description', $task->description) }}</textarea>
                            <div class="invalid-feedback" id="description-error">{{ $errors->first('description') }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Due Date <span class="text-danger">*</span></label>
                            <input type="text" name="due_date" id="due_date"
                                value="{{ old('due_date', optional($task->due_date)->format('Y-m-d')) }}"
                                placeholder="dd/mm/yyyy"
                                data-min-date="today"
                                class="form-control js-date @error('due_date') is-invalid @enderror" required>
                            <div class="invalid-feedback" id="due_date-error">{{ $errors->first('due_date') }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Priority <span class="text-danger">*</span></label>
                            <select name="priority" id="priority"
                                class="form-select @error('priority') is-invalid @enderror" required>
                                <option value="">Select Priority</option>
                                <option value="low" @selected(old('priority', $task->priority) === 'low')>Low</option>
                                <option value="medium" @selected(old('priority', $task->priority) === 'medium')>Medium
                                </option>
                                <option value="high" @selected(old('priority', $task->priority) === 'high')>High</option>
                            </select>
                            <div class="invalid-feedback" id="priority-error">{{ $errors->first('priority') }}</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" id="status"
                                class="form-select @error('status') is-invalid @enderror js-status-comment-trigger"
                                required>
                                <option value="">Select Status</option>
                                <option value="pending" @selected(old('status', $task->status) === 'pending')>Pending</option>
                                <option value="in_progress" @selected(old('status', $task->status) === 'in_progress')>In
                                    Progress</option>
                                <option value="completed" @selected(old('status', $task->status) === 'completed')>Completed
                                </option>
                            </select>
                            <div class="invalid-feedback" id="status-error">{{ $errors->first('status') }}</div>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-top d-flex flex-sm-row justify-content-end gap-2 form-actions">
                        <a href="{{ route('tasks.index') }}" class="btn btn-outline-dark-blue">Cancel</a>
                        <button type="submit" class="btn btn-dark-blue" id="submitBtn">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"
                                id="btnSpinner"></span>
                            <span id="btnText">Update</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @include('crm.partials.status-history-table', ['histories' => $task->statusHistories])
    </div>
    @push('styles')
        <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    @endpush

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
        <script src="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'js/tasks.js') }}"></script>
    @endpush
@endsection
