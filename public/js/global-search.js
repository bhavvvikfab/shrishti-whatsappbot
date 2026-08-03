document.addEventListener('DOMContentLoaded', function () {
    // Premium Global Search JS
    const searchInput = document.getElementById('globalSearchInput');
    const searchResults = document.getElementById('globalSearchResults');
    const searchWrapper = document.getElementById('globalSearchWrapper');
    let debounceTimeout = null;
    let activeIndex = -1;
    let searchAbortController = null;

    if (searchInput && searchResults) {
        const moduleLinks = [
            {
                url: searchResults.getAttribute('data-whatsapp-url'),
                title: 'WhatsApp Chat Inbox',
                subtitle: 'Open WhatsApp conversations and replies',
                icon: 'fab fa-whatsapp',
                style: 'background: rgba(34, 197, 94, 0.1); color: #22c55e;'
            },
            {
                url: searchResults.getAttribute('data-whatsapp-analytics-url'),
                title: 'WhatsApp Analytics',
                subtitle: 'Open WhatsApp analytics dashboard',
                icon: 'bi bi-bar-chart',
                style: 'background: rgba(14, 165, 233, 0.1); color: #0ea5e9;'
            },
            {
                url: searchResults.getAttribute('data-settings-url'),
                title: 'WhatsApp Settings',
                subtitle: 'Open WhatsApp configuration and credentials',
                icon: 'fa-solid fa-gear',
                style: 'background: rgba(100, 116, 139, 0.1); color: #64748b;'
            }
        ].filter(module => module.url);

        // Focus keybind: '/' or 'Ctrl + K'
        document.addEventListener('keydown', function (e) {
            const activeElement = document.activeElement;
            const isInput = activeElement.tagName === 'INPUT' || activeElement.tagName === 'TEXTAREA' || activeElement.isContentEditable;
            
            if (!isInput) {
                if (e.key === '/' || (e.ctrlKey && e.key === 'k')) {
                    e.preventDefault();
                    searchInput.focus();
                }
            }
        });

        function getMatchingModules(query) {
            const normalizedQuery = query.trim().toLowerCase();

            if (!normalizedQuery) {
                return moduleLinks;
            }

            return moduleLinks.filter(module => {
                const searchableText = `${module.title} ${module.subtitle}`.toLowerCase();
                return searchableText.includes(normalizedQuery);
            });
        }

        function performSearch(query) {
            if (searchAbortController) {
                searchAbortController.abort();
            }
            searchAbortController = new AbortController();
            const signal = searchAbortController.signal;
            const matchingModules = getMatchingModules(query);

            fetch(`/global-search?q=${encodeURIComponent(query)}`, { signal })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderResults(data.results || [], matchingModules);
                    } else {
                        renderNoResults(matchingModules);
                    }
                })
                .catch(error => {
                    if (error.name !== 'AbortError') {
                        console.error('Error fetching search results:', error);
                    }
                });
        }

        // Show all modules or existing search results on focus/click
        const showDropdownOnFocus = function() {
            const query = searchInput.value.trim();
            if (query.length < 2) {
                renderModulesList(query);
            } else {
                if (searchResults.innerHTML.trim() === '') {
                    performSearch(query);
                } else {
                    searchResults.classList.add('show');
                }
            }
        };

        searchInput.addEventListener('focus', showDropdownOnFocus);
        searchInput.addEventListener('click', showDropdownOnFocus);

        // Input key listener
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimeout);
            const query = this.value.trim();

            if (query.length < 2) {
                renderModulesList(query);
                return;
            }

            debounceTimeout = setTimeout(() => {
                performSearch(query);
            }, 500); // Increased debounce delay to 500ms
        });

        // Keydown navigation in the results
        searchInput.addEventListener('keydown', function (e) {
            const items = searchResults.querySelectorAll('.search-result-item');
            if (items.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = (activeIndex + 1) % items.length;
                updateActiveItem(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = (activeIndex - 1 + items.length) % items.length;
                updateActiveItem(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (activeIndex >= 0 && activeIndex < items.length) {
                    items[activeIndex].click();
                }
            } else if (e.key === 'Escape') {
                closeDropdown();
            }
        });

        // Close dropdown on click outside
        document.addEventListener('click', function (e) {
            if (searchWrapper && !searchWrapper.contains(e.target)) {
                closeDropdown();
            }
        });

        function renderModuleItems(modules) {
            return modules.map(function (module) {
                return `
                    <a href="${module.url}" class="search-result-item">
                        <div class="search-result-icon" style="${module.style}">
                            <i class="${module.icon}"></i>
                        </div>
                        <div class="search-result-info">
                            <div class="search-result-title">${escapeHtml(module.title)}</div>
                            <div class="search-result-subtitle">${escapeHtml(module.subtitle)}</div>
                        </div>
                    </a>`;
            }).join('');
        }

        function renderResults(results, matchingModules = []) {
            // Group results by type
            const groups = {};
            results.forEach(item => {
                if (!groups[item.type]) {
                    groups[item.type] = [];
                }
                groups[item.type].push(item);
            });

            let html = '';
            if (matchingModules.length > 0) {
                html += `
                    <div class="p-2">
                        <div class="search-results-section-header">Matching Modules</div>
                        <div class="d-flex flex-column gap-1">
                            ${renderModuleItems(matchingModules)}
                        </div>
                    </div>
                `;
            }

            for (const [type, items] of Object.entries(groups)) {
                html += `<div class="search-results-section-header">${type}s</div>`;
                items.forEach(item => {
                    html += `
                        <a href="${item.url}" class="search-result-item">
                            <div class="search-result-icon">
                                <i class="${item.icon}"></i>
                            </div>
                            <div class="search-result-info">
                                <div class="search-result-title">${escapeHtml(item.title)}</div>
                                <div class="search-result-subtitle">${escapeHtml(item.subtitle)}</div>
                            </div>
                            <span class="badge ${item.badge_class} search-result-type-badge">${item.badge}</span>
                        </a>
                    `;
                });
            }

            if (html === '') {
                renderNoResults(matchingModules);
                return;
            }

            searchResults.innerHTML = html;
            searchResults.classList.add('show');
            activeIndex = -1;
        }

        function renderNoResults(matchingModules = []) {
            if (matchingModules.length > 0) {
                searchResults.innerHTML = `
                    <div class="p-2">
                        <div class="search-results-section-header">Matching Modules</div>
                        <div class="d-flex flex-column gap-1">
                            ${renderModuleItems(matchingModules)}
                        </div>
                    </div>
                `;
            } else {
                searchResults.innerHTML = `
                    <div class="p-3 text-center text-muted small">
                        <i class="bi bi-exclamation-circle d-block mb-1 fs-5"></i>
                        No matching records or modules found.
                    </div>
                `;
            }
            searchResults.classList.add('show');
            activeIndex = -1;
        }

        function renderModulesList(query = '') {
            let modulesHtml = renderModuleItems(getMatchingModules(query));

            if (modulesHtml === '') {
                searchResults.innerHTML = `
                    <div class="p-3 text-center text-muted small">
                        No matching modules found.
                    </div>
                `;
            } else {
                searchResults.innerHTML = `
                    <div class="p-2">
                        <div class="search-results-section-header">${query ? 'Matching Modules' : 'Searchable Modules'}</div>
                        <div class="d-flex flex-column gap-1">
                            ${modulesHtml}
                        </div>
                    </div>
                `;
            }
            searchResults.classList.add('show');
            activeIndex = -1;
        }

        function updateActiveItem(items) {
            items.forEach((item, idx) => {
                if (idx === activeIndex) {
                    item.classList.add('active');
                    item.scrollIntoView({ block: 'nearest' });
                } else {
                    item.classList.remove('active');
                }
            });
        }

        function closeDropdown() {
            searchResults.classList.remove('show');
            activeIndex = -1;
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;')
                      .replace(/</g, '&lt;')
                      .replace(/>/g, '&gt;')
                      .replace(/"/g, '&quot;')
                      .replace(/'/g, '&#039;');
        }
    }
});
