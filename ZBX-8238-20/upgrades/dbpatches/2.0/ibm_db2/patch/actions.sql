ALTER TABLE actions ALTER COLUMN actionid SET WITH DEFAULT NULL
/
REORG TABLE actions
/
UPDATE actions SET esc_period=3600 WHERE eventsource=0 AND esc_period=0
/
