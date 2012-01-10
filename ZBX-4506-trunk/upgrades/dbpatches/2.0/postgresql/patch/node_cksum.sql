ALTER TABLE ONLY node_cksum ALTER nodeid DROP DEFAULT,
			    ALTER recordid DROP DEFAULT;
DELETE FROM node_cksum WHERE NOT EXISTS (SELECT 1 FROM nodes WHERE nodes.nodeid=node_cksum.nodeid);
CREATE INDEX node_cksum_1 ON node_cksum (nodeid,cksumtype,tablename,recordid);
ALTER TABLE ONLY node_cksum ADD CONSTRAINT c_node_cksum_1 FOREIGN KEY (nodeid) REFERENCES nodes (nodeid) ON DELETE CASCADE;
