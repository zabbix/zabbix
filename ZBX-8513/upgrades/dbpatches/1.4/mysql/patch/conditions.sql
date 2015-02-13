CREATE TABLE conditions_tmp (
	conditionid		bigint unsigned		DEFAULT '0'	NOT NULL,
	actionid		bigint unsigned		DEFAULT '0'	NOT NULL,
	conditiontype		integer		DEFAULT '0'	NOT NULL,
	operator		integer		DEFAULT '0'	NOT NULL,
	value		varchar(255)		DEFAULT ''	NOT NULL,
	PRIMARY KEY (conditionid)
) ENGINE=InnoDB;
CREATE INDEX conditions_1 on conditions_tmp (actionid);

insert into conditions_tmp select * from conditions;
drop table conditions;
alter table conditions_tmp rename conditions;
