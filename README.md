🇮🇳 Open Source Donation Manager

The Digital Receipt Book for Modern NGOs & Non-Profits

💸 COST SAVING ALERT: Stop spending money on printing physical receipt books! This app lets you go 100% digital, saving paper and thousands of rupees in printing costs while ensuring transparency.

📖 About

A mobile-first PHP application designed specifically for Indian NGOs, Trusts, and Charities. It streamlines the donation collection process, manages volunteers, and issues instant 80G/PAN compliant digital receipts directly via WhatsApp.

✨ Key Features

🏢 For Organizations (Admin)

Zero Printing Costs: Replace physical receipt books with unlimited digital PDFs.

Multi-Org Support: Manage multiple trusts/entities from a single dashboard.

Volunteer Control: Approve registrations, assign access, and instantly disable access (Kill Switch).

Financial Reports: Real-time reporting with Cash vs. Online breakdowns and date filters.

Statutory Compliance: Built-in fields for PAN, 80G Registration No, and Indian currency formatting.

Soft Delete: Maintain financial history even after removing a volunteer.

🏃 For Volunteers (Collectors)

Mobile-First UI: Works perfectly on smartphones for field collection.

Secure Login: OTP-based login (No passwords to forget).

Smart Collection:

UPI: Generates dynamic QR codes for scanning.

Cheque/Bank: Records Cheque No, Bank Name, and Dates.

Instant Receipts: Generates PDF receipts signed digitally by the collector.

WhatsApp Integration: One-click share button to send receipts to donors.

🛠️ Tech Stack

Backend: PHP (Vanilla, optimized for performance)

Database: MySQL

Frontend: Bootstrap 5 (Responsive Design)

Security: CSRF Tokens, Prepared Statements, XSS Sanitization, Session Hardening

⚙️ Installation & Setup Guide

Follow these steps to set up the project on your local machine or server.

Prerequisites

XAMPP / WAMP / MAMP (or any Apache/Nginx server with PHP & MySQL).

PHP 7.4 or higher.

Git (Optional, to clone the repo).

Step 1: Get the Code

Clone this repository into your server's root directory (e.g., htdocs for XAMPP).

cd D:\Installations\xampp\htdocs\
git clone [https://github.com/codecasm/open-donation-manager.git](https://github.com/codecasm/open-donation-manager.git)


Or download the ZIP and extract it to a folder named donation_app.

Step 2: Database Setup

Open phpMyAdmin (http://localhost/phpmyadmin).

Create a new database named donation_app_v2.

Click Import tab.

Choose the file database.sql provided in this repository and click Go.

Step 3: Configuration

Open the folder in your code editor (VS Code / Notepad).

Rename config.example.php to config.php (if applicable, or just edit config.php).

Update the database credentials if you changed them (Default XAMPP settings are usually correct):

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Leave empty for default XAMPP
define('DB_NAME', 'donation_app_v2');


Step 4: Permissions (Important)

Ensure the uploads/ folder exists and is writable so the app can save Organization Logos and QR Codes.

Windows: Right-click folder > Properties > Security > Allow Write.

Linux: chmod -R 755 uploads/

🚀 How to Run the App

1. Start Server

Open XAMPP Control Panel and start Apache and MySQL.

2. Access Admin Panel

Open browser and go to: http://localhost/donation_app/admin_login.php

Default Admin Credentials:

Mobile: 9999999999

Password: admin123 (Please change this in the database for production!)

3. Setup Organization

Once logged in as Admin, go to the Organizations tab.

Click Add New Organization.

Fill in details (Name, PAN, 80G, UPI ID for QRs).

Upload your Logo and a QR Code image (e.g., screenshot of your GooglePay QR).

4. Add Volunteers (Collectors)

Open http://localhost/donation_app/login.php in a different browser/incognito window.

Register a new user with a Mobile Number and Name.

Back to Admin Panel: Go to the Approvals tab and click Approve.

Go to Manage Access tab, select the user, select the Organization, and click Assign.

5. Start Collecting!

The volunteer can now login (using the Mock OTP displayed in the alert/console).

Select the Organization.

Fill in donor details, select Payment Mode, and click Make Receipt.

🤝 Contributing

Contributions are welcome! Please fork the repository and create a pull request.

📄 License

This project is licensed under the MIT License.

Created with ❤️ by CodeCasm.com