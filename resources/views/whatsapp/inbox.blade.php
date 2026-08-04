@extends('layouts.app')

@push('styles')
<style>
.wa-inbox-wrap { display: flex; height: calc(100vh - 70px); overflow: hidden; border-radius: 12px; }
.wa-sidebar { width: 340px; min-width: 280px; border-right: 1px solid var(--crm-border); display: flex; flex-direction: column; background: var(--crm-bg-card); }
.wa-sidebar-header { padding: 16px; border-bottom: 1px solid var(--crm-border); }
.wa-search { position: relative; }
.wa-search input { padding-left: 36px; border-radius: 20px; background: var(--crm-bg-input); border: 1px solid var(--crm-border); color: var(--crm-text-body); }
.wa-search .bi-search { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; }
.wa-conv-list { flex: 1; overflow-y: auto; }
.wa-conv-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; cursor: pointer; border-bottom: 1px solid var(--crm-border); transition: background .15s; text-decoration: none; color: inherit; }
.wa-conv-item:hover, .wa-conv-item.active { background: var(--crm-bg-soft); }
.wa-conv-avatar { width: 46px; height: 46px; border-radius: 50%; background: #25d366; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 18px; flex-shrink: 0; }
.wa-conv-info { flex: 1; min-width: 0; }
.wa-conv-name { font-weight: 600; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wa-conv-preview { font-size: 12px; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wa-conv-meta { text-align: right; flex-shrink: 0; }
.wa-conv-time { font-size: 11px; color: #9ca3af; }
.wa-badge { background: #25d366; color: #fff; border-radius: 999px; min-width: 20px; height: 20px; padding: 0 6px; font-size: 11px; font-weight: 700; line-height: 1; display: inline-flex; align-items: center; justify-content: center; margin-left: auto; margin-top: 4px; box-shadow: 0 1px 3px rgba(0,0,0,.18); }
.wa-main { flex: 1; display: flex; align-items: center; justify-content: center; background: var(--crm-bg-page); }
.wa-empty { text-align: center; color: #9ca3af; }
.wa-empty i { font-size: 64px; color: #d1d5db; }
.wa-status-tabs { display: flex; gap: 4px; padding: 8px 16px; border-bottom: 1px solid var(--crm-border); overflow-x: auto; scrollbar-width: none; }
.wa-status-tab { padding: 4px 12px; border-radius: 20px; font-size: 12px; cursor: pointer; border: 1px solid var(--crm-border); background: var(--crm-bg-card); color: var(--crm-text-muted); flex-shrink: 0; white-space: nowrap; }
.wa-status-tab.active { background: #25d366; color: #fff; border-color: #25d366; }

.wa-status-tabs::-webkit-scrollbar { display: none; }
.wa-conv-tag {
    display: inline-block;
    font-size: 10px;
    font-weight: 700;
    line-height: 1;
    padding: 2px 6px;
    border-radius: 999px;
    margin-left: 6px;
    vertical-align: middle;
}
.wa-conv-tag.pending { background: #fff3cd; color: #856404; }
.wa-conv-tag.paid { background: #d1f4e0; color: #0f5132; }
.wa-conv-tag.important { background: #fde8e8; color: #b42318; }
[data-theme="dark"] .wa-conv-tag.pending { background: rgba(255, 193, 7, 0.18); color: #ffc107; }
[data-theme="dark"] .wa-conv-tag.paid { background: rgba(37, 211, 102, 0.18); color: #25d366; }
[data-theme="dark"] .wa-conv-tag.important { background: rgba(220, 53, 69, 0.18); color: #ff6b6b; }
.wa-search-section-title {
    padding: 10px 16px 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #25d366;
}
.wa-search-hit {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--crm-border);
    text-decoration: none;
    color: inherit;
}
.wa-search-hit:hover { background: var(--crm-bg-soft); }
.wa-search-hit-body { flex: 1; min-width: 0; }
.wa-search-hit-name { font-weight: 600; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wa-search-hit-snippet { font-size: 12px; color: #6b7280; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wa-search-hit-time { font-size: 11px; color: #9ca3af; flex-shrink: 0; }
.wa-search-hit mark { background: rgba(37, 211, 102, 0.28); color: inherit; padding: 0 1px; border-radius: 2px; }

/* Custom Scrollbars */
.wa-conv-list::-webkit-scrollbar, .wa-main::-webkit-scrollbar { width: 6px; }
.wa-conv-list::-webkit-scrollbar-track, .wa-main::-webkit-scrollbar-track { background: transparent; }
.wa-conv-list::-webkit-scrollbar-thumb, .wa-main::-webkit-scrollbar-thumb { background-color: rgba(0, 0, 0, 0.2); border-radius: 10px; }
[data-theme="dark"] .wa-conv-list::-webkit-scrollbar-thumb, [data-theme="dark"] .wa-main::-webkit-scrollbar-thumb { background-color: rgba(255, 255, 255, 0.2); }

@media (max-width: 767.98px) {
    .wa-sidebar {
        width: 100%;
        min-width: 0;
        border-right: 0;
        border-radius: 0;
        min-height: 0;
        flex: 1 1 auto;
    }

    .wa-main {
        display: none;
    }

    .wa-sidebar-header {
        padding: 14px;
    }

    .wa-status-tabs {
        padding: 8px 14px;
        overflow-x: auto;
        scrollbar-width: none;
    }

    .wa-status-tabs::-webkit-scrollbar {
        display: none;
    }

    .wa-status-tab {
        flex: 0 0 auto;
        white-space: nowrap;
    }

    .wa-conv-item {
        padding: 12px 14px;
    }
}
</style>
@endpush

@section('content')
<div class="wa-inbox-wrap">
    <!-- Sidebar: Conversation List -->
    <div class="wa-sidebar">
        <div class="wa-sidebar-header">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="mb-0 fw-bold">
                    <i class="fab fa-whatsapp text-success me-2"></i>WhatsApp Inbox
                    @if($totalUnread > 0)
                        <span class="badge bg-success ms-1">{{ $totalUnread }}</span>
                    @endif
                </h6>
                <!-- <a href="{{ route('whatsapp.analytics') }}" class="btn btn-sm btn-outline-secondary" title="Analytics">
                    <i class="bi bi-bar-chart"></i>
                </a> -->
            </div>
            <div class="wa-search">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control form-control-sm" id="convSearch" placeholder="Search name, number, or messages...">
            </div>
        </div>

        @php $activeFilter = $filter ?? 'all'; @endphp
        <div class="wa-status-tabs">
            <button class="wa-status-tab {{ $activeFilter === 'all' ? 'active' : '' }}" data-status="all">All</button>
            <button class="wa-status-tab {{ $activeFilter === 'open' ? 'active' : '' }}" data-status="open">Open</button>
            <button class="wa-status-tab {{ $activeFilter === 'unread' ? 'active' : '' }}" data-status="unread">Unread</button>
            <button class="wa-status-tab {{ $activeFilter === 'pending_payment' ? 'active' : '' }}" data-status="pending_payment">Pending (Payment)</button>
            <button class="wa-status-tab {{ $activeFilter === 'paid' ? 'active' : '' }}" data-status="paid">Paid</button>
            <button class="wa-status-tab {{ $activeFilter === 'important' ? 'active' : '' }}" data-status="important">Important</button>
            <button class="wa-status-tab {{ $activeFilter === 'closed' ? 'active' : '' }}" data-status="closed">Closed</button>
            <button class="wa-status-tab {{ $activeFilter === 'archived' ? 'active' : '' }}" data-status="archived">Archived</button>
        </div>

        <div class="wa-conv-list" id="convList">
            @forelse($conversations as $conv)
            <a href="{{ route('whatsapp.conversation', $conv) }}" class="wa-conv-item">
                <div class="wa-conv-avatar">
                    {{ strtoupper(substr($conv->contact_name ?? $conv->phone_number, 0, 1)) }}
                </div>
                <div class="wa-conv-info">
                    <div class="wa-conv-name">
                        {{ $conv->contact_name ?? $conv->phone_number }}
                        @if($conv->hasTag('pending_payment'))
                            <span class="wa-conv-tag pending">Pending</span>
                        @endif
                        @if($conv->hasTag('paid'))
                            <span class="wa-conv-tag paid">Paid</span>
                        @endif
                        @if($conv->hasTag('important'))
                            <span class="wa-conv-tag important">Important</span>
                        @endif
                    </div>
                    <div class="wa-conv-preview">
                        @if($conv->latestMessage?->message_type === 'reaction')
                            Reacted {{ $conv->latestMessage->message }}
                        @else
                            {{ $conv->latestMessage?->message ?? 'No messages yet' }}@if($conv->latestMessage && count($conv->latestMessage->reactionEmojis())) {{ implode('', $conv->latestMessage->reactionEmojis()) }}@endif
                        @endif
                    </div>
                </div>
                <div class="wa-conv-meta">
                    <div class="wa-conv-time">{{ $conv->last_message_at?->diffForHumans(null, true) }}</div>
                    @if($conv->unread_count > 0)
                        <div class="wa-badge" title="{{ $conv->unread_count }} unread messages" aria-label="{{ $conv->unread_count }} unread messages">
                            {{ $conv->unread_count > 99 ? '99+' : $conv->unread_count }}
                        </div>
                    @endif
                </div>
            </a>
            @empty
            <div class="text-center px-4 py-5 text-muted">
                <i class="fab fa-whatsapp fa-3x mb-3 d-block" style="color:#d1d5db"></i>
                <p>No conversations yet</p>
                <small>Messages will appear here when customers contact you on WhatsApp</small>
            </div>
            @endforelse
        </div>

        {{ $conversations->links('pagination::simple-bootstrap-5') }}
    </div>

    <!-- Main: Empty state -->
    <div class="wa-main">
        <div class="wa-empty">
            <i class="fab fa-whatsapp d-block mb-3"></i>
            <h5>Select a conversation</h5>
            <p class="text-muted">Choose a conversation from the left to start chatting</p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
var currentStatus = @json($filter ?? 'all');
var inboxPollInFlight = false;
var INBOX_POLL_MS = 30000;

document.querySelectorAll('.wa-status-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.wa-status-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        currentStatus = this.dataset.status;
        loadConversations();
    });
});

function statusTagHtml(tags) {
    const list = Array.isArray(tags) ? tags : [];
    let html = '';
    if (list.includes('pending_payment')) {
        html += '<span class="wa-conv-tag pending">Pending</span>';
    }
    if (list.includes('paid')) {
        html += '<span class="wa-conv-tag paid">Paid</span>';
    }
    if (list.includes('important')) {
        html += '<span class="wa-conv-tag important">Important</span>';
    }
    return html;
}

var searchTimeout;
var inboxSearchInFlight = false;
document.getElementById('convSearch').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(performInboxSearch, 300);
});

function escapeHtml(text) {
    return String(text ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function highlightSearchSnippet(snippet, query) {
    const safe = escapeHtml(snippet || '');
    const q = String(query || '').trim();
    if (!q) return safe;
    const pattern = new RegExp(q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
    return safe.replace(pattern, (match) => `<mark>${match}</mark>`);
}

function renderConversationItem(c) {
    return `
        <a href="${c.url}" class="wa-conv-item">
            <div class="wa-conv-avatar">${(c.contact_name || c.phone_number).charAt(0).toUpperCase()}</div>
            <div class="wa-conv-info">
                <div class="wa-conv-name">${escapeHtml(c.contact_name || c.phone_number)}${statusTagHtml(c.tags)}</div>
                <div class="wa-conv-preview">${escapeHtml(c.last_message || 'No messages yet')}</div>
            </div>
            <div class="wa-conv-meta">
                <div class="wa-conv-time">${c.last_message_at || ''}</div>
                ${Number(c.unread_count) > 0 ? `<div class="wa-badge" title="${c.unread_count} unread messages" aria-label="${c.unread_count} unread messages">${Number(c.unread_count) > 99 ? '99+' : c.unread_count}</div>` : ''}
            </div>
        </a>
    `;
}

function renderMessageHit(hit, query) {
    if (!hit || !hit.url) return '';
    const name = hit.contact_name || hit.phone_number || '?';
    return `
        <a href="${hit.url}" class="wa-search-hit">
            <div class="wa-conv-avatar">${name.charAt(0).toUpperCase()}</div>
            <div class="wa-search-hit-body">
                <div class="wa-search-hit-name">${escapeHtml(hit.contact_name || hit.phone_number)}</div>
                <div class="wa-search-hit-snippet">${highlightSearchSnippet(hit.snippet, query)}</div>
            </div>
            <div class="wa-search-hit-time">${escapeHtml(hit.time || '')}</div>
        </a>
    `;
}

function performInboxSearch() {
    const search = document.getElementById('convSearch')?.value.trim() || '';
    if (search.length < 2) {
        loadConversations();
        return;
    }
    if (inboxSearchInFlight) return;
    inboxSearchInFlight = true;

    const params = new URLSearchParams({ q: search, status: currentStatus });
    fetch(`{{ route('whatsapp.inbox.search') }}?${params}`, {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
    })
        .then(async (r) => {
            if (!r.ok) throw new Error('Search failed');
            return r.json();
        })
        .then(data => {
            const list = document.getElementById('convList');
            if (!list) return;
            const chats = data.chats || [];
            const messages = data.messages || [];
            if (!chats.length && !messages.length) {
                list.innerHTML = `<div class="text-center py-5 text-muted"><i class="bi bi-search fa-3x mb-3 d-block" style="color:#d1d5db"></i><p>No chats or messages found</p></div>`;
                return;
            }
            let html = '';
            if (chats.length) {
                html += `<div class="wa-search-section-title">Chats</div>`;
                html += chats.map(renderConversationItem).join('');
            }
            if (messages.length) {
                html += `<div class="wa-search-section-title">Messages</div>`;
                html += messages.map(hit => renderMessageHit(hit, search)).join('');
            }
            list.innerHTML = html;
        })
        .catch(() => {
            const list = document.getElementById('convList');
            if (list) {
                list.innerHTML = `<div class="text-center py-5 text-muted"><p>Search failed. Please try again.</p></div>`;
            }
        })
        .finally(() => { inboxSearchInFlight = false; });
}

function loadConversations() {
    const search = document.getElementById('convSearch')?.value.trim() || '';
    if (search.length >= 2) {
        performInboxSearch();
        return;
    }
    if (inboxPollInFlight) return;
    inboxPollInFlight = true;

    const params = new URLSearchParams({ status: currentStatus });
    if (search) params.set('search', search);

    fetch(`{{ route('whatsapp.conversations.list') }}?${params}`, {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
    })
        .then(async (r) => {
            if (!r.ok) throw new Error('Load failed');
            return r.json();
        })
        .then(data => {
            const list = document.getElementById('convList');
            if (!list) return;
            if (!data.conversations.length) {
                list.innerHTML = `<div class="text-center py-5 text-muted"><i class="fab fa-whatsapp fa-3x mb-3 d-block" style="color:#d1d5db"></i><p>No conversations found</p></div>`;
                return;
            }
            list.innerHTML = data.conversations.map(c => renderConversationItem(c)).join('');
        })
        .catch(() => {})
        .finally(() => { inboxPollInFlight = false; });
}

function pollInboxConversations() {
    if (document.hidden) return;
    const search = document.getElementById('convSearch')?.value.trim() || '';
    if (search.length >= 2) {
        if (!inboxSearchInFlight) performInboxSearch();
        return;
    }
    loadConversations();
}

// Live refresh (pauses when tab is hidden)
if (window.inboxPollInterval) clearInterval(window.inboxPollInterval);
window.inboxPollInterval = setInterval(pollInboxConversations, INBOX_POLL_MS);
setTimeout(pollInboxConversations, 1500);

document.addEventListener('visibilitychange', () => {
    if (!document.hidden) pollInboxConversations();
});

// Horizontal scroll for status tabs using mouse wheel and drag
document.addEventListener('DOMContentLoaded', () => {
    const slider = document.querySelector('.wa-status-tabs');
    if (!slider) return;

    slider.addEventListener('wheel', (e) => {
        if (e.deltaY !== 0) {
            e.preventDefault();
            slider.scrollLeft += e.deltaY;
        }
    });

    let isDown = false;
    let startX;
    let scrollLeft;

    slider.addEventListener('mousedown', (e) => {
        isDown = true;
        startX = e.pageX - slider.offsetLeft;
        scrollLeft = slider.scrollLeft;
        slider.style.cursor = 'grabbing';
    });
    slider.addEventListener('mouseleave', () => {
        isDown = false;
        slider.style.cursor = 'pointer';
    });
    slider.addEventListener('mouseup', () => {
        isDown = false;
        slider.style.cursor = 'pointer';
    });
    slider.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        e.preventDefault();
        const x = e.pageX - slider.offsetLeft;
        const walk = (x - startX) * 2;
        slider.scrollLeft = scrollLeft - walk;
    });
});
</script>
@endpush
