# SocialApp - Twitter/X-like Social Media Platform

A social media platform similar to Twitter/X built with PHP, JavaScript, Tailwind CSS, HTML, and MySQL.

## Features

- User registration and login with email verification
- Real-time clock display
- Profile pages with customizable profile and banner images
- Post creation and timeline
- Follow/unfollow functionality
- User search and discovery
- Mobile-friendly tablet interface design

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache, Nginx, etc.)
- SMTP server for email verification (optional)

## Installation

1. Clone or download this repository to your web server's document root (e.g., `htdocs` for XAMPP).

2. Create a MySQL database named `socialapp_db` (or update the database name in `includes/config.php`).

3. Configure the database connection in `includes/config.php`:
   ```php
   define('DB_SERVER', 'localhost');
   define('DB_USERNAME', 'your_mysql_username');
   define('DB_PASSWORD', 'your_mysql_password');
   define('DB_NAME', 'socialapp_db');
   ```

4. Configure email settings for verification in `includes/config.php`:
   ```php
   define('SMTP_HOST', 'smtp.example.com');
   define('SMTP_USERNAME', 'your_email@example.com');
   define('SMTP_PASSWORD', 'your_email_password');
   define('SMTP_PORT', 587);
   define('SMTP_FROM', 'noreply@yourwebsite.com');
   define('SMTP_FROM_NAME', 'SocialApp');
   ```

5. Install PHPMailer for email verification:
   - Create a directory `includes/PHPMailer`
   - Download PHPMailer from https://github.com/PHPMailer/PHPMailer/releases
   - Extract the files and place `PHPMailer.php`, `SMTP.php`, and `Exception.php` in the `includes/PHPMailer` directory

6. Download default images by accessing `download_default_images.php` in your browser.

7. Access the application through your web browser (e.g., `http://localhost/SocialApp`).

## Usage

1. Register a new account with a valid email address.
2. Verify your email by clicking the link sent to your email.
3. Log in with your username and password.
4. Explore users, follow interesting profiles, and create posts.
5. Customize your profile by adding a bio, phone number, and uploading profile and banner images.

## Directory Structure

- `auth/` - Authentication-related files (login, register, verify, logout)
- `includes/` - Core PHP files (config, functions, header, footer)
- `assets/` - Static assets (images, icons)
- `index.php` - Home page with timeline
- `profile.php` - User profile page
- `edit_profile.php` - Profile editing page
- `explore.php` - User discovery page

## Email Verification

If you don't have access to an SMTP server, you can disable email verification by modifying the registration process in `auth/register.php`. Find the section that checks for verification and modify it to automatically set users as verified.

## License

This project is open-source and available for personal and commercial use.

## Credits

- [Tailwind CSS](https://tailwindcss.com/)
- [Font Awesome](https://fontawesome.com/)
- [PHPMailer](https://github.com/PHPMailer/PHPMailer)
