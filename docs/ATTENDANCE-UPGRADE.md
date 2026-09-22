# Attendance upgrade — implementation and deployment

## Existing system reused
The upgrade extends the existing PHP/PDO application. It reuses users, employees, departments, role permissions, hr_records configuration, hr_requests regularisation, hr_punches raw punches, hr_attendance daily calculations, leave accounting and holiday configuration. Employee, department and company shift defaults resolve in that order. Dated overrides/defaults preserve snapshots; started assignments and policies cannot silently change history.

No production migration, account import, password reset or physical device test was performed by the development process.

## Deploy the server package
1. Back up the existing application and database.
2. Deploy the server ZIP contents directly into the configured subdomain root, normally `public_html/employeeportal`. Do not create `public_html/public_html/employeeportal`.
3. Preserve config/peopleflow.local.php, config/mail.local.php, uploads and storage. The package excludes local secrets and employee files.
4. Sign in as Super Admin. Enable Core HR if needed, then Company Settings → Attendance Settings → Enable Attendance Upgrade. This applies additive migration 006 with schema checks; existing users/passwords/data are retained.
5. Configure Attendance Settings, Shifts, Default Shift Assignments and Time Policies in Company Settings. Biometric mode defaults OFF. Manual attendance remains available.
6. Assign permissions to custom roles. New attendance import, approval, biometric, calendar and policy permissions are not automatically granted to existing custom roles.

## Manual attendance
The manual punch dialog writes Admin Manual provenance and an immutable punch-event record. Duplicate check-ins/check-outs are rejected. Corrections use optimistic record versions and a mandatory reason. CSV preview validates employee IDs, dates, times, effective shifts and existing rows. Confirming revalidates the complete batch under a transaction: any invalid row prevents all writes. Supported format is CSV, maximum 1,000 rows/2 MB. XLSX is not advertised.

## Bridge API
All requests require HTTPS and `Authorization: Bearer <device token>`.
- GET `/api/attendance/bridge/ping.php`: token/device validation, heartbeat and queued commands.
- POST `/api/attendance/bridge/punches.php`: `{device_serial, events:[{biometric_id,event_key,punched_at,direction}]}`. Maximum 500 events. `punched_at` uses the configured company timezone; direction is in/out/unknown. Successful response includes persisted=true and accepted/duplicates event-key arrays.
- POST `/api/attendance/bridge/sync-status.php`: `{device_serial,device_online,last_device_timestamp,error,ack_commands}`. Device health is distinct from bridge health.

Tokens are device-scoped, SHA-256 hashed at rest, displayed once and revocable/rotatable. The legacy shared-key `/api/punches.php` remains compatible but is also blocked when biometrics are OFF. Configure new installations with scoped bridge tokens.

Raw punches are retained before mapping/calculation. Unique source keys and fingerprints prevent retry duplication. Server processing preserves manual attendance. Unknown mappings and invalid shift resolution are retained for review. Overnight matching uses the previous scheduled shift and a six-hour post-shift window; verify your office schedule before production use.

## Current boundaries
- Actual eSSL SDK integration is pending the vendor SDK/model; no hardware success is simulated.
- Historical unmatched-punch reprocessing is not yet exposed as a self-service action.
- CSV imports require actual punch times; absence-only status imports are not supported.
- Department defaults are assigned one department at a time.
- Request validation saves a reviewed daily correction; multi-day corrections must be checked individually.
- Calendar events retain department/employee audiences; this release targets the Super Admin workspace, not employee calendar delivery.

## Validation
PHP syntax and pure attendance calculations; durable queue/retry tests; disposable MySQL migration, permission, manual punch, bridge mapping/deduplication/overnight, token revocation and calendar tests; authenticated HTTP route rendering. GitHub Actions supplies MySQL and the Windows executable build. Deployment and hardware validation are separate.
