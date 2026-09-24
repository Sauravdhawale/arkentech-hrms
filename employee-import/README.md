# Arkentech employee import source

107 employee records with HRMS employee IDs and eSSL IDs. Mapping: remove the AT prefix and leading zeros (AT00177 becomes 177).

This CSV is source data, not an executable SQL import. Do not import it directly into users, employees, or biometric mapping tables. Those tables require coordinated IDs, duplicate checks, and existing-account preservation. Status describes physical ID-card processing, not employment status.

Keep this branch private and outside the deployed website. Importing employees into the live database has not been performed by uploading this file.
