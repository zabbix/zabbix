alter table functions modify lastvalue varchar(255);
alter table functions modify parameter varchar(255) default '0' not null;
alter table hosts add network_errors int(4) DEFAULT '0'	NOT NULL;

--
-- Table structure for table 'service_alarms'
--

CREATE TABLE service_alarms (
  servicealarmid	int(4)		NOT NULL auto_increment,
  serviceid		int(4)		DEFAULT '0' NOT NULL,
  clock			int(4)		DEFAULT '0' NOT NULL,
  value			int(4)		DEFAULT '0' NOT NULL,
  PRIMARY KEY (servicealarmid),
  KEY (serviceid,clock),
  KEY (clock)
);

insert into items_template (itemtemplateid,description,key_,delay,value_type)
	values (80,'Average number of bytes received on interface lo (1min)','netloadin1[lo]', 5, 0);
insert into items_template (itemtemplateid,description,key_,delay,value_type)
	values (81,'Average number of bytes received on interface lo (5min)','netloadin5[lo]', 10, 0);
insert into items_template (itemtemplateid,description,key_,delay,value_type)
	values (82,'Average number of bytes received on interface lo (15min)','netloadin15[lo]', 20, 0);
insert into items_template (itemtemplateid,description,key_,delay,value_type)
	values (83,'Average number of bytes received on interface eth0 (1min)','netloadin1[eth0]', 5, 0);
insert into items_template (itemtemplateid,description,key_,delay,value_type)
	values (84,'Average number of bytes received on interface eth0 (5min)','netloadin5[eth0]', 10, 0);
insert into items_template (itemtemplateid,description,key_,delay,value_type)
	values (85,'Average number of bytes received on interface eth0 (15min)','netloadin15[eth0]', 20, 0);
insert into items_template (itemtemplateid,description,key_,delay,value_type)
	values (86,'Average number of bytes received on interface eth1 (1min)','netloadin1[eth1]', 5, 0);
insert into items_template (itemtemplateid,description,key_,delay,value_type)
	values (87,'Average number of bytes received on interface eth1 (5min)','netloadin5[eth1]', 10, 0);
insert into items_template (itemtemplateid,description,key_,delay,value_type)
	values (88,'Average number of bytes received on interface eth1 (15min)','netloadin15[eth1]', 20, 0);
insert into items_template (itemtemplateid,description,key_,delay,value_type)
	values (89,'Average number of bytes sent from interface lo (1min)','netloadout1[lo]', 5, 0);
insert into items_template (itemtemplateid,description,key_,delay,value_type)
	values (90,'Average number of bytes sent from interface lo (5min)','netloadout5[lo]', 10, 0);
insert into items_template (itemtemplateid,description,key_,delay,value_type)
	values (91,'Average number of bytes sent from interface lo (15min)','netloadout15[lo]', 20, 0);
insert into items_template (itemtemplateid,description,key_,delay,value_type)
	values (92,'Average number of bytes sent from interface eth0 (1min)','netloadout1[eth0]', 5, 0);
insert into items_template (itemtemplateid,description,key_,delay,value_type)
	values (93,'Average number of bytes sent from interface eth0 (5min)','netloadout5[eth0]', 10, 0);
insert into items_template (itemtemplateid,description,key_,delay,value_type)
	values (94,'Average number of bytes sent from interface eth0 (15min)','netloadout15[eth0]', 20, 0);
insert into items_template (itemtemplateid,description,key_,delay,value_type)
	values (95,'Average number of bytes sent from interface eth1 (1min)','netloadout1[eth1]', 5, 0);
insert into items_template (itemtemplateid,description,key_,delay,value_type)
	values (96,'Average number of bytes sent from interface eth1 (5min)','netloadout5[eth1]', 10, 0);
insert into items_template (itemtemplateid,description,key_,delay,value_type)
	values (97,'Average number of bytes sent from interface eth1 (15min)','netloadout15[eth1]', 20, 0);
