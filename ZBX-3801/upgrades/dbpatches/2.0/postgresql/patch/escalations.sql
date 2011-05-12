ALTER TABLE ONLY escalations ALTER escalationid DROP DEFAULT,
			     ALTER actionid DROP DEFAULT,
			     ALTER triggerid DROP DEFAULT,
			     ALTER triggerid DROP NOT NULL,
			     ALTER eventid DROP DEFAULT,
			     ALTER r_eventid DROP DEFAULT,
			     ALTER r_eventid DROP NOT NULL;
UPDATE escalations SET triggerid=NULL WHERE triggerid=0;
UPDATE escalations SET r_eventid=NULL WHERE r_eventid=0;
