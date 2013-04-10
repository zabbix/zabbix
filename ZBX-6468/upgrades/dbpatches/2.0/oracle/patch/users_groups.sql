ALTER TABLE users_groups MODIFY id DEFAULT NULL;
ALTER TABLE users_groups MODIFY usrgrpid DEFAULT NULL;
ALTER TABLE users_groups MODIFY userid DEFAULT NULL;
DELETE FROM users_groups WHERE usrgrpid NOT IN (SELECT usrgrpid FROM usrgrp);
DELETE FROM users_groups WHERE userid NOT IN (SELECT userid FROM users);

-- remove duplicates to allow unique index
DELETE FROM users_groups
	WHERE id IN (
		SELECT ug1.id
		FROM users_groups ug1
		LEFT OUTER JOIN (
			SELECT MIN(ug2.id) AS id
			FROM users_groups ug2
			GROUP BY ug2.usrgrpid,ug2.userid
		) keep_rows ON
			ug1.id=keep_rows.id
		WHERE keep_rows.id IS NULL
	);

DROP INDEX users_groups_1;
CREATE UNIQUE INDEX users_groups_1 ON users_groups (usrgrpid,userid);
ALTER TABLE users_groups ADD CONSTRAINT c_users_groups_1 FOREIGN KEY (usrgrpid) REFERENCES usrgrp (usrgrpid) ON DELETE CASCADE;
ALTER TABLE users_groups ADD CONSTRAINT c_users_groups_2 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE;
