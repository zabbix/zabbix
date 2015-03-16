CREATE TABLE history_str_sync (
	id              number(20)                      ,
	nodeid          number(20)              DEFAULT '0'     NOT NULL,
	itemid          number(20)              DEFAULT '0'     NOT NULL,
	clock           number(10)              DEFAULT '0'     NOT NULL,
	value           varchar2(255)           DEFAULT ''      ,
	PRIMARY KEY (id)
);
CREATE INDEX history_str_sync_1 on history_str_sync (nodeid,id);

create sequence history_str_sync_id
start with 1
increment by 1
nomaxvalue;

create trigger history_str_sync_trigger
before insert on history_str_sync
for each row
begin
	if (:new.id is null or :new.id = 0) then
		select history_str_sync_id.nextval into :new.id from dual;
	end if;
end;
/
