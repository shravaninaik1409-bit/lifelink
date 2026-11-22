# LifeLink – Online Blood Donor Finder and Management System

**"Connecting Lives, One Donation at a Time."**

LifeLink is a complete, responsive, database-driven web application built with **PHP, MySQL, HTML5, CSS3, JavaScript, and Bootstrap 5**, designed to run locally in **XAMPP (Apache + MySQL)**.

---

## 🎨 Healthcare Color Palette
Inspired by the clean medical aesthetic:
- **Crimson / Blood Red Accent**: `#DC2626` & `#E11D48`
- **Deep Navy & Slate**: `#0B192C` & `#1E3A5F`
- **Vitality Azure / Cyan**: `#0284C7` & `#0EA5E9`
- **Crisp Healthcare White / Light Slate**: `#F8FAFC` & `#FFFFFF`
- **Soft Emergency / Rose Tints**: `#FEE2E2` & `#FFE4E6`

---

## 🚀 How to Run in XAMPP

### Step 1: Copy Project Folder
Place the `lifeLink` folder inside your XAMPP `htdocs` directory:
```
C:\xampp\htdocs\lifeLink
```

### Step 2: Start Apache and MySQL
1. Open the **XAMPP Control Panel**.
2. Click **Start** for **Apache**.
3. Click **Start** for **MySQL**.

### Step 3: 1-Click Database Setup
Open your browser and navigate to:
```
http://localhost/lifeLink/install.php
```
Click **"Install & Seed Database Now"**.
This automatically creates the `lifelink_db` database, generates all 11 normalized tables, and seeds realistic test data with hashed passwords.

*(Alternatively, you can import `database/lifelink_db.sql` into phpMyAdmin at `http://localhost/phpmyadmin/`)*.

### Step 4: Open the Application
Navigate to:
```
http://localhost/lifeLink/
```

---

## 🔑 Pre-Configured Demo Accounts

| Role | Email | Password | Details |
|---|---|---|---|
| **Administrator** | `admin@lifelink.org` | `Admin@123` | Full system control, analytics, user & request management |
| **Donor (O- Universal)** | `marcus.vance@lifelink.org` | `Password@123` | Dr. Marcus Vance, Mumbai, Verified Universal Donor |
| **Donor (A+)** | `priya.patel@lifelink.org` | `Password@123` | Priya Patel, Bengaluru, Verified Donor |
| **Requester / Patient** | `sarah.requester@lifelink.org` | `Password@123` | Sarah Jenkins, Family Requester |
| **Hospital Requester** | `care.hospital@lifelink.org` | `Password@123` | City Care Hospital Representative |

*(Quick autofill buttons for these accounts are also available directly on `login.php`)*.

---

## 📂 Project Structure

```
lifeLink/
│
├── config/
│   └── database.php             # PDO database connection & auto-installer
│
├── database/
│   └── lifelink_db.sql          # Complete SQL schema & pre-seeded data
│
├── includes/
│   ├── header.php               # HTML head, typography, styles & meta
│   ├── navbar.php               # Public navigation bar with role detection
│   ├── footer.php               # Universal healthcare footer & hotline
│   ├── auth.php                 # Role guard & session authentication
│   ├── functions.php            # Security, CSRF, ID generation & helpers
│   ├── dashboard_sidebar.php    # Role-aware sidebar menu
│   └── dashboard_topbar.php     # Top bar with notifications & user profile
│
├── assets/
│   ├── css/
│   │   ├── style.css            # Public healthcare palette & responsive styles
│   │   └── dashboard.css        # Dashboard metric cards & tracking timeline
│   └── js/
│       ├── main.js              # Tooltips, flash auto-dismiss, sidebar toggle
│       └── search.js            # Live search filters & form reset
│
├── donor/
│   ├── dashboard.php            # Stats, availability toggle, emergency alerts
│   ├── profile.php              # Personal info, privacy status, badges
│   ├── edit_profile.php         # Edit profile, location & change password
│   ├── requests.php             # Filter & search matching blood requests
│   ├── request_details.php      # Accept/decline request with arrival note
│   ├── donation_history.php     # Past donation records & milestone badges
│   └── notifications.php        # Notification center & read status
│
├── requester/
│   ├── dashboard.php            # Active requests, donor responses, metrics
│   ├── create_request.php       # Post blood request (Generates unique LL-ID)
│   ├── my_requests.php          # Manage & cancel requests
│   ├── request_details.php      # 6-step dynamic request tracking timeline
│   ├── favourites.php           # Bookmarked donors directory
│   └── notifications.php        # Real-time donor response alerts
│
├── admin/
│   ├── dashboard.php            # Global stats, blood inventory & city charts
│   ├── users.php                # User management, verification & suspension
│   ├── donors.php               # Donor directory & availability controls
│   ├── requests.php             # All blood requests monitoring
│   ├── request_details.php      # Administrative status override
│   ├── reports.php              # Demand vs supply matrix & export view
│   ├── feedback.php             # Inquiries & incident reports
│   └── logs.php                 # System audit logs
│
├── index.php                    # Public homepage with live stats & hero search
├── about.php                    # Mission, vision, core values & disclaimer
├── find_donors.php              # Multi-filter donor finder with privacy cards
├── contact.php                  # Contact form (saves to DB) & 24/7 hotline
├── login.php                    # Role-based login with demo autofill
├── register.php                 # Dynamic Donor / Requester registration
├── forgot_password.php          # Password reset interface
├── logout.php                   # Secure session termination
└── install.php                  # 1-Click interactive setup wizard
```

---

## 🔒 Security & Best Practices Implemented
- **Password Security**: Strong hashing with PHP `password_hash()` (bcrypt) and `password_verify()`.
- **SQL Injection Prevention**: All queries use PDO prepared statements with bound parameters.
- **XSS Prevention**: Clean output escaping using `htmlspecialchars()`.
- **CSRF Defense**: Session-bound cryptographic tokens on form submissions.
- **Session Security**: `session_regenerate_id(true)` upon login, role-restricted dashboard guards.
- **Donor Privacy**: Sensitive mobile numbers and exact addresses are shielded from public scrapers; communication happens via secure request flows.

---

## 🗄️ Database Tables & Architecture (Phase 3.2 Documentation Review)

LifeLink uses normalized relational storage in MySQL (`lifelink_db`):

| Table | Status | Key Columns | Primary Usage |
|---|:---:|---|---|
| `users` | **Active** | `user_id`, `full_name`, `email`, `password`, `role`, `verification_status` | Core authentication & account roles |
| `donors` | **Active** | `donor_id`, `user_id`, `blood_group`, `availability`, `hide_phone` | Donor profiles, privacy settings & badges |
| `blood_requests` | **Active** | `request_id`, `unique_request_number`, `requester_id`, `blood_group`, `urgency` | Blood request lifecycle tracking (`LL-YYYY-XXXXXX`) |
| `request_responses`| **Active** | `response_id`, `request_id`, `donor_id`, `response_status`, `consent_*` | Donor response and medical consent records |
| `donations` | **Active** | `donation_id`, `donor_id`, `hospital_name`, `verification_status`, `verified_by` | Verified donation history & admin verification |
| `donor_screenings` | **Active** | `screening_id`, `donor_id`, `eligibility_status`, `screening_date` | 90-day recurring health screening reconfirmation |
| `notifications` | **Active** | `notification_id`, `user_id`, `notification_type`, `related_request_id` | In-app alerts (request, emergency, status) |
| `password_resets` | **Active** | `id`, `user_id`, `token_hash`, `expires_at`, `used_at` | Cryptographically secure token password recovery |
| `reports` | **Active** | `report_id`, `reported_by`, `reported_user`, `request_id`, `status` | User incident reporting & moderation (Phase 3.1) |
| `contact_messages`| **Active** | `message_id`, `name`, `email`, `subject`, `message`, `is_read` | Public Contact Us inquiries (`contact.php`) & admin moderation (`admin/feedback.php`) |
| `feedback` | **Reserved**| `feedback_id`, `user_id`, `subject`, `rating`, `message` | User review/rating schema placeholder (currently dormant, preserved per project specs) |
| `favourites` | **Active** | `favourite_id`, `user_id`, `donor_id` | Bookmarked donors for rapid re-contact |
| `admin_logs` | **Active** | `log_id`, `admin_id`, `action`, `details`, `ip_address` | Audit log of administrative state changes |
