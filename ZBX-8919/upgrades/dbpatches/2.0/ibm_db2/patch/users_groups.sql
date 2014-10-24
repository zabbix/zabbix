ALTER TABLE users_groups ALTER COLUMN id SET WITH DEFAULT NULL
/
REORG TABLE users_groups
/
ALTER TABLE users_groups ALTER COLUMN usrgrpid SET WITH DEFAULT NULL
/
REORG TABLE users_groups
/
ALTER TABLE users_groups ALTER COLUMN userid SET WITH DEFAULT NULL
/
REORG TABLE users_groups
/
DELETE FROM users_groups WHERE usrgrpid NOT IN (SELECT usrgrpid FROM usrgrp)
/
DELETE FROM users_groups WHERE userid NOT IN (SELECT userid FROM users)
/
-- remove duplicates to allow unique index
DELETE FROM users_groups
	WHERE id IN (
		SELECT hm1.id
		FROM users_groups hm1
		LEFT OUTER JOIN (
			SELECT MIN(hm2.id) AS id
			FROM users_groups hm2
			GROUP BY hm2.usrgrpid,hm2.userid
		) keep_rows ON
			hm1.id=keep_rows.id
		WHERE keep_rows.id IS NULL
	)
/
DROP INDEX users_groups_1
/
CREATE UNIQUE INDEX users_groups_1 ON users_groups (usrgrpid,userid)
/
ALTER TABLE users_groups ADD CONSTRAINT c_users_groups_1 FOREIGN KEY (usrgrpid) REFERENCES usrgrp (usrgrpid) ON DELETE CASCADE
/
ALTER TABLE users_groups ADD CONSTRAINT c_users_groups_2 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE
/
