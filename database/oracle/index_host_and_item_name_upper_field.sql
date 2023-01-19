ALTER TABLE hosts ADD name_upper nvarchar2(128) DEFAULT '';

CREATE INDEX hosts_6 ON HOSTS(name_upper);

UPDATE hosts SET name_upper=UPPER(name);

CREATE TRIGGER hosts_name_upper_insert
BEFORE INSERT ON hosts FOR EACH ROW
BEGIN
	:NEW.name_upper:=UPPER(:NEW.name);
END;
/

CREATE TRIGGER hosts_name_upper_update
BEFORE UPDATE ON hosts FOR EACH ROW
BEGIN
	IF :NEW.name<>:OLD.name
	THEN
		:NEW.name_upper:=UPPER(:NEW.name);
	END IF;
END;
/

ALTER TABLE items ADD name_upper nvarchar2(255) DEFAULT '';

CREATE INDEX items_9 ON items (hostid,name_upper);

UPDATE items SET name_upper=UPPER(name);

CREATE TRIGGER items_name_upper_insert
BEFORE INSERT ON items FOR EACH ROW
BEGIN
	:NEW.name_upper:=UPPER(:NEW.name);
END;
/

CREATE TRIGGER items_name_upper_update
BEFORE UPDATE ON items FOR EACH ROW
BEGIN
	IF :NEW.name<>:OLD.name
	THEN
		:NEW.name_upper:=UPPER(:NEW.name);
	END IF;
END;
/
