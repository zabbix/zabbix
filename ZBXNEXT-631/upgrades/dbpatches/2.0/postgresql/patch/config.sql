ALTER TABLE ONLY config ALTER configid DROP DEFAULT,
			ALTER alert_usrgrpid DROP DEFAULT,
			ALTER alert_usrgrpid DROP NOT NULL,
			ALTER discovery_groupid DROP DEFAULT,
			ALTER default_theme SET DEFAULT 'css_ob.css',
			ADD ns_support integer DEFAULT '0' NOT NULL;
			ADD severity_color_0 varchar(6) DEFAULT 'AADDAA' NOT NULL;
			ADD severity_color_1 varchar(6) DEFAULT 'CCE2CC' NOT NULL;
			ADD severity_color_2 varchar(6) DEFAULT 'EFEFCC' NOT NULL;
			ADD severity_color_3 varchar(6) DEFAULT 'DDAAAA' NOT NULL;
			ADD severity_color_4 varchar(6) DEFAULT 'FF8888' NOT NULL;
			ADD severity_color_5 varchar(6) DEFAULT 'FF0000' NOT NULL;
			ADD severity_name_0 varchar(32) DEFAULT 'Not classified' NOT NULL;
			ADD severity_name_1 varchar(32) DEFAULT 'Information' NOT NULL;
			ADD severity_name_2 varchar(32) DEFAULT 'Warning' NOT NULL;
			ADD severity_name_3 varchar(32) DEFAULT 'Average' NOT NULL;
			ADD severity_name_4 varchar(32) DEFAULT 'High' NOT NULL;
			ADD severity_name_5 varchar(32) DEFAULT 'Disaster' NOT NULL;
UPDATE config SET alert_usrgrpid=NULL WHERE NOT EXISTS (SELECT 1 FROM usrgrp WHERE usrgrp.usrgrpid=config.alert_usrgrpid);
UPDATE config SET discovery_groupid=NULL WHERE NOT EXISTS (SELECT 1 FROM groups WHERE groups.groupid=config.discovery_groupid);
UPDATE config SET default_theme='css_ob.css' WHERE default_theme='default.css';
ALTER TABLE ONLY config ADD CONSTRAINT c_config_1 FOREIGN KEY (alert_usrgrpid) REFERENCES usrgrp (usrgrpid);
ALTER TABLE ONLY config ADD CONSTRAINT c_config_2 FOREIGN KEY (discovery_groupid) REFERENCES groups (groupid);
