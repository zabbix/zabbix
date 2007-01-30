CREATE TABLE config (
--  smtp_server		varchar(255)	DEFAULT '' NOT NULL,
--  smtp_helo		varchar(255)	DEFAULT '' NOT NULL,
--  smtp_email		varchar(255)	DEFAULT '' NOT NULL,
--  password_required	int(1)		DEFAULT '0' NOT NULL,
  alert_history		int(4)		DEFAULT '0' NOT NULL,
  alarm_history		int(4)		DEFAULT '0' NOT NULL,
  refresh_unsupported	int(4)		DEFAULT '0' NOT NULL,
  work_period		varchar(100)	DEFAULT '1-5,00:00-24:00' NOT NULL
) type=InnoDB;
