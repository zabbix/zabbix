ALTER TABLE ONLY alerts ALTER alertid DROP DEFAULT,
			ALTER actionid DROP DEFAULT,
			ALTER eventid DROP DEFAULT,
			ALTER userid DROP DEFAULT,
			ALTER userid DROP NOT NULL,
			ALTER mediatypeid DROP DEFAULT,
			ALTER mediatypeid DROP NOT NULL;
UPDATE alerts SET userid=NULL WHERE userid=0;
UPDATE alerts SET mediatypeid=NULL WHERE mediatypeid=0;
DELETE FROM alerts WHERE NOT EXISTS (SELECT 1 FROM actions WHERE actions.actionid=alerts.actionid);
DELETE FROM alerts WHERE NOT EXISTS (SELECT 1 FROM events WHERE events.eventid=alerts.eventid);
DELETE FROM alerts WHERE NOT EXISTS (SELECT 1 FROM users WHERE users.userid=alerts.userid);
DELETE FROM alerts WHERE NOT EXISTS (SELECT 1 FROM media_type WHERE media_type.mediatypeid=alerts.mediatypeid);
ALTER TABLE ONLY alerts ADD CONSTRAINT c_alerts_1 FOREIGN KEY (actionid) REFERENCES actions (actionid) ON DELETE CASCADE;
ALTER TABLE ONLY alerts ADD CONSTRAINT c_alerts_2 FOREIGN KEY (eventid) REFERENCES events (eventid) ON DELETE CASCADE;
ALTER TABLE ONLY alerts ADD CONSTRAINT c_alerts_3 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE;
ALTER TABLE ONLY alerts ADD CONSTRAINT c_alerts_4 FOREIGN KEY (mediatypeid) REFERENCES media_type (mediatypeid) ON DELETE CASCADE;
