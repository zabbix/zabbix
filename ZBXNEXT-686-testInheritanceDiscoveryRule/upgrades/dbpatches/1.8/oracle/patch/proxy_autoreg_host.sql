CREATE TABLE proxy_autoreg_host (
	id	number(20)			NOT NULL,
	clock	number(10)	DEFAULT '0'	NOT NULL,
	host	nvarchar2(64)	DEFAULT '',
	PRIMARY KEY (id)
)
/

CREATE INDEX proxy_autoreg_host_1 on proxy_autoreg_host (clock)
/

CREATE SEQUENCE proxy_autoreg_host_seq
START WITH 1
INCREMENT BY 1
NOMAXVALUE
/

CREATE TRIGGER proxy_autoreg_host_tr
BEFORE INSERT ON proxy_autoreg_host
FOR EACH ROW
BEGIN
SELECT proxy_history_seq.nextval INTO :new.id FROM dual;
END;
/
