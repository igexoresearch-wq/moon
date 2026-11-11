-- Moon Program propulsion upgrade. Apply to moonland after schema.sql.
-- SI units. Combined fuel + oxidizer are represented by one propellant mass.
-- Engine thrust and mass flow are nominal approximations, not mission-specific
-- performance curves. No engines are commanded on during installation.
-- Historical sources:
-- https://www.nasa.gov/wp-content/uploads/static/apollo50th/pdf/A15_PressKit.pdf
-- https://www.nasa.gov/wp-content/uploads/static/history/alsj/a11/Apollo11_Press-Kit_restored.pdf
-- https://web.mit.edu/digitalapollo/Documents/Chapter5/saturnas501.pdf
CREATE TABLE IF NOT EXISTS propulsion_tanks (
 vehicle_id INT UNSIGNED NOT NULL PRIMARY KEY,
 capacity_kg DOUBLE NOT NULL,
 remaining_kg DOUBLE NOT NULL,
 description VARCHAR(100) NOT NULL,
 CONSTRAINT fk_propulsion_tank_vehicle FOREIGN KEY(vehicle_id) REFERENCES objects(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS propulsion_engines (
 id INT UNSIGNED NOT NULL PRIMARY KEY,
 vehicle_id INT UNSIGNED NOT NULL,
 engine_name VARCHAR(80) NOT NULL,
 thrust_n DOUBLE NOT NULL,
 mass_flow_kg_s DOUBLE NOT NULL,
 throttleable TINYINT(1) NOT NULL DEFAULT 0,
 minimum_throttle_pct DOUBLE NOT NULL DEFAULT 100,
 command_on TINYINT(1) NOT NULL DEFAULT 0,
 throttle_pct DOUBLE NOT NULL DEFAULT 100,
 firing TINYINT(1) NOT NULL DEFAULT 0,
 starts_used INT UNSIGNED NOT NULL DEFAULT 0,
 maximum_starts INT UNSIGNED NOT NULL DEFAULT 0,
 CONSTRAINT fk_propulsion_engine_vehicle FOREIGN KEY(vehicle_id) REFERENCES objects(id),
 INDEX prop_engine_vehicle(vehicle_id)
) ENGINE=InnoDB;
-- Tank loads: approximate S-IC/S-II/S-IVB; Apollo 11 SPS ~18,500 kg;
-- Apollo 11 LM descent ~8248 kg, ascent ~2376 kg. The numbers below
-- are reference initial loads, not a flight-readiness assertion.
INSERT IGNORE INTO propulsion_tanks(vehicle_id,capacity_kg,remaining_kg,description) VALUES
(4,18500,18500,'CSM service propulsion'),
(6,2376,2376,'LM ascent propulsion'),
(7,8248,8248,'LM descent propulsion'),
(8,109000,109000,'S-IVB combined LOX and LH2'),
(9,450000,450000,'S-II combined LOX and LH2'),
(10,2000000,2000000,'S-IC combined LOX and RP-1');
-- Representative engine values: F-1 ~6.77 MN at sea level (263 s Isp);
-- J-2 ~1.03 MN vacuum (~421 s Isp); SPS 91.2 kN (~314 s);
-- LM APS 15.6 kN (~311 s); LM DPS 43.9 kN (~311 s).
-- Flow rates computed as thrust / (Isp * 9.80665).
-- Non-throttleable engines must stay at 100%; 10%-100% LM DPS
-- represents an educational simplification of the real discontinuous
-- fixed/full-throttle and variable-throttle operating regimes.
INSERT IGNORE INTO propulsion_engines
(id,vehicle_id,engine_name,thrust_n,mass_flow_kg_s,throttleable,minimum_throttle_pct,command_on,throttle_pct,firing,starts_used,maximum_starts)
VALUES
(101,10,'S-IC F-1 CENTER',6770000,6770000/(263*9.80665),0,100,0,100,0,0,1),
(102,10,'S-IC F-1 OUTBOARD A',6770000,6770000/(263*9.80665),0,100,0,100,0,0,1),
(103,10,'S-IC F-1 OUTBOARD B',6770000,6770000/(263*9.80665),0,100,0,100,0,0,1),
(104,10,'S-IC F-1 OUTBOARD C',6770000,6770000/(263*9.80665),0,100,0,100,0,0,1),
(105,10,'S-IC F-1 OUTBOARD D',6770000,6770000/(263*9.80665),0,100,0,100,0,0,1),
(201,9,'S-II J-2 CENTER',1030000,1030000/(421*9.80665),0,100,0,100,0,0,1),
(202,9,'S-II J-2 OUTBOARD A',1030000,1030000/(421*9.80665),0,100,0,100,0,0,1),
(203,9,'S-II J-2 OUTBOARD B',1030000,1030000/(421*9.80665),0,100,0,100,0,0,1),
(204,9,'S-II J-2 OUTBOARD C',1030000,1030000/(421*9.80665),0,100,0,100,0,0,1),
(205,9,'S-II J-2 OUTBOARD D',1030000,1030000/(421*9.80665),0,100,0,100,0,0,1),
(301,8,'S-IVB J-2',1030000,1030000/(421*9.80665),0,100,0,100,0,0,2),
(401,4,'CSM SPS',91200,91200/(314*9.80665),0,100,0,100,0,0,0),
(601,6,'LM ASCENT',15570,15570/(311*9.80665),0,100,0,100,0,0,1),
(701,7,'LM DESCENT',43900,43900/(311*9.80665),1,10,0,100,0,0,0);
