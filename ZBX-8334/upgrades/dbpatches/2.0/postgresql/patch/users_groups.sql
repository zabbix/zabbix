ALTER TABLE ONLY users_groups ALTER id DROP DEFAULT,
			      ALTER usrgrpid DROP DEFAULT,
			      ALTER userid DROP DEFAULT;
DELETE FROM users_groups WHERE NOT EXISTS (SELECT 1 FROM usrgrp WHERE usrgrp.usrgrpid=users_groups.usrgrpid);
DELETE FROM users_groups WHERE NOT EXISTS (SELECT 1 FROM users WHERE users.userid=users_groups.userid);

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
ALTER TABLE ONLY users_groups ADD CONSTRAINT c_users_groups_1 FOREIGN KEY (usrgrpid) REFERENCES usrgrp (usrgrpid) ON DELETE CASCADE;
ALTER TABLE ONLY users_groups ADD CONSTRAINT c_users_groups_2 FOREIGN KEY (userid) REFERENCES users (userid) ON DELETE CASCADE;
