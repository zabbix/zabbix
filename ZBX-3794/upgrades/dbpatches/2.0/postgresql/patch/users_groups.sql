ALTER TABLE ONLY users_groups ALTER id DROP DEFAULT,
			      ALTER usrgrpid DROP DEFAULT,
			      ALTER userid DROP DEFAULT;
DROP INDEX users_groups_1;
DELETE FROM users_groups WHERE NOT EXISTS (SELECT 1 FROM usrgrp WHERE usrgrp.usrgrpid=users_groups.usrgrpid);
DELETE FROM users_groups WHERE NOT EXISTS (SELECT 1 FROM users WHERE users.userid=users_groups.userid);
CREATE UNIQUE INDEX users_groups_1 ON users_groups (usrgrpid,userid);
ALTER TABLE ONLY users_groups ADD CONSTRAINT c_users_groups_1 FOREIGN KEY (usrgrpid) REFERENCES usrgrp (usrgrpid) ON DELETE CASCADE;
ALTER TABLE ONLY users_groups ADD CONSTRAINT c_users_groups_2 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE;
