# MyLoginServer

A modern, extensible login server with admin dashboard, user management, AD integration, and secure SMTP mail support. Built with PHP and SQLite, fully containerized with Docker support.
After a user has successfully logged in, a defined selection of link addresses is displayed.

---

## 🚀 Features

- 🔐 Combined Login:
  - Local users with password
  - Active Directory (AD) login via LDAP
  - Inactive AD users are auto-created locally

- 👤 Admin User Management:
  - Activate/deactivate, assign roles, reset password
  - Welcome mail with login link
  - Import users via JSON

- 🔗 User-Specific Link Management:
  - Default links per user
  - Users can submit link suggestions

- ✉️ SMTP via PHPMailer:
  - Configured via `.env`
  - Supports encryption of sensitive variables
  - Mail test & config editor in admin UI

- 🛠️ Debugging & System Status:
  - Toggleable debug output on login
  - Debug panel and logging
  - Status tab with PHP info, file checks, mail setup, etc.

- 📦 Simple Structure, Bootstrap UI

- 🐳 Docker Support via `setup.sh`

---

## 🐳 Quickstart (Docker)

```bash
chmod +x setup.sh
./setup.sh
```

If needed:

```bash
sudo apt install dos2unix
dos2unix setup.sh
```

This script:
- checks for Docker
- launches container
- sets up SQLite DB
- creates `.env`, `audit.log`, `error.log`
- tests PHPMailer & Composer
- shows IP and login link

---

## ✉️ SMTP Configuration (`.env`)

Example:

```env
SMTP_HOST=smtp.example.com
SMTP_PORT=25
SMTP_FROM=noreply@example.com
SMTP_SECURE=none
SMTP_AUTH=off
ADMIN_EMAIL=admin@example.com
```

Manage and encrypt this via the Admin Mail tab. A template `.env.example` is included.

---

## 🔐 AD Configuration (`.envad`)

AD login is configured via `.envad`. This file supports encryption via `encryption.key`.

Example:

```env
AD_HOST=ldap://yourldap.server
AD_PORT=389
AD_BASE_DN=OU=user,DC=domain,DC=example
AD_USER_ATTRIBUTE=sAMAccountName
AD_DOMAIN=domain.example
AD_BIND_DN=CN=ldapreader,OU=user,DC=domain,DC=example
AD_BIND_PW=password
```

The Admin LDAP tab allows editing and encrypting `.envad`.

---

## 🔑 encryption.key

Create a file named `encryption.key` to define your own secret used for encrypting `.env` and `.envad` values.

- If it exists, it overrides the default XOR key.
- If missing or empty, a base64 fallback key is used (defined in code).

Do NOT commit this file to version control.

---

## 🧩 Web-based Link Editor

A clean JSON-driven UI for defining user links.

- Alias + URL table
- Assign to one, multiple or all users
- Full import/export to `.json`
- JSON display + download with current date
- Existing links can also be exported per user

### JSON structure:

```json
{
  "users": ["alice", "bob"],
  "links": [
    { "alias": "Heise", "url": "https://heise.de" },
    { "alias": "Golem", "url": "https://golem.de" }
  ]
}
```

---

## 🧪 Test URL

```bash
http://<your-ip>:8080
```

Default login after setup:

- Username: `admin`
- Password: `adminpass` (or as defined in `setup.sh`)

---

## 🔄 Architecture Notes

- Central config logic: `config_support.php`
- Supports: `.env`, `.envad`, encryption, PHPMailer
- Admin tabs:
  - Users
  - Links
  - Link Requests
  - Mail Config/Test
  - Log Viewer
  - System Status
- Log files: `audit.log`, `error.log` in root directory
- All admin files require `admin` role (`requireRole('admin')`), except `admin_tab_status.php`

---

## 📅 Last updated:
 2025-05-22 13:30:53 Europe/Berlin
