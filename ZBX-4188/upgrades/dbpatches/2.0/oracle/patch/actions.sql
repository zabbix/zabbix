ALTER TABLE actions MODIFY actionid DEFAULT NULL;
ALTER TABLE actions MODIFY def_longdata nvarchar2(2000) DEFAULT '';
ALTER TABLE actions MODIFY r_longdata nvarchar2(2000) DEFAULT '';
UPDATE actions SET esc_period=3600 WHERE eventsource=0 AND esc_period=0;
