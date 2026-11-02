# Live attendance correction timeline

Open Attendance → Attendance Corrections, select an employee and choose **Current shift · Live**. An overnight shift is selected by its start date, matching the live employee header. Use a specific shift start date to review history.

Yellow shows work; red shows breaks. Work continues after the scheduled end until a recorded OUT. Break totals count gaps within the scheduled shift. New punches refresh every 10 seconds; the work/break counters advance between refreshes. The view displays only punches already received and processed by HRMS; it cannot recover missing device uploads.

Drag a marker, use its exact time, or use the one-minute controls to correct attendance. Editing pauses the timeline at the last received snapshot. Enter a reason and Save correction. A new punch or concurrent edit blocks a stale save without replacing the draft. Discard edits & reload live data explicitly discards the draft and resumes updates. Original raw punches and correction history remain preserved.

## Shared hosting deployment

Back up the existing files. Upload the contents of the deployment ZIP into the employee portal root, preserving the assets/ and includes/ paths and replacing only the five matching files. This update requires the previously installed Attendance Corrections timeline. No schema migration or biometric machine changes are required. Refresh the portal after upload.

The patch changes:
- assets/css/attendance-timeline.css
- assets/js/attendance-timeline.js
- includes/attendance/timeline.php
- includes/attendance/timeline-view.php
- includes/foundation/controller.php

## Visible times and period actions

The timeline shows clock times on wider segments, a current-time marker, and a complete list of work/break periods with exact start/end dates, times and durations. Short periods remain readable in the list. All times use the shift timezone.

- **Remove break** changes that interval to work (for example, approved office duty).
- **Remove work** excludes that interval from worked time. An interior interval becomes break time; removing the first work period moves the first check-in to the next working period.
- Removing an ongoing period also changes the corrected current activity until the next recorded punch. Review the new status before saving.
- At least one working period must remain. To remove all work for a day, use the attendance sheet's absence correction instead.

These actions affect a draft only. Reset changes restores the previous timeline; saving requires a reason and retains the original punches and audit trail. The break summary counts only time within the shift, while individual period durations show the entire displayed interval.
