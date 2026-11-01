# Changelog

## 3.6.0 - Communication Center & Enterprise Messaging Suite

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
