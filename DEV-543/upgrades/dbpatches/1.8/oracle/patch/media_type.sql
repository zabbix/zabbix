alter table media_type modify description             nvarchar2(100)          DEFAULT '';
alter table media_type modify smtp_server             nvarchar2(255)          DEFAULT '';
alter table media_type modify smtp_helo               nvarchar2(255)          DEFAULT '';
alter table media_type modify smtp_email              nvarchar2(255)          DEFAULT '';
alter table media_type modify exec_path               nvarchar2(255)          DEFAULT '';
alter table media_type modify gsm_modem               nvarchar2(255)          DEFAULT '';
alter table media_type modify username                nvarchar2(255)          DEFAULT '';
alter table media_type modify passwd          nvarchar2(255)          DEFAULT '';

