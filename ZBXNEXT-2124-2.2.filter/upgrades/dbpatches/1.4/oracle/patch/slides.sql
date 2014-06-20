CREATE TABLE slides (
	slideid		number(20)	DEFAULT '0'	NOT NULL,
	slideshowid	number(20)	DEFAULT '0'	NOT NULL,
	screenid	number(20)	DEFAULT '0'	NOT NULL,
	step		number(10)	DEFAULT '0'	NOT NULL,
	delay		number(10)	DEFAULT '0'	NOT NULL,
	PRIMARY KEY (slideid)
);
CREATE INDEX slides_slides_1 on slides (slideshowid);
