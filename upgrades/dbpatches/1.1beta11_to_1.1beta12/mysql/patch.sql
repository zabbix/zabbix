alter table media_type add	gsm_modem	varchar(255)	DEFAULT '' NOT NULL;

alter table actions drop delay;
alter table actions drop nextcheck;
