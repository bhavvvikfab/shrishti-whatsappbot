<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LeadController extends Controller
{
    public function index()
    {
        return view('crm.leads.index');
    }

    // public function create()
    // {
    //     $sources = LeadSource::orderBy('name')->get();
    //     $stages = Stage::orderBy('name')->get();
    //     $users = User::orderBy('name')->get();

    //     return view('crm.leads.create', compact('sources', 'stages', 'users'));
    // }

    public function create()
    {
        $sources = LeadSource::orderBy('name')->get();
        $stages = Stage::orderBy('name')->get();
        $users = $this->selectableUsers();

        return view('crm.leads.create', compact('sources', 'stages', 'users'));
    }

    // public function edit(Lead $lead)
    // {
    //     $lead->load(['statusHistories.updater']);
    //     $sources = LeadSource::orderBy('name')->get();
    //     $stages = Stage::orderBy('name')->get();
    //     $users = User::orderBy('name')->get();

    //     return view('crm.leads.edit', compact('lead', 'sources', 'stages', 'users'));
    // }

    public function edit(Lead $lead)
    {
        $this->authorize('update', $lead);
        $lead->load(['statusHistories.updater']);
        $sources = LeadSource::orderBy('name')->get();
        $stages = Stage::orderBy('name')->get();
        $users = $this->selectableUsers();

        return view('crm.leads.edit', compact('lead', 'sources', 'stages', 'users'));
    }

    public function show(Lead $lead)
    {
        $this->authorize('view', $lead);
        $lead->load(['leadSource', 'stage', 'assignedUser', 'followUps.assignedUser', 'tasks.assignedUser', 'statusHistories.updater']);

        return view('crm.leads.show', compact('lead'));
    }

    public function image(Lead $lead)
    {
        if (!$lead->image || !Storage::disk('public')->exists($lead->image)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($lead->image));
    }

    public function convertToCustomer(Lead $lead)
    {
        if ($lead->is_converted && $lead->converted_customer_id) {
            return redirect()
                ->route('masters.customers.edit', $lead->converted_customer_id)
                ->with('success', 'Lead already converted.');
        }

        $customer = DB::transaction(function () use ($lead) {
            $existing = null;

            if (!empty($lead->email)) {
                $existing = Customer::where('email', $lead->email)->first();
            }

            if (!$existing && !empty($lead->phone)) {
                $existing = Customer::where('phone', $lead->phone)->first();
            }

            if (!$existing) {
                $customerData = [
                    'name' => $lead->name,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'whatsapp' => $lead->whatsapp,
                    'address' => $lead->address,
                    'company_name' => $lead->company_name,
                    'image' => $lead->image,
                    'type' => 'Individual',
                    'is_active' => true,
                ];

                if (\Illuminate\Support\Facades\Schema::hasColumn('customers', 'created_by')) {
                    $customerData['created_by'] = auth()->id();
                }

                if (\Illuminate\Support\Facades\Schema::hasColumn('customers', 'updated_by')) {
                    $customerData['updated_by'] = auth()->id();
                }

                if (\Illuminate\Support\Facades\Schema::hasColumn('customers', 'user_id')) {
                    $customerData['user_id'] = $lead->user_id
                        ?? $lead->assigned_user_id
                        ?? auth()->id();
                }

                $existing = Customer::create($customerData);
            }

            $lead->update([
                'is_converted' => true,
                'converted_customer_id' => $existing->id,
            ]);

            return $existing;
        });

        return redirect()
            ->route('masters.customers.edit', $customer->id)
            ->with('success', 'Customer created from lead.');
    }

    public function export(Request $request)
    {
        $fileName = 'leads_' . date('Y-m-d_H-i-s') . '.csv';

        $query = $this->scopeOwnedRecords(
            Lead::with(['leadSource', 'stage', 'assignedUser'])
        )
            ->latest()
            ->when($request->search, function ($q) use ($request) {
                $search = $request->search;
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('whatsapp', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->source_id, fn($q) => $q->where('lead_source_id', $request->source_id))
            ->when($request->stage_id, fn($q) => $q->where('stage_id', $request->stage_id))
            ->when($request->from_date && $request->to_date, function ($q) use ($request) {
                $q->whereBetween('created_at', [
                    $request->from_date . ' 00:00:00',
                    $request->to_date . ' 23:59:59',
                ]);
            });

        $leads = $query->get();

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$fileName",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = ['No', 'Name', 'Email', 'Phone', 'WhatsApp', 'Status', 'Source', 'Stage', 'Notes', 'Created At'];

        $callback = function () use ($leads, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            $i = 1;
            foreach ($leads as $lead) {
                fputcsv($file, [
                    $i++,
                    $lead->name,
                    $lead->email,
                    $lead->phone,
                    $lead->whatsapp,
                    ucfirst(str_replace('_', ' ', $lead->status)),
                    $lead->leadSource?->name ?? $lead->source,
                    $lead->stage?->name,
                    $lead->notes,
                    $lead->created_at?->timezone('Asia/Kolkata')->format('d-m-Y h:i A') ?? 'N/A',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function selectableUsers()
    {
        if (auth()->user()?->isAdmin()) {
            return User::orderBy('name')->get();
        }

        return User::where('id', auth()->id())->orderBy('name')->get();
    }
}
