# Chuyen Bien Hoa Youth Online

<p align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo">
</p>

<p align="center">
  A comprehensive social and community platform built with the Laravel framework.
</p>

---

## About This Project

Chuyen Bien Hoa Youth Online is a feature-rich web application designed to foster a vibrant online community. It combines a traditional forum system with modern social media features like user profiles, activity feeds, real-time chat, and stories. The platform also includes a detailed administrative backend for managing users, content, and school-related activities such as class schedules and student violations.

This project is built on the Laravel framework, leveraging its powerful features for routing, ORM, and authentication, with a modern frontend powered by Inertia.js.

## Key Features

- **User Authentication:** Secure user registration, login, password reset, and email verification.
- **User Profiles:** Customizable user profiles with avatars, bios, follower/following stats, and activity points.
- **Forum System:** Multi-level forums with main categories and subforums for organized discussions.
- **Topics & Comments:** Users can create topics, post comments, and engage in nested reply threads.
- **Voting System:** Upvote and downvote functionality for both topics and comments.
- **Real-time Chat:** Private and group chat functionality with message read receipts and file sharing.
- **Stories:** Ephemeral, 24-hour stories similar to Instagram or Facebook, with reactions and viewer tracking.
- **Activity Feed:** A personalized feed showing the latest posts from followed users.
- **Search:** Robust search functionality to find users and posts.
- **Admin Panel:** A comprehensive dashboard for administrators to manage users, forum content, school classes, schedules, student violations, and user reports.
- **Notification System:** In-app and email notifications for various events.

## Getting Started

Follow these instructions to get a local copy of the project up and running for development and testing purposes.

### Prerequisites

- PHP >= 8.1
- Composer
- Node.js & npm
- A database server (e.g., MySQL, PostgreSQL)

### Installation

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/your-username/your-repository.git
    cd your-repository
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
    Open the `.env` file and update the database credentials (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) and any other necessary configuration, such as mail settings.

6.  **Run database migrations:**
    ```bash
    php artisan migrate
    ```

7.  **Compile frontend assets:**
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
    ```
    The application will be available at `http://localhost:8000` by default.

## Usage

Once the application is running, you can register a new account or log in with an existing one. The main features are accessible through the navigation bar.

- **Admin Access:** To access the admin panel, a user must have their `role` set to `admin` in the `cyo_auth_accounts` table. The admin panel is available at the `/admin` prefix.

- **API:** The application exposes a versioned RESTful API under the `/api/v1.0/` prefix. Refer to the `routes/api.php` file for a full list of available endpoints.

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).