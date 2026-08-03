@extends('layouts.app')

@push('styles')
<style>
.stat-card { border-radius: 12px; border: none; transition: transform .2s; }
.stat-card:hover { transform: translateY(-2px); }
.stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
</style>
@endpush

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1"><i class="bi bi-bar-chart text-primary me-2"></i>WhatsApp Analytics</h4>
            <p class="text-muted mb-0">Overview of your WhatsApp CRM performance</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('whatsapp.inbox') }}" class="btn btn-outline-success btn-sm">
                <i class="fab fa-whatsapp me-1"></i>Inbox
            </a>
        </div>
    </div>

    <!-- Key Stats -->
    <div class="row g-3 mb-4">
        @php
        $statCards = [
            ['label' => 'Total Leads', 'value' => number_format($stats['total_leads']), 'icon' => 'bi-people', 'color' => 'primary', 'bg' => '#eff6ff'],
            ['label' => 'New Today', 'value' => number_format($stats['new_leads_today']), 'icon' => 'bi-person-plus', 'color' => 'success', 'bg' => '#f0fdf4'],
            ['label' => 'Converted', 'value' => number_format($stats['converted_leads']), 'icon' => 'bi-trophy', 'color' => 'warning', 'bg' => '#fffbeb'],
            ['label' => 'Conversion Rate', 'value' => $stats['conversion_rate'] . '%', 'icon' => 'bi-graph-up', 'color' => 'info', 'bg' => '#f0f9ff'],
            ['label' => 'Open Chats', 'value' => number_format($stats['open_conversations']), 'icon' => 'bi-chat-dots', 'color' => 'success', 'bg' => '#f0fdf4'],
            ['label' => 'Unread', 'value' => number_format($stats['unread_messages']), 'icon' => 'bi-envelope-exclamation', 'color' => 'danger', 'bg' => '#fef2f2'],
            ['label' => 'Messages Today', 'value' => number_format($stats['messages_today']), 'icon' => 'bi-chat-text', 'color' => 'primary', 'bg' => '#eff6ff'],
        ];
        @endphp
        @foreach($statCards as $card)
        <div class="col-6 col-md-3">
            <div class="card stat-card shadow-sm" style="background:{{ $card['bg'] }}">
                <div class="card-body d-flex align-items-center gap-3 py-3">
                    <div class="stat-icon bg-{{ $card['color'] }} bg-opacity-10 text-{{ $card['color'] }}">
                        <i class="bi {{ $card['icon'] }}"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold">{{ $card['value'] }}</div>
                        <div class="text-muted small">{{ $card['label'] }}</div>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="row g-4">
        <!-- Messages Chart -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-bold">Message Activity (Last 7 Days)</h6>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-secondary active" onclick="loadChart('messages', '7days', this)">7D</button>
                        <button class="btn btn-outline-secondary" onclick="loadChart('messages', '30days', this)">30D</button>
                        <button class="btn btn-outline-secondary" onclick="loadChart('leads', '7days', this)">Leads</button>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="activityChart" height="100"></canvas>
                </div>
            </div>
        </div>

        <!-- Lead Sources -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent">
                    <h6 class="mb-0 fw-bold">Leads by Source</h6>
                </div>
                <div class="card-body">
                    <canvas id="sourceChart" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Lead Stages -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent">
                    <h6 class="mb-0 fw-bold">Leads by Stage</h6>
                </div>
                <div class="card-body">
                    @forelse($leadsByStage as $item)
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between small mb-1">
                                <span>{{ $item['stage'] }}</span>
                                <span class="fw-semibold">{{ $item['total'] }}</span>
                            </div>
                            <div class="progress" style="height:6px">
                                @php $max = collect($leadsByStage)->max('total') ?: 1; @endphp
                                <div class="progress-bar bg-primary" style="width:{{ ($item['total'] / $max) * 100 }}%"></div>
                            </div>
                        </div>
                    </div>
                    @empty
                    <p class="text-muted text-center py-3">No stage data available</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Top Agents -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent">
                    <h6 class="mb-0 fw-bold">Top Agents by Conversations</h6>
                </div>
                <div class="card-body">
                    @forelse($topAgents as $agent)
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold"
                            style="width:36px;height:36px;font-size:14px">
                            {{ strtoupper(substr($agent['agent'], 0, 1)) }}
                        </div>
                        <div class="flex-grow-1">
                            <div class="small fw-semibold">{{ $agent['agent'] }}</div>
                            <div class="progress mt-1" style="height:4px">
                                @php $maxAgent = collect($topAgents)->max('total') ?: 1; @endphp
                                <div class="progress-bar bg-success" style="width:{{ ($agent['total'] / $maxAgent) * 100 }}%"></div>
                            </div>
                        </div>
                        <span class="badge bg-light text-dark border">{{ $agent['total'] }}</span>
                    </div>
                    @empty
                    <p class="text-muted text-center py-3">No agent data available</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endpush

@push('scripts')
<script>
const CHART_DATA_URL = '{{ route('whatsapp.analytics.chart_data') }}';
let activityChart = null;

const sourceData = @json($leadsBySource);

// Source Pie Chart
const sourceCtx = document.getElementById('sourceChart').getContext('2d');
new Chart(sourceCtx, {
    type: 'doughnut',
    data: {
        labels: sourceData.map(d => d.source),
        datasets: [{
            data: sourceData.map(d => d.total),
            backgroundColor: ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#f97316'],
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
});

function loadChart(type, period, btn) {
    document.querySelectorAll('.btn-group .btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    fetch(`${CHART_DATA_URL}?type=${type}&period=${period}`)
        .then(r => r.json())
        .then(({ data }) => {
            const labels = data.map(d => d.date);
            const datasets = type === 'messages' ? [
                { label: 'Incoming', data: data.map(d => d.incoming || 0), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.1)', fill: true, tension: .4 },
                { label: 'Outgoing', data: data.map(d => d.outgoing || 0), borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,.1)', fill: true, tension: .4 },
            ] : [
                { label: 'New Leads', data: data.map(d => d.total || 0), borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,.1)', fill: true, tension: .4 },
                { label: 'Converted', data: data.map(d => d.converted || 0), borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.1)', fill: true, tension: .4 },
            ];

            if (activityChart) activityChart.destroy();
            const ctx = document.getElementById('activityChart').getContext('2d');
            activityChart = new Chart(ctx, {
                type: 'line',
                data: { labels, datasets },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'top' } },
                    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
                }
            });
        });
}

loadChart('messages', '7days', document.querySelector('.btn-group .btn'));
</script>
@endpush
