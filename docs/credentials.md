# DEGMS — Demo Account Credentials

School-demo accounts seeded by `database/seeders/UserSeeder.php`.

## System accounts

| Username | Password | Role | Display name | Access |
|---|---|---|---|---|
| `admin` | `password` | `admin` | System Administrator | Everything, plus Admin area: reports, records, settings, activity log |
| `frontdesk` | `password` | `staff` | Front Desk Staff | Bay monitor, allocation, extensions, checkout, receipts, DTR |

## Employees (no system account — DTR terminal only)

Clock in/out at `/dtr` using the Employee ID:

| Employee ID | Name | Position |
|---|---|---|
| `558675` | Andrian Christian Hernadez | Front Desk |
| `835242` | Charlie Menedez | — |
| `189324` | Jerred Santos | Instructor |

## Notes

- Passwords are stored **bcrypt-hashed** in `users.password` (Laravel `hashed` cast) — the plaintext above is the seeded demo value.
- Roles are enforced by the `admin` Gate; staff get **403** on all `/admin/*` routes.
- Change these before any real deployment.
