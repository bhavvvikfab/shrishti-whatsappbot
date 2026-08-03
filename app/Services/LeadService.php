<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\LeadTag;
use App\Models\LeadSource;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class LeadService
{
    public function getAllLeads(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Lead::with(['leadSource', 'stage', 'assignedUser', 'tags']);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('email', 'like', "%{$filters['search']}%")
                    ->orWhere('phone', 'like', "%{$filters['search']}%")
                    ->orWhere('whatsapp', 'like', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['source_id'])) {
            $query->where('lead_source_id', $filters['source_id']);
        }

        if (!empty($filters['stage_id'])) {
            $query->where('lead_stage_id', $filters['stage_id']);
        }

        if (!empty($filters['assigned_user_id'])) {
            $query->where('assigned_user_id', $filters['assigned_user_id']);
        }

        if (!empty($filters['tag'])) {
            $query->withTag($filters['tag']);
        }

        if (!empty($filters['with_whatsapp'])) {
            $query->withWhatsApp();
        }

        if (!empty($filters['from_date']) && !empty($filters['to_date'])) {
            $query->whereBetween('created_at', [
                $filters['from_date'] . ' 00:00:00',
                $filters['to_date'] . ' 23:59:59',
            ]);
        }

        return $query->latest()->paginate($perPage);
    }

    public function createLead(array $data): Lead
    {
        return DB::transaction(function () use ($data) {
            $lead = Lead::create($data);

            if (!empty($data['tags'])) {
                $lead->tags()->sync($data['tags']);
            }

            return $lead->load('tags');
        });
    }

    public function updateLead(Lead $lead, array $data): Lead
    {
        return DB::transaction(function () use ($lead, $data) {
            $lead->update($data);

            if (isset($data['tags'])) {
                $lead->tags()->sync($data['tags']);
            }

            return $lead->load('tags');
        });
    }

    public function deleteLead(Lead $lead): bool
    {
        return $lead->delete();
    }

    public function addNote(Lead $lead, string $note, string $noteType = 'general', bool $isPrivate = false): LeadNote
    {
        return LeadNote::create([
            'lead_id' => $lead->id,
            'created_by' => auth()->id(),
            'note' => $note,
            'note_type' => $noteType,
            'is_private' => $isPrivate,
        ]);
    }

    public function getLeadStats(): array
    {
        return [
            'total' => Lead::count(),
            'open' => Lead::where('status', 'open')->count(),
            'in_progress' => Lead::where('status', 'in_progress')->count(),
            'won' => Lead::where('status', 'won')->count(),
            'lost' => Lead::where('status', 'lost')->count(),
            'converted' => Lead::where('is_converted', true)->count(),
            'with_whatsapp' => Lead::whereNotNull('whatsapp')->count(),
        ];
    }

    public function getLeadsByStage(): array
    {
        return Stage::with(['leads' => function ($query) {
            $query->where('status', '!=', 'lost');
        }])->get()->map(function ($stage) {
            return [
                'stage' => $stage->name,
                'count' => $stage->leads->count(),
                'leads' => $stage->leads,
            ];
        })->toArray();
    }

    public function convertToCustomer(Lead $lead): array
    {
        if ($lead->is_converted && $lead->converted_customer_id) {
            return ['success' => false, 'message' => 'Lead already converted'];
        }

        return DB::transaction(function () use ($lead) {
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
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'user_id' => $lead->user_id ?? $lead->assigned_user_id ?? auth()->id(),
            ];

            $customer = \App\Models\Customer::create($customerData);

            $lead->update([
                'is_converted' => true,
                'converted_customer_id' => $customer->id,
            ]);

            return [
                'success' => true,
                'customer_id' => $customer->id,
                'message' => 'Lead converted to customer successfully',
            ];
        });
    }
}
