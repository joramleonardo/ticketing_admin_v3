# DOST-STII IT Ticketing & Events Management System (v3)

<p align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="220" alt="Laravel Logo" />
  &nbsp;&nbsp;&nbsp;&nbsp;
  <img src="https://vuejs.org/images/logo.png" width="60" alt="Vue Logo" />
</p>

<p align="center">
  <a href="https://php.net"><img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP Version"></a>
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel Version"></a>
  <a href="https://vuejs.org"><img src="https://img.shields.io/badge/Vue.js-3.x-4FC08D?style=flat-square&logo=vue.js&logoColor=white" alt="Vue 3"></a>
  <a href="https://inertiajs.com"><img src="https://img.shields.io/badge/Inertia.js-v2-9553E9?style=flat-square&logo=inertia&logoColor=white" alt="Inertia v2"></a>
  <a href="https://tailwindcss.com"><img src="https://img.shields.io/badge/TailwindCSS-v3-38B2AC?style=flat-square&logo=tailwind-css&logoColor=white" alt="Tailwind CSS"></a>
  <a href="https://pestphp.com"><img src="https://img.shields.io/badge/Tested%20With-Pest%20v3-F25B54?style=flat-square&logo=pest&logoColor=white" alt="Pest v3"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/License-MIT-blue.svg?style=flat-square" alt="License"></a>
</p>

---

## Overview

The **DOST-STII IT Ticketing & Events Management System** is an enterprise-grade IT Service Management (ITSM), Technical Support Dispatch, Events Scheduling, and Customer Satisfaction Measurement (CSM) platform built specifically for the **Department of Science and Technology - Science and Technology Information Institute (DOST-STII)**.

Combining a modern **Laravel 12** backend with a reactive **Inertia.js v2** and **Vue 3** single-page application (SPA) frontend, the system provides end-to-end incident tracking, intelligent staff dispatch, multi-day event venue scheduling with real-time double-booking conflict detection, SLA & Turnaround Time (TAT) compliance monitoring, and standardized citizen feedback analytics.

---

## Key Features

### 1. Comprehensive Ticket Lifecycle & Triage (Tickets V2)
- **Multi-State Lifecycle**: Seamless transition across `New`, `Pending`, `In Progress`, `Completed`, and `Closed` statuses.
- **SLA & TAT Monitoring**:
  - Precision Turnaround Time (TAT) clock initiated upon ticket assignment/attendance.
  - Color-coded SLA Badges: *Compliant*, *Approaching SLA Deadline*, and *Overdue / Delayed*.
- **Soft Delete & Trash Management**:
  - Safe soft-deletion with dedicated Trash tab.
  - Bulk and individual ticket restore, permanent force delete, and one-click "Empty Trash" capabilities.
- **Bulk Operations**: Bulk assign IT technicians, bulk close resolved issues, and bulk trash management with select-all capabilities across filtered views.
- **Universal Global Search**:
  - Sticky GitHub-styled navigation bar with `/` keyboard shortcut.
  - Instant debounced auto-complete searching by Reference Number, Client Name, Division, or Problem description.
- **Real-Time Client-Staff Messaging**: Integrated ticket thread allowing two-way communication and document attachments between clients and attending IT personnel.
- **Audit Trail & Activity Logs**: Immutable chronological activity logging capturing every dispatch action, status change, and internal note.

### 2. IT Events Calendar & Monitoring Workspace
- **Multi-View Scheduling Board**:
  - **Month View**: Overview with status pills, conflict badges, and multi-day timeline spans.
  - **Week View**: Detailed daily breakdown with time slots and technician assignments.
  - **Agenda View**: Chronological upcoming events feed with urgency highlights.
  - **Venues View (Occupancy Board)**: Live availability tracker for institutional venues (`DOST-STII Atrium`, `Cast Room`, `Training Room`, `Conference Room`, `Mini Theater`, `Zoom / Online`, and external/custom venues via `Others`).
- **Conflict & Double-Booking Detection**:
  - Real-time overlap detection for physical venues and IT staff technicians.
  - Top Schedule Conflict Alert Banner with one-click filtering.
  - Proactive dispatch warnings (`Busy (#Ref)`) when assigning staff members already committed to overlapping events.
- **Click-to-Schedule & Inline Quick Actions**:
  - Quick-schedule events directly by clicking any calendar date cell.
  - Inline event dispatch, schedule adjustments, and status transitions directly from the preview drawer.
- **Urgent Unassigned Event Warnings**: Visual alerts for upcoming events lacking assigned technical support within 48–72 hours.

### 3. Role-Based Workspaces & Navigation
- **Super Admin (Executive Administration)**:
  - High-level executive overview, agency-wide SLA compliance rates, division-by-division request breakdowns, and technician workload metrics.
  - Full user management, role assignments, and account activation controls.
  - Monthly ticket summary CSV exports.
- **Admin (Dispatcher & Triage)**:
  - Triage & Queue Dispatcher dashboard highlighting unassigned tickets.
  - Events management and venue conflict resolution.
  - Customer ratings and CSM compliance reports.
- **Staff Specialist (Personal Workstation)**:
  - Dedicated **"My Tickets"** queue sorted by active assignments, in-progress items, overdue warnings, and completed tasks.
  - One-click ticket attendance (`Attend Ticket`), status completion, and technical remarks.
  - Personal productivity metrics and performance indicators.

### 4. Self-Service Client Portal (`/client`)
- **Frictionless Ticket Submission**: Submit technical support requests without requiring prior authentication.
- **Support Classifications**: Hardware, Software, Network/Internet, Server-related, Events Support, and Preventive Maintenance.
- **Multi-Day Event Booking**: Specify venue, start/end schedules, and logistical requirements.
- **Live Ticket Tracking**: Real-time status tracker via employee ID code or reference code (`/client/track/{employee_code}`).
- **Interactive Ratings & Feedback (CSM)**: Rate resolved tickets through a streamlined Customer Satisfaction Measurement survey (`/client/rate/{ref}`).

### 5. Customer Satisfaction Measurement (CSM) & Analytics
- Government-standard CSM survey reporting covering Service Quality Dimensions (SQD):
  - Responsiveness, Reliability, Integrity, Facility, Communication, Costs, and Overall Satisfaction.
- Comprehensive ratings dashboard with Net Promoter Score (NPS), CSAT indices, and division analytics.

### 6. Live Dispatch Mode & Sound Notifications
- Persistent background polling with live pulsing status indicator.
- Synthesized Web Audio API sound alert engine (zero external audio asset latency) with customizable ringtone presets (Chime, Bell, Ping, Modern Synth) and per-user mute controls.

---

## Technology Stack

| Layer | Technology | Description |
| :--- | :--- | :--- |
| **Backend Framework** | [Laravel 12](https://laravel.com/) | Robust MVC architecture, Eloquent ORM, Gates & Policies |
| **Language Runtime** | [PHP 8.2+](https://php.net/) | Modern typed PHP with constructor property promotion |
| **Frontend SPA** | [Vue 3](https://vuejs.org/) | Composition API (`<script setup>`), reactive components |
| **SPA Bridge** | [Inertia.js v2](https://inertiajs.com/) | Server-driven client-side routing, deferred props, polling |
| **Styling & UI** | [Tailwind CSS v3](https://tailwindcss.com/) | Modern utility-first responsive styling with dark mode support |
| **Icons & Media** | [FontAwesome 6](https://fontawesome.com/) | Vector icons and visual indicators |
| **Charts & Analytics**| [Chart.js 4](https://www.chartjs.org/) & [vue-chartjs](https://vue-chartjs.org/) | Interactive KPI charts and workload distribution graphs |
| **Database** | SQLite / MySQL | Relational data persistence with foreign key constraints |
| **Testing** | [Pest v3](https://pestphp.com/) & PHPUnit 11 | Elegant functional and feature test suite |
| **Code Formatter** | [Laravel Pint](https://laravel.com/docs/pint) | Automated PSR-12 and Laravel code style enforcement |
| **Asset Bundler** | [Vite 5](https://vitejs.dev/) | Ultra-fast Hot Module Replacement (HMR) and production bundling |

---

## Getting Started

### Prerequisites
Ensure your local development environment meets the following requirements:
- **PHP** `>= 8.2` (with `pdo`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `curl`, and `bcmath` extensions)
- **Composer** `>= 2.x`
- **Node.js** `>= 18.x` & **npm** `>= 9.x`
- **SQLite** or **MySQL / MariaDB**

---

### Installation Steps

1. **Clone the Repository**
   ```bash
   git clone https://github.com/joramleonardo/ticketing_admin_v3.git
   cd ticketing_admin_v3
   ```

2. **Install PHP Dependencies**
   ```bash
   composer install
   ```

3. **Install JavaScript Dependencies**
   ```bash
   npm install
   ```

4. **Configure Environment Variables**
   ```bash
   cp .env.example .env
   ```
   *Verify and adjust database configuration in `.env` (SQLite is enabled by default):*
   ```env
   APP_NAME="DOST-STII Ticketing"
   APP_URL=http://localhost:8000

   DB_CONNECTION=sqlite
   # Or for MySQL:
   # DB_CONNECTION=mysql
   # DB_HOST=127.0.0.1
   # DB_PORT=3306
   # DB_DATABASE=ticketing_v3
   # DB_USERNAME=root
   # DB_PASSWORD=
   ```

5. **Generate Application Key**
   ```bash
   php artisan key:generate
   ```

6. **Run Database Migrations & Seeders**
   ```bash
   # Create SQLite database file if it does not exist:
   touch database/database.sqlite

   # Run migrations and initial seeds:
   php artisan migrate --seed
   ```

7. **Compile Frontend Assets**
   ```bash
   # Development mode with HMR:
   npm run dev

   # Or build for production:
   npm run build
   ```

8. **Start Local Development Server**
   ```bash
   # Using standard artisan command:
   php artisan serve

   # Or use concurrently to launch server, queue worker, logs, and Vite together:
   composer run dev
   ```

   Access the application at `http://localhost:8000` (or `http://127.0.0.1:8000`).

---

## Testing & Quality Assurance

This project adheres to rigorous testing standards utilizing **Pest v3**.

```bash
# Run the complete test suite
php artisan test

# Run a specific feature test file (e.g. Events Calendar)
php artisan test tests/Feature/TicketEventsCalendarTest.php

# Run tests with filter
php artisan test --filter=SlaTat

# Check and fix PHP code style with Laravel Pint
vendor/bin/pint --dirty
```

---

## Project Architecture

```
ticketing_admin_v3/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/             # Admin, User, and Ticket controllers
│   │   │   ├── TicketEventController.php # Events calendar & conflict engine
│   │   │   ├── TicketV2Controller.php    # Tickets V2 triage & bulk actions
│   │   │   ├── MyTicketsController.php   # Staff personal queue
│   │   │   ├── ClientController.php      # Public client portal & tracking
│   │   │   └── CsmReportsController.php  # CSM reports & feedback
│   │   └── Requests/              # Form request validation classes
│   ├── Models/                    # Eloquent models (TicketClientDetail, User, etc.)
│   └── Services/                  # Business logic (StaffService, TicketSlaService)
├── database/
│   ├── migrations/                # Database schema migrations
│   └── seeders/                   # Default database seeders
├── resources/
│   ├── css/                       # Global Tailwind CSS definitions
│   └── js/
│       ├── Components/            # Reusable Vue components (badges, modals, dropdowns)
│       ├── Layouts/               # AuthenticatedLayout (GitHub-style drawer & navbar)
│       ├── Pages/
│       │   ├── TicketsV2/         # All Tickets, Events Calendar, My Tickets
│       │   ├── ClientMain.vue     # Public ticket submission portal
│       │   ├── ClientTrack.vue    # Ticket tracking interface
│       │   └── Dashboard.vue      # Role-based KPI analytics
│       └── Utils/                 # SLA calculators, sound alerts, date helpers
├── routes/
│   ├── web.php                    # Web and Inertia routes
│   └── auth.php                   # Authentication routes
└── tests/
    └── Feature/                   # Pest feature tests (SLA, Events, Lifecycle, etc.)
```

---

## User Roles & Access Control

| Role | Default Landing | Description |
| :--- | :--- | :--- |
| **Super Admin** | `/dashboard` | Complete oversight, user account management, agency-wide metrics |
| **Admin** | `/admin/tickets-v2` | Central dispatcher, triage management, event scheduling, bulk operations |
| **Staff** | `/admin/my-tickets` | Individual technical queue, ticket attendance, resolution notes |
| **Client (Public)** | `/client` | Open self-service request submission, status tracking, rating submission |

---

## License

This software is open-sourced under the [MIT License](LICENSE).

---

<p align="center">
  Developed for <strong>DOST - Science and Technology Information Institute (DOST-STII)</strong><br>
  <em>Information Technology Unit</em>
</p>
