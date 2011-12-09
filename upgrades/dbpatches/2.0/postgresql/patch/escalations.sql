ALTER TABLE ONLY escalations ALTER escalationid DROP DEFAULT,
		ALTER actionid DROP DEFAULT,
		ALTER triggerid DROP DEFAULT,
		ALTER triggerid DROP NOT NULL,
		ALTER eventid DROP DEFAULT,
		ALTER eventid DROP NOT NULL,
		ALTER r_eventid DROP DEFAULT,
		ALTER r_eventid DROP NOT NULL;
DELETE FROM escalations;
