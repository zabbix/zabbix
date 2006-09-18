
alter table graphs add graphtype	number(2) DEFAULT '0' NOT NULL;
alter table items  add delay_flex       varchar(255) DEFAULT NULL;

--
-- Table structure for table 'services_times'
--

CREATE TABLE services_times (
	timeid		number(10)		NOT NULL auto_increment,
	serviceid	number(10)          	DEFAULT '0' NOT NULL,
	type		number(3)		DEFAULT '0' NOT NULL,
	ts_from		number(10)		DEFAULT '0' NOT NULL,
	ts_to		number(10)		DEFAULT '0' NOT NULL,
	note		varchar(255)		DEFAULT NULL,
	CONSTRAINT services_times_pk PRIMARY KEY (timeid)
) type=InnoDB;

CREATE INDEX services_times_servicid on services_times (serviceid);
CREATE UNIQUE INDEX services_times_uniq on services_times (serviceid,type,ts_from,ts_to);

create sequence services_times_timeid 
start with 20000 
increment by 1 
nomaxvalue; 

create trigger services_times
before insert on services_times
for each row
begin
	if (:new.timeid is null or :new.timeid = 0) then
		select services_times_timeid.nextval into :new.timeid from dual;
	end if;
end;
/
