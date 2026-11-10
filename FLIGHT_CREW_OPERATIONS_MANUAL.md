# APOLLO FLIGHT CREW OPERATIONS HANDBOOK
## MOON PROGRAM — SIMULATED SPACECRAFT
**FLIGHT CREW COPY** · **REVISION 0 — 07 NOV 2026**  
**Development handbook; historically inspired, not an original NASA flight manual.**  
Prepared for later typesetting as a black-and-white, mid-1960s-style, paginated PDF.

---

### CHANGE RECORD
| Revision | Date | Subject |
|:--|:--|:--|
| 0 | 07 NOV 2026 | Initial manual; complete initial RCS operating chapter |

### TABLE OF CONTENTS
1. Mission simulator organization — planned
2. Spacecraft and staging — planned
3. Command/Service Module flight stations — planned
4. Lunar Module flight stations — planned
5. **REACTION CONTROL SYSTEM (RCS)** — present chapter
6. Guidance and navigation — planned
7. Electrical power — planned
8. Environmental control — planned
9. Propulsion systems — planned
10. Communications and Mission Control — planned
11. Caution, warning, and fault isolation — planned
12. Normal procedures and checklists — evolving
13. Emergency procedures — planned
Appendix A. Simulator-specific limitations and differences from Apollo — present
Appendix B. Engineering tables — evolving
Appendix C. Revision history / configuration control — evolving

---

# SECTION 5 — REACTION CONTROL SYSTEM
**CREW OPERATING INSTRUCTIONS**  
**Applicable stations:** Command Module Commander; Lunar Module Commander.

## 5.1 Purpose and operating principle
The Reaction Control System produces small, directed impulses to control spacecraft **orientation** and **translation** in free flight. Unlike aircraft maneuvering, a spacecraft does not need to pitch or yaw first in order to move sideways. RCS thrusters can produce translation while holding the existing attitude by balancing opposing torques. Likewise, a matched rotational firing can change attitude without substantial net translation.

The controller is divided into:
- ATTITUDE — PITCH and YAW: change rotational rates about spacecraft axes.
- ATTITUDE — ROLL: rotate about the spacecraft forward axis.
- TRANSLATION — FORWARD/AFT, LEFT/RIGHT, UP/DOWN: produce a velocity change along the commanded local axis without commanding an attitude change.
- FUEL and OXIDIZER quantity: two analog pointer gauges indicating remaining propellant fraction relative to each system's full-scale mass capacity (kg stored internally). No continuously changing digital quantity labels are displayed.

The present simulator implements **individual pulses per press**, not continuous holding or automatic attitude stabilization. This is an important interim limitation: the craft may **continue rotating after a pulse** until an opposite pulse counters its angular rate. Releasing the button presently does NOT produce a stopping pulse.

## 5.2 Spacecraft coordinate conventions
This simulator currently uses:
- Forward (spacecraft +X at pitch=0, yaw=0): command FWD.
- Aft (spacecraft -X): command AFT.
- Right: the spacecraft's positive local-right direction.
- Left: the negative local-right direction.
- Up: the positive local-up direction.
- Down: the negative local-up direction.
- PITCH +/− and YAW +/−: changes in degrees/second relative to the program's existing pitch/yaw convention.
- ROLL +/−: changes to the local roll rate.

**CAUTION:** The current simulation attitude representation is Euler pitch/yaw/roll, not a fully integrated quaternion rigid-body model. Large coordinated maneuvers and long-duration rotations are approximations. A subsequent revision should implement rigid-body angular momentum and per-jet torque vectors.

## 5.3 Control head — arrangement
```text
                  REACTION CONTROL SYSTEM

                    ATTITUDE — PITCH / YAW

                           [PITCH +]
                  [YAW −]             [YAW +]
                           [PITCH −]

                      ATTITUDE — ROLL
                      [ROLL −] [ROLL +]

             TRANSLATION — FORWARD / AFT / L/R
                            [FWD]
                   [LEFT]             [RIGHT]
                            [AFT]

                     TRANSLATION — U/D
                        [UP]   [DOWN]

                (FUEL POINTER)  (OX POINTER)
```
A short command response is indicated below the quantities:
- PULSE FIRED: the server confirmed a commanded pulse.
- NO RESPONSE: no pulse confirmed. It does **not** identify whether the cause is temporary, permanent, electrical, or depletion.
- CONTROL UNAVAILABLE: telemetry/server communication unavailable; **do not infer hardware condition**.

The control head is a separate positionable instrument file, `instruments/rcs.php`, on both Commander consoles. Moving or rearranging it in the layout editor does not alter the physics.

## 5.4 Attitude control procedure
**PITCH, YAW or ROLL — CHANGE ATTITUDE**
1. Verify appropriate spacecraft control configuration and sufficient propellant indicated.
2. Select desired rotational direction using a single press.
3. Observe change in measured rate and attitude on attitude instruments (when installed). Do not infer exact rotation from a control-button response alone.
4. For a small rotation, apply a brief pulse, allow coast, and counter-pulse in the opposite direction as required.
5. Do not assume rate will automatically return to zero upon release. There is currently no rate-hold/autostop controller.
6. If no response is observed, check indications, try an approved alternate control mode/jet path if available, and inform CAPCOM if mission-critical. Do not assume or display hidden failure duration.

**CAUTION — MULTIPLE PULSES:** In space, velocity and angular momentum persist. Repeated presses can accumulate substantial drift. A long coast with slight residual rate can lead to significant attitude error.

## 5.5 Translation control procedure
**TRANSLATION — WITHOUT PITCH OR YAW**
1. Hold existing attitude. Do not steer the craft as an aircraft.
2. Use FWD/AFT for movement along the spacecraft's longitudinal axis.
3. Use LEFT/RIGHT for lateral movement, and UP/DOWN for vertical movement in the spacecraft frame.
4. Each press commands a short thrust impulse and changes **velocity**, not absolute position directly. The craft will continue coasting with that added velocity.
5. Use the opposite translation direction to reduce unwanted drift when necessary.
6. Translation does not intentionally change pitch, yaw or roll rates in the current simplified implementation.

The direction of a translation pulse depends on *current attitude*, not an absolute Earth-centered XYZ direction. A spacecraft rolled by 90 degrees has a differently oriented local UP/RIGHT direction.

**RENDEZVOUS WARNING:** Translation alone does not guarantee a stable rendezvous. Orbital mechanics, relative velocity, target motion, and gravity still govern the encounter. Small translation inputs must be planned using FIDO and onboard navigation.

## 5.6 RCS systems and propellants
There are three modeled RCS propellant systems:
- Command Module RCS (CM, ID 3), intended for CM-only control after separation.
- Service Module RCS (SM, ID 4), nominal propulsion source for the attached Command/Service Module.
- Lunar Module RCS (LM ascent stage, ID 6), used for LM attitude and translation.

The current CM Commander control head consumes **SM RCS** propellant, and the LM Commander control head consumes **LM RCS** propellant.

**IMPORTANT ENGINEERING NOTE:** This is an interim CSM system selection. Before implementing CM/SM separation, the controller must select CM RCS when the SM is no longer attached, and use correct thruster body geometry for the combined spacecraft.

Two propellant quantities are represented in the database and indicated by analog E-to-F pointer dials (no numerical kg text on the cockpit control):
- FUEL (kg)
- OXIDIZER (kg)

Thruster operation requires both liquids. Each successful pulse deducts from each quantity. No propellant is consumed if the command is refused or fails. The current modeled fuel-to-oxidizer split is 40:60 by mass; it is a configurable simulation assumption, not a certified Apollo mixture ratio.

The current seeded capacities, thrust levels, specific impulses, torque arms and inertias are **development placeholders**, NOT historically verified loading values. Replace them with component-accurate references as the model matures. Quantities must remain nonnegative.

The dial needles point toward **E** when quantity is depleted and toward **F** when full. Each needle is computed from remaining mass divided by its configured full-scale capacity; these capacities remain constant during flight. This indicator measures quantity, not a direct flow-rate or pressure reading.\n\n### Engineering pulse model
For commanded pulse duration `t`, thrust `F`, specific impulse `Isp`, gravity constant `g0`:

- Total expelled propellant (kg): `m = F × t / (Isp × 9.80665)`
- Translational impulse (N·s): `J = F × t`
- Approximated linear velocity increment: `Δv = J / vehicle mass`
- Approximated angular-rate increment (rad/s): `Δω = (F × torque_arm × t) / moment_of_inertia`

These formulas are engineering approximations that omit actual thruster layout, coupled six-axis forces, jet-pair timing and changing inertia during a single pulse. They are suitable for early integration testing, **not validation against Apollo flight data**.

## 5.7 Reliability, faults, caution and warning
Each of the 12 possible commanded axes/directions has a separate reliability record. A command is screened by the preexisting reliability engine before applying a velocity or attitude change.

A failed activation may simply produce NO RESPONSE. A failure may also lead to an ongoing component malfunction. The computer internally distinguishes operating conditions; **neither crew nor Mission Control is shown whether a malfunction is temporary or permanent**.

Crew indications shall show only observable behavior, associated alarms, and quantities. The MASTER caution light may be acknowledged, but acknowledgment does not restore a failed RCS component.

**CAUTION — SIMULATOR RELIABILITY:** Probability bands in this project are deliberately user-selected, not historical NASA failure rates.

## 5.8 Operational modes planned, not yet available
The interface currently performs ONE PULSE PER PRESS.
- **RATE COMMAND (planned):** press to achieve desired angular rate; maintain by pulse modulation; release to command zero angular rate using opposite jets.
- **PULSE (current):** a press commands one limited-duration thruster activation.
- **DIRECT (planned):** holding a control commands real jet firing; releasing ends firing, but does not cancel residual momentum.

No other mode should be documented as operational until the associated software exists and is validated.

## 5.9 Preflight/operational checklist — draft
- [ ] Verify assigned CM/SM or LM RCS system selected.
- [ ] Confirm RCS FUEL quantity.
- [ ] Confirm RCS OXIDIZER quantity.
- [ ] Confirm system power and isolation valves (future).
- [ ] Verify caution/master indicators (future).
- [ ] Confirm current rotational rates near planned values.
- [ ] Apply minimal attitude-control pulse and verify expected movement.
- [ ] Apply opposite pulse to remove residual rotational rate.
- [ ] Apply minimal translation pulse and verify predicted velocity change.
- [ ] Counter-pulse and verify residual relative velocity.
- [ ] Record anomalies through crew/CAPCOM channels.

## 5.10 Abnormal operation — draft
**Control selected — no observed response:**
1. Check propellant quantities and applicable caution indications.
2. Repeat only if operationally justified; repeated failed requests are not proof of permanence.
3. Verify system status using independent rate/velocity instruments.
4. Select appropriate alternate jet or backup control as procedures permit.
5. Communicate observable symptoms to CAPCOM. Do not diagnose internal failure state from hidden server data.

**Unexpected attitude or translation:**
1. Discontinue commanded pulses.
2. Observe attitude rate and relative velocity.
3. Counter-control cautiously if sufficient thruster authority remains.
4. Request FIDO assistance for trajectory implications.

---

# APPENDIX A — SIMULATOR-SPECIFIC DIFFERENCES AND LIMITATIONS
1. Earth has a **fixed orientation**; sunlight has a fixed simulated direction. No axial rotation is presently performed.
2. The optical windows render Earth/Moon using a custom WebGL sphere ray-caster.
3. The RCS controller is presently simplified and one-pulse-per-press; does not yet model multiple independent jet strings/quads, valve states, continuous thrust on hold, rate-command automatic stabilization or full six-axis rigid-body physics.
4. The CSM and LM mass/inertia models are preliminary, not certified.
5. Electrical power and consumable switching, isolation, individual propellant valves, and many instruments are not yet implemented.
6. This document will expand incrementally; never present planned equipment as installed or tested.

# APPENDIX B — ENGINEERING TABLES
| Item | Current development default | Status |
|---|---:|---|
| RCS pulse duration | 0.1 second | Configurable, approximate |
| SM modeled thrust | 445 N | Approximate |
| LM modeled thrust | 445 N | Approximate |
| CM modeled thrust | 410 N | Approximate |
| Fuel/oxidizer consumed | 40% / 60% of total | Assumed until verified |
| RCS control behavior | One pulse per click | Implemented in first version |
| Local translation directions | ±forward, ±right, ±up | Implemented in first version |
| Attitude directions | ±pitch, ±yaw, ±roll | Implemented in first version |

# APPENDIX C — AUTHORING AND PRINT PREPARATION
**Target format:** U.S. letter, monochrome, two-column technical manual where useful; bold all-caps headings, monospaced tables, numbered steps, warning/caution boxes, revision numbers, page numbers, schematic line drawings, and blank annotation space. Use original wording and historically inspired *design*, not NASA trademarks or false claims of official origin. A PDF can be rendered from this Markdown once sections stabilize. Cross-reference equipment names to actual simulator files/SQL schema before each release.


## Flight Director Attitude Indicator (FDAI) / IMU — development edition
The **eight-ball** is a separately movable instrument on the CM Commander and LM Commander stations. It shows roll (R), pitch (P) and yaw (Y) as a round moving ball and fixed numerical scales rounded to **0.1 degree**. The display represents the **IMU's estimated attitude**, not an independently perfect measurement of the spacecraft's attitude. The optical window remains tied to the true simulation physics state.

### Attitude tracking
Each one-second simulation tick the IMU module reads the current craft pitch, yaw and roll rates, rounds **each rate to 5 decimal places (degrees/second)**, multiplies by the one-second timestep, and integrates its own angle state. The estimate is retained in SQL independently of the physics object, so small quantization discrepancies can accumulate. This first-pass Euler-angle integration is simplified; it does not model full 3D gimbal kinematics, mechanical response, star-sighting errors, or measured Apollo gyro drift.

### Alignment — temporary simulator shortcut
Pressing **IMU ALIGN** currently prompts for confirmation and makes the estimated attitude equal to the spacecraft's true orientation without moving the spacecraft. This is a development/testing control, **not a historically accurate Apollo alignment method**. A later revision will substitute star sightings and guidance-computer-based alignment; it should never provide the crew with a direct view of hidden true values.

### Reset and installation
Run **imu_schema.sql once** after the main schema exists, before deploying the IMU-enabled execute.php worker. The schema registers independent Commander instruments and retains their later editor positions. The launch-reset specification includes IMU state, its reference configuration and alignment timestamps while retaining the instrument layout.

## ELECTRICAL POWER AND IMU/RCS SWITCHING — 08 NOV 2026
The CM and LM have independent RCS group and IMU power selectors. **ON** changes state 4 (switched OFF) to state 1 (operational); **OFF** changes working state 1 to 4. A failed component in state 2 or 3 cannot be repaired by cycling its selector. RCS group switching applies only to healthy direction components; faults remain faults. Power-off is not a fault and does not activate MASTER caution. An off IMU freezes its estimated attitude while the spacecraft continues to rotate in true physical space; realignment is a separate operation. Nominal model electrical loads are 0.1 A per powered RCS cluster and 2 A per powered IMU. Power-bus load integration is not yet present.

### Reliability patterns
RCS direction commands preserve their earlier user-selected activation bands. IMU sampling, once per simulation second when powered and operational, checks the current shared 1–100000 random value. Values **below 100** ending in **3** cause a temporary fault; values below 100 ending in **4** cause a permanent fault. These are simulation-specific decisions and must not be presented as NASA measured failure rates. Every future component needs an explicit electrical load and its own approved reliability rule.


## SERVICE MODULE FUEL CELLS — FLIGHT CREW SUPPLEMENT
**Applicable station:** CM Commander only. **Instrument:** independent `instruments/fuel_cells.php` with three analog ammeters and separate ON/OFF switches.

Each service-module fuel cell is modeled as a generator of **−50 A** in the simulator's signed electrical-load convention. Its cockpit gauge indicates **50 A output** at nominal operation and zero when off or unavailable; the negative database sign means power generation and is not a negative needle position. Real Apollo fuel-cell current varied with demand; this is an initial fixed-output simulator abstraction, not a historical NASA electrical curve.

An ON switch takes state 4 (intentionally OFF) to state 1 (working) only while the CM and SM remain mated. OFF changes a working cell from state 1 to state 4. A temporary or permanent failed component (2 or 3) cannot be repaired by cycling the switch. Each fuel cell's Master Caution lamp is separate in the CM/SM alarm panel; OFF is not a fault.

The once-per-second fuel-cell logic evaluates the common 1–100000 random roll when the cell is operating. For temporary failures, cell 1 uses **10–20**, cell 2 **21–40**, cell 3 **41–50**; permanent failure occurs at **501**, **502**, **503** respectively. The boundary allocation avoids overlapping triggers at 20 and 40. Temporary failure lasts a randomly selected 60–3600 seconds in the current wall-clock-based reliability implementation. The user-selected probabilities are not historical failure data.

**CAUTION — CM/SM SEPARATION:** The SM supplies the three fuel cells. When the SM ceases to be mated with the CM, any fuel cell not in the intentionally OFF position fails permanently and an event is recorded. **Switch all three fuel cells OFF before separation.** Reattaching a module will not reset a permanent failure. This rule is a simulator-specific operational consequence, not a statement about actual Apollo fuel-cell design. The future electrical bus will have to transfer the CM to its appropriate batteries before SM separation.


## CM ELECTRICAL SYSTEM — DEVELOPMENT OPERATING PROCEDURES
**Independent panel:** `instruments/batteries.php` (CM Commander). Two batteries, each nominal **40 ampere-hours**. Each has its own analog dial from 0 to 40 Ah and an ON/OFF selector. An isolated battery neither supplies nor accepts charge.

**Electrical sign convention:** An ON component with positive `amps` is a load; an ON fuel cell with negative `amps` generates current. The calculator adds CM components and SM components only while the modules are mated. For each simulation second, the total Ah variation is **−net_current_A / 3600**. Equal charging or discharging is shared among the enabled batteries until either 0 or their nameplate capacities; then remaining charge/discharge is redistributed to batteries with headroom.

**BUS UNDERVOLT:** While CM/SM are mated, a positive net current triggers a CM caution/warning condition, including Master Alarm until acknowledged. This is a **simplified current-deficit alert**, not an actual measured bus voltage or Apollo-valid undervoltage threshold. After separation, ordinary positive battery demand does not activate this specific warning.

**LOAD SHED:** If the batteries cannot provide the next second's demand, operating positive-load components are switched to state 4 in ascending order of current draw until supply covers demand, or there are no eligible loads. The resulting shed components remain switched off; they do not automatically recover. The crew must find the cause and deliberately re-enable equipment. The LOAD SHED secondary warning and Master Alarm indicate the brownout event. Switch OFF is not permanent failure (state 3). No random sequence is used: lowest-load first is the prescribed order.

**LIMITATIONS:** This electrical bus is a training approximation. Battery terminal voltage, maximum discharge current, circuit routing, electrochemistry, bus ties, full Apollo electrical topology and actual fuel-cell current regulation are not modeled yet. Always verify crew indications and do not assume that a 40 Ah gauge measures instantaneous amperes.


## Reliability overhaul — 09 Nov 2026
All registered components share **one ID-based reliability algorithm**. Each second, `moonReliabilityTick` draws a single random integer from 1 through 70000000 and checks every component whose state is 1. For component ID `N`: permanently fail at exactly `N`; temporarily fail at exactly `10000+N`, `20000+N`, `30000+N`, `40000+N`, `50000+N`. States 2, 3, 4 are not sampled for new failures. The old RCS per-command odds, IMU suffix odds, and separate fuel-cell random bands are obsolete; physical SM-separation failure conditions remain. Component IDs must be below 10000. The one-time `reset_component_failures.sql` returns states 2/3 to 1, keeps intentional OFF (4), clears fault history and electrical caution flags, and leaves simulator positions, batteries and layouts untouched. Run while execute worker is stopped, after deploying updated code. Active underlying power deficits or separation can legitimately cause alarms again after resuming.


## RENDEZVOUS RADAR — DEVELOPMENT OPERATING PROCEDURES
Each CM and LM Commander console has a separate `rendezvous_radar.php` instrument with a vertical 0–740 km tape gauge, nose-relative OFF AXIS angle in degrees and separate switches for local RADAR and local RESPONDER. A valid reading requires **the observing craft's radar ON and the other craft's responder ON**, with both vehicles active and within 740 km. The reciprocal direction requires the other radar and this craft's responder ON. Power state 4 yields no return; state 2 or 3 faults cannot be repaired by cycling a switch. Both radar and responder are normal `reliability_components` so the shared database-ID random rule applies on each simulation tick.

The geometry uses the true `objects` XYZ positions and pointing angles (pitch/yaw/roll); the off-axis angle is the three-dimensional separation between the spacecraft nose axis and the line to the target, from 0° pointing directly at the target to 180° pointed away. A simple idealized measurement is saved in `computer_registers` for each vehicle as `RENDEZVOUS_RANGE_M`, `RENDEZVOUS_OFF_AXIS_DEG`, `RENDEZVOUS_AZIMUTH_DEG`, and `RENDEZVOUS_ELEVATION_DEG`. Each contains a numeric value, validity flag and simulation tick. The eventual DSKY can read these without recalculating. Loss of radar/responder power, excess range, or inactive target **invalidates** the stored measurement; it does not misleadingly report zero.

**Limitations:** This is a simplified two-way responder-assisted geometric radar for the training simulator, not a verified reconstruction of the Apollo CSM/LM hardware; actual spacecraft radar and transponder architectures were asymmetric. No target occlusion, antenna pointing limit, time-of-flight delay, noise, relative range rate, or lock-on/search is yet modeled. Radar and responder nominal electrical loads are each 3 A when ON, as a simulator assumption; the existing electrical calculator includes this current for the CM/SM bus.


## Reliability range update — 09 Nov 2026
The shared once-per-second random roll is now uniformly chosen from **1 through 70,000,000**. Each component's existing six trigger values are unchanged: permanent at its database ID; temporary at 10,000 × (1 through 5) plus that ID. This makes those random events 100× less likely than the prior 100,000-number range. Existing databases must first run `reliability_70million_upgrade.sql` to replace the old CHECK constraint; then deploy updated `reliability.php`. To clear all accumulated failures and cautions without disturbing ON/OFF choices, run `reset_component_failures.sql` while the worker is stopped. Intentional OFF state (4), instrument layout, spacecraft motion, and battery charge remain unchanged. Physical failures and electric shortages can regenerate alarms after restart.


## Reliability range adjustment — 70 million
The current random roll is **1–70,000,000 per simulated second**. ID-specific failure trigger numbers have not changed. Relative to the 10-million range, random temporary and permanent failure probabilities are approximately seven times lower for a continuously operating component. Apply `reliability_70million_upgrade.sql` to existing databases before deploying the revised `reliability.php`, while the simulation worker is stopped. This range upgrade does not itself reset component states or alarm history; `reset_component_failures.sql` remains a separate optional reset.


## SAMPLED ACCELEROMETERS / G-METERS — NOV 2026
The CM **ACCEL G** and LM **T/W / G** instruments are each independently movable small analog gauges. Both draw **0.1 A** while ON and obey the common reliability system. The CM real Apollo ACCEL G and LM T/W display differ in details; the simulator deliberately provides a common signed forward-axis G dial (-2 to +10 Earth g) and a supplementary total G reading.

The new table `g_meter_samples` retains each craft's previous measured XYZ velocity rounded to **0.01 m/s**, a sample tick/time, the calculated Earth+Moon gravity vector, and the latest nongravitational acceleration vector, forward-axis signed `g_forward`, magnitude `g_magnitude`, and `valid` flag. `moonGMeterTick()` is called once per simulation second after physics. The difference in quantized velocity snapshots is divided by elapsed simulated seconds, with the average gravitational acceleration subtracted. Thus a freely coasting craft should display **approximately 0 G**, despite its orbital velocity continuously changing due to gravity. Signed forward G follows craft true pitch/yaw, not estimated IMU attitude. Values are stored, and the UI reads them from `g_meter_status.php`, **never recomputing from live true velocity**.

When switched OFF or failed, readings become invalid and baseline is dropped. On returning to ON, one sampled second seeds a new baseline before reporting G. This instrument is approximate and not a physical inertial sensor model; maneuvers or unmodeled forces can cause discrepancies, particularly at scene transitions, and the LM T/W scale is not presented as authentic lunar-g units.
