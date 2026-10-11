import * as bootstrap from 'bootstrap';

window.bootstrap = bootstrap;

// Keep the bay grid in sync with the server every second.
document.addEventListener('DOMContentLoaded', () => {
    const grid = document.querySelector('[data-bay-status-url]');

    if (! grid) {
        return;
    }

    const url = grid.getAttribute('data-bay-status-url');
    const formatter = (seconds) => {
        const sign = seconds < 0 ? '-' : '';
        const total = Math.abs(seconds);
        const h = Math.floor(total / 3600);
        const m = Math.floor((total % 3600) / 60);
        const s = total % 60;

        return `${sign}${h}h ${m}m ${s}s`;
    };

    const refresh = async () => {
        try {
            // X-Requested-With stops StartSession from recording this tick as
            // the session's "previous URL" - that's what made the receipt
            // Back button land on this JSON endpoint.
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (! response.ok) {
                return;
            }

            const { bays } = await response.json();

            // A session appearing/disappearing (allocation elsewhere, or the
            // auto-finish sweep closing an expired one) invalidates the buttons
            // and the unsettled-receipts panel, so re-render from the server.
            let needsReload = false;

            bays.forEach((bay) => {
                const card = document.querySelector(`[data-bay-id="${bay.id}"]`);

                if (! card) {
                    return;
                }

                const cardSession = card.dataset.sessionId || '';
                const serverSession = bay.session_id ? String(bay.session_id) : '';
                const cardPending = card.dataset.pending === '1';
                const serverPending = !! bay.pending_customer;

                if (cardSession !== serverSession || cardPending !== serverPending) {
                    needsReload = true;
                    return;
                }

                const timer = card.querySelector('[data-timer]');
                const customer = card.querySelector('[data-customer]');
                const badge = card.querySelector('[data-badge]');
                const state = bay.out_of_order
                    ? 'maintenance'
                    : (bay.overstayed || bay.expiring ? 'expiring' : bay.status);

                card.classList.remove('border-available', 'border-occupied', 'border-pending', 'border-maintenance', 'border-expiring');
                card.classList.add(`border-${state}`);

                if (timer) {
                    timer.textContent = bay.seconds_left === null
                        ? (bay.status === 'pending' ? 'Waiting' : '--:--:--')
                        : formatter(bay.seconds_left);
                    timer.classList.toggle('text-danger', bay.overstayed);
                }

                if (customer) {
                    customer.textContent = bay.session_no
                        ? `${bay.session_no} - ${bay.customer ?? ''}`
                        : (bay.pending_customer ? `Pending: ${bay.pending_customer}` : 'Available');
                }

                if (badge) {
                    let label = 'Free';
                    let classes = 'badge text-bg-success';

                    if (bay.out_of_order) {
                        label = 'Maintenance';
                        classes = 'badge text-bg-secondary';
                    } else if (bay.session_id && bay.overstayed) {
                        label = 'Overstay';
                        classes = 'badge text-bg-danger';
                    } else if (bay.session_id && bay.expiring) {
                        label = 'Soon to expire';
                        classes = 'badge text-bg-danger';
                    } else if (bay.session_id) {
                        label = 'Busy';
                        classes = 'badge text-bg-primary';
                    } else if (bay.pending_customer) {
                        label = 'Pending';
                        classes = 'badge text-bg-warning text-dark';
                    }

                    badge.textContent = label;
                    badge.className = classes;
                }
            });

            // Skip the reload while a modal is open - it would discard what
            // the cashier is typing; the next tick retries.
            if (needsReload && ! document.querySelector('.modal.show')) {
                window.location.reload();
            }
        } catch (error) {
            // Network blip - keep the dashboard usable, retry next tick.
        }
    };

    refresh();
    setInterval(refresh, 1000);
});
