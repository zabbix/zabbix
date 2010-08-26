ALTER TABLE acknowledges MODIFY acknowledgeid DEFAULT NULL;
ALTER TABLE acknowledges MODIFY userid DEFAULT NULL;
ALTER TABLE acknowledges MODIFY eventid DEFAULT NULL;
DELETE FROM acknowledges WHERE NOT userid IN (SELECT userid FROM users);
DELETE FROM acknowledges WHERE NOT eventid IN (SELECT eventid FROM events);
ALTER TABLE acknowledges ADD CONSTRAINT c_acknowledges_1 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE;
ALTER TABLE acknowledges ADD CONSTRAINT c_acknowledges_2 FOREIGN KEY (eventid) REFERENCES events (eventid) ON DELETE CASCADE;
