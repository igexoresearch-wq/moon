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

The new `instruments/dsky.php` provides four selectable numeric input registers (R1–R4), a PROGRAM keypad, ENTER, EXECUTE, and five persistent execution registers. A CM and LM computer share the same interface design but have independent database state. CM programs 1–5, 30–32 and 33 are installed via their respective SQL migrations, while LM programs 30 and 31 are also available after the star-alignment migration. See the later sections for P32/P33.

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

RESET ZERO stores `accumulated_mps=0` with the current simulation tick under a database transaction. It does not change engines, spacecraft velocity, IMU alignment, or the G meter. The same sample cannot be integrated twice. Unpowered, missing, or stale G-meter readings cause a HOLD rather than an invented acceleration. The counter remains persistent across page reloads and stops adding while measurements are unavailable. The standalone accumulator is not directly wired to a DSKY target; **P32/P33 use the same G-meter measurements through a separate, scheduled burn controller** after their migration.

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

### Optical instrument authenticity rule — permanent UI requirement

**Do not put dynamically changing printed text, star counts, coordinate readouts, modern alerts, identifiers, or tracking diagnostics on the spacecraft windows, sextant glass, or other purely optical displays.** These instruments show physical scenery, reticles, fixed etched numbers/ticks, and optical marks. Technical errors may be logged to the JavaScript console, not projected as moving strings over the glass. The separate paper-style reference star chart may have printed fixed labels, as may the historical-style DSKY digital displays and FIDO computer terminal; these are not optical windows.

The sextant is now a fixed crosshair with numbered **5° major graduations**, 1° intermediate graduations and **0.5° minor ticks**, and no automatic X/Y/PACK text. The user visually estimates each star's X and Y position from the etched scales (X positive right, Y positive up), applies 50 = 0° and two counts per degree, and manually enters packed XXYY in DSKY P30. The chart continues to use the identical inertial star catalog as the real windows. This should remain true through subsequent visual/design changes.

### Live optical sky deployment safeguard

The optical sky renderer is now **self-contained** inside `instruments/window.php` and `instruments/sextant.php`. These two pages do not import or read `instruments/star_field.js` at runtime, preventing stale server copies from reintroducing A/B/C letters or constellation connector lines. The live sky draws stars only. Only the separate reference chart may label A/B/C. Deploy these two PHP files together; there is no SQL migration or physics change. `instruments/star_field.js` remains a standalone historical/shared code file but is not used by the live optical pages.

## Independent onboard state-vector navigation (initial test implementation)

Install `navigation_schema.sql` into the existing `moonland` schema. Deploy `navigation.php` **before** the updated `execute.php` (the worker includes it unconditionally). No physics-table changes or reset required. The migration creates `navigation_states` (current six-component estimated state for CM=3 and LM=6), `navigation_bodies` (onboard Earth/Moon reference states), and `navigation_telemetry` (one historical row per active spacecraft per 60 simulated seconds). Telemetry can be read in SQL; there is not yet a DSKY or FIDO data-request program. This is a separate navigation model, **not** a replacement for `objects`.

The first navigation worker tick after installation seeds the current craft X/Y/Z from simulation truth **once**, rounded to 1 metre, and VX/VY/VZ rounded to 0.01 m/s. Earth/Moon initial states are likewise copied **once** (Moon velocities rounded to 0.001 m/s). After initialization, the worker only uses stored onboard estimates and the G-meter's `acceleration_{x,y,z}_mps2` sensor columns; it never reads actual spacecraft position/velocity again. The onboard Moon model advances independently with Earth gravity. Estimated gravity is computed from stored predicted celestial states and the craft's *estimated* position, not from `g_meter_samples.gravity_*` (which was calculated using physical truth).

On each one-second physics tick, the worker first updates `g_meter_samples`, then calls `moonNavigationTick` inside a separate savepoint and updates the onboard velocity and position with integrated instrument acceleration + predicted gravity. A fresh sample with `valid=1` and matching tick is required for non-gravitational acceleration; if missing, the system predicts gravity-only motion and marks `DEGRADED SENSOR` rather than fabricating thrust data. Duplicate or skipped navigation ticks are rejected. Sensor noise/quantization can cause divergence. The initial bootstrap is a **temporary testing concession** for the ongoing in-orbit simulation, not an Apollo procedure; future launch initialization should derive position and velocity from launch-site and time. The current sensor columns are already expressed in simulation inertial XYZ, so no IMU rotation is applied: full platform-axis measurement and IMU-derived transformation are future realism work. Orbital propagation is simplified and not flight qualified.

Reset behavior: if sim clock goes backward, navigation reinitializes on the next worker tick and clears its old telemetry; a mission reset that leaves the clock unchanged should explicitly clear `navigation_states` and `navigation_bodies` to force a fresh seed. This migration does **not** modify existing simulation state and has not been runtime-validated on GoDaddy. A navigation error is logged and its own SQL writes rolled back without rolling back physical flight progression.

## Independent FIDO ground-navigation estimate (ground tracking test implementation)

Install `fido_navigation_schema.sql` in `moonland`. Deploy `fido_navigation.php` **before** the updated `execute.php`, and also deploy `fido_pages/navigation.php` and `fido_console.php`. On the FIDO console choose **005 GROUND NAV**. No reset of physical objects, fuel, IMU, or DSKY is required. On the first eligible worker tick, the ground estimate bootstraps independently for CM and LM from the current state; onboard `navigation_states` remains untouched. Worker failures in this optional module are isolated using a separate SQL savepoint.

FIDO has its own `fido_navigation_states` (current estimated X/Y/Z and VX/VY/VZ), `fido_navigation_bodies` (its independently propagated Moon ephemeris), `fido_navigation_telemetry` (minute-interval solution snapshots), and `fido_tracking_observations` (ground measurement snapshots). It propagates once per simulated second with its own gravity calculation. It **does not use onboard `navigation_states`, IMU, or G-meter readings to maintain its trajectory**, and it does not update the onboard navigation computer. Its precision is intentionally one decimal place finer than the initial onboard copy: ground positions to 0.1 m versus onboard 1 m; ground velocities to 0.001 m/s versus onboard 0.01 m/s. Ground Moon initialization is 0.1 m and 0.0001 m/s, versus onboard 1 m and 0.001 m/s. Precision is not a guarantee of actual accuracy.

**Important realism boundary:** Every 60 simulated seconds the ground sensor adapter produces a **simulated tracking solution**, copying the physical spacecraft position and velocity through an explicit rounding/measurement boundary (0.1 m and 0.001 m/s). This is a temporary TEST proxy for radio/range/Doppler orbit determination, **not** a real geometric range-and-range-rate station solver and not a historical accuracy claim. The true-state read is restricted to `moonFidoTrackingObservation()` and one-time initialization; between fixes, the ground estimate uses its own predicted state and ephemeris. This enables development of crew-versus-ground disagreements now without pretending the sensor geometry has been modeled. The FIDO display compares ground and onboard **estimates**, never labels that difference as ground-to-truth error. There is currently no ground-to-crew navigation upload.

The original FIDO displays 001–004, the rendezvous solver, flight physics, DSKY interface, and on-board navigation integrator are unchanged. `tests/fido_navigation_test.php` and the GitHub PHP lint workflow provide non-live regression checks; GoDaddy runtime and instrument accuracy remain to be validated.

## FIDO estimated-only display and rendezvous targeting

**All FIDO displays 001–005 and RTCC targeting now use `fido_navigation_states` and `fido_navigation_bodies` exclusively for trajectory and velocity calculations.** The shared read-only adapter `fido_ground.php` takes a consistent ground navigation snapshot, translates to Earth-origin inertial XYZ, and supplies the FIDO data format. It never queries `objects` or onboard `navigation_states`. The display controller `fido_page.php` no longer fetches `objects`. The five CRT pages now calculate altitude, speed, apsides, inclination, flight-path angle, and relative motion from that same FIDO estimate. Page 005 no longer shows position or navigation-system errors.

**004 RENDEZVOUS** links to `rtcc_solution.php`, which uses the *same FIDO estimate* to calculate CM-to-LM two-impulse intercept candidates with the existing Earth/Moon numerical propagator. The former impact-check PHP argument type error was corrected. The calculation screen allows **near**, **standard**, or **extended** search windows and selectable maximum impulse magnitudes, and reports candidate ignition/intercept times, both delta-V vectors and ideal pitch/yaw directions, total delta-V and numerical residual miss. No maneuver is executed, stored, scheduled, or communicated to the DSKY. These are ideal instantaneous-impulse **advisory** intercepts, not finite-thrust burns, a docking approach, or a flight-qualified solution; active burns, staging, safety corridors and hardware constraints remain outside the solver. Both FIDO navigation states must be initialized at a common tick. If the assumed inertial free-flight trajectories intersect a planetary surface, the solver refuses to propose a candidate.

**Deployment sequence:** Ensure the previously committed `fido_navigation_schema.sql` is installed and `fido_navigation.php` plus the updated `execute.php` worker is running. Upload `fido_ground.php`, the updated `fido_page.php`, `rtcc_solution.php`, `rtcc_rendezvous.php`, and all five `fido_pages/{summary,orbit,state,rendezvous,navigation}.php` pages. No new SQL migration, physics modifications, or mission reset are required by this display-and-solver change. Source-isolation tests in `tests/fido_ground_source_test.php` and the RTCC math regression suite are configured in GitHub Actions; GoDaddy runtime confirmation is still pending.

**Intentional test boundary:** FIDO's underlying independent *tracking-measurement adapter* in `fido_navigation.php` still generates a 0.1-m / 0.001-m/s observation from true `objects` once per minute. That is a simulated ground-sensor reading used to correct its estimate, **not a direct FIDO display or RTCC state lookup**. Real radio range/range-rate station geometry has not yet been implemented. The true `objects` tables remain the physical simulator's source of reality, while all ground CRT calculations are now based on its estimated tracking solution.

## Timed rendezvous execution: DSKY P32 SPS and P33 RCS

This feature is implemented in GitHub; the live GoDaddy installation and flight-dynamics validation are **not yet confirmed**.

**Deploy in this order:** Upload `dsky_rcs_translate.php`, `dsky_rendezvous.php`, `rtcc_maneuver.php`, the revised `dsky_runtime.php`, `dsky_api.php`, `instruments/dsky.php`, `rtcc_rendezvous.php`, `rtcc_solution.php`, and `execute.php`. Apply **`dsky_rendezvous_schema.sql`** in phpMyAdmin AFTER `dsky_schema.sql` and `star_alignment_schema.sql`. It adds P32/P33 and one persistent `dsky_dv_burns` table; it does **not** reset the mission clock, modify physical positions, or replace prior programs. Ensure the earlier G-meter, delta-V, RCS, propulsion, reliability and IMU schemas are installed; P32 requires the SPS-equipped service module and P33 requires SM RCS fuel, electrical power and the forward translation component. The worker includes the new DSKY module unconditionally, so deploy its PHP files before updating the worker.

**FIDO RTCC:** The read-only `rtcc_solution.php` accepts search window and delta-V constraints. It obtains FIDO's independent state estimate (not `objects`), propagates to a whole-minute planning epoch at least two minutes ahead, calculates two-impulse rendezvous candidates, and returns a four-register entry card **for each option and both burns**, with exact MET ignition (HHH:MM:SS), rounded DSKY MET HHMM, positive delta-V, and wrapped pitch/yaw codes. For small delta-V (up to 10 m/s), it recommends the existing SM RCS translation subsystem if estimated fuel and 40-minute burn duration permit; otherwise the SPS. It also shows estimated two-maneuver fuel usage and remaining fuel, based on assumed CM+SM dry mass 11,660 kg. These are nominal, simplified fuel estimates, not confirmed engine feasibility with an LM or another stage still attached.

**DSKY operation:** Choose **P32 (SPS)** or **P33 (SM RCS)** according to RTCC, and enter:

- R1 = **HHMM**; `2012` means mission hour 120, minute 12 when current elapsed time is in hours 100–199. The next occurrence in a repeating 100-hour window is selected, strictly after the current mission tick. This is absolute mission time, **not** a delay. Resolution is **one minute**; seconds are always 00.
- R2 = **positive delta-V × 10**; `1998` means 199.8 m/s. Four digits support 0.1 through 999.9 m/s. Delta-V is intentionally truncated to tenths in the RTCC entry card, not rounded upward.
- R3 = **inertial pitch × 10**, degrees wrapped to 000.0–359.9.
- R4 = **inertial yaw × 10**, same encoding.

After EXECUTE, the program uses only the simulated **IMU** for attitude targeting and the existing RCS attitude jets to align before TIG. It waits for the mission clock, then opens the existing SPS command (P32) or applies a forward SM RCS translation impulse each tick (P33). The physical velocity changes under the existing physics engine; the G-meter's signed XYZ proper-acceleration samples are projected onto the intended engine direction, accumulated, and used for shutoff when the requested delta-V is reached or exceeded. There is no magical direct velocity or attitude setter in the DSKY program. Program records contain the achieved delta-V and status. Fail-safe checks include missing instrument samples, attitude not ready at TIG, missed TIG, unavailable propellant/engine/RCS, competing DSKY commands, and timeout. CLEAR aborts an active maneuver. Only one timed delta-V maneuver may be queued or running at once per craft. The second maneuver is entered **after** the first completes; preferably recalculate the RTCC solution from updated ground tracking before using it.

**Limitations:** The numerical RTCC planner assumes **instantaneous impulses** at scheduled MET. Real one-second SPS thrust steps and slower RCS burns cause finite-burn gravity losses and possible intercept errors. Especially for the SPS, a single one-second impulse can exceed the four-digit target resolution; the program reports actual observed delta-V rather than silently claiming an exact cutoff. RCS uses the existing educational SM thruster with one-second continuous forward firing, measured using the G-meter; it is a training simplification, not a historical jet-by-jet CSM translation controller. No automatic docking, finite-burn iterative retargeting, attitude/roll certification, zero-visibility observation, or real communication/flight-data upload has been claimed. A displayed RTCC pitch/yaw direction is inertial; correct physical pointing depends on a properly aligned IMU. The planner still assumes a coasting LM, and no intermediate stage is attached during SPS execution. Live runtime and trajectory accuracy have not yet been validated.

## Shared Mission Elapsed Time (MET) clock

One authoritative mission-time source: `sim_clock.tick` (elapsed simulated seconds since mission reset). The read-only `met_status.php` endpoint converts the integer tick count to **HHH:MM:SS**, retaining hours beyond 99, using `met_clock.php`. It cannot advance from wall time; paused/stalled physics means a stationary MET indication. The reusable, fixed-caption, 1960s-style `instruments/met_clock.php` polls this endpoint every second and renders the same MET on all surfaces, including the compact clock above every FIDO page. There are no independent cockpit clocks to drift out of sync; normal query and display latency still applies.

**Installation:** Deploy `met_clock.php`, `met_status.php`, `instruments/met_clock.php`, and revised `fido_console.php`. Apply `met_clock_schema.sql` once to add the instrument to CM Commander/Pilot, LM Commander/Pilot, and Flight Director. The clock is positioned at x=1510,y=65 on spacecraft cockpits and x=20,y=65 on Flight; use the existing instrument editor to reposition it. FIDO displays the clock in the header automatically, with no SQL insert. No reset or physics/worker code changes are needed. The clock works with both SPS P32 and RCS P33 time-of-ignition input cards because each uses the same sim_clock mission epoch. Automated tests: `tests/met_clock_test.php`.

**Limitations:** MET counts *simulated time*, not guaranteed real elapsed wall time. Its initial zero is the simulation reset epoch, not an independently modeled historical Apollo launch ignition event. The clock is an indication only; it cannot pause, reset, or alter the simulation.

### SPS overburn protection (November 2026)

**Incident:** A user observed approximately 900 m/s on the CM's cumulative delta-V gauge after requesting ~99 m/s. The accumulator is persistent across burns and does **not** reset when P32 starts; therefore its reading alone cannot establish the per-burn delivered delta-V. The instrument and status API now display the DSKY's own `achieved_mps / target_mps` separately.

**Critical safety change:** `dsky_dv_safety.php` computes the current propulsion attachment-root mass using the same dry masses and remaining tank fuel as the physics engine, and reads the SPS thrust. `dsky_rendezvous.php` independently checks elapsed ignition ticks against expected SPS impulse **before physics**, and checks the estimated impulse and plausibility of the G-meter acceleration **after physics**. It commands engine 401 off on overrun, missing model, or implausible sensor data. This is a conservative protective bound, not a precise finite-burn guidance solution. The 1-second simulation step and changing propellant mass still limit accuracy.

**Deploy together:** `dsky_dv_safety.php` (new), `dsky_rendezvous.php` (revised), `delta_v_status.php` and `instruments/delta_v.php` (revised). The new safety module is a required include: deploy it before replacing the controller. Existing `execute.php` already calls the post-sensor cutoff. There is **no SQL migration**. Run `php tests/dsky_rendezvous_test.php` as a source/math regression. Do not conduct another ignition test until these files are deployed and the previous active burn is verified stopped.

**Emergency stop if the DSKY CLEAR command cannot stop an active SPS:**
```sql
START TRANSACTION;
UPDATE propulsion_engines SET command_on=0, firing=0 WHERE id=401;
UPDATE dsky_registers SET state='aborted', message='EMERGENCY SPS CUTOFF'
 WHERE craft_id=3 AND program_no=32 AND state IN ('queued','running');
UPDATE dsky_dv_burns SET status='ABORTED'
 WHERE craft_id=3 AND register_no IN (
  SELECT register_no FROM dsky_registers WHERE craft_id=3 AND program_no=32
 );
COMMIT;
```
This directly changes engine commands and register state but never changes spacecraft position, velocity or mission clock. Coordinate database writes with the tick worker's transaction; confirm the engine is off afterward. Preserve incident data before clearing registers when possible.
