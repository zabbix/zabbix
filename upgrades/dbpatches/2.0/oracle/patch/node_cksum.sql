ALTER TABLE node_cksum MODIFY nodeid DEFAULT NULL;
ALTER TABLE node_cksum MODIFY recordid DEFAULT NULL;
DELETE FROM node_cksum WHERE NOT nodeid IN (SELECT nodeid FROM nodes);
ALTER TABLE node_cksum ADD CONSTRAINT c_node_cksum_1 FOREIGN KEY (nodeid) REFERENCES nodes (nodeid) ON DELETE CASCADE;
