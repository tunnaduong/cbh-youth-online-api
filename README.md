# Chuyen Bien Hoa Youth Online

<p align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo">
</p>

<p align="center">
  A comprehensive social and community platform built with the Laravel framework.
</p>

---

## About This Project

Chuyen Bien Hoa Youth Online is a feature-rich community platform for students of THPT Chuyên Biên Hòa. It combines a traditional forum system with modern social media features like user profiles, activity feeds, real-time chat, and stories, plus a points wallet, a gift shop, and study tools. The platform also includes administrative tools for managing users, content, and school-related activities such as class schedules and student violations.

This repository is the Laravel 10 backend served at `https://api.chuyenbienhoa.com`. It exposes one JSON API used by the [web app](https://github.com/tunnaduong/cbh-youth-online-next-js) (chuyenbienhoa.com), the [mobile app](https://github.com/tunnaduong/cbh-youth-online-mobile), and the [gift shop](https://github.com/tunnaduong/cbh-youth-online-gift-shop) (giftshop.chuyenbienhoa.com). Authentication uses Laravel Sanctum bearer tokens and real-time features use Laravel Reverb. A legacy Inertia.js frontend still lives in `routes/web.php`, but the clients above don't use it.

## Key Features

- **User Authentication:** Secure user registration, login (username/password, Google, Facebook, Apple), password reset, email verification, a one-time handoff that signs the mobile app's user in on the web, optional two-factor login (email code or authenticator app), a list of logged-in devices with remote log-out, and an email when the account is used on a new device.
- **User Profiles:** Customizable user profiles with avatars, covers, bios, follower/following stats, activity points, and unlockable profile themes (avatar frames, name effects, profile effects).
- **Forum System:** Multi-level forums with main categories and subforums for organized discussions.
- **Topics & Comments:** Users can create topics with images and video, post comments, and engage in nested reply threads, with mentions and hashtags.
- **Voting System:** Upvote and downvote functionality for both topics and comments.
- **Real-time Chat:** Private and group chat with read receipts, reactions, file sharing, invite links, a public chat room, and an AI chat assistant.
- **Stories:** Ephemeral, 24-hour stories similar to Instagram or Facebook, with overlays, music, reactions, and viewer tracking.
- **Activity Feed:** A personalized feed showing the latest posts from followed users.
- **Search:** Robust search functionality to find users and posts.
- **Points & Wallet:** Daily check-in, point gifting, deposits via SePay bank transfer, and withdrawals. See [POINTS_SYSTEM_README.md](POINTS_SYSTEM_README.md).
- **Gift Shop:** Product catalog and orders paid with points, QR transfer, or cash on delivery, with a discount for verified students.
- **Learning Tools:** Study materials, AI-generated and custom quizzes, and mini games with leaderboards.
- **Admin Panel:** Admin APIs used by the web app's `/admin` dashboard, plus a Filament panel, for managing users, forum content, moderation, reports, deposits and withdrawals, student verification, the shop, school classes, schedules, and student violations.
- **Notification System:** In-app, email, Expo push (mobile), and Web Push notifications, plus a weekly newsletter.

## Getting Started

Follow these instructions to get a local copy of the project up and running for development and testing purposes.

### Prerequisites

- PHP >= 8.1
- Composer
- Node.js & npm
- A database server (e.g., MySQL)

### Installation

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/tunnaduong/cbh-youth-online-api.git
    cd cbh-youth-online-api
    ```

2.  **Install PHP dependencies:**
    ```bash
    composer install
    ```

3.  **Install JavaScript dependencies:**
    ```bash
    npm install
    ```

4.  **Create your environment file:**
    Copy the example environment file and generate your application key.
    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

5.  **Configure your environment (`.env`):**
    Open the `.env` file and update the database credentials (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) and any other necessary configuration, such as mail, Reverb, OAuth (Google/Facebook/Apple), VAPID, SePay, and AI keys. Use a persistent `CACHE_DRIVER` (`file` or `redis`, not `array`).

6.  **Run database migrations and link storage:**
    ```bash
    php artisan migrate
    php artisan storage:link
    ```

7.  **Compile frontend assets (legacy Inertia site only):**
    To build the assets for development and watch for changes:
    ```bash
    npm run dev
    ```
    For production, run:
    ```bash
    npm run build
    ```

8.  **Serve the application:**
    ```bash
    php artisan serve
    php artisan reverb:start    # websockets for chat
    php artisan queue:work      # media compression, AI replies, broadcasts
    php artisan schedule:work   # scheduled jobs (expired stories, newsletter)
    ```
    The application will be available at `http://localhost:8000` by default.

## Usage

Once the application is running, point a client (web, mobile, or gift shop) at it, or call the API directly.

- **Admin Access:** To use the admin features, a user must have their `role` set to `admin` in the `cyo_auth_accounts` table. The admin dashboard lives in the web app at `/admin` and calls the `/v1.0/admin/*` endpoints.

- **API:** The application exposes a versioned RESTful API under the `/v1.0/` prefix (for example `https://api.chuyenbienhoa.com/v1.0/login`). Refer to the `routes/api.php` file for a full list of available endpoints.

- **Deployment:** Production runs on an aaPanel server with nginx and PHP-FPM. See `deploy/` for the nginx upload-size and supervisor queue-worker configs.

## Contributing

The default branch is **`main`**: unless told otherwise, commit and push changes there.

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
