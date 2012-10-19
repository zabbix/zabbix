ALTER TABLE timeperiods ALTER COLUMN timeperiodid SET WITH DEFAULT NULL
/
REORG TABLE timeperiods
/
