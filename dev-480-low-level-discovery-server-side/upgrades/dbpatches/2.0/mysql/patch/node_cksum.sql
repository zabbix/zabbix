ALTER TABLE node_cksum MODIFY nodeid integer NOT NULL,
		       MODIFY recordid bigint unsigned NOT NULL;
DELETE FROM node_cksum WHERE NOT nodeid IN (SELECT nodeid FROM nodes);
ALTER TABLE node_cksum ADD CONSTRAINT c_node_cksum_1 FOREIGN KEY (nodeid) REFERENCES nodes (nodeid) ON DELETE CASCADE;
