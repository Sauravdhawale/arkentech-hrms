# Attendance upgrade continuation — September 2026

Project: Sauravdhawale/arkentech-hrms. Review branch: codex/attendance-bridge-upgrade. PHP/MySQL, existing users and data preserved. User approved public repository publication; no repeat approval needed.

Specs: ATTENDANCE-UPGRADE-SPEC.md and BRIDGE-SPEC.md. Deployment instructions: ATTENDANCE-UPGRADE.md; bridge instructions: ../bridge/README.md.

Implementation includes Company Settings attendance configuration, default-OFF server/menu/API gate, dated employee→department→company shift selection, time policies, manual punches and absence, atomic CSV preview/import, reports, calendar, requests, device mappings/scoped hashed tokens, raw punch processing/protection/retry, and Windows durable SQLite bridge/service/build scripts.

Full PHP/MySQL/HTTP checks passed for commit 3efa122e1b5ed4741ab8206b4677673da408b20a in run 35917776901. Windows executable and server packages built in run 35917773741. Later absence/retry/offline-queue improvements require the final workflow checks. Fetch latest branch/main/run state to determine completion.

Local checkout is attendance-upgrade, based on 2b1cf707ee448dd4cc01636b149964a85dbd5bb1. GitHub publication uses the GitHub app, so local uncommitted diff and remote commits overlap. Do not force-push local history. Files tracked by GitHub must not also be saved in Library.

No Hostinger deployment or production migration has been performed. Correct root: public_html/employeeportal. No eSSL vendor SDK was provided. The default adapter fails explicitly until integrated; mock mode requires explicit test_mode=true. Do not claim hardware tests. User requests English replies.
