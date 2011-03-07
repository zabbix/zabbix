ALTER TABLE ONLY scripts ALTER scriptid DROP DEFAULT,
			 ALTER usrgrpid DROP DEFAULT,
			 ALTER usrgrpid DROP NOT NULL,
			 ALTER groupid DROP DEFAULT,
			 ALTER groupid DROP NOT NULL,
			 ADD description text DEFAULT '' NOT NULL,
			 ADD confirmation varchar(255) DEFAULT '' NOT NULL;
UPDATE scripts SET usrgrpid=NULL WHERE usrgrpid=0;
UPDATE scripts SET groupid=NULL WHERE groupid=0;
DELETE FROM scripts WHERE usrgrpid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM usrgrp WHERE usrgrp.usrgrpid=scripts.usrgrpid);
DELETE FROM scripts WHERE groupid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM groups WHERE groups.groupid=scripts.groupid);
ALTER TABLE ONLY scripts ADD CONSTRAINT c_scripts_1 FOREIGN KEY (usrgrpid) REFERENCES usrgrp (usrgrpid);
ALTER TABLE ONLY scripts ADD CONSTRAINT c_scripts_2 FOREIGN KEY (groupid) REFERENCES groups (groupid);
