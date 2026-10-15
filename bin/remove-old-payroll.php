<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
fwrite(STDERR,"Old Payroll cleanup is retired. The new Payroll module may contain new data; nothing was deleted.\n");exit(1);
