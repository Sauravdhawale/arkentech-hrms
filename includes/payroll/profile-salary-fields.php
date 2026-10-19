<?php // Shared profile salary fields; employee identity comes only from the enclosing form.
require_once __DIR__.'/profile-salary.php';
$salaryRows=chr_rows($pdo,'pay_structure');
$salaryStructureDate=($salaryInput['effective_from']??'')?:pay_profile_default_date();
$salaryInput['effective_from']=$salaryStructureDate;
$salaryRules=pay_profile_structure_choices($salaryRows,$salaryStructureDate,(int)($salaryInput['structure_id']??0));
if($salaryRules['selected'])$salaryInput['structure_id']=$salaryRules['selected']['id'];
$salaryRuleData=[];$salaryManualFields=[];
foreach($salaryRows as $r){
 if($r['status']!=='Active')continue;
 $v=$r['values'];$salaryRuleData[]=['id'=>(int)$r['id'],'title'=>$r['title'],'family'=>strtoupper(trim((string)($v['code']??$r['title']))),'from'=>$v['effective_from'],'until'=>$v['effective_to']?:''];
 foreach(($v['components']??[]) as $c)if(($c['calculation']??'')==='Manual'&&($c['section']??'Fixed')!=='Variable')$salaryManualFields[$c['code']]=$c['name']??$c['code'];
}
$salaryOptionsOpen=!empty($error)||!empty($salaryDraftError)||(!$salaryRules['selected']&&count($salaryRules['effective'])>1);
?>
<div class="profile-salary-fields" data-salary-editor data-salary-month-start="<?=h(pay_profile_default_date())?>" data-salary-date-touched="<?=!empty($error)?'1':'0'?>" data-salary-rules="<?=h(json_encode($salaryRuleData,JSON_THROW_ON_ERROR))?>">
 <div class="form-grid salary-primary-fields">
  <label><span data-salary-amount-label><?=($salaryInput['salary_basis']??'Monthly Gross')==='Annual CTC'?'Annual CTC (₹)':'Monthly salary (₹)'?> *</span><input name="salary[salary_amount]" type="number" min="0.01" step="0.01" value="<?=h($salaryInput['salary_amount']??'')?>" required><span class="hint"><span data-salary-basis-note><?=($salaryInput['salary_basis']??'Monthly Gross')==='Annual CTC'?'Annual company cost; company rules calculate the monthly salary.':'Monthly gross before attendance and other deductions.'?></span></span></label>
  <label>Applies from *<input name="salary[effective_from]" type="date" value="<?=h($salaryStructureDate)?>" required><span class="hint">The date this salary starts. It does not need to match the joining date.</span></label>
 </div>
 <p class="salary-rules-note <?=!$salaryRules['selected']?'salary-rules-warning':''?>" data-salary-rule-note aria-live="polite"><?php if($salaryRules['selected']):?>Salary breakup uses <?=h($salaryRules['selected']['title'])?> automatically.<?php else:?><?=h($salaryRules['issue'])?><?php endif;?></p>
 <?php foreach(['reason','notes'] as $salaryField):?><input type="hidden" name="salary[<?=h($salaryField)?>]" value="<?=h($salaryInput[$salaryField]??($salaryField==='reason'?'Initial salary':''))?>"><?php endforeach;?>
 <details class="profile-salary-extra" <?=$salaryOptionsOpen?'open':''?>>
  <summary>More salary options <span>Rules, optional end date and additional amounts</span></summary>
  <div class="form-grid">
   <label>Salary rules<select name="salary[structure_id]" required><option value="">Choose applicable salary rules</option><?php foreach($salaryRules['choices'] as $family=>$r):?><option value="<?=(int)$r['id']?>" data-family="<?=h($family)?>" <?=((int)($salaryInput['structure_id']??0)===(int)$r['id'])?'selected':''?>><?=h($r['title'].' · from '.$r['values']['effective_from'])?></option><?php endforeach;?></select><span class="hint">Company rules calculate the salary breakup. Choose a different set only when needed.</span></label>
   <label>Amount entered as<select name="salary[salary_basis]"><?php foreach(['Monthly Gross','Annual CTC'] as $basis):?><option <?=($salaryInput['salary_basis']??'Monthly Gross')===$basis?'selected':''?>><?=h($basis)?></option><?php endforeach;?></select><span class="hint">Use Monthly Gross for the normal monthly salary.</span></label>
   <label>Applies until<input name="salary[effective_to]" type="date" value="<?=h($salaryInput['effective_to']??'')?>"><span class="hint">Leave blank. A future salary change will end this salary automatically.</span></label>
  </div>
  <?php if($salaryManualFields):?><div class="salary-additional"><h4>Additional amounts</h4><p class="hint">Only manual components in the selected rules apply. Keep the saved values when increasing salary.</p><div class="form-grid"><?php foreach($salaryManualFields as $salaryCode=>$salaryLabel):?><label><?=h($salaryLabel)?> (₹)<input type="number" min="0" step="0.01" name="salary[manual][<?=h($salaryCode)?>]" value="<?=h($salaryInput['manual'][$salaryCode]??'0')?>" required></label><?php endforeach;?></div></div><?php endif;?>
  <?php foreach(($salaryInput['manual']??[]) as $salaryCode=>$salaryValue)if(!isset($salaryManualFields[$salaryCode])):?><input type="hidden" name="salary[manual][<?=h($salaryCode)?>]" value="<?=h($salaryValue)?>"><?php endif;?>
  <div class="form-grid three">
   <label>Custom hourly overtime rate (₹)<input type="number" min="0" step="0.01" name="salary[employee_ot_rate]" value="<?=h($salaryInput['employee_ot_rate']??'0')?>"></label>
   <label>Bank account last 4 characters<input name="salary[bank_last4]" maxlength="4" value="<?=h($salaryInput['bank_last4']??'')?>"></label>
   <label>PAN last 4 characters<input name="salary[pan_last4]" maxlength="4" value="<?=h($salaryInput['pan_last4']??'')?>"></label>
  </div>
 </details>
</div>
