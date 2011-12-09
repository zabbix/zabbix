ALTER TABLE escalations ALTER COLUMN escalationid SET WITH DEFAULT NULL
/
REORG TABLE escalations
/
ALTER TABLE escalations ALTER COLUMN actionid SET WITH DEFAULT NULL
/
REORG TABLE escalations
/
ALTER TABLE escalations ALTER COLUMN triggerid SET WITH DEFAULT NULL
/
REORG TABLE escalations
/
ALTER TABLE escalations ALTER COLUMN triggerid DROP NOT NULL
/
REORG TABLE escalations
/
ALTER TABLE escalations ALTER COLUMN eventid SET WITH DEFAULT NULL
/
REORG TABLE escalations
/
ALTER TABLE escalations ALTER COLUMN eventid DROP NOT NULL
/
REORG TABLE escalations
/
ALTER TABLE escalations ALTER COLUMN r_eventid SET WITH DEFAULT NULL
/
REORG TABLE escalations
/
ALTER TABLE escalations ALTER COLUMN r_eventid DROP NOT NULL
/
REORG TABLE escalations
/
DELETE FROM escalations
/
