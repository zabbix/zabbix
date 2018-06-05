CREATE TABLE item_discovery (
	itemdiscoveryid          number(20)                                NOT NULL,
	itemid                   number(20)                                NOT NULL,
	parent_itemid            number(20)                                NOT NULL,
	key_                     nvarchar2(255)  DEFAULT ''                ,
	lastcheck                number(10)      DEFAULT '0'               NOT NULL,
	ts_delete                number(10)      DEFAULT '0'               NOT NULL,
	PRIMARY KEY (itemdiscoveryid)
);
CREATE UNIQUE INDEX item_discovery_1 on item_discovery (itemid,parent_itemid);
ALTER TABLE item_discovery ADD CONSTRAINT c_item_discovery_1 FOREIGN KEY (itemid) REFERENCES items (itemid) ON DELETE CASCADE;
ALTER TABLE item_discovery ADD CONSTRAINT c_item_discovery_2 FOREIGN KEY (parent_itemid) REFERENCES items (itemid) ON DELETE CASCADE;
