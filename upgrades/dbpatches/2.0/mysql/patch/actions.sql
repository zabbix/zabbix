ALTER TABLE actions
	MODIFY actionid bigint unsigned NOT NULL,
	MODIFY def_longdata text NOT NULL,
	MODIFY r_longdata text NOT NULL;
UPDATE actions SET esc_period=3600 WHERE eventsource=0 AND esc_period=0;
