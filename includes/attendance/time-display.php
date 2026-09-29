<?php
/** Presentation only: persisted times remain canonical. */
function att_time_label(?string $value,bool $seconds=false,bool $date=false):string {
 if(!$value)return '—';
 try{return (new DateTimeImmutable($value))->format(($date?'d M Y · ':'').($seconds?'h:i:s A':'h:i A'));}catch(Exception $e){return $value;}
}
function att_shift_label(array $shift):string {
 $v=$shift['values']??$shift;
 return ($shift['title']??'Shift').' — '.att_time_label($v['start']??null).' to '.att_time_label($v['end']??null).(($v['end']??'')<($v['start']??'')?' · Ends next day':'');
}
function att_time_input(string $name,string $label,array $values):void {
 $value=$values[$name]??'';
 echo '<label>'.h($label).'<input name="'.h($name).'" value="'.h($value?att_time_label($value):'').'" placeholder="09:00 AM" pattern="(0?[1-9]|1[0-2]):[0-5][0-9] [AaPp][Mm]" required><span class="hint">12-hour time · e.g. 06:00 PM</span></label>';
}
