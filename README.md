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

## Simplified mission computer / DSKY

The new `instruments/dsky.php` provides four selectable numeric input registers (R1–R4), a PROGRAM keypad, ENTER, EXECUTE, and five persistent execution registers. A CM and LM computer share the same interface design but have independent database state. Currently only CM programs 1–5 are installed. LM programs can be added later by SQL.

**Deploy and initialize in this order:**
1. Ensure the existing `rcs_schema.sql` and `propulsion_schema.sql` are already installed, with their supporting PHP worker files deployed. The new system does not recreate them.
2. Upload `dsky_api.php`, `dsky_rcs.php`, `dsky_runtime.php`, and `instruments/dsky.php`.
3. Import `dsky_schema.sql` into the existing `moonland` database in phpMyAdmin. This creates program definitions, entry drafts, five registers per vehicle, the omitted CM yaw-right RCS component and console instrument placement. No simulation tables are reset.
4. Deploy the updated `execute.php`. Its one-second transaction runs DSKY commands before orbital physics and verifies actual propulsion afterward.

**CM program catalog:**
- Program 1 — timed SPS burn. R1 is integer seconds, e.g. `0010` = ten seconds. The engine burns only if the required SM attachment and tank/engine conditions permit.
- Program 2 — target pitch. R1 has one implied decimal: `1532` = 153.2 degrees.
- Program 3 — target yaw, same encoding.
- Program 4 — target roll, same encoding.
- Program 5 — target pitch, yaw and roll; R1, R2 and R3 respectively. R4 is unused and cannot be selected.

Press PROGRAM, digits, ENTER to load a program; select an R1–R4 button, enter digits, ENTER for each required number; then EXECUTE queues it. The execution registers persist across page reloads and worker cycles. Queued tasks wait if another task is already using the same class of actuator. Completed slots may be reused; failed tasks remain until manually cleared. CLEAR terminates a live timed burn or interrupts an attitude target. During a target maneuver the control computer applies actual RCS pulses that consume their fuel and oxidizer rather than setting attitude directly; it attempts to brake angular velocity before declaring completion. The RCS must be powered and available. A power/fuel hold remains occupied until the condition clears or the user aborts it.

**SQL-extensible programs:** `dsky_programs` stores program number, labels for up to four nullable entry windows, min/max limits, and a JSON instruction describing an allowed operation. It does not contain arbitrary executable PHP, HTML or JavaScript. This allows new programs in the existing two families (`burn_seconds` and `attitude_target`) through SQL alone, without changing DSKY code.

Example: a second pitch program without any PHP edits:
```sql
INSERT INTO dsky_programs
(craft_id,program_no,title,operation,config_json,field1_label,field1_min,field1_max)
VALUES
(3,20,'PITCH HOLD','attitude_target',
 JSON_OBJECT('axis','pitch','scale',10),'PITCH DEG',0,3599);
```
For group engines the burn configuration can instead specify `engine_ids` as a JSON array. Optional `throttle_field` and `throttle_scale` keys allow a future SQL-defined program to use a second input for approved engine throttling, while keeping hardware restrictions. The executable operation set is deliberately restricted: functions such as new guidance laws, actual docking, separation, trajectory targeting and data retrieval will need implemented physics/read-only handlers before their SQL rows can be enabled.

**Known limitations before live use:** The RCS controller uses the project's existing simplified torque/inertia model, not a full rotational rigid-body integration or historic spacecraft autopilot. A powered RCS system's existing component/electrical behavior is reused; no new per-pulse electrical loads have been claimed. The SPS engine still lacks a detailed electrically actuated valve and ignition circuit model. Multi-stage launch dependencies and any remaining staging logic must be completed before launch programs can work. SQL can select installed actions but cannot safely invent missing actuators or physics. PHP lint workflow was added, but neither GoDaddy runtime tests nor mission-profile validation have been completed.

### DSKY attitude source: IMU only

The CM and LM DSKY attitude-target programs now use `imu.pitch_deg`, `imu.yaw_deg` and `imu.roll_deg`, never the real angle columns in `objects`. The controller estimates measured angular rate by comparing consecutive IMU readings and uses those measurements for steering, braking and completion. Physical RCS impulses still update spacecraft motion through `objects` and consume existing RCS fuel/oxidizer. Therefore IMU misalignment or drift can lead the DSKY to stop at a physically different attitude, intentionally.

**Required one-time migration:** Import `dsky_imu_samples.sql` after `dsky_schema.sql`, then deploy the updated `dsky_runtime.php`. This adds only the prior IMU observation table; it does not alter `objects`, `imu`, ongoing programs, or any current sensor values.

If the IMU record is absent, stale, or the history table has not been installed, the program remains running with `WAITING FOR FRESH IMU` instead of consulting physical truth. The first usable observation is followed by `ACQUIRING IMU RATE` until a second distinct IMU sample is available. A disabled IMU stops supplying fresh measurements; the attitude program holds without using the underlying physical angles. The IMU itself still updates once per physics tick from its existing sampled/quantized motion model.

This is a simplified Euler-axis autopilot (not a flight-qualified rigid-body attitude controller); turning and settling require runtime validation, especially near angle wraparound.
