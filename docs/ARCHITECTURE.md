# Attend - System Architecture

## Overview

Attend is an internal time and attendance management system built with Laravel, featuring ESP32 hardware time clocks and a Filament admin panel. It is a single-tenant application designed for one company.

## Project Scope

### What Attend IS
- Internal time & attendance system for ~300 employees (designed to handle 1,000+)
- ESP32-P4 hardware time clocks with NFC badge readers
- Admin panel (Filament) for managers and HR staff
- Integration hub for pulling/pushing data to Pace ERP and ADP Payroll

### What Attend is NOT (today)
- Not customer-facing
- Not multi-tenant (single company)
- Not a full HRIS (yet - see Future Roadmap)

### Future Roadmap
- **Employee Self-Service Portal** - Separate employee-facing pages (not Filament)
  - View hours worked and pay stubs
  - Download W2s
  - View holiday schedule
  - Access employee handbook/manuals
  - Request time off
- **HR Platform expansion** - Evolve from time & attendance into broader HR tool

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend Framework | Laravel 11.x |
| Admin Panel | Filament 3.x |
| Database | MySQL |
| Queue/Cache | Redis |
| Time Clock Firmware | ESP-IDF 5.5.1 (ESP32-P4) |
| UI Framework | LVGL (via SquareLine Studio) |

## Scale & Performance

| Metric | Current | Designed For |
|--------|---------|-------------|
| Employees | ~300 | 1,000+ |
| Clock events/day | ~1,500 | 5,000+ |
| Time clocks | Multiple | No hard limit |
| Concurrent admin users | Low | ~10 |

## System Components

```
┌─────────────────────────────────────────────────────────────────┐
│                        Attend System                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐     │
│  │   Filament   │    │  REST API    │    │  Scheduler   │     │
│  │ Admin Panel  │    │  (Devices)   │    │   (Cron)     │     │
│  │ (Internal)   │    │              │    │              │     │
│  └──────┬───────┘    └──────┬───────┘    └──────┬───────┘     │
│         │                   │                   │              │
│         └───────────────────┼───────────────────┘              │
│                             │                                  │
│                    ┌────────┴────────┐                        │
│                    │  Laravel Core   │                        │
│                    │  (Models, Jobs) │                        │
│                    └────────┬────────┘                        │
│                             │                                  │
│         ┌───────────────────┼───────────────────┐             │
│         │                   │                   │              │
│  ┌──────┴───────┐   ┌──────┴───────┐   ┌──────┴───────┐     │
│  │   Database   │   │    Redis     │   │ Integrations │     │
│  │   (MySQL)    │   │ (Cache/Queue)│   │ (Pace, ADP)  │     │
│  └──────────────┘   └──────────────┘   └──────────────┘     │
│                                                                │
└─────────────────────────────────────────────────────────────────┘

         │                    │
         │ HTTPS              │ HTTPS
         ▼                    ▼
┌──────────────────┐  ┌──────────────────┐
│  ESP32 Time      │  │  Future:         │
│  Clocks          │  │  Employee Portal │
│  (NFC Readers)   │  │  (Self-Service)  │
└──────────────────┘  └──────────────────┘
```

## Directory Structure

```
app/
├── Console/           # Artisan commands
├── Filament/          # Admin panel resources
│   └── Resources/     # CRUD resources for each entity
├── Http/
│   ├── Controllers/   # API controllers (minimal, device endpoints)
│   └── Middleware/    # Custom middleware
├── Jobs/              # Queue jobs (sync, processing)
├── Models/            # Eloquent models
├── Services/          # Business logic services
│   └── Integrations/  # External API clients (Pace, ADP)
└── Observers/         # Model observers

database/
└── migrations/        # Database migrations

storage/app/templates/ # ESP32 firmware templates
├── esp32_p4_nfc_espidf/  # Main firmware project
└── esp32_p4_lvgl_test/   # UI development project

docs/                  # Documentation (you are here)
```

## Core Entities

### Employee Management
- **Employee** - Core employee record with personal/employment data
- **Department** - Organizational structure
- **Shift** - Work shift definitions (start/end times)
- **ShiftSchedule** - Assignment of shifts to patterns

### Time Tracking
- **ClockEvent** - Raw punch data from devices
- **Attendance** - Processed attendance records
- **Punch** - Individual punch records

### Payroll Configuration
- **PayrollFrequency** - Pay period definitions
- **PayPeriod** - Individual pay periods
- **RoundGroup / RoundingRule** - Punch rounding rules
- **OvertimeRule** - Overtime calculation rules

### Hardware
- **Device** - Time clock devices (ESP32)
- **Credential** - Employee credentials (badges, PINs)

### Integrations
- **IntegrationConnection** - External system connections
- **IntegrationObject** - Mapped object types
- **IntegrationQueryTemplate** - Reusable API queries
- **IntegrationFieldMapping** - Field-to-field mappings
- **IntegrationSyncLog** - Sync operation logs

## Data Flow

### Clock Event Processing

```
ESP32 Time Clock
      │
      │ POST /api/device/punch
      ▼
┌─────────────────┐
│   ClockEvent    │ (Raw punch data)
│   status=new    │
└────────┬────────┘
         │
         │ Scheduler: ProcessClockEventsCommand
         ▼
┌─────────────────┐     ┌─────────────────┐
│   Attendance    │────▶│     Punch       │
│   (Daily)       │     │ (In/Out/Break)  │
└─────────────────┘     └─────────────────┘
```

### Integration Sync (Pace ERP)

```
┌─────────────────┐
│  Pace ERP API   │
│ (loadValueObjs) │
└────────┬────────┘
         │
         │ PaceApiClient::loadValueObjects()
         ▼
┌─────────────────┐
│ IntegrationSync │ (Parse, transform)
│     Log         │
└────────┬────────┘
         │
         │ Field mappings applied
         ▼
┌─────────────────┐
│  Local Models   │ (Employee, etc.)
└─────────────────┘
```

Primary Pace use cases:
- **Pull employee data** into Attend (names, departments, IDs)
- **Future**: Push hours worked back to Pace for job costing

## Authentication & Authorization

- **Admin Panel**: Laravel session auth via Filament (internal staff only)
- **Time Clocks**: Token-based auth (device_id + api_token, 30-day expiry)
- **Permissions**: Spatie Laravel Permission (roles/permissions)
- **Future Employee Portal**: Separate auth (employee login, not admin users)

## Configuration

### Company Setup (Single Record)
Central configuration for company-wide settings:
- Payroll frequency
- Attendance rules
- Device polling intervals
- SMTP settings
- Vacation accrual methods

### Per-Entity Configuration
- Employees can have individual overtime/rounding rules
- Devices can override company polling settings
- Departments can have managers for alerts

## Environment Variables

Key environment variables for deployment:

```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=attend
DB_USERNAME=root
DB_PASSWORD=

# Redis (recommended for production)
REDIS_HOST=127.0.0.1
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Mail (or configure SMTP in Company Setup)
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587

# App
APP_URL=https://attend.yourcompany.com
APP_ENV=production
APP_DEBUG=false
```

## Scheduled Tasks

Defined in `app/Console/Kernel.php`:

| Schedule | Task | Description |
|----------|------|-------------|
| Every 5 min | ProcessClockEvents | Convert ClockEvents to Attendance |
| Hourly | DeviceOfflineCheck | Send alerts for offline devices |
| Daily | VacationAccrual | Process vacation accruals |

## Deployment

### Development
- **Laravel Herd** on macOS

### Production
- **1-2 Linux VMs** on HPE DL380 Gen10 servers
- Hardware: Dual CPU, up to 100GB RAM, 8TB RAID 10 SSD + PCIe cache
- Single server is sufficient for current scale (300 employees, 1,500 events/day)
- No CI/CD pipeline yet - manual deploy via git pull + migrate
- Redis for queue workers and caching

### Deployment Checklist
1. Clone repo to server
2. `composer install --no-dev`
3. Configure `.env` (database, Redis, mail, APP_URL)
4. `php artisan migrate`
5. `php artisan config:cache && php artisan route:cache`
6. Configure cron: `* * * * * php /path/artisan schedule:run`
7. Start queue worker: `php artisan queue:work redis`
8. Configure web server (Nginx/Apache) with SSL

### CI/CD (Future)
CI/CD (Continuous Integration / Continuous Deployment) automates testing and deployment.
Not needed yet with a single developer, but worth adding when:
- Multiple developers contribute
- You want automatic testing before deploy
- You want push-button deployments

Options: **GitHub Actions** (free), **Laravel Forge** ($12/mo, handles server + deploy), **Envoyer** (zero-downtime deploys).

## Related Documentation

- [INTEGRATIONS.md](./INTEGRATIONS.md) - External system integrations (Pace, ADP)
- [DATA_MODEL.md](./DATA_MODEL.md) - Database schema details
- [FIRMWARE_MERGE_STRATEGY.md](./FIRMWARE_MERGE_STRATEGY.md) - ESP32 firmware updates
- [PINOUT_REFERENCE.md](./PINOUT_REFERENCE.md) - ESP32 hardware pinout
