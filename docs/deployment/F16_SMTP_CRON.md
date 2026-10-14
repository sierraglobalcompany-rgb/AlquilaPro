# F16 SMTP and cron configuration

Set these environment variables in the PHP application process. SMTP credentials and the cron secret are read at startup and are not stored in the database.

| Variable | Required value |
| --- | --- |
| `ALQUILAPRO_SMTP_HOST` | SMTP hostname |
| `ALQUILAPRO_SMTP_PORT` | Port from 1 to 65535 |
| `ALQUILAPRO_SMTP_USERNAME` | SMTP account username |
| `ALQUILAPRO_SMTP_PASSWORD` | SMTP account password |
| `ALQUILAPRO_SMTP_ENCRYPTION` | `tls` (STARTTLS) or `ssl` (implicit TLS) |
| `ALQUILAPRO_SMTP_FROM_EMAIL` | Valid sender email address |
| `ALQUILAPRO_SMTP_FROM_NAME` | Sender display name |
| `ALQUILAPRO_CRON_SECRET` | Random secret with at least 32 characters |

Configure a scheduler to send `POST /cron/notifications` periodically with the `X-AlquilaPro-Cron-Secret` header. The endpoint has no session or CSRF token; the shared secret is checked before reminders are generated or jobs are claimed. Calendar dates use `America/Bogota`; queued timestamps are UTC. It generates daily-deduplicated reminders and processes at most 50 due jobs per request. Schedule it often enough to deliver candidate emails promptly; charge and obligation reminders repeat at most once per day, policy expiry notices are generated on the coverage end date, and maintenance visit notices are generated on the scheduled date.

The response exposes only generated, sent, and failed counts. SMTP exceptions are reduced to a generic stored error key.
