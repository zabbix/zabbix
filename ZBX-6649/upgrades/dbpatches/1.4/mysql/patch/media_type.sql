CREATE TABLE media_type_tmp (
	mediatypeid		bigint unsigned		DEFAULT '0'	NOT NULL,
	type		integer		DEFAULT '0'	NOT NULL,
	description		varchar(100)		DEFAULT ''	NOT NULL,
	smtp_server		varchar(255)		DEFAULT ''	NOT NULL,
	smtp_helo		varchar(255)		DEFAULT ''	NOT NULL,
	smtp_email		varchar(255)		DEFAULT ''	NOT NULL,
	exec_path		varchar(255)		DEFAULT ''	NOT NULL,
	gsm_modem		varchar(255)		DEFAULT ''	NOT NULL,
	username		varchar(255)		DEFAULT ''	NOT NULL,
	passwd			varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (mediatypeid)
) ENGINE=InnoDB;

insert into media_type_tmp select mediatypeid,type,description,smtp_server,smtp_helo,smtp_email,exec_path,gsm_modem,'','' from media_type;
drop table media_type;
alter table media_type_tmp rename media_type;
