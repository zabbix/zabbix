CREATE TABLE opconditions (
	opconditionid	number(20)	DEFAULT '0'	NOT NULL,
	operationid	number(20)	DEFAULT '0'	NOT NULL,
	conditiontype	number(10)	DEFAULT '0'	NOT NULL,
	operator	number(10)	DEFAULT '0'	NOT NULL,
	value		varchar2(255)	DEFAULT '',
	PRIMARY KEY (opconditionid)
);
CREATE INDEX opconditions_1 on opconditions (operationid);
