ALTER TABLE ONLY config ALTER configid DROP DEFAULT,
			ALTER alert_usrgrpid DROP DEFAULT,
			ALTER alert_usrgrpid DROP NOT NULL,
			ALTER discovery_groupid DROP DEFAULT,
			ADD ns_support integer DEFAULT '0' NOT NULL;
UPDATE config SET alert_usrgrpid=NULL WHERE NOT alert_usrgrpid IN (SELECT usrgrpid FROM usrgrp);
UPDATE config SET discovery_groupid=NULL WHERE NOT discovery_groupid IN (SELECT groupid FROM groups);
ALTER TABLE ONLY config ADD CONSTRAINT c_config_1 FOREIGN KEY (alert_usrgrpid) REFERENCES usrgrp (usrgrpid);
ALTER TABLE ONLY config ADD CONSTRAINT c_config_2 FOREIGN KEY (discovery_groupid) REFERENCES groups (groupid);
