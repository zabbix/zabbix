CREATE LANGUAGE 'plpgsql';

CREATE or REPLACE FUNCTION zbx_drop_index(idx_name varchar)
RETURNS VOID
AS $$
DECLARE cnt integer;
BEGIN
	SELECT INTO cnt count(relname)
		FROM pg_class
		WHERE relname=idx_name
			AND oid IN (
				SELECT indexrelid
					FROM pg_index, pg_class
					WHERE pg_class.oid=pg_index.indrelid);
	IF cnt > 0 THEN
		EXECUTE 'DROP INDEX ' || idx_name;
	END IF;
END;
$$ LANGUAGE 'plpgsql';

SELECT zbx_drop_index('node_cksum_1');
SELECT zbx_drop_index('node_cksum_cksum_1');

DROP FUNCTION zbx_drop_index(idx_name varchar);

DROP LANGUAGE 'plpgsql';

ALTER TABLE ONLY node_cksum ALTER nodeid DROP DEFAULT,
			    ALTER recordid DROP DEFAULT;
DELETE FROM node_cksum WHERE NOT EXISTS (SELECT 1 FROM nodes WHERE nodes.nodeid=node_cksum.nodeid);
CREATE INDEX node_cksum_1 ON node_cksum (nodeid,cksumtype,tablename,recordid);
ALTER TABLE ONLY node_cksum ADD CONSTRAINT c_node_cksum_1 FOREIGN KEY (nodeid) REFERENCES nodes (nodeid) ON DELETE CASCADE;
