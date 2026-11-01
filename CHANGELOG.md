# Changelog

## 3.6.2 - Deep Server Load Reduction, Anti-Spam Elimination & Direct WhatsApp Student Dispatch

- **Server Load & Database Write Reductions**:
  - Gated teacher console view logging (`batch_viewed`, `student_viewed`) behind 30-minute session transients to eliminate thousands of redundant database writes on page refreshes.
  - Cached schema checks and database table queries, preventing repeated `SHOW TABLES` and `SHOW COLUMNS` lookups across requests.
  - Aggregated multi-card dashboard metrics into unified SQL queries, cutting dashboard database hits by over 70%.
- **Email Anti-Spam Overhaul**:
  - Fixed WordPress core `wp_new_user_notification` to respect `cmp_notify_admin_on_admission` instead of hardcoding `'both'`, stopping automated WordPress registration spam to the admin on every student intake.
  - Added suppression filters for Tutor LMS native instructor and admin notification emails during batch enrollment, preventing teacher email inboxes from flooding with enrollment alerts.
  - Switched individual admission alerts to a consolidated, beautiful Daily Institute Digest Report with real-time in-app dashboard notification logs.
- **Direct Student WhatsApp API & Webhooks**:
  - Added individual one-click "WA API" direct dispatch button in student table rows with instant AJAX feedback.
  - Added "Send WhatsApp (Direct API)" option to student bulk actions for instant mass student notification.
  - Built direct WhatsApp message delivery supporting Meta WhatsApp Cloud API and custom webhook gateways (AiSensy, Wati, UltraMsg, WA-Sender).
- **UI/UX & Frontend Polish**:
  - Fixed `.cmp-action-grid` on Dashboard from rigid 5 columns to responsive auto-fit grid (`minmax(200px, 1fr)`), preventing cramped cards and overflowing text.
  - Balanced public student intake form hero header and summary cards on desktop and mobile.
  - Polished action buttons in table rows with WhatsApp API badge styling.

## 3.6.1 - Server Load Optimization, WhatsApp API & Webhook Suite, Email Anti-Spam & UI/UX Optimization

- **Server Load Optimization**:
  - Gated continuous 1000ms countdown timers so they only execute when countdown elements actually exist in DOM and pause when tab is inactive.
  - Implemented 500ms debouncing and minimum query length checks on live search filters to prevent excessive `admin-ajax.php` requests.
  - Added transient caching with memory layers for heavy database queries (batch overview metrics, student overview metrics, dashboard revenue summaries) with automatic invalidation on updates.
  - Prevented redundant `SHOW COLUMNS` schema inspection queries during public requests, REST requests, and AJAX operations.
- **Email Spam Prevention & Daily Digest**:
  - Replaced per-admission instant email spam with an automated consolidated Daily Institute Digest Report for admin and teachers.
  - Added configurable anti-spam toggles in Settings to keep teacher and admin inboxes clean while maintaining a real-time live audit trail in the dashboard.
- **Direct WhatsApp API & Webhook Suite**:
  - Added `includes/whatsapp.php` supporting Meta WhatsApp Cloud API (Graph API) and Custom WhatsApp Gateway Webhooks (AiSensy, Wati, UltraMsg, WA-Sender).
  - Added incoming Webhook endpoint (`/wp-json/cmp/v1/whatsapp-webhook`) with verification handshake support (hub.challenge) and receipt logging.
  - Automated direct WhatsApp welcome messaging to students upon admission with customizable dynamic tags.
  - Added a live "Send Test Message" diagnostic utility in Settings.
- **UI/UX & Frontend Touch-Up**:
  - Eliminated mobile table breaking and row-collapse overlaps by providing clean, horizontal responsive scrolling with sticky headers.
  - Fixed action buttons in table rows into compact, modern pills to prevent column width explosions.
  - Redesigned public student intake form progress indicator into a sleek, responsive step bar that never wraps jaggedly or overlaps.
  - Prevented toolbar and filter form controls from stretching vertically into infinite columns on smaller screens.

- Added a new Communication Center with Email Center, WhatsApp Center, Campaign Manager, Templates, Automation, History, Statistics, and Settings pages.
- Added reusable communication tables for templates, campaigns, messages, and automation rules without removing any existing features or data tables.
- Added dispatch hooks for student registration, payment recording, fee reminders, batch announcements, and batch completion workflows.
- Added scheduled campaign handling, message history logging, template archiving, and admin/AJAX actions for enterprise communication workflows.
- Fixed a load-time fatal from a duplicated Communication Center page callback and restored the missing template archive handler.
- Kept all admissions, operations, CRM, analytics, Tutor LMS, and Razorpay workflows backward compatible.

## 3.5.0 - Operations Center & Smart Planning System

- Added a new Operations Center module with dashboard, calendar, monthly planner, batch schedule, teacher schedule, marketing planner, business planner, document center, holiday manager, goals, and settings pages.
- Added FullCalendar-backed scheduling with AJAX refreshes, filterable views, event editing, drag-and-drop rescheduling, and ICS export support.
- Added operations data tables for events, documents, and goals so planning records are stored cleanly without changing existing workflows.
- Added reminder processing, notification hooks, and scope-aware teacher/admin access for the new operations screens.
- Preserved existing pages, tables, and business logic while extending the admin experience for production use.

## 3.4.0 - Production Release

- Redesigned the admin experience around workflow-first navigation with clearer Admissions, Academics, Finance, Reports, Teachers, and Settings groupings.
- Added inline AJAX saves and live section refreshes for student, class, batch, payment, attendance, CRM follow-up, and follow-up email actions to reduce full page reloads.
- Improved the dashboard with stronger action-oriented entry points, quick links, and attention-focused admin cues.
- Tightened responsive behavior and admin layout spacing so the plugin feels more usable on smaller screens and in the teacher console.
- Kept all existing pages, features, integrations, and business logic intact while improving stability and interaction speed.

## 3.3.0 - CRM, Reports & Business Intelligence

- Upgraded Interested Students into a mini CRM with follow-up status, next follow-up date, contact attempts, email and WhatsApp status, notes, assigned teacher tracking, and a visible activity timeline.
- Added advanced CRM exports for all records, filtered records, selected rows, and status-based subsets with CSV and Excel output support.
- Improved analytics into a business intelligence dashboard with growth, revenue, admissions, payment, lead-source, repeat-student, and comparison widgets.
- Added lead-source analytics, repeat-student reporting, teacher performance insights, top cities, top courses, batch occupancy, and data-quality warning summaries.
- Standardized city normalization while preserving the original city value for reporting and auditing.
- Made email mandatory for student registration across admin admission and public registration flows, while preserving existing duplicate-handling and batch-assignment behavior.

## 3.2.0 - Admission & Registration System Upgrade

- Redesigned the registration workflow so public submissions, admin admissions, imports, and Razorpay-driven enrollments all flow through one shared admission pipeline.
- Improved temporary registration links with creation time, countdown-style remaining validity, manual close handling, and clearer active, expired, closed, and used status labels.
- Expanded permanent registration support for free batches with copy actions, QR code preview, registration totals, last registration timestamps, and link status reporting.
- Added duplicate-safe student matching across email and mobile so existing students are reused and assigned to the selected batch instead of creating duplicates.
- Added batch capacity enforcement with optional admin override support for manual admissions and clearer batch-full feedback.
- Added a batch admissions dashboard with registration overview cards, link summaries, and searchable registration history.
- Upgraded the public registration form with a clearer progress indicator and a more professional success screen.
- Expanded admission notifications so student, admin, and teacher emails reuse the existing notification stack after successful registration.

## 3.1.0 - Critical Stability & Razorpay Update

- Fixed Razorpay payment accounting so the stored values now represent student fee, Razorpay commission, and net amount received separately.
- Updated dashboard, analytics, teacher reporting, payment views, CSV export, and Razorpay import previews to use net revenue calculations.
- Added a permanent registration link option for free batches and kept paid batches on temporary secure links.
- Reworked the public registration flow so successful submissions always land on a professional confirmation page.
- Improved Razorpay import error handling and summary messaging for imported, skipped, duplicate, and failed records.
- Refined public registration and admin workspace presentation for clearer workflows.
