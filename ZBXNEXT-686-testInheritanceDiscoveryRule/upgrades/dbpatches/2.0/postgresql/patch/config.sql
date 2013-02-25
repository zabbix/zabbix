ALTER TABLE ONLY config
	ALTER configid DROP DEFAULT,
	ALTER alert_usrgrpid DROP DEFAULT,
	ALTER alert_usrgrpid DROP NOT NULL,
	ALTER discovery_groupid DROP DEFAULT,
	ALTER default_theme SET DEFAULT 'originalblue',
	ADD severity_color_0 varchar(6) DEFAULT 'DBDBDB' NOT NULL,
	ADD severity_color_1 varchar(6) DEFAULT 'D6F6FF' NOT NULL,
	ADD severity_color_2 varchar(6) DEFAULT 'FFF6A5' NOT NULL,
	ADD severity_color_3 varchar(6) DEFAULT 'FFB689' NOT NULL,
	ADD severity_color_4 varchar(6) DEFAULT 'FF9999' NOT NULL,
	ADD severity_color_5 varchar(6) DEFAULT 'FF3838' NOT NULL,
	ADD severity_name_0 varchar(32) DEFAULT 'Not classified' NOT NULL,
	ADD severity_name_1 varchar(32) DEFAULT 'Information' NOT NULL,
	ADD severity_name_2 varchar(32) DEFAULT 'Warning' NOT NULL,
	ADD severity_name_3 varchar(32) DEFAULT 'Average' NOT NULL,
	ADD severity_name_4 varchar(32) DEFAULT 'High' NOT NULL,
	ADD severity_name_5 varchar(32) DEFAULT 'Disaster' NOT NULL,
	ADD ok_period integer DEFAULT '1800' NOT NULL,
	ADD blink_period integer DEFAULT '1800' NOT NULL,
	ADD problem_unack_color varchar(6) DEFAULT 'DC0000' NOT NULL,
	ADD problem_ack_color varchar(6) DEFAULT 'DC0000' NOT NULL,
	ADD ok_unack_color varchar(6) DEFAULT '00AA00' NOT NULL,
	ADD ok_ack_color varchar(6) DEFAULT '00AA00' NOT NULL,
	ADD problem_unack_style integer DEFAULT '1' NOT NULL,
	ADD problem_ack_style integer DEFAULT '1' NOT NULL,
	ADD ok_unack_style integer DEFAULT '1' NOT NULL,
	ADD ok_ack_style integer DEFAULT '1' NOT NULL,
	ADD snmptrap_logging integer DEFAULT '1' NOT NULL,
	ADD server_check_interval integer DEFAULT '60' NOT NULL;

UPDATE config SET alert_usrgrpid=NULL WHERE NOT EXISTS (SELECT 1 FROM usrgrp WHERE usrgrp.usrgrpid=config.alert_usrgrpid);
UPDATE config SET discovery_groupid=(SELECT MIN(groupid) FROM groups) WHERE NOT EXISTS (SELECT 1 FROM groups WHERE groups.groupid=config.discovery_groupid);

UPDATE config SET default_theme='darkblue' WHERE default_theme='css_bb.css';
UPDATE config SET default_theme='originalblue' WHERE default_theme IN ('css_ob.css', 'default.css');
UPDATE config SET default_theme='darkorange' WHERE default_theme='css_od.css';

ALTER TABLE ONLY config ADD CONSTRAINT c_config_1 FOREIGN KEY (alert_usrgrpid) REFERENCES usrgrp (usrgrpid);
ALTER TABLE ONLY config ADD CONSTRAINT c_config_2 FOREIGN KEY (discovery_groupid) REFERENCES groups (groupid);
