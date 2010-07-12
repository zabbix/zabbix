ALTER TABLE sysmaps ADD expandproblem INTEGER DEFAULT '1' NOT NULL;
ALTER TABLE sysmaps ADD markelements INTEGER DEFAULT '0' NOT NULL;
ALTER TABLE sysmaps ADD show_unack INTEGER DEFAULT '0' NOT NULL;

UPDATE sysmaps SET show_unack=1 WHERE highlight>7 AND highlight<16;
UPDATE sysmaps SET show_unack=2 WHERE highlight>23;
UPDATE sysmaps SET highlight=(highlight-16) WHERE highlight>15;
UPDATE sysmaps SET highlight=(highlight-8) WHERE highlight>7;
UPDATE sysmaps SET markelements=1 WHERE highlight>3  AND highlight<8;
UPDATE sysmaps SET highlight=(highlight-4) WHERE highlight>3;
UPDATE sysmaps SET expandproblem=0 WHERE highlight>1 AND highlight<4;
UPDATE sysmaps SET highlight=(highlight-2) WHERE highlight>1;