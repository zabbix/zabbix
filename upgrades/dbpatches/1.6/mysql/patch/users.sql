alter table users add autologin integer DEFAULT '0' NOT NULL;
alter table users add theme varchar(128) DEFAULT 'default.css' NOT NULL;
