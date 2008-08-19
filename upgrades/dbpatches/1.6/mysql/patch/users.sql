alter table users add theme varchar(128) DEFAULT 'default.css' NOT NULL;
alter table users add attempt_failed          integer         DEFAULT 0       NOT NULL;
alter table users add attempt_ip              varchar(39)             DEFAULT ''      NOT NULL;
alter table users add attempt_clock           integer         DEFAULT 0       NOT NULL;
alter table users add autologin integer DEFAULT '0' NOT NULL after url;
