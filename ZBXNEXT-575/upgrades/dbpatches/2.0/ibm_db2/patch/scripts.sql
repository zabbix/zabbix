ALTER TABLE scripts ALTER COLUMN scriptid SET WITH DEFAULT NULL
/
REORG TABLE scripts
/
ALTER TABLE scripts ALTER COLUMN usrgrpid SET WITH DEFAULT NULL
/
REORG TABLE scripts
/
ALTER TABLE scripts ALTER COLUMN usrgrpid DROP NOT NULL
/
REORG TABLE scripts
/
ALTER TABLE scripts ALTER COLUMN groupid SET WITH DEFAULT NULL
/
REORG TABLE scripts
/
ALTER TABLE scripts ALTER COLUMN groupid DROP NOT NULL
/
REORG TABLE scripts
/
UPDATE scripts SET usrgrpid=NULL WHERE usrgrpid=0
/
UPDATE scripts SET groupid=NULL WHERE groupid=0
/
DELETE FROM scripts WHERE NOT usrgrpid IS NULL AND NOT usrgrpid IN (SELECT usrgrpid FROM usrgrp)
/
DELETE FROM scripts WHERE NOT groupid IS NULL AND NOT groupid IN (SELECT groupid FROM groups)
/
ALTER TABLE scripts ADD CONSTRAINT c_scripts_1 FOREIGN KEY (usrgrpid) REFERENCES usrgrp (usrgrpid)
/
REORG TABLE scripts
/
ALTER TABLE scripts ADD CONSTRAINT c_scripts_2 FOREIGN KEY (groupid) REFERENCES groups (groupid)
/
REORG TABLE scripts
/
