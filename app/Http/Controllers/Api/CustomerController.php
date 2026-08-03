<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    /**
     * Search customers for dropdown/autocomplete.
     */
    public function search(Request $request)
    {
        $query = $this->scopeOwnedRecords(Customer::query()->whereNull('deleted_at'));
        
        $search = $request->get('q', $request->get('search', ''));
        
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }
        
        $customers = $query->limit(20)->get(['id', 'name', 'email', 'phone', 'company_name']);
        
        return response()->json([
            'success' => true,
            'data' => $customers->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'text' => $customer->name,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'phone' => $customer->phone,
                    'company_name' => $customer->company_name,
                ];
            }),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Customer::class);

        $authUser = auth()->user();
        $query = Customer::with(['country', 'city']);

        // Explicit Visibility Logic:
        // Admin: Show all
        // Staff: Show only records where user_id = their ID (data they created)
        if (!$authUser->isAdmin()) {
            $query->where('created_by', $authUser->id);
        }

        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        $customers = $query->latest()->paginate(10);
        $authUser = auth()->user();
        $authId = $authUser?->id;

        $customers->getCollection()->transform(function (Customer $customer) use ($authUser, $authId) {
            // Check if staff owns this customer directly
            $isOwned = $customer->isOwnedBy($authUser);

            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'company_name' => $customer->company_name,
                'created_at' => optional($customer->created_at)->toIso8601String(),
                'can_view' => true,
                'can_update' => $isOwned,  // Only owned customers can be edited
                'can_delete' => $isOwned,  // Only owned customers can be deleted
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Customers retrieved successfully.',
            'data' => $customers
        ]);
    }

    public function show(Customer $customer)
    {
        $this->authorize('view', $customer);
        $customer->load(['country', 'city']);

        $authUser = auth()->user();

        return response()->json([
            'success' => true,
            'data' => $customer,
            'can_update' => $customer->isOwnedBy($authUser),
            'can_delete' => $customer->isOwnedBy($authUser),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Customer::class);

        try {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255', Rule::unique('customers', 'email')->whereNull('deleted_at')],
                'phone' => ['required', 'string', 'min:10', 'max:10', Rule::unique('customers', 'phone')->whereNull('deleted_at')],
                'whatsapp' => ['nullable', 'string', 'max:50'],
                'address' => ['required', 'string'],
                'dob' => ['nullable', 'date'],
                'anniversary_date' => ['nullable', 'date'],
                'company_name' => ['nullable', 'string', 'max:255'],
                'website' => ['nullable', 'string', 'max:255'],
                'tax_number' => ['nullable', 'string', 'max:100'],
                'type' => ['nullable', 'string', 'max:100'],
                'country_id' => ['nullable', 'exists:countries,id'],
                'city_id' => ['nullable', 'exists:cities,id'],
                'image' => ['nullable', 'image', 'max:2048'],
            ]);

            if ($request->hasFile('image')) {
                $data['image'] = $request->file('image')->store('customers', 'public');
            }

            $data['is_active'] = $request->boolean('is_active', true);

            if (Schema::hasColumn('customers', 'created_by')) {
                $data['created_by'] = auth()->id();
            }

            if (Schema::hasColumn('customers', 'updated_by')) {
                $data['updated_by'] = auth()->id();
            }

            if (Schema::hasColumn('customers', 'user_id')) {
                $data['user_id'] = auth()->id();
            }

            $customer = Customer::create($data);
            if ($request->has('custom_fields')) {
                $customer->saveCustomFields($request->get('custom_fields'));
            }

            // WhatsApp welcome message
            try {
                $phone = $customer->whatsapp ?: $customer->phone;
                if ($phone) {
                    app(\App\Services\WhatsAppService::class)->sendForModule(
                        'customer_welcome_message',
                        $phone,
                        [
                            $customer->name ?? 'Customer',
                            $customer->company_name ?: 'N/A',
                            $customer->phone ?: 'N/A',
                            $customer->address ?: 'N/A',
                        ],
                        $customer->id
                    );
                }
            } catch (\Throwable $e) {
                Log::error('Customer create WhatsApp block failed', [
                    'customer_id' => $customer->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully.',
                'redirect' => '/masters/customers',
                'data' => $customer
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating customer: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorize('update', $customer);

        try {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customer->id)->whereNull('deleted_at')],
                'phone' => ['required', 'string', 'min:10', 'max:10', Rule::unique('customers', 'phone')->ignore($customer->id)->whereNull('deleted_at')],
                'whatsapp' => ['nullable', 'string', 'max:50'],
                'address' => ['required', 'string'],
                'dob' => ['nullable', 'date'],
                'anniversary_date' => ['nullable', 'date'],
                'company_name' => ['nullable', 'string', 'max:255'],
                'website' => ['nullable', 'string', 'max:255'],
                'tax_number' => ['nullable', 'string', 'max:100'],
                'type' => ['nullable', 'string', 'max:100'],
                'country_id' => ['nullable', 'exists:countries,id'],
                'city_id' => ['nullable', 'exists:cities,id'],
                'image' => ['nullable', 'image', 'max:2048'],
            ]);

            if ($request->hasFile('image')) {
                if ($customer->image) {
                    Storage::disk('public')->delete($customer->image);
                }
                $data['image'] = $request->file('image')->store('customers', 'public');
            }

            if ($request->has('is_active')) {
                $data['is_active'] = $request->boolean('is_active');
            }

            if (Schema::hasColumn('customers', 'updated_by')) {
                $data['updated_by'] = auth()->id();
            }

            $customer->update($data);
            if ($request->has('custom_fields')) {
                $customer->saveCustomFields($request->get('custom_fields'));
            }

            try {
                $phone = $customer->whatsapp ?: $customer->phone;
                if ($phone) {
                    app(\App\Services\WhatsAppService::class)->sendForModule(
                        'customer_profile_updated',
                        $phone,
                        [
                            $customer->name ?? 'Customer',
                            $customer->company_name ?: 'N/A',
                            $customer->phone ?: 'N/A',
                            $customer->address ?: 'N/A',
                        ],
                        $customer->id
                    );
                }
            } catch (\Throwable $e) {
                Log::error('Customer update WhatsApp block failed', [
                    'customer_id' => $customer->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Customer updated successfully.',
                'redirect' => '/masters/customers',
                'data' => $customer
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating customer: ' . $e->getMessage()
            ], 500);
        }
    }


    public function destroy(Customer $customer)
    {
        $this->authorize('delete', $customer);
        try {
            if ($customer->image) {
                Storage::disk('public')->delete($customer->image);
            }
            $customer->delete();

            return response()->json([
                'success' => true,
                'message' => 'Customer deleted successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting customer: ' . $e->getMessage()
            ], 500);
        }
    }

    public function import(Request $request)
    {
        $this->authorize('create', Customer::class);

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'import_file' => ['required', 'file', 'mimes:csv,txt'],
        ], [
            'import_file.required' => 'Please select an import file.',
            'import_file.file' => 'Please upload a valid import file.',
            'import_file.mimes' => 'Please upload a CSV file.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('import_file');
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to read the import file.',
            ], 422);
        }

        $headers = fgetcsv($handle);

        if (!$headers) {
            fclose($handle);
            return response()->json([
                'success' => false,
                'message' => 'Import file is empty.',
            ], 422);
        }

        $normalizedHeaders = array_map(function ($header) {
            return \Illuminate\Support\Str::of((string) $header)->lower()->replace([' ', '-', '.'], '_')->toString();
        }, $headers);

        $rowNumber = 1;
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $rowData = $this->mapImportRow($normalizedHeaders, $row);
            $name = trim((string) ($rowData['name'] ?? ''));
            $email = trim((string) ($rowData['email'] ?? ''));
            $phone = trim((string) ($rowData['phone'] ?? ''));

            if ($name === '' || ($email === '' && $phone === '')) {
                $skipped++;
                $errors[] = "Row {$rowNumber}: Name and either Email or Phone are required.";
                continue;
            }

            // Basic email validation if provided
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                $errors[] = "Row {$rowNumber}: Invalid email format.";
                continue;
            }

            // Find existing customer by email or phone
            $customer = null;
            if ($email !== '') {
                $customer = Customer::where('email', $email)->first();
            }
            if (!$customer && $phone !== '') {
                $customer = Customer::where('phone', $phone)->first();
            }

            $payload = [
                'name' => $name,
                'email' => $email ?: ($customer ? $customer->email : null),
                'phone' => $phone ?: ($customer ? $customer->phone : null),
                'whatsapp' => trim((string) ($rowData['whatsapp'] ?? ($customer ? $customer->whatsapp : ''))),
                'company_name' => trim((string) ($rowData['company'] ?? ($rowData['company_name'] ?? ($customer ? $customer->company_name : '')))),
                'address' => trim((string) ($rowData['address'] ?? ($customer ? $customer->address : ''))),
                'type' => trim((string) ($rowData['type'] ?? ($customer ? $customer->type : ''))),
                'is_active' => true,
            ];

            if ($customer) {
                $customer->update($payload);
                $updated++;
            } else {
                $payload['created_by'] = auth()->id();
                $payload['user_id'] = auth()->id();
                Customer::create($payload);
                $imported++;
            }
        }

        fclose($handle);

        $message = "{$imported} customer(s) imported successfully.";
        if ($updated > 0) {
            $message .= " {$updated} existing customer(s) updated.";
        }
        if ($skipped > 0) {
            $message .= " {$skipped} row(s) skipped.";
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'summary' => [
                'imported' => $imported,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errors,
            ],
            'redirect' => '/masters/customers',
        ]);
    }

    private function mapImportRow(array $headers, array $row): array
    {
        $mapped = [];
        foreach ($headers as $index => $header) {
            $value = $row[$index] ?? null;
            $mapped[$header] = is_string($value) ? trim($value) : $value;
        }
        return $mapped;
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }
}
