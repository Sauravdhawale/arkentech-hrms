<?php
/** Entry locks also apply while other employees in the month are still drafts. */
function pay_entry_locked(array $entry):bool {
 return !empty($entry['published_at'])||in_array($entry['status']??'',['Finalized','Paid'],true);
}
function pay_entry_published(array $entry):bool {
 return !empty($entry['published_at'])&&in_array($entry['status']??$entry['run_status']??'',['Finalized','Paid'],true);
}
function pay_entry_mutable(array $entry):void {
 if(pay_entry_locked($entry))throw new InvalidArgumentException('This payslip is finalized or published. Reopen payroll with a documented reason before changing it.');
}
