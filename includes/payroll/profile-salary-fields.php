<?php // Shared fields. The enclosing form defines the employee; no employee selector is accepted here.
$salaryStructures=chr_rows($pdo,'pay_structure');
?>
<div class="form-grid three">
 <label>Salary amount (₹) *<input name="salary[salary_amount]" type="number" min="0.01" step="0.01" value="<?=h($salaryInput['salary_amount']??'')?>" required><span class="hint">Enter the agreed amount; the salary breakup is calculated from the selected rules.</span></label>
 <label>Amount is<select name="salary[salary_basis]"><?php foreach(['Monthly Gross','Annual CTC'] as $basis):?><option <?=($salaryInput['salary_basis']??'Monthly Gross')===$basis?'selected':''?>><?=h($basis)?></option><?php endforeach;?></select></label>
 <label>Salary structure / rules *<select name="salary[structure_id]" required><option value="">Choose salary structure</option><?php foreach($salaryStructures as $r)if($r['status']==='Active'):?><option value="<?=(int)$r['id']?>" <?=($salaryInput['structure_id']??'')==$r['id']?'selected':''?>><?=h($r['title'].' · from '.$r['values']['effective_from'])?></option><?php endif;?></select></label>
 <label>Effective from<input name="salary[effective_from]" type="date" value="<?=h($salaryInput['effective_from']??'')?>"><span class="hint">Blank uses the joining date. A revision must start after the latest saved salary.</span></label>
 <label>Effective until (optional)<input name="salary[effective_to]" type="date" value="<?=h($salaryInput['effective_to']??'')?>"><span class="hint">Leave blank for an ongoing salary.</span></label>
 <label>Reason for assignment / revision *<input name="salary[reason]" maxlength="1000" value="<?=h($salaryInput['reason']??'')?>" required></label>
</div>
<details class="profile-salary-extra"><summary>Component amounts & additional details</summary><p class="hint">Only manual components included in the selected salary structure apply. Keep existing values when revising salary.</p><div class="form-grid three">
<?php $shown=[];foreach(chr_rows($pdo,'pay_component') as $c){$cv=$c['values'];if($cv['calculation']!=='Manual'||$cv['section']==='Variable'||isset($shown[$cv['code']]))continue;$shown[$cv['code']]=true;?><label><?=h($c['title'].' ('.$cv['code'].')')?><input type="number" min="0" step="0.01" name="salary[manual][<?=h($cv['code'])?>]" value="<?=h($salaryInput['manual'][$cv['code']]??'0')?>"></label><?php } ?>
 <label>Custom hourly OT rate (₹)<input type="number" min="0" step="0.01" name="salary[employee_ot_rate]" value="<?=h($salaryInput['employee_ot_rate']??'0')?>"></label>
 <label>Bank account last 4 characters<input name="salary[bank_last4]" maxlength="4" value="<?=h($salaryInput['bank_last4']??'')?>"></label>
 <label>PAN last 4 characters<input name="salary[pan_last4]" maxlength="4" value="<?=h($salaryInput['pan_last4']??'')?>"></label>
 <label class="full">Notes<textarea name="salary[notes]" maxlength="3000"><?=h($salaryInput['notes']??'')?></textarea></label>
</div></details>
