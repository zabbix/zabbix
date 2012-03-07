CREATE TABLE item_discovery (
	itemdiscoveryid          bigint                                    NOT NULL,
	itemid                   bigint                                    NOT NULL,
	parent_itemid            bigint                                    NOT NULL,
	key_                     varchar(255)    WITH DEFAULT ''           NOT NULL,
	lastcheck                integer         WITH DEFAULT '0'          NOT NULL,
	ts_delete                integer         WITH DEFAULT '0'          NOT NULL,
	PRIMARY KEY (itemdiscoveryid)
)
/
CREATE UNIQUE INDEX item_discovery_1 on item_discovery (itemid,parent_itemid)
/
ALTER TABLE item_discovery ADD CONSTRAINT c_item_discovery_1 FOREIGN KEY (itemid) REFERENCES items (itemid) ON DELETE CASCADE
/
ALTER TABLE item_discovery ADD CONSTRAINT c_item_discovery_2 FOREIGN KEY (parent_itemid) REFERENCES items (itemid) ON DELETE CASCADE
/
