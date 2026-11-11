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

### Pilot-only delta-V accumulator

Run `delta_v_schema.sql` in `moonland`, upload `delta_v.php`, `delta_v_status.php`, `instruments/delta_v.php` and updated `execute.php`. **Upload the new PHP files before replacing `execute.php`.** The migration moves the DSKY panels from the Commander pages to the CM/LM **Pilot** pages and installs the small delta-V accumulator on the Pilot pages only. Existing G-meter instruments are left in place.

The accumulator integrates the **signed forward-axis proper acceleration** reported by the existing `g_meter_samples.g_forward` instrument reading:
`delta_v_mps += g_forward * 9.80665 * 1.0`.
It is sampled inside the worker's one-second transaction immediately after `moonGMeterTick`. It does **not** add the gravitational acceleration back: the G-meter already subtracts gravity in its proper-acceleration model. It does not use raw simulation positions or orbital velocities.

The current readout measures **signed axial accumulated delta-V**, not inertial three-axis velocity change. It is appropriate as an educational forward-axis burn counter, including negative acceleration, but is not a vector guidance solution when the thrust axis changes. This counter integrates whatever axial non-gravitational acceleration the G meter detects, including any vehicle maneuvers. It is subject to the existing G-meter's sample quantization and accuracy.

RESET ZERO stores `accumulated_mps=0` with the current simulation tick under a database transaction. It does not change engines, spacecraft velocity, IMU alignment, or the G meter. The same sample cannot be integrated twice. Unpowered, missing, or stale G-meter readings cause a HOLD rather than an invented acceleration. The counter remains persistent across page reloads and stops adding while measurements are unavailable. DSKY burn-to-target-delta-V is intentionally **not connected yet**.

The new accumulator is optional: the worker checks for the new table and skips it until SQL is installed. The G-meter savepoint also prevents an optional instrument error from corrupting orbital physics. Runtime validation on GoDaddy remains pending.

## RTCC numerical rendezvous planner (FIDO advisory only)

The read-only `rtcc_rendezvous.php` library calculates **candidate two-impulse CM-to-LM intercepts**, independently of the cockpit DSKY. The existing FIDO `004 RENDEZVOUS` display now links to `rtcc_solution.php`, which runs the solver on a consistent snapshot of `objects`, prints up to five candidate maneuver plans and can return structured data with `?format=json`.

Each solution proposes a first instantaneous velocity change (inertial direction, magnitude and ignition delay) followed by a second instantaneous change to match LM velocity at intercept; the numerical shooting algorithm refines the first impulse by finite-difference sensitivity and three-variable linear solves. It uses the same Earth-fixed and moving-Moon gravity constants as the current PHP physics model, with 10-second velocity-Verlet integration and sample positions from the two spacecraft. It scans four ignition delays and five transfer durations by default, rejects Earth/Moon surface impacts, requires a position miss under 50 meters and sorts candidates by the sum of two delta-V magnitudes. The default scan covers up to three hours from the snapshot, and has a 1,200 m/s maximum magnitude for each ideal impulse. A "no solution" result applies only to the chosen scan window and safety limits.

**Deployment:** Upload `rtcc_rendezvous.php`, `rtcc_solution.php`, and the revised `fido_pages/rendezvous.php`. No SQL changes, worker/cron edits, or spacecraft state resets. For automatic syntax/testing, `tests/rtcc_rendezvous_test.php` is covered by the repository's GitHub Actions PHP test workflow.

**Important limitations:** This is an **ideal impulsive maneuver proposal**, not an executable Apollo guidance program. It assumes the LM coasts passively through both burns, neglects the finite burn duration, attitude slewing, engine readiness, propellant limits, gimbal constraints, start limits, external burns, disturbances, orbital determination error, rendezvous radar range limits, target vehicle attitude and collision keep-out geometry. It offers a nominal mathematical intercept and velocity match only; real approach and docking need additional guidance. The displayed yaw/pitch are inertial directions derived from the ideal velocity vectors and are **not** the IMU frame or directly usable DSKY orientation commands. Nothing writes to `objects`, `dsky_registers`, propulsion tanks or engine commands. A solution must be recalculated for a new tracking snapshot. Do not enable auto-burn from these ideal candidates without implementing finite-burn planning and independent verification.

## Optical star field, sextant and DSKY P30 platform alignment

**Deploy before SQL:** upload `star_alignment.php`, `star_catalog.php`, `instruments/star_field.js`, `instruments/sextant.php`, the revised `instruments/window.php` and revised `dsky_runtime.php`. Then import `star_alignment_schema.sql` into the existing `moonland` database. Do **not** reset `objects`, IMU or existing programs. The new P30 worker module is only used after its catalog and SQL migration exist. Existing IMU-driven P2–P5 and DSKY execution remain installed. Import `dsky_imu_samples.sql` too if not already installed; attitude-target programs rely on it to derive IMU rates.

All windows retain the existing 3D Earth/Moon rendering and now overlay a fixed inertial star catalog. Three special training stars, A/CORNER, B/ARROW and C/CROWN, form a recognizable right-angle pattern; faint synthetic stars and other bright identifiers provide background. The stars are infinite-direction vectors fixed in the simulation XYZ reference frame (not finite locations). **Window and sextant optics use the real spacecraft attitude**; the onboard DSKY/IMU does not get to read that truth as an attitude correction.

Only the **CM Pilot** and **LM Pilot** pages receive the sextant. Select star A, B or C and sight its sextant **horizontal X** and **vertical Y** angle. For this training optical encoder, each coordinate is two digits 00–99 with `50=0.0°`, increments of **0.5°**, and `XXYY` packs both angular measurements into one four-digit register. Example `3446` means X=-8°, Y=-2°. Standard inertial view (+X forward, +Y right, +Z up) gives `3446`, `5846`, `3470` for A, B, C respectively. The display rounds X/Y to the same quantized values decoded by P30.

**P30 / PLATFORM ALIGN:** Load DSKY program 30. Enter star A XXYY into R1, B into R2 and C into R3; execute. The physics worker fits the rotation from the **three optical line-of-sight vectors** to the fixed inertial catalog, rejects inconsistent angular separations, then updates only `imu.pitch_deg`, `imu.yaw_deg`, `imu.roll_deg`, reference fields and `imu.aligned_at_tick`. It does not query true `objects.pitch/yaw/roll` in the alignment solver, does not alter physical position/motion, and does not slew the spacecraft. IMU must be powered and updating.

**P31 / ALIGN CHECK 000:** Enter `0000` for the pitch, yaw and roll fields, then execute. The normal DSKY attitude controller uses the **new IMU estimate** to drive actual RCS pulses toward the reference origin. Crew confirms visually that the training constellation matches its expected 000 view (A=-8°/-2°, B=4°/-2°, C=-8°/10°). P31 is a genuine maneuver; check RCS/IMU readiness before executing.

**Optical limitations:** The optics and solver are simplified training models, not a historical Apollo sextant or a star-camera pointing controller. Sightings must be made at substantially the **same, stationary spacecraft attitude**; measurements taken before/after significant rotation cannot be combined without recording each sighting's epoch and the integrated turn. The current input has 0.5-degree precision and one fixed three-star set with visibility restricted to a 49° square field. Reference stars outside the sextant FOV require manually turning the spacecraft or choosing an appropriate orientation. The platform can still drift after a successful alignment. The existing development IMU instant-align shortcut remains separate from P30 and should not be used during crew training.

Regression tests for the optical solver are in `tests/star_alignment_test.php`. No live-server runtime validation has been performed yet.

### Star identification chart (visual training update)

The CM/LM optical windows and pilot sextants display only real projected star dots; **no floating A/B/C labels and no invented connecting lines**. The sextant has a separate, collapsible **REFERENCE STAR CHART / INERTIAL +X**, generated server-side from the same fixed star catalog as the live view. A/B/C identification labels appear only on that reference card and can be hidden. The pilot compares the chart with the sky, clicks each live star to place the measurement cursor, reads the packed XXYY code, and enters the three observations into P30. The sextant no longer highlights the selected star or automatically fills coordinates for it. This visual-only change does not affect IMU/physics state, DSKY programs, or the database. Deploy updated `instruments/star_field.js`, `instruments/window.php`, and `instruments/sextant.php` together. PHP includes the renderer from disk, so keeping the live `star_field.js` synchronized is necessary even though browsers no longer import it directly.
