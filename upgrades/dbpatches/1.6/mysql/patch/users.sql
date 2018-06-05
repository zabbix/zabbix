alter table users add theme varchar(128) DEFAULT 'default.css' NOT NULL;
alter table users add attempt_failed          integer         DEFAULT 0       NOT NULL;
alter table users add attempt_ip              varchar(39)             DEFAULT ''      NOT NULL;
alter table users add attempt_clock           integer         DEFAULT 0       NOT NULL;
alter table users add autologin integer DEFAULT '0' NOT NULL after url;
update users set passwd='5fce1b3e34b520afeffb37ce08c7cd66' where alias<>'guest' and passwd='d41d8cd98f00b204e9800998ecf8427e';
