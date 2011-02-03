ALTER TABLE config MODIFY configid bigint unsigned NOT NULL,
		   MODIFY alert_usrgrpid bigint unsigned NULL,
		   MODIFY discovery_groupid bigint unsigned NOT NULL,
		   MODIFY default_theme varchar(128) DEFAULT 'css_ob.css' NOT NULL,
		   ADD ns_support integer DEFAULT '0' NOT NULL;
			 ADD severity_color_0 varchar(6) DEFAULT 'information' NOT NULL;
			 ADD severity_color_1 varchar(6) DEFAULT 'information' NOT NULL;
			 ADD severity_color_2 varchar(6) DEFAULT 'warning' NOT NULL;
			 ADD severity_color_3 varchar(6) DEFAULT 'average' NOT NULL;
			 ADD severity_color_4 varchar(6) DEFAULT 'high' NOT NULL;
			 ADD severity_color_5 varchar(6) DEFAULT 'disaster' NOT NULL;
			 ADD severity_name_0 varchar(6) DEFAULT 'Not classified' NOT NULL;
			 ADD severity_name_1 varchar(6) DEFAULT 'Information' NOT NULL;
			 ADD severity_name_2 varchar(6) DEFAULT 'Warning' NOT NULL;
			 ADD severity_name_3 varchar(6) DEFAULT 'Average' NOT NULL;
			 ADD severity_name_4 varchar(6) DEFAULT 'High' NOT NULL;
			 ADD severity_name_5 varchar(6) DEFAULT 'Disaster' NOT NULL;


UPDATE config SET alert_usrgrpid=NULL WHERE NOT alert_usrgrpid IN (SELECT usrgrpid FROM usrgrp);
UPDATE config SET discovery_groupid=NULL WHERE NOT discovery_groupid IN (SELECT groupid FROM groups);
UPDATE config SET default_theme='css_ob.css' WHERE default_theme='default.css';

ALTER TABLE config ADD CONSTRAINT c_config_1 FOREIGN KEY (alert_usrgrpid) REFERENCES usrgrp (usrgrpid);
ALTER TABLE config ADD CONSTRAINT c_config_2 FOREIGN KEY (discovery_groupid) REFERENCES groups (groupid);
