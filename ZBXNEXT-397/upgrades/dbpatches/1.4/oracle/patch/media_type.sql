CREATE TABLE media_type_tmp (
	mediatypeid             number(20)              DEFAULT '0'     NOT NULL,
	type            number(10)              DEFAULT '0'     NOT NULL,
	description             varchar2(100)           DEFAULT ''      ,
	smtp_server             varchar2(255)           DEFAULT ''      ,
	smtp_helo               varchar2(255)           DEFAULT ''      ,
	smtp_email              varchar2(255)           DEFAULT ''      ,
	exec_path               varchar2(255)           DEFAULT ''      ,
	gsm_modem               varchar2(255)           DEFAULT ''      ,
	username                varchar2(255)           DEFAULT ''      ,
	passwd          varchar2(255)           DEFAULT ''      ,
	PRIMARY KEY (mediatypeid)
);

insert into media_type_tmp select mediatypeid,type,description,smtp_server,smtp_helo,smtp_email,exec_path,gsm_modem,'','' from media_type;
drop trigger media_type_trigger;
drop sequence media_type_mediatypeid;
drop table media_type;
alter table media_type_tmp rename to media_type;
