<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Customer;
use App\Models\Booking;
use App\Models\Deal;
use App\Models\Stage;
use App\Models\Task;
use App\Models\Meeting;
use App\Models\WhatsappConversation;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use App\Models\FollowUp;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    private const REMINDER_DISPLAY_TIMEZONE = 'Asia/Kolkata';

    public function index(): View
    {
        $stages = $this->buildLeadBoard();
        $canViewCustomers = auth()->check();

        return view('dashboard.index', [
            'stats' => $this->buildStats(),
            'stages' => $stages,
            'canViewCustomers' => $canViewCustomers,
        ]);
    }

    public function apiStats(): JsonResponse
    {
        try {
            $stats = $this->buildStats();
            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Dashboard stats error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load stats: ' . $e->getMessage(),
                'data' => [
                    'customers' => 0,
                    'follow_ups' => 0,
                    'leads' => 0,
                    'deals' => 0,
                    'whatsapp_conversations' => 0,
                    'new_customers_today' => 0,
                    'new_leads_today' => 0,
                    'pending_followups' => 0,
                    'completed_followups_today' => 0,
                    'active_leads' => 0,
                    'confirmed_bookings' => 0,
                    'conversion_rate' => 0,
                ],
            ]);
        }
    }

    public function apiLeadBoard(): JsonResponse
    {
        try {
            if (!auth()->user()?->hasMatrixPermission('view_leads')) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $this->buildLeadBoard()->map(function ($stage) {
                    return [
                        'id' => $stage->id,
                        'name' => $stage->name,
                        'count' => $stage->leads_count,
                        'leads' => collect($stage->leads)->map(function (Lead $lead) {
                            return [
                                'id' => $lead->id,
                                'name' => $lead->name,
                                'email' => $lead->email,
                                'phone' => $lead->phone,
                                'assigned_to' => $lead->assignedUser?->name,
                                'created_at' => optional($lead->created_at)->toIso8601String(),
                            ];
                        })->values()->toArray(),
                    ];
                })->values()->toArray(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Dashboard lead board error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load lead board: ' . $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function apiTasksWidget(Request $request): JsonResponse
    {
        try {
            if (!auth()->user()?->hasMatrixPermission('view_tasks')) {
                return response()->json(['success' => true, 'data' => []]);
            }

            $filter = $request->get('filter', 'all');

            $query = Task::query()
                ->with([
                    'assignedUser:id,name',
                    'customer:id,name',
                    'project:id,customer_id',
                    'project.customer:id,name',
                ]);

            $this->scopeOwnedRecords($query);

            if ($filter === 'due') {
                $query->whereDate('due_date', '<=', today())
                      ->whereNotIn('status', ['completed', 'cancelled']);
            }

            $tasks = $query->latest()
                ->take(5)
                ->get(['id', 'title', 'priority', 'status', 'assigned_user_id', 'related_type', 'related_id', 'project_id', 'due_date']);

            return response()->json([
                'success' => true,
                'data' => $tasks->map(function (Task $task) {
                    return [
                        'id'          => $task->id,
                        'title'       => $task->title,
                        'priority'    => $task->priority,
                        'status'      => $task->status,
                        'assigned_to' => $task->assignedUser?->name,
                        'customer'    => $task->customer?->name ?? $task->project?->customer?->name,
                        'due_date'    => optional($task->due_date)->format('d M Y'),
                    ];
                })->values()->toArray(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Dashboard tasks widget error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to load tasks.', 'data' => []], 500);
        }
    }

    public function apiInactiveLeads(): JsonResponse
    {
        try {
            if (!auth()->user()?->hasMatrixPermission('view_leads')) {
                return response()->json(['success' => true, 'data' => []]);
            }

            $cutoff = Carbon::now()->subDays(3);

            $leads = $this->visibleLeads()
                ->with('assignedUser:id,name')
                ->where('is_converted', false)
                ->where('updated_at', '<=', $cutoff)
                ->latest('updated_at')
                ->take(5)
                ->get(['id', 'name', 'email', 'phone', 'status', 'assigned_user_id', 'updated_at']);

            return response()->json([
                'success' => true,
                'data' => $leads->map(function (Lead $lead) {
                    return [
                        'id'          => $lead->id,
                        'name'        => $lead->name,
                        'email'       => $lead->email,
                        'phone'       => $lead->phone,
                        'status'      => $lead->status,
                        'assigned_to' => $lead->assignedUser?->name ?? 'Unassigned',
                        'last_active' => optional($lead->updated_at)->diffForHumans(),
                    ];
                })->values()->toArray(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Dashboard inactive leads error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to load inactive leads.', 'data' => []], 500);
        }
    }

    public function apiTrend(): JsonResponse
    {
        try {
            $months = collect(range(5, 0))->map(function (int $offset) {
                $dt = Carbon::now()->subMonths($offset)->startOfMonth();

                return [
                    'label' => $dt->format('M Y'),
                    'start' => $dt->copy()->startOfMonth(),
                    'end' => $dt->copy()->endOfMonth(),
                ];
            })->push([
                        'label' => Carbon::now()->format('M Y'),
                        'start' => Carbon::now()->copy()->startOfMonth(),
                        'end' => Carbon::now()->copy()->endOfMonth(),
                    ])->values();

            $user = auth()->user();
            $canLeads = $user?->hasMatrixPermission('view_leads') ?? false;
            $canFollowups = $user?->hasMatrixPermission('view_followups') ?? false;
            $canCustomers = (bool) $user;
            $canDeals = $user?->hasMatrixPermission('view_deals') ?? false;

            $emptySeries = $months->map(fn() => 0)->values();

            $countByRange = function (callable $queryFactory) use ($months) {
                return $months->map(function (array $month) use ($queryFactory) {
                    try {
                        return $queryFactory()
                            ->whereBetween('created_at', [$month['start'], $month['end']])
                            ->count();
                    } catch (\Exception $e) {
                        return 0;
                    }
                })->values();
            };
            $countCustomersByRange = function () use ($months, $user) {
                return $months->map(function (array $month) use ($user) {
                    try {
                        $query = $this->scopeOwnedRecords(Customer::query());
                        return $query->whereBetween('created_at', [$month['start'], $month['end']])
                            ->count();
                    } catch (\Exception $e) {
                        return 0;
                    }
                })->values();
            };

            return response()->json([
                'success' => true,
                'data' => [
                    'labels' => $months->pluck('label')->values()->toArray(),
                    'datasets' => [
                        'leads' => $canLeads ? $countByRange(fn () => $this->visibleLeads())->toArray() : $emptySeries->toArray(),
                        'followups' => $canFollowups ? $countByRange(fn () => $this->visibleFollowUps())->toArray() : $emptySeries->toArray(),
                        'customers' => $canCustomers ? $countCustomersByRange()->toArray() : $emptySeries->toArray(),
                        'deals' => $canDeals ? $countByRange(fn () => $this->visibleDeals())->toArray() : $emptySeries->toArray(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Dashboard trend error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load trend: ' . $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function apiCustomerReport(Request $request): JsonResponse
    {
        try {
            if (!auth()->check()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'year' => (int) ($request->get('year') ?: now()->year),
                        'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                        'series' => collect(range(1, 12))->map(fn() => 0)->values()->toArray(),
                    ],
                ]);
            }

            $year = (int) ($request->get('year') ?: now()->year);
            $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

            $series = collect(range(1, 12))->map(function (int $month) use ($year) {
                $query = $this->scopeOwnedRecords(Customer::query());
                return $query->whereYear('created_at', $year)
                    ->whereMonth('created_at', $month)
                    ->count();
            })->values()->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'year' => $year,
                    'labels' => $labels,
                    'series' => $series,
                ],
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Dashboard customer report error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load customer report: ' . $e->getMessage(),
                'data' => [
                    'year' => (int) ($request->get('year') ?: now()->year),
                    'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    'series' => collect(range(1, 12))->map(fn() => 0)->values()->toArray(),
                ],
            ]);
        }
    }

    public function apiDealsWidget(): JsonResponse
    {
        try {
            if (!auth()->user()?->hasMatrixPermission('view_deals')) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $query = Deal::query()
                ->with([
                    'status:id,name,color',
                    'customer:id,name',
                    'assignedUser:id,name',
                ]);
            
            $this->scopeOwnedRecords($query);
            
            $deals = $query->latest()
                ->take(4)
                ->get(['id', 'title', 'amount', 'probability', 'status_id', 'customer_id', 'assigned_user_id', 'expected_close_date']);

            return response()->json([
                'success' => true,
                'data' => $deals->map(function (Deal $deal) {
                    return [
                        'id' => $deal->id,
                        'name' => $deal->title,
                        'amount' => (float) $deal->amount,
                        'probability' => $deal->probability,
                        'status' => $deal->status?->name,
                        'status_color' => $deal->status?->color,
                        'customer' => $deal->customer?->name,
                        'assigned_to' => $deal->assignedUser?->name,
                        'expected_close_date' => optional($deal->expected_close_date)->format('d M Y'),
                    ];
                })->values()->toArray(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Dashboard deals widget error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load deals: ' . $e->getMessage(),
                'data' => [],
            ]);
        }
    }

    public function apiUpcomingReminders(): JsonResponse
    {
        try {
            $now = now();
            $displayNow = $now->copy()->timezone(self::REMINDER_DISPLAY_TIMEZONE);
            $dayStart = $displayNow->copy()->startOfDay()->timezone(config('app.timezone', 'UTC'));
            $dayEnd = $displayNow->copy()->endOfDay()->timezone(config('app.timezone', 'UTC'));
            $user = auth()->user();

            $meetings = collect();
            if ($user?->hasMatrixPermission('view_meetings')) {
                $meetings = $this->scopeOwnedRecords(
                    Meeting::with(['customer:id,name', 'assignedUser:id,name'])
                )
                    ->where('status', 'scheduled')
                    ->whereBetween('scheduled_at', [$dayStart, $dayEnd])
                    ->orderBy('scheduled_at')
                    ->get()
                    ->map(function (Meeting $meeting) use ($now) {
                        $scheduledAt = $meeting->scheduled_at?->copy()->timezone(self::REMINDER_DISPLAY_TIMEZONE);
                        $startsInMinutes = $meeting->scheduled_at ? $now->diffInMinutes($meeting->scheduled_at, false) : null;

                        return [
                            'id' => $meeting->id,
                            'type' => 'meeting',
                            'title' => $meeting->title ?: 'Upcoming Meeting',
                            'related_name' => $meeting->customer?->name ?? 'Customer',
                            'assigned_to' => $meeting->assignedUser?->name ?? 'Unassigned',
                            'status' => $meeting->status,
                            'meeting_type' => $meeting->meeting_type ? ucfirst($meeting->meeting_type) : null,
                            'location' => $meeting->address,
                            'details' => $meeting->agenda,
                            'starts_in_minutes' => $startsInMinutes,
                            'scheduled_for_text' => $scheduledAt?->format('d M Y, h:i A') ?? '-',
                            'view_url' => route('meetings.show', $meeting),
                        ];
                    })
                    ->values();
            }

            $followUps = collect();
            if ($user?->hasMatrixPermission('view_followups')) {
                $followUps = $this->scopeOwnedRecords(
                    FollowUp::with(['lead:id,name', 'assignedUser:id,name'])
                )
                    ->whereIn('status', ['pending', 'resheduled'])
                    ->whereBetween('follow_up_at', [$dayStart, $dayEnd])
                    ->orderBy('follow_up_at')
                    ->get()
                    ->map(function (FollowUp $followUp) use ($now) {
                        $followUpAt = $followUp->follow_up_at?->copy()->timezone(self::REMINDER_DISPLAY_TIMEZONE);
                        $startsInMinutes = $followUp->follow_up_at ? $now->diffInMinutes($followUp->follow_up_at, false) : null;

                        return [
                            'id' => $followUp->id,
                            'type' => 'follow_up',
                            'title' => $followUp->purpose ?: 'Upcoming Follow Up',
                            'related_name' => $followUp->lead?->name ?? 'Lead',
                            'assigned_to' => $followUp->assignedUser?->name ?? 'Unassigned',
                            'status' => $followUp->status,
                            'priority' => $followUp->priority,
                            'details' => $followUp->comment,
                            'starts_in_minutes' => $startsInMinutes,
                            'scheduled_for_text' => $followUpAt?->format('d M Y, h:i A') ?? '-',
                            'view_url' => route('followups.show', $followUp),
                        ];
                    })
                    ->values();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'date_label' => now()->timezone(self::REMINDER_DISPLAY_TIMEZONE)->format('d M Y'),
                    'meetings' => $meetings->toArray(),
                    'follow_ups' => $followUps->toArray(),
                    'has_items' => $meetings->isNotEmpty() || $followUps->isNotEmpty(),
                ],
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Dashboard reminders error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load reminders: ' . $e->getMessage(),
                'data' => [
                    'date_label' => now()->timezone(self::REMINDER_DISPLAY_TIMEZONE)->format('d M Y'),
                    'meetings' => [],
                    'follow_ups' => [],
                    'has_items' => false,
                ],
            ]);
        }
    }

    private function buildStats(): array
    {
        try {
            $user = auth()->user();
            $canCustomers = (bool) $user;
            $canFollowups = $user?->hasMatrixPermission('view_followups') ?? false;
            $canLeads = $user?->hasMatrixPermission('view_leads') ?? false;
            $canDeals = $user?->hasMatrixPermission('view_deals') ?? false;
            $canBookings = $user?->hasMatrixPermission('view_bookings') ?? false;
            $canWhatsappConversations = \App\Models\Setting::isEnabled('whatsapp_module_enabled', true)
                && ($user?->canUseWhatsappInbox() ?? false);

            $customersQuery = $canCustomers
                ? $this->scopeOwnedRecords(Customer::query())
                : Customer::query()->whereRaw('1 = 0');

            $customers = $canCustomers ? (clone $customersQuery)->count() : 0;
            $followUps = $canFollowups ? $this->visibleFollowUps()->count() : 0;
            $pendingFollowUps = $canFollowups ? $this->visibleFollowUps()->where('status', 'pending')->count() : 0;
            $completedFollowUpsToday = $canFollowups
                ? $this->visibleFollowUps()->where('status', 'completed')->whereDate('updated_at', today())->count()
                : 0;
            $leads = $canLeads ? $this->visibleLeads()->count() : 0;
            $activeLeads = $canLeads ? $this->visibleLeads()->whereNotIn('status', ['won', 'lost'])->count() : 0;
            $deals = $canDeals ? $this->visibleDeals()->count() : 0;
            $whatsappConversations = $canWhatsappConversations
                ? WhatsappConversation::query()->where('unread_count', '>', 0)->count()
                : 0;
            $confirmedBookings = $canBookings ? $this->visibleBookings()->where('status', 'confirmed')->count() : 0;
            $totalLeads = $leads;

            return [
                'customers' => $customers,
                'follow_ups' => $followUps,
                'leads' => $leads,
                'deals' => $deals,
                'whatsapp_conversations' => $whatsappConversations,
                'new_customers_today' => $canCustomers ? (clone $customersQuery)->whereDate('created_at', today())->count() : 0,
                'new_leads_today' => $canLeads ? $this->visibleLeads()->whereDate('created_at', today())->count() : 0,
                'pending_followups' => $pendingFollowUps,
                'completed_followups_today' => $completedFollowUpsToday,
                'active_leads' => $activeLeads,
                'confirmed_bookings' => $confirmedBookings,
                'conversion_rate' => $totalLeads > 0 ? round(($confirmedBookings / $totalLeads) * 100) : 0,
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('buildStats error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    private function buildLeadBoard()
    {
        try {
            if (!auth()->user()?->hasMatrixPermission('view_leads')) {
                return collect();
            }

            $statusLabels = [
                'new' => 'New',
                'qualified' => 'Qualified',
                'working' => 'Working',
                'ready_to_close' => 'Ready to Close',
            ];

            return collect($statusLabels)->map(function ($label, $key) {
                try {
                    $query = $this->visibleLeads()->with('assignedUser:id,name')->where('status', $key);

                    $leads = (clone $query)
                        ->latest()
                        ->limit(1)
                        ->get(['id', 'name', 'email', 'phone', 'assigned_user_id', 'created_at', 'status']);

                    return (object) [
                        'id' => $key,
                        'name' => $label,
                        'color' => null,
                        'leads_count' => $query->count(),
                        'leads' => $leads,
                    ];
                } catch (\Exception $e) {
                    return (object) [
                        'id' => $key,
                        'name' => $label,
                        'color' => null,
                        'leads_count' => 0,
                        'leads' => collect(),
                    ];
                }
            })->values();
        } catch (\Exception $e) {
            return collect();
        }
    }

    private function visibleLeads(): Builder
    {
        return $this->scopeOwnedRecords(Lead::query());
    }

    private function visibleDeals(): Builder
    {
        return $this->scopeOwnedRecords(Deal::query());
    }

    private function visibleFollowUps(): Builder
    {
        return $this->scopeOwnedRecords(FollowUp::query());
    }

    private function visibleBookings(): Builder
    {
        $user = auth()->user();
        $query = Booking::query();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($user) {
            $builder->whereHas('lead', function (Builder $leadQuery) use ($user) {
                $leadQuery->where('user_id', $user->id);
            })->orWhereHas('customer', function (Builder $customerQuery) use ($user) {
                $customerQuery->where('user_id', $user->id);
            });
        });
    }
}
