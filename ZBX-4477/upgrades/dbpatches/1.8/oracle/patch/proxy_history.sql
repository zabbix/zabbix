DROP TABLE proxy_history
/

CREATE TABLE proxy_history (
	id		number(20)			NOT NULL,
	itemid		number(20)	DEFAULT '0'	NOT NULL,
	clock		number(10)	DEFAULT '0'	NOT NULL,
	timestamp	number(10)	DEFAULT '0'	NOT NULL,
	source		nvarchar2(64)	DEFAULT '',
	severity	number(10)	DEFAULT '0'	NOT NULL,
	value		nclob		DEFAULT '',
	logeventid	number(10)	DEFAULT '0'	NOT NULL,
	PRIMARY KEY (id)
)
/

CREATE INDEX proxy_history_1 on proxy_history (clock)
/

CREATE SEQUENCE proxy_history_seq
START WITH 1
INCREMENT BY 1
NOMAXVALUE
/

CREATE TRIGGER proxy_history_tr
BEFORE INSERT ON proxy_history
FOR EACH ROW
BEGIN
SELECT proxy_history_seq.nextval INTO :new.id FROM dual;
END;
/

