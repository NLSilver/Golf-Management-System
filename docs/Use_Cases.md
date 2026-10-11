# DEGMS — Use Case List

Updated use cases for **A Facility-based Management Information System for Davao EagleMasters Driving Range** (replaces the Use Case Diagram figure, Chapter 3).
Every use case maps to a real route in `routes/web.php`; nothing here is planned-but-unimplemented.

## Actors

| Actor | Description | Credentials |
|---|---|---|
| **Front Desk Staff** | Standard staff account — runs daily operations (bay allocation, time logging, checkout, DTR entry). | `frontdesk` / `password` (role `staff`) |
| **Administrator** | Elevated account — everything staff can do, plus reporting, records, settings, audit. | `admin` / `password` (role `admin`) |
| **Employee** | Facility employee without a system account — clocks in/out using their Employee ID at the shared DTR terminal. | Employee ID (e.g. `558675`) |

## Use Cases — Front Desk Staff

| UC | Use case | Description | Route |
|---|---|---|---|
| UC01 | Log in / log out | Username + password authentication; every login is audit-logged. | `GET/POST /login`, `POST /logout` |
| UC02 | Monitor live bay status | 12-bay dashboard with colour-coded states (available / occupied / pending / expiring / maintenance) and live countdowns; status polled every second. | `GET /dashboard`, `GET /dashboard/status` |
| UC03 | Allocate a bay | Seat a new or existing customer in a free bay; duration validated against the 1h 30m minimum (max 12h). | `POST /bays/{bay}/allocate` |
| UC04 | Search / register customer | Filter existing customers by ID or last name inside the allocate modal; one-click registration for walk-ins. | `GET /dashboard/customers/search` + UC03 |
| UC05 | Add to waiting list | Place a customer in a busy bay's "pending spot" queue; seat or cancel later. | `POST /bays/{bay}/queue`, `POST /queue/{bayQueue}/cancel` |
| UC06 | Extend a session | Add time with quick buttons +15m / +20m / +1h 30m; charge computed from the bay rate. | `POST /bay-sessions/{baySession}/extend` |
| UC07 | Check out & take payment | Close the session, add-on services (club rental, instructor, food), discount, tendered/change; methods cash / GCash / Maya / Card / E-Banking — non-cash **recorded only, no gateway**. | `POST /bay-sessions/{baySession}/checkout` |
| UC08 | Print official receipt | 80mm-friendly printable receipt (works on A4 too — no printer required, print dialog / save as PDF). Linked from the checkout flash, transactions list and admin dashboard. | `GET /receipts/{transaction}` |
| UC09 | Clock employee in/out (DTR) | Employee enters their Employee ID for time-in / time-out; duplicate clock-in and unknown IDs rejected. | `POST /dtr/clock-in`, `POST /dtr/clock-out` |

## Use Cases — Administrator (adds to staff)

| UC | Use case | Description | Route |
|---|---|---|---|
| UC10 | View revenue dashboard | Today's / last month's revenue, customer volume, 14-day revenue graph, bay monitor, today's attendance. | `GET /admin` |
| UC11 | View / filter transactions | Date-range transaction list with per-method totals; receipt links for re-printing. | `GET /admin/transactions` |
| UC12 | View employee records | Per-employee DTR with today's clock-in/out and minutes worked. | `GET /admin/employees` |
| UC13 | View customer data | Customer master list with session count, transaction count and lifetime spend. | `GET /admin/customers` |
| UC14 | Generate monthly reports | Operational, transactional and attendance sections for a chosen month. | `GET /admin/reports` |
| UC15 | Export reports to CSV | `degms-transactions-YYYY-MM.csv` / `degms-dtr-YYYY-MM.csv` downloads. | `GET /admin/reports/export` |
| UC16 | View activity log | Filterable audit trail (logins, allocations, checkouts, settings, maintenance) with user + IP. | `GET /admin/activity` |
| UC17 | Manage facility settings | Facility name/address, receipt footer, operating hours, minimum session, "soon to expire" threshold. | `GET/POST /admin/settings` |
| UC18 | Toggle bay maintenance | Mark a free bay out of order / back in service (blocked while a session is active). | `POST /admin/bays/{bay}/maintenance` |

## Use Cases — Employee (no account)

| UC | Use case | Description | Route |
|---|---|---|---|
| UC19 | Clock in / clock out | Same DTR terminal as UC09; employee self-service via Employee ID. | `POST /dtr/clock-in`, `POST /dtr/clock-out` |
| UC20 | View today's attendance | Live day table of clock-in / clock-out / minutes for all staff. | `GET /dtr` |

## Objective → Use Case mapping (Chapter 3, System Objectives)

| System objective | Fulfilling use cases |
|---|---|
| 1. Automate logging of customer time-in and time-out | UC03, UC05, UC06, UC07 |
| 2. Automated employee attendance module replacing paper DTR | UC09, UC19, UC20 |
| 3. Real-time monitoring / display of hitting bay availability | UC02, UC18 |
| 4. Automatic generation of monthly operational, transactional and attendance reports | UC10, UC11, UC14, UC15 |
| (Cross-cutting) Accountability & security — RBAC per Security Plan | UC01, UC16, UC17 |

## Scope boundaries (unchanged from the paper)

- No online booking / customer-facing app; internal front-desk use only.
- Payroll, HR and corporate accounting excluded — parent corporation (Davao Eagle Masters Inc.) handles them.
- GCash / Maya / Card / E-Banking are **recorded placeholders** for the school demo — no payment gateway.
- Inventory and field-maintenance tables exist in the schema but have **no delivered use case** (out of scope).
