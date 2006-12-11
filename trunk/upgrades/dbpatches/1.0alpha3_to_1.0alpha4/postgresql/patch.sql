-- Drop column "platformid" from items_template

CREATE TABLE temp AS SELECT itemtemplateid,description,key_,delay from items_template;

DROP TABLE items_template;

CREATE TABLE items_template (
	itemtemplateid        int4            NOT NULL,
	description           varchar(255)    DEFAULT '' NOT NULL,
	key_                  varchar(64)     DEFAULT '' NOT NULL,
	delay                 int4            DEFAULT '0' NOT NULL,
	PRIMARY KEY (itemtemplateid)
);

INSERT INTO items_template SELECT * FROM temp;
DROP TABLE temp;

-- Drop column "platformid" from hosts

create table temp as select hostid,host,port,status from hosts;

drop table hosts;

CREATE TABLE hosts (
	hostid                int default nextval('hosts_hostid_seq'),
	host                  varchar(64)     DEFAULT '' NOT NULL,
	port                  int4            DEFAULT '0' NOT NULL,
	status                int4            DEFAULT '0' NOT NULL,
	PRIMARY KEY (hostid)
);

INSERT INTO hosts SELECT * FROM temp;
DROP TABLE temp;
drop table platforms;

CREATE INDEX triggers_istrue on triggers (istrue);
CREATE INDEX items_nextcheck on items (nextcheck);
CREATE INDEX items_status on items (status);
