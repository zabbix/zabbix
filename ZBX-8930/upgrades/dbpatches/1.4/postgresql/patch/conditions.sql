CREATE TABLE conditions_tmp (
	conditionid	bigint DEFAULT '0'	NOT NULL,
	actionid	bigint DEFAULT '0'	NOT NULL,
	conditiontype	integer		DEFAULT '0'	NOT NULL,
	operator	integer		DEFAULT '0'	NOT NULL,
	value		varchar(255)	DEFAULT ''	NOT NULL,
	PRIMARY KEY (conditionid)
) with OIDS;
CREATE INDEX conditions_1 on conditions_tmp (actionid);

insert into conditions_tmp select * from conditions;
drop table conditions;
alter table conditions_tmp rename to conditions;
