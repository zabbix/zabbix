ALTER TABLE sysmaps ADD expandproblem number(10) DEFAULT '1' NOT NULL;
UPDATE sysmaps SET expandproblem=0 WHERE highlight>1;
UPDATE sysmaps SET highlight=(highlight-2) WHERE highlight>1;