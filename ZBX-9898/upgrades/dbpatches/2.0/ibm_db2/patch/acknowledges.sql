ALTER TABLE acknowledges ALTER COLUMN acknowledgeid SET WITH DEFAULT NULL
/
REORG TABLE acknowledges
/
ALTER TABLE acknowledges ALTER COLUMN userid SET WITH DEFAULT NULL
/
REORG TABLE acknowledges
/
ALTER TABLE acknowledges ALTER COLUMN eventid SET WITH DEFAULT NULL
/
REORG TABLE acknowledges
/
DELETE FROM acknowledges WHERE NOT userid IN (SELECT userid FROM users)
/
DELETE FROM acknowledges WHERE NOT eventid IN (SELECT eventid FROM events)
/
ALTER TABLE acknowledges ADD CONSTRAINT c_acknowledges_1 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE
/
ALTER TABLE acknowledges ADD CONSTRAINT c_acknowledges_2 FOREIGN KEY (eventid) REFERENCES events (eventid) ON DELETE CASCADE
/
