ALTER TABLE hosts ADD name_upper VARCHAR(128) DEFAULT '' NOT NULL;

CREATE INDEX hosts_6 ON hosts (name_upper);

UPDATE hosts SET name_upper=UPPER(name);

CREATE OR REPLACE FUNCTION hosts_name_upper_upper()
RETURNS TRIGGER LANGUAGE PLPGSQL AS $func$
BEGIN
	UPDATE hosts SET name_upper=UPPER(name)
	WHERE hostid=NEW.hostid;
	RETURN NULL;
END $func$;

CREATE TRIGGER hosts_name_upper_insert AFTER INSERT ON hosts
FOR EACH ROW EXECUTE FUNCTION hosts_name_upper_upper();

CREATE TRIGGER hosts_name_upper_update AFTER UPDATE OF name ON hosts
FOR EACH ROW EXECUTE FUNCTION hosts_name_upper_upper();

ALTER TABLE items ADD name_upper VARCHAR(255) DEFAULT '' NOT NULL;

CREATE INDEX items_9 ON ITEMS (hostid,name_upper);

UPDATE items SET name_upper=UPPER(name);

CREATE OR REPLACE FUNCTION items_name_upper_upper()
RETURNS TRIGGER LANGUAGE PLPGSQL AS $func$
BEGIN
	UPDATE items SET name_upper=UPPER(name)
	WHERE itemid=NEW.itemid;
	RETURN NULL;
END $func$;

CREATE TRIGGER items_name_upper_insert AFTER INSERT ON items
FOR EACH ROW EXECUTE FUNCTION items_name_upper_upper();

CREATE TRIGGER items_name_upper_update AFTER UPDATE OF NAME ON ITEMS
FOR EACH ROW EXECUTE FUNCTION items_name_upper_upper();
