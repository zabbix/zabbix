CREATE TABLE images_tmp (
	imageid         number(20)              DEFAULT '0'     NOT NULL,
	imagetype               number(10)              DEFAULT '0'     NOT NULL,
	name            varchar2(64)            DEFAULT '0'     ,
	image           blob            DEFAULT ''      NOT NULL,
	PRIMARY KEY (imageid)
);
CREATE INDEX images_1 on images_tmp (imagetype,name);

insert into images_tmp select * from images;
drop trigger images_trigger;
drop sequence images_imageid;
drop table images;
alter table images_tmp rename to images;
