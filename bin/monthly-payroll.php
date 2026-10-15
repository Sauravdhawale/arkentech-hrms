<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
fwrite(STDERR,"The old Payroll module has been retired. No processing was performed.\n");exit(1);
