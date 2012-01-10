ALTER TABLE scripts
	MODIFY scriptid bigint unsigned NOT NULL,
	MODIFY usrgrpid bigint unsigned NULL,
	MODIFY groupid bigint unsigned NULL,
	ADD description text NOT NULL,
	ADD confirmation varchar(255) DEFAULT '' NOT NULL,
	ADD type integer DEFAULT '0' NOT NULL,
	ADD execute_on integer DEFAULT '1' NOT NULL;
UPDATE scripts SET usrgrpid=NULL WHERE usrgrpid=0;
UPDATE scripts SET groupid=NULL WHERE groupid=0;
UPDATE scripts SET type=1,command=TRIM(SUBSTRING(command, 5)) WHERE SUBSTRING(command, 1, 4)='IPMI';
DELETE FROM scripts WHERE usrgrpid IS NOT NULL AND usrgrpid NOT IN (SELECT usrgrpid FROM usrgrp);
DELETE FROM scripts WHERE groupid IS NOT NULL AND groupid NOT IN (SELECT groupid FROM groups);
ALTER TABLE scripts ADD CONSTRAINT c_scripts_1 FOREIGN KEY (usrgrpid) REFERENCES usrgrp (usrgrpid);
ALTER TABLE scripts ADD CONSTRAINT c_scripts_2 FOREIGN KEY (groupid) REFERENCES groups (groupid);
