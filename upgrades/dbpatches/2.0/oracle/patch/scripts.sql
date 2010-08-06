ALTER TABLE scripts MODIFY usrgrpid DEFAULT NULL;
ALTER TABLE scripts MODIFY usrgrpid NULL;
ALTER TABLE scripts MODIFY groupid DEFAULT NULL;
ALTER TABLE scripts MODIFY groupid NULL;
UPDATE scripts SET usrgrpid=NULL WHERE usrgrpid=0;
UPDATE scripts SET groupid=NULL WHERE groupid=0;
ALTER TABLE scripts ADD CONSTRAINT c_scripts_1 FOREIGN KEY (usrgrpid) REFERENCES usrgrp (usrgrpid);
ALTER TABLE scripts ADD CONSTRAINT c_scripts_2 FOREIGN KEY (groupid) REFERENCES groups (groupid);
