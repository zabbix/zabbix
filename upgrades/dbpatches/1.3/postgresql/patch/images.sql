CREATE TABLE images_tmp (
	imageid		bigint unsigned		DEFAULT '0'	NOT NULL,
	imagetype		integer		DEFAULT '0'	NOT NULL,
	name		varchar(64)		DEFAULT '0'	NOT NULL,
	image		longblob		DEFAULT ''	NOT NULL,
	PRIMARY KEY (imageid)
) ENGINE=InnoDB;
CREATE INDEX images_1 on images_tmp (imagetype,name);

insert into images_tmp select * from images;
drop table images;
alter table images_tmp rename images;
