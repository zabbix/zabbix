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
ALTER TABLE scripts ADD description varchar(2048) WITH DEFAULT '' NOT NULL
/
REORG TABLE scripts
/
ALTER TABLE scripts ADD confirmation varchar(255) WITH DEFAULT '' NOT NULL
/
REORG TABLE scripts
/
ALTER TABLE scripts ADD type integer WITH DEFAULT '0' NOT NULL
/
REORG TABLE scripts
/
ALTER TABLE scripts ADD execute_on integer WITH DEFAULT '1' NOT NULL
/
REORG TABLE scripts
/
UPDATE scripts SET usrgrpid=NULL WHERE usrgrpid=0
/
UPDATE scripts SET groupid=NULL WHERE groupid=0
/
UPDATE scripts SET type=1,command=TRIM(SUBSTR(command, 5)) WHERE SUBSTR(command, 1, 4)='IPMI'
/
DELETE FROM scripts WHERE usrgrpid IS NOT NULL AND usrgrpid NOT IN (SELECT usrgrpid FROM usrgrp)
/
DELETE FROM scripts WHERE groupid IS NOT NULL AND groupid NOT IN (SELECT groupid FROM groups)
/
ALTER TABLE scripts ADD CONSTRAINT c_scripts_1 FOREIGN KEY (usrgrpid) REFERENCES usrgrp (usrgrpid)
/
ALTER TABLE scripts ADD CONSTRAINT c_scripts_2 FOREIGN KEY (groupid) REFERENCES groups (groupid)
/
