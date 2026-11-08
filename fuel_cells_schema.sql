-- Add-on, run once AFTER reliability_power_upgrade.sql.
-- Negative amps means electrical generation, not consumption.
INSERT IGNORE INTO reliability_components
(component_key,component_name,spacecraft_id,master,alarm_name,state,amps,failure_rule)
VALUES
('sm_fuel_cell_1','Fuel Cell 1',4,1,'FUEL CELL 1',1,-50.000,'none'),
('sm_fuel_cell_2','Fuel Cell 2',4,1,'FUEL CELL 2',1,-50.000,'none'),
('sm_fuel_cell_3','Fuel Cell 3',4,1,'FUEL CELL 3',1,-50.000,'none');
INSERT IGNORE INTO console_instruments(console_id,file_name,pos_x,pos_y)
VALUES ('cmc','fuel_cells.php',790,60);
-- Existing components are NOT reset or overwritten by this installer.
