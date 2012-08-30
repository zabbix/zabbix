ALTER TABLE ONLY hostmacro ALTER hostmacroid DROP DEFAULT,
			   ALTER hostid DROP DEFAULT;
DELETE FROM hostmacro WHERE NOT EXISTS (SELECT 1 FROM hosts WHERE hosts.hostid=hostmacro.hostid);
-- remove duplicates to allow unique index
DELETE FROM hostmacro
	WHERE hostmacroid IN (
		SELECT hm1.hostmacroid
		FROM hostmacro hm1
		LEFT OUTER JOIN (
			SELECT MIN(hm2.hostmacroid) AS hostmacroid
			FROM hostmacro hm2
			GROUP BY hm2.hostid,hm2.macro
		) keep_rows ON
			hm1.hostmacroid=keep_rows.hostmacroid
		WHERE keep_rows.hostmacroid IS NULL
	);
DROP INDEX hostmacro_1;
CREATE UNIQUE INDEX hostmacro_1 ON hostmacro (hostid,macro);
ALTER TABLE ONLY hostmacro ADD CONSTRAINT c_hostmacro_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
