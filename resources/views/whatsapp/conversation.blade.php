@extends('layouts.app')

@push('styles')
<style>
.wa-inbox-wrap { display: flex; height: calc(100vh - 70px); overflow: hidden; border-radius: 12px; }
.wa-sidebar { width: 320px; min-width: 260px; border-right: 1px solid var(--crm-border); display: flex; flex-direction: column; background: var(--crm-bg-card); }
.wa-sidebar-header { padding: 12px 16px; border-bottom: 1px solid var(--crm-border); }
.wa-conv-list { flex: 1; overflow-y: auto; }
.wa-conv-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; cursor: pointer; border-bottom: 1px solid var(--crm-border); transition: background .15s; text-decoration: none; color: inherit; }
.wa-conv-item:hover, .wa-conv-item.active { background: var(--crm-bg-soft); }
.wa-conv-avatar { width: 40px; height: 40px; border-radius: 50%; background: #25d366; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 16px; flex-shrink: 0; }
.wa-conv-info { flex: 1; min-width: 0; }
.wa-conv-name { font-weight: 600; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wa-conv-preview { font-size: 11px; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wa-conv-meta { flex-shrink: 0; margin-left: auto; display: flex; align-items: center; justify-content: flex-end; min-width: 24px; }
.wa-badge { background: #25d366; color: #fff; border-radius: 999px; min-width: 20px; height: 20px; padding: 0 6px; font-size: 11px; font-weight: 700; line-height: 1; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 1px 3px rgba(0,0,0,.18); }

/* Chat area */
.wa-chat-area { flex: 1; display: flex; flex-direction: column; background: #e5ddd5; transition: background .3s; }
.wa-chat-header { background: #075e54; color: #fff; padding: 12px 20px; display: flex; align-items: center; gap: 12px; transition: background .3s; }
.wa-chat-header-avatar { width: 40px; height: 40px; border-radius: 50%; background: #128c7e; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px; color: #fff; }
.wa-chat-status-badge {
    display: inline-block;
    font-size: 10px;
    font-weight: 700;
    line-height: 1;
    padding: 3px 8px;
    border-radius: 999px;
    margin-top: 4px;
    margin-right: 4px;
    background: rgba(255,255,255,.18);
    color: #fff;
}
.wa-chat-status-badges { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
.wa-chat-status-badge.pending { background: rgba(255, 193, 7, 0.28); }
.wa-chat-status-badge.paid { background: rgba(37, 211, 102, 0.28); }
.wa-chat-status-badge.important { background: rgba(220, 53, 69, 0.35); }
.wa-chat-status-badge.closed { background: rgba(255,255,255,.12); }
.wa-chat-status-badge.archived { background: rgba(255,255,255,.12); }
.wa-tag-menu {
    min-width: 260px;
    padding: 6px 0;
}
.wa-tag-menu .dropdown-header {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #8696a0;
    padding: 8px 16px 6px;
}
.wa-tag-menu-item {
    display: flex !important;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 10px 14px !important;
    white-space: nowrap;
}
.wa-tag-menu-item .wa-tag-icon {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 14px;
}
.wa-tag-menu-item .wa-tag-icon.pending { background: rgba(255, 193, 7, 0.18); color: #c79100; }
.wa-tag-menu-item .wa-tag-icon.paid { background: rgba(37, 211, 102, 0.16); color: #128c7e; }
.wa-tag-menu-item .wa-tag-icon.important { background: rgba(220, 53, 69, 0.12); color: #dc3545; }
.wa-tag-menu-item .wa-tag-label {
    flex: 1;
    min-width: 0;
    font-size: 14px;
    font-weight: 600;
    color: #111b21;
    text-align: left;
}
.wa-tag-menu-item .wa-tag-action {
    flex-shrink: 0;
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
    padding: 5px 10px;
    border-radius: 999px;
    background: #eef2f5;
    color: #54656f;
}
.wa-tag-menu-item.is-on .wa-tag-action {
    background: #e7f8ef;
    color: #128c7e;
}
.wa-tag-menu-item.is-on .wa-tag-action::before {
    content: none;
}
[data-theme="dark"] .wa-tag-menu-item .wa-tag-label { color: #e9edef; }
[data-theme="dark"] .wa-tag-menu-item .wa-tag-action { background: #2a3942; color: #aebac1; }
[data-theme="dark"] .wa-tag-menu-item.is-on .wa-tag-action { background: rgba(37, 211, 102, 0.16); color: #25d366; }
.wa-messages { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 8px; min-height: 0; -webkit-overflow-scrolling: touch; }
.wa-messages-wrap {
    position: relative;
    flex: 1;
    min-height: 0;
    display: flex;
}
.wa-messages-wrap .wa-messages { width: 100%; }
.wa-scroll-bottom-btn {
    position: absolute;
    right: 14px;
    bottom: 14px;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    border: none;
    background: #fff;
    color: #54656f;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.18);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    line-height: 1;
    z-index: 6;
    opacity: 0;
    visibility: hidden;
    transform: translateY(8px);
    transition: opacity .2s ease, visibility .2s ease, transform .2s ease;
}
.wa-scroll-bottom-btn.is-visible {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}
.wa-scroll-bottom-btn:active { transform: scale(0.95); }
.wa-scroll-bottom-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 999px;
    background: #25d366;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    line-height: 18px;
    text-align: center;
}
[data-theme="dark"] .wa-scroll-bottom-btn {
    background: #233138;
    color: #aebac1;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.45);
}
.wa-msg { max-width: 65%; padding: 8px 12px; border-radius: 8px; font-size: 14px; position: relative; word-break: break-word; transition: background .3s, color .3s; flex-shrink: 0; }
.wa-msg.incoming { background: #fff; color: #111b21; align-self: flex-start; border-radius: 0 8px 8px 8px; }
.wa-msg.outgoing { background: #dcf8c6; color: #111b21; align-self: flex-end; border-radius: 8px 0 8px 8px; }
.wa-msg.has-reactions { margin-bottom: 14px; }
.wa-msg-reactions {
    position: absolute;
    bottom: -10px;
    display: inline-flex;
    align-items: center;
    gap: 2px;
    background: #fff;
    border: 1px solid #e9edef;
    border-radius: 999px;
    padding: 1px 5px;
    font-size: 14px;
    line-height: 1.2;
    box-shadow: 0 1px 3px rgba(0,0,0,.12);
    z-index: 2;
}
.wa-msg.incoming .wa-msg-reactions { left: 8px; }
.wa-msg.outgoing .wa-msg-reactions { right: 8px; }
[data-theme="dark"] .wa-msg-reactions {
    background: #202c33;
    border-color: #3b4a54;
    color: #e9edef;
}
.wa-msg-reactions.is-clickable {
    cursor: pointer;
}
.wa-msg-reactions.is-clickable:hover { transform: scale(1.05); }
.wa-reaction-picker {
    display: none;
    position: fixed;
    z-index: 10060;
    align-items: center;
    gap: 4px;
    padding: 6px 8px;
    border-radius: 999px;
    background: #fff;
    border: 1px solid #e9edef;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.18);
    pointer-events: none;
    opacity: 0;
}
.wa-reaction-picker.is-visible {
    display: flex;
    pointer-events: auto;
    opacity: 1;
}
.wa-reaction-picker button {
    border: none;
    background: transparent;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    font-size: 22px;
    line-height: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}
.wa-reaction-picker button:hover { background: #f0f2f5; transform: scale(1.12); }
[data-theme="dark"] .wa-reaction-picker { background: #233138; border-color: #3b4a54; }
[data-theme="dark"] .wa-reaction-picker button:hover { background: #182229; }

/* Dark mode WhatsApp theme */
[data-theme="dark"] .wa-chat-area { background: #0b141a; }
[data-theme="dark"] .wa-chat-header { background: #202c33; color: #e9edef; }
[data-theme="dark"] .wa-msg.incoming { background: #202c33; color: #e9edef; }
[data-theme="dark"] .wa-msg.outgoing { background: #005c4b; color: #e9edef; }
[data-theme="dark"] .wa-date-divider span { background: rgba(32,44,51,.8); color: #8696a0; }
[data-theme="dark"] .wa-chat-header-avatar { background: #25d366; color: #fff; }
.wa-msg-time { font-size: 10px; color: #9ca3af; text-align: right; margin-top: 2px; }
.wa-msg-status { font-size: 10px; }
.wa-msg-status .bi-check2-all { color: #53bdeb; }
.wa-msg.is-revoked { background: rgba(255,255,255,.55) !important; color: #667781 !important; font-style: italic; }
.wa-msg.outgoing.is-revoked { background: rgba(220,248,198,.65) !important; }
[data-theme="dark"] .wa-msg.is-revoked { background: rgba(32,44,51,.85) !important; color: #8696a0 !important; }
[data-theme="dark"] .wa-msg.outgoing.is-revoked { background: rgba(0,92,75,.65) !important; }
.wa-msg-deleted { font-size: 13px; display: flex; align-items: center; gap: 4px; min-height: 20px; }
.wa-msg-link {
    color: #027eb5;
    text-decoration: underline;
    word-break: break-word;
    cursor: pointer;
}
.wa-msg.outgoing .wa-msg-link { color: #0563c1; }
[data-theme="dark"] .wa-msg-link { color: #53bdeb; }
[data-theme="dark"] .wa-msg.outgoing .wa-msg-link { color: #53bdeb; }
.wa-msg.has-reply { padding-top: 6px; }
.wa-msg-quote {
    border-left: 3px solid #53bdeb;
    background: rgba(0,0,0,.06);
    border-radius: 4px;
    padding: 4px 8px 4px 6px;
    margin-bottom: 6px;
    max-width: 100%;
}
.wa-msg-quote.is-clickable {
    cursor: pointer;
    position: relative;
    z-index: 2;
    -webkit-tap-highlight-color: rgba(37, 211, 102, 0.25);
    touch-action: manipulation;
    user-select: none;
}
.wa-msg-quote.is-clickable:hover { background: rgba(0,0,0,.1); }
.wa-msg-quote.is-clickable:active { background: rgba(0,0,0,.14); }
button.wa-msg-quote {
    display: block;
    width: 100%;
    border: none;
    text-align: left;
    font: inherit;
    color: inherit;
    padding: 4px 8px 4px 6px;
    appearance: none;
    -webkit-appearance: none;
}
button.wa-msg-quote:focus-visible { outline: 2px solid #25d366; outline-offset: 1px; }
.wa-msg.outgoing .wa-msg-quote { border-left-color: #128c7e; background: rgba(0,0,0,.05); }
.wa-msg-quote.quote-you { border-left-color: #128c7e; }
.wa-msg-quote.quote-them { border-left-color: #53bdeb; }
.wa-msg-quote-author { font-size: 11px; font-weight: 700; color: #128c7e; line-height: 1.3; }
.wa-msg.incoming .wa-msg-quote-author { color: #53bdeb; }
.wa-msg-quote-text {
    font-size: 12px;
    color: #667781;
    line-height: 1.35;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: min(240px, 70vw);
}
.wa-msg.wa-msg-highlight { outline: 2px solid #25d366; outline-offset: 1px; border-radius: 8px; }
.wa-msg.is-search-active { outline: 2px solid #ffc107; outline-offset: 1px; border-radius: 8px; }
.wa-chat-header.is-searching .wa-chat-header-main { display: none !important; }
.wa-chat-header-search {
    display: none;
    align-items: center;
    gap: 8px;
    flex: 1;
    min-width: 0;
}
.wa-chat-header.is-searching .wa-chat-header-search { display: flex; }
.wa-chat-header-search input {
    flex: 1;
    min-width: 0;
    border: none;
    border-radius: 8px;
    padding: 8px 12px;
    background: rgba(255,255,255,.15);
    color: #fff;
}
.wa-chat-header-search input::placeholder { color: rgba(255,255,255,.7); }
.wa-chat-search-count { font-size: 12px; color: rgba(255,255,255,.85); min-width: 44px; text-align: center; }
.wa-chat-search-nav {
    border: none;
    background: transparent;
    color: #fff;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
}
.wa-chat-search-nav:disabled { opacity: 0.35; }
.wa-chat-search-nav:not(:disabled):hover { background: rgba(255,255,255,.12); }
.wa-msg.is-pinned-msg { box-shadow: inset 3px 0 0 #25d366; }
.wa-pinned-bar {
    display: none;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    background: #f0f2f5;
    border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    cursor: pointer;
    flex-shrink: 0;
}
.wa-pinned-bar.is-visible { display: flex; }
.wa-pinned-bar:active { background: #e9edef; }
.wa-pinned-bar-icon {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: rgba(37, 211, 102, 0.14);
    color: #128c7e;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 14px;
}
.wa-pinned-bar-body { flex: 1; min-width: 0; }
.wa-pinned-bar-label {
    font-size: 11px;
    font-weight: 700;
    color: #25d366;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}
.wa-pinned-bar-text {
    font-size: 13px;
    color: #111b21;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.wa-pinned-bar-unpin {
    border: none;
    background: transparent;
    color: #667781;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.wa-pinned-bar-unpin:hover { background: rgba(0, 0, 0, 0.06); }
[data-theme="dark"] .wa-pinned-bar { background: #111b21; border-bottom-color: rgba(255,255,255,.08); }
[data-theme="dark"] .wa-pinned-bar-text { color: #e9edef; }
[data-theme="dark"] .wa-pinned-bar-unpin { color: #aebac1; }
[data-theme="dark"] .wa-msg-context-menu button.menu-pin { color: #e9edef; }
[data-theme="dark"] .wa-msg-quote { background: rgba(255,255,255,.08); }
[data-theme="dark"] .wa-msg-quote-text { color: #8696a0; }
.wa-input-wrap { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.wa-input-row { display: flex; align-items: flex-end; gap: 10px; width: 100%; }
.wa-reply-bar {
    display: flex;
    align-items: stretch;
    background: var(--crm-bg-soft);
    border-left: 3px solid #25d366;
    border-radius: 8px 8px 0 0;
    margin-bottom: 8px;
    overflow: hidden;
}
.wa-reply-bar-inner { display: flex; align-items: center; width: 100%; padding: 8px 10px; gap: 8px; }
.wa-reply-bar-content { flex: 1; min-width: 0; }
.wa-reply-bar-author { font-size: 12px; font-weight: 700; color: #25d366; }
.wa-reply-bar-text { font-size: 12px; color: #667781; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wa-reply-bar-close {
    border: none;
    background: transparent;
    color: #667781;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.wa-reply-bar-close:hover { background: rgba(0,0,0,.06); color: #111b21; }
.wa-paste-bar {
    display: flex;
    align-items: stretch;
    background: var(--crm-bg-soft);
    border-left: 3px solid #53bdeb;
    border-radius: 8px 8px 0 0;
    margin-bottom: 8px;
    overflow: hidden;
}
.wa-paste-bar-inner {
    display: flex;
    align-items: center;
    width: 100%;
    padding: 8px 10px;
    gap: 10px;
}
.wa-paste-items {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
    min-width: 0;
    overflow-x: auto;
}
.wa-paste-item {
    position: relative;
    flex-shrink: 0;
    border-radius: 8px;
    overflow: hidden;
    background: #000;
}
.wa-paste-item img.is-loading,
.wa-paste-item video.is-loading {
    background: linear-gradient(110deg, #e9edef 8%, #f5f6f6 18%, #e9edef 33%);
    background-size: 200% 100%;
}
.wa-paste-item img,
.wa-paste-item video {
    display: block;
    width: 72px;
    height: 72px;
    object-fit: cover;
}
.wa-paste-item.is-single img,
.wa-paste-item.is-single video {
    width: 120px;
    height: 120px;
}
.wa-paste-file-icon {
    width: 72px;
    height: 72px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--crm-bg-card);
    color: #64748b;
    font-size: 28px;
}
.wa-paste-item.is-single .wa-paste-file-icon {
    width: 120px;
    height: 120px;
}
.wa-paste-item-name {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    padding: 2px 4px;
    font-size: 9px;
    color: #fff;
    background: rgba(0,0,0,.55);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.wa-paste-bar-close {
    border: none;
    background: transparent;
    color: #667781;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.wa-paste-bar-close:hover { background: rgba(0,0,0,.06); color: #111b21; }
.wa-paste-mark-btn {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    border: 0;
    background: rgba(7, 94, 84, 0.88);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 4px 0;
    line-height: 1.1;
}
.wa-mark-editor {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 21000;
    background: #0b141a;
    color: #fff;
    flex-direction: column;
}
.wa-mark-editor.is-open { display: flex; }
.wa-mark-editor__top,
.wa-mark-editor__bottom {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    background: #202c33;
    flex-shrink: 0;
}
.wa-mark-editor__top { justify-content: space-between; }
.wa-mark-editor__title { font-size: 14px; font-weight: 700; }
.wa-mark-editor__stage {
    flex: 1;
    min-height: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 10px;
    overflow: hidden;
    touch-action: none;
}
.wa-mark-editor__canvas {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    border-radius: 8px;
    background: #111;
    cursor: crosshair;
    touch-action: none;
}
.wa-mark-editor__bottom { justify-content: space-between; flex-wrap: wrap; gap: 10px; }
.wa-mark-editor__tools { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.wa-mark-editor__colors { display: flex; align-items: center; gap: 6px; }
.wa-mark-color {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 2px solid transparent;
    padding: 0;
}
.wa-mark-color.is-active { border-color: #fff; box-shadow: 0 0 0 2px rgba(255,255,255,.25); }
.wa-mark-editor__btn {
    border: 0;
    border-radius: 999px;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 700;
    background: #2a3942;
    color: #e9edef;
}
.wa-mark-editor__btn.primary { background: #25d366; color: #054d2a; }
.wa-mark-editor__btn.ghost { background: transparent; color: #aebac1; }
.wa-msg-context-menu button.menu-reply { color: #111b21; }
.wa-msg-context-menu button.menu-mark { color: #111b21; }
[data-theme="dark"] .wa-msg-context-menu button.menu-reply { color: #e9edef; }
[data-theme="dark"] .wa-msg-context-menu button.menu-mark { color: #e9edef; }
.wa-msg-actions {
    position: absolute;
    top: 2px;
    z-index: 3;
    opacity: 0;
    pointer-events: none;
    transition: opacity .15s;
}
.wa-msg.incoming .wa-msg-actions { right: 2px; }
.wa-msg.outgoing .wa-msg-actions { left: 2px; }
.wa-msg:hover .wa-msg-actions,
.wa-msg-actions.is-open { opacity: 1; pointer-events: auto; }
.wa-msg-menu-btn {
    width: 22px;
    height: 22px;
    border: none;
    border-radius: 4px;
    background: rgba(0,0,0,.08);
    color: #54656f;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    line-height: 1;
    padding: 0;
}
.wa-msg-menu-btn:hover { background: rgba(0,0,0,.14); }
[data-theme="dark"] .wa-msg-menu-btn { background: rgba(255,255,255,.12); color: #aebac1; }
.wa-msg-context-menu {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    z-index: 10050;
    min-width: 140px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,.22);
    overflow: hidden;
    pointer-events: none;
    opacity: 0;
}
.wa-msg-context-menu.is-visible {
    display: block;
    pointer-events: auto;
    opacity: 1;
}
.wa-msg-context-menu button {
    display: block;
    width: 100%;
    border: none;
    background: transparent;
    text-align: left;
    padding: 12px 16px;
    font-size: 14px;
    color: #ea0038;
    white-space: nowrap;
}
.wa-msg-context-menu button:hover { background: #f5f6f6; }
[data-theme="dark"] .wa-msg-context-menu { background: #233138; box-shadow: 0 4px 20px rgba(0,0,0,.45); }
[data-theme="dark"] .wa-msg-context-menu button { color: #ff6b81; }
[data-theme="dark"] .wa-msg-context-menu button:hover { background: #182229; }
.wa-input-area { background: var(--crm-bg-card); padding: 12px 16px; display: flex; align-items: flex-end; gap: 10px; border-top: 1px solid var(--crm-border); flex-shrink: 0; }
/* Visually hidden but clickable — display:none breaks file/camera pickers on some iOS/Android browsers */
.wa-hidden-file-input {
    position: fixed;
    left: 0;
    bottom: 0;
    width: 1px;
    height: 1px;
    opacity: 0;
    overflow: hidden;
    z-index: -1;
}
.wa-input-area textarea { flex: 1; border-radius: 20px; border: 1px solid var(--crm-border); padding: 10px 16px; resize: none; max-height: 120px; background: var(--crm-bg-input); color: var(--crm-text-body); }
.wa-send-btn { width: 44px; height: 44px; border-radius: 50%; background: #25d366; border: none; color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.wa-send-btn:hover { background: #128c7e; }
.wa-voice-btn { width: 44px; height: 44px; border-radius: 50%; background: #25d366; border: none; color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0; padding: 0; touch-action: none; user-select: none; -webkit-user-select: none; }
.wa-voice-btn:hover { background: #128c7e; }
.wa-voice-btn.is-recording { background: #ea0038; animation: wa-voice-pulse 1.2s ease-in-out infinite; }
@keyframes wa-voice-pulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(234, 0, 56, 0.35); } 50% { box-shadow: 0 0 0 8px rgba(234, 0, 56, 0); } }
.wa-voice-recording-bar {
    display: none;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    margin-bottom: 8px;
    border-radius: 12px;
    background: rgba(234, 0, 56, 0.08);
    border: 1px solid rgba(234, 0, 56, 0.18);
}
.wa-voice-recording-bar.is-active { display: flex; }
.wa-voice-recording-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #ea0038;
    flex-shrink: 0;
    animation: wa-voice-blink 1s step-end infinite;
}
@keyframes wa-voice-blink { 50% { opacity: 0.25; } }
.wa-voice-recording-time { font-size: 14px; font-weight: 600; color: #ea0038; min-width: 42px; font-variant-numeric: tabular-nums; }
.wa-voice-recording-hint { flex: 1; font-size: 12px; color: #6b7280; min-width: 0; }
.wa-voice-recording-send {
    border: none;
    background: #ea0038;
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    padding: 6px 14px;
    border-radius: 999px;
    flex-shrink: 0;
}
.wa-voice-recording-cancel {
    border: none;
    background: transparent;
    color: #ea0038;
    font-size: 13px;
    font-weight: 600;
    padding: 4px 8px;
}
.wa-upload-bar {
    display: none;
    padding: 10px 12px;
    margin-bottom: 8px;
    border-radius: 12px;
    background: rgba(37, 211, 102, 0.1);
    border: 1px solid rgba(37, 211, 102, 0.25);
}
.wa-upload-bar.is-active { display: block; }
.wa-upload-bar-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}
.wa-upload-label { flex: 1; font-size: 13px; font-weight: 600; color: #128c7e; min-width: 0; }
.wa-upload-count { font-size: 12px; color: #6b7280; font-variant-numeric: tabular-nums; }
.wa-upload-track {
    height: 4px;
    border-radius: 999px;
    background: rgba(0, 0, 0, 0.08);
    overflow: hidden;
}
.wa-upload-fill {
    height: 100%;
    width: 0%;
    border-radius: 999px;
    background: linear-gradient(90deg, #25d366, #128c7e);
    transition: width 0.2s ease;
}
.wa-msg.is-pending { opacity: 0.92; }
.wa-pending-media { position: relative; }
.wa-pending-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.35);
    color: #fff;
}
.wa-pending-file {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 14px;
    font-size: 13px;
    color: #667781;
}
.wa-msg.is-pending.is-failed .wa-pending-overlay { background: rgba(220, 53, 69, 0.45); }
.wa-input-row .wa-send-btn.d-none,
.wa-input-row .wa-voice-btn.d-none { display: none !important; }
.wa-template-btn { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; padding: 0; }
.wa-attach-btn { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; padding: 0; }
.wa-date-divider { text-align: center; margin: 8px 0; }
.wa-date-divider span { background: rgba(255,255,255,.7); padding: 3px 12px; border-radius: 8px; font-size: 11px; color: #6b7280; }

/* Custom Scrollbars */
.wa-conv-list::-webkit-scrollbar, .wa-messages::-webkit-scrollbar { width: 6px; }
.wa-conv-list::-webkit-scrollbar-track, .wa-messages::-webkit-scrollbar-track { background: transparent; }
.wa-conv-list::-webkit-scrollbar-thumb, .wa-messages::-webkit-scrollbar-thumb { background-color: rgba(0, 0, 0, 0.2); border-radius: 10px; }
[data-theme="dark"] .wa-conv-list::-webkit-scrollbar-thumb, [data-theme="dark"] .wa-messages::-webkit-scrollbar-thumb { background-color: rgba(255, 255, 255, 0.2); }
.wa-right-panel { width: 300px; border-left: 1px solid var(--crm-border); background: var(--crm-bg-card); overflow-y: auto; display: flex; flex-direction: column; }
.wa-panel-section { padding: 16px; border-bottom: 1px solid var(--crm-border); }
.wa-panel-section h6 { font-size: 12px; text-transform: uppercase; letter-spacing: .5px; color: #9ca3af; margin-bottom: 10px; }
.wa-info-row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 6px; }
.wa-info-label { color: #6b7280; }
.wa-info-value { font-weight: 500; }
.msg-media { position: relative; line-height: 0; }
.msg-media img,
.msg-media video {
    display: block;
    width: auto;
    height: auto;
    max-width: min(280px, 78vw);
    max-height: min(420px, 58vh);
    object-fit: contain;
    object-position: center;
    border-radius: 8px;
    cursor: pointer;
    -webkit-tap-highlight-color: rgba(37, 211, 102, 0.25);
    touch-action: manipulation;
    user-select: none;
    -webkit-user-drag: none;
    pointer-events: auto;
}
.wa-msg.has-media {
    max-width: min(300px, 82vw);
    padding: 3px;
}
.wa-msg.has-media .wa-msg-time {
    padding: 2px 8px 4px;
    line-height: normal;
}
.wa-pending-media img {
    display: block;
    width: auto;
    height: auto;
    max-width: min(280px, 78vw);
    max-height: min(420px, 58vh);
    object-fit: contain;
    border-radius: 8px;
}
.msg-media video {
    display: block;
    width: auto;
    height: auto;
    max-width: min(320px, 78vw);
    max-height: min(420px, 58vh);
    object-fit: contain;
    background: #000;
}
.msg-media audio { display: block; width: min(300px, 70vw); max-width: 100%; }
.msg-media .doc-link { display: flex; align-items: center; gap: 8px; background: var(--crm-bg-soft); padding: 8px 12px; border-radius: 8px; text-decoration: none; color: var(--crm-text-body); font-size: 13px; }

/* WhatsApp-style location card */
.wa-msg.has-location,
.wa-msg.has-contacts {
    max-width: min(280px, 85%);
    padding: 4px;
    overflow: visible;
}
.wa-msg.has-location .wa-msg-time,
.wa-msg.has-contacts .wa-msg-time {
    padding: 2px 8px 4px;
    margin-top: 0;
}
.wa-location-card {
    display: block;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    border-radius: 8px;
    overflow: hidden;
    text-decoration: none !important;
    color: #111b21 !important;
    background: #ffffff;
    border: 0;
}
.wa-location-map {
    display: block;
    width: 100%;
    height: 130px;
    min-height: 130px;
    background: #dbeafe center/cover no-repeat;
    position: relative;
}
.wa-location-map img {
    display: block;
    width: 100%;
    height: 130px;
    object-fit: cover;
    border: 0;
}
.wa-location-body { padding: 8px 10px 10px; background: #fff; }
.wa-location-name {
    font-weight: 600;
    font-size: 13px;
    line-height: 1.3;
    color: #111b21;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.wa-location-address {
    font-size: 11px;
    color: #667781;
    margin-top: 2px;
    line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.wa-location-open { font-size: 11px; color: #027eb5; margin-top: 6px; font-weight: 600; }
[data-theme="dark"] .wa-location-card,
[data-theme="dark"] .wa-location-body { background: #1f2c34; }
[data-theme="dark"] .wa-location-name { color: #e9edef; }
[data-theme="dark"] .wa-location-address { color: #8696a0; }
[data-theme="dark"] .wa-location-open { color: #53bdeb; }
.wa-attach-menu .dropdown-item { font-size: 13px; }

/* WhatsApp-style shared contact card */
.wa-contact-card {
    width: 100%;
    max-width: 100%;
    min-height: 64px;
    box-sizing: border-box;
    border-radius: 8px;
    overflow: hidden;
    background: #ffffff;
    border: 0;
}
.wa-contact-card + .wa-contact-card { margin-top: 8px; }
.wa-contact-head {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
}
.wa-contact-avatar {
    width: 44px;
    height: 44px;
    min-width: 44px;
    border-radius: 50%;
    background: #25d366;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 18px;
    flex-shrink: 0;
}
.wa-contact-name { font-weight: 600; font-size: 14px; line-height: 1.25; color: #111b21; }
.wa-contact-phone { font-size: 12px; color: #667781; margin-top: 2px; }
[data-theme="dark"] .wa-contact-card { background: #1f2c34; }
[data-theme="dark"] .wa-contact-name { color: #e9edef; }
[data-theme="dark"] .wa-contact-phone { color: #8696a0; }

/* WhatsApp-style media lightbox (must sit on body above mobile nav/chatbot) */
.wa-media-viewer {
    position: fixed !important;
    inset: 0 !important;
    width: 100vw;
    height: 100dvh;
    z-index: 20050 !important;
    display: none;
    align-items: center;
    justify-content: center;
    background: rgba(11, 20, 26, 0.96);
    overscroll-behavior: none;
    -webkit-overflow-scrolling: touch;
}
.wa-media-viewer.is-open { display: flex !important; }
.wa-media-viewer__top {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: calc(56px + env(safe-area-inset-top, 0px));
    padding-top: env(safe-area-inset-top, 0px);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-left: 16px;
    padding-right: 8px;
    color: #e9edef;
    background: linear-gradient(180deg, rgba(0,0,0,.55), transparent);
    z-index: 2;
}
.wa-media-viewer__title {
    font-size: 15px;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: calc(100% - 180px);
}
.wa-media-viewer__actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
}
.wa-media-viewer__mark {
    border: 0;
    border-radius: 999px;
    padding: 8px 12px;
    background: #25d366;
    color: #054d2a;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
    touch-action: manipulation;
}
.wa-media-viewer__close {
    width: 44px;
    height: 44px;
    border: 0;
    border-radius: 50%;
    background: rgba(255,255,255,.12);
    color: #e9edef;
    font-size: 22px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    touch-action: manipulation;
    -webkit-tap-highlight-color: transparent;
}
.wa-media-viewer__close:active { background: rgba(255,255,255,.22); }
.wa-media-viewer__body {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: calc(64px + env(safe-area-inset-top, 0px)) 12px calc(24px + env(safe-area-inset-bottom, 0px));
    box-sizing: border-box;
    z-index: 1;
}
.wa-media-viewer__body img,
.wa-media-viewer__body video {
    max-width: 100%;
    max-height: calc(100dvh - 110px);
    width: auto;
    height: auto;
    object-fit: contain;
    border-radius: 4px;
    box-shadow: 0 8px 32px rgba(0,0,0,.35);
    background: #000;
    touch-action: pinch-zoom;
}
.wa-media-viewer__body video { width: min(100%, 960px); }

/* Hide mobile chrome while media viewer is open */
body.wa-media-open .crm-mobile-nav,
body.wa-media-open .chatbot-float-btn,
body.wa-media-open .chatbot-card {
    visibility: hidden !important;
    pointer-events: none !important;
}

@media (max-width: 767.98px) {
    .wa-chat-area {
        min-width: 0;
        min-height: 0;
    }

    .wa-chat-header {
        padding: 10px 12px;
        gap: 10px;
    }

    .wa-chat-header-avatar {
        width: 36px;
        height: 36px;
        font-size: 14px;
        flex-shrink: 0;
    }

    .wa-chat-header .flex-grow-1 {
        min-width: 0;
    }

    .wa-chat-header .fw-bold,
    .wa-chat-header .small {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .wa-messages {
        padding: 8px 6px;
    }

    .wa-msg {
        max-width: 88%;
        font-size: 13px;
    }

    .wa-msg.has-media {
        max-width: min(300px, 88vw);
        padding: 3px;
    }

    .wa-msg.has-location,
    .wa-msg.has-contacts {
        max-width: min(270px, 82vw);
        width: auto;
        padding: 4px;
        box-sizing: border-box;
        flex-shrink: 0;
    }

    .wa-location-map,
    .wa-location-map img {
        height: 118px;
        min-height: 118px;
    }

    .wa-contact-card {
        width: 100%;
        max-width: 100%;
        min-height: 64px;
    }

    .wa-input-area textarea {
        padding: 10px 14px;
    }
}
</style>
@endpush

@section('content')
<div class="wa-inbox-wrap">
    <!-- Sidebar -->
    <div class="wa-sidebar d-none d-lg-flex flex-column">
        <div class="wa-sidebar-header">
            <div class="d-flex align-items-center justify-content-between">
                <a href="{{ route('whatsapp.inbox') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold small">Conversations</span>
                <span></span>
            </div>
        </div>
        <div class="wa-conv-list" id="sidebarConvList">
            <div class="text-center py-3 text-muted small">Loading...</div>
        </div>
    </div>

    <!-- Chat Area -->
    <div class="wa-chat-area">
        <!-- Header -->
        <div class="wa-chat-header" id="chatHeader">
            <a href="{{ route('whatsapp.inbox') }}" class="text-white d-lg-none me-2 wa-chat-header-main">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div class="wa-chat-header-search" id="chatSearchBar">
                <button type="button" class="wa-chat-search-nav" onclick="closeChatSearch()" aria-label="Close search">
                    <i class="bi bi-arrow-left"></i>
                </button>
                <input type="search" id="chatSearchInput" placeholder="Search in chat..." autocomplete="off" enterkeyhint="search">
                <span class="wa-chat-search-count" id="chatSearchCount"></span>
                <button type="button" class="wa-chat-search-nav" id="chatSearchPrev" onclick="jumpChatSearchMatch(-1)" aria-label="Previous match" disabled>
                    <i class="bi bi-chevron-up"></i>
                </button>
                <button type="button" class="wa-chat-search-nav" id="chatSearchNext" onclick="jumpChatSearchMatch(1)" aria-label="Next match" disabled>
                    <i class="bi bi-chevron-down"></i>
                </button>
            </div>
            <div class="wa-chat-header-main d-flex align-items-center gap-12 flex-grow-1 min-w-0" style="gap:12px;">
            <div class="wa-chat-header-avatar">
                {{ strtoupper(substr($conversation->contact_name ?? $conversation->phone_number, 0, 1)) }}
            </div>
            <div class="flex-grow-1 min-w-0">
                <div class="fw-bold">{{ $conversation->contact_name ?? $conversation->phone_number }}</div>
                <div class="small opacity-75">
                    {{ $conversation->phone_number }}
                </div>
                <div id="chatStatusBadges" class="wa-chat-status-badges">
                    @if($conversation->hasTag('pending_payment'))
                        <span class="wa-chat-status-badge pending" data-tag="pending_payment">Pending (Payment)</span>
                    @endif
                    @if($conversation->hasTag('paid'))
                        <span class="wa-chat-status-badge paid" data-tag="paid">Paid</span>
                    @endif
                    @if($conversation->hasTag('important'))
                        <span class="wa-chat-status-badge important" data-tag="important">Important</span>
                    @endif
                    @if($conversation->status === 'closed')
                        <span class="wa-chat-status-badge closed" data-status="closed">Closed</span>
                    @elseif($conversation->status === 'archived')
                        <span class="wa-chat-status-badge archived" data-status="archived">Archived</span>
                    @endif
                </div>
            </div>
            <div class="d-flex gap-2 align-items-center ms-auto">
                <button type="button" class="btn text-white p-1 border-0" style="background:transparent; box-shadow:none;" onclick="openChatSearch()" aria-label="Search messages" title="Search messages">
                    <i class="bi bi-search fs-5"></i>
                </button>
                @if($whatsappAutoAiEnabled)
                    <div class="d-none d-sm-flex align-items-center gap-2 rounded px-2 py-1 bg-white bg-opacity-10" title="Auto AI reply after the delay if no agent replies from CRM">
                        <span class="small text-white-50 text-nowrap">Auto AI</span>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input m-0" type="checkbox" id="waAiToggle" role="switch"
                                {{ $conversation->aiReplyEnabled() ? 'checked' : '' }}
                                onchange="toggleWaAiReply(this.checked)">
                        </div>
                    </div>
                @endif
                <div class="dropdown">
                    <button class="btn text-white p-1 border-0" style="background:transparent; box-shadow:none;" data-bs-toggle="dropdown">
                        <i class="bi bi-three-dots-vertical fs-5"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end wa-tag-menu">
                        <li><h6 class="dropdown-header">Tags</h6></li>
                        <li>
                            <button type="button"
                                class="dropdown-item wa-tag-menu-item {{ $conversation->hasTag('pending_payment') ? 'is-on' : '' }}"
                                id="tagMenuPending"
                                onclick="toggleConversationTag('pending_payment')">
                                <span class="wa-tag-icon pending"><i class="bi bi-credit-card"></i></span>
                                <span class="wa-tag-label">Pending (Payment)</span>
                                <span class="wa-tag-action">{{ $conversation->hasTag('pending_payment') ? 'Remove' : 'Add' }}</span>
                            </button>
                        </li>
                        <li>
                            <button type="button"
                                class="dropdown-item wa-tag-menu-item {{ $conversation->hasTag('paid') ? 'is-on' : '' }}"
                                id="tagMenuPaid"
                                onclick="toggleConversationTag('paid')">
                                <span class="wa-tag-icon paid"><i class="bi bi-check2-circle"></i></span>
                                <span class="wa-tag-label">Paid</span>
                                <span class="wa-tag-action">{{ $conversation->hasTag('paid') ? 'Remove' : 'Add' }}</span>
                            </button>
                        </li>
                        <li>
                            <button type="button"
                                class="dropdown-item wa-tag-menu-item {{ $conversation->hasTag('important') ? 'is-on' : '' }}"
                                id="tagMenuImportant"
                                onclick="toggleConversationTag('important')">
                                <span class="wa-tag-icon important"><i class="bi bi-star-fill"></i></span>
                                <span class="wa-tag-label">Important</span>
                                <span class="wa-tag-action">{{ $conversation->hasTag('important') ? 'Remove' : 'Add' }}</span>
                            </button>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button class="dropdown-item" onclick="updateStatus('closed')">
                                <i class="bi bi-x-circle me-2"></i>Close Conversation
                            </button>
                        </li>
                        <li>
                            <button class="dropdown-item" onclick="updateStatus('archived')">
                                <i class="bi bi-archive me-2"></i>Archive
                            </button>
                        </li>
                        @if(in_array($conversation->status, ['closed', 'archived'], true))
                        <li>
                            <button class="dropdown-item" onclick="updateStatus('open')">
                                <i class="bi bi-arrow-counterclockwise me-2"></i>Reopen
                            </button>
                        </li>
                        @endif
                    </ul>
                </div>
            </div>
            </div>
        </div>

        <div id="pinnedMessageBar" class="wa-pinned-bar" role="button" tabindex="0" aria-label="Jump to pinned message" onclick="jumpToPinnedMessage(event)">
            <span class="wa-pinned-bar-icon" id="pinnedMessageIcon"><i class="bi bi-pin-angle-fill"></i></span>
            <div class="wa-pinned-bar-body">
                <div class="wa-pinned-bar-label">Pinned message</div>
                <div class="wa-pinned-bar-text" id="pinnedMessageText"></div>
            </div>
            <button type="button" class="wa-pinned-bar-unpin" onclick="unpinMessage(event)" aria-label="Unpin message" title="Unpin">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <!-- Messages -->
        <div class="wa-messages-wrap">
            <div class="wa-messages" id="messagesContainer">
            @php
                $lastDate = null;
                $chatTimezone = config('services.whatsapp.display_timezone', 'Asia/Kolkata');
            @endphp
            @foreach($chatMessages as $msg)
                @php
                    $displayTime = $msg->created_at?->copy()->timezone($chatTimezone);
                    $msgDate = $displayTime?->format('d M Y');
                    $reactionEmojis = $msg->reactionEmojis();
                    $replyCtx = $msg->replyContext();
                @endphp
                @if($msgDate !== $lastDate)
                    <div class="wa-date-divider"><span>{{ $msgDate }}</span></div>
                    @php $lastDate = $msgDate; @endphp
                @endif
                <div class="wa-msg {{ $msg->direction }} {{ $msg->message_type === 'revoked' ? 'is-revoked' : '' }}{{ $replyCtx ? ' has-reply' : '' }} {{ count($reactionEmojis) ? 'has-reactions' : '' }} {{ in_array($msg->message_type, ['image', 'video'], true) ? 'has-media' : '' }} {{ $msg->message_type === 'location' ? 'has-location' : '' }} {{ $msg->message_type === 'contacts' ? 'has-contacts' : '' }}{{ ($conversation->pinnedMessageId() === $msg->id) ? ' is-pinned-msg' : '' }}{{ (int) ($highlightMessageId ?? 0) === $msg->id ? ' is-search-active wa-msg-highlight' : '' }}" id="msg-{{ $msg->id }}" data-message-id="{{ $msg->id }}" data-meta-message-id="{{ $msg->meta_message_id }}" data-direction="{{ $msg->direction }}" data-message-type="{{ $msg->message_type }}">
                    @if($msg->message_type === 'revoked')
                        <div class="wa-msg-deleted"><i class="bi bi-slash-circle"></i>This message was deleted</div>
                    @else
                        <div class="wa-msg-actions">
                            <button type="button" class="wa-msg-menu-btn" onclick="toggleMsgMenu(event, {{ $msg->id }})" aria-label="Message options">
                                <i class="bi bi-chevron-down"></i>
                            </button>
                        </div>
                    @if($replyCtx)
                        @php
                            $quoteClickable = !empty($replyCtx['message_id']) || !empty($replyCtx['meta_message_id']);
                        @endphp
                        @if($quoteClickable)
                            <button type="button" class="wa-msg-quote {{ ($replyCtx['direction'] ?? '') === 'outgoing' ? 'quote-you' : 'quote-them' }} is-clickable"
                                data-quote-target-id="{{ $replyCtx['message_id'] ?? '' }}"
                                data-quote-target-meta="{{ $replyCtx['meta_message_id'] ?? '' }}"
                                aria-label="Jump to original message">
                                <div class="wa-msg-quote-author">{{ $replyCtx['author'] ?? 'Message' }}</div>
                                <div class="wa-msg-quote-text">{{ $replyCtx['text'] ?? 'Message' }}</div>
                            </button>
                        @else
                            <div class="wa-msg-quote {{ ($replyCtx['direction'] ?? '') === 'outgoing' ? 'quote-you' : 'quote-them' }}">
                                <div class="wa-msg-quote-author">{{ $replyCtx['author'] ?? 'Message' }}</div>
                                <div class="wa-msg-quote-text">{{ $replyCtx['text'] ?? 'Message' }}</div>
                            </div>
                        @endif
                    @endif
                    @if($msg->message_type === 'image' && $msg->media_url)
                        <div class="msg-media"><img src="{{ $msg->displayMediaUrl() }}" alt="Image" data-wa-media="image"></div>
                        @if($msg->message && $msg->message !== '[Image]')<div class="mt-1">{!! $msg->messageHtml() !!}</div>@endif
                    @elseif($msg->message_type === 'video' && $msg->media_url)
                        <div class="msg-media"><video src="{{ $msg->displayMediaUrl() }}" preload="metadata" playsinline data-wa-media="video"></video></div>
                        @if($msg->message && $msg->message !== '[Video]')<div class="mt-1">{!! $msg->messageHtml() !!}</div>@endif
                    @elseif($msg->message_type === 'audio' && $msg->media_url)
                        <div class="msg-media"><audio src="{{ $msg->displayMediaUrl() }}" controls preload="metadata"></audio></div>
                    @elseif($msg->message_type === 'document' && $msg->media_url)
                        <div class="msg-media">
                            <a href="{{ $msg->displayMediaUrl() }}" target="_blank" class="doc-link">
                                <i class="bi bi-file-earmark me-1"></i>{{ $msg->message }}
                            </a>
                        </div>
                    @elseif($msg->message_type === 'location')
                        @php $loc = $msg->locationData(); @endphp
                        @if($loc)
                            <a class="wa-location-card" href="{{ $loc['maps_url'] }}" target="_blank" rel="noopener">
                                <div class="wa-location-map">
                                    <img src="{{ $loc['preview_url'] }}" alt="Map" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
                                </div>
                                <div class="wa-location-body">
                                    <div class="wa-location-name">{{ $loc['name'] ?: 'Location' }}</div>
                                    @if($loc['address'])
                                        <div class="wa-location-address">{{ $loc['address'] }}</div>
                                    @else
                                        <div class="wa-location-address">{{ number_format($loc['latitude'], 5) }}, {{ number_format($loc['longitude'], 5) }}</div>
                                    @endif
                                    <div class="wa-location-open"><i class="bi bi-geo-alt me-1"></i>Open in Maps</div>
                                </div>
                            </a>
                        @else
                            <div>{!! $msg->messageHtml() !!}</div>
                        @endif
                    @elseif($msg->message_type === 'contacts')
                        @foreach($msg->contactsData() as $contact)
                            <div class="wa-contact-card">
                                <div class="wa-contact-head">
                                    <div class="wa-contact-avatar">{{ $contact['initial'] }}</div>
                                    <div class="min-w-0">
                                        <div class="wa-contact-name">{{ $contact['name'] }}</div>
                                        @if(!empty($contact['phones'][0]['display']))
                                            <div class="wa-contact-phone">{{ $contact['phones'][0]['display'] }}</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div>{!! $msg->messageHtml() !!}</div>
                    @endif
                    @endif
                    @if($msg->message_type !== 'revoked' && count($reactionEmojis))
                        <div class="wa-msg-reactions is-clickable" data-reaction-badge data-message-id="{{ $msg->id }}" onclick="openReactionPickerForMessage(event, {{ $msg->id }})" title="Change reaction">{{ implode('', $reactionEmojis) }}</div>
                    @endif
                    <div class="wa-msg-time d-flex align-items-center justify-content-end gap-1">
                        {{ $displayTime?->format('h:i A') }}
                        @if($msg->direction === 'outgoing')
                            <span class="wa-msg-status">
                                @if($msg->status === 'read')
                                    <i class="bi bi-check2-all"></i>
                                @elseif($msg->status === 'delivered')
                                    <i class="bi bi-check2-all text-muted"></i>
                                @elseif($msg->status === 'sent')
                                    <i class="bi bi-check2 text-muted"></i>
                                @elseif($msg->status === 'failed')
                                    <i class="bi bi-exclamation-circle text-danger"></i>
                                @endif
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
            </div>
            <button type="button" class="wa-scroll-bottom-btn" id="scrollToBottomBtn" onclick="scrollChatToBottom(true)" aria-label="Scroll to latest messages" title="Latest messages">
                <i class="bi bi-chevron-down"></i>
                <span class="wa-scroll-bottom-badge d-none" id="scrollToBottomBadge" aria-hidden="true"></span>
            </button>
        </div>

        <!-- Input Area -->
        <div class="wa-input-area">
            <input type="file" id="cameraInput" class="wa-hidden-file-input" accept="image/*" capture="environment"
                onchange="sendMedia(this)">
            <input type="file" id="photoInput" class="wa-hidden-file-input" multiple accept="image/*"
                onchange="sendMedia(this)">
            <input type="file" id="mediaInput" class="wa-hidden-file-input"
                accept="video/mp4,video/3gpp,video/quicktime,.mp4,.3gp,.mov,audio/mpeg,audio/mp4,audio/aac,audio/amr,audio/ogg,.mp3,.m4a,.opus,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv"
                onchange="sendMedia(this)">
            <div class="dropdown">
                <button type="button" class="btn btn-outline-secondary wa-attach-btn" data-bs-toggle="dropdown" aria-expanded="false"
                    title="Attach" aria-label="Attach">
                    <i class="bi bi-paperclip"></i>
                </button>
                <ul class="dropdown-menu wa-attach-menu">
                    <li>
                        <button type="button" class="dropdown-item" onclick="openCameraPicker()">
                            <i class="bi bi-camera me-2 text-success"></i>Camera
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item" onclick="openPhotoPicker()">
                            <i class="bi bi-images me-2 text-primary"></i>Photos
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item" onclick="openMediaPicker()">
                            <i class="bi bi-file-earmark me-2 text-secondary"></i>Video / File
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item" onclick="pasteFromClipboard()">
                            <i class="bi bi-clipboard me-2 text-info"></i>Paste Image / File
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item" onclick="sendShopLocation()">
                            <i class="bi bi-shop me-2 text-success"></i>Shop Location
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item" onclick="sendMyLocation()">
                            <i class="bi bi-geo-alt me-2 text-danger"></i>My Current Location
                        </button>
                    </li>
                </ul>
            </div>
            <div class="wa-input-wrap">
                <div id="pastePreviewBar" class="wa-paste-bar d-none">
                    <div class="wa-paste-bar-inner">
                        <div id="pastePreviewItems" class="wa-paste-items"></div>
                        <button type="button" class="wa-paste-bar-close" onclick="clearPastePreview()" aria-label="Remove pasted media">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                <div id="replyPreviewBar" class="wa-reply-bar d-none">
                    <div class="wa-reply-bar-inner">
                        <div class="wa-reply-bar-content">
                            <div class="wa-reply-bar-author" id="replyPreviewAuthor"></div>
                            <div class="wa-reply-bar-text" id="replyPreviewText"></div>
                        </div>
                        <button type="button" class="wa-reply-bar-close" onclick="cancelReply()" aria-label="Cancel reply">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                <div id="voiceRecordingBar" class="wa-voice-recording-bar" aria-live="polite">
                    <span class="wa-voice-recording-dot" aria-hidden="true"></span>
                    <span class="wa-voice-recording-time" id="voiceRecordingTime">0:00</span>
                    <span class="wa-voice-recording-hint" id="voiceRecordingHint">Tap Send when done · Cancel to discard</span>
                    <button type="button" class="wa-voice-recording-send" id="voiceRecordingSendBtn" onclick="stopVoiceRecording(true)">Send</button>
                    <button type="button" class="wa-voice-recording-cancel" id="voiceRecordingCancelBtn" onclick="cancelVoiceRecording()">Cancel</button>
                </div>
                <div id="waUploadBar" class="wa-upload-bar" aria-live="polite" aria-atomic="true">
                    <div class="wa-upload-bar-row">
                        <span class="spinner-border spinner-border-sm text-success" role="status" aria-hidden="true"></span>
                        <span class="wa-upload-label" id="waUploadLabel">Sending...</span>
                        <span class="wa-upload-count" id="waUploadCount"></span>
                    </div>
                    <div class="wa-upload-track"><div class="wa-upload-fill" id="waUploadFill"></div></div>
                </div>
                <div class="wa-input-row">
                    <textarea id="messageInput" class="form-control" rows="1" placeholder="Type a message..." onkeydown="handleEnter(event)"></textarea>
                    <button type="button" class="wa-voice-btn" id="voiceRecordBtn" aria-label="Record voice message" title="Tap to record voice message">
                        <i class="bi bi-mic-fill"></i>
                    </button>
                    <button class="wa-send-btn d-none" id="sendMessageBtn" onclick="sendMessage()">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>


</div>

{{-- WhatsApp-style full-screen media viewer (moved to body by JS for mobile) --}}
<div id="waMsgContextMenu" class="wa-msg-context-menu" role="menu" aria-hidden="true">
    <button type="button" role="menuitem" class="menu-reply" onclick="startReplyFromMenu()">Reply</button>
    <button type="button" role="menuitem" class="menu-react" onclick="openReactionPickerFromMenu()">React</button>
    <button type="button" role="menuitem" id="menuPinMessage" class="menu-pin" onclick="togglePinFromMenu()">Pin</button>
    <button type="button" role="menuitem" id="menuMarkResend" class="menu-mark" onclick="markAndResendFromMenu()" style="display:none">
        Mark &amp; Resend
    </button>
    <button type="button" role="menuitem" class="text-danger" onclick="confirmDeleteMessage()">Delete</button>
</div>
<div id="waReactionPicker" class="wa-reaction-picker" role="menu" aria-hidden="true" aria-label="Choose reaction">
    <button type="button" onclick="sendReactionEmoji('👍')" aria-label="Thumbs up">👍</button>
    <button type="button" onclick="sendReactionEmoji('❤️')" aria-label="Heart">❤️</button>
    <button type="button" onclick="sendReactionEmoji('😂')" aria-label="Laugh">😂</button>
    <button type="button" onclick="sendReactionEmoji('😮')" aria-label="Wow">😮</button>
    <button type="button" onclick="sendReactionEmoji('😢')" aria-label="Sad">😢</button>
    <button type="button" onclick="sendReactionEmoji('🙏')" aria-label="Thanks">🙏</button>
</div>
<div id="waMediaViewer" class="wa-media-viewer" aria-hidden="true">
    <div class="wa-media-viewer__top">
        <div class="wa-media-viewer__title" id="waMediaViewerTitle">Media</div>
        <div class="wa-media-viewer__actions">
            <button type="button" class="wa-media-viewer__mark d-none" id="waMediaViewerMarkBtn" aria-label="Mark and resend">
                Mark &amp; Resend
            </button>
            <button type="button" class="wa-media-viewer__close" aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    </div>
    <div class="wa-media-viewer__body" id="waMediaViewerBody"></div>
</div>

{{-- Draw / mark on photo before sending --}}
<div id="waMarkEditor" class="wa-mark-editor" aria-hidden="true">
    <div class="wa-mark-editor__top">
        <button type="button" class="wa-mark-editor__btn ghost" onclick="closeMarkEditor()">Cancel</button>
        <div class="wa-mark-editor__title">Mark photo</div>
        <button type="button" class="wa-mark-editor__btn primary" onclick="saveMarkEditor()">Done</button>
    </div>
    <div class="wa-mark-editor__stage">
        <canvas id="waMarkCanvas" class="wa-mark-editor__canvas"></canvas>
    </div>
    <div class="wa-mark-editor__bottom">
        <div class="wa-mark-editor__tools">
            <div class="wa-mark-editor__colors" id="waMarkColors">
                <button type="button" class="wa-mark-color is-active" data-color="#25d366" style="background:#25d366" aria-label="Green"></button>
                <button type="button" class="wa-mark-color" data-color="#ff3b30" style="background:#ff3b30" aria-label="Red"></button>
                <button type="button" class="wa-mark-color" data-color="#ffcc00" style="background:#ffcc00" aria-label="Yellow"></button>
                <button type="button" class="wa-mark-color" data-color="#ffffff" style="background:#ffffff" aria-label="White"></button>
            </div>
            <button type="button" class="wa-mark-editor__btn" onclick="undoMarkStroke()">Undo</button>
            <button type="button" class="wa-mark-editor__btn" onclick="clearMarkStrokes()">Clear</button>
        </div>
        <div class="small text-white-50">Draw to mark the product</div>
    </div>
</div>

<!-- Create Lead Modal -->
<div class="modal fade" id="createLeadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Lead from Conversation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="leadName" value="{{ $conversation->contact_name }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" id="leadEmail">
                </div>
                <div class="mb-3">
                    <label class="form-label">Lead Source</label>
                    <select class="form-select" id="leadSourceId">
                        <option value="">Select source</option>
                        @foreach($sources as $src)
                        <option value="{{ $src->id }}">{{ $src->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Stage</label>
                    <select class="form-select" id="leadStageId">
                        <option value="">Select stage</option>
                        @foreach($stages as $stage)
                        <option value="{{ $stage->id }}">{{ $stage->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="createLead()">Create Lead</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Followup Modal -->
@if($conversation->lead_id)
<div class="modal fade" id="addFollowupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Schedule Followup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="fuTitle" placeholder="e.g. Follow up on pricing">
                </div>
                <div class="mb-3">
                    <label class="form-label">Due Date <span class="text-danger">*</span></label>
                    <input type="datetime-local" class="form-control" id="fuDueDate">
                </div>
                <div class="mb-3">
                    <label class="form-label">Assign To</label>
                    <select class="form-select" id="fuAssignedUser">
                        <option value="">Select agent</option>
                        @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="addFollowup()">Schedule</button>
            </div>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script src="{{ crm_asset('js/opus-media-recorder/OpusMediaRecorder.umd.js') }}"></script>
<script>
var OPUS_RECORDER_BASE = @json(crm_asset('js/opus-media-recorder'));
var CONV_ID = {{ $conversation->id }};
var SEND_URL = '{{ route('whatsapp.conversation.send', $conversation) }}';
var SEND_MEDIA_URL = '{{ route('whatsapp.conversation.send_media', $conversation) }}';
var SEND_LOCATION_URL = '{{ route('whatsapp.conversation.send_location', $conversation) }}';
var MESSAGES_URL = '{{ route('whatsapp.conversation.messages', $conversation) }}';
var DELETE_MESSAGE_URL = '{{ route('whatsapp.conversation.delete_message', ['conversation' => $conversation, 'message' => '__MSG__']) }}';
var REACT_MESSAGE_URL = '{{ route('whatsapp.conversation.react', ['conversation' => $conversation, 'message' => '__MSG__']) }}';
var SHOP_LOCATION = {
    latitude: 21.207227,
    longitude: 72.78275598,
    name: @json(\App\Models\Setting::getValue('company_name', 'Shrishti Trip')),
    address: @json(\App\Models\Setting::getValue('company_address', 'Shrishti Trip — tour and travel enquiries'))
};
var ASSIGN_URL = '{{ route('whatsapp.conversation.assign', $conversation) }}';
var STATUS_URL = '{{ route('whatsapp.conversation.status', $conversation) }}';
var TAG_URL = '{{ route('whatsapp.conversation.tag', $conversation) }}';
var PIN_MESSAGE_URL = '{{ route('whatsapp.conversation.pin', ['conversation' => $conversation, 'message' => '__MSG__']) }}';
var UNPIN_MESSAGE_URL = '{{ route('whatsapp.conversation.unpin', $conversation) }}';
var conversationTags = @json($conversation->activeTags());
var conversationStatus = @json($conversation->status);
var pinnedMessageState = @json($conversation->pinnedMessagePreview());
var AI_REPLY_URL = '{{ route('whatsapp.conversation.ai_reply', $conversation) }}';
var CREATE_LEAD_URL = '{{ route('whatsapp.conversation.create_lead', $conversation) }}';
var FOLLOWUP_URL = '{{ route('whatsapp.followups.store') }}';
var CONV_CONTACT_NAME = @json($conversation->contact_name ?? $conversation->phone_number);
var lastMessageId = {{ $lastChatMessageId ?? 0 }};
var HIGHLIGHT_MESSAGE_ID = {{ (int) ($highlightMessageId ?? 0) }};
var CHAT_SEARCH_URL = '{{ route('whatsapp.conversation.message_search', $conversation) }}';
var chatSearchMatches = [];
var chatSearchIndex = -1;
var chatSearchTimer = null;
var replyToMessageId = null;
var replyToPreview = null;
var activeContextMessageId = null;
var activeReactionPickerMessageId = null;
var reactionPickerIgnoreCloseUntil = 0;
var pendingPasteFiles = [];
var pastePreviewUrls = [];
var isSendingMessage = false;
var mediaBatchUploadActive = false;
var historyJumpLoading = false;
var uploadProgressState = { total: 0, done: 0, filePercent: 0 };
var voiceRecorderState = {
    mediaRecorder: null,
    stream: null,
    chunks: [],
    mimeType: 'audio/ogg',
    fileExt: 'ogg',
    isVoiceNote: true,
    startedAt: 0,
    timerId: null,
    holdMode: false,
    cancelled: false,
    startX: 0,
    desktopActive: false,
    isStarting: false,
};
var unreadWhileScrolledUp = 0;
var SCROLL_BOTTOM_THRESHOLD = 96;

function isChatNearBottom(el) {
    if (!el) return true;
    return (el.scrollHeight - el.scrollTop - el.clientHeight) < SCROLL_BOTTOM_THRESHOLD;
}

function updateScrollToBottomBtn() {
    const container = document.getElementById('messagesContainer');
    const btn = document.getElementById('scrollToBottomBtn');
    const badge = document.getElementById('scrollToBottomBadge');
    if (!container || !btn) return;

    const nearBottom = isChatNearBottom(container);
    btn.classList.toggle('is-visible', !nearBottom);

    if (!badge) return;
    if (nearBottom || unreadWhileScrolledUp <= 0) {
        badge.classList.add('d-none');
        badge.textContent = '';
        return;
    }

    badge.classList.remove('d-none');
    badge.textContent = unreadWhileScrolledUp > 9 ? '9+' : String(unreadWhileScrolledUp);
}

function scrollChatToBottom(smooth) {
    const container = document.getElementById('messagesContainer');
    if (!container) return;

    if (smooth && typeof container.scrollTo === 'function') {
        container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
    } else {
        container.scrollTop = container.scrollHeight;
    }

    unreadWhileScrolledUp = 0;
    window.setTimeout(updateScrollToBottomBtn, smooth ? 280 : 0);
}

function initScrollToBottom() {
    const container = document.getElementById('messagesContainer');
    if (!container) return;

    container.addEventListener('scroll', () => {
        if (isChatNearBottom(container)) {
            unreadWhileScrolledUp = 0;
        }
        updateScrollToBottomBtn();
    }, { passive: true });

    updateScrollToBottomBtn();
}

// Scroll to bottom on load (skip when opening a specific message from inbox search)
var container = document.getElementById('messagesContainer');
if (container) {
    if (!HIGHLIGHT_MESSAGE_ID) {
        container.scrollTop = container.scrollHeight;
    }
    initScrollToBottom();
}

// Auto-resize textarea
var textarea = document.getElementById('messageInput');
textarea.addEventListener('input', function() {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    updateSendVoiceToggle();
});

textarea.addEventListener('paste', handlePasteInChat, true);

function extractFilesFromClipboard(clipboard) {
    const files = [];
    if (!clipboard) return files;

    if (clipboard.items && clipboard.items.length) {
        for (let i = 0; i < clipboard.items.length; i++) {
            const item = clipboard.items[i];
            if (item.kind === 'file' || (item.type && (item.type.startsWith('image/') || item.type.startsWith('video/')))) {
                const file = item.getAsFile();
                if (file) files.push(file);
            }
        }
        if (files.length) return dedupePastedFiles(files);
    }

    if (clipboard.files && clipboard.files.length) {
        files.push(...Array.from(clipboard.files));
    }

    return dedupePastedFiles(files);
}

function pickClipboardMediaType(types) {
    const preferred = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'image/gif', 'image/heic', 'image/heif'];
    for (const type of preferred) {
        if (types.includes(type)) return type;
    }
    return types.find(type => type.startsWith('image/') || type.startsWith('video/') || type.startsWith('application/')) || null;
}

function dedupePastedFiles(files) {
    const seen = new Set();
    return files.filter(file => {
        const key = `${file.name}|${file.size}|${file.type}|${file.lastModified}`;
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
    });
}

async function readFilesFromClipboardApi() {
    if (!navigator.clipboard || typeof navigator.clipboard.read !== 'function') {
        return [];
    }

    const items = await navigator.clipboard.read();
    const files = [];

    for (const item of items) {
        const mediaType = pickClipboardMediaType(item.types);
        if (!mediaType) continue;
        try {
            const blob = await item.getType(mediaType);
            if (!blob || !blob.size) continue;
            const ext = (mediaType.split('/')[1] || 'bin').replace('jpeg', 'jpg');
            files.push(new File([blob], `pasted-${Date.now()}.${files.length + 1}.${ext}`, {
                type: blob.type || mediaType,
                lastModified: Date.now(),
            }));
        } catch (e) {}
    }

    return dedupePastedFiles(files);
}

function applyPastedFiles(rawFiles) {
    const uniqueFiles = dedupePastedFiles(rawFiles);
    if (!uniqueFiles.length) return false;

    const normalized = uniqueFiles.map((file, index) => {
        const name = defaultPasteFileName(file, index);
        if (file.name === name) return file;
        return new File([file], name, {
            type: file.type || 'application/octet-stream',
            lastModified: file.lastModified || Date.now(),
        });
    });

    const allImages = normalized.every(isImageFile);
    if (normalized.length > 1 && !allImages) {
        showToast('Paste multiple images together, or one video/file at a time.', 'warning');
        return false;
    }

    pendingPasteFiles = normalized;
    renderPastePreview();
    textarea.focus();
    return true;
}

async function pasteFromClipboard() {
    try {
        const apiFiles = await readFilesFromClipboardApi();
        if (applyPastedFiles(apiFiles)) return;
    } catch (e) {}

    showToast('Copy an image first, then tap Paste. Or use Photos / File attach.', 'warning');
    textarea.focus();
}

async function handlePasteInChat(event) {
    let pastedFiles = extractFilesFromClipboard(event.clipboardData);

    if (!pastedFiles.length) {
        try {
            pastedFiles = await readFilesFromClipboardApi();
        } catch (e) {}
    }

    if (!pastedFiles.length) return;

    event.preventDefault();
    applyPastedFiles(pastedFiles);
}

function handleEnter(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

function isImageFile(file) {
    const lowerName = (file.name || '').toLowerCase();
    return (file.type || '').startsWith('image/') || /\.(jpe?g|png|webp|gif|heic|heif|bmp)$/i.test(lowerName);
}

function isVideoFile(file) {
    const lowerName = (file.name || '').toLowerCase();
    return (file.type || '').startsWith('video/') || /\.(mp4|3gp|mov|webm)$/i.test(lowerName);
}

function defaultPasteFileName(file, index) {
    if (file.name && file.name.trim()) return file.name;
    const ext = (file.type || '').split('/')[1] || 'bin';
    return `pasted-${Date.now()}-${index + 1}.${ext.replace('jpeg', 'jpg')}`;
}

async function buildPreviewThumb(file) {
    if (!isImageFile(file)) return null;
    try {
        if (typeof createImageBitmap === 'function') {
            let bitmap;
            try {
                bitmap = await createImageBitmap(file, {
                    resizeWidth: 160,
                    resizeHeight: 160,
                    resizeQuality: 'low',
                });
            } catch (e) {
                bitmap = await createImageBitmap(file);
            }
            const max = 160;
            const scale = Math.min(1, max / Math.max(bitmap.width, bitmap.height, 1));
            const w = Math.max(1, Math.round(bitmap.width * scale));
            const h = Math.max(1, Math.round(bitmap.height * scale));
            const canvas = document.createElement('canvas');
            canvas.width = w;
            canvas.height = h;
            canvas.getContext('2d', { alpha: false }).drawImage(bitmap, 0, 0, w, h);
            bitmap.close?.();
            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.72));
            return blob ? URL.createObjectURL(blob) : URL.createObjectURL(file);
        }
    } catch (e) { /* fall through */ }
    return URL.createObjectURL(file);
}

function schedulePreviewThumb(imgEl, file) {
    imgEl.classList.add('is-loading');
    buildPreviewThumb(file).then((url) => {
        if (!url || !imgEl.isConnected) return;
        pastePreviewUrls.push(url);
        imgEl.src = url;
        imgEl.classList.remove('is-loading');
    }).catch(() => {
        if (!imgEl.isConnected) return;
        const url = URL.createObjectURL(file);
        pastePreviewUrls.push(url);
        imgEl.src = url;
        imgEl.classList.remove('is-loading');
    });
}

function revokePastePreviewUrls() {
    pastePreviewUrls.forEach(url => {
        try { URL.revokeObjectURL(url); } catch (e) {}
    });
    pastePreviewUrls = [];
}

function renderPastePreview() {
    const bar = document.getElementById('pastePreviewBar');
    const itemsEl = document.getElementById('pastePreviewItems');
    if (!bar || !itemsEl) return;

    revokePastePreviewUrls();
    itemsEl.innerHTML = '';

    if (!pendingPasteFiles.length) {
        bar.classList.add('d-none');
        textarea.placeholder = 'Type a message...';
        updateSendVoiceToggle();
        return;
    }

    bar.classList.remove('d-none');
    textarea.placeholder = pendingPasteFiles.length > 1 ? 'Add a caption (last photo only)...' : 'Add a caption...';

    pendingPasteFiles.forEach((file, index) => {
        const wrap = document.createElement('div');
        wrap.className = `wa-paste-item${pendingPasteFiles.length === 1 ? ' is-single' : ''}`;

        if (isImageFile(file)) {
            const img = document.createElement('img');
            img.alt = file.name || 'Pasted image';
            img.loading = 'lazy';
            img.decoding = 'async';
            wrap.appendChild(img);
            schedulePreviewThumb(img, file);

            const markBtn = document.createElement('button');
            markBtn.type = 'button';
            markBtn.className = 'wa-paste-mark-btn';
            markBtn.textContent = 'Mark';
            markBtn.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                openMarkEditor(index);
            };
            wrap.appendChild(markBtn);
        } else if (isVideoFile(file)) {
            const url = URL.createObjectURL(file);
            pastePreviewUrls.push(url);
            const video = document.createElement('video');
            video.src = url;
            video.muted = true;
            video.playsInline = true;
            video.preload = 'metadata';
            wrap.appendChild(video);
        } else {
            const icon = document.createElement('div');
            icon.className = 'wa-paste-file-icon';
            icon.innerHTML = '<i class="bi bi-file-earmark"></i>';
            wrap.appendChild(icon);
            const name = document.createElement('div');
            name.className = 'wa-paste-item-name';
            name.textContent = file.name || 'File';
            wrap.appendChild(name);
        }

        itemsEl.appendChild(wrap);
    });

    updateSendVoiceToggle();
}

function clearPastePreview() {
    pendingPasteFiles = [];
    renderPastePreview();
}

/* ---- Mark / draw on photo before send ---- */
var markEditorIndex = -1;
var markEditorColor = '#25d366';
var markEditorDrawing = false;
var markEditorStrokes = [];
var markEditorCurrentStroke = null;
var markEditorImage = null;
var markEditorBase = null;

function ensureMarkEditorOnBody() {
    const editor = document.getElementById('waMarkEditor');
    if (editor && editor.parentElement !== document.body) {
        document.body.appendChild(editor);
    }
    return document.getElementById('waMarkEditor');
}

function openMarkEditor(index) {
    const file = pendingPasteFiles[index];
    if (!file || !isImageFile(file)) {
        showToast('Only photos can be marked', 'warning');
        return;
    }

    const editor = ensureMarkEditorOnBody();
    const canvas = document.getElementById('waMarkCanvas');
    if (!editor || !canvas) return;

    markEditorIndex = index;
    markEditorStrokes = [];
    markEditorCurrentStroke = null;
    markEditorDrawing = false;

    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
        URL.revokeObjectURL(url);
        markEditorImage = img;

        // Keep full original resolution for export quality.
        // Only shrink the on-screen display with CSS.
        const w = Math.max(1, img.naturalWidth);
        const h = Math.max(1, img.naturalHeight);
        canvas.width = w;
        canvas.height = h;

        const maxW = Math.max(200, window.innerWidth - 24);
        const maxH = Math.max(200, window.innerHeight - 160);
        const displayScale = Math.min(maxW / w, maxH / h, 1);
        canvas.style.width = Math.round(w * displayScale) + 'px';
        canvas.style.height = Math.round(h * displayScale) + 'px';

        markEditorBase = document.createElement('canvas');
        markEditorBase.width = w;
        markEditorBase.height = h;
        const baseCtx = markEditorBase.getContext('2d');
        baseCtx.imageSmoothingEnabled = true;
        baseCtx.imageSmoothingQuality = 'high';
        baseCtx.drawImage(img, 0, 0, w, h);

        redrawMarkCanvas();
        editor.classList.add('is-open');
        editor.setAttribute('aria-hidden', 'false');
        document.body.classList.add('wa-media-open');
        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';
    };
    img.onerror = () => {
        URL.revokeObjectURL(url);
        showToast('Could not open photo for marking', 'danger');
    };
    img.src = url;
}

function closeMarkEditor() {
    const editor = document.getElementById('waMarkEditor');
    if (editor) {
        editor.classList.remove('is-open');
        editor.setAttribute('aria-hidden', 'true');
    }
    markEditorIndex = -1;
    markEditorImage = null;
    markEditorBase = null;
    markEditorStrokes = [];
    markEditorCurrentStroke = null;
    markEditorDrawing = false;
    document.body.classList.remove('wa-media-open');
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
}

function redrawMarkCanvas() {
    const canvas = document.getElementById('waMarkCanvas');
    if (!canvas || !markEditorBase) return;
    const ctx = canvas.getContext('2d');
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(markEditorBase, 0, 0);

    const strokes = markEditorCurrentStroke
        ? markEditorStrokes.concat([markEditorCurrentStroke])
        : markEditorStrokes;

    strokes.forEach(stroke => {
        if (!stroke.points || stroke.points.length < 2) return;
        ctx.strokeStyle = stroke.color;
        ctx.lineWidth = stroke.width;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.beginPath();
        ctx.moveTo(stroke.points[0].x, stroke.points[0].y);
        for (let i = 1; i < stroke.points.length; i++) {
            ctx.lineTo(stroke.points[i].x, stroke.points[i].y);
        }
        ctx.stroke();
    });
}

function canvasPointFromEvent(event, canvas) {
    const rect = canvas.getBoundingClientRect();
    const source = event.touches && event.touches[0]
        ? event.touches[0]
        : (event.changedTouches && event.changedTouches[0] ? event.changedTouches[0] : event);
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    return {
        x: (source.clientX - rect.left) * scaleX,
        y: (source.clientY - rect.top) * scaleY,
    };
}

function startMarkStroke(event) {
    const canvas = document.getElementById('waMarkCanvas');
    if (!canvas || markEditorIndex < 0) return;
    event.preventDefault();
    markEditorDrawing = true;
    const point = canvasPointFromEvent(event, canvas);
    // Stroke width scales with real photo size so marks look similar on large images.
    const width = Math.max(6, Math.round(Math.min(canvas.width, canvas.height) / 120));
    markEditorCurrentStroke = {
        color: markEditorColor,
        width,
        points: [point],
    };
}

function moveMarkStroke(event) {
    if (!markEditorDrawing || !markEditorCurrentStroke) return;
    const canvas = document.getElementById('waMarkCanvas');
    if (!canvas) return;
    event.preventDefault();
    markEditorCurrentStroke.points.push(canvasPointFromEvent(event, canvas));
    redrawMarkCanvas();
}

function endMarkStroke(event) {
    if (!markEditorDrawing) return;
    if (event) event.preventDefault();
    markEditorDrawing = false;
    if (markEditorCurrentStroke && markEditorCurrentStroke.points.length > 1) {
        markEditorStrokes.push(markEditorCurrentStroke);
    }
    markEditorCurrentStroke = null;
    redrawMarkCanvas();
}

function undoMarkStroke() {
    markEditorStrokes.pop();
    redrawMarkCanvas();
}

function clearMarkStrokes() {
    markEditorStrokes = [];
    markEditorCurrentStroke = null;
    redrawMarkCanvas();
}

function saveMarkEditor() {
    const canvas = document.getElementById('waMarkCanvas');
    if (!canvas || markEditorIndex < 0) {
        closeMarkEditor();
        return;
    }

    redrawMarkCanvas();
    // Export at full canvas resolution with high JPEG quality.
    canvas.toBlob((blob) => {
        if (!blob) {
            showToast('Could not save marked photo', 'danger');
            return;
        }
        const old = pendingPasteFiles[markEditorIndex];
        const baseName = (old && old.name ? old.name.replace(/\.[^.]+$/, '') : 'marked-photo');
        pendingPasteFiles[markEditorIndex] = new File([blob], `${baseName}-marked.jpg`, {
            type: 'image/jpeg',
            lastModified: Date.now(),
        });
        closeMarkEditor();
        renderPastePreview();
        showToast('Photo marked. Tap Send when ready.', 'success');
    }, 'image/jpeg', 0.98);
}

(function initMarkEditorEvents() {
    const canvas = document.getElementById('waMarkCanvas');
    if (!canvas) return;

    canvas.addEventListener('mousedown', startMarkStroke);
    canvas.addEventListener('mousemove', moveMarkStroke);
    window.addEventListener('mouseup', endMarkStroke);
    canvas.addEventListener('mouseleave', endMarkStroke);

    canvas.addEventListener('touchstart', startMarkStroke, { passive: false });
    canvas.addEventListener('touchmove', moveMarkStroke, { passive: false });
    canvas.addEventListener('touchend', endMarkStroke, { passive: false });
    canvas.addEventListener('touchcancel', endMarkStroke, { passive: false });

    document.getElementById('waMarkColors')?.querySelectorAll('.wa-mark-color').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.wa-mark-color').forEach(b => b.classList.remove('is-active'));
            btn.classList.add('is-active');
            markEditorColor = btn.dataset.color || '#25d366';
        });
    });
})();

function appendPendingTextMessage(text, tempId) {
    const chat = document.getElementById('messagesContainer');
    if (!chat) return;
    const div = document.createElement('div');
    div.className = 'wa-msg outgoing is-pending';
    div.id = tempId;
    div.innerHTML = `<div>${linkifyText(text)}</div><div class="wa-msg-time">Sending... <i class="bi bi-clock text-muted"></i></div>`;
    chat.appendChild(div);
    scrollChatToBottom(false);
}

async function sendMessage() {
    if (isSendingMessage) return;

    if (pendingPasteFiles.length) {
        isSendingMessage = true;
        const filesCopy = [...pendingPasteFiles];
        const caption = textarea.value.trim();
        clearPastePreview();
        textarea.value = '';
        textarea.style.height = 'auto';
        updateSendVoiceToggle();
        try {
            await sendPreparedFiles(filesCopy, caption, {
                attachButton: document.querySelector('.wa-attach-btn'),
            });
        } finally {
            isSendingMessage = false;
        }
        return;
    }

    const msg = textarea.value.trim();
    if (!msg) return;

    isSendingMessage = true;
    textarea.value = '';
    textarea.style.height = 'auto';
    updateSendVoiceToggle();
    setSendButtonLoading(true);

    const payload = { message: msg };
    if (replyToMessageId) payload.reply_to_message_id = replyToMessageId;
    const tempId = `pending-text-${Date.now()}`;
    appendPendingTextMessage(msg, tempId);

    try {
        const r = await fetch(SEND_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            body: JSON.stringify(payload)
        });
        const data = await r.json();
        if (data.success) {
            removePendingMessage(tempId);
            appendMessage(data.message);
            cancelReply();
            scheduleChatPoll(800);
            scheduleSidebarRefresh();
        } else {
            markPendingMessageFailed(tempId);
            showToast('Failed to send message', 'danger');
        }
    } catch (e) {
        markPendingMessageFailed(tempId);
        showToast('Failed to send message', 'danger');
    } finally {
        isSendingMessage = false;
        setSendButtonLoading(false);
    }
}

function sendTemplate(templateName) {
    fetch(SEND_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        body: JSON.stringify({ template_name: templateName })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) appendMessage(data.message);
        else showToast('Failed to send template', 'danger');
    });
}

function updateSendVoiceToggle() {
    const hasText = (textarea.value || '').trim().length > 0;
    const hasPaste = pendingPasteFiles.length > 0;
    const isRecording = !!(voiceRecorderState.mediaRecorder && voiceRecorderState.mediaRecorder.state === 'recording');
    const showSend = hasText || hasPaste;
    const sendBtn = document.getElementById('sendMessageBtn');
    const voiceBtn = document.getElementById('voiceRecordBtn');
    if (sendBtn) sendBtn.classList.toggle('d-none', !showSend || isRecording);
    // Keep the mic/stop button visible while recording so user can tap stop to send.
    if (voiceBtn) voiceBtn.classList.toggle('d-none', showSend && !isRecording);
}

function isIOSDevice() {
    return /iPad|iPhone|iPod/i.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
}

function isAndroidTouchDevice() {
    const isTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
    return isTouch && !isIOSDevice();
}

function getOpusRecorderWorkerOptions() {
    const base = String(OPUS_RECORDER_BASE || '').replace(/\/$/, '');
    return {
        encoderWorkerFactory: () => new Worker(base + '/encoderWorker.umd.js'),
        OggOpusEncoderWasmPath: base + '/OggOpusEncoder.wasm',
        WebMOpusEncoderWasmPath: base + '/WebMOpusEncoder.wasm',
    };
}

function pickNativeVoiceMimeType() {
    const candidates = [
        { mime: 'audio/ogg;codecs=opus', ext: 'ogg', voice: true },
        { mime: 'audio/ogg', ext: 'ogg', voice: true },
        { mime: 'audio/webm;codecs=opus', ext: 'webm', voice: false },
        { mime: 'audio/webm', ext: 'webm', voice: false },
    ];
    // iPhone Safari m4a recordings are rejected by WhatsApp — never use native mp4/aac there.
    if (!isIOSDevice()) {
        candidates.push(
            { mime: 'audio/mp4', ext: 'm4a', voice: false },
            { mime: 'audio/aac', ext: 'm4a', voice: false }
        );
    }
    if (!window.MediaRecorder) return null;
    for (const candidate of candidates) {
        if (MediaRecorder.isTypeSupported(candidate.mime)) {
            return candidate;
        }
    }
    return null;
}

async function createVoiceRecorder(stream) {
    const OpusRec = window.OpusMediaRecorder;
    if (OpusRec && OpusRec.isTypeSupported('audio/ogg')) {
        try {
            return {
                recorder: new OpusRec(stream, { mimeType: 'audio/ogg' }, getOpusRecorderWorkerOptions()),
                mime: 'audio/ogg',
                ext: 'ogg',
                voice: true,
            };
        } catch (error) {
            console.warn('OpusMediaRecorder init failed', error);
        }
    }

    const native = pickNativeVoiceMimeType();
    if (native && native.voice) {
        return {
            recorder: new MediaRecorder(stream, { mimeType: native.mime }),
            mime: native.mime,
            ext: native.ext,
            voice: true,
        };
    }

    if (native && !isIOSDevice()) {
        return {
            recorder: new MediaRecorder(stream, { mimeType: native.mime }),
            mime: native.mime,
            ext: native.ext,
            voice: false,
        };
    }

    throw new Error('Voice recording is not supported in this browser.');
}

function formatVoiceDuration(ms) {
    const totalSec = Math.max(0, Math.floor(ms / 1000));
    const minutes = Math.floor(totalSec / 60);
    const seconds = totalSec % 60;
    return minutes + ':' + String(seconds).padStart(2, '0');
}

function setVoiceRecordingUi(active) {
    const bar = document.getElementById('voiceRecordingBar');
    const btn = document.getElementById('voiceRecordBtn');
    if (bar) bar.classList.toggle('is-active', active);
    if (btn) {
        btn.classList.toggle('is-recording', active);
        if (active && !voiceRecorderState.holdMode) {
            btn.innerHTML = '<i class="bi bi-stop-fill"></i>';
            btn.setAttribute('aria-label', 'Stop and send voice message');
            btn.setAttribute('title', 'Stop and send');
        }
    }
    updateSendVoiceToggle();
}

function startVoiceTimer() {
    const timeEl = document.getElementById('voiceRecordingTime');
    voiceRecorderState.startedAt = Date.now();
    voiceRecorderState.timerId = window.setInterval(() => {
        if (timeEl) {
            timeEl.textContent = formatVoiceDuration(Date.now() - voiceRecorderState.startedAt);
        }
    }, 250);
}

function stopVoiceTimer() {
    if (voiceRecorderState.timerId) {
        clearInterval(voiceRecorderState.timerId);
        voiceRecorderState.timerId = null;
    }
}

function cleanupVoiceStream() {
    if (voiceRecorderState.stream) {
        voiceRecorderState.stream.getTracks().forEach(track => track.stop());
        voiceRecorderState.stream = null;
    }
}

function resetVoiceRecordButton() {
    const btn = document.getElementById('voiceRecordBtn');
    if (btn) {
        btn.innerHTML = '<i class="bi bi-mic-fill"></i>';
        btn.setAttribute('aria-label', 'Record voice message');
        btn.setAttribute('title', isIOSDevice() ? 'Tap to record voice message' : (isAndroidTouchDevice() ? 'Hold to record voice message' : 'Click to record voice message'));
    }
    voiceRecorderState.desktopActive = false;
}

function cleanupVoiceRecording() {
    stopVoiceTimer();
    setVoiceRecordingUi(false);
    voiceRecorderState.mediaRecorder = null;
    voiceRecorderState.chunks = [];
    voiceRecorderState.isStarting = false;
    cleanupVoiceStream();
    resetVoiceRecordButton();
    const timeEl = document.getElementById('voiceRecordingTime');
    if (timeEl) timeEl.textContent = '0:00';
}

async function beginVoiceRecording() {
    if (isSendingMessage || voiceRecorderState.mediaRecorder || voiceRecorderState.isStarting) return;
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        showToast('Microphone access is not supported in this browser.', 'danger');
        return;
    }

    voiceRecorderState.isStarting = true;
    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            audio: {
                echoCancellation: true,
                noiseSuppression: true,
                channelCount: 1,
            },
        });
        const info = await createVoiceRecorder(stream);
        const recorder = info.recorder;

        voiceRecorderState.stream = stream;
        voiceRecorderState.chunks = [];
        voiceRecorderState.mimeType = info.mime;
        voiceRecorderState.fileExt = info.ext;
        voiceRecorderState.isVoiceNote = !!info.voice;
        voiceRecorderState.cancelled = false;
        voiceRecorderState.mediaRecorder = recorder;

        recorder.ondataavailable = (event) => {
            if (event.data && event.data.size) {
                voiceRecorderState.chunks.push(event.data);
            }
        };
        recorder.onstop = () => finalizeVoiceRecording();
        recorder.onerror = () => {
            showToast('Voice recording failed.', 'danger');
            cleanupVoiceRecording();
        };

        recorder.start(200);
        setVoiceRecordingUi(true);
        startVoiceTimer();

        const hint = document.getElementById('voiceRecordingHint');
        if (hint) {
            hint.textContent = voiceRecorderState.holdMode
                ? 'Release to send · Slide left to cancel'
                : 'Tap Send when done · Cancel to discard';
        }
    } catch (error) {
        cleanupVoiceRecording();
        if (error && (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError')) {
            showToast('Microphone blocked. On iPhone: Settings → Safari → Microphone → Allow for this site.', 'danger');
        } else {
            showToast(error?.message || 'Microphone permission denied or unavailable.', 'danger');
        }
    } finally {
        voiceRecorderState.isStarting = false;
    }
}

function stopVoiceRecording(send) {
    voiceRecorderState.cancelled = !send;
    const recorder = voiceRecorderState.mediaRecorder;
    if (recorder && recorder.state === 'recording') {
        try {
            recorder.stop();
        } catch (error) {
            cleanupVoiceRecording();
        }
        return;
    }
    cleanupVoiceRecording();
}

function cancelVoiceRecording() {
    stopVoiceRecording(false);
}

async function finalizeVoiceRecording() {
    const chunks = voiceRecorderState.chunks.slice();
    const mimeType = voiceRecorderState.mimeType || 'audio/ogg';
    const fileExt = voiceRecorderState.fileExt || 'ogg';
    const isVoiceNote = !!voiceRecorderState.isVoiceNote && fileExt === 'ogg';
    const cancelled = voiceRecorderState.cancelled;
    const startedAt = voiceRecorderState.startedAt || Date.now();

    cleanupVoiceRecording();

    if (cancelled || !chunks.length) {
        return;
    }

    const durationMs = Date.now() - startedAt;
    if (durationMs < 600) {
        showToast('Recording too short.', 'warning');
        return;
    }

    if (fileExt === 'webm') {
        showToast('This browser cannot create WhatsApp voice notes. Please try Chrome/Firefox or attach an audio file.', 'danger');
        return;
    }

    const blob = new Blob(chunks, { type: mimeType });
    if (blob.size > 16 * 1024 * 1024) {
        showToast('Voice message must be 16 MB or smaller.', 'danger');
        return;
    }

    const file = new File([blob], 'voice-note-' + Date.now() + '.' + fileExt, {
        type: mimeType,
        lastModified: Date.now(),
    });

    await sendPreparedFiles([file], '', {
        voiceNote: isVoiceNote || fileExt === 'ogg',
        attachButton: document.getElementById('voiceRecordBtn'),
        onComplete: updateSendVoiceToggle,
    });
}

function bindTapVoiceRecorder(btn) {
    let suppressClick = false;

    const handleTap = async () => {
        if (isSendingMessage) return;

        if (!voiceRecorderState.desktopActive && !voiceRecorderState.mediaRecorder && !voiceRecorderState.isStarting) {
            voiceRecorderState.desktopActive = true;
            btn.innerHTML = '<i class="bi bi-stop-fill"></i>';
            btn.setAttribute('aria-label', 'Stop and send voice message');
            btn.setAttribute('title', 'Stop and send');
            await beginVoiceRecording();
            return;
        }

        if (voiceRecorderState.mediaRecorder && voiceRecorderState.mediaRecorder.state === 'recording') {
            stopVoiceRecording(true);
        }
    };

    btn.addEventListener('touchend', (event) => {
        event.preventDefault();
        suppressClick = true;
        window.setTimeout(() => { suppressClick = false; }, 500);
        handleTap();
    }, { passive: false });

    btn.addEventListener('click', (event) => {
        if (suppressClick) {
            event.preventDefault();
            return;
        }
        handleTap();
    });
}

function initVoiceRecorder() {
    const btn = document.getElementById('voiceRecordBtn');
    if (!btn || (!window.MediaRecorder && !window.OpusMediaRecorder)) {
        if (btn) btn.classList.add('d-none');
        return;
    }

    const useHoldMode = isAndroidTouchDevice();
    voiceRecorderState.holdMode = useHoldMode;

    if (useHoldMode) {
        btn.setAttribute('title', 'Hold to record voice message');
        btn.addEventListener('touchstart', (event) => {
            if (isSendingMessage || voiceRecorderState.isStarting) return;
            event.preventDefault();
            voiceRecorderState.startX = event.touches[0]?.clientX || 0;
            voiceRecorderState.cancelled = false;
            beginVoiceRecording();
        }, { passive: false });

        btn.addEventListener('touchmove', (event) => {
            if (!voiceRecorderState.mediaRecorder) return;
            const currentX = event.touches[0]?.clientX || 0;
            if (voiceRecorderState.startX - currentX > 70) {
                voiceRecorderState.cancelled = true;
            }
        }, { passive: true });

        btn.addEventListener('touchend', (event) => {
            if (!voiceRecorderState.mediaRecorder || voiceRecorderState.isStarting) return;
            event.preventDefault();
            stopVoiceRecording(!voiceRecorderState.cancelled);
        }, { passive: false });
    } else {
        btn.setAttribute('title', isIOSDevice() ? 'Tap to record voice message' : 'Click to record voice message');
        bindTapVoiceRecorder(btn);
    }

    updateSendVoiceToggle();
}

const IMAGE_MAX_EDGE = 4096;
const IMAGE_UPLOAD_MAX_BYTES = 4.5 * 1024 * 1024;
const IMAGE_COMPRESS_QUALITY = 0.92;

function canvasToJpegBlob(canvas, quality) {
    return new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', quality));
}

async function loadOrientedBitmap(file) {
    if (typeof createImageBitmap !== 'function') {
        throw new Error('createImageBitmap unavailable');
    }
    try {
        return await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch (e) {
        return await createImageBitmap(file);
    }
}

function scaledDimensions(width, height, maxEdge) {
    const safeW = Math.max(1, width);
    const safeH = Math.max(1, height);
    const maxSide = Math.max(safeW, safeH);
    if (maxSide <= maxEdge) {
        return { width: safeW, height: safeH };
    }
    const scale = maxEdge / maxSide;
    return {
        width: Math.max(1, Math.round(safeW * scale)),
        height: Math.max(1, Math.round(safeH * scale)),
    };
}

async function optimizeImageForUpload(file) {
    if (!isImageFile(file)) return file;

    const type = (file.type || '').toLowerCase();
    const name = (file.name || '').toLowerCase();
    if (type === 'image/gif' || name.endsWith('.gif')) return file;

    const isHeic = ['image/heic', 'image/heif'].includes(type) || /\.(heic|heif)$/i.test(name);
    const isWhatsAppSafe = ['image/jpeg', 'image/png', 'image/webp'].includes(type);

    // Keep camera/gallery photos as-is when already WhatsApp-safe and under 5 MB.
    if (!isHeic && isWhatsAppSafe && file.size <= IMAGE_UPLOAD_MAX_BYTES) {
        return file;
    }

    if (typeof createImageBitmap !== 'function') {
        return isHeic ? convertIphonePhotoLegacy(file) : file;
    }

    try {
        const bitmap = await loadOrientedBitmap(file);
        const { width: targetW, height: targetH } = scaledDimensions(bitmap.width, bitmap.height, IMAGE_MAX_EDGE);
        const needsResize = targetW !== bitmap.width || targetH !== bitmap.height;
        const needsConvert = isHeic || type !== 'image/jpeg' || file.size > IMAGE_UPLOAD_MAX_BYTES;

        if (!needsResize && !needsConvert) {
            bitmap.close?.();
            return file;
        }

        const canvas = document.createElement('canvas');
        canvas.width = targetW;
        canvas.height = targetH;
        canvas.getContext('2d', { alpha: false }).drawImage(bitmap, 0, 0, targetW, targetH);
        bitmap.close?.();

        let quality = IMAGE_COMPRESS_QUALITY;
        let blob = await canvasToJpegBlob(canvas, quality);
        while (blob && blob.size > IMAGE_UPLOAD_MAX_BYTES && quality > 0.72) {
            quality -= 0.05;
            blob = await canvasToJpegBlob(canvas, quality);
        }
        if (!blob) return file;
        if (!isHeic && type === 'image/jpeg' && blob.size >= file.size * 0.98) return file;

        const baseName = (file.name || 'photo').replace(/\.[^.]+$/i, '') || 'photo';
        return new File([blob], `${baseName}.jpg`, {
            type: 'image/jpeg',
            lastModified: Date.now(),
        });
    } catch (e) {
        return isHeic ? convertIphonePhotoLegacy(file) : file;
    }
}

const UPLOAD_CONCURRENCY = 2;

async function convertIphonePhotoLegacy(file) {
    const extension = (file.name || '').split('.').pop().toLowerCase();
    const isHeic = ['image/heic', 'image/heif'].includes((file.type || '').toLowerCase()) || ['heic', 'heif'].includes(extension);
    if (!isHeic) return file;

    const objectUrl = URL.createObjectURL(file);
    try {
        const image = new Image();
        image.src = objectUrl;
        await image.decode();
        const maxEdge = Math.max(image.naturalWidth, image.naturalHeight, 1);
        const scale = Math.min(1, IMAGE_MAX_EDGE / maxEdge);
        const targetW = Math.round(image.naturalWidth * scale);
        const targetH = Math.round(image.naturalHeight * scale);
        const canvas = document.createElement('canvas');
        canvas.width = targetW;
        canvas.height = targetH;
        canvas.getContext('2d', { alpha: false }).drawImage(image, 0, 0, targetW, targetH);
        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', IMAGE_COMPRESS_QUALITY));
        if (!blob) throw new Error('Could not convert this iPhone photo.');
        const jpegName = file.name.replace(/\.(heic|heif)$/i, '') + '.jpg';
        return new File([blob], jpegName, { type: 'image/jpeg', lastModified: file.lastModified });
    } finally {
        URL.revokeObjectURL(objectUrl);
    }
}

async function prepareFilesForUpload(rawFiles) {
    const prepared = [];
    for (let i = 0; i < rawFiles.length; i++) {
        const prepPct = rawFiles.length ? Math.round(((i) / rawFiles.length) * 35) : 0;
        updateUploadBar(
            rawFiles.length > 1 ? `Preparing photo ${i + 1} of ${rawFiles.length}...` : 'Preparing photo...',
            prepPct
        );
        if (i > 0) {
            await new Promise(resolve => setTimeout(resolve, 0));
        }
        prepared.push(await optimizeImageForUpload(rawFiles[i]));
    }
    updateUploadBar('Ready to send...', 35);
    return prepared;
}

function updateUploadBar(label, percent) {
    const bar = document.getElementById('waUploadBar');
    const labelEl = document.getElementById('waUploadLabel');
    const fill = document.getElementById('waUploadFill');
    const countEl = document.getElementById('waUploadCount');
    if (!bar) return;
    bar.classList.add('is-active');
    if (labelEl) labelEl.textContent = label || 'Sending...';
    if (fill) fill.style.width = `${Math.min(100, Math.max(0, percent || 0))}%`;
    if (countEl) {
        if (uploadProgressState.total > 1) {
            const current = Math.min(uploadProgressState.total, uploadProgressState.done + 1);
            countEl.textContent = `${current} / ${uploadProgressState.total}`;
        } else {
            countEl.textContent = '';
        }
    }
}

function hideUploadBar() {
    const bar = document.getElementById('waUploadBar');
    const fill = document.getElementById('waUploadFill');
    if (bar) bar.classList.remove('is-active');
    if (fill) fill.style.width = '0%';
    uploadProgressState = { total: 0, done: 0, filePercent: 0 };
}

function setAttachButtonLoading(attachButton, loading) {
    if (!attachButton) return;
    if (loading) {
        attachButton.disabled = true;
        if (!attachButton.dataset.prevHtml) {
            attachButton.dataset.prevHtml = attachButton.innerHTML;
        }
        attachButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        return;
    }
    attachButton.disabled = false;
    if (attachButton.dataset.prevHtml) {
        attachButton.innerHTML = attachButton.dataset.prevHtml;
        delete attachButton.dataset.prevHtml;
    } else {
        attachButton.innerHTML = attachButton.id === 'voiceRecordBtn'
            ? '<i class="bi bi-mic-fill"></i>'
            : '<i class="bi bi-paperclip"></i>';
    }
}

function setSendButtonLoading(loading) {
    const btn = document.getElementById('sendMessageBtn');
    if (!btn) return;
    if (loading) {
        btn.disabled = true;
        btn.classList.remove('d-none');
        if (!btn.dataset.prevHtml) btn.dataset.prevHtml = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        return;
    }
    btn.disabled = false;
    if (btn.dataset.prevHtml) {
        btn.innerHTML = btn.dataset.prevHtml;
        delete btn.dataset.prevHtml;
    }
    updateSendVoiceToggle();
}

function appendPendingMessage(file, tempId) {
    const chat = document.getElementById('messagesContainer');
    if (!chat) return;

    const div = document.createElement('div');
    div.className = 'wa-msg outgoing is-pending';
    div.id = tempId;
    div.dataset.pending = '1';

    let body = '';
    if (isImageFile(file)) {
        const url = URL.createObjectURL(file);
        div.dataset.previewUrl = url;
        body = `<div class="msg-media wa-pending-media"><img src="${url}" alt="Sending"><div class="wa-pending-overlay"><span class="spinner-border spinner-border-sm text-light"></span></div></div>`;
    } else if (isVideoFile(file)) {
        body = `<div class="wa-pending-file"><i class="bi bi-camera-video"></i><span>Sending video...</span></div>`;
    } else if ((file.type || '').startsWith('audio/')) {
        body = `<div class="wa-pending-file"><i class="bi bi-mic-fill"></i><span>Sending voice message...</span></div>`;
    } else {
        body = `<div class="wa-pending-file"><i class="bi bi-file-earmark"></i><span>Sending ${escapeHtml(file.name || 'file')}...</span></div>`;
    }

    div.innerHTML = `${body}<div class="wa-msg-time">Sending... <i class="bi bi-clock text-muted"></i></div>`;
    chat.appendChild(div);
    scrollChatToBottom(false);
}

function removePendingMessage(tempId) {
    const el = document.getElementById(tempId);
    if (!el) return;
    const url = el.dataset.previewUrl;
    if (url) {
        try { URL.revokeObjectURL(url); } catch (e) {}
    }
    el.remove();
}

function markPendingMessageFailed(tempId) {
    const el = document.getElementById(tempId);
    if (!el) return;
    el.classList.add('is-failed');
    const timeEl = el.querySelector('.wa-msg-time');
    if (timeEl) timeEl.innerHTML = 'Failed <i class="bi bi-exclamation-circle text-danger"></i>';
    window.setTimeout(() => removePendingMessage(tempId), 2500);
}

function uploadPreparedFile(file, index, prepared, caption, allImages, captionTargetIndex, options, onProgress) {
    const canHaveCaption = !(file.type || '').startsWith('audio/');
    const useCaption = caption && canHaveCaption && allImages && index === captionTargetIndex;

    const formData = new FormData();
    const cleanName = (file.name || 'media.bin').replace(/[^\w.\-]+/g, '_');
    formData.append('file', file, cleanName);
    if (options.voiceNote) {
        formData.append('voice_note', '1');
    }
    if (useCaption) {
        formData.append('caption', caption);
    }

    const csrf = document.querySelector('meta[name=csrf-token]')?.content || '';

    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', SEND_MEDIA_URL);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
        xhr.upload.onprogress = (event) => {
            if (!event.lengthComputable || typeof onProgress !== 'function') return;
            onProgress(event.loaded / event.total);
        };
        xhr.onload = () => {
            let data = {};
            try { data = JSON.parse(xhr.responseText || '{}'); } catch (e) {}
            if (xhr.status < 200 || xhr.status >= 300) {
                const validationMsg = data.errors?.file?.[0]
                    || Object.values(data.errors || {}).flat()?.[0]
                    || data.message
                    || 'Failed to send attachment';
                reject(new Error(validationMsg));
                return;
            }
            resolve({ message: data.message, usedCaption: useCaption });
        };
        xhr.onerror = () => reject(new Error('Network error while uploading'));
        xhr.onabort = () => reject(new Error('Upload cancelled'));
        xhr.send(formData);
    });
}

async function sendPreparedFiles(rawFiles, caption, options = {}) {
    const attachButton = options.attachButton || document.querySelector('.wa-attach-btn');
    const input = options.input || null;
    const onComplete = typeof options.onComplete === 'function' ? options.onComplete : null;

    if (!rawFiles.length) return;

    const sendBtn = document.getElementById('sendMessageBtn');
    uploadProgressState = { total: rawFiles.length, done: 0, filePercent: 0 };
    updateUploadBar(rawFiles.length > 1 ? `Preparing ${rawFiles.length} photos...` : 'Preparing...', 2);
    setAttachButtonLoading(attachButton, true);
    setSendButtonLoading(true);
    if (sendBtn) sendBtn.disabled = true;

    let prepared;
    try {
        prepared = await prepareFilesForUpload(rawFiles);
    } catch (error) {
        showToast(error.message || 'One photo could not be prepared.', 'danger');
        if (input) input.value = '';
        hideUploadBar();
        setAttachButtonLoading(attachButton, false);
        setSendButtonLoading(false);
        return;
    }

    const allImages = prepared.every(isImageFile);
    if (prepared.length > 1 && !allImages) {
        showToast('Select multiple photos together, or send one video/file at a time.', 'danger');
        if (input) input.value = '';
        hideUploadBar();
        setAttachButtonLoading(attachButton, false);
        setSendButtonLoading(false);
        return;
    }

    for (const file of prepared) {
        const isImage = isImageFile(file);
        const isVideo = isVideoFile(file);
        if (isImage && file.size > 20 * 1024 * 1024) {
            showToast('One photo is too large (over 20 MB). Please choose smaller images.', 'danger');
            if (input) input.value = '';
            hideUploadBar();
            setAttachButtonLoading(attachButton, false);
            setSendButtonLoading(false);
            return;
        }
        if (!isImage && file.size > 16 * 1024 * 1024) {
            showToast(isVideo
                ? 'WhatsApp video must be 16 MB or smaller.'
                : 'Each attachment must be 16 MB or smaller.', 'danger');
            if (input) input.value = '';
            hideUploadBar();
            setAttachButtonLoading(attachButton, false);
            setSendButtonLoading(false);
            return;
        }
    }

    uploadProgressState.total = prepared.length;
    let sentCount = 0;
    let failedCount = 0;
    let captionUsed = false;
    const captionTargetIndex = allImages && caption ? prepared.length - 1 : -1;

    mediaBatchUploadActive = prepared.length > 1;

    let nextIndex = 0;
    const workerCount = Math.min(UPLOAD_CONCURRENCY, prepared.length);
    const workers = Array.from({ length: workerCount }, async () => {
        while (nextIndex < prepared.length) {
            const index = nextIndex++;
            const file = prepared[index];
            const tempId = `pending-${Date.now()}-${index}-${Math.random().toString(36).slice(2, 7)}`;
            appendPendingMessage(file, tempId);

            const label = prepared.length > 1
                ? `Sending photo ${index + 1} of ${prepared.length}...`
                : (isVideoFile(file) ? 'Sending video...' : (isImageFile(file) ? 'Sending photo...' : 'Sending file...'));
            updateUploadBar(label, 35 + Math.round((uploadProgressState.done / prepared.length) * 60));

            try {
                const result = await uploadPreparedFile(
                    file,
                    index,
                    prepared,
                    caption,
                    allImages,
                    captionTargetIndex,
                    options,
                    (filePct) => {
                        uploadProgressState.filePercent = filePct;
                        const overall = 35 + (((uploadProgressState.done + filePct) / prepared.length) * 60);
                        updateUploadBar(label, overall);
                    }
                );
                removePendingMessage(tempId);
                if (result.message) appendMessage(result.message);
                uploadProgressState.done++;
                uploadProgressState.filePercent = 0;
                sentCount++;
                if (result.usedCaption) captionUsed = true;
                updateUploadBar(
                    prepared.length > 1 ? `Sent ${uploadProgressState.done} of ${prepared.length}` : 'Almost done...',
                    35 + Math.round((uploadProgressState.done / prepared.length) * 60)
                );
            } catch (error) {
                failedCount++;
                markPendingMessageFailed(tempId);
                uploadProgressState.done++;
                showToast(error.message || `Failed to send ${file.name}`, 'danger');
            }
        }
    });
    await Promise.all(workers);

    updateUploadBar(failedCount ? 'Some files failed' : 'Sent!', 100);

    if (mediaBatchUploadActive) {
        mediaBatchUploadActive = false;
        scrollChatToBottom(false);
    }

    if (captionUsed) {
        textarea.value = '';
        textarea.style.height = 'auto';
        updateSendVoiceToggle();
    }

    if (prepared.length > 1) {
        if (failedCount === 0) {
            showToast(`Sent ${sentCount} photos`, 'success');
        } else if (sentCount > 0) {
            showToast(`Sent ${sentCount} of ${prepared.length} photos`, 'danger');
        }
    } else if (sentCount === 1 && failedCount === 0) {
        showToast('Sent', 'success');
    }

    if (input) input.value = '';
    hideUploadBar();
    setAttachButtonLoading(attachButton, false);
    setSendButtonLoading(false);
    if (onComplete) onComplete();
    if (sentCount > 0) scheduleChatPoll(1500);
}

function closeAttachDropdown() {
    const btn = document.querySelector('.wa-attach-btn');
    if (!btn || typeof bootstrap === 'undefined') return;
    const instance = bootstrap.Dropdown.getInstance(btn) || bootstrap.Dropdown.getOrCreateInstance(btn);
    instance.hide();
}

function triggerFileInput(input) {
    if (!input) return;
    input.value = '';
    try {
        input.click();
    } catch (e) {
        showToast('Could not open file picker. Try again.', 'danger');
    }
}

function openCameraPicker() {
    const input = document.getElementById('cameraInput');
    closeAttachDropdown();
    triggerFileInput(input);
}

function openPhotoPicker() {
    const input = document.getElementById('photoInput');
    closeAttachDropdown();
    triggerFileInput(input);
}

function openMediaPicker() {
    const input = document.getElementById('mediaInput');
    closeAttachDropdown();
    triggerFileInput(input);
}

async function sendMedia(input) {
    const rawFiles = input.files ? Array.from(input.files) : [];
    if (!rawFiles.length) return;

    const imageFiles = rawFiles.filter(isImageFile);
    const otherFiles = rawFiles.filter(file => !isImageFile(file));

    // Photos go to preview first so you can mark them before sending.
    if (imageFiles.length && !otherFiles.length) {
        const normalized = imageFiles.map((file, index) => {
            const name = defaultPasteFileName(file, index);
            if (file.name === name) return file;
            return new File([file], name, {
                type: file.type || 'image/jpeg',
                lastModified: file.lastModified || Date.now(),
            });
        });
        input.value = '';
        requestAnimationFrame(() => {
            pendingPasteFiles = normalized;
            renderPastePreview();
            textarea.focus();
            showToast(
                imageFiles.length > 1
                    ? `${imageFiles.length} photos selected. Add a caption and tap Send.`
                    : 'Photo ready. Tap Mark to draw, then Send.',
                'success'
            );
        });
        return;
    }

    isSendingMessage = true;
    try {
        await sendPreparedFiles(rawFiles, textarea.value.trim(), {
            attachButton: document.querySelector('.wa-attach-btn'),
            input,
        });
    } finally {
        isSendingMessage = false;
    }
}

function locationCardHtml(loc, fallbackText) {
    if (!loc || loc.latitude == null || loc.longitude == null) {
        return `<div>${escapeHtml(fallbackText || 'Location')}</div>`;
    }
    const name = escapeHtml(loc.name || 'Location');
    const address = escapeHtml(loc.address || `${Number(loc.latitude).toFixed(5)}, ${Number(loc.longitude).toFixed(5)}`);
    const mapsUrl = loc.maps_url || `https://www.google.com/maps?q=${encodeURIComponent(loc.latitude + ',' + loc.longitude)}`;
    const mapImg = loc.preview_url || `https://static-maps.yandex.ru/1.x/?lang=en_US&ll=${loc.longitude},${loc.latitude}&z=15&l=map&size=450,200&pt=${loc.longitude},${loc.latitude},pm2rdm`;
    return `<a class="wa-location-card" href="${escapeHtml(mapsUrl)}" target="_blank" rel="noopener">
        <div class="wa-location-map">
            <img src="${escapeHtml(mapImg)}" alt="Map" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
        </div>
        <div class="wa-location-body">
            <div class="wa-location-name">${name}</div>
            <div class="wa-location-address">${address}</div>
            <div class="wa-location-open"><i class="bi bi-geo-alt me-1"></i>Open in Maps</div>
        </div>
    </a>`;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function linkifyText(text) {
    const raw = String(text || '');
    // Match on raw text so & in query strings (Instagram, utm, etc.) stay in the link.
    const pattern = /(?:https?:\/\/|www\.)[^\s<>"']+/gi;
    let out = '';
    let last = 0;
    let match;
    while ((match = pattern.exec(raw)) !== null) {
        out += escapeHtml(raw.slice(last, match.index)).replace(/\r\n|\r|\n/g, '<br>');
        const url = match[0];
        const trimmed = url.replace(/[.,;:!?)\]]+$/g, '');
        const trailing = url.slice(trimmed.length);
        const href = /^https?:\/\//i.test(trimmed) ? trimmed : `https://${trimmed}`;
        out += `<a class="wa-msg-link" href="${escapeHtml(href)}" target="_blank" rel="noopener noreferrer">${escapeHtml(trimmed)}</a>${escapeHtml(trailing)}`;
        last = match.index + url.length;
    }
    out += escapeHtml(raw.slice(last)).replace(/\r\n|\r|\n/g, '<br>');
    return out;
}

function contactCardsHtml(contacts, fallbackText) {
    if (!Array.isArray(contacts) || !contacts.length) {
        return `<div>${escapeHtml(fallbackText || 'Contact')}</div>`;
    }

    return contacts.map(contact => {
        const name = escapeHtml(contact.name || 'Contact');
        const initial = escapeHtml(contact.initial || (contact.name || 'C').charAt(0).toUpperCase());
        const primary = (contact.phones && contact.phones[0]) || null;
        const phone = primary ? escapeHtml(primary.display || '') : '';
        return `<div class="wa-contact-card">
            <div class="wa-contact-head">
                <div class="wa-contact-avatar">${initial}</div>
                <div class="min-w-0">
                    <div class="wa-contact-name">${name}</div>
                    ${phone ? `<div class="wa-contact-phone">${phone}</div>` : ''}
                </div>
            </div>
        </div>`;
    }).join('');
}

function sendLocationPayload(payload) {
    const attachButton = document.querySelector('.wa-attach-btn');
    if (attachButton) {
        attachButton.disabled = true;
        attachButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    }

    return fetch(SEND_LOCATION_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
        },
        body: JSON.stringify(payload)
    })
    .then(async response => {
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || 'Failed to send location');
        return data;
    })
    .then(data => appendMessage(data.message))
    .catch(error => showToast(error.message, 'danger'))
    .finally(() => {
        if (attachButton) {
            attachButton.disabled = false;
            attachButton.innerHTML = '<i class="bi bi-paperclip"></i>';
        }
    });
}

function sendShopLocation() {
    sendLocationPayload(SHOP_LOCATION);
}

function sendMyLocation() {
    if (!navigator.geolocation) {
        showToast('Location is not supported in this browser.', 'danger');
        return;
    }
    showToast('Getting your location...', 'success');
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            sendLocationPayload({
                latitude: pos.coords.latitude,
                longitude: pos.coords.longitude,
                name: 'My Location',
                address: null,
            });
        },
        () => showToast('Could not get current location. Please allow location permission.', 'danger'),
        { enableHighAccuracy: true, timeout: 15000 }
    );
}

function reactionBadgeHtml(reactions, messageId) {
    if (!Array.isArray(reactions) || !reactions.length) return '';
    return `<div class="wa-msg-reactions is-clickable" data-reaction-badge data-message-id="${messageId}" onclick="openReactionPickerForMessage(event, ${messageId})" title="Change reaction">${reactions.join('')}</div>`;
}

function messageMenuHtml(msg) {
    if (msg.revoked || msg.message_type === 'revoked') return '';
    return `
        <div class="wa-msg-actions">
            <button type="button" class="wa-msg-menu-btn" onclick="toggleMsgMenu(event, ${msg.id})" aria-label="Message options">
                <i class="bi bi-chevron-down"></i>
            </button>
        </div>
    `;
}

var msgMenuIgnoreCloseUntil = 0;

function closeReactionPicker() {
    const picker = document.getElementById('waReactionPicker');
    if (!picker) return;
    picker.classList.remove('is-visible');
    picker.setAttribute('aria-hidden', 'true');
    picker.style.removeProperty('top');
    picker.style.removeProperty('left');
    activeReactionPickerMessageId = null;
}

function positionReactionPicker(picker, anchorEl) {
    const anchor = anchorEl.getBoundingClientRect();
    const gap = 8;

    picker.style.display = 'flex';
    picker.style.visibility = 'hidden';
    picker.style.opacity = '0';

    const pickerWidth = picker.offsetWidth || 280;
    const pickerHeight = picker.offsetHeight || 52;

    let top = anchor.top - pickerHeight - gap;
    if (top < 8) top = anchor.bottom + gap;
    top = Math.max(8, Math.min(top, window.innerHeight - pickerHeight - 8));

    let left = anchor.left + (anchor.width / 2) - (pickerWidth / 2);
    left = Math.max(8, Math.min(left, window.innerWidth - pickerWidth - 8));

    picker.style.top = `${Math.round(top)}px`;
    picker.style.left = `${Math.round(left)}px`;
    picker.style.visibility = '';
    picker.style.opacity = '';
}

function openReactionPickerForMessage(event, messageId) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const msgEl = document.getElementById(`msg-${messageId}`);
    if (!msgEl || msgEl.classList.contains('is-revoked')) return;

    const picker = document.getElementById('waReactionPicker');
    if (!picker) return;

    if (picker.classList.contains('is-visible') && activeReactionPickerMessageId === messageId) {
        closeReactionPicker();
        return;
    }

    closeMsgContextMenu();
    closeReactionPicker();
    activeReactionPickerMessageId = messageId;
    reactionPickerIgnoreCloseUntil = Date.now() + 200;

    if (picker.parentElement !== document.body) {
        document.body.appendChild(picker);
    }

    const anchor = (event && event.target && event.target.closest)
        ? (event.target.closest('[data-reaction-badge]') || event.target.closest('.wa-msg-menu-btn') || msgEl)
        : msgEl;
    positionReactionPicker(picker, anchor);
    picker.classList.add('is-visible');
    picker.setAttribute('aria-hidden', 'false');
}

function openReactionPickerFromMenu() {
    const id = activeContextMessageId;
    const msgEl = id ? document.getElementById(`msg-${id}`) : null;
    closeMsgContextMenu();
    if (!id || !msgEl) return;
    const anchor = msgEl.querySelector('.wa-msg-menu-btn') || msgEl;
    openReactionPickerForMessage({ preventDefault() {}, stopPropagation() {}, target: anchor }, id);
}

async function sendReactionEmoji(emoji) {
    const messageId = activeReactionPickerMessageId;
    if (!messageId) return;

    const msgEl = document.getElementById(`msg-${messageId}`);
    const isOutgoing = !!msgEl?.classList.contains('outgoing');
    closeReactionPicker();

    try {
        const response = await fetch(REACT_MESSAGE_URL.replace('__MSG__', String(messageId)), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            },
            body: JSON.stringify({ emoji }),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Could not react to this message');
        }

        setMessageReactions(data.message_id || messageId, data.reactions || []);
        if (isOutgoing) {
            showToast('Reaction saved', 'success');
        } else if (!(data.reactions || []).length) {
            showToast('Reaction removed', 'success');
        } else {
            showToast('Reaction sent', 'success');
        }
    } catch (error) {
        showToast(error.message || 'Could not react to this message', 'danger');
    }
}

function closeMsgContextMenu() {
    const menu = document.getElementById('waMsgContextMenu');
    if (!menu) return;
    menu.classList.remove('is-visible');
    menu.setAttribute('aria-hidden', 'true');
    menu.style.removeProperty('top');
    menu.style.removeProperty('left');
    menu.style.removeProperty('display');
    menu.style.removeProperty('visibility');
    menu.style.removeProperty('opacity');
    menu.style.removeProperty('pointer-events');
    document.querySelectorAll('.wa-msg-actions.is-open').forEach(el => el.classList.remove('is-open'));
    activeContextMessageId = null;
}

function positionMsgContextMenu(menu, anchorEl, isOutgoing) {
    const anchor = anchorEl.getBoundingClientRect();
    const gap = 6;

    menu.style.display = 'block';
    menu.style.visibility = 'hidden';
    menu.style.opacity = '0';
    menu.style.pointerEvents = 'none';

    const menuWidth = menu.offsetWidth || 140;
    const menuHeight = menu.offsetHeight || 120;

    let top = anchor.bottom + gap;
    if (top + menuHeight > window.innerHeight - 8) {
        top = anchor.top - menuHeight - gap;
    }
    top = Math.max(8, Math.min(top, window.innerHeight - menuHeight - 8));

    let left = isOutgoing ? anchor.right - menuWidth : anchor.left;
    left = Math.max(8, Math.min(left, window.innerWidth - menuWidth - 8));

    menu.style.top = `${Math.round(top)}px`;
    menu.style.left = `${Math.round(left)}px`;
    menu.style.visibility = '';
    menu.style.opacity = '';
    menu.style.pointerEvents = '';
}

function toggleMsgMenu(event, messageId) {
    event.preventDefault();
    event.stopPropagation();

    const menu = document.getElementById('waMsgContextMenu');
    const btn = event.currentTarget;
    const msgEl = document.getElementById(`msg-${messageId}`);
    if (!menu || !msgEl || !btn) return;

    if (menu.classList.contains('is-visible') && activeContextMessageId === messageId) {
        closeMsgContextMenu();
        return;
    }

    closeMsgContextMenu();
    activeContextMessageId = messageId;
    msgMenuIgnoreCloseUntil = Date.now() + 200;

    const actions = msgEl.querySelector('.wa-msg-actions');
    if (actions) actions.classList.add('is-open');

    const markBtn = document.getElementById('menuMarkResend');
    const hasImage = msgEl.dataset.messageType === 'image'
        || !!msgEl.querySelector('.msg-media img[data-wa-media="image"], .msg-media img');
    if (markBtn) markBtn.style.display = hasImage ? '' : 'none';

    const pinBtn = document.getElementById('menuPinMessage');
    if (pinBtn) {
        const isRevoked = msgEl.classList.contains('is-revoked');
        pinBtn.style.display = isRevoked ? 'none' : '';
        const isPinned = pinnedMessageState && Number(pinnedMessageState.message_id) === Number(messageId);
        pinBtn.textContent = isPinned ? 'Unpin' : 'Pin';
    }

    const reactBtn = menu.querySelector('.menu-react');
    if (reactBtn) {
        reactBtn.style.display = msgEl.classList.contains('is-revoked') ? 'none' : '';
    }

    if (menu.parentElement !== document.body) {
        document.body.appendChild(menu);
    }

    const isOutgoing = msgEl.classList.contains('outgoing');
    positionMsgContextMenu(menu, btn, isOutgoing);

    menu.classList.add('is-visible');
    menu.setAttribute('aria-hidden', 'false');
}

document.addEventListener('click', (event) => {
    if (Date.now() < msgMenuIgnoreCloseUntil) return;
    const menu = document.getElementById('waMsgContextMenu');
    if (!menu?.classList.contains('is-visible')) return;
    if (menu.contains(event.target) || event.target.closest('.wa-msg-menu-btn')) return;
    closeMsgContextMenu();
});

document.addEventListener('click', (event) => {
    if (Date.now() < reactionPickerIgnoreCloseUntil) return;
    const picker = document.getElementById('waReactionPicker');
    if (!picker?.classList.contains('is-visible')) return;
    if (picker.contains(event.target) || event.target.closest('[data-reaction-badge]')) return;
    closeReactionPicker();
});

document.getElementById('messagesContainer')?.addEventListener('scroll', () => {
    if (document.getElementById('waMsgContextMenu')?.classList.contains('is-visible')) {
        closeMsgContextMenu();
    }
    if (document.getElementById('waReactionPicker')?.classList.contains('is-visible')) {
        closeReactionPicker();
    }
}, { passive: true });

(function initMsgContextMenu() {
    const menu = document.getElementById('waMsgContextMenu');
    if (menu && menu.parentElement !== document.body) {
        document.body.appendChild(menu);
    }
    menu?.addEventListener('click', (e) => e.stopPropagation());

    const picker = document.getElementById('waReactionPicker');
    if (picker && picker.parentElement !== document.body) {
        document.body.appendChild(picker);
    }
    picker?.addEventListener('click', (e) => e.stopPropagation());
})();

function pinMessageUrl(messageId) {
    return PIN_MESSAGE_URL.replace('__MSG__', String(messageId));
}

function pinnedTypeIcon(type) {
    const icons = {
        image: 'bi-image',
        video: 'bi-camera-video',
        audio: 'bi-mic',
        location: 'bi-geo-alt',
        contacts: 'bi-person',
        document: 'bi-file-earmark',
    };
    return icons[type] || 'bi-chat-left-text';
}

function renderPinnedBar(pinned) {
    pinnedMessageState = pinned || null;
    const bar = document.getElementById('pinnedMessageBar');
    const textEl = document.getElementById('pinnedMessageText');
    const iconEl = document.getElementById('pinnedMessageIcon');
    if (!bar || !textEl) return;

    if (!pinned || !pinned.message_id) {
        bar.classList.remove('is-visible');
        textEl.textContent = '';
        document.querySelectorAll('.wa-msg.is-pinned-msg').forEach(el => el.classList.remove('is-pinned-msg'));
        return;
    }

    bar.classList.add('is-visible');
    const author = pinned.author ? `${pinned.author}: ` : '';
    textEl.textContent = author + (pinned.text || 'Message');
    if (iconEl) {
        iconEl.innerHTML = `<i class="bi ${pinnedTypeIcon(pinned.message_type)}"></i>`;
    }
    applyPinnedMessageStyles(pinned.message_id);
}

function applyPinnedMessageStyles(messageId) {
    document.querySelectorAll('.wa-msg.is-pinned-msg').forEach(el => el.classList.remove('is-pinned-msg'));
    const el = document.getElementById(`msg-${messageId}`);
    if (el) el.classList.add('is-pinned-msg');
}

async function pinMessageById(messageId) {
    try {
        const response = await fetch(pinMessageUrl(messageId), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            },
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Could not pin message');
        }
        renderPinnedBar(data.pinned_message);
        showToast('Message pinned', 'success');
    } catch (error) {
        showToast(error.message || 'Could not pin message', 'danger');
    }
}

async function unpinMessage(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    try {
        const response = await fetch(UNPIN_MESSAGE_URL, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            },
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Could not unpin message');
        }
        renderPinnedBar(null);
        showToast('Message unpinned', 'success');
    } catch (error) {
        showToast(error.message || 'Could not unpin message', 'danger');
    }
}

function togglePinFromMenu() {
    const id = activeContextMessageId;
    closeMsgContextMenu();
    if (!id) return;
    if (pinnedMessageState && Number(pinnedMessageState.message_id) === Number(id)) {
        unpinMessage(null);
        return;
    }
    pinMessageById(id);
}

function jumpToPinnedMessage(event) {
    if (event && event.target.closest('.wa-pinned-bar-unpin')) return;
    if (!pinnedMessageState?.message_id) return;
    scrollToMessage(pinnedMessageState.message_id);
}

function extractWamidMessageKey(wamid) {
    if (!wamid) return null;
    try {
        let b64 = String(wamid).replace(/^wamid\./i, '').replace(/-/g, '+').replace(/_/g, '/');
        while (b64.length % 4) b64 += '=';
        const raw = atob(b64);
        const matches = raw.match(/[0-9A-Fa-f]{16,64}/g);
        if (!matches || !matches.length) return null;
        return matches[matches.length - 1].toUpperCase();
    } catch (e) {
        return null;
    }
}

function replyQuoteHtml(reply) {
    if (!reply || !reply.text) return '';
    const dirClass = reply.direction === 'outgoing' ? ' quote-you' : ' quote-them';
    const clickable = !!(reply.message_id || reply.meta_message_id);
    const attrs = clickable
        ? ` type="button" class="wa-msg-quote${dirClass} is-clickable" data-quote-target-id="${reply.message_id || ''}" data-quote-target-meta="${escapeHtml(reply.meta_message_id || '')}" aria-label="Jump to original message"`
        : ` class="wa-msg-quote${dirClass}"`;
    const tag = clickable ? 'button' : 'div';
    return `<${tag}${attrs}>
        <div class="wa-msg-quote-author">${escapeHtml(reply.author || 'Message')}</div>
        <div class="wa-msg-quote-text">${escapeHtml(reply.text || '')}</div>
    </${tag}>`;
}

function findQuotedMessageElement(messageId, metaMessageId) {
    if (messageId) {
        const byId = document.getElementById(`msg-${messageId}`);
        if (byId) return byId;
    }
    if (metaMessageId) {
        const target = String(metaMessageId);
        const targetKey = extractWamidMessageKey(target);
        const nodes = document.querySelectorAll('[data-meta-message-id]');
        for (let i = 0; i < nodes.length; i++) {
            const nodeMeta = nodes[i].dataset.metaMessageId || '';
            if (nodeMeta === target) {
                return nodes[i];
            }
            if (targetKey && extractWamidMessageKey(nodeMeta) === targetKey) {
                return nodes[i];
            }
        }
    }
    return null;
}

function scrollMessageIntoChatView(el) {
    const chatContainer = document.getElementById('messagesContainer');
    if (!el || !chatContainer) return;

    function computeTop() {
        const containerRect = chatContainer.getBoundingClientRect();
        const elRect = el.getBoundingClientRect();
        const relativeTop = elRect.top - containerRect.top + chatContainer.scrollTop;
        const targetTop = relativeTop - (chatContainer.clientHeight / 2) + (elRect.height / 2);
        return Math.max(0, Math.min(targetTop, chatContainer.scrollHeight - chatContainer.clientHeight));
    }

    const top = computeTop();
    chatContainer.scrollTop = top;

    requestAnimationFrame(() => {
        const nextTop = computeTop();
        chatContainer.scrollTop = nextTop;
        try {
            chatContainer.scrollTo({ top: nextTop, behavior: 'smooth' });
        } catch (e) {
            chatContainer.scrollTop = nextTop;
        }
    });
}

async function scrollToQuotedMessage(messageId, metaMessageId) {
    const id = messageId ? parseInt(messageId, 10) : null;
    if (id) {
        await ensureMessageLoaded(id);
    }
    const el = findQuotedMessageElement(id, metaMessageId);
    if (!el) {
        showToast('Original message is not in this chat history', 'warning');
        return;
    }
    scrollMessageIntoChatView(el);
    el.classList.add('wa-msg-highlight');
    setTimeout(() => el.classList.remove('wa-msg-highlight'), 1600);
}

function scrollToMessage(messageId) {
    scrollToQuotedMessage(messageId, null);
}

async function jumpToHighlightMessage(messageId) {
    const targetId = parseInt(messageId, 10);
    if (!targetId) return false;

    await ensureMessageLoaded(targetId);
    const el = document.getElementById(`msg-${targetId}`);
    if (!el) return false;

    scrollMessageIntoChatView(el);
    el.classList.add('is-search-active', 'wa-msg-highlight');
    window.setTimeout(() => el.classList.remove('wa-msg-highlight'), 2000);
    updateScrollToBottomBtn();
    return true;
}

function openChatSearch() {
    const header = document.getElementById('chatHeader');
    const input = document.getElementById('chatSearchInput');
    if (!header || !input) return;
    header.classList.add('is-searching');
    input.value = '';
    chatSearchMatches = [];
    chatSearchIndex = -1;
    updateChatSearchUi();
    window.setTimeout(() => input.focus(), 50);
}

function closeChatSearch() {
    const header = document.getElementById('chatHeader');
    const input = document.getElementById('chatSearchInput');
    if (header) header.classList.remove('is-searching');
    if (input) input.value = '';
    chatSearchMatches = [];
    chatSearchIndex = -1;
    document.querySelectorAll('.wa-msg.is-search-active').forEach(el => el.classList.remove('is-search-active'));
    updateChatSearchUi();
}

function updateChatSearchUi() {
    const countEl = document.getElementById('chatSearchCount');
    const prevBtn = document.getElementById('chatSearchPrev');
    const nextBtn = document.getElementById('chatSearchNext');
    const total = chatSearchMatches.length;
    if (countEl) {
        countEl.textContent = total ? `${chatSearchIndex + 1}/${total}` : '';
    }
    const hasMatches = total > 0;
    if (prevBtn) prevBtn.disabled = !hasMatches;
    if (nextBtn) nextBtn.disabled = !hasMatches;
}

async function ensureMessageLoaded(messageId) {
    const targetId = parseInt(messageId, 10);
    if (!targetId) return false;
    if (document.getElementById(`msg-${targetId}`)) return true;

    const chatContainer = document.getElementById('messagesContainer');
    if (!chatContainer) return false;

    try {
        const response = await fetch(`${MESSAGES_URL}?around_id=${encodeURIComponent(String(targetId))}`, {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
        });
        if (!response.ok) throw new Error('load_failed');
        const data = await response.json();
        const messages = (data.messages || []).slice().sort((a, b) => a.id - b.id);
        if (!messages.length) return false;

        const hasTarget = messages.some((m) => m.id === targetId);
        if (!hasTarget) return false;

        mergeMessagesIntoChat(messages);

        if (messages.length) {
            lastMessageId = Math.max(lastMessageId, ...messages.map((m) => m.id));
        }
    } catch (e) {
        return false;
    }

    return !!document.getElementById(`msg-${targetId}`);
}

async function focusChatSearchMatch(index) {
    if (!chatSearchMatches.length) return;
    chatSearchIndex = (index + chatSearchMatches.length) % chatSearchMatches.length;
    const match = chatSearchMatches[chatSearchIndex];
    if (!match) return;

    document.querySelectorAll('.wa-msg.is-search-active').forEach(el => el.classList.remove('is-search-active'));
    await ensureMessageLoaded(match.id);
    const el = document.getElementById(`msg-${match.id}`);
    if (!el) {
        showToast('Message is not loaded in this chat', 'warning');
        return;
    }
    scrollMessageIntoChatView(el);
    el.classList.add('is-search-active', 'wa-msg-highlight');
    window.setTimeout(() => el.classList.remove('wa-msg-highlight'), 1600);
    updateChatSearchUi();
}

function jumpChatSearchMatch(direction) {
    if (!chatSearchMatches.length) return;
    focusChatSearchMatch(chatSearchIndex + direction);
}

async function runChatSearch(query) {
    const q = String(query || '').trim();
    chatSearchMatches = [];
    chatSearchIndex = -1;
    document.querySelectorAll('.wa-msg.is-search-active').forEach(el => el.classList.remove('is-search-active'));
    if (!q) {
        updateChatSearchUi();
        return;
    }

    try {
        const response = await fetch(`${CHAT_SEARCH_URL}?q=${encodeURIComponent(q)}`, {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
        });
        const data = await response.json();
        chatSearchMatches = (data.results || []).slice().reverse();
        if (chatSearchMatches.length) {
            await focusChatSearchMatch(0);
        } else {
            updateChatSearchUi();
            showToast('No messages found', 'warning');
        }
    } catch (e) {
        showToast('Could not search messages', 'danger');
    }
}

(function initChatSearch() {
    const input = document.getElementById('chatSearchInput');
    if (!input) return;
    input.addEventListener('input', () => {
        clearTimeout(chatSearchTimer);
        chatSearchTimer = setTimeout(() => runChatSearch(input.value), 350);
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            if (event.shiftKey) jumpChatSearchMatch(-1);
            else jumpChatSearchMatch(1);
        }
        if (event.key === 'Escape') closeChatSearch();
    });
})();

if (HIGHLIGHT_MESSAGE_ID) {
    window.requestAnimationFrame(() => {
        jumpToHighlightMessage(HIGHLIGHT_MESSAGE_ID).then((ok) => {
            if (!ok) {
                window.setTimeout(() => jumpToHighlightMessage(HIGHLIGHT_MESSAGE_ID), 500);
            }
        });
    });
    window.addEventListener('load', () => {
        jumpToHighlightMessage(HIGHLIGHT_MESSAGE_ID);
    }, { once: true });
}

(function initQuoteJumpHandlers() {
    const chatContainer = document.getElementById('messagesContainer');
    if (!chatContainer || chatContainer.dataset.quoteJumpBound) return;
    chatContainer.dataset.quoteJumpBound = '1';

    let lastQuoteTapAt = 0;

    function activateQuoteJump(quoteEl) {
        if (!quoteEl) return;
        const now = Date.now();
        if (now - lastQuoteTapAt < 350) return;
        lastQuoteTapAt = now;

        const messageId = quoteEl.dataset.quoteTargetId ? parseInt(quoteEl.dataset.quoteTargetId, 10) : null;
        const metaMessageId = quoteEl.dataset.quoteTargetMeta || null;
        scrollToQuotedMessage(messageId, metaMessageId);
    }

    chatContainer.addEventListener('click', (event) => {
        const quote = event.target.closest('.wa-msg-quote.is-clickable');
        if (!quote || !chatContainer.contains(quote)) return;
        event.preventDefault();
        event.stopPropagation();
        activateQuoteJump(quote);
    });

    chatContainer.addEventListener('touchend', (event) => {
        const quote = event.target.closest('.wa-msg-quote.is-clickable');
        if (!quote || !chatContainer.contains(quote)) return;
        event.preventDefault();
        event.stopPropagation();
        activateQuoteJump(quote);
    }, { passive: false });
})();

function buildReplyPreviewFromMessageEl(msgEl) {
    const id = parseInt(msgEl.dataset.messageId, 10);
    const direction = msgEl.dataset.direction || 'incoming';
    const author = direction === 'outgoing' ? 'You' : (CONV_CONTACT_NAME || 'Contact');
    let text = 'Message';

    if (msgEl.classList.contains('is-revoked')) {
        text = 'This message was deleted';
    } else if (msgEl.querySelector('.msg-media img')) {
        text = 'Photo';
    } else if (msgEl.querySelector('.msg-media video')) {
        text = 'Video';
    } else if (msgEl.querySelector('.msg-media audio')) {
        text = 'Audio';
    } else if (msgEl.querySelector('.msg-media .doc-link')) {
        text = msgEl.querySelector('.msg-media .doc-link').textContent.trim() || 'Document';
    } else if (msgEl.classList.contains('has-location')) {
        text = 'Location';
    } else if (msgEl.classList.contains('has-contacts')) {
        text = 'Contact';
    } else {
        const parts = [...msgEl.querySelectorAll(':scope > div')]
            .filter(el => !el.classList.contains('wa-msg-actions')
                && !el.classList.contains('wa-msg-quote')
                && !el.classList.contains('wa-msg-time')
                && !el.classList.contains('wa-msg-reactions')
                && !el.classList.contains('msg-media'))
            .map(el => el.textContent.trim())
            .filter(Boolean);
        text = parts[0] || 'Message';
    }

    return {
        message_id: id,
        author,
        text: text.slice(0, 120),
        direction,
        message_type: 'text',
    };
}

function startReply(messageId, preview) {
    replyToMessageId = messageId;
    replyToPreview = preview;
    const bar = document.getElementById('replyPreviewBar');
    document.getElementById('replyPreviewAuthor').textContent = preview.author || '';
    document.getElementById('replyPreviewText').textContent = preview.text || '';
    bar?.classList.remove('d-none');
    textarea.focus();
    closeMsgContextMenu();
}

function startReplyFromMenu() {
    const id = activeContextMessageId;
    if (!id) return;
    const msgEl = document.getElementById(`msg-${id}`);
    if (!msgEl || msgEl.classList.contains('is-revoked')) return;
    startReply(id, buildReplyPreviewFromMessageEl(msgEl));
}

async function blobFromChatImage(img) {
    if (!img) throw new Error('Photo not found');

    try {
        const response = await fetch(img.currentSrc || img.src, {
            credentials: 'same-origin',
            cache: 'force-cache',
        });
        if (response.ok) {
            const blob = await response.blob();
            if (blob && blob.size) return blob;
        }
    } catch (e) {}

    if (img.decode) {
        try { await img.decode(); } catch (e) {}
    }
    if (!img.naturalWidth || !img.naturalHeight) {
        throw new Error('Could not load this photo');
    }

    const canvas = document.createElement('canvas');
    canvas.width = img.naturalWidth;
    canvas.height = img.naturalHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(img, 0, 0);
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.98));
    if (!blob) throw new Error('Could not prepare this photo');
    return blob;
}

async function markAndResendFromMenu() {
    const id = activeContextMessageId;
    closeMsgContextMenu();
    if (!id) return;

    const msgEl = document.getElementById(`msg-${id}`);
    if (!msgEl || msgEl.classList.contains('is-revoked')) return;

    const img = msgEl.querySelector('.msg-media img[data-wa-media="image"], .msg-media img');
    if (!img) {
        showToast('Only photos can be marked and resent', 'warning');
        return;
    }

    await markImageAndPrepareResend(img, id);
}

async function markAndResendFromViewer() {
    const img = document.getElementById('waMediaViewerImage')
        || document.querySelector('#waMediaViewerBody img');
    if (!img) {
        showToast('Only photos can be marked and resent', 'warning');
        return;
    }
    closeMediaViewer();
    await markImageAndPrepareResend(img, 'viewer');
}

async function markImageAndPrepareResend(img, sourceId) {
    try {
        showToast('Opening photo for marking...', 'success');
        const blob = await blobFromChatImage(img);
        const mime = blob.type && blob.type.startsWith('image/') ? blob.type : 'image/jpeg';
        const ext = mime.includes('png') ? 'png' : 'jpg';
        const file = new File([blob], `chat-photo-${sourceId || 'resend'}.${ext}`, {
            type: mime,
            lastModified: Date.now(),
        });

        pendingPasteFiles = [file];
        renderPastePreview();
        openMarkEditor(0);
    } catch (error) {
        showToast(error.message || 'Could not open this photo for marking', 'danger');
    }
}

function cancelReply() {
    replyToMessageId = null;
    replyToPreview = null;
    document.getElementById('replyPreviewBar')?.classList.add('d-none');
}

function confirmDeleteMessage(messageId) {
    const id = messageId || activeContextMessageId;
    if (!id) return;
    closeMsgContextMenu();
    if (!window.confirm('Delete this message from your CRM inbox?')) return;

    fetch(DELETE_MESSAGE_URL.replace('__MSG__', String(id)), {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            'Accept': 'application/json',
        },
    })
        .then(async (response) => {
            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(data.message || 'Could not delete message');
            return data;
        })
        .then((data) => {
            const el = document.getElementById(`msg-${id}`);
            if (el) el.remove();
            if (data.pinned_message === null && pinnedMessageState && Number(pinnedMessageState.message_id) === Number(id)) {
                renderPinnedBar(null);
            }
            showToast('Message deleted', 'success');
        })
        .catch((error) => showToast(error.message || 'Could not delete message', 'danger'));
}

function applyRevokedMessage(messageId) {
    const el = document.getElementById(`msg-${messageId}`);
    if (!el || el.classList.contains('is-revoked')) return;
    el.classList.add('is-revoked');
    el.classList.remove('has-location', 'has-contacts', 'has-reactions');
    el.innerHTML = `
        <div class="wa-msg-deleted"><i class="bi bi-slash-circle"></i>This message was deleted</div>
        <div class="wa-msg-time"></div>
    `;
}

function setMessageReactions(messageId, reactions) {
    const el = document.getElementById(`msg-${messageId}`);
    if (!el) return;
    const existing = el.querySelector('[data-reaction-badge]');
    if (existing) existing.remove();
    if (Array.isArray(reactions) && reactions.length) {
        el.classList.add('has-reactions');
        const timeEl = el.querySelector('.wa-msg-time');
        const badge = document.createElement('div');
        badge.className = 'wa-msg-reactions is-clickable';
        badge.setAttribute('data-reaction-badge', '');
        badge.setAttribute('data-message-id', String(messageId));
        badge.setAttribute('title', 'Change reaction');
        badge.textContent = reactions.join('');
        badge.addEventListener('click', (event) => openReactionPickerForMessage(event, messageId));
        if (timeEl) el.insertBefore(badge, timeEl);
        else el.appendChild(badge);
    } else {
        el.classList.remove('has-reactions');
    }
}

function buildMessageElement(msg) {
    if (msg.message_type === 'reaction') return null;

    const div = document.createElement('div');
    const typeClass = msg.message_type === 'location' ? ' has-location' : (msg.message_type === 'contacts' ? ' has-contacts' : '');
    const mediaClass = ['image', 'video'].includes(msg.message_type) ? ' has-media' : '';
    const revokedClass = (msg.revoked || msg.message_type === 'revoked') ? ' is-revoked' : '';
    const replyClass = msg.reply_to ? ' has-reply' : '';
    div.className = `wa-msg ${msg.direction}${(msg.reactions || []).length ? ' has-reactions' : ''}${mediaClass}${typeClass}${revokedClass}${replyClass}`;
    div.id = `msg-${msg.id}`;
    div.dataset.messageId = String(msg.id);
    div.dataset.metaMessageId = msg.meta_message_id || '';
    div.dataset.direction = msg.direction || '';
    div.dataset.messageType = msg.message_type || '';

    if (msg.revoked || msg.message_type === 'revoked') {
        div.innerHTML = `
            <div class="wa-msg-deleted"><i class="bi bi-slash-circle"></i>This message was deleted</div>
            <div class="wa-msg-time">${msg.time || ''}</div>
        `;
        return div;
    }

    let content = `<div>${linkifyText(msg.message || '')}</div>`;
    if (msg.message_type === 'image' && msg.media_url) {
        content = `<div class="msg-media"><img src="${escapeHtml(msg.media_url)}" alt="Image" data-wa-media="image"></div>${msg.message && msg.message !== '[Image]' ? `<div class="mt-1">${linkifyText(msg.message)}</div>` : ''}`;
    } else if (msg.message_type === 'video' && msg.media_url) {
        content = `<div class="msg-media"><video src="${escapeHtml(msg.media_url)}" preload="metadata" playsinline data-wa-media="video"></video></div>${msg.message && msg.message !== '[Video]' ? `<div class="mt-1">${linkifyText(msg.message)}</div>` : ''}`;
    } else if (msg.message_type === 'audio' && msg.media_url) {
        content = `<div class="msg-media"><audio src="${escapeHtml(msg.media_url)}" controls preload="metadata"></audio></div>`;
    } else if (msg.message_type === 'document' && msg.media_url) {
        content = `<div class="msg-media"><a href="${escapeHtml(msg.media_url)}" target="_blank" class="doc-link"><i class="bi bi-file-earmark me-1"></i>${escapeHtml(msg.message || 'Document')}</a></div>`;
    } else if (msg.message_type === 'location') {
        content = locationCardHtml(msg.location, msg.message);
    } else if (msg.message_type === 'contacts') {
        content = contactCardsHtml(msg.contacts || [], msg.message);
    }
    div.innerHTML = `
        ${messageMenuHtml(msg)}
        ${replyQuoteHtml(msg.reply_to)}
        ${content}
        ${reactionBadgeHtml(msg.reactions || [], msg.id)}
        <div class="wa-msg-time">${msg.time || ''} ${msg.direction === 'outgoing' ? '<i class="bi bi-check2 text-muted"></i>' : ''}</div>
    `;
    return div;
}

function insertMessageInOrder(msg) {
    if (msg.message_type === 'reaction' || document.getElementById(`msg-${msg.id}`)) return;

    const div = buildMessageElement(msg);
    if (!div) return;

    const chatContainer = document.getElementById('messagesContainer');
    if (!chatContainer) return;

    const nodes = chatContainer.querySelectorAll('.wa-msg[data-message-id]');
    for (let i = 0; i < nodes.length; i++) {
        const existingId = parseInt(nodes[i].dataset.messageId, 10);
        if (existingId > msg.id) {
            nodes[i].insertAdjacentElement('beforebegin', div);
            lastMessageId = Math.max(lastMessageId, msg.id);
            return;
        }
    }

    chatContainer.appendChild(div);
    lastMessageId = Math.max(lastMessageId, msg.id);
}

function mergeMessagesIntoChat(messages) {
    if (!Array.isArray(messages) || !messages.length) return;

    historyJumpLoading = true;
    messages
        .slice()
        .sort((a, b) => a.id - b.id)
        .forEach((msg) => insertMessageInOrder(msg));
    historyJumpLoading = false;
}

function appendMessage(msg) {
    if (msg.message_type === 'reaction') return;
    if (document.getElementById(`msg-${msg.id}`)) {
        if (msg.revoked || msg.message_type === 'revoked') {
            applyRevokedMessage(msg.id);
            return;
        }
        setMessageReactions(msg.id, msg.reactions || []);
        return;
    }

    const div = buildMessageElement(msg);
    if (!div) return;

    const wasNearBottom = isChatNearBottom(container);
    container.appendChild(div);

    if (msg.revoked || msg.message_type === 'revoked') {
        if (!historyJumpLoading && wasNearBottom) {
            scrollChatToBottom(false);
        } else if (!historyJumpLoading) {
            updateScrollToBottomBtn();
        }
        lastMessageId = Math.max(lastMessageId, msg.id);
        return;
    }

    if (mediaBatchUploadActive && msg.direction === 'outgoing') {
        // Scroll once after the whole batch finishes (keeps phone UI smooth).
    } else if (historyJumpLoading) {
        // Keep scroll position while merging history for jump-to-message.
    } else if (msg.direction === 'outgoing' || wasNearBottom) {
        scrollChatToBottom(false);
    } else if (msg.direction === 'incoming') {
        unreadWhileScrolledUp++;
        updateScrollToBottomBtn();
    } else {
        updateScrollToBottomBtn();
    }

    lastMessageId = Math.max(lastMessageId, msg.id);
}

var lastReactionPollAt = new Date(Date.now() - 15000).toISOString();
var chatPollInFlight = false;
var chatPollTimer = null;
var sidebarPollInFlight = false;
var sidebarRefreshTimer = null;
var CHAT_POLL_MS = {{ (int) config('services.whatsapp.chat_poll_ms', 3000) }};
var SIDEBAR_POLL_MS = {{ (int) config('services.whatsapp.sidebar_poll_ms', 4000) }};

function scheduleChatPoll(delayMs) {
    if (chatPollTimer) clearTimeout(chatPollTimer);
    chatPollTimer = setTimeout(() => {
        chatPollTimer = null;
        pollChatMessages();
    }, typeof delayMs === 'number' ? delayMs : 0);
}

function pollChatMessages() {
    if (document.hidden || chatPollInFlight) return;
    chatPollInFlight = true;

    const params = new URLSearchParams({
        after_id: String(lastMessageId || 0),
        since: lastReactionPollAt,
    });

    fetch(`${MESSAGES_URL}?${params}`, {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
    })
        .then(r => {
            if (!r.ok) throw new Error('poll_failed');
            return r.json();
        })
        .then(data => {
            let hadNewMessages = false;
            (data.messages || []).forEach(msg => {
                if (!document.getElementById(`msg-${msg.id}`)) {
                    hadNewMessages = true;
                    appendMessage(msg);
                    if (msg.direction === 'incoming' && document.hidden) {
                        if (typeof window.crmShowOsNotification === 'function') {
                            window.crmShowOsNotification(
                                'Shrishti Trip WhatsApp',
                                String(msg.message || 'New message').slice(0, 120),
                                window.location.href,
                                'wa-msg-' + msg.id
                            );
                        } else if ('Notification' in window && Notification.permission === 'granted') {
                            try {
                                const n = new Notification('Shrishti Trip WhatsApp', {
                                    body: String(msg.message || 'New message').slice(0, 120),
                                    tag: 'wa-msg-' + msg.id,
                                    renotify: true,
                                    requireInteraction: true,
                                });
                                n.onclick = () => { window.focus(); n.close(); };
                            } catch (e) {}
                        }
                    }
                } else {
                    setMessageReactions(msg.id, msg.reactions || []);
                }
            });
            (data.reaction_updates || []).forEach(update => {
                setMessageReactions(update.id, update.reactions || []);
            });
            (data.revoked_updates || []).forEach(update => {
                applyRevokedMessage(update.id);
            });
            if (data.server_time) {
                lastReactionPollAt = data.server_time;
            }
            if (hadNewMessages) {
                scheduleSidebarRefresh();
            }
        })
        .catch((err) => {
            console.warn('WhatsApp chat poll failed', err);
        })
        .finally(() => { chatPollInFlight = false; });
}

// Poll for new messages (pauses when tab is hidden to protect server resources)
if (window.chatPollInterval) clearInterval(window.chatPollInterval);
window.chatPollInterval = setInterval(pollChatMessages, CHAT_POLL_MS);
scheduleChatPoll(800);

document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
        scheduleChatPoll(300);
        refreshSidebarConversations();
    }
});

function ensureMediaViewerOnBody() {
    const viewer = document.getElementById('waMediaViewer');
    if (viewer && viewer.parentElement !== document.body) {
        document.body.appendChild(viewer);
    }
    return document.getElementById('waMediaViewer');
}

function openMediaViewer(src, type = 'image') {
    if (!src) return;
    const viewer = ensureMediaViewerOnBody();
    const body = document.getElementById('waMediaViewerBody');
    const title = document.getElementById('waMediaViewerTitle');
    const markBtn = document.getElementById('waMediaViewerMarkBtn');
    if (!viewer || !body) return;

    body.innerHTML = '';
    if (type === 'video') {
        title.textContent = 'Video';
        if (markBtn) markBtn.classList.add('d-none');
        const video = document.createElement('video');
        video.src = src;
        video.controls = true;
        video.autoplay = true;
        video.playsInline = true;
        video.setAttribute('playsinline', '');
        video.setAttribute('webkit-playsinline', '');
        body.appendChild(video);
    } else {
        title.textContent = 'Photo';
        if (markBtn) markBtn.classList.remove('d-none');
        const img = document.createElement('img');
        img.src = src;
        img.alt = 'Photo';
        img.draggable = false;
        img.id = 'waMediaViewerImage';
        body.appendChild(img);
    }

    viewer.classList.add('is-open');
    viewer.setAttribute('aria-hidden', 'false');
    document.documentElement.style.overflow = 'hidden';
    document.body.style.overflow = 'hidden';
    document.body.classList.add('wa-media-open');
}

function closeMediaViewer() {
    const viewer = document.getElementById('waMediaViewer');
    const body = document.getElementById('waMediaViewerBody');
    const markBtn = document.getElementById('waMediaViewerMarkBtn');
    if (!viewer) return;

    const playing = body?.querySelector('video');
    if (playing) {
        playing.pause();
        playing.removeAttribute('src');
        playing.load();
    }

    if (body) body.innerHTML = '';
    if (markBtn) markBtn.classList.add('d-none');
    viewer.classList.remove('is-open');
    viewer.setAttribute('aria-hidden', 'true');
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
    document.body.classList.remove('wa-media-open');
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeMediaViewer();
    }
});

// Mobile-friendly: tap image/video to open (works better than inline onclick on iOS)
(function bindWaMediaOpen() {
    ensureMediaViewerOnBody();

    const messages = document.getElementById('messagesContainer');
    if (messages) {
        messages.addEventListener('click', function (event) {
            const media = event.target.closest('[data-wa-media]');
            if (!media) return;
            event.preventDefault();
            event.stopPropagation();
            const type = media.getAttribute('data-wa-media') || 'image';
            const src = media.currentSrc || media.src || media.getAttribute('src');
            openMediaViewer(src, type);
        }, { passive: false });
    }

    const viewer = document.getElementById('waMediaViewer');
    if (viewer) {
        viewer.addEventListener('click', function (event) {
            if (event.target.closest('#waMediaViewerMarkBtn') || event.target.closest('.wa-media-viewer__mark')) {
                event.preventDefault();
                event.stopPropagation();
                markAndResendFromViewer();
                return;
            }
            if (event.target.closest('.wa-media-viewer__close')) {
                event.preventDefault();
                closeMediaViewer();
                return;
            }
            if (event.target.id === 'waMediaViewerBody' || event.target === viewer) {
                closeMediaViewer();
            }
        });
    }
})();

function assignAgent(userId) {
    fetch(ASSIGN_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        body: JSON.stringify({ user_id: userId || null })
    }).then(r => r.json()).then(d => {
        if (d.success) showToast('Agent assigned', 'success');
    });
}

function renderChatBadges() {
    const wrap = document.getElementById('chatStatusBadges');
    if (!wrap) return;

    const parts = [];
    if (conversationTags.includes('pending_payment')) {
        parts.push('<span class="wa-chat-status-badge pending" data-tag="pending_payment">Pending (Payment)</span>');
    }
    if (conversationTags.includes('paid')) {
        parts.push('<span class="wa-chat-status-badge paid" data-tag="paid">Paid</span>');
    }
    if (conversationTags.includes('important')) {
        parts.push('<span class="wa-chat-status-badge important" data-tag="important">Important</span>');
    }
    if (conversationStatus === 'closed') {
        parts.push('<span class="wa-chat-status-badge closed" data-status="closed">Closed</span>');
    } else if (conversationStatus === 'archived') {
        parts.push('<span class="wa-chat-status-badge archived" data-status="archived">Archived</span>');
    }
    wrap.innerHTML = parts.join('');
}

function syncTagMenuItem(tag, enabled) {
    const ids = {
        pending_payment: 'tagMenuPending',
        paid: 'tagMenuPaid',
        important: 'tagMenuImportant',
    };
    const btn = document.getElementById(ids[tag] || '');
    if (!btn) return;
    btn.classList.toggle('is-on', enabled);
    const action = btn.querySelector('.wa-tag-action');
    if (action) action.textContent = enabled ? 'Remove' : 'Add';
}

function refreshAllTagMenuItems() {
    syncTagMenuItem('pending_payment', conversationTags.includes('pending_payment'));
    syncTagMenuItem('paid', conversationTags.includes('paid'));
    syncTagMenuItem('important', conversationTags.includes('important'));
}

function toggleConversationTag(tag) {
    fetch(TAG_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        body: JSON.stringify({ tag })
    }).then(r => r.json()).then(d => {
        if (!d.success) {
            showToast('Could not update tag', 'danger');
            return;
        }

        conversationTags = Array.isArray(d.tags) ? d.tags : [];
        refreshAllTagMenuItems();
        renderChatBadges();
        showToast((d.enabled ? 'Added: ' : 'Removed: ') + (d.tag_label || tag), 'success');
    }).catch(() => showToast('Could not update tag', 'danger'));
}

function updateStatus(status) {
    fetch(STATUS_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        body: JSON.stringify({ status })
    }).then(r => r.json()).then(d => {
        if (!d.success) {
            showToast('Could not update status', 'danger');
            return;
        }

        conversationStatus = d.status || status;
        if (Array.isArray(d.tags)) conversationTags = d.tags;
        renderChatBadges();
        showToast('Status updated to ' + (d.status_label || status), 'success');

        if (status === 'closed' || status === 'archived') {
            setTimeout(() => { location.href = '{{ route('whatsapp.inbox') }}'; }, 700);
        }
    });
}

function toggleWaAiReply(enabled) {
    fetch(AI_REPLY_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        body: JSON.stringify({ ai_reply_enabled: enabled })
    }).then(r => r.json()).then(d => {
        if (d.success) {
            showToast(enabled ? 'Auto AI reply on for this chat' : 'Auto AI reply off for this chat', 'success');
        } else {
            showToast(d.message || 'Could not update', 'danger');
            document.getElementById('waAiToggle').checked = !enabled;
        }
    }).catch(() => {
        showToast('Could not update', 'danger');
        document.getElementById('waAiToggle').checked = !enabled;
    });
}

function createLead() {
    const name = document.getElementById('leadName').value.trim();
    if (!name) { showToast('Name is required', 'danger'); return; }

    fetch(CREATE_LEAD_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        body: JSON.stringify({
            name,
            email: document.getElementById('leadEmail').value,
            lead_source_id: document.getElementById('leadSourceId').value || null,
            lead_stage_id: document.getElementById('leadStageId').value || null,
        })
    }).then(r => r.json()).then(d => {
        if (d.success) {
            showToast('Lead created!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('createLeadModal')).hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(d.message || 'Error', 'danger');
        }
    });
}

function addFollowup() {
    const title = document.getElementById('fuTitle').value.trim();
    const dueDate = document.getElementById('fuDueDate').value;
    if (!title || !dueDate) { showToast('Title and due date are required', 'danger'); return; }

    fetch(FOLLOWUP_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
        body: JSON.stringify({
            lead_id: {{ $conversation->lead_id ?? 'null' }},
            conversation_id: CONV_ID,
            title,
            due_date: dueDate,
            assigned_user_id: document.getElementById('fuAssignedUser').value || null,
        })
    }).then(r => r.json()).then(d => {
        if (d.success) {
            showToast('Followup scheduled!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('addFollowupModal')).hide();
        } else {
            showToast('Error scheduling followup', 'danger');
        }
    });
}

function scheduleSidebarRefresh() {
    if (sidebarRefreshTimer) {
        clearTimeout(sidebarRefreshTimer);
    }
    sidebarRefreshTimer = setTimeout(() => {
        sidebarRefreshTimer = null;
        refreshSidebarConversations();
    }, 350);
}

function refreshSidebarConversations() {
    if (document.hidden || sidebarPollInFlight) return;
    const list = document.getElementById('sidebarConvList');
    if (!list) return;

    sidebarPollInFlight = true;
    fetch('{{ route('whatsapp.conversations.list') }}?status=all')
        .then(r => r.json())
        .then(data => {
            if (!data.conversations.length) {
                list.innerHTML = '<div class="text-center py-3 text-muted small">No conversations</div>';
                return;
            }
            list.innerHTML = data.conversations.map(c => `
                <a href="${c.url}" class="wa-conv-item ${c.id == CONV_ID ? 'active' : ''}">
                    <div class="wa-conv-avatar">${(c.contact_name || c.phone_number).charAt(0).toUpperCase()}</div>
                    <div class="wa-conv-info">
                        <div class="wa-conv-name">${c.contact_name || c.phone_number}</div>
                        <div class="wa-conv-preview">${c.last_message || ''}</div>
                    </div>
                    <div class="wa-conv-meta">
                        ${Number(c.unread_count) > 0 ? `<span class="wa-badge" title="${c.unread_count} unread messages" aria-label="${c.unread_count} unread messages">${Number(c.unread_count) > 99 ? '99+' : c.unread_count}</span>` : ''}
                    </div>
                </a>
            `).join('');
        })
        .catch((err) => {
            console.warn('WhatsApp sidebar poll failed', err);
        })
        .finally(() => { sidebarPollInFlight = false; });
}

// Load sidebar conversations (paused when tab is hidden)
refreshSidebarConversations();
if (window.sidebarPollInterval) clearInterval(window.sidebarPollInterval);
window.sidebarPollInterval = setInterval(refreshSidebarConversations, SIDEBAR_POLL_MS);

function showToast(msg, type = 'success') {
    const container = document.getElementById('toastContainer');
    const id = 'toast_' + Date.now();
    container.insertAdjacentHTML('beforeend', `
        <div id="${id}" class="toast align-items-center text-bg-${type} border-0 show" role="alert">
            <div class="d-flex">
                <div class="toast-body">${msg}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `);
    setTimeout(() => document.getElementById(id)?.remove(), 3000);
}

initVoiceRecorder();
renderPinnedBar(pinnedMessageState);
</script>
@endpush
