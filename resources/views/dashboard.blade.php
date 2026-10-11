@extends('layouts.app')

@section('title', 'Front Desk Dashboard')

@section('content')
    @php
        $fmt = function (?int $sec) {
            if ($sec === null) {
                return '--:--:--';
            }
            $sign = $sec < 0 ? '-' : '';
            $t = abs($sec);

            return sprintf('%s%dh %dm %ds', $sign, intdiv($t, 3600), intdiv($t % 3600, 60), $t % 60);
        };

        $state = function ($bay) use ($expiringMinutes) {
            if (! $bay->is_active || $bay->is_out_of_order) {
                return 'maintenance';
            }
            $session = $bay->activeSession;
            if ($session && ($session->isExpired() || $session->isExpiringSoon($expiringMinutes))) {
                return 'expiring';
            }
            if ($session) {
                return 'occupied';
            }

            return $bay->pendingCustomer()->exists() ? 'pending' : 'available';
        };

        $addons = $services
            ->where('category', '!=', \App\Models\Service::CATEGORY_BAY_TIME)
            ->values();

        $servicesJson = $services
            ->map(fn ($s) => ['id' => $s->id, 'rate' => (float) $s->rate, 'name' => $s->name, 'category' => $s->category])
            ->values();
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h4 class="mb-0 fw-bold">Dashboard <span class="text-muted small">(Front Desk)</span></h4>
            <div class="text-muted small">Today is {{ now()->format('d/m/Y h:i:s a') }} &middot; Minimum stay 1h 30m</div>
        </div>
        <div class="small">
            <span class="legend-dot bg-success"></span>Available
            <span class="legend-dot bg-primary ms-2"></span>Allocated
            <span class="legend-dot bg-warning ms-2"></span>Pending
            <span class="legend-dot bg-danger ms-2"></span>Soon to expire
            <span class="legend-dot bg-secondary ms-2"></span>Maintenance
        </div>
    </div>

    @if ($unsettled->isNotEmpty())
        <div class="card border-0 shadow-sm border-start border-start-danger border-4 mb-3">
            <div class="card-header bg-white fw-semibold">
                Unsettled receipts
                <span class="badge text-bg-danger ms-1">{{ $unsettled->count() }}</span>
                <span class="text-muted small fw-normal">- sessions the system auto-closed past end time; take payment below.</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Receipt</th>
                        <th>Session / Bay</th>
                        <th>Customer</th>
                        <th>Closed at</th>
                        <th class="text-end">Amount due</th>
                        <th class="text-end">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($unsettled as $transaction)
                        <tr>
                            <td>
                                <a href="{{ route('receipts.show', $transaction) }}" target="_blank" class="text-decoration-none">
                                    {{ $transaction->receipt_no }} &#128424;
                                </a>
                            </td>
                            <td>{{ $transaction->baySession?->session_no ?? '-' }} / {{ $transaction->baySession?->bay?->code ?? '-' }}</td>
                            <td>{{ $transaction->customer?->getFullNameAttribute() ?? 'Walk-in' }}</td>
                            <td>{{ $transaction->baySession?->actual_end_at?->format('d/m h:i a') ?? $transaction->created_at->format('d/m h:i a') }}</td>
                            <td class="text-end fw-semibold">&#8369;{{ number_format((float) $transaction->total, 2) }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-success"
                                        data-bs-toggle="modal" data-bs-target="#settleModal"
                                        data-settle-url="{{ route('receipts.settle', $transaction) }}"
                                        data-receipt-no="{{ $transaction->receipt_no }}"
                                        data-customer="{{ $transaction->customer?->getFullNameAttribute() ?? 'Walk-in' }}"
                                        data-total="{{ (float) $transaction->total }}">
                                    Take payment
                                </button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="row g-3 mb-4" data-bay-status-url="{{ route('dashboard.status') }}">
        @foreach ($bays as $bay)
            @php
                $session = $bay->activeSession;
                $pending = $bay->pendingCustomer;
                $current = $state($bay);
            @endphp
            <div class="col-6 col-md-4 col-xl-3">
                <div class="card bay-card border-{{ $current }} h-100"
                     data-bay-id="{{ $bay->id }}"
                     data-session-id="{{ $session?->id ?? '' }}"
                     data-pending="{{ $pending ? '1' : '0' }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <span class="bay-code">{{ $bay->code }}</span>
                            @if ($current === 'available')
                                <span class="badge text-bg-success" data-badge>Free</span>
                            @elseif ($current === 'occupied')
                                <span class="badge text-bg-primary" data-badge>Busy</span>
                            @elseif ($current === 'pending')
                                <span class="badge text-bg-warning text-dark" data-badge>Pending</span>
                            @elseif ($current === 'expiring')
                                <span class="badge text-bg-danger" data-badge>{{ $session && $session->isExpired() ? 'Overstay' : 'Soon to expire' }}</span>
                            @else
                                <span class="badge text-bg-secondary" data-badge>Maintenance</span>
                            @endif
                        </div>

                        <div class="bay-timer mt-2 {{ $session && $session->isExpired() ? 'text-danger' : '' }}" data-timer>
                            {{ $session ? $fmt($session->secondsRemaining()) : ($current === 'pending' ? 'Waiting' : '--:--:--') }}
                        </div>

                        <div class="bay-customer text-muted mb-2" data-customer>
                            @if ($session)
                                {{ $session->session_no }} - {{ $session->customer->getFullNameAttribute() }}
                            @elseif ($pending)
                                Pending: {{ $pending->customer->getFullNameAttribute() }}
                            @else
                                Available
                            @endif
                        </div>

                        <div class="d-grid gap-1">
                            @if ($current === 'available')
                                <button type="button" class="btn btn-success btn-sm"
                                        data-bs-toggle="modal" data-bs-target="#allocateModal"
                                        data-bay-id="{{ $bay->id }}" data-bay-code="{{ $bay->code }}">
                                    Allocate spot
                                </button>
                            @elseif ($current === 'pending')
                                <button type="button" class="btn btn-success btn-sm"
                                        data-bs-toggle="modal" data-bs-target="#allocateModal"
                                        data-bay-id="{{ $bay->id }}" data-bay-code="{{ $bay->code }}"
                                        data-customer-id="{{ $pending->customer_id }}">
                                    Seat pending customer
                                </button>
                                <form action="{{ route('queue.cancel', $pending) }}" method="POST" class="m-0">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary btn-sm w-100">Cancel pending</button>
                                </form>
                            @elseif ($session)
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-warning btn-sm flex-fill text-dark"
                                            data-bs-toggle="modal" data-bs-target="#extendModal"
                                            data-session-id="{{ $session->id }}" data-session-no="{{ $session->session_no }}">
                                        Add Time
                                    </button>
                                    <button type="button" class="btn btn-primary btn-sm flex-fill"
                                            data-bs-toggle="modal" data-bs-target="#checkoutModal"
                                            data-session-id="{{ $session->id }}"
                                            data-session-no="{{ $session->session_no }}"
                                            data-bay-code="{{ $bay->code }}"
                                            data-customer="{{ $session->customer->getFullNameAttribute() }}"
                                            data-minutes="{{ $session->totalMinutes() }}">
                                        Checkout
                                    </button>
                                </div>
                                <button type="button" class="btn btn-outline-danger btn-sm"
                                        data-bs-toggle="modal" data-bs-target="#allocateModal"
                                        disabled>
                                    Occupied
                                </button>
                            @else
                                <button type="button" class="btn btn-outline-secondary btn-sm" disabled>{{ $bay->code }}</button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Waiting list (Pending Spots)</div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Bay</th>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Queued at</th>
                            <th class="text-end">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($openQueue as $entry)
                            <tr>
                                <td>{{ $entry->bay->code }}</td>
                                <td>{{ $entry->customer->getFullNameAttribute() }}</td>
                                <td>{{ $entry->customer->contact_no ?? '-' }}</td>
                                <td>{{ $entry->queued_at->format('h:i:s a') }}</td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal" data-bs-target="#allocateModal"
                                            data-bay-id="{{ $entry->bay_id }}" data-bay-code="{{ $entry->bay->code }}"
                                            data-customer-id="{{ $entry->customer_id }}">
                                        Allocate
                                    </button>
                                    <form action="{{ route('queue.cancel', $entry) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Cancel Bay</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No customers waiting.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Quick Bay Allocation (New Customer)</div>
                <div class="card-body">
                    <p class="text-muted small mb-2">
                        Use <strong>Allocate spot</strong> on any free bay to register a new customer or
                        search an existing one by Id or last name.
                    </p>
                    <button type="button" class="btn btn-success w-100"
                            data-bs-toggle="modal" data-bs-target="#allocateModal"
                            data-bay-id="" data-bay-code="Select a bay">
                        Allocate spot
                    </button>
                    <hr>
                    <div class="small text-muted">
                        Bay rate: <strong>&#8369;{{ number_format((float) ($bayService?->rate ?? 0), 2) }}/hr</strong><br>
                        Minimum stay: <strong>{{ $minMinutes }} minutes</strong><br>
                        Operating hours: {{ \App\Models\Setting::getValue('operating_open') }} -
                        {{ \App\Models\Setting::getValue('operating_close') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Allocate --}}
    <div class="modal fade" id="allocateModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="POST" id="allocate-form" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Allocate spot - <span data-allocate-bay>Bay</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Search Customer by Id or LastName</label>
                    <input type="text" class="form-control mb-2" id="customer-search"
                           placeholder="Type to filter..." autocomplete="off">
                    <select class="form-select mb-3" name="customer_id" id="customer-select" size="4">
                        <option value="">-- Select customer --</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}"
                                    data-search="{{ strtolower($customer->id.' '.$customer->last_name.' '.$customer->first_name) }}">
                                {{ $customer->id }} - {{ $customer->getFullNameAttribute() }}
                            </option>
                        @endforeach
                    </select>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="new-customer" value="1">
                        <label class="form-check-label" for="new-customer">New customer (not in the list)</label>
                    </div>

                    <div class="row g-2 d-none" id="new-customer-fields">
                        <div class="col-6">
                            <input type="text" class="form-control" name="first_name" placeholder="Customer FirstName">
                        </div>
                        <div class="col-6">
                            <input type="text" class="form-control" name="last_name" placeholder="Customer LastName">
                        </div>
                        <div class="col-12">
                            <input type="text" class="form-control" name="contact_no" placeholder="Contact No">
                        </div>
                    </div>

                    <hr>
                    <label class="form-label">Duration (min. {{ $minMinutes }}m)</label>
                    <div class="d-flex gap-2 mb-2">
                        <button type="button" class="btn btn-outline-primary btn-sm duration-btn" data-minutes="{{ $minMinutes }}">
                            {{ intdiv($minMinutes, 60) }}h {{ $minMinutes % 60 }}m
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm duration-btn" data-minutes="120">2h 0m</button>
                        <button type="button" class="btn btn-outline-primary btn-sm duration-btn" data-minutes="180">3h 0m</button>
                    </div>
                    <input type="number" class="form-control" id="duration-input"
                           name="minutes" min="{{ $minMinutes }}" max="720" value="{{ $minMinutes }}">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Allocate spot</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Add Time --}}
    <div class="modal fade" id="extendModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <form class="modal-content" method="POST" id="extend-form" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Add Time - <span data-extend-session>Session</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2">Add the preferred time to extend the customer stay.</p>
                    <div class="d-grid gap-2 mb-3">
                        @foreach ($extensionOptions as $option)
                            <button type="button" class="btn btn-outline-primary extension-btn" data-minutes="{{ $option }}">
                                @if ($option >= 60)
                                    +{{ intdiv($option, 60) }}h{{ $option % 60 ? $option % 60 : '' }}m
                                @else
                                    +{{ $option }}m
                                @endif
                            </button>
                        @endforeach
                    </div>
                    <input type="number" class="form-control" id="extension-input" name="minutes"
                           min="5" max="720" value="{{ $extensionOptions[0] ?? 15 }}">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Time</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Checkout --}}
    <div class="modal fade" id="checkoutModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form class="modal-content" method="POST" id="checkout-form" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Checkout - <span data-checkout-session>Session</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-6">
                            <div class="text-muted small">Bay</div>
                            <div class="fw-semibold" data-checkout-bay>-</div>
                        </div>
                        <div class="col-6">
                            <div class="text-muted small">Customer</div>
                            <div class="fw-semibold" data-checkout-customer>-</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center bg-light rounded p-2 mb-3">
                        <span class="small text-muted">Bay time charge</span>
                        <strong id="checkout-total">&#8369;0.00</strong>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Add-on services</label>
                        <div class="row g-2">
                            @foreach ($addons as $index => $addon)
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text">{{ $addon->name }}</span>
                                        <input type="hidden" name="addons[{{ $index }}][service_id]" value="{{ $addon->id }}">
                                        <input type="number" class="form-control addon-qty" name="addons[{{ $index }}][quantity]"
                                               min="0" max="999" value="0" data-rate="{{ $addon->rate }}">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">Payment Method</label>
                            <select class="form-select" name="payment_method">
                                <option value="cash">Cash</option>
                                <option value="gcash">GCash</option>
                                <option value="maya">Maya</option>
                                <option value="card">Card</option>
                                <option value="e-banking">E-Banking</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Discount</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="discount" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Payment Amount</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="amount_tendered" id="amount-tendered" required>
                        </div>
                    </div>
                    <div class="text-end small text-muted mt-2">
                        Change: <strong id="checkout-change">&#8369;0.00</strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Complete checkout</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Settle (unpaid receipt left by the auto-finish sweep) --}}
    <div class="modal fade" id="settleModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="POST" id="settle-form" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Take payment - <span data-settle-receipt>Receipt</span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center bg-light rounded p-2 mb-3">
                        <span class="small text-muted">Amount due</span>
                        <strong id="settle-total">&#8369;0.00</strong>
                    </div>
                    <div class="mb-3">
                        <div class="text-muted small">Customer</div>
                        <div class="fw-semibold" data-settle-customer>-</div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Payment Method</label>
                            <select class="form-select" name="payment_method">
                                <option value="cash">Cash</option>
                                <option value="gcash">GCash</option>
                                <option value="maya">Maya</option>
                                <option value="card">Card</option>
                                <option value="e-banking">E-Banking</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Amount</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="amount_tendered"
                                   id="settle-tendered" required>
                        </div>
                    </div>
                    <div class="text-end small text-muted mt-2">
                        Change: <strong id="settle-change">&#8369;0.00</strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Complete payment</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const DEGMS = {
            bayRate: {{ (float) ($bayService?->rate ?? 0) }},
            minMinutes: {{ $minMinutes }},
            services: @json($servicesJson),
        };

        const peso = (value) => '₱' + Number(value || 0).toFixed(2);

        // ---- Allocate -----------------------------------------------------
        const allocateForm = document.getElementById('allocate-form');
        const allocateBayLabel = document.querySelector('[data-allocate-bay]');
        const customerSelect = document.getElementById('customer-select');
        const customerSearch = document.getElementById('customer-search');
        const newCustomer = document.getElementById('new-customer');
        const newCustomerFields = document.getElementById('new-customer-fields');
        const durationInput = document.getElementById('duration-input');

        // Bootstrap fires show.bs.modal on the modal element itself, not on the
        // button that opened it - so read the opener from event.relatedTarget.
        document.getElementById('allocateModal').addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            const bayId = trigger?.dataset.bayId || '';
            const bayCode = trigger?.dataset.bayCode || 'Select a bay';
            const customerId = trigger?.dataset.customerId || '';

            allocateBayLabel.textContent = bayCode;
            if (bayId) {
                allocateForm.setAttribute('action', `{{ url('bays') }}/${bayId}/allocate`);
            } else {
                allocateForm.removeAttribute('action');
            }

            customerSelect.value = customerId;
            newCustomer.checked = false;
            newCustomerFields.classList.add('d-none');
            customerSelect.disabled = false;
            customerSelect.querySelectorAll('option').forEach((o) => o.hidden = false);
            customerSearch.value = '';
            durationInput.value = DEGMS.minMinutes;
        });

        // The side-panel button opens this modal without a bay chosen yet.
        allocateForm.addEventListener('submit', (event) => {
            if (! allocateForm.getAttribute('action')) {
                event.preventDefault();
                alert('Please choose a bay first - click Allocate spot on a free bay.');
            }
        });

        customerSearch.addEventListener('input', () => {
            const term = customerSearch.value.toLowerCase().trim();
            customerSelect.querySelectorAll('option').forEach((option) => {
                if (!option.dataset.search) return;
                option.hidden = term !== '' && !option.dataset.search.includes(term);
            });
        });

        newCustomer.addEventListener('change', () => {
            newCustomerFields.classList.toggle('d-none', !newCustomer.checked);
            customerSelect.disabled = newCustomer.checked;
            if (newCustomer.checked) {
                customerSelect.value = '';
            }
        });

        document.querySelectorAll('.duration-btn').forEach((btn) => {
            btn.addEventListener('click', () => { durationInput.value = btn.dataset.minutes; });
        });

        // ---- Add Time -----------------------------------------------------
        const extendForm = document.getElementById('extend-form');
        const extensionInput = document.getElementById('extension-input');

        document.getElementById('extendModal').addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            if (! trigger?.dataset.sessionId) {
                extendForm.removeAttribute('action');
                return;
            }
            extendForm.setAttribute('action', `{{ url('bay-sessions') }}/${trigger.dataset.sessionId}/extend`);
            document.querySelector('[data-extend-session]').textContent = trigger.dataset.sessionNo;
            extensionInput.value = {{ $extensionOptions[0] ?? 15 }};
        });

        document.querySelectorAll('.extension-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                extensionInput.value = btn.dataset.minutes;
                document.querySelectorAll('.extension-btn').forEach((b) => b.classList.remove('active', 'btn-primary'));
                btn.classList.add('active', 'btn-primary');
            });
        });

        // ---- Checkout -----------------------------------------------------
        const checkoutForm = document.getElementById('checkout-form');
        const tendered = document.getElementById('amount-tendered');
        const totalEl = document.getElementById('checkout-total');
        const changeEl = document.getElementById('checkout-change');
        let bayMinutes = DEGMS.minMinutes;

        const computeTotal = () => {
            const billed = Math.max(DEGMS.minMinutes, bayMinutes);
            let total = DEGMS.bayRate * billed / 60;

            document.querySelectorAll('.addon-qty').forEach((input) => {
                total += Number(input.value || 0) * Number(input.dataset.rate || 0);
            });

            const discount = Number(checkoutForm.querySelector('[name="discount"]').value || 0);
            total = Math.max(0, total - discount);

            totalEl.textContent = peso(total);
            const change = Number(tendered.value || 0) - total;
            changeEl.textContent = peso(change >= 0 ? change : 0);

            return total;
        };

        document.getElementById('checkoutModal').addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            if (! trigger?.dataset.sessionId) {
                checkoutForm.removeAttribute('action');
                return;
            }
            checkoutForm.setAttribute('action', `{{ url('bay-sessions') }}/${trigger.dataset.sessionId}/checkout`);
            document.querySelector('[data-checkout-session]').textContent = trigger.dataset.sessionNo;
            document.querySelector('[data-checkout-bay]').textContent = trigger.dataset.bayCode;
            document.querySelector('[data-checkout-customer]').textContent = trigger.dataset.customer;
            bayMinutes = Number(trigger.dataset.minutes || DEGMS.minMinutes);
            tendered.value = '';
            document.querySelectorAll('.addon-qty').forEach((i) => i.value = 0);
            checkoutForm.querySelector('[name="discount"]').value = 0;
            computeTotal();
        });

        document.querySelectorAll('.addon-qty, [name="discount"]').forEach((input) => {
            input.addEventListener('input', computeTotal);
        });
        tendered.addEventListener('input', computeTotal);

        // Drop untouched add-ons so validation only sees real quantities.
        checkoutForm.addEventListener('submit', () => {
            document.querySelectorAll('.addon-qty').forEach((input) => {
                if (Number(input.value || 0) === 0) {
                    input.disabled = true;
                    const hidden = input.parentElement.querySelector('input[type="hidden"]');
                    if (hidden) hidden.disabled = true;
                }
            });
        });

        // ---- Settle (auto-closed unpaid receipts) -----------------------
        const settleForm = document.getElementById('settle-form');
        const settleTendered = document.getElementById('settle-tendered');
        const settleTotalEl = document.getElementById('settle-total');
        const settleChangeEl = document.getElementById('settle-change');
        let settleDue = 0;

        const computeSettleChange = () => {
            const change = Number(settleTendered.value || 0) - settleDue;
            settleChangeEl.textContent = peso(change >= 0 ? change : 0);
        };

        document.getElementById('settleModal').addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            if (! trigger?.dataset.settleUrl) {
                settleForm.removeAttribute('action');
                return;
            }
            settleForm.setAttribute('action', trigger.dataset.settleUrl);
            document.querySelector('[data-settle-receipt]').textContent = trigger.dataset.receiptNo;
            document.querySelector('[data-settle-customer]').textContent = trigger.dataset.customer || 'Walk-in';
            settleDue = Number(trigger.dataset.total || 0);
            settleTotalEl.textContent = peso(settleDue);
            settleTendered.value = '';
            computeSettleChange();
        });

        settleTendered.addEventListener('input', computeSettleChange);
    </script>
@endsection
