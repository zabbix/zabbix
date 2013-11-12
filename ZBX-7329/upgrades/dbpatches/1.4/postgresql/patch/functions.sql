CREATE TABLE functions_tmp (
	functionid	bigint	DEFAULT '0'	NOT NULL,
	itemid		bigint	DEFAULT '0'	NOT NULL,
	triggerid	bigint	DEFAULT '0'	NOT NULL,
	lastvalue	varchar(255)			,
	function	varchar(12)		DEFAULT ''	NOT NULL,
	parameter	varchar(255)		DEFAULT '0'	NOT NULL,
	PRIMARY KEY (functionid)
) with OIDS;
CREATE INDEX functions_1 on functions_tmp (triggerid);
CREATE INDEX functions_2 on functions_tmp (itemid,function,parameter);

insert into functions_tmp select * from functions;
drop table functions;
alter table functions_tmp rename to functions;
