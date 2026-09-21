<?php
// Copy to mail.local.php on the server. Never commit the local configuration.
if (!defined('PEOPLEFLOW_INTERNAL')) { http_response_code(403); exit; }
return [
 'from' => 'hr@your-company.example', // Set a sender authorized by your hosting mail service.
 'base_url' => 'https://employeeportal.arkentechsolutions.com',
];
