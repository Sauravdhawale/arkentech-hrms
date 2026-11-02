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
