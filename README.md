# Class Manager Pro

Advanced Class, Batch, Student and Payment Management System with Tutor LMS and Razorpay Integration.

## Overview

Class Manager Pro is a WordPress admin plugin built to manage:

- Classes
- Batches
- Students
- Payments
- Attendance
- Tutor LMS enrollment
- Razorpay import and payment mapping
- Admin and teacher activity logs

This plugin is designed for coaching centers, training institutes, and class-based learning businesses that need a single admin workflow inside WordPress.

## Main Features

- Class management with default fee and next Tutor LMS course support
- Batch management with class mapping, teacher assignment, Tutor LMS course linking, fee due date, public intake form, and Razorpay page mapping
- Student management with class and batch assignment, fee tracking, notes, and payment status
- Payment management with manual and Razorpay payment support
- Tutor LMS integration for automatic student enrollment
- Razorpay Payment Pages import support
- Attendance Quick View inside each batch
- Teacher console for assigned batch access
- Bulk actions for classes, batches, and students
- CSV export tools
- Admin activity logs
- Teacher activity logs
- Direct follow-up email sending with `wp_mail()`
- One-click WhatsApp follow-up links
- Simplified dashboard and admin navigation

## Attendance Quick View

- Added inside the batch detail page
- Shows attendance for the selected date
- Displays student-wise status
- Supports Present, Absent, and Leave status
- Saves through AJAX without leaving the batch page
- Includes a quick summary for present, absent, and leave counts

## Delete System

- Delete buttons for classes, batches, and students use AJAX
- Delete confirmation popup is shown before removal
- Delete button changes to `Deleting...` during request
- Related records are cleaned safely during deletion
- Admin notices are shown for success and failure

## Integrations

### Tutor LMS

- Optional integration
- Assign Tutor LMS course at batch level
- Student is linked to WordPress user automatically
- Student is enrolled into the linked Tutor LMS course

### Razorpay

- Optional integration
- Uses Razorpay Payment Pages for import
- Imports only successful captured payments
- Payment Page import can create or update student records
- Duplicate payment IDs are skipped automatically

## Requirements

- WordPress latest stable version
- PHP 7.4+ recommended
- MySQL / MariaDB supported by WordPress
- Tutor LMS plugin for course enrollment features
- Razorpay API keys for Razorpay payment sync/import features

## Plugin Folder

Upload only this folder:

`class-manager-pro`

Do not upload the parent project folder. The plugin main file must remain:

`class-manager-pro/class-manager-pro.php`

## Installation on WordPress

### Method 1: Upload ZIP from WordPress Admin

1. Compress the `class-manager-pro` folder into `class-manager-pro.zip`
2. Open WordPress Admin
3. Go to `Plugins > Add New > Upload Plugin`
4. Upload `class-manager-pro.zip`
5. Click `Install Now`
6. Click `Activate`

### Method 2: Upload Directly to Server

1. Open your hosting file manager, SFTP, or terminal
2. Navigate to:

`wp-content/plugins/`

3. Upload the full `class-manager-pro` folder there
4. Open WordPress Admin
5. Go to `Plugins`
6. Activate `Class Manager Pro`

## Upload on Replit

Use this guide if your WordPress site is hosted or managed inside Replit.

### Option 1: Upload Plugin Folder in Replit Project

1. Open your Replit WordPress project
2. In the file sidebar, open:

`wp-content/plugins/`

3. Upload the `class-manager-pro` folder into that directory
4. Confirm the main plugin file exists at:

`wp-content/plugins/class-manager-pro/class-manager-pro.php`

5. Start or reload the Replit app
6. Open your WordPress admin panel
7. Go to `Plugins`
8. Activate `Class Manager Pro`

### Option 2: Upload ZIP from WordPress Admin on Replit

1. Zip the plugin folder as `class-manager-pro.zip`
2. Open the WordPress admin URL running on Replit
3. Go to `Plugins > Add New > Upload Plugin`
4. Upload the zip file
5. Install and activate it

## First-Time Setup

After activation:

1. Open `Class Manager Pro > Settings`
2. Add Razorpay API settings if required
3. Configure email and WhatsApp templates if needed
4. Configure attendance settings
5. Create at least one class
6. Create at least one batch inside a class
7. Add a Razorpay Payment Page ID to the batch if needed
8. Link Tutor LMS course to the batch if needed
9. Add students manually or through Razorpay/public intake flow

## Basic Workflow

### Step 1: Create Classes

- Go to `Class Manager Pro > Classes`
- Add class name
- Add description
- Set default fee
- Set next course if needed

### Step 2: Create Batches

- Go to `Class Manager Pro > Batches`
- Click `Add Batch`
- Select class
- Add batch name
- Assign teacher
- Link Tutor LMS course
- Set batch fee
- Set start date
- Set fee due date
- Save batch
- Add the Razorpay Payment Page ID if this batch should import paid students

### Step 3: Add Students

- Go to `Class Manager Pro > Students`
- Add student details
- Select class and batch
- Save student

### Step 4: Import Students from Razorpay

- Open `Class Manager Pro > Import`
- Choose a Razorpay Payment Page
- Review successful captured payments
- Select the class and batch
- Click `Import Students`

### Step 5: Track Attendance

- Open any batch
- Use `Attendance Quick View`
- Select date
- Mark attendance
- Save through AJAX

### Step 6: Track Payments

- Add payments from the payment section
- Import Razorpay payments if needed
- Review paid, pending, and partial fee status

### Step 7: Follow Up with Students

- Open a student record or batch student list
- Click `Send Email` to send a follow-up email directly through WordPress
- Click `WhatsApp` to open a prefilled WhatsApp message

## Safety and Behavior Notes

- Batch names are validated per class to avoid duplicates
- Delete actions use AJAX and capability checks
- Attendance is stored in the plugin attendance table
- Tutor LMS enrollment only runs when a linked course exists
- Razorpay features work only after keys are configured
- Scheduled reminders send email only
- WhatsApp opens through `wa.me` and does not use any external API

## Recommended Live Deployment Checklist

Before going live:

1. Test plugin activation on staging first
2. Confirm WordPress admin access is working
3. Confirm plugin menu loads correctly
4. Create one class, one batch, and one student
5. Test delete for student, batch, and class
6. Test attendance save inside batch page
7. Test Tutor LMS enrollment if Tutor LMS is active
8. Test Razorpay Payment Page import if Razorpay is in use
9. Test direct follow-up email with your SMTP plugin
10. Export CSV backups before production changes

## Troubleshooting

### Plugin does not appear in WordPress

- Confirm the folder name is `class-manager-pro`
- Confirm the main file is:

`class-manager-pro/class-manager-pro.php`

- Confirm the plugin was uploaded inside `wp-content/plugins/`

### Delete button does not remove records

- Confirm you are logged in as an admin user
- Confirm WordPress AJAX is working
- Check browser console and network tab
- Confirm no security plugin is blocking `admin-ajax.php`

### Razorpay import does not work

- Confirm Razorpay key ID and secret are saved
- Confirm webhook secret is correct
- Confirm payment page is linked to the right batch

### Tutor LMS enrollment does not work

- Confirm Tutor LMS is active
- Confirm the batch has a valid linked Tutor LMS course
- Confirm the student has a valid phone/email/user mapping

## File Structure

- `class-manager-pro.php` : Main plugin bootstrap
- `includes/` : Core logic, DB, Tutor LMS, Razorpay, helpers
- `admin/` : Admin pages
- `assets/js/` : Admin JavaScript
- `assets/css/` : Admin styles

## Final Notes

- Keep a backup before production deployment
- Use staging for first validation
- Upload only the plugin folder or plugin zip
- Do not rename internal files unless you also update references
