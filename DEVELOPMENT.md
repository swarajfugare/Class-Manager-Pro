# Development Notes

## Release: 3.6.0

This release adds the new Communication Center for enterprise email, WhatsApp, campaign, template, automation, history, and statistics workflows.

## What Changed

- New admin module pages under Communication Center.
- New database tables for communication templates, campaigns, messages, and automation rules.
- Admin-post and AJAX handlers for templates, campaigns, automation, settings, test messages, and history updates.
- Scheduled campaign runner and message dispatch hooks for admissions and batch workflows.
- Template archiving, notification logging, and history/statistics summaries for communication activity.

## Compatibility Rules

- Keep all existing pages, workflows, and database tables intact.
- Do not remove legacy admin flows.
- Preserve backward compatibility for admissions, payments, CRM, analytics, operations, and Razorpay imports.

## Validation Checklist

- Check Communication Center dashboard load.
- Check template save, archive, and preview flows.
- Check campaign save, run, cancel, and scheduled runner behavior.
- Check WhatsApp link generation and test email delivery.
- Check history filters and statistics counters.
- Check admin access rules and cron execution safety.

## Notes

- PHP linting was not available in this environment.
- JavaScript syntax was validated locally with `node --check`.
