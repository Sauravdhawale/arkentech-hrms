<?php
if(in_array((string)($_GET['page']??''),['salary','contracts','advances','components','payroll_loans','employee_salary','payroll_processing','payroll_history','payroll_reports','salary_structures','statutory_settings','payroll_settings'],true)){http_response_code(410);exit('Use Payroll Dashboard, Payslips, Payslip Designer or Configuration.');}
