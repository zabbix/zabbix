CREATE TABLE items_applications_tmp (
	itemappid               number(20)              DEFAULT '0'     NOT NULL,
	applicationid           number(20)              DEFAULT '0'     NOT NULL,
	itemid          number(20)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (itemappid)
);
CREATE INDEX items_applications_1 on items_applications_tmp (applicationid,itemid);
CREATE INDEX items_applications_2 on items_applications_tmp (itemid);

create sequence itemapp_tmp_itemappid
start with 1
increment by 1
nomaxvalue;

create trigger itemapp_tmp_trigger
before insert on items_applications_tmp
for each row
begin
	select itemapp_tmp_itemappid.nextval into :new.itemappid from dual;
end;
/

insert into items_applications_tmp select NULL,applicationid,itemid from items_applications;
drop trigger itemapp_tmp_trigger;
drop sequence itemapp_tmp_itemappid;
drop table items_applications;
alter table items_applications_tmp rename to items_applications;
