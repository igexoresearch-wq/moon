# MOON PROGRAM — RESET SPECIFICATION & STATE INVENTORY
**Status:** planning / living document. **A RESET BUTTON DOES NOT EXIST YET.**
**Purpose:** Keep a reliable inventory of everything future reset-to-launch logic must restore, even while test flights change positions, attitudes, alarms, and component states. Update this file when any new table, subsystem, or instrument is introduced.

## 1. Ground rules
1. **Reset must restore a deliberately chosen launch snapshot, NOT the database values present when the reset button was clicked.** Launch starting conditions are **TBD**. Current seed data is only a development/test starting point.
2. A reset affects **simulation runtime state**, not the definitions of instruments, UI layouts, component catalog, or database schema. Never DROP and re-import the database as an operational reset.
3. Use an explicit, versioned, reviewed baseline/snapshot for mutable state. New tables and new runtime columns must be added to this inventory and the future reset routine.
4. Only an authorized administrator can reset. Confirm the action, stop/pause the simulation worker, acquire its lock, perform an atomic database transaction, then resume. Prevent an active tick or command from racing a reset.
5. Reset must leave a clean alarm state. The master alarm acknowledging a failure **is not** a reset and must not repair any component.
6. Crew and ground displays must **never expose hidden permanent-vs-temporary failure status**. Reset functionality must not disclose the failure cause, probability, or internal state.
7. Do not silently overwrite the final mission's chosen initial values with `schema.sql` test seeds.
8. Back up/export current runtime data before implementing or first using a destructive reset.

## 2. Tables as currently implemented (repository audit, 2026-11-07)
| Table | Role | Future reset behavior |
| --- | --- | --- |
| `objects` | Earth, Moon, spacecraft stages, masses, positions, XYZ velocities, pitch/yaw/roll, rates, attachments, active flags, landing coordinates | **RESTORE launch snapshot** for every launch object, including all mutable fields. Keep stable IDs and definitions where appropriate. |
| `sim_clock` | Simulation tick and last update | **RESET** tick to launch baseline (typically 0), clear/set updated_at as decided. |
| `reliability_random` | One 1–100000 roll, simulation tick, generated_at | **RESET** to a neutral roll / baseline and tick; do not preserve a stale random draw. |
| `reliability_components` | Hardware catalog, spacecraft_id, hidden working/temporarily offline/permanently failed state, restore_at, last command, master flag, alarm_name | **RESET runtime condition/timestamps only**: state=1, state_since/restore_at/last_command_at to baseline. **PRESERVE** component_key, name, spacecraft_id, master, alarm_name, IDs and any later static metadata. |
| `reliability_failures` | Historical failure records, unacknowledged alarms, recovery times, actual hidden failure classification | **CLEAR active mission failures** on reset. Optionally archive prior mission history elsewhere; do not leave unacknowledged alarms. |
| `imu` | CM and LM estimated attitudes, independent reference axes, alignment timestamps and last processed tick | **RESTORE** estimated attitude and initial reference from final launch snapshot. Preserve installation and instrument layout. |
| `rcs` | CM/SM/LM RCS fuel and oxidizer masses, thrust configuration, model inertia, system enabled state | **RESTORE** launch fuel/oxidizer and enabled state. **PRESERVE** calibrated hardware specifications, craft ownership and IDs. Current RCS seed values are placeholders. |
| `console_instruments` | Instruments assigned to stations, pos_x/pos_y | **PRESERVE**. Editing layout must survive reset. |
| `instrument_sources` | Pasted HTML instrument code and dimensions | **PRESERVE**. Reset must never delete custom instrument definitions. |

**Installed DB caution:** `schema.sql`, `reliability_schema.sql`, and `reliability_alarm_upgrade.sql` are installation/migration scripts; **do not run them as the reset**. The upgrade SQL is only for databases created before the two alarm-display columns existed.

## 3. Current development seed — NOT FINAL LAUNCH SETUP
Units: meters, m/s, kg; angles in degrees. Earth fixed at (0,0,0) with **no Earth spin**. Lighting direction fixed in the optical renderer.
- **1 Earth**: radius 6,371,000; center (0,0,0); active=1.
- **2 Moon**: radius 1,737,400; initially (384,400,000,0,0), velocity (0,1018.3,0); active=1.
- **3 CM**: radius 3.9; initially (7,371,000,0,0), velocity (0,7354.3,0); active=1.
- **4 SM**: radius 7.5; initially (7,370,997,0,0), attached/dependent on CM (3), offset 3m; active=1.
- **5 Launch Escape Tower**: currently inactive, dependent on CM (3) in seed.
- **6 LM Ascent Stage**: currently inactive, dependent on LM Descent (7) in seed.
- **7 LM Descent Stage**: currently inactive, dependent on Saturn V third stage (8) in seed.
- **8 Saturn V Third Stage**: currently inactive, dependent on second stage (9) in seed.
- **9 Saturn V Second Stage**: currently inactive, dependent on first stage (10) in seed.
- **10 Saturn V First Stage**: currently inactive; top-level of launch stack.
- **Current `sim_clock` seed**: tick 0.
- **Current reliability initial catalog**: `cm_rcs_yaw_right`, spacecraft_id=3. Others will be added later.

**Not decided:** precise pad/site, Earth coordinate convention/rotation decision, Saturn V orientation, stack assembly geometry, velocities on pad, initial Moon phase/orbit, initial fuels/consumables, crew readiness and subsystem power states. These will be deliberately specified at the end of development.

## 4. Systems and reset data inventory — expand as built
### Physics and trajectory
- [ ] XYZ positions of all objects; translational velocity, rates, attitudes.
- [ ] Attached/detached dependencies, offsets, active flags, surface contacts/landing offsets.
- [ ] Mission tick/time, stage sequencing, burns/impulses, docking state, rendezvous state (when implemented).
- [ ] Preserve Earth/Moon natural-body definitions; restore chosen launch positions/velocities. **Do not automatically enable Earth's axial rotation.**

### RCS — implemented initial pulse controller (2026-11-07)
- [ ] Restore RCS fuel_kg, oxidizer_kg and enabled for CM, SM and LM at chosen final launch baseline.
- [ ] Preserve RCS fuel_capacity_kg, oxidizer_capacity_kg, thrust_n, isp_s, torque_arm_m, inertia_kg_m2, pulse_seconds and craft_id once validated. Capacities define analog E/F gauge full-scale, NOT mutable propellant quantities.
- [ ] Reset all RCS direction reliability records with other component failure states.
- [ ] Keep `instruments/rcs.php` positions and `console_instruments` layout during reset.
- [ ] Current controls use one pulse per click, not rate-hold or auto-stop; final design may add commands/counters/timers requiring their own reset treatment.
- [ ] Future RCS/vehicle systems must be scanned for any addition of runtime columns or tables.

### Propulsion and spacecraft hardware (future)
- [ ] Engine armed/on/off, throttle, ignition/start attempts, thrust state.
- [ ] RCS thruster commands and actual thruster availability.
- [ ] Fuel/oxidizer levels and consumables, stage-specific reserves.
- [ ] Battery charge, fuel-cell availability, power-bus switches.
- [ ] Environmental control, communications, guidance, radar, landing gear.
- [ ] Mission-specific failures and warnings; any future cooldowns/fault timers.

### Reliability and crew warnings (currently implemented)
- [ ] Set component **state=1** at reset regardless of whether its previous failure was temporary or permanent.
- [ ] Clear runtime restore_at, state_since and last_command_at.
- [ ] Delete/archive mission `reliability_failures` rows and their acknowledgment status.
- [ ] Reset `reliability_random` roll and `sim_tick` coherently.
- [ ] Preserve `master` flag and `alarm_name` for cockpit light arrangement.
- [ ] CM warning scope IDs **3 + 4**; LM warning scope IDs **6 + 7**. Their master alarms are independent.
- [ ] Pressing MASTER only acknowledges alarms; it **does not** restore failed hardware.
- [ ] Never send hidden `state`, `failure_type`, `restore_at` or permanence reason to crew UI.

### Layout and presentation (should survive reset)
- [ ] Preserve six station identities: `cmc`, `cmp`, `lmc`, `lmp`, `fido`, `flight`.
- [ ] Preserve `console_instruments` positions, instrument file selections, and `instrument_sources`.
- [ ] The CM/LM Commander shared optical instrument currently resides in `instruments/window.php` (400 x 400, sloped upper-left clip, 45° field, right-side tick marks); 3D work in `instruments/window3d.js`.
- [ ] Shared master/caution panel is currently embedded in `window.php`; **future instruments should be separate, positionable instrument modules**. Existing implementation can be refactored later.

### Mission Control and communications (future)
- [ ] Log/reset voice/comms queues, messages, telemetry caches, CAPCOM routing, FIDO calculation scratch states, crew logs **only after deciding what belongs to an individual mission**.
- [ ] Preserve software, instrument definitions, reusable calculation methods, and training reference materials.

## 5. Reset implementation blueprint (DO NOT EXECUTE YET)
1. Decide and review **LAUNCH_BASELINE_V1**: complete object states and initial values for every other mutable subsystem. Store it in a versioned, protected baseline file/table separate from live runtime data.
2. Inventory every actual database table in deployed `moonland`; compare to the list above. Newly introduced runtime tables must register a reset strategy.
3. Add admin-only Reset control, explicit confirmation and **dry-run preview** of affected tables and row counts.
4. Acquire the same MySQL advisory lock currently used by `execute.php`: `moon_v2_execute`. Stop further scheduled ticks/commands from modifying mission state. Use transaction and consistent row locking.
5. Within one transaction: restore `objects` fields from baseline (and clear extra mission runtime rows according to policy), set `sim_clock`, clear mission failure records, restore component conditions, reset the RNG row, restore future subsystem states. **Never truncate definition/configuration tables.**
6. Commit or rollback as a unit; record reset timestamp/baseline version and verify physics state (no object inside a planet, attached objects aligned, consistent stage states).
7. Notify clients to refresh telemetry; do not rely on browser refresh to perform the reset.
8. Test on a copy of the database with scrambled positions, failures, layout changes, and additional hardware records; confirm layout and definitions survive and all mission state is restored.

## 6. Integration checklist for EVERY new component or feature
When adding code, explicitly ask:
- Does it add a **new table or runtime column**? Add reset treatment to section 2/4.
- Is this **configuration** (preserve) or **mission state** (restore/clear)?
- Which spacecraft `objects.id` owns it? Which warning panel (CM IDs 3/4, LM IDs 6/7)?
- Does it need an initial launch value? Add that to the future baseline inventory.
- Does it have hidden fault status or timestamp? Reset it and keep it out of crew-facing outputs.
- Does it need a separate movable instrument? Create a **new file + `console_instruments` entry**, not extra markup appended to the optical viewport.
- Has the reset specification been updated in the SAME change?

## 7. Current limits / open decisions
- There is **no production reset routine or reset button** now.
- Final launch-pad conditions and detailed hardware state are **TBD**, not supplied by the current orbit test seeds.
- Historical NASA reliability probabilities are **not** implied by the currently user-selected random bands.
- Failure recovery currently uses database wall-clock DATETIME; decide whether mission reset and sim-time pauses require simulated-time-based recovery.
- `reliability_command.php` is a preflight endpoint; hardware controls must not apply thrust unless its result allows the action.
- The current worker uses 60 1-second physics ticks per minute and requires the reliability tables after the reliability extension is deployed.
- Schema/seed consistency must be checked when the full final launch baseline is designed; do not use installer scripts as a substitute for reset.

**Maintenance instruction:** This is the canonical tracking document for eventual **reset to launch**. Update it during development; do not invent a launch snapshot or implement a destructive reset before those details are agreed.

### IMU and eight-ball (2026-11-07)
- [ ] Restore `imu` rows for CM (object 3) and LM ascent (object 6) to deliberately specified initial orientation, alignment and `last_tick`.
- [ ] Never replace actual `objects` orientation or rates with values from IMU.
- [ ] The current IMU one-second estimator rounds each angular rate to **five decimal places** and integrates the result; the display rounds angles to tenths.
- [ ] Current component-angle integration is an approximate three-axis model, not a full rigid-body gyro/gimbal model. It may produce error at coupled rotations; true Apollo drift/noise not yet simulated.
- [ ] `imu_align.php` is a **development-only shortcut** to copy current true orientation into the estimate, not historical star alignment. Future optics procedure must replace it.
- [ ] Preserve movable `eight_ball.php` layout in CM and LM Commander consoles.

## Electrical power and failure states (2026-11-08)
- [ ] On launch reset, restore component state to approved baseline (usually 1 ON), while preserving component catalog, `amps` and `failure_rule`.
- [ ] `reliability_components.state`: 1 operational; 2 temporary failure; 3 permanent failure; 4 intentionally switched OFF.
- [ ] RCS CM/LM cluster OFF switches operational direction components from 1 to 4, ON changes only 4 to 1; failures 2 and 3 remain unchanged.
- [ ] IMU CM/LM OFF stops measured attitude integration without touching actual `objects` attitude; ON resumes estimates.
- [ ] `amps`: RCS cluster 0.100 A, IMU 2.000 A, per-direction RCS controls 0 A to prevent double-counted continuous load. Actual bus consumption integration is future work.
- [ ] `failure_rule`: configurable per component; current RCS legacy command pattern vs IMU less-than-100 trailing 3/4 per-tick pattern. Do not apply RCS probabilities automatically to future equipment.
- [ ] Off state (4) is not an alarm/failure and must not turn the master caution yellow.


## Service Module fuel cells (2026-11-08)
- [ ] Restore `sm_fuel_cell_1`, `sm_fuel_cell_2`, `sm_fuel_cell_3` component states from final launch baseline (currently seeded 1 ON), and clear their temporary timers and mission fault records.
- [ ] Preserve their static `amps=-50.000` generation ratings; energized generator contributes −50 A to the future signed electrical accounting convention. A failed or off generator outputs 0 A.
- [ ] Preserve each cell's independent ON/OFF selector and CM Commander `fuel_cells.php` instrument layout.
- [ ] Every tick compare the shared reliability roll with the fuel cell's own rules: cell 1 temporary 10–20, cell 2 temporary 21–40, cell 3 temporary 41–50, and permanent 501, 502, 503 respectively. The two overlapping range boundaries 20 and 40 are assigned to the preceding cell to ensure one outcome per roll. This choice may be revised with the user.
- [ ] When SM (object 4) is no longer active and dependent on CM (object 3), permanently fail all fuel cells not intentionally switched OFF (state 4). Preserve cells intentionally off. Restoring physical mating does NOT fix permanent faults.
- [ ] Future electrical bus must aggregate only actual operating outputs; do not subtract three nameplate ratings without checking component states.


## CM batteries and electrical bus (2026-11-08)
- [ ] Restore both `batteries` rows for craft 3 with 40 Ah capacity; reset `charge_ah` and `enabled` to final launch baseline, preserve battery identity, number and capacity.
- [ ] Reset `reliability_components.auto_shed` to zero and restore affected component states from baseline. Do not mistake intentional automatic shed (state 4) for a permanent fault.
- [ ] Reset `electrical_alarms` flags `active`, `acknowledged`, and `updated_tick` and preserve alarm labels.
- [ ] Preserve `instruments/batteries.php` CM Commander positioning.
- [ ] Each tick signed current across ON components of craft 3 and, when attached, craft 4: positive = demand, negative = generation. Apply Ah change current / 3600; distribute across ON batteries, limit between 0 and rated capacity.
- [ ] Alert CM BUS UNDERVOLT if CM+SM mated and net current positive. Alert LOAD SHED when demand cannot be supplied and automatic load shedding occurs; restore power to shed equipment manually after resolving deficit.
- [ ] The simple power budget currently ignores bus voltages, distribution switches, discharge-rate limits, thermal behavior and priority circuits; replace with physically accurate electrical modeling as data becomes available.
