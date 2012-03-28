ALTER TABLE node_cksum ALTER COLUMN nodeid SET WITH DEFAULT NULL
/
REORG TABLE node_cksum
/
ALTER TABLE node_cksum ALTER COLUMN recordid SET WITH DEFAULT NULL
/
REORG TABLE node_cksum
/
DELETE FROM node_cksum WHERE NOT nodeid IN (SELECT nodeid FROM nodes)
/
ALTER TABLE node_cksum ADD CONSTRAINT c_node_cksum_1 FOREIGN KEY (nodeid) REFERENCES nodes (nodeid) ON DELETE CASCADE
/
