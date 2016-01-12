CREATE TABLE history_sync (
	id              number(20)                      ,
	nodeid          number(20)              DEFAULT '0'     NOT NULL,
	itemid          number(20)              DEFAULT '0'     NOT NULL,
	clock           number(10)              DEFAULT '0'     NOT NULL,
	value           number(20,4)            DEFAULT '0.0000'        NOT NULL,
	PRIMARY KEY (id)
);
CREATE INDEX history_sync_1 on history_sync (nodeid,id);

create sequence history_sync_id
start with 1
increment by 1
nomaxvalue;

create trigger history_sync_trigger
before insert on history_sync
for each row
begin
	if (:new.id is null or :new.id = 0) then
	        select history_sync_id.nextval into :new.id from dual;
	end if;
end;
/
