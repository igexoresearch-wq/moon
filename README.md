# Moon

Minimal Apollo orbital simulator. Start at `index.php`. Import `database/schema.sql` into the `moonland` schema. Cockpits are gray and instruments are shared PHP includes. Physics is advanced by the `execute.php` cron worker.

## FIDO flight-dynamics display

Open `console.php?console=fido` or `fido_console.php`. The FIDO CRT is 1000 × 800 px; the selector sits to its left. The default format is `001 TRAJECTORY`. Additional implemented formats are `002 ORBITAL DATA`, `003 POWERED FLIGHT`, and `004 RENDEZVOUS`. The displays use Apollo-inspired flight-dynamics terminology but SI-based teaching units: distances in kilometers, velocities and range rates in meters per second, time intervals in seconds, and angles in degrees. They do not show raw simulation coordinates or tick counters.

The `fido_displays` SQL table stores each button's key, label, sort order, enabled flag and matching PHP page filename. The FIDO console creates and seeds these four rows on its first load (or you may import `fido_displays.sql` in phpMyAdmin). Existing buttons' Apollo labels and order are updated while their enabled/disabled settings are preserved.

New formats require a reviewed, trusted PHP page in `fido_pages/` and a new database row. The selector does not execute PHP or HTML stored in SQL. The display pages use the live `objects` and `sim_clock` tables and reload every five seconds, without changing the selected button.

Deploy the complete `fido_pages/` directory plus `fido_console.php`, `fido_page.php` and the updated `console.php` to the same Moon folder on the web server. No changes to the physics worker or existing orbital state are required. Server-side execution remains to be validated.

## Propulsion subsystem (implementation complete, runtime validation pending)

Apply `propulsion_schema.sql` once to the existing `moonland` database. The migration adds `propulsion_tanks` (one combined fuel/oxidizer mass per stage) and `propulsion_engines` (one row per physical main engine). It does not modify existing objects or reset simulation time. All seeded engines have `command_on=0`, and there are no ignition controls or planned-burn commands yet.

The existing `physics.php` calls `propulsion.php` during each one-second transactional tick. Individual active engines draw from their owner's single propellant tank; fuel changes and velocity updates commit or roll back together. Acceleration divides total thrust by the mass of all active attached vehicles and their remaining propellant. A stage with fewer engines running uses less propellant and makes less thrust. Engine commands must respect physical attachment order and staging. Full-thrust-only engines cannot be throttled; the LM descent engine supports its historical variable-thrust band and a separate full-thrust setting. Fuel depletion prevents further firing.

**Deploy `propulsion.php` before replacing `physics.php`** or the worker will fail on a missing include. The engine code checks whether the new database tables exist; until installed, it leaves the current physics unchanged.

**Assembly limitation:** The original `schema.sql` object dependencies do not describe a complete launch stack from CSM → SM → S-IVB → S-II → S-IC. The new propulsion interlocks intentionally refuse to ignite an incompletely attached stack; no live attachment relationships have been changed. Future launch/reset and staging code must set those dependencies and active flags properly. The LM requires ascent-stage root with descent stage attached for descent thrust, and descent stage detached for ascent thrust. Current surface-lock behavior also requires a later liftoff update before launch from a landed condition.

**Model approximations:** Nominal thrust and mass-flow constants are sourced from Apollo reference specifications but are not yet altitude-dependent, gimbaled, pressure-sensitive, or coupled to guidance. The combined-tank model ignores fuel/oxidizer mixture imbalance, ullage, pressure and reserve flow. Per-engine ignition counts track limited restarts where known. The engine's integer command and throttle fields are reserved for future authenticated flight programming—not exposed by a public endpoint. No RCS engines were added in this main-propulsion pass.
