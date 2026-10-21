MIS Helpdesk GUI Starter
========================

Files:
- index.php: New Request form (preserves your existing includes, dropdowns, report number display, and submit.php action)
- track-request.php: Track Request GUI only; database lookup is not implemented yet
- login.php: Login GUI only; authentication is not implemented yet
- css/style.css: Shared visual styles

Installation:
1. Back up your current project files first.
2. Copy these files into the same directory as your existing DB_connection.php and submit.php.
3. Copy css/style.css into your existing css folder (back up your existing style.css first).
4. Keep app/Model/Company.php, app/Model/User.php, DB_connection.php, and submit.php in their existing locations.
5. Test New Request submission before moving to the next step.

Important:
- Tracking and login are visual prototypes, not secure/functional authentication or database search.
- The sample tracking details are placeholders.
- The report number generation shown in index.php follows your current MAX(report_id)+1 approach; it can have concurrency issues if multiple users submit simultaneously. Server-side submission should remain authoritative for the final report ID.

Navigation update:
- The three page links are now inside a hamburger menu (three horizontal lines).
- Click the icon to open the menu; click outside or press Escape to close it.
