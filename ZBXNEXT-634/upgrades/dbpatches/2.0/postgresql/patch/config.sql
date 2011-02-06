ALTER TABLE ONLY config ALTER configid DROP DEFAULT,
			ALTER alert_usrgrpid DROP DEFAULT,
			ALTER alert_usrgrpid DROP NOT NULL,
			ALTER discovery_groupid DROP DEFAULT,
			ALTER default_theme SET DEFAULT 'css_ob.css',
			ADD ns_support integer DEFAULT '0' NOT NULL;
UPDATE config SET alert_usrgrpid=NULL WHERE NOT EXISTS (SELECT 1 FROM usrgrp WHERE usrgrp.usrgrpid=config.alert_usrgrpid);
UPDATE config SET discovery_groupid=NULL WHERE NOT EXISTS (SELECT 1 FROM groups WHERE groups.groupid=config.discovery_groupid);
UPDATE config SET default_theme='css_ob.css' WHERE default_theme='default.css';
ALTER TABLE ONLY config ADD CONSTRAINT c_config_1 FOREIGN KEY (alert_usrgrpid) REFERENCES usrgrp (usrgrpid);
ALTER TABLE ONLY config ADD CONSTRAINT c_config_2 FOREIGN KEY (discovery_groupid) REFERENCES groups (groupid);
