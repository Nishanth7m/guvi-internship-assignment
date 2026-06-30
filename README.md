# Secure Developer Guild Portal

A production-grade, secure session authentication and profile management system. This application demonstrates clean frontend-backend decoupling by storing session tokens in the browser's `localStorage` and validating them asynchronously on the backend using Redis, entirely bypassing native PHP sessions.

---

## 🚀 Architectural Overview

To prevent mixed code styles and tutorial patterns, this project implements a strict separation of concerns:
- **Presentation**: Semantic HTML5 cards customized with a bespoke dark glassmorphism system and real-time validation feedback.
- **Client-Side Scripting**: Async handlers utilizing **jQuery AJAX exclusively** for interacting with the API endpoints. No HTML form submissions or page-reloading queries are used.
- **API Backend**: Modulized PHP 8+ endpoints returning standardized JSON envelopes with proper HTTP status codes.
- **Storage Layer**:
  - **MySQL**: Holds core account credentials. Queries utilize `PDO` parameterized **Prepared Statements** to prevent SQL injection.
  - **MongoDB**: Holds rich developer-specific details (bio, age, dob, phone, address, and interactive skills lists) stored in document collections.
  - **Redis**: High-speed memory storage for session verification. Maps secure bearer tokens to user IDs with a 24-hour Time-to-Live (TTL).

---

## 📂 Project Structure

```
/ (Workspace Root)
├── index.html          # Portal entry landing page
├── register.html       # Account registration form interface
├── login.html          # Credential authentication form interface
├── profile.html        # Interactive user dashboard profile editor
├── .env                # Environment connection settings for MySQL, MongoDB, and Redis
├── README.md           # Professional installation and configuration guide
├── css/
│   └── style.css       # Design system style sheets containing glassmorphism variables & animations
├── js/
│   ├── index.js        # Dynamic button state loader for landing page
│   ├── register.js     # User registration jQuery AJAX and client validations
│   ├── login.js        # Auth session jQuery AJAX and localStorage token handler
│   └── profile.js      # Dashboard data fetch/update AJAX, skills tags engine & logout handler
└── php/
    ├── login.php       # Login controller verifying MySQL credentials & setting Redis token
    ├── register.php    # Registration controller creating MySQL row & MongoDB doc
    ├── profile.php     # Session-authorized endpoint for GET and POST updates
    ├── logout.php      # Session-invalidation endpoint destroying Redis keys
    └── config/
        ├── db.php      # Centralized Database classes loading env values
        └── helpers.php # Shared helpers for input checks, sanitizations & headers
```

---

## 🛠️ Installation & Server Setup

### 1. Prerequisites
Ensure you have a local server environment (like XAMPP, Laragon, or Nginx) running **PHP 8.0+**.

Ensure the following PHP extensions are enabled in your `php.ini`:
```ini
extension=pdo_mysql
extension=mongodb
extension=redis
```
*(Note: You can download precompiled `.dll` binaries for PHP extensions from the official PECL directory if they aren't pre-packaged with your server environment).*

### 2. MySQL Setup
1. Create a database named `guvi_internship`.
2. Execute the following SQL query to instantiate the `users` table:
```sql
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO-INCREMENT PRIMARY KEY,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3. MongoDB Setup
MongoDB collections will be created automatically upon first document write in `php/register.php`. The schema model maps user profiles as follows:
```json
{
  "mysql_user_id": 1,
  "age": 25,
  "dob": "2001-06-30",
  "phone": "+919876543210",
  "address": "Salem, India",
  "bio": "Full-stack developer focused on performance.",
  "skills": ["PHP", "MySQL", "MongoDB", "Redis", "jQuery"],
  "updated_at": ISODate("2026-06-30T10:00:00Z")
}
```
*Tip: To optimize queries, run the following index command in your MongoDB Shell:*
```javascript
use guvi_internship;
db.profiles.createIndex({ mysql_user_id: 1 }, { unique: true });
```

### 4. Redis Setup
Make sure the Redis server daemon is running locally:
```bash
# Verify connection
redis-cli ping
# Response should be PONG
```
Tokens are saved using the string keyspace: `session:<token> -> <mysql_user_id>` with a expiration of 86400 seconds (24 hours).

### 5. Application Configuration
Copy the `.env` template in the workspace root and adjust port/host configurations according to your local environment ports:
```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=guvi_internship
DB_USER=root
DB_PASS=your_mysql_password

MONGO_HOST=127.0.0.1
MONGO_PORT=27017

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

---

## 🧪 Comprehensive Testing Checklist

Follow this checklist to verify correctness of the implementation:

### 1. Registration Flow
- [ ] **Empty Form Fields**: Click "Create Account" with blank fields. Confirm that input fields highlight in red and show correct error messages.
- [ ] **Weak Password**: Try registering with a password like `1234567` or `abcdef`. Verify validation blocks it (minimum 8 characters, containing both letters and numbers).
- [ ] **Email Validity**: Input an email like `john@com` or `john.doe`. Check for validation feedback.
- [ ] **Password Mismatch**: Supply mismatched passwords. Confirm it blocks submission.
- [ ] **Duplicate Email**: Register a user, then attempt registering another account with the same email. Confirm the API returns a `409 Conflict` response saying "Email address is already registered."

### 2. Login Flow
- [ ] **Incorrect Credentials**: Submit a wrong password or unregistered email. Confirm you receive a `410 Unauthorized` response indicating "Invalid email address or password."
- [ ] **Successful Authentication**: Submit correct details. Verify a success toast appears, the page redirects to `profile.html`, and a token named `auth_token` is present in browser LocalStorage (`Developer Tools > Application > Local Storage`).

### 3. Session Authorization
- [ ] **Unauthorized Access**: Delete the `auth_token` from your browser LocalStorage and try accessing `profile.html` directly. Verify you are automatically redirected back to `login.html`.
- [ ] **Backend Invalidation**: Intentionally supply a fake token in `auth_token` or modify the value. Verify the API returns `401 Unauthorized` and the frontend redirects to `login.html`.

### 4. Profile Management & MongoDB Updates
- [ ] **Profile Load**: Upon landing on `profile.html`, check that details loaded dynamically (User Name/Email from MySQL, other details empty/default). Initials should match your name.
- [ ] **Form Validations**: Input invalid age (> 120 or negative) or phone numbers. Confirm validation outlines flag the entries.
- [ ] **Interactive Skills Editor**: Add a skill (e.g. `React`), press Enter or click Add. Verify it renders a custom badge tag. Click the `x` on the badge to confirm deletion.
- [ ] **Save Synchronisation**: Input details and click "Save Profile Changes." Verify loading indicator animates and a toast confirms synchronization.
- [ ] **Data Verification**: Reload the page. Verify the details (including the skills list and bio) reload perfectly from both MySQL and MongoDB databases.

### 5. Logout
- [ ] **Logout trigger**: Click "Logout" from the navbar. Verify the loader displays, the token is deleted from Redis (run `KEYS *` in `redis-cli` to verify session key deletion), `localStorage` is cleared, and you redirect back to `login.html`.

---

## 🔮 Future Improvements

1. **JSON Web Tokens (JWT)**: While standard cryptographically secure random tokens stored in Redis are very fast and easily revokable, converting them to cryptographically signed JWTs allows stateless payload reads.
2. **Access Control Policies**: Introduce role-based access controls to support multiple privilege levels (e.g., standard users and administrators).
3. **Database Migration Scripts**: Write database migration tools to bootstrap databases, collections, and index properties programmatically.
