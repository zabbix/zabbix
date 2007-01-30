CREATE TABLE media_type (
	mediatypeid		bigint unsigned		DEFAULT '0'	NOT NULL,
	type		integer		DEFAULT '0'	NOT NULL,
	description		varchar(100)		DEFAULT ''	NOT NULL,
	smtp_server		varchar(255)		DEFAULT ''	NOT NULL,
	smtp_helo		varchar(255)		DEFAULT ''	NOT NULL,
	smtp_email		varchar(255)		DEFAULT ''	NOT NULL,
	exec_path		varchar(255)		DEFAULT ''	NOT NULL,
	gsm_modem		varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (mediatypeid)
);
