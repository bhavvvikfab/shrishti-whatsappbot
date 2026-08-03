<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Campaign;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\Project;
use App\Models\Meeting;
use App\Models\SupportTicket;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Service;
use App\Models\Pipeline;

class ChatbotController extends Controller
{
    public function assistant(Request $request)
    {
        if ($request->boolean('create_ticket')) {
            return $this->createTicket($request);
        }

        $userMessage = $request->message;

        // Route based on detected intent
        if (strpos($userMessage, 'fetch_') === 0) {
            return $this->handleFetchRequest($userMessage);
        }

        switch ($userMessage) {
            case 'search_customer':
                return $this->searchCustomer($userMessage);

            default:
                return response()->json([
                    'reply' => "I can help with customers, leads, campaigns, and updates.",
                ]);
        }
    }

    private function handleFetchRequest($intent)
    {
        // Format: fetch_{model}_{period}
        // Example: fetch_leads_today, fetch_customers_this_week
        preg_match('/fetch_(.+?)_(.+)/', $intent, $matches);

        if (count($matches) < 3) {
            // Check if it's just fetch_customers or similar (no period)
            if ($intent === 'fetch_customers') {
                return $this->getRecords(Customer::class, 'all', 'Customers');
            }
            return response()->json(['reply' => "I couldn't understand that request."]);
        }

        $type = $matches[1]; // leads, customers, etc.
        $period = $matches[2]; // today, this_week, this_month

        $modelMap = [
            'leads' => Lead::class,
            'customers' => Customer::class,
            'followups' => FollowUp::class,
            'tasks' => Task::class,
            'projects' => Project::class,
            'meetings' => Meeting::class,
            'deals' => Deal::class,
            'invoices' => Invoice::class,
            'tickets' => SupportTicket::class,
            'products' => Product::class,
            'services' => Service::class,
            'staff' => User::class,
            'pipeline' => Pipeline::class,
        ];

        if (!isset($modelMap[$type])) {
            return response()->json(['reply' => "I don't know how to fetch $type."]);
        }

        $label = ucfirst($type);
        return $this->getRecords($modelMap[$type], $period, $label);
    }

    private function getRecords($modelClass, $period, $label)
    {
        $query = $modelClass::query();
        $type = strtolower($label);
        if ($type === 'services') {
            $query->with('product');
        }

        if ($period === 'today') {
            $query->whereDate('created_at', today());
            $timeLabel = "today's";
        } elseif ($period === 'this_week') {
            $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            $timeLabel = "this week's";
        } elseif ($period === 'this_month') {
            $query->whereMonth('created_at', now()->month)
                  ->whereYear('created_at', now()->year);
            $timeLabel = "this month's";
        } else {
            $timeLabel = "";
        }

        $records = $query->limit(10)->get();

        if ($records->isEmpty()) {
            return response()->json([
                'reply' => "No $label found for $timeLabel."
            ]);
        }

        $formattedData = $records->map(function ($record) use ($type) {
            return [
                'id' => $record->id,
                'name' => $record->name ?? $record->title ?? $record->pipeline_name ?? $record->service_name ?? $record->product_name ?? $record->ticket_name ?? 'Record',
                'status' => $record->status,
                'service_name' => $record->service_name ?? null,
                'product_name' => ($type === 'services' && $record->product) ? $record->product->name : ($record->product_name ?? $record->name ?? null),
                'price' => $record->service_price ?? $record->price ?? null,
                'url' => $this->getRecordUrl($type, $record),
            ];
        });

        return response()->json([
            'reply' => "Here are $timeLabel $label:",
            'data'  => $formattedData
        ]);
    }

    private function getRecordUrl($type, $record)
    {
        $baseUrl = url('/');
        $routes = [
            'leads' => '/leads/',
            'customers' => '/masters/customers/',
            'followups' => '/followups/',
            'tasks' => '/tasks',
            'projects' => '/projects/',
            'meetings' => '/meetings/',
            'deals' => '/deals/',
            'invoices' => '/invoices/',
            'tickets' => '/tickets/',
            'products' => '/products/',
            'services' => '/services/',
            'staff' => '/users/',
            'pipeline' => '/pipeline/',
        ];

        $path = $routes[$type] ?? '#';
        if ($path !== '#' && isset($record->id)) {
            return $baseUrl . $path . '/' . $record->id;
        }
        return '#';
    }

    private function createTicket(Request $request)
    {
        $message = trim($request->message ?? '');
        
        if ($message === '') {
            return response()->json(['reply' => 'Please provide the ticket details.'], 422);
        }

        $ticket = SupportTicket::create([
            'customer_id' => auth()->id(),
            'ticket_name' => $message,
            'priority' => 'Medium',
            'status' => 'In Progress',
            'description' => $message,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
        
        if (!function_exists('send_ticket_created_notification')) {
            require_once app_path('Helpers/emailSendHelper.php');
        }

        $ticket->load(['customer', 'creator']);
        \send_ticket_created_notification($ticket);
        
        return response()->json([
            'reply' => 'Ticket saved successfully with name: ' . $message,
            'data' => $ticket,
        ]);
    }

    // 📌 Search customer by name
    private function searchCustomer($message)
    {
        preg_match('/(customer|find) (.+)/i', $message, $match);

        if (!isset($match[2])) {
            return ['reply' => "Please provide a customer name."];
        }

        $name = trim($match[2]);

        $results = Customer::where('name', 'like', "%$name%")->get();

        if ($results->isEmpty()) {
            return ['reply' => "No customer found with the name '$name'."];
        }

        return [
            'reply' => "Here are matching customers:",
            'data' => $results
        ];
    }

}