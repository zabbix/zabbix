ALTER TABLE config MODIFY configid DEFAULT NULL;
ALTER TABLE config MODIFY alert_usrgrpid DEFAULT NULL;
ALTER TABLE config MODIFY alert_usrgrpid NULL;
ALTER TABLE config MODIFY discovery_groupid DEFAULT NULL;
ALTER TABLE config ADD ns_support number(10) DEFAULT '0' NOT NULL;
UPDATE config SET alert_usrgrpid=NULL WHERE NOT alert_usrgrpid IN (SELECT usrgrpid FROM usrgrp);
UPDATE config SET discovery_groupid=NULL WHERE NOT discovery_groupid IN (SELECT groupid FROM groups);
ALTER TABLE config ADD CONSTRAINT c_config_1 FOREIGN KEY (alert_usrgrpid) REFERENCES usrgrp (usrgrpid);
ALTER TABLE config ADD CONSTRAINT c_config_2 FOREIGN KEY (discovery_groupid) REFERENCES groups (groupid);
