alter table history_str_sync modify value           nvarchar2(255)          DEFAULT '';

CREATE SEQUENCE history_str_sync_seq
START WITH 1
INCREMENT BY 1
NOMAXVALUE
/

CREATE TRIGGER history_str_sync_tr
BEFORE INSERT ON history_str_sync
FOR EACH ROW
BEGIN
SELECT proxy_history_seq.nextval INTO :new.id FROM dual;
END;
/

