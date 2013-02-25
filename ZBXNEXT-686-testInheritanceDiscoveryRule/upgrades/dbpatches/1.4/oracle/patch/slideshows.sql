CREATE TABLE slideshows (
	slideshowid             number(20)              DEFAULT '0'     NOT NULL,
	name            varchar2(255)           DEFAULT ''      ,
	delay           number(10)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (slideshowid)
);
