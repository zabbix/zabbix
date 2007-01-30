CREATE TABLE items_applications (
	itemappid		bigint unsigned		DEFAULT '0'	NOT NULL,
	applicationid		bigint unsigned		DEFAULT '0'	NOT NULL,
	itemid		bigint unsigned		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (itemappid)
);
CREATE INDEX items_applications_1 on items_applications (applicationid,itemid);
