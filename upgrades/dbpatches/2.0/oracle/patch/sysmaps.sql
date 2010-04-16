ALTER TABLE sysmaps ADD expandproblem number(10) DEFAULT '1' NOT NULL;
ALTER TABLE sysmaps ADD markelements number(10) DEFAULT '0' NOT NULL;

UPDATE sysmaps SET expandproblem=0 WHERE highlight>1 AND highlight<4;
UPDATE sysmaps SET markelements=1 WHERE highlight>3;
UPDATE sysmaps SET highlight=(highlight-4) WHERE highlight>3;
UPDATE sysmaps SET highlight=(highlight-2) WHERE highlight>1;