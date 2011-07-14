ALTER TABLE escalations MODIFY escalationid DEFAULT NULL;
ALTER TABLE escalations MODIFY actionid DEFAULT NULL;
ALTER TABLE escalations MODIFY triggerid DEFAULT NULL;
ALTER TABLE escalations MODIFY triggerid NULL;
ALTER TABLE escalations MODIFY eventid DEFAULT NULL;
ALTER TABLE escalations MODIFY r_eventid DEFAULT NULL;
ALTER TABLE escalations MODIFY r_eventid NULL;
UPDATE escalations SET triggerid=NULL WHERE triggerid=0;
UPDATE escalations SET r_eventid=NULL WHERE r_eventid=0;
