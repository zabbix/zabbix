DROP TABLE proxy_dhistory
/

CREATE TABLE proxy_dhistory (
	id		number(20)			NOT NULL,
	clock		number(10)	DEFAULT '0'	NOT NULL,
	druleid		number(20)	DEFAULT '0'	NOT NULL,
	type		number(10)	DEFAULT '0'	NOT NULL,
	ip		nvarchar2(39)	DEFAULT '',
	port		number(10)	DEFAULT '0'	NOT NULL,
	key_		nvarchar2(255)	DEFAULT '',
	value		nvarchar2(255)	DEFAULT '',
	status		number(10)	DEFAULT '0'	NOT NULL,
	dcheckid	number(20)	DEFAULT '0'	NOT NULL,
	PRIMARY KEY (id)
)
/

CREATE INDEX proxy_dhistory_1 on proxy_dhistory (clock)
/

CREATE SEQUENCE proxy_dhistory_seq
START WITH 1
INCREMENT BY 1
NOMAXVALUE
/

CREATE TRIGGER proxy_dhistory_tr
BEFORE INSERT ON proxy_dhistory
FOR EACH ROW
BEGIN
SELECT proxy_history_seq.nextval INTO :new.id FROM dual;
END;
/

