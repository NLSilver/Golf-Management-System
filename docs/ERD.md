# DEGMS — Entity-Relationship Diagram

Updated ERD for **A Facility-based Management Information System for Davao EagleMasters Driving Range** (replaces Figure 5, Chapter 3).
Every entity and attribute below is derived from the actual migrations in `database/migrations/` — nothing is aspirational.

## Mermaid ERD

```mermaid
erDiagram
    users {
        int id PK
        string name
        string username UK
        string role "admin or staff"
        int employee_id FK "nullable"
        string password "bcrypt hash"
        string email "nullable"
    }
    employees {
        int id PK
        string employee_no UK
        string first_name
        string middle_name "nullable"
        string last_name
        string position "nullable"
        string contact_no "nullable"
        date date_hired "nullable"
        bool is_active
    }
    attendance_records {
        int id PK
        int employee_id FK
        date date
        datetime clock_in
        datetime clock_out "nullable"
        int minutes_worked "nullable"
        int recorded_by FK "users nullable"
    }
    customers {
        int id PK
        string first_name
        string last_name
        string contact_no "nullable"
        string email "nullable"
        text notes "nullable"
    }
    bays {
        int id PK
        string code UK "B1..B12"
        int bay_number UK
        string label "nullable"
        bool is_active
        bool is_out_of_order
        string notes "nullable"
    }
    services {
        int id PK
        string code UK
        string name
        string category "bay_time / club_rental / lesson / food"
        string unit "hour / session / piece"
        decimal rate
        int minimum_minutes "nullable"
        bool is_active
        int sort_order
    }
    settings {
        int id PK
        string name UK
        text value "nullable"
        string group
    }
    bay_sessions {
        int id PK
        string session_no UK
        int bay_id FK
        int customer_id FK
        int service_id FK "nullable"
        int user_id FK "nullable"
        datetime started_at
        int planned_minutes
        datetime ends_at "moves on extension"
        datetime actual_end_at "nullable"
        string status "active / completed"
    }
    session_extensions {
        int id PK
        int bay_session_id FK
        int minutes
        decimal amount
        int user_id FK "nullable"
        string reason "nullable"
    }
    bay_queues {
        int id PK
        int bay_id FK
        int customer_id FK
        string status "waiting / seated / cancelled"
        datetime queued_at
        datetime served_at "nullable"
        int user_id FK "nullable"
        string notes "nullable"
    }
    transactions {
        int id PK
        string receipt_no UK
        int customer_id FK "nullable"
        int bay_session_id FK "nullable"
        int user_id FK "nullable"
        decimal subtotal
        decimal discount
        decimal total
        string payment_method "cash / gcash / maya / card / e-banking"
        decimal amount_tendered "nullable"
        decimal change_due "nullable"
        string status "paid / unpaid / void"
        datetime paid_at "nullable"
    }
    transaction_items {
        int id PK
        int transaction_id FK
        int service_id FK "nullable"
        string item_type
        string description
        decimal quantity
        decimal unit_price
        decimal amount
        json meta "nullable"
    }
    inventory_items {
        int id PK
        string code UK
        string name
        string category
        int quantity
        string unit
        int reorder_level
        bool is_active
    }
    inventory_movements {
        int id PK
        int inventory_item_id FK
        string type "in / out / adjustment"
        decimal quantity
        string reference "nullable"
        text notes "nullable"
        int user_id FK "nullable"
    }
    maintenance_logs {
        int id PK
        string type
        int employee_id FK "nullable"
        datetime started_at
        datetime finished_at "nullable"
        text notes "nullable"
        int user_id FK "nullable"
    }
    activity_logs {
        int id PK
        int user_id FK "nullable"
        string action
        string subject_type "nullable morph"
        int subject_id "nullable morph"
        text description "nullable"
        string ip_address "nullable"
        timestamp created_at
    }

    employees ||--o{ users : "has account"
    employees ||--o{ attendance_records : "clocks in"
    users ||--o{ attendance_records : "records"
    bays ||--o{ bay_sessions : "hosts"
    customers ||--o{ bay_sessions : "starts"
    services ||--o{ bay_sessions : "bills"
    users ||--o{ bay_sessions : "allocates"
    bay_sessions ||--o{ session_extensions : "extended by"
    users ||--o{ session_extensions : "approves"
    bays ||--o{ bay_queues : "waits for"
    customers ||--o{ bay_queues : "queued as"
    users ||--o{ bay_queues : "enqueues"
    customers ||--o{ transactions : "pays"
    bay_sessions ||--o{ transactions : "settled by"
    users ||--o{ transactions : "cashes"
    transactions ||--o{ transaction_items : "contains"
    services ||--o{ transaction_items : "priced from"
    inventory_items ||--o{ inventory_movements : "stock movements on"
    users ||--o{ inventory_movements : "logs"
    employees ||--o{ maintenance_logs : "performs"
    users ||--o{ maintenance_logs : "logs"
    users ||--o{ activity_logs : "audits"
```

## Relationship notes

| Relationship | FK / delete rule | Paper concept it replaces |
|---|---|---|
| employees → users | `users.employee_id`, nullable, null on delete | Staff account with RBAC role (`admin` / `staff`) |
| employees → attendance_records | cascade on delete; unique `(employee_id, clock_in)` | Paper **Daily Time Record sheet** |
| bays / customers / services / users → bay_sessions | restrict / restrict / null / null | Front-desk **manual logbook** (time-in/out) |
| bay_sessions → session_extensions | cascade on delete | Add-time entries (+15 / +20 / +1h30m) |
| bay_sessions → transactions | nullable, null on delete | Receipt book |
| transactions → transaction_items | cascade on delete | Line items on the printed official receipt |
| bays / customers → bay_queues | restrict on delete | "Pending spot" waiting list |
| users → activity_logs | nullable, null on delete | **Audit trail** (new — replaces nothing manual) |

## Scope notes

- **Framework tables excluded from the diagram** (not domain data): `sessions`, `password_reset_tokens`, `cache`, `jobs`.
- `inventory_items`, `inventory_movements` and `maintenance_logs` are **schema-ready but have no delivered UI module** — consistent with the paper's Scope and Limitations.
- `transactions.payment_method` records `gcash`, `maya`, `card`, `e-banking` as **record-only placeholders — no payment gateway** (school demo, as stated in the paper).
- Business rules encoded in the schema: minimum session 90 min (`services.minimum_minutes` / `settings.minimum_session_minutes`), 12 bays (`bays` B1–B12), operating hours 06:00–22:00 (`settings`), "soon to expire" threshold 10 min (`settings`), ₱300/hr bay rate (`services.rate`).
