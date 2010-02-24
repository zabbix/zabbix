CREATE TABLE items_applications_tmp (
	itemappid	bigint DEFAULT '0'	NOT NULL,
	applicationid	bigint DEFAULT '0'	NOT NULL,
	itemid		bigint DEFAULT '0'	NOT NULL,
	PRIMARY KEY (itemappid)
) with OIDS;
CREATE INDEX items_applications_tmp_1 on items_applications_tmp (applicationid,itemid);

insert into items_applications_tmp select NULL,applicationid,itemid from items_applications;
drop table items_applications;
alter table items_applications_tmp rename to items_applications;

CREATE TABLE items_applications_tmp (
	itemappid	bigint DEFAULT '0'	NOT NULL,
	applicationid	bigint DEFAULT '0'	NOT NULL,
	itemid		bigint DEFAULT '0'	NOT NULL,
	PRIMARY KEY (itemappid)
) with OIDS;
CREATE INDEX items_applications_1 on items_applications_tmp (applicationid,itemid);
CREATE INDEX items_applications_2 on items_applications_tmp (itemid);

insert into items_applications_tmp select * from items_applications;
drop table items_applications;
alter table items_applications_tmp rename to items_applications;
