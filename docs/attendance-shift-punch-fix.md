# Attendance shift and punch processing update

## Root causes found in source

- Biometric processing checked `users.active` (login access) instead of `employees.employment_status`. An employed person with disabled portal login could incorrectly fail as inactive.
- Overnight routing used a fixed six-hour cutoff and calendar-day logic. Check-ins after midnight were rejected by the shared calculation's calendar-date guard.
- An out-only event remained failed/pending even when a later delivery supplied the preceding check-in.
- Punch timestamps were rendered directly in canonical 24-hour format. Punch log and attendance report used visible page navigation.

These are confirmed code defects. The live portal requires sign-in in this session; individual production failures have not been inspected or reprocessed.

## Changes

- Use Active / Notice Period employment eligibility independently of login access. Archived/unavailable employees remain blocked; no employee or login status is changed.
- Resolve each punch against effective shift instances on adjacent dates and configurable pre/post buffers (default 120 minutes each). Prefer the closest valid shift; ambiguous windows require review.
- Preserve employee > department > company precedence and saved attendance snapshots.
- Retain explicit IN/OUT events. Unknown events use first and last valid timestamps within the shift instance; all intermediate raw punches remain stored.
- Later and out-of-order punches recalculate the existing biometric attendance row. Duplicates remain protected by existing fingerprints/event keys. Manual attendance and manual absence marks are protected.
- Reprocessing is authorized, device-scoped, limited to 100 unresolved punches per invocation, and audited. Successful recalculations are audited against their raw punch IDs.
- Add AM/PM shift input, overnight validation, and break hours/minutes. Add optional shift assignment/effective dates inside employee save, atomically with the employee record. Overlapping assignments are rejected rather than rewriting history.
- Show AM/PM punch times (with seconds), daily/monthly attendance, exception reports, employee attendance and device/mapping timestamps.
- Punch log uses 100-row keyset batches, scrolling and an accessible Load more/retry button. No numbered pagination. Attendance report retains its existing bounded date query and displays its computed rows in the scrolling container.

## Shift setup after deployment

Company Settings → Attendance → Shifts → **Set up Day & Night shifts** safely creates/reuses definitions:

| Shift | Start | End | Break | Weekly off |
|---|---|---|---|---|
| Day Shift | 09:00 AM | 06:00 PM | 1h 30m | Saturday, Sunday |
| Night Shift | 06:00 PM | 09:00 AM next day | 1h 30m | Saturday, Sunday |

No database migration is required. The explicit setup action retains matching definition IDs. It rejects conflicting matches. Existing assignment snapshots remain unchanged; assign updated definitions from the intended effective date, or use existing department/company default settings. It does not guess which shift each employee works.

## Live follow-through

1. Deploy the tested main commit through the existing Hostinger Git deployment.
2. Apply the shift setup and confirm effective employee/default assignments. Existing overlapping assignments must be ended through the existing history-preserving controls before a replacement starts.
3. Correct any genuinely unmapped biometric IDs. Do not reactivate or remap employees based only on a punch failure.
4. In Check-In / Check-Out Log, expand **Reprocess pending / failed punches**, select the device, and run Reprocess. The running office bridge also retries bounded unresolved batches.
5. Check a real day-shift and night-shift pair in Daily Attendance and Attendance Sheet. Confirm the bridge heartbeat is recent; UI/processing fixes cannot make an offline office bridge transmit punches.

No tables are dropped, truncated or reset. No employee, raw punch, mapping or existing attendance record is deleted. Stored timestamps retain canonical format. Production shift setup, deployment and reprocessing are pending live access; test fixtures run only in `peopleflow_ci`.

## Validation

The regression suite covers day/night first/last punches, middle-event retention, check-out arriving first, after-midnight check-in, same-record updates, duplicate delivery, disabled-login/current-employment separation, genuine inactive employment, failed-event retry, configurable buffers, weekend settings, AM/PM formatting and atomic employee shift creation. Existing tests cover manual protections, unmapped retry fairness, CSRF and permission gates. HTTP tests cover rendered controls, filters and incremental punch-log traversal.
