ALTER TABLE escalations MODIFY escalationid DEFAULT NULL;
ALTER TABLE escalations MODIFY actionid DEFAULT NULL;
ALTER TABLE escalations MODIFY triggerid DEFAULT NULL;
ALTER TABLE escalations MODIFY triggerid NULL;
ALTER TABLE escalations MODIFY eventid DEFAULT NULL;
ALTER TABLE escalations MODIFY eventid NULL;
ALTER TABLE escalations MODIFY r_eventid DEFAULT NULL;
ALTER TABLE escalations MODIFY r_eventid NULL;
DELETE FROM escalations;
