<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\SupplierPayable;
use App\Models\Lead;
use App\Models\Customer;
use App\Models\Agent;
use App\Models\Payment;
use App\Models\SupplierPayment;
use App\Models\Task;
use App\Models\FollowUp;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    private const FOLLOW_UP_TIMEZONE = 'Asia/Kolkata';

    public function index()
    {
        $visibleBookings = $this->visibleBookings();
        $visibleLeads = $this->visibleLeads();
        $visibleCustomers = $this->visibleCustomers();
        $visibleTasks = $this->visibleTasks();
        $visibleFollowUps = $this->visibleFollowUps();

        $stats = [
            'total_revenue' => (clone $visibleBookings)->sum('total_amount'),
            'total_costs' => $this->visibleSupplierPayables()->sum('amount'),
            'total_leads' => (clone $visibleLeads)->count(),
            'converted_leads' => (clone $visibleLeads)->where('is_converted', true)->count(),
            'total_customers' => (clone $visibleCustomers)->count(),
            'total_tasks' => (clone $visibleTasks)->count(),
            'pending_tasks' => (clone $visibleTasks)->where('status', 'pending')->count(),
            'pending_receivables' => (clone $visibleBookings)->sum('total_amount') - $this->visiblePayments()->sum('amount'),
            'pending_payables' => $this->visibleSupplierPayables()->sum('amount') - $this->visibleSupplierPayments()->sum('amount'),
        ];

        $stats['gross_profit'] = $stats['total_revenue'] - $stats['total_costs'];
        $stats['conversion_rate'] = $stats['total_leads'] > 0
            ? round(($stats['converted_leads'] / $stats['total_leads']) * 100, 1)
            : 0;

        // Lead Status Distribution
        $leadsByStatus = $this->visibleLeads()->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status');

        // Task Status Distribution
        $tasksByStatus = $this->visibleTasks()->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status');

        // Customer Growth Data
        $customerGrowth = $this->visibleCustomers()->select(
            DB::raw('count(*) as total'),
            DB::raw("DATE_FORMAT(created_at, '%b %Y') as month"),
            DB::raw("YEAR(created_at) as year"),
            DB::raw("MONTH(created_at) as month_num")
        )
            ->groupBy('month', 'year', 'month_num')
            ->orderBy('year')
            ->orderBy('month_num')
            ->get();

        // Revenue Data for Charts
        $monthlyRevenue = $this->visibleBookings()->select(
            DB::raw('SUM(total_amount) as revenue'),
            DB::raw("DATE_FORMAT(created_at, '%b %Y') as month"),
            DB::raw("YEAR(created_at) as year"),
            DB::raw("MONTH(created_at) as month_num")
        )
            ->groupBy('month', 'year', 'month_num')
            ->orderBy('year')
            ->orderBy('month_num')
            ->get();

        return view('crm.reports.index', compact('stats', 'leadsByStatus', 'tasksByStatus', 'customerGrowth', 'monthlyRevenue'));
    }

    public function profitAndLoss()
    {
        $bookings = $this->visibleBookings()->with(['customer', 'payables'])
            ->latest()
            ->paginate(20);

        return view('crm.reports.profit_loss', compact('bookings'));
    }

    public function salesPerformance()
    {
        $agentPerformance = Agent::select('agents.*')
            ->addSelect([
                'bookings_count' => Booking::whereColumn('agent_id', 'agents.id')->selectRaw('count(*)'),
                'total_sales' => Booking::whereColumn('agent_id', 'agents.id')->selectRaw('sum(total_amount)')
            ])
            ->orderByDesc('total_sales')
            ->get();

        return view('crm.reports.sales', compact('agentPerformance'));
    }

    public function pendingAccounts()
    {
        $receivables = Booking::with('customer')
            ->get()
            ->filter(function ($booking) {
                return $booking->total_amount > $booking->invoices->flatMap->payments->sum('amount');
            });

        $payables = SupplierPayable::with(['supplier', 'booking'])
            ->where('status', '!=', 'paid')
            ->get();

        return view('crm.reports.pending', compact('receivables', 'payables'));
    }

    public function customersReport(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $from_date = $request->get('from_date');
        $to_date = $request->get('to_date');
        $search = trim((string) $request->get('search', ''));

        if ($request->ajax()) {
            $query = $this->visibleCustomers()->with(['country', 'city'])->latest();

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%");
                });
            }

            if ($from_date) {
                $query->whereDate('created_at', '>=', $from_date);
            }
            if ($to_date) {
                $query->whereDate('created_at', '<=', $to_date);
            }
            if ($year && !$from_date && !$to_date) {
                $query->whereYear('created_at', $year);
            }

            $customers = $query->paginate(10)->appends($request->query());

            return response()->json([
                'success' => true,
                'message' => 'Customers retrieved successfully.',
                'data' => $customers,
            ]);
        }

        $years = $this->visibleCustomers()->selectRaw('YEAR(created_at) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        if ($years->isEmpty()) {
            $years = collect([date('Y')]);
        }

        // Fetch chart data
        $chartQuery = $this->visibleCustomers()->select(
            DB::raw('count(*) as total'),
            DB::raw("MONTH(created_at) as month_num")
        );

        if ($from_date) {
            $chartQuery->whereDate('created_at', '>=', $from_date);
        }
        if ($to_date) {
            $chartQuery->whereDate('created_at', '<=', $to_date);
        }
        if ($year && !$from_date && !$to_date) {
            $chartQuery->whereYear('created_at', $year);
        }

        $chartDataRaw = $chartQuery->groupBy('month_num')
            ->orderBy('month_num')
            ->get()
            ->pluck('total', 'month_num')
            ->toArray();

        $chartData = [];
        for ($i = 1; $i <= 12; $i++) {
            $chartData[] = $chartDataRaw[$i] ?? 0;
        }
        
        return view('crm.reports.customers_report', compact('years', 'year', 'from_date', 'to_date', 'chartData'));
    }

    public function leadsReport(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $from_date = $request->get('from_date');
        $to_date = $request->get('to_date');
        $search = trim((string) $request->get('search', ''));

        // ── DataTable AJAX response ──────────────────────────────
        if ($request->ajax()) {
            $query = $this->visibleLeads()->with(['leadSource', 'assignedUser'])->latest();

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('leadSource', function ($leadSourceQuery) use ($search) {
                            $leadSourceQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('assignedUser', function ($assignedUserQuery) use ($search) {
                            $assignedUserQuery->where('name', 'like', "%{$search}%");
                        });
                });
            }

            if ($from_date) {
                $query->whereDate('created_at', '>=', $from_date);
            }
            if ($to_date) {
                $query->whereDate('created_at', '<=', $to_date);
            }
            if ($year && !$from_date && !$to_date) {
                $query->whereYear('created_at', $year);
            }

            $leads = $query->paginate(10)->appends($request->query());

            return response()->json([
                'success' => true,
                'message' => 'Leads retrieved successfully.',
                'data' => $leads,
            ]);
        }

        // ── Year dropdown ────────────────────────────────────────
        $years = $this->visibleLeads()->selectRaw('YEAR(created_at) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        if ($years->isEmpty()) {
            $years = collect([date('Y')]);
        }

        // ── Chart data ───────────────────────────────────────────
        $chartQuery = $this->visibleLeads()->select(
            DB::raw('count(*) as total'),
            DB::raw('YEAR(created_at)  as year_num'),
            DB::raw('MONTH(created_at) as month_num')
        );

        if ($from_date) {
            $chartQuery->whereDate('created_at', '>=', $from_date);
        }
        if ($to_date) {
            $chartQuery->whereDate('created_at', '<=', $to_date);
        }
        if ($year && !$from_date && !$to_date) {
            $chartQuery->whereYear('created_at', $year);
        }

        $chartDataRaw = $chartQuery
            ->groupBy('year_num', 'month_num')
            ->orderBy('year_num')
            ->orderBy('month_num')
            ->get();

        if ($from_date || $to_date) {
            // Date-range mode: dynamic YYYY-MM labels
            $chartLabels = [];
            $chartData = [];
            foreach ($chartDataRaw as $row) {
                $chartLabels[] = sprintf('%d-%02d', $row->year_num, $row->month_num);
                $chartData[] = $row->total;
            }
        } else {
            // Full-year mode: Jan–Dec
            $byMonth = $chartDataRaw->pluck('total', 'month_num')->toArray();
            $chartLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $chartData = [];
            for ($i = 1; $i <= 12; $i++) {
                $chartData[] = $byMonth[$i] ?? 0;
            }
        }

        return view('crm.reports.leads_report', compact(
            'years',
            'year',
            'from_date',
            'to_date',
            'chartData',
            'chartLabels'
        ));
    }

    // Export method
    public function leadsExport()
    {
        $leads = $this->visibleLeads()->with(['leadSource', 'assignedUser'])->latest()->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="leads_export_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($leads) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Sr.No', 'Name', 'Email', 'Phone', 'Lead Source', 'Assigned To', 'Status', 'Is Converted', 'Created At']);

            foreach ($leads as $index => $lead) {
                fputcsv($handle, [
                    $index + 1,
                    $lead->name,
                    $lead->email,
                    $lead->phone,
                    $lead->leadSource?->name ?? '-',
                    $lead->assignedUser?->name ?? 'Unassigned',
                    $lead->status,
                    $lead->is_converted ? 'Yes' : 'No',
                    $lead->created_at->format('Y-m-d H:i'),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function dealsReport(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $from_date = $request->get('from_date');
        $to_date = $request->get('to_date');
        $search = trim((string) $request->get('search', ''));

        // ── DataTable AJAX response ──────────────────────────────
        if ($request->ajax()) {
            $query = $this->visibleDeals()->with(['customer', 'currency', 'status', 'assignedUser', 'stage', 'creator'])->latest();

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('amount', 'like', "%{$search}%")
                        ->orWhereHas('stage', function ($stageQuery) use ($search) {
                            $stageQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('status', function ($statusQuery) use ($search) {
                            $statusQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('creator', function ($creatorQuery) use ($search) {
                            $creatorQuery->where('name', 'like', "%{$search}%");
                        });
                });
            }

            if ($from_date) {
                $query->whereDate('created_at', '>=', $from_date);
            }
            if ($to_date) {
                $query->whereDate('created_at', '<=', $to_date);
            }
            if ($year && !$from_date && !$to_date) {
                $query->whereYear('created_at', $year);
            }

            $deals = $query->paginate(10)->appends($request->query());

            return response()->json([
                'success' => true,
                'message' => 'Deals retrieved successfully.',
                'data' => $deals,
            ]);
        }

        // ── Year dropdown ────────────────────────────────────────
        $years = $this->visibleDeals()->selectRaw('YEAR(created_at) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        if ($years->isEmpty()) {
            $years = collect([date('Y')]);
        }

        // ── Chart data ───────────────────────────────────────────
        $chartQuery = $this->visibleDeals()->select(
            DB::raw('count(*) as total'),
            DB::raw('YEAR(created_at)  as year_num'),
            DB::raw('MONTH(created_at) as month_num')
        );

        if ($from_date) {
            $chartQuery->whereDate('created_at', '>=', $from_date);
        }
        if ($to_date) {
            $chartQuery->whereDate('created_at', '<=', $to_date);
        }
        if ($year && !$from_date && !$to_date) {
            $chartQuery->whereYear('created_at', $year);
        }

        $chartDataRaw = $chartQuery
            ->groupBy('year_num', 'month_num')
            ->orderBy('year_num')
            ->orderBy('month_num')
            ->get();

        if ($from_date || $to_date) {
            // Date-range mode: dynamic YYYY-MM labels
            $chartLabels = [];
            $chartData = [];
            foreach ($chartDataRaw as $row) {
                $chartLabels[] = sprintf('%d-%02d', $row->year_num, $row->month_num);
                $chartData[] = $row->total;
            }
        } else {
            // Full-year mode: Jan–Dec
            $byMonth = $chartDataRaw->pluck('total', 'month_num')->toArray();
            $chartLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $chartData = [];
            for ($i = 1; $i <= 12; $i++) {
                $chartData[] = $byMonth[$i] ?? 0;
            }
        }

        return view('crm.reports.deals_report', compact(
            'years',
            'year',
            'from_date',
            'to_date',
            'chartData',
            'chartLabels'
        ));
    }

    public function dealsExport()
    {
        $deals = $this->visibleDeals()->with(['customer', 'currency', 'status', 'assignedUser', 'stage', 'creator'])->latest()->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="deals_export_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($deals) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Sr.No', 'Title', 'Customer', 'Amount', 'Currency', 'Probability', 'Stage', 'Status', 'Assigned To', 'Created By', 'Created At']);

            foreach ($deals as $index => $deal) {
                fputcsv($handle, [
                    $index + 1,
                    $deal->title,
                    $deal->customer?->name ?? '-',
                    $deal->amount,
                    $deal->currency?->code ?? '-',
                    $deal->probability . '%',
                    $deal->stage?->name ?? '-',
                    $deal->status?->name ?? '-',
                    $deal->assignedUser?->name ?? 'Unassigned',
                    $deal->creator?->name ?? '-',
                    $deal->created_at->format('Y-m-d H:i'),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function projectsReport(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $from_date = $request->get('from_date');
        $to_date = $request->get('to_date');
        $search = trim((string) $request->get('search', ''));

        if ($request->ajax()) {
            $query = $this->visibleProjects()->with(['customer', 'assignedUser', 'creator'])->latest();

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('creator', function ($creatorQuery) use ($search) {
                            $creatorQuery->where('name', 'like', "%{$search}%");
                        });
                });
            }

            if ($from_date) {
                $query->whereDate('created_at', '>=', $from_date);
            }
            if ($to_date) {
                $query->whereDate('created_at', '<=', $to_date);
            }
            if ($year && !$from_date && !$to_date) {
                $query->whereYear('created_at', $year);
            }

            $projects = $query->paginate(10)->appends($request->query());

            return response()->json([
                'success' => true,
                'message' => 'Projects retrieved successfully.',
                'data' => $projects,
            ]);
        }

        $years = $this->visibleProjects()->selectRaw('YEAR(created_at) as year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year');

        if ($years->isEmpty()) {
            $years = collect([date('Y')]);
        }

        $chartQuery = $this->visibleProjects()->select(
            DB::raw('count(*) as total'),
            DB::raw('YEAR(created_at)  as year_num'),
            DB::raw('MONTH(created_at) as month_num')
        );

        if ($from_date) {
            $chartQuery->whereDate('created_at', '>=', $from_date);
        }
        if ($to_date) {
            $chartQuery->whereDate('created_at', '<=', $to_date);
        }
        if ($year && !$from_date && !$to_date) {
            $chartQuery->whereYear('created_at', $year);
        }

        $chartDataRaw = $chartQuery
            ->groupBy('year_num', 'month_num')
            ->orderBy('year_num')
            ->orderBy('month_num')
            ->get();

        if ($from_date || $to_date) {
            $chartLabels = [];
            $chartData = [];
            foreach ($chartDataRaw as $row) {
                $chartLabels[] = sprintf('%d-%02d', $row->year_num, $row->month_num);
                $chartData[] = $row->total;
            }
        } else {
            $byMonth = $chartDataRaw->pluck('total', 'month_num')->toArray();
            $chartLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $chartData = [];
            for ($i = 1; $i <= 12; $i++) {
                $chartData[] = $byMonth[$i] ?? 0;
            }
        }

        return view('crm.reports.projects_report', compact(
            'years',
            'year',
            'from_date',
            'to_date',
            'chartData',
            'chartLabels'
        ));
    }

    public function projectsExport()
    {
        $projects = $this->visibleProjects()->with(['customer', 'assignedUser', 'creator'])->latest()->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="projects_export_' . date('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($projects) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Sr.No', 'Project Code', 'Name', 'Customer', 'Assigned To', 'Status', 'Start Date', 'End Date', 'Created By', 'Created At']);

            foreach ($projects as $index => $project) {
                fputcsv($handle, [
                    $index + 1,
                    $project->project_code,
                    $project->name,
                    $project->customer?->name ?? '-',
                    $project->assignedUser?->name ?? 'Unassigned',
                    $project->status ?? '-',
                    $project->start_date ? $project->start_date->format('Y-m-d') : '-',
                    $project->end_date ? $project->end_date->format('Y-m-d') : '-',
                    $project->creator?->name ?? '-',
                    $project->created_at->format('Y-m-d H:i'),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function tasksReport(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $from_date = $request->get('from_date');
        $to_date = $request->get('to_date');
        $search = trim((string) $request->get('search', ''));

        if ($request->ajax()) {
            $query = $this->visibleTasks()->with(['project.customer'])->latest();

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('priority', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('project', function ($projectQuery) use ($search) {
                            $projectQuery->where('name', 'like', "%{$search}%")
                                ->orWhereHas('customer', function ($customerQuery) use ($search) {
                                    $customerQuery->where('name', 'like', "%{$search}%");
                                });
                        });
                });
            }

            if ($from_date) {
                $query->whereDate('created_at', '>=', $from_date);
            }
            if ($to_date) {
                $query->whereDate('created_at', '<=', $to_date);
            }
            if ($year && !$from_date && !$to_date) {
                $query->whereYear('created_at', $year);
            }

            $tasks = $query->paginate(10)->appends($request->query());

            return response()->json([
                'success' => true,
                'message' => 'Tasks retrieved successfully.',
                'data' => $tasks,
            ]);
        }

        $years = $this->visibleTasks()->selectRaw('YEAR(created_at) as year')->distinct()->orderBy('year', 'desc')->pluck('year');
        if ($years->isEmpty()) {
            $years = collect([date('Y')]);
        }

        $chartQuery = $this->visibleTasks()->select(DB::raw('count(*) as total'), DB::raw('YEAR(created_at)  as year_num'), DB::raw('MONTH(created_at) as month_num'));
        if ($from_date) {
            $chartQuery->whereDate('created_at', '>=', $from_date);
        }
        if ($to_date) {
            $chartQuery->whereDate('created_at', '<=', $to_date);
        }
        if ($year && !$from_date && !$to_date) {
            $chartQuery->whereYear('created_at', $year);
        }

        $chartDataRaw = $chartQuery->groupBy('year_num', 'month_num')->orderBy('year_num')->orderBy('month_num')->get();

        if ($from_date || $to_date) {
            $chartLabels = [];
            $chartData = [];
            foreach ($chartDataRaw as $row) {
                $chartLabels[] = sprintf('%d-%02d', $row->year_num, $row->month_num);
                $chartData[] = $row->total;
            }
        } else {
            $byMonth = $chartDataRaw->pluck('total', 'month_num')->toArray();
            $chartLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $chartData = [];
            for ($i = 1; $i <= 12; $i++) {
                $chartData[] = $byMonth[$i] ?? 0;
            }
        }

        return view('crm.reports.tasks_report', compact('years', 'year', 'from_date', 'to_date', 'chartData', 'chartLabels'));
    }

    public function tasksExport()
    {
        $tasks = $this->visibleTasks()->with(['project.customer'])->latest()->get();
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="tasks_export_' . date('Y-m-d') . '.csv"'];
        $callback = function () use ($tasks) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Sr.No', 'Customer Name', 'Project Name', 'Task Title', 'Priority', 'Status', 'Due Date']);
            foreach ($tasks as $index => $task) {
                fputcsv($handle, [
                    $index + 1,
                    $task->project?->customer?->name ?? '-',
                    $task->project?->name ?? '-',
                    $task->title ?? '-',
                    $task->priority ?? '-',
                    $task->status ?? '-',
                    $task->due_date ? $task->due_date->format('Y-m-d') : '-'
                ]);
            }
            fclose($handle);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function followupsReport(Request $request)
    {
        $year = $request->get('year', date('Y'));
        $from_date = $request->get('from_date');
        $to_date = $request->get('to_date');
        $search = trim((string) $request->get('search', ''));

        if ($request->ajax()) {
            $query = $this->visibleFollowUps()->with(['lead', 'customer', 'assignedUser'])->latest();

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('purpose', 'like', "%{$search}%")
                        ->orWhere('priority', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhereHas('lead', function ($leadQuery) use ($search) {
                            $leadQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('assignedUser', function ($assignedUserQuery) use ($search) {
                            $assignedUserQuery->where('name', 'like', "%{$search}%");
                        });
                });
            }

            if ($from_date) {
                $query->whereDate('created_at', '>=', $from_date);
            }
            if ($to_date) {
                $query->whereDate('created_at', '<=', $to_date);
            }
            if ($year && !$from_date && !$to_date) {
                $query->whereYear('created_at', $year);
            }

            $followups = $query->paginate(10)->appends($request->query());
            $followups->getCollection()->transform(function (FollowUp $followup) {
                $localFollowUpAt = $followup->follow_up_at?->copy()->timezone(self::FOLLOW_UP_TIMEZONE);

                $followup->setAttribute('follow_up_at_display_date', $localFollowUpAt?->format('d M Y'));
                $followup->setAttribute('follow_up_at_display_time', $localFollowUpAt?->format('h:i A'));

                return $followup;
            });

            return response()->json([
                'success' => true,
                'message' => 'Followups retrieved successfully.',
                'data' => $followups,
            ]);
        }

        $years = $this->visibleFollowUps()->selectRaw('YEAR(created_at) as year')->distinct()->orderBy('year', 'desc')->pluck('year');
        if ($years->isEmpty()) {
            $years = collect([date('Y')]);
        }

        $chartQuery = $this->visibleFollowUps()->select(DB::raw('count(*) as total'), DB::raw('YEAR(created_at)  as year_num'), DB::raw('MONTH(created_at) as month_num'));
        if ($from_date) {
            $chartQuery->whereDate('created_at', '>=', $from_date);
        }
        if ($to_date) {
            $chartQuery->whereDate('created_at', '<=', $to_date);
        }
        if ($year && !$from_date && !$to_date) {
            $chartQuery->whereYear('created_at', $year);
        }

        $chartDataRaw = $chartQuery->groupBy('year_num', 'month_num')->orderBy('year_num')->orderBy('month_num')->get();

        if ($from_date || $to_date) {
            $chartLabels = [];
            $chartData = [];
            foreach ($chartDataRaw as $row) {
                $chartLabels[] = sprintf('%d-%02d', $row->year_num, $row->month_num);
                $chartData[] = $row->total;
            }
        } else {
            $byMonth = $chartDataRaw->pluck('total', 'month_num')->toArray();
            $chartLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $chartData = [];
            for ($i = 1; $i <= 12; $i++) {
                $chartData[] = $byMonth[$i] ?? 0;
            }
        }

        return view('crm.reports.followups_report', compact('years', 'year', 'from_date', 'to_date', 'chartData', 'chartLabels'));
    }

    public function followupsExport()
    {
        $followups = $this->visibleFollowUps()->with(['lead', 'customer', 'assignedUser'])->latest()->get();
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="followups_export_' . date('Y-m-d') . '.csv"'];
        $callback = function () use ($followups) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Sr.No', 'Purpose', 'Entity Name', 'Entity Type', 'Follow Up Date', 'Priority', 'Assigned To', 'Status']);
            foreach ($followups as $index => $followup) {
                $entity = $followup->lead ?? $followup->customer;
                $entityType = $followup->lead ? 'Lead' : ($followup->customer ? 'Customer' : 'Unknown');
                fputcsv($handle, [
                    $index + 1,
                    $followup->purpose ?? '-',
                    $entity?->name ?? 'Unknown',
                    $entityType,
                    $followup->follow_up_at?->timezone(self::FOLLOW_UP_TIMEZONE)->format('Y-m-d H:i') ?? '-',
                    $followup->priority ?? '-',
                    $followup->assignedUser?->name ?? 'Unassigned',
                    $followup->status ?? '-'
                ]);
            }
            fclose($handle);
        };
        return response()->stream($callback, 200, $headers);
    }

    private function visibleLeads(): Builder
    {
        return $this->scopeOwnedRecords(Lead::query());
    }

    private function visibleDeals(): Builder
    {
        return $this->scopeOwnedRecords(Deal::query());
    }

    private function visibleProjects(): Builder
    {
        return $this->scopeOwnedRecords(Project::query());
    }

    private function visibleTasks(): Builder
    {
        return $this->scopeOwnedRecords(Task::query());
    }

    private function visibleFollowUps(): Builder
    {
        return $this->scopeOwnedRecords(FollowUp::query());
    }

    private function visibleCustomers(): Builder
    {
        return $this->scopeOwnedRecords(Customer::query());
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

    private function visiblePayments(): Builder
    {
        $user = auth()->user();
        $query = Payment::query();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereHas('invoice.customer', function (Builder $customerQuery) use ($user) {
            $customerQuery->where('user_id', $user->id);
        });
    }

    private function visibleSupplierPayables(): Builder
    {
        $user = auth()->user();
        $query = SupplierPayable::query();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereHas('booking', function (Builder $bookingQuery) use ($user) {
            $bookingQuery->where(function (Builder $builder) use ($user) {
                $builder->whereHas('lead', function (Builder $leadQuery) use ($user) {
                    $leadQuery->where('user_id', $user->id);
                })->orWhereHas('customer', function (Builder $customerQuery) use ($user) {
                    $customerQuery->where('user_id', $user->id);
                });
            });
        });
    }

    private function visibleSupplierPayments(): Builder
    {
        $user = auth()->user();
        $query = SupplierPayment::query();

        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereHas('payable.booking', function (Builder $bookingQuery) use ($user) {
            $bookingQuery->where(function (Builder $builder) use ($user) {
                $builder->whereHas('lead', function (Builder $leadQuery) use ($user) {
                    $leadQuery->where('user_id', $user->id);
                })->orWhereHas('customer', function (Builder $customerQuery) use ($user) {
                    $customerQuery->where('user_id', $user->id);
                });
            });
        });
    }
}
