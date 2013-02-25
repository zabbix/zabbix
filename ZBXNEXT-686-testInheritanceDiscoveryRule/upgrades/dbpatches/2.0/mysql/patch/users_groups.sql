ALTER TABLE users_groups MODIFY id bigint unsigned NOT NULL,
			 MODIFY usrgrpid bigint unsigned NOT NULL,
			 MODIFY userid bigint unsigned NOT NULL;
DELETE FROM users_groups WHERE usrgrpid NOT IN (SELECT usrgrpid FROM usrgrp);
DELETE FROM users_groups WHERE userid NOT IN (SELECT userid FROM users);

-- remove duplicates to allow unique index
CREATE TEMPORARY TABLE tmp_users_groups (id bigint unsigned PRIMARY KEY);
INSERT INTO tmp_users_groups (id) (
	SELECT MIN(id)
		FROM users_groups
		GROUP BY usrgrpid,userid
);
DELETE FROM users_groups WHERE id NOT IN (SELECT id FROM tmp_users_groups);
DROP TABLE tmp_users_groups;

DROP INDEX users_groups_1 ON users_groups;
CREATE UNIQUE INDEX users_groups_1 ON users_groups (usrgrpid,userid);
ALTER TABLE users_groups ADD CONSTRAINT c_users_groups_1 FOREIGN KEY (usrgrpid) REFERENCES usrgrp (usrgrpid) ON DELETE CASCADE;
ALTER TABLE users_groups ADD CONSTRAINT c_users_groups_2 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE;
