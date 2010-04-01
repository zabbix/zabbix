CREATE TABLE images_tmp (
	imageid		bigint DEFAULT '0'	NOT NULL,
	imagetype	integer		DEFAULT '0'	NOT NULL,
	name		varchar(64)		DEFAULT '0'	NOT NULL,
	image		bytea	DEFAULT ''	NOT NULL,
	PRIMARY KEY (imageid)
) with OIDS;
CREATE INDEX images_1 on images_tmp (imagetype,name);

insert into images_tmp select * from images;
drop table images;
alter table images_tmp rename to images;
