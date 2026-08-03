@extends('layouts.masters')

@section('page_title', 'Masters - Edit Customer')

@section('masters_content')
    {{-- Header Section --}}
    <div class="p-4 border-bottom bg-light bg-opacity-50">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 w-100">
            <div class="flex-grow-1 w-100">
                <h1 class="h4 mb-1 fw-bold">Edit Customer</h1>
                <p class="text-muted small mb-0">Update customer profile and settings in the master database.</p>
            </div>
            <div class="d-flex flex-wrap gap-2 w-100 w-md-auto justify-content-lg-end justify-content-md-end">
                <a href="{{ route('masters.customers.show', $customer->id) }}" class="btn btn-outline-dark-blue flex-grow-1 flex-md-grow-0">
                    <i class="bi bi-eye me-1"></i>View
                </a>
                <a href="{{ route('masters.customers.index') }}" class="btn btn-dark-blue flex-grow-1 flex-md-grow-0">
                    <i class="fa-solid fa-angle-left pe-2"></i>Back
                </a>
            </div>
        </div>
    </div>
    <div class="card-body p-3 p-md-4">
                <form id="customerForm" data-id="{{ $customer->id }}" enctype="multipart/form-data" novalidate>
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <div class="d-flex flex-wrap gap-2" id="customerFormSteps">
                            <button type="button" class="btn btn-outline-dark-blue active flex-grow-1 flex-md-grow-0"
                                data-step="1">Personal Details</button>
                            <button type="button" class="btn btn-outline-dark-blue flex-grow-1 flex-md-grow-0"
                                data-step="2">Address Details</button>
                            <button type="button" class="btn btn-outline-dark-blue flex-grow-1 flex-md-grow-0"
                                data-step="3">Other Details</button>
                        </div>
                    </div>

                    <div class="customer-form-step" data-step="1">
                        <div class="border-bottom mb-4 pb-3">
                            <h5 class="mb-1">Personal Details</h5>
                            <p class="text-muted small mb-0">Enter the customer name, contact and basic profile information.
                            </p>
                        </div>
                        <div class="row g-4 mb-4">
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control"
                                    value="{{ old('name', $customer->name) }}" placeholder="Customer Full Name">
                            </div>
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-semibold">Email</label>
                                <input type="email" name="email" class="form-control"
                                    value="{{ old('email', $customer->email) }}" placeholder="email@example.com">
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-semibold">Phone no. <span class="text-danger">*</span></label>
                                <input type="text" name="phone" class="form-control"
                                    value="{{ old('phone', $customer->phone) }}" placeholder="+1 234 567 890">
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-semibold">WhatsApp no.</label>
                                <input type="text" name="whatsapp" class="form-control"
                                    value="{{ old('whatsapp', $customer->whatsapp) }}" placeholder="WhatsApp Number">
                            </div>
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-semibold">Image</label>
                                <input type="file" name="image" class="form-control">
                                @if($customer->image)
                                    <div class="mt-1 small text-muted">Current: {{ basename($customer->image) }}</div>
                                @endif
                            </div>
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-semibold">Company Name</label>
                                <input type="text" name="company_name" class="form-control"
                                    value="{{ old('company_name', $customer->company_name) }}"
                                    placeholder="Business / Company Name">
                            </div>
                        </div>
                    </div>

                    <div class="customer-form-step d-none" data-step="2">
                        <div class="border-bottom mb-4 pb-3">
                            <h5 class="mb-1">Address Details</h5>
                            <p class="text-muted small mb-0">Provide the customer address and location information.</p>
                        </div>
                        <div class="row g-4 mb-4">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Address <span class="text-danger">*</span></label>
                                <textarea name="address" class="form-control" rows="2"
                                    placeholder="Full residential or office address">{{ old('address', $customer->address) }}</textarea>
                            </div>
                            <div class="col-md-4 col-12">
                                <label class="form-label fw-semibold">Country</label>
                                <select name="country_id" id="country_id" class="form-select">
                                    <option value="">-- Search Country --</option>
                                    @foreach($countries as $country)
                                        <option value="{{ $country->id }}" @selected(old('country_id', $customer->country_id) == $country->id)>{{ $country->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 col-12">
                                <label class="form-label fw-semibold">City</label>
                                <select name="city_id" id="city_id" class="form-select">
                                    <option value="">-- Search City --</option>
                                    @foreach($cities as $city)
                                        <option value="{{ $city->id }}" @selected(old('city_id', $customer->city_id) == $city->id)>{{ $city->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 col-12">
                                <label class="form-label fw-semibold">Type</label>
                                <select name="type" class="form-select">
                                    @foreach(['Individual', 'Corporate', 'Government', 'NGO'] as $type)
                                        <option value="{{ $type }}" {{ old('type', $customer->type) == $type ? 'selected' : '' }}>
                                            {{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="customer-form-step d-none" data-step="3">
                        <div class="border-bottom mb-4 pb-3">
                            <h5 class="mb-1">Contact Details</h5>
                            <p class="text-muted small mb-0">Complete the remaining customer settings and profile details.
                            </p>
                        </div>
                        <div class="row g-4 mb-4">
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-semibold">Website</label>
                                <input type="text" name="website" class="form-control"
                                    value="{{ old('website', $customer->website) }}" placeholder="https://example.com">
                            </div>
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-semibold">Date of Birth</label>
                                <input type="date" name="dob" class="form-control" value="{{ old('dob', $customer->dob) }}">
                            </div>
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-semibold">Anniversary Date</label>
                                <input type="date" name="anniversary_date" class="form-control"
                                    value="{{ old('anniversary_date', $customer->anniversary_date) }}">
                            </div>
                            <div class="col-md-6 col-12">
                                <label class="form-label fw-semibold">Tax Number</label>
                                <input type="text" name="tax_number" class="form-control"
                                    value="{{ old('tax_number', $customer->tax_number) }}" placeholder="GST / VAT / Tax ID">
                            </div>
                        </div>

                        @include('partials.custom_fields', ['model' => $customer])
                    </div>

                    <div class="mt-4 pt-4 border-top d-flex justify-content-between align-items-center gap-2 customer-form-actions">
                        <a href="{{ route('masters.customers.index') }}" class="btn btn-outline-dark-blue cancel-step">Cancel</a>
                        <button type="button" class="btn btn-outline-dark-blue prev-step d-none">Previous</button>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-dark-blue next-step">Next</button>
                            <button type="submit" class="btn btn-dark-blue d-none">Update</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection


    @push('styles')
        <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    @endpush

    @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.5/jquery.validate.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
        <script src="{{ url((env('PUBLIC_PATH') ? rtrim(env('PUBLIC_PATH'), '/') . '/' : '') . 'js/customer.js') }}?v={{ filemtime(public_path('js/customer.js')) }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const countrySelect = new TomSelect('#country_id', {
                    placeholder: '-- Search Country --',
                    allowEmptyOption: true,
                    dropdownParent: 'body'
                });
                const citySelect = new TomSelect('#city_id', {
                    placeholder: '-- Search City --',
                    allowEmptyOption: true,
                    dropdownParent: 'body'
                });

                document.getElementById('country_id').addEventListener('change', function () {
                    const countryId = this.value;

                    citySelect.clear();
                    citySelect.clearOptions();
                    citySelect.addOption({ value: 'loading', text: 'Loading...' });
                    citySelect.setValue('loading');

                    if (!countryId) {
                        citySelect.clear();
                        citySelect.clearOptions();
                        citySelect.addOption({ value: '', text: '-- Search City --' });
                        return;
                    }

                    fetch(`/masters/cities-by-country/${countryId}`)
                        .then(response => response.json())
                        .then(data => {
                            citySelect.clear();
                            citySelect.clearOptions();
                            citySelect.addOption({ value: '', text: '-- Search City --' });
                            data.forEach(city => {
                                citySelect.addOption({ value: city.id, text: city.name });
                            });
                        })
                        .catch(error => {
                            console.error('Error fetching cities:', error);
                            citySelect.clear();
                            citySelect.clearOptions();
                            citySelect.addOption({ value: '', text: 'Error loading cities' });
                        });
                });
            });
        </script>
    @endpush