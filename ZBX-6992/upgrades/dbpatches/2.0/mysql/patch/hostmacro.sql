ALTER TABLE hostmacro MODIFY hostmacroid bigint unsigned NOT NULL,
		      MODIFY hostid bigint unsigned NOT NULL;
DROP INDEX hostmacro_1 ON hostmacro;
DELETE FROM hostmacro WHERE NOT hostid IN (SELECT hostid FROM hosts);

-- remove duplicates to allow unique index
CREATE TEMPORARY TABLE tmp_hostmacro (hostmacroid bigint unsigned PRIMARY KEY);
INSERT INTO tmp_hostmacro (hostmacroid) (
	SELECT MIN(hostmacroid)
		FROM hostmacro
		GROUP BY hostid,macro
);
DELETE FROM hostmacro WHERE hostmacroid NOT IN (SELECT hostmacroid FROM tmp_hostmacro);
DROP TABLE tmp_hostmacro;

CREATE UNIQUE INDEX hostmacro_1 ON hostmacro (hostid,macro);
ALTER TABLE hostmacro ADD CONSTRAINT c_hostmacro_1 FOREIGN KEY (hostid) REFERENCES hosts (hostid) ON DELETE CASCADE;
