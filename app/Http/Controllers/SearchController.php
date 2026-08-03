<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Task;
use App\Models\Project;
use App\Models\FollowUp;
use App\Models\Meeting;
use App\Models\SupportTicket;
use App\Models\Product;
use App\Models\WhatsappConversation;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    /**
     * Perform global search across Leads, Customers, Deals, Tasks, and Projects.
     * Enforces user permissions and multi-tenant data boundaries.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $query = trim($request->get('q', ''));

        if (strlen($query) < 2) {
            return response()->json([
                'success' => true,
                'results' => []
            ]);
        }

        $results = [];
        $user = auth()->user();

        // 1. Leads
        if ($user && $user->hasMatrixPermission('view_leads')) {
            $leadsQuery = Lead::query();
            $this->scopeOwnedRecords($leadsQuery);
            $leads = $leadsQuery->where(function ($sub) use ($query) {
                $sub->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get();

            foreach ($leads as $lead) {
                $results[] = [
                    'type' => 'Lead',
                    'title' => $lead->name,
                    'subtitle' => $lead->email ?? $lead->phone ?? 'No contact info',
                    'badge' => ucfirst(str_replace('_', ' ', $lead->status ?? 'new')),
                    'badge_class' => 'bg-warning text-dark',
                    'url' => route('leads.show', $lead->id),
                    'icon' => 'fa-solid fa-bullhorn text-warning'
                ];
            }
        }

        // 2. Customers
        if ($user && $user->hasMatrixPermission('view_customers')) {
            $customersQuery = Customer::query();
            $this->scopeOwnedRecords($customersQuery);
            $customers = $customersQuery->where(function ($sub) use ($query) {
                $sub->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get();

            foreach ($customers as $customer) {
                $results[] = [
                    'type' => 'Customer',
                    'title' => $customer->name,
                    'subtitle' => $customer->email ?? $customer->phone ?? 'No contact info',
                    'badge' => $customer->is_active ? 'Active' : 'Inactive',
                    'badge_class' => $customer->is_active ? 'bg-success text-white' : 'bg-secondary text-white',
                    'url' => route('masters.customers.show', $customer->id),
                    'icon' => 'fa-solid fa-users text-success'
                ];
            }
        }

        // 3. Deals
        if ($user && $user->hasMatrixPermission('view_deals')) {
            $dealsQuery = Deal::query();
            $this->scopeOwnedRecords($dealsQuery);
            $deals = $dealsQuery->where('title', 'like', "%{$query}%")
                ->limit(5)
                ->get();

            foreach ($deals as $deal) {
                $results[] = [
                    'type' => 'Deal',
                    'title' => $deal->title,
                    'subtitle' => $deal->amount ? '$' . number_format($deal->amount, 2) : 'No amount',
                    'badge' => 'Deal',
                    'badge_class' => 'bg-info text-dark',
                    'url' => route('deals.show', $deal->id),
                    'icon' => 'fa-solid fa-medal text-warning'
                ];
            }
        }

        // 4. Tasks
        if ($user && $user->hasMatrixPermission('view_tasks')) {
            $tasksQuery = Task::query();
            $this->scopeOwnedRecords($tasksQuery);
            $tasks = $tasksQuery->where(function ($sub) use ($query) {
                $sub->where('title', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get();

            foreach ($tasks as $task) {
                $results[] = [
                    'type' => 'Task',
                    'title' => $task->title,
                    'subtitle' => $task->due_date ? 'Due: ' . $task->due_date->format('Y-m-d') : 'No due date',
                    'badge' => ucfirst($task->status ?? 'pending'),
                    'badge_class' => $task->status === 'completed' ? 'bg-success text-white' : 'bg-info text-white',
                    'url' => route('tasks.show', $task->id),
                    'icon' => 'fa-solid fa-tasks text-info'
                ];
            }
        }

        // 5. Projects
        if ($user && $user->hasMatrixPermission('view_projects')) {
            $projectsQuery = Project::query();
            $this->scopeOwnedRecords($projectsQuery);
            $hasProjectCode = \Illuminate\Support\Facades\Schema::hasColumn('projects', 'project_code');
            $projects = $projectsQuery->where(function ($sub) use ($query, $hasProjectCode) {
                $sub->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
                if ($hasProjectCode) {
                    $sub->orWhere('project_code', 'like', "%{$query}%");
                }
            })
            ->limit(5)
            ->get();

            foreach ($projects as $project) {
                $results[] = [
                    'type' => 'Project',
                    'title' => $project->name,
                    'subtitle' => $hasProjectCode ? ($project->project_code ?? 'No project code') : 'Project',
                    'badge' => ucfirst($project->status ?? 'active'),
                    'badge_class' => 'bg-danger text-white',
                    'url' => route('projects.show', $project->id),
                    'icon' => 'fas fa-project-diagram text-danger'
                ];
            }
        }

        // 6. Follow ups
        if ($user && $user->hasMatrixPermission('view_followups')) {
            $followupsQuery = FollowUp::query();
            $this->scopeOwnedRecords($followupsQuery);
            $followups = $followupsQuery->where(function ($sub) use ($query) {
                $sub->where('purpose', 'like', "%{$query}%")
                    ->orWhere('comment', 'like', "%{$query}%")
                    ->orWhere('priority', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get();

            foreach ($followups as $followup) {
                $results[] = [
                    'type' => 'Follow up',
                    'title' => $followup->purpose ?? 'No purpose',
                    'subtitle' => $followup->follow_up_at ? 'At: ' . $followup->follow_up_at->format('Y-m-d H:i') : 'No date',
                    'badge' => ucfirst($followup->status ?? 'pending'),
                    'badge_class' => 'bg-secondary text-white',
                    'url' => route('followups.index'),
                    'icon' => 'fa fa-user-tie text-secondary'
                ];
            }
        }

        // 7. Meetings
        if ($user && $user->hasMatrixPermission('view_meetings')) {
            $meetingsQuery = Meeting::query();
            $this->scopeOwnedRecords($meetingsQuery);
            $meetings = $meetingsQuery->where(function ($sub) use ($query) {
                $sub->where('title', 'like', "%{$query}%")
                    ->orWhere('agenda', 'like', "%{$query}%")
                    ->orWhere('address', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get();

            foreach ($meetings as $meeting) {
                $results[] = [
                    'type' => 'Meeting',
                    'title' => $meeting->title ?? 'Untitled Meeting',
                    'subtitle' => $meeting->scheduled_at ? 'At: ' . $meeting->scheduled_at->format('Y-m-d H:i') : 'No schedule',
                    'badge' => ucfirst($meeting->status ?? 'scheduled'),
                    'badge_class' => 'bg-success text-white',
                    'url' => route('meetings.index'),
                    'icon' => 'fa-solid fa-handshake text-success'
                ];
            }
        }
        // 8. Tickets
        if ($user && $user->hasMatrixPermission('view_tickets')) {
            $ticketsQuery = SupportTicket::query();
            $tickets = $ticketsQuery->where(function ($sub) use ($query) {
                $sub->where('ticket_name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get();

            foreach ($tickets as $ticket) {
                $results[] = [
                    'type' => 'Ticket',
                    'title' => $ticket->ticket_name ?? 'Untitled Ticket',
                    'subtitle' => $ticket->description ? (strlen($ticket->description) > 50 ? substr($ticket->description, 0, 47) . '...' : $ticket->description) : 'No description',
                    'badge' => ucfirst($ticket->status ?? 'open'),
                    'badge_class' => 'bg-info text-white',
                    'url' => route('tickets.show', $ticket->id),
                    'icon' => 'fa fa-ticket text-info'
                ];
            }
        }

        // 9. Products
        if ($user && $user->hasMatrixPermission('view_products')) {
            $productsQuery = Product::query();
            $products = $productsQuery->where(function ($sub) use ($query) {
                $sub->where('name', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get();

            foreach ($products as $product) {
                $results[] = [
                    'type' => 'Product',
                    'title' => $product->name ?? 'Untitled Product',
                    'subtitle' => $product->sku ? 'SKU: ' . $product->sku : ($product->price ? '$' . number_format($product->price, 2) : 'No SKU'),
                    'badge' => $product->stock_quantity ? $product->stock_quantity . ' in stock' : 'Out of stock',
                    'badge_class' => $product->stock_quantity ? 'bg-warning text-dark' : 'bg-secondary text-white',
                    'url' => route('products.index'),
                    'icon' => 'fa fa-cube text-warning'
                ];
            }
        }

        // 10. WhatsApp Conversations
        if ($user && Setting::isEnabled('whatsapp_module_enabled', true)) {
            $conversations = WhatsappConversation::query()
                ->where(function ($sub) use ($query) {
                    $sub->where('contact_name', 'like', "%{$query}%")
                        ->orWhere('phone_number', 'like', "%{$query}%");
                })
                ->limit(5)
                ->get();

            foreach ($conversations as $conversation) {
                $results[] = [
                    'type' => 'WhatsApp',
                    'title' => $conversation->contact_name ?: $conversation->phone_number,
                    'subtitle' => $conversation->phone_number ?? 'No phone',
                    'badge' => ucfirst($conversation->status ?? 'open'),
                    'badge_class' => 'bg-success text-white',
                    'url' => route('whatsapp.conversation', $conversation->id),
                    'icon' => 'fab fa-whatsapp',
                ];
            }
        }

        return response()->json([
            'success' => true,
            'results' => $results
        ]);
    }
}
