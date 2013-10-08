CREATE TABLE trigger_depends_tmp (
	triggerdepid            number(20)              DEFAULT '0'     NOT NULL,
	triggerid_down          number(20)              DEFAULT '0'     NOT NULL,
	triggerid_up            number(20)              DEFAULT '0'     NOT NULL,
	PRIMARY KEY (triggerdepid)
);
CREATE INDEX trigger_depends_1 on trigger_depends_tmp (triggerid_down,triggerid_up);
CREATE INDEX trigger_depends_2 on trigger_depends_tmp (triggerid_up);

create sequence triggerdep_tmp_triggerdepid
start with 1
increment by 1
nomaxvalue;

create trigger trigger_depends_tmp_trigger
before insert on trigger_depends_tmp
for each row
begin
	select triggerdep_tmp_triggerdepid.nextval into :new.triggerdepid from dual;
end;
/

insert into trigger_depends_tmp select NULL,triggerid_down,triggerid_up from trigger_depends;
drop trigger trigger_depends_tmp_trigger;
drop sequence triggerdep_tmp_triggerdepid;
drop table trigger_depends;
alter table trigger_depends_tmp rename to trigger_depends;
