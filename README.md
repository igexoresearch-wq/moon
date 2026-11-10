# Moon

Minimal Apollo orbital simulator. Start at `index.php`. Import `database/schema.sql` into the `moonland` schema. Cockpits are gray and instruments are shared PHP includes. Physics is advanced by the `execute.php` cron worker.

## FIDO flight-dynamics display

Open `console.php?console=fido` or `fido_console.php`. The FIDO CRT is 1000 × 800 px; the selector sits to its left. The default format is `001 SUMMARY`. Additional implemented formats are `002 STATE VECTOR`, `003 ORBIT`, and `004 RENDEZVOUS`.

The `fido_displays` SQL table stores each button's key, label, sort order, enabled flag and matching PHP page filename. The FIDO console creates and seeds these four rows on its first load (or you may import `fido_displays.sql` in phpMyAdmin). Existing rows are not overwritten.

New formats require a reviewed, trusted PHP page in `fido_pages/` and a new database row. The selector does not execute PHP or HTML stored in SQL. The display pages use the live `objects` and `sim_clock` tables and reload every five seconds, without changing the selected button.

Deploy the complete `fido_pages/` directory plus `fido_console.php`, `fido_page.php` and the updated `console.php` to the same Moon folder on the web server. No changes to the physics worker or existing orbital state are required. Server-side execution remains to be validated.
