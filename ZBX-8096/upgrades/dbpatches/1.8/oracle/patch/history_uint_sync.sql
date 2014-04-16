CREATE SEQUENCE history_uint_sync_seq
START WITH 1
INCREMENT BY 1
NOMAXVALUE
/

CREATE TRIGGER history_uint_sync_tr
BEFORE INSERT ON history_uint_sync
FOR EACH ROW
BEGIN
SELECT proxy_history_seq.nextval INTO :new.id FROM dual;
END;
/

