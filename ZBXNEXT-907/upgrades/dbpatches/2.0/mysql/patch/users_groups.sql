ALTER TABLE users_groups MODIFY id bigint unsigned NOT NULL,
			 MODIFY usrgrpid bigint unsigned NOT NULL,
			 MODIFY userid bigint unsigned NOT NULL;
DROP INDEX users_groups_1 ON users_groups;
DELETE FROM users_groups WHERE usrgrpid NOT IN (SELECT usrgrpid FROM usrgrp);
DELETE FROM users_groups WHERE userid NOT IN (SELECT userid FROM users);
CREATE UNIQUE INDEX users_groups_1 ON users_groups (usrgrpid,userid);
ALTER TABLE users_groups ADD CONSTRAINT c_users_groups_1 FOREIGN KEY (usrgrpid) REFERENCES usrgrp (usrgrpid) ON DELETE CASCADE;
ALTER TABLE users_groups ADD CONSTRAINT c_users_groups_2 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE;
