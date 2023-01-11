ALTER TABLE `hosts` ADD `name_upper` varchar(128) DEFAULT '' NOT NULL;

CREATE INDEX hosts_6 ON hosts (name_upper);

UPDATE hosts SET name_upper=UPPER(name);

DELIMITER $$

CREATE TRIGGER hosts_name_upper_insert
BEFORE INSERT ON hosts FOR EACH ROW
SET NEW.name_upper=UPPER(NEW.name)
$$

CREATE TRIGGER hosts_name_upper_update BEFORE UPDATE ON hosts
FOR EACH ROW
BEGIN
	IF NEW.name<>OLD.name
	THEN
		SET NEW.name_upper=UPPER(NEW.name);
	END IF;
END;
$$
DELIMITER ;
ALTER TABLE `items` ADD `name_upper` VARCHAR(255) DEFAULT '' NOT NULL;

CREATE INDEX items_9 ON items (hostid,name_upper);

UPDATE items SET name_upper=UPPER(name);

DELIMITER $$

CREATE TRIGGER items_name_upper_insert
BEFORE INSERT ON items FOR EACH ROW
SET NEW.name_upper=UPPER(NEW.name);
$$

CREATE TRIGGER items_name_upper_update BEFORE UPDATE ON items
FOR EACH ROW
BEGIN
	IF NEW.name<>OLD.name
	THEN
		SET NEW.name_upper=UPPER(NEW.name);
	END IF;
END;
$$
DELIMITER ;
