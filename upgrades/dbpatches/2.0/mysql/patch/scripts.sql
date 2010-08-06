ALTER TABLE scripts MODIFY usrgrpid bigint unsigned NULL;
ALTER TABLE scripts MODIFY groupid bigint unsigned NULL;
UPDATE scripts SET usrgrpid=NULL WHERE usrgrpid=0;
UPDATE scripts SET groupid=NULL WHERE groupid=0;
ALTER TABLE scripts ADD CONSTRAINT c_scripts_1 FOREIGN KEY (usrgrpid) REFERENCES usrgrp (usrgrpid);
ALTER TABLE scripts ADD CONSTRAINT c_scripts_2 FOREIGN KEY (groupid) REFERENCES groups (groupid);
