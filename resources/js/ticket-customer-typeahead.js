// Customer typeahead for the ticket filters

function initCustomerTypeahead() {
    var searchInput = document.getElementById('customer_search');
    var hiddenInput = document.getElementById('customer_id');
    var resultsDiv = document.getElementById('customer_results');
    var clearBtn = document.getElementById('clear_customer');
    var routeEl = document.querySelector('[data-customer-search-url]');
    var route = routeEl ? routeEl.getAttribute('data-customer-search-url') : '';

    if (!searchInput) {
        return;
    }

    var debounceTimer = null;
    var requestToken = 0;

    var hideResults = function () {
        if (resultsDiv) {
            resultsDiv.classList.add('hidden');
        }
    };

    var clearCustomer = function () {
        if (searchInput) {
            searchInput.value = '';
        }
        if (hiddenInput) {
            hiddenInput.value = '';
        }
        if (clearBtn) {
            clearBtn.classList.add('hidden');
        }
        if (searchInput) {
            searchInput.focus();
        }
    };

    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        var query = this.value.trim();

        if (hiddenInput) {
            hiddenInput.value = '';
        }
        if (clearBtn) {
            clearBtn.classList.add('hidden');
        }

        if (query.length < 2) {
            hideResults();
            return;
        }

        var token = ++requestToken;

        debounceTimer = setTimeout(function () {
            fetch(route + '?q=' + encodeURIComponent(query), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
            .then(function (response) {
                if (! response.ok) {
                    throw new Error('Search failed');
                }
                return response.json();
            })
            .then(function (customers) {
                if (token !== requestToken) {
                    return;
                }
                if (! customers || customers.length === 0) {
                    hideResults();
                    return;
                }

                var fragment = document.createDocumentFragment();

                customers.forEach(function (c) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'w-full px-3 py-2 text-left hover:bg-gray-100 text-xs';
                    btn.dataset.id = c.id;
                    btn.dataset.name = c.name;
                    btn.dataset.email = c.email;

                    var label = document.createElement('span');
                    label.textContent = c.name + ' <' + c.email + '>';
                    btn.appendChild(label);

                    fragment.appendChild(btn);
                });

                resultsDiv.innerHTML = '';
                resultsDiv.appendChild(fragment);
                resultsDiv.classList.remove('hidden');
            })
            .catch(function () {
                if (token !== requestToken) {
                    return;
                }
                hideResults();
            });
        }, 200);
    });

    if (resultsDiv) {
        resultsDiv.addEventListener('click', function (e) {
            var btn = e.target.closest('button[data-id]');
            if (! btn) {
                return;
            }
            if (searchInput) {
                searchInput.value = btn.dataset.name;
            }
            if (hiddenInput) {
                hiddenInput.value = btn.dataset.id;
            }
            if (clearBtn) {
                clearBtn.classList.remove('hidden');
            }
            hideResults();
        });
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            clearCustomer();
        });
    }

    document.addEventListener('click', function (e) {
        if (searchInput.contains(e.target)) {
            return;
        }
        if (resultsDiv && resultsDiv.contains(e.target)) {
            return;
        }
        if (clearBtn && clearBtn.contains(e.target)) {
            return;
        }
        hideResults();
    });

    if (hiddenInput && hiddenInput.value) {
        if (clearBtn) {
            clearBtn.classList.remove('hidden');
        }
    }
}

window.initCustomerTypeahead = initCustomerTypeahead;
initCustomerTypeahead();
