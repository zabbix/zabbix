DECLARE index_not_exists EXCEPTION;
PRAGMA EXCEPTION_INIT(index_not_exists, -1418);
BEGIN
	EXECUTE IMMEDIATE 'DROP INDEX NODE_CKSUM_1';
EXCEPTION
	WHEN index_not_exists THEN NULL;
END;
/
DECLARE index_not_exists EXCEPTION;
PRAGMA EXCEPTION_INIT(index_not_exists, -1418);
BEGIN
	EXECUTE IMMEDIATE 'DROP INDEX NODE_CKSUM_CKSUM_1';
EXCEPTION
	WHEN index_not_exists THEN NULL;
END;
/
ALTER TABLE node_cksum MODIFY nodeid DEFAULT NULL;
ALTER TABLE node_cksum MODIFY recordid DEFAULT NULL;
DELETE FROM node_cksum WHERE NOT nodeid IN (SELECT nodeid FROM nodes);
CREATE INDEX node_cksum_1 ON node_cksum (nodeid,cksumtype,tablename,recordid);
ALTER TABLE node_cksum ADD CONSTRAINT c_node_cksum_1 FOREIGN KEY (nodeid) REFERENCES nodes (nodeid) ON DELETE CASCADE;
