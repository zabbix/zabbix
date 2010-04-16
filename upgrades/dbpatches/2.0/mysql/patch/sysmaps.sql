ALTER TABLE sysmaps ADD expandproblem INTEGER DEFAULT '1' NOT NULL;
ALTER TABLE sysmaps ADD markelements INTEGER DEFAULT '0' NOT NULL;

UPDATE sysmaps SET expandproblem=0 WHERE highlight>1 AND highlight<4;
UPDATE sysmaps SET markelements=1 WHERE highlight>4;
UPDATE sysmaps SET highlight=(highlight-4) WHERE highlight>4;
UPDATE sysmaps SET highlight=(highlight-2) WHERE highlight>1;