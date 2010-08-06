ALTER TABLE ONLY scripts ALTER usrgrpid DROP DEFAULT;
ALTER TABLE ONLY scripts ALTER usrgrpid DROP NOT NULL;
ALTER TABLE ONLY scripts ALTER groupid DROP DEFAULT;
ALTER TABLE ONLY scripts ALTER groupid DROP NOT NULL;
UPDATE scripts SET usrgrpid=NULL WHERE usrgrpid=0;
UPDATE scripts SET groupid=NULL WHERE groupid=0;
ALTER TABLE ONLY scripts ADD CONSTRAINT c_scripts_1 FOREIGN KEY (usrgrpid) REFERENCES usrgrp (usrgrpid);
ALTER TABLE ONLY scripts ADD CONSTRAINT c_scripts_2 FOREIGN KEY (groupid) REFERENCES groups (groupid);
