# Fablead CRM

Fablead CRM is a robust Customer Relationship Management application built with Laravel. It provides comprehensive tools for managing deals, projects, tasks, and follow-ups.

## Features

- **Deals Management:** Track and manage sales deals through their lifecycle.
- **Project Tracking:** Organize and monitor projects with associated tasks.
- **Task Management:** Assign, track, and complete tasks efficiently.
- **Follow-ups:** Keep track of communications and follow-ups with clients.
- **Comprehensive Reporting:** Generate detailed reports for Deals, Projects, Tasks, and Follow-ups, including data filtering and CSV exports.

## Requirements

- PHP >= 8.1
- Composer
- MySQL/MariaDB or PostgreSQL
- Node.js & NPM

## Installation

1. Clone the repository:
   ```bash
   git clone <repository-url>
   cd crm-laravel
   ```

2. Install PHP dependencies:
   ```bash
   composer install
   ```

3. Create a copy of your `.env` file:
   ```bash
   cp .env.example .env
   ```

4. Generate an app encryption key:
   ```bash
   php artisan key:generate
   ```

5. Configure your database in the `.env` file and run migrations & seeders:
   ```bash
   php artisan migrate --seed
   ```

6. Start the local development server:
   ```bash
   php artisan serve
   ```

## License

This project is open-source software licensed under the [MIT license](https://opensource.org/licenses/MIT).
