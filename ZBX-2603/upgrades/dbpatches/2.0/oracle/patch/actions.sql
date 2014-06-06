ALTER TABLE actions MODIFY actionid DEFAULT NULL;
UPDATE actions SET esc_period=3600 WHERE eventsource=0 AND esc_period=0;
