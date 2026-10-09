# Studio 94 SnapTrack — Project Context

## Project Overview
**SnapTrack** is a photography studio management and booking system designed for **Studio 94**. It facilitates booking management, payment tracking, inventory control, and customer engagement through an AI-powered interface.

### Key Technologies
- **Backend:** PHP (>= 8.1)
- **Database:** MySQL/MariaDB
- **Frontend:** Vanilla JS, CSS (Responsive), HTML
- **Integrations:**
  - **AI:** Google Gemini (Feedback analysis & Chatbot)
  - **Auth:** Google OAuth, MFA (TOTP), Email Verification
  - **Payments:** PayMongo, Manual GCash/Bank Tracking
  - **Email:** PHPMailer (SMTP), Resend API (HTTPS)
  - **Utilities:** Endroid QR Code, TCPDF/FPDF (PDF generation)

## Architecture & Structure
The project follows a modular structure with role-based access control (RBAC).

- `config.php`: Global configuration, `.env` loading, and constant definitions.
- `functions.php`: Core business logic, database helpers, and auto-migration logic.
- `index.php`: The main router that loads pages based on user roles (`admin`, `staff`, `client`).
- `pages/`: Contains role-specific UI and business logic.
  - `admin/`: Dashboard, Sales, Staff Management, Packages, Reports.
  - `staff/`: Booking Management, Walk-ins, Inventory, Schedule.
  - `client/`: Personal Booking, Dashboard, Payments, Photo Gallery.
  - `api/`: AJAX/Fetch endpoints for dynamic features (availability, payments, etc.).
- `includes/`: Shared components like the Chatbot UI and booking helpers.
- `sql/`: Database installation and schema definitions.
- `assets/`: Static files (CSS, JS, Images, Uploads).

## Building and Running

### Prerequisites
- PHP 8.1 or higher
- MySQL / MariaDB
- Composer

### Installation
1.  **Clone the repository.**
2.  **Install dependencies:**
    ```bash
    composer install
    ```
3.  **Environment Setup:**
    - Copy `.env.example` to `.env`.
    - Configure your database credentials and API keys (Gemini, Google OAuth, PayMongo, etc.).
4.  **Database Setup:**
    - Create the database specified in your `.env`.
    - Run the SQL script: `sql/install.sql` or visit `APP_URL/sql/install.php` in your browser.
5.  **Start the Server:**
    - For local development: `php -S localhost:8000` or use a local stack like XAMPP/Laragon.

## Development Conventions

### Coding Style
- **Procedural with Helpers:** The core logic is procedural, utilizing `functions.php` for shared utilities.
- **Naming:** CamelCase for functions in `functions.php`, snake_case for database columns and some local variables.
- **Database:** Use the `db()` helper in `functions.php` which returns a PDO instance.
- **Security:**
  - Use `password_hash()` and `password_verify()` for user authentication.
  - Prepared statements are mandatory for all SQL queries.
  - Role-based access is enforced in the router (`index.php`) and API endpoints.

### UI/UX
- **Responsiveness:** The project uses a custom responsive framework defined in `assets/css/styles.css`. Ensure new components follow the established breakpoints (768px for mobile, 1024px for tablet).
- **Modals:** Interactive features (like booking or payment logging) often use custom modals rather than separate pages.

### AI Integration
- **Gemini Helper:** Use `ai_helper.php` for interacting with Google Gemini. It includes templates for feedback analysis and general chat.
- **Chatbot:** The chatbot interface is located in `includes/chatbot.php`.

## Deployment
The project is configured for deployment using **Nixpacks**, making it compatible with platforms like **Railway**. Environment variables should be set in the deployment platform's dashboard.
