alter table items add value_type int4 DEFAULT '0' NOT NULL;
alter table items_template add value_type int4 DEFAULT '0' NOT NULL;

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
