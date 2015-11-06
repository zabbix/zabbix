CREATE SEQUENCE history_sync_seq
START WITH 1
INCREMENT BY 1
NOMAXVALUE
/

CREATE TRIGGER history_sync_tr
BEFORE INSERT ON history_sync
FOR EACH ROW
BEGIN
SELECT proxy_history_seq.nextval INTO :new.id FROM dual;
END;
/

