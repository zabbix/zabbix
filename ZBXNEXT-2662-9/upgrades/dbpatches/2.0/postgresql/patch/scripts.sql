ALTER TABLE ONLY scripts
	ALTER scriptid DROP DEFAULT,
	ALTER usrgrpid DROP DEFAULT,
	ALTER usrgrpid DROP NOT NULL,
	ALTER groupid DROP DEFAULT,
	ALTER groupid DROP NOT NULL,
	ADD description text DEFAULT '' NOT NULL,
	ADD confirmation varchar(255) DEFAULT '' NOT NULL,
	ADD type integer DEFAULT '0' NOT NULL,
	ADD execute_on integer DEFAULT '1' NOT NULL;
UPDATE scripts SET usrgrpid=NULL WHERE usrgrpid=0;
UPDATE scripts SET groupid=NULL WHERE groupid=0;
UPDATE scripts SET type=1,command=TRIM(SUBSTRING(command FROM 5)) WHERE SUBSTRING(command FROM 1 FOR 4)='IPMI';
DELETE FROM scripts WHERE usrgrpid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM usrgrp WHERE usrgrp.usrgrpid=scripts.usrgrpid);
DELETE FROM scripts WHERE groupid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM groups WHERE groups.groupid=scripts.groupid);
ALTER TABLE ONLY scripts ADD CONSTRAINT c_scripts_1 FOREIGN KEY (usrgrpid) REFERENCES usrgrp (usrgrpid);
ALTER TABLE ONLY scripts ADD CONSTRAINT c_scripts_2 FOREIGN KEY (groupid) REFERENCES groups (groupid);
