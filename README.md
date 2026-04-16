# SCOUTMASTER — Boy Scout Management System

A web-based platform for managing scout organizations: tracking badge progress, rank advancements, events, meetings, registrations, and user accounts across multiple schools.

---

## Technology Stack

- **Backend**: PHP (flat-file, no framework)
- **Database**: MySQL (via `mysqli`)
- **Email**: PHPMailer + Brevo API
- **Dependencies**: Managed via Composer
- **Frontend**: HTML, CSS, vanilla JS (embedded in PHP views)

---

## Features

- **Dashboard** — Role-specific dashboards for scouts, leaders, and admins
- **Badge & Rank Tracking** — Submit, review, and approve badge progress and rank advancements
- **Event Management** — Create, join, approve, and export event attendees
- **Meeting Scheduling** — Schedule, approve, and manage troop meetings (including video meetings)
- **Scout Registration** — Individual and batch scout registration with waiver and form printing
- **User Management** — Admin-controlled user creation, approval, and role assignment
- **Reports & Exports** — Export attendee lists, generate certificates, download user reports
- **Activity Logs** — Full audit trail of system actions
- **Email Notifications** — SMTP + Brevo transactional email integration
- **Multi-school Support** — Manage scouts across multiple schools from one platform

---

## User Roles

| Role | Capabilities |
|---|---|
| Scout | View profile, track badge/rank progress, join events |
| Scout Leader | Approve progress, manage scouts, schedule meetings, register scouts |
| Admin | Full system access, user management, reports, audit logs |

---

## Project Structure

```
BOYSCOUTMANAGEMENTSYSTEM/
├── index.php                  # Entry point / login redirect
├── login.php / register.php   # Authentication
├── dashboard.php              # Role router dashboard
├── dashboard_scout.php        # Scout dashboard
├── dashboard_admin_leader.php # Leader/Admin dashboard
├── config.php                 # DB + SMTP credentials (not committed)
├── config.example.php         # Safe credential template
├── config-production-template.php  # Full production setup guide
├── database.php               # (legacy, gitignored)
├── composer.json              # PHP dependencies
├── uploads/                   # User-uploaded files (gitignored)
│   ├── badge_icons/
│   ├── profile_pictures/
│   ├── proofs/
│   └── waivers/
├── db/                        # SQL dumps (gitignored)
├── vendor/                    # Composer packages (gitignored)
└── templates/                 # Shared templates
```

---

## Getting Started

### Prerequisites

- PHP 7.4+
- MySQL 5.7+
- Composer
- A web server (Apache/Nginx) or `php -S`

### Installation

```bash
# 1. Clone the repository
git clone https://github.com/Nardszi/SCOUTMASTER.git
cd SCOUTMASTER

# 2. Install PHP dependencies
composer install

# 3. Set up your config
cp config.example.php config.php
# Edit config.php with your DB credentials, SMTP, and Brevo API key

# 4. Import the database
# Import the SQL file from the db/ directory into your MySQL server

# 5. Run locally
php -S localhost:8000
```

### Configuration

Copy `config.example.php` to `config.php` and fill in:

| Setting | Description |
|---|---|
| `$host`, `$user`, `$password`, `$database` | MySQL connection details |
| `SMTP_HOST`, `SMTP_USER`, `SMTP_PASS`, `SMTP_PORT` | Email sending (Gmail App Password recommended) |
| `BREVO_API_KEY` | Brevo transactional email API key |

> See `config-production-template.php` for a full production deployment checklist.

---

## Security Notes

- `config.php` and `database.php` are gitignored — never commit real credentials
- Role-based access control on all pages
- Input sanitization and prepared statements throughout
- Activity logging for audit trails
- HTTPS + `session.cookie_secure` recommended for production

---

## Deployment

Use the included `clean-and-deploy.sh` script to prepare and push a clean production release:

```bash
bash clean-and-deploy.sh https://github.com/Nardszi/SCOUTMASTER.git
```

The script handles: gitignore verification, sensitive file protection, debug file removal, uploads placeholders, composer verification, git init, and push.

---

## License

[Add your license here]

## Author

**Nardszi** — Project Creator

---

*Last updated: April 2026*
