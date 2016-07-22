ALTER TABLE ONLY nodes
	ALTER nodeid DROP DEFAULT,
	ALTER masterid DROP DEFAULT,
	ALTER masterid DROP NOT NULL,
	DROP COLUMN timezone,
	DROP COLUMN slave_history,
	DROP COLUMN slave_trends;
UPDATE nodes SET masterid=NULL WHERE masterid=0;
ALTER TABLE ONLY nodes ADD CONSTRAINT c_nodes_1 FOREIGN KEY (masterid) REFERENCES nodes (nodeid);
