-- 
-- Zabbix
-- Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 2 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program; if not, write to the Free Software
-- Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
--

--
-- Dumping data for table `config`
--

INSERT INTO config VALUES (1,365,365,600,'1-5,00:00-24:00');

--
-- Dumping data for table `media_type`
--

INSERT INTO media_type VALUES (1,0,'Email','localhost','localhost','zabbix@localhost','','');

--
-- Dumping data for table `users`
--

INSERT INTO users VALUES (1,'Admin','Zabbix','Administrator','d41d8cd98f00b204e9800998ecf8427e',' ',900,'en_gb',30);
INSERT INTO users VALUES (2,'guest','Default','User','d41d8cd98f00b204e9800998ecf8427e',' ',900,'en_gb',30);

--
-- Dumping data for table `usrgrp`
--

INSERT INTO usrgrp VALUES (1,'UNIX administrators');
INSERT INTO usrgrp VALUES (2,'Database administrators');
INSERT INTO usrgrp VALUES (3,'Network administrators');
INSERT INTO usrgrp VALUES (4,'Security specialists');
INSERT INTO usrgrp VALUES (5,'WEB administrators');
INSERT INTO usrgrp VALUES (6,'Head of IT department');
INSERT INTO usrgrp VALUES (7,'Zabbix administrators');

--
-- Dumping data for table `rights`
--

INSERT INTO rights VALUES (1,1,'Default permission','U',0);
INSERT INTO rights VALUES (2,1,'Default permission','A',0);
INSERT INTO rights VALUES (3,2,'Default permission','R',0);

--
-- Dumping data for table `hosts`
--

INSERT INTO hosts VALUES (10001,'Unix_t',0,'',10000,3,0,'',0,0,0);
INSERT INTO hosts VALUES (10002,'Windows_t',0,'',10000,3,0,'',0,0,0);
INSERT INTO hosts VALUES (10004,'Standalone_t',0,'',10000,3,0,'',0,0,0);
INSERT INTO hosts VALUES (10003,'MySQL_t',0,'',10000,3,0,'',0,0,0);
INSERT INTO hosts VALUES (10007,'SNMP_t',0,'',161,3,0,'',0,0,0);

INSERT INTO hosts VALUES (10008,'Test',1,'127.0.0.1',10050,0,0,'',0,0,0);

--
-- Dumping data for table `groups`
--

INSERT INTO groups VALUES (1,'Templates');

--
-- Dumping data for table `hosts_groups`
--

INSERT INTO hosts_groups VALUES (1,10001,1);
INSERT INTO hosts_groups VALUES (2,10002,1);
INSERT INTO hosts_groups VALUES (3,10003,1);
INSERT INTO hosts_groups VALUES (4,10004,1);
INSERT INTO hosts_groups VALUES (5,10007,1);

INSERT INTO items VALUES (10001,0,'','',161,10008,'Free memory','vm.memory.size[free]',30,7,365,0,NULL,NULL,NULL,0,0,'','B',0,0,NULL,'',0,'','','0','',0,'',0,0);
INSERT INTO items VALUES (10002,0,'','',161,10008,'Free disk space on $1','vfs.fs.size[/,free]',30,7,365,0,NULL,NULL,NULL,0,0,'','B',0,0,NULL,'',0,'','','1','',0,'',0,0);
INSERT INTO `items` VALUES (10010,0,'','',161,10008,'Processor load','system.cpu.load[,avg1]',5,7,365,0,NULL,NULL,NULL,0,0,'','',0,0,NULL,'',0,'','','0','',0,'',0,0);

INSERT INTO functions VALUES (10010,10010,10010,NULL,'last','0');

INSERT INTO triggers VALUES (10010,'{10010}>2','Processor load is too high on {HOSTNAME}','',0,2,3,0,0,'','',0);

INSERT INTO `actions` VALUES (1,2,'{TRIGGER.NAME}: {STATUS}','{TRIGGER.NAME}: {STATUS}',1,0,600,0,0,0,'');

INSERT INTO `media` VALUES (1,1,1,'aaa',0,63,'1-7,00:00-23:59;');
