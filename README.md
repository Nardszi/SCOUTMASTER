SCOUTMASTER
A comprehensive scout management system that tracks scout progress, badges, attendance, registrations, and user accounts.
Overview
SCOUTMASTER is a web-based platform designed to manage scout organizations efficiently. It enables scouts to track their progress, scout leaders to approve advancements, and administrators to oversee the entire system across multiple schools.

Technology Stack
Backend: PHP
Frontend: Node.js, HTML, CSS
Purpose: Full-stack scout progress management and organizational operations

Features
Badge Progress Tracking - Monitor and track scout badge completion and requirements
Registration Forms - Streamlined registration process for new scouts and system users
Virtual Meeting API - Host online meetings and deliver requirements remotely
Audit Logs - Comprehensive system activity logging for accountability and monitoring
Scout Management - Organize and manage scouts by school and group
School Management - Multi-school support with dedicated management
Activity/Event Management - Create, manage, and track scout activities and events
User Account Management - Secure login with primary key ID (membership card number) authentication

User Roles & Permissions

Scout
Submit and track badge progress
View personal profile
Attend activities and events
Track requirements

Scout Leader
Approve/decline scout progress submissions
Track individual scout progress
Manage scout activities and events
Register new scouts and system users
Host online meetings
Assign and communicate requirements

Admin
Manage scouts across assigned schools
Monitor system activity and audit logs
Track registered members
Oversee platform operations

Getting Started

Access
Navigate to the SCOUTMASTER website
Log in with your credentials or create a new account
To create an account, use your primary key ID (membership card number)

Installation
```bash
# Clone the repository
git clone https://github.com/Nardszi/SCOUTMASTER.git

# Navigate to project directory
cd SCOUTMASTER

# Install backend dependencies (PHP)
# Ensure PHP is installed and configured

# Install frontend dependencies (Node.js)
npm install

# Configure database connection
# Update configuration files with your database credentials

# Start the application
npm start
# or
php -S localhost:8000
```
Prerequisites
PHP 7.4 or higher
Node.js 14 or higher
npm or yarn package manager
Database (MySQL/PostgreSQL)

Usage

For Scouts
Log in to your account
View your profile and badge progress
Submit badge requirements for approval
Attend registered activities and events
Participate in virtual meetings

For Scout Leaders
Log in to your dashboard
Review pending badge submissions
Approve or decline progress
Create and manage activities/events
Register new scouts and users
Host virtual meetings and assign requirements

For Admins
Access the admin panel
Manage schools and scout organizations
Monitor system activity through audit logs
Review registered members and accounts
Generate reports and analytics

Project Structure
```
SCOUTMASTER/
├── backend/          # PHP backend files
├── frontend/         # Node.js, HTML, CSS frontend
├── public/          # Public assets and HTML files
├── src/             # Source code
├── config/          # Configuration files
├── database/        # Database schemas and migrations
└── README.md        # This file
```

Database Structure

SCOUTMASTER manages:
Scout profiles and records
Badge and requirement tracking
Activity and event registrations
User accounts and roles
Audit logs and system activity
School and organization data

Security
Secure login with credential validation
Primary key ID verification for account creation
Role-based access control
Comprehensive audit logging
User account management
Password encryption and validation

API Endpoints

Virtual Meeting API
Real-time meeting hosting
Requirement delivery
Activity notifications
Progress tracking

Authentication
User login and registration
Primary key ID verification
Session management
Password reset functionality

Contributing
Contributions are welcome! Please follow these guidelines:
Fork the repository
Create a feature branch (`git checkout -b feature/your-feature`)
Commit your changes (`git commit -m 'Add your feature'`)
Push to the branch (`git push origin feature/your-feature`)
Open a Pull Request

Support
For issues, questions, or suggestions, please:
Open an issue on the GitHub repository
Contact the development team
Check existing documentation

License
[Add your license here - e.g., MIT, Apache 2.0, etc.]

Authors
Nardszi - Project Creator
---

Last Updated: 2026-04-13
Status: Active Development
