-- Run once on an EXISTING moonland database after the original reliability_schema.sql.
ALTER TABLE reliability_components
 ADD COLUMN master TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = display individual alarm light',
 ADD COLUMN alarm_name VARCHAR(40) NULL COMMENT 'Short label on cockpit light';
-- Existing components remain unselected until you set master=1.
-- Example: UPDATE reliability_components SET master=1,alarm_name='Fuel Cell 1' WHERE component_key='sm_fuel_cell_1';
