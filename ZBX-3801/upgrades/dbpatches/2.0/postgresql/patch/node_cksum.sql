ALTER TABLE ONLY node_cksum ALTER nodeid DROP DEFAULT,
			    ALTER recordid DROP DEFAULT;
DELETE FROM node_cksum WHERE NOT EXISTS (SELECT 1 FROM nodes WHERE nodes.nodeid=node_cksum.nodeid);
ALTER TABLE ONLY node_cksum ADD CONSTRAINT c_node_cksum_1 FOREIGN KEY (nodeid) REFERENCES nodes (nodeid) ON DELETE CASCADE;
