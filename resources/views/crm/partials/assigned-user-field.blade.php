@php
    $fieldName = $name ?? 'assigned_user_id';
    $fieldId = $id ?? $fieldName;
    $labelText = $label ?? 'Assigned To';
    $searchPlaceholder = $searchPlaceholder ?? '-- Search User --';
    $readonlyUser = auth()->user();
    $selectedValue = old($fieldName, $selected ?? $readonlyUser?->id);
@endphp

<label class="form-label fw-semibold">{{ $labelText }}</label>
@if($readonlyUser?->isAdmin())
    <select
        name="{{ $fieldName }}"
        id="{{ $fieldId }}"
        class="form-select @error($fieldName) is-invalid @enderror"
        data-search-url="{{ route('api.users.search') }}"
        data-search-type="user"
        data-search-placeholder="{{ $searchPlaceholder }}"
    >
        <option value="">{{ $searchPlaceholder }}</option>
        @foreach($users as $user)
            <option value="{{ $user->id }}" data-email="{{ $user->email }}" @selected((string) $selectedValue === (string) $user->id)>
                {{ $user->name }}
            </option>
        @endforeach
    </select>
@else
    <input type="hidden" name="{{ $fieldName }}" value="{{ $selectedValue }}">
    <input type="text" class="form-control" value="{{ $readonlyUser?->name }}" readonly>
@endif
<div class="invalid-feedback" id="{{ $fieldId }}-error">@error($fieldName) {{ $message }} @enderror</div>
