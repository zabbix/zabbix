alter table items add value_type int4 DEFAULT '0' NOT NULL;
alter table items_template add value_type int4 DEFAULT '0' NOT NULL;

alter table items modify lastvalue varchar(255) default null;
alter table items modify prevvalue varchar(255) default null;

alter table functions modify lastvalue varchar(255) default '0.0000' not null;

CREATE TABLE history_str (
  itemid                int4            DEFAULT '0' NOT NULL,
  clock                 int4            DEFAULT '0' NOT NULL,
  value                 varchar(255)    DEFAULT '' NOT NULL,
  PRIMARY KEY (itemid,clock),
  FOREIGN KEY (itemid) REFERENCES items
);

insert into items_template (itemtemplateid,description,key_,delay,value_type)
        values (65,'Host name','system[hostname]', 1800, 1);
insert into items_template (itemtemplateid,description,key_,delay,value_type)
        values (66,'Host information','system[uname]', 1800, 1);
insert into items_template (itemtemplateid,description,key_,delay,value_type)
        values (67,'Version of zabbix_agent(d) running','version[zabbix_agent]',3600, 1);
