ALTER TABLE hostmacro MODIFY hostmacroid bigint unsigned NOT NULL,
		      MODIFY hostid bigint unsigned NOT NULL;
DELETE FROM hostmacro WHERE NOT hostid IN (SELECT hostid FROM hosts);
-- remove duplicates to allow unique index
DELETE hostmacro
	FROM hostmacro
	LEFT OUTER JOIN (
		SELECT MIN(hostmacroid) AS hostmacroid
		FROM hostmacro
		GROUP BY hostid,macro
	) keep_rows ON
		hostmacro.hostmacroid=keep_rows.hostmacroid
	WHERE keep_rows.hostmacroid IS NULL;
DROP INDEX hostmacro_1 ON hostmacro;
CREATE UNIQUE INDEX hostmacro_1 ON hostmacro (hostid,macro);
ALTER TABLE hostmacro ADD CONSTRAINT c_hostmacro_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
