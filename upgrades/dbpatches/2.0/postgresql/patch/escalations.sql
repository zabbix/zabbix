ALTER TABLE ONLY escalations ALTER actionid DROP DEFAULT;
ALTER TABLE ONLY escalations ALTER triggerid DROP DEFAULT;
ALTER TABLE ONLY escalations ALTER triggerid DROP NOT NULL;
ALTER TABLE ONLY escalations ALTER eventid DROP DEFAULT;
ALTER TABLE ONLY escalations ALTER r_eventid DROP DEFAULT;
ALTER TABLE ONLY escalations ALTER r_eventid DROP NOT NULL;
UPDATE escalations SET triggerid=NULL WHERE triggerid=0;
UPDATE escalations SET r_eventid=NULL WHERE r_eventid=0;
