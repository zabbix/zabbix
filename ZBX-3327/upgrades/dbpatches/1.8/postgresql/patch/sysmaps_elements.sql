CREATE TABLE sysmaps_elements_tmp (
        selementid              bigint          DEFAULT '0'     NOT NULL,
        sysmapid                bigint          DEFAULT '0'     NOT NULL,
        elementid               bigint          DEFAULT '0'     NOT NULL,
        elementtype             integer         DEFAULT '0'     NOT NULL,
        iconid_off              bigint          DEFAULT '0'     NOT NULL,
        iconid_on               bigint          DEFAULT '0'     NOT NULL,
        iconid_unknown          bigint          DEFAULT '0'     NOT NULL,
        label           varchar(255)            DEFAULT ''      NOT NULL,
        label_location          integer                 NULL,
        x               integer         DEFAULT '0'     NOT NULL,
        y               integer         DEFAULT '0'     NOT NULL,
        url             varchar(255)            DEFAULT ''      NOT NULL,
        iconid_disabled         bigint          DEFAULT '0'     NOT NULL,
	iconid_maintenance      bigint          DEFAULT '0'     NOT NULL,
        PRIMARY KEY (selementid)
) with OIDS;

insert into sysmaps_elements_tmp select selementid,sysmapid,elementid,elementtype,iconid_off,iconid_on,iconid_unknown,label,label_location,x,y,url,iconid_disabled from sysmaps_elements;
drop table sysmaps_elements;
alter table sysmaps_elements_tmp rename to sysmaps_elements;
