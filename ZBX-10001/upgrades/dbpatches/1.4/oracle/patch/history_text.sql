CREATE TABLE history_text_tmp (
	id              number(20)              DEFAULT '0'     NOT NULL,
	itemid          number(20)              DEFAULT '0'     NOT NULL,
	clock           number(10)              DEFAULT '0'     NOT NULL,
	value           clob            DEFAULT ''      NOT NULL,
	PRIMARY KEY (id)
);
CREATE INDEX history_text_1 on history_text_tmp (itemid,clock);

create sequence history_text_tmp_id
start with 1
increment by 1
nomaxvalue;

create trigger history_text_tmp_trigger
before insert on history_text_tmp
for each row
begin
	select history_text_tmp_id.nextval into :new.id from dual;
end;
/

insert into history_text_tmp select NULL,itemid,clock,value from history_text;
drop trigger history_text_tmp_trigger;
drop sequence history_text_tmp_id;
drop table history_text;
alter table history_text_tmp rename to history_text;
