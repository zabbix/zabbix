ALTER TABLE config ALTER COLUMN configid SET WITH DEFAULT NULL
/
REORG TABLE config
/
ALTER TABLE config ALTER COLUMN alert_usrgrpid SET WITH DEFAULT NULL
/
REORG TABLE config
/
ALTER TABLE config ALTER COLUMN alert_usrgrpid DROP NOT NULL
/
REORG TABLE config
/
ALTER TABLE config ALTER COLUMN discovery_groupid SET WITH DEFAULT NULL
/
REORG TABLE config
/
ALTER TABLE config ALTER COLUMN default_theme SET WITH DEFAULT 'originalblue'
/
REORG TABLE config
/
ALTER TABLE config ADD severity_color_0 varchar(6) WITH DEFAULT 'DBDBDB' NOT NULL
/
REORG TABLE config
/
ALTER TABLE config ADD severity_color_1 varchar(6) WITH DEFAULT 'D6F6FF' NOT NULL
/
REORG TABLE config
/
ALTER TABLE config ADD severity_color_2 varchar(6) WITH DEFAULT 'FFF6A5' NOT NULL
/
REORG TABLE config
/
ALTER TABLE config ADD severity_color_3 varchar(6) WITH DEFAULT 'FFB689' NOT NULL
/
REORG TABLE config
/
ALTER TABLE config ADD severity_color_4 varchar(6) WITH DEFAULT 'FF9999' NOT NULL
/
REORG TABLE config
/
ALTER TABLE config ADD severity_color_5 varchar(6) WITH DEFAULT 'FF3838' NOT NULL
/
REORG TABLE config
/
ALTER TABLE config ADD severity_name_0 varchar(32) WITH DEFAULT 'Not classified' NOT NULL
/
REORG TABLE config
/
ALTER TABLE config ADD severity_name_1 varchar(32) WITH DEFAULT 'Information' NOT NULL
/
REORG TABLE config
/
ALTER TABLE config ADD severity_name_2 varchar(32) WITH DEFAULT 'Warning' NOT NULL
/
REORG TABLE config
/
ALTER TABLE config ADD severity_name_3 varchar(32) WITH DEFAULT 'Average' NOT NULL
/
REORG TABLE config
/
ALTER TABLE config ADD severity_name_4 varchar(32) WITH DEFAULT 'High' NOT NULL
/
REORG TABLE config
/
ALTER TABLE config ADD severity_name_5 varchar(32) WITH DEFAULT 'Disaster' NOT NULL
/
REORG TABLE config
/
ALTER TABLE config ADD ok_period integer WITH DEFAULT '1800' NOT NULL
/
REORG TABLE config
/
ALTER TABLE config ADD blink_period integer WITH DEFAULT '1800' NOT NULL
/
REORG TABLE config
/
ALTER TABLE config ADD problem_unack_color varchar(6) WITH DEFAULT 'DC0000' NOT NULL
/
REORG TABLE config
/
ALTER TABLE config ADD problem_ack_color varchar(6) WITH DEFAULT 'DC0000' NOT NULL
/
REORG TABLE config
/
ALTER TABLE config ADD ok_unack_color varchar(6) WITH DEFAULT '00AA00' NOT NULL
/
REORG TABLE config
/
ALTER TABLE config ADD ok_ack_color varchar(6) WITH DEFAULT '00AA00' NOT NULL
/
REORG TABLE config
/
ALTER TABLE config ADD problem_unack_style integer WITH DEFAULT '1' NOT NULL
/
REORG TABLE config
/
ALTER TABLE config ADD problem_ack_style integer WITH DEFAULT '1' NOT NULL
/
REORG TABLE config
/
ALTER TABLE config ADD ok_unack_style integer WITH DEFAULT '1' NOT NULL
/
REORG TABLE config
/
ALTER TABLE config ADD ok_ack_style integer WITH DEFAULT '1' NOT NULL
/
REORG TABLE config
/
ALTER TABLE config ADD snmptrap_logging integer WITH DEFAULT '1' NOT NULL
/
REORG TABLE config
/
ALTER TABLE config ADD server_check_interval integer WITH DEFAULT '60' NOT NULL
/
REORG TABLE config
/
UPDATE config SET alert_usrgrpid=NULL WHERE NOT alert_usrgrpid IN (SELECT usrgrpid FROM usrgrp)
/
UPDATE config SET discovery_groupid=(SELECT MIN(groupid) FROM groups) WHERE NOT discovery_groupid IN (SELECT groupid FROM groups)
/

UPDATE config SET default_theme='darkblue' WHERE default_theme='css_bb.css'
/
UPDATE config SET default_theme='originalblue' WHERE default_theme IN ('css_ob.css', 'default.css')
/
UPDATE config SET default_theme='darkorange' WHERE default_theme='css_od.css'
/

ALTER TABLE config ADD CONSTRAINT c_config_1 FOREIGN KEY (alert_usrgrpid) REFERENCES usrgrp (usrgrpid)
/
ALTER TABLE config ADD CONSTRAINT c_config_2 FOREIGN KEY (discovery_groupid) REFERENCES groups (groupid)
/
