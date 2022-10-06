-- Activate Zabbix Server, set visible name and make it a more unique name
UPDATE hosts SET status=0,name='ЗАББИКС Сервер',host='Test host' WHERE host='Zabbix server';

-- More medias for user 'Admin'
INSERT INTO media (mediaid, userid, mediatypeid, sendto, active, severity, period) VALUES (1,1,1,'test@zabbix.com',0,63,'1-7,00:00-24:00');
INSERT INTO media (mediaid, userid, mediatypeid, sendto, active, severity, period) VALUES (2,1,1,'test2@zabbix.com',1,60,'1-7,00:00-24:00');
INSERT INTO media (mediaid, userid, mediatypeid, sendto, active, severity, period) VALUES (3,1,3,'123456789',0,32,'1-7,00:00-24:00');

-- More user scripts
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation) VALUES (4,'Reboot','/sbin/shutdown -r',3,7,4,'This command reboots server.','Do you really want to reboot it?');

-- Add proxies
INSERT INTO hosts (hostid, host, status, description) VALUES (20000, 'Active proxy 1', 5, '');
INSERT INTO hosts (hostid, host, status, description) VALUES (20001, 'Active proxy 2', 5, '');
INSERT INTO hosts (hostid, host, status, description) VALUES (20002, 'Active proxy 3', 5, '');
INSERT INTO hosts (hostid, host, status, description) VALUES (20003, 'Passive proxy 1', 6, '');
INSERT INTO hosts (hostid, host, status, description) VALUES (20004, 'Passive proxy 2', 6, '');
INSERT INTO hosts (hostid, host, status, description) VALUES (20005, 'Passive proxy 3', 6, '');
INSERT INTO hosts (hostid, host, status, description) VALUES (20010, 'Active proxy to delete', 5, '');
INSERT INTO hosts (hostid, host, status, description) VALUES (20011, 'Passive proxy to delete', 6, '');

INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) VALUES (10018,20003,1,0,1,'127.0.0.1','proxy1.zabbix.com','10051');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) VALUES (10019,20004,1,0,1,'127.0.0.1','proxy2.zabbix.com','10333');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) VALUES (10020,20005,1,0,0,'127.0.0.1','proxy3.zabbix.com','10051');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) VALUES (10030,10084,1,4,1,'127.0.0.1','jmxagent.zabbix.com','10051');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) VALUES (10040,20011,1,0,0,'127.0.0.1','proxy4.zabbix.com','10051');

-- create an empty host "Template linkage test host"
INSERT INTO hosts (hostid, host, name, status, description) VALUES (10053, 'Template linkage test host', 'Visible host for template linkage', 0, '');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) VALUES (10021,10053,1,1,1,'127.0.0.1','','10050');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (10022,10053,1,2,1,'127.0.0.1','','161');
INSERT INTO interface_snmp (interfaceid, version, bulk, community) values (10022, 2, 1, '{$SNMP_COMMUNITY}');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (10023,10053,1,3,1,'127.0.0.1','','623');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (10024,10053,1,4,1,'127.0.0.1','','12345');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (90278, 10053, 4);

-- Add regular expressions
INSERT INTO regexps (regexpid, name, test_string) VALUES (20,'1_regexp_1','first test string');
INSERT INTO regexps (regexpid, name, test_string) VALUES (21,'1_regexp_2','first test string');
INSERT INTO regexps (regexpid, name, test_string) VALUES (22,'2_regexp_1','second test string');
INSERT INTO regexps (regexpid, name, test_string) VALUES (23,'2_regexp_2','second test string');
INSERT INTO regexps (regexpid, name, test_string) VALUES (24,'3_regexp_1','test');
INSERT INTO regexps (regexpid, name, test_string) VALUES (25,'3_regexp_2','test');
INSERT INTO regexps (regexpid, name, test_string) VALUES (26,'4_regexp_1','abcd');
INSERT INTO regexps (regexpid, name, test_string) VALUES (27,'4_regexp_2','abcd');
INSERT INTO regexps (regexpid, name, test_string) VALUES (28,'5_regexp_1','abcd');
INSERT INTO regexps (regexpid, name, test_string) VALUES (29,'5_regexp_2','abcd');

-- Add expressions for regexps
INSERT INTO expressions (expressionid,regexpid,expression,expression_type,exp_delimiter,case_sensitive) VALUES (20,20,'first test string',0,',',1);
INSERT INTO expressions (expressionid,regexpid,expression,expression_type,exp_delimiter,case_sensitive) VALUES (21,21,'first test string2',0,',',1);
INSERT INTO expressions (expressionid,regexpid,expression,expression_type,exp_delimiter,case_sensitive) VALUES (22,22,'second test string',1,',',1);
INSERT INTO expressions (expressionid,regexpid,expression,expression_type,exp_delimiter,case_sensitive) VALUES (23,23,'second string',1,',',1);
INSERT INTO expressions (expressionid,regexpid,expression,expression_type,exp_delimiter,case_sensitive) VALUES (24,24,'abcd test',2,',',1);
INSERT INTO expressions (expressionid,regexpid,expression,expression_type,exp_delimiter,case_sensitive) VALUES (25,25,'test',2,',',1);
INSERT INTO expressions (expressionid,regexpid,expression,expression_type,exp_delimiter,case_sensitive) VALUES (26,26,'abcd',3,',',1);
INSERT INTO expressions (expressionid,regexpid,expression,expression_type,exp_delimiter,case_sensitive) VALUES (27,27,'asdf',3,',',1);
INSERT INTO expressions (expressionid,regexpid,expression,expression_type,exp_delimiter,case_sensitive) VALUES (28,28,'abcd',4,',',1);
INSERT INTO expressions (expressionid,regexpid,expression,expression_type,exp_delimiter,case_sensitive) VALUES (29,29,'asdf',4,',',1);

-- trigger actions
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period) VALUES (10,'Simple action',0,0,0,'60s');
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period) VALUES (11,'Trigger action 1',0,0,0,'1h');
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period) VALUES (12,'Trigger action 2',0,0,0,'60s');
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period) VALUES (13,'Trigger action 3',0,0,0,'60s');
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period) VALUES (14,'Trigger action 4',0,0,1,'60s');

-- autoregistration actions
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period) VALUES (9,'Autoregistration action 1',2,0,0,'1h');
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period) VALUES (15,'Autoregistration action 2',2,0,1,'1h');

INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (500, 9, 22, 3, 'DB2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (501, 9, 22, 2, 'MySQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (502, 9, 20, 1, '20001');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (503, 9, 20, 0, '20000');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (504, 10, 16, 11, '');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (505, 11, 16, 11, '');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (507, 12, 16, 10, '');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (508, 12, 15, 3, 'PostgreSQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (509, 12, 15, 2, 'MYSQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (510, 12, 15, 0, 'MySQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (511, 12, 13, 1, '10081');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (512, 12, 13, 0, '10001');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (513, 12, 6, 7, '6-7,08:00-18:00');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (514, 12, 6, 4, '1-7,00:00-24:00');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (517, 12, 4, 6, '4');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (518, 12, 4, 5, '3');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (519, 12, 4, 1, '2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (520, 12, 4, 0, '5');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (521, 12, 4, 0, '1');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (522, 12, 3, 3, 'DB2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (523, 12, 3, 2, 'Oracle');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (524, 12, 2, 1, '18704');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (525, 12, 2, 0, '16054');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (526, 12, 1, 1, '10084');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (527, 12, 1, 0, '10084');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (528, 12, 0, 1, '4');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (529, 12, 0, 0, '2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (530, 13, 16, 11, '');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (532, 13, 15, 3, 'PostgreSQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (533, 13, 15, 2, 'MYSQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (534, 13, 15, 0, 'MySQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (535, 13, 13, 1, '10081');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (536, 13, 13, 0, '10001');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (537, 13, 6, 7, '6-7,08:00-18:00');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (538, 13, 6, 4, '1-7,00:00-24:00');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (541, 13, 4, 6, '4');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (542, 13, 4, 5, '3');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (543, 13, 4, 1, '2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (544, 13, 4, 0, '5');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (545, 13, 4, 0, '1');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (546, 13, 3, 3, 'DB2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (547, 13, 3, 2, 'Oracle');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (548, 13, 2, 1, '18704');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (549, 13, 2, 0, '16054');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (550, 13, 1, 1, '10084');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (551, 13, 1, 0, '10084');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (552, 13, 0, 1, '4');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (553, 13, 0, 0, '2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (554, 14, 16, 11, '');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (556, 14, 15, 3, 'PostgreSQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (557, 14, 15, 2, 'MYSQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (558, 14, 15, 0, 'MySQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (559, 14, 13, 1, '10081');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (560, 14, 13, 0, '10001');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (561, 14, 6, 7, '6-7,08:00-18:00');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (562, 14, 6, 4, '1-7,00:00-24:00');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (565, 14, 4, 6, '4');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (566, 14, 4, 5, '3');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (567, 14, 4, 1, '2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (568, 14, 4, 0, '5');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (569, 14, 4, 0, '1');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (570, 14, 3, 3, 'DB2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (571, 14, 3, 2, 'Oracle');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (572, 14, 2, 1, '18704');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (573, 14, 2, 0, '16054');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (574, 14, 1, 1, '10084');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (575, 14, 1, 0, '10084');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (576, 14, 0, 1, '4');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (577, 14, 0, 0, '2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (578, 15, 22, 3, 'DB2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (579, 15, 22, 2, 'MySQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (580, 15, 20, 1, '20001');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (581, 15, 20, 0, '20000');

INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (11, 10, 0, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (12, 11, 0, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (13, 12, 0, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (14, 13, 0, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (15, 13, 0, 3600, 2, 2, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (16, 13, 0, 0, 5, 6, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (17, 14, 0, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (18, 14, 0, 3600, 2, 2, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (19, 14, 0, 0, 5, 6, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (20, 14, 1, 0, 20, 0, 0);

INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (11, 1, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', NULL);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (12, 1, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', NULL);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (13, 1, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', NULL);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (14, 1, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', NULL);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (15, 1, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', 1);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (16, 0, 'Custom: {TRIGGER.NAME}: {TRIGGER.STATUS}', 'Custom: {TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', 1);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (17, 1, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', NULL);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (18, 1, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', 1);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (19, 0, 'Custom: {TRIGGER.NAME}: {TRIGGER.STATUS}', 'Custom: {TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', 1);

INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (10, 11, 7);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (11, 12, 7);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (12, 13, 7);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (13, 14, 7);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (14, 15, 7);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (15, 17, 7);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (16, 18, 7);

INSERT INTO opmessage_usr (opmessage_usrid, operationid, userid) VALUES (2, 16, 1);
INSERT INTO opmessage_usr (opmessage_usrid, operationid, userid) VALUES (3, 19, 1);

INSERT INTO opcommand (operationid, type, scriptid, execute_on, port, authtype, username, password, publickey, privatekey, command) VALUES (20, 0, NULL, 0, '', 0, '', '', '', '', '/sbin/shutdown -r');

INSERT INTO opcommand_hst (opcommand_hstid, operationid, hostid) VALUES (1, 20, NULL);

INSERT INTO opconditions (opconditionid, operationid, conditiontype, operator, value) VALUES (1,15,14,0,'0');
INSERT INTO opconditions (opconditionid, operationid, conditiontype, operator, value) VALUES (2,15,14,0,'1');
INSERT INTO opconditions (opconditionid, operationid, conditiontype, operator, value) VALUES (3,16,14,0,'0');
INSERT INTO opconditions (opconditionid, operationid, conditiontype, operator, value) VALUES (4,18,14,0,'0');
INSERT INTO opconditions (opconditionid, operationid, conditiontype, operator, value) VALUES (5,18,14,0,'1');
INSERT INTO opconditions (opconditionid, operationid, conditiontype, operator, value) VALUES (6,19,14,0,'0');
INSERT INTO opconditions (opconditionid, operationid, conditiontype, operator, value) VALUES (7,20,14,0,'0');

INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (21, 9, 0, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (22, 9, 0, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (23, 9, 1, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (24, 9, 2, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (25, 9, 9, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (26, 9, 4, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (27, 9, 6, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (28, 15, 0, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (29, 15, 0, 0, 1, 1, 0);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (30, 15, 1, 0, 1, 1, 0);

INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (21, 0, 'Special: {TRIGGER.NAME}: {TRIGGER.STATUS}', 'Special: {TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', NULL);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (22, 1, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', NULL);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (28, 0, 'Special: {TRIGGER.NAME}: {TRIGGER.STATUS}', 'Special: {TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', NULL);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (29, 1, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', NULL);

INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (17, 21, 7);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (18, 22, 7);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (19, 28, 7);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (20, 29, 7);

INSERT INTO opcommand (operationid, type, command) VALUES (23, 0, 'echo TEST');
INSERT INTO opcommand (operationid, type, command) VALUES (30, 0, 'echo TEST');

INSERT INTO opcommand_hst (opcommand_hstid, operationid, hostid) VALUES (2, 23, NULL);
INSERT INTO opcommand_hst (opcommand_hstid, operationid, hostid) VALUES (3, 30, NULL);

INSERT INTO opgroup (opgroupid, operationid, groupid) VALUES (3, 26, 5);

INSERT INTO optemplate (optemplateid, operationid, templateid) VALUES (3, 27, 10001);

-- Add test graph
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (200000,'Test graph 1',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,0);

-- Add graph items
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (200000, 200000, 29155, 1, 1, 'FF5555', 0, 2, 0);

-- Add more screens
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200000, 'Test screen (graph)'                          , 1, 1, NULL, 1, 0);
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200001, 'Test screen (clock)'                          , 1, 1, NULL, 1, 0);
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200002, 'Test screen (data overview, left align)'      , 1, 1, NULL, 1, 0);
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200003, 'Test screen (history of actions)'             , 1, 1, NULL, 1, 0);
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200004, 'Test screen (history of events)'              , 1, 1, NULL, 1, 0);
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200005, 'Test screen (hosts info, horizontal align)'   , 1, 1, NULL, 1, 0);
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200006, 'Test screen (hosts info, vertical align)'     , 1, 1, NULL, 1, 0);
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200007, 'Test screen (map)'                            , 1, 1, NULL, 1, 0);
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200008, 'Test screen (plain text)'                     , 1, 1, NULL, 1, 0);
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200010, 'Test screen (server info)'                    , 1, 1, NULL, 1, 0);
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200011, 'Test screen (simple graph)'                   , 1, 1, NULL, 1, 0);
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200012, 'Test screen (status of hostgroup triggers)'   , 1, 1, NULL, 1, 0);
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200013, 'Test screen (status of host triggers)'        , 1, 1, NULL, 1, 0);
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200014, 'Test screen (system status)'                  , 1, 1, NULL, 1, 0);
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200015, 'Test screen (triggers info, horizontal align)', 1, 1, NULL, 1, 0);
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200016, 'Test screen (triggers overview, left align)'  , 1, 1, NULL, 1, 0);
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200017, 'Test screen (triggers overview, top align)'   , 1, 1, NULL, 1, 0);
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200018, 'Test screen (url)'                            , 1, 1, NULL, 1, 0);
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200019, 'Test screen (data overview, top align)'       , 1, 1, NULL, 1, 0);
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200020, 'Test screen (triggers info, vertical align)'  , 1, 1, NULL, 1, 0);

INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200000,200000,0,200000,500,100,0,0,0,0,0,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200001,200001,7,0,500,100,0,0,0,0,0,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200002,200002,10,4,500,100,0,0,0,0,0,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200003,200003,12,0,500,100,0,0,0,0,25,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200004,200004,13,0,500,100,0,0,0,0,25,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200005,200005,4,4,500,100,0,0,0,0,0,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200006,200006,4,4,500,100,0,0,0,0,0,0,0,1,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200007,200007,2,3,500,100,0,0,1,1,0,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200008,200008,3,29187,500,100,0,0,0,0,25,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200010,200010,6,0,500,100,0,0,0,0,0,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200011,200011,1,29141,500,100,0,0,0,0,0,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200012,200012,14,2,500,100,0,0,0,0,25,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200013,200013,16,10084,500,100,0,0,0,0,25,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200014,200014,15,0,500,100,0,0,0,0,0,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200015,200015,5,4,500,100,0,0,0,0,0,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200016,200016,9,4,500,100,0,0,0,0,0,0,0,0,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200017,200017,9,4,500,100,0,0,0,0,0,0,0,1,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200018,200018,11,0,500,500,0,0,0,0,0,0,0,0,'http://www.google.com',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200019,200019,10,4,500,100,0,0,0,0,0,0,0,1,'',0,0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200020,200020,5,4,500,100,0,0,0,0,0,0,0,1,'',0,0);

-- Add slide shows
INSERT INTO slideshows (slideshowid, name, delay, userid, private) VALUES (200001, 'Test slide show 1', 10, 1, 0);
INSERT INTO slideshows (slideshowid, name, delay, userid, private) VALUES (200002, 'Test slide show 2', 10, 1, 0);
INSERT INTO slideshows (slideshowid, name, delay, userid, private) VALUES (200003, 'Test slide show 3', 900, 1, 0);

INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (200000, 200001, 200000, 0, 0);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (200001, 200001, 200001, 1, 0);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (200003, 200002, 200002, 0, 0);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (200004, 200002, 200003, 1, 0);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (200005, 200002, 200004, 2, 15);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (200006, 200002, 200005, 3, 20);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (200007, 200003, 200007, 0, 0);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (200009, 200003, 200016, 1, 15);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (200010, 200003, 200019, 2, 20);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (200011, 200003, 200020, 3, 60);

-- Add maintenance periods
INSERT INTO maintenances (maintenanceid, name, maintenance_type, description, active_since, active_till,tags_evaltype) VALUES (1,'Maintenance period 1 (data collection)',0,'Test description 1',1294760280,1294846680,0);
INSERT INTO maintenances (maintenanceid, name, maintenance_type, description, active_since, active_till,tags_evaltype) VALUES (2,'Maintenance period 2 (no data collection)',1,'Test description 1',1294760280,1294846680,0);
INSERT INTO maintenances (maintenanceid, name, maintenance_type, description, active_since, active_till,tags_evaltype) VALUES (3,'Maintenance for update (data collection)',0,'Test description',1534885200,1534971600,2);

INSERT INTO maintenances_hosts (maintenance_hostid, maintenanceid, hostid) VALUES (1,1,20000);
INSERT INTO maintenances_hosts (maintenance_hostid, maintenanceid, hostid) VALUES (2,2,20000);

INSERT INTO maintenances_groups (maintenance_groupid, maintenanceid, groupid) VALUES (1,1,4);
INSERT INTO maintenances_groups (maintenance_groupid, maintenanceid, groupid) VALUES (2,2,4);
INSERT INTO maintenances_groups (maintenance_groupid, maintenanceid, groupid) VALUES (3,3,4);

INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (1,0,1,0,0,1,43200,184200,1294760340);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (2,2,2,0,0,1,43200,93780,0);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (3,3,2,0,85,1,85800,300,0);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (4,4,1,1365,0,15,37500,183840,0);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (5,4,1,2730,85,0,84600,1800,0);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (6,0,1,0,0,1,43200,184200,1294760340);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (7,2,2,0,0,1,43200,93780,0);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (8,3,2,0,85,1,85800,300,0);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (9,4,1,1365,0,15,37500,183840,0);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (10,4,1,2730,85,0,84600,1800,0);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (11,0,1,0,0,1,43200,90000,1534950000);

INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (1,1,1);
INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (2,1,2);
INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (3,1,3);
INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (4,1,4);
INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (5,1,5);
INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (6,2,6);
INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (7,2,7);
INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (8,2,8);
INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (9,2,9);
INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (10,2,10);
INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (11,3,11);

INSERT INTO maintenance_tag (maintenancetagid, maintenanceid, tag, operator,value) VALUES (1,3,'Tag1',2,'A');
INSERT INTO maintenance_tag (maintenancetagid, maintenanceid, tag, operator,value) VALUES (2,3,'Tag2',0,'B');

-- Add maps
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, userid, private) VALUES (3, 'Test map 1', 800, 600, NULL, 0, 0, 1, 1, 1, 2, 1, 0);

INSERT INTO sysmaps_elements (selementid, sysmapid, elementid, elementtype, iconid_off, iconid_on, label, label_location, x, y, iconid_disabled, iconid_maintenance) VALUES (3,3,0,4,7,NULL,'Test phone icon',0,151,101,NULL,NULL);
INSERT INTO sysmaps_elements (selementid, sysmapid, elementid, elementtype, iconid_off, iconid_on, label, label_location, x, y, iconid_disabled, iconid_maintenance) VALUES (4,3,1,1,3,NULL,'Map element (Local network)',0,401,101,NULL,NULL);
INSERT INTO sysmaps_elements (selementid, sysmapid, elementid, elementtype, iconid_off, iconid_on, label, label_location, x, y, iconid_disabled, iconid_maintenance) VALUES (5,3,0,2,15,NULL,'Trigger element (CPU load)',0,101,301,NULL,NULL);
INSERT INTO sysmaps_elements (selementid, sysmapid, elementid, elementtype, iconid_off, iconid_on, label, label_location, x, y, iconid_disabled, iconid_maintenance) VALUES (6,3,2,3,1,NULL,'Host group element (Linux servers)',0,301,351,NULL,NULL);
INSERT INTO sysmaps_elements (selementid, sysmapid, elementid, elementtype, iconid_off, iconid_on, label, label_location, x, y, iconid_disabled, iconid_maintenance) VALUES (7,3,10084,0,19,NULL,'Host element (Zabbix Server)',0,501,301,NULL,NULL);

INSERT INTO sysmap_element_trigger (selement_triggerid, selementid, triggerid) VALUES (1,5,18707);

INSERT INTO sysmaps_links (linkid, sysmapid, selementid1, selementid2, drawtype, color, label) VALUES (1,3,3,4,2,'00CC00','CPU load: {Zabbix Server:system.cpu.load[].last(0)}');
INSERT INTO sysmaps_links (linkid, sysmapid, selementid1, selementid2, drawtype, color, label) VALUES (2,3,3,5,0,'00CC00','');
INSERT INTO sysmaps_links (linkid, sysmapid, selementid1, selementid2, drawtype, color, label) VALUES (3,3,6,5,0,'00CC00','');
INSERT INTO sysmaps_links (linkid, sysmapid, selementid1, selementid2, drawtype, color, label) VALUES (4,3,7,6,0,'00CC00','');
INSERT INTO sysmaps_links (linkid, sysmapid, selementid1, selementid2, drawtype, color, label) VALUES (5,3,4,7,0,'00CC00','');
INSERT INTO sysmaps_links (linkid, sysmapid, selementid1, selementid2, drawtype, color, label) VALUES (6,3,4,5,0,'00CC00','');
INSERT INTO sysmaps_links (linkid, sysmapid, selementid1, selementid2, drawtype, color, label) VALUES (7,3,3,6,0,'00CC00','');
INSERT INTO sysmaps_links (linkid, sysmapid, selementid1, selementid2, drawtype, color, label) VALUES (8,3,7,3,0,'00CC00','');

INSERT INTO sysmaps_link_triggers (linktriggerid, linkid, triggerid, drawtype, color) VALUES (1,1,13545,4,'DD0000');

INSERT INTO sysmap_element_url (sysmapelementurlid, selementid, name, url) VALUES (1,4,'Zabbix home','http://www.zabbix.com');
INSERT INTO sysmap_element_url (sysmapelementurlid, selementid, name, url) VALUES (2,5,'www.wikipedia.org','http://www.wikipedia.org');
-- Add shapes
INSERT INTO sysmap_shape (sysmap_shapeid, sysmapid, type, x, y, width, height, text, font, font_size, font_color, text_halign, text_valign, border_type, border_width, border_color, background_color, zindex) VALUES (100,3,1,425,257,199,135,'',9,11,'000000',0,0,1,2,'000000','FFCCCC',0);
INSERT INTO sysmap_shape (sysmap_shapeid, sysmapid, type, x, y, width, height, text, font, font_size, font_color, text_halign, text_valign, border_type, border_width, border_color, background_color, zindex) VALUES (101,3,0,113,82,124,86,'',9,11,'000000',0,0,3,5,'009900','',1);
INSERT INTO sysmap_shape (sysmap_shapeid, sysmapid, type, x, y, width, height, text, font, font_size, font_color, text_halign, text_valign, border_type, border_width, border_color, background_color, zindex) VALUES (102,3,0,408,0,233,50,'Map name: {MAP.NAME}',10,14,'BB0000',1,2,0,2,'000000','',2);


-- Host inventories
INSERT INTO host_inventory (type,type_full,name,alias,os,os_full,os_short,serialno_a,serialno_b,tag,asset_tag,macaddress_a,macaddress_b,hardware,hardware_full,software,software_full,software_app_a,software_app_b,software_app_c,software_app_d,software_app_e,contact,location,location_lat,location_lon,notes,chassis,model,hw_arch,vendor,contract_number,installer_name,deployment_status,url_a,url_b,url_c,host_networks,host_netmask,host_router,oob_ip,oob_netmask,oob_router,date_hw_purchase,date_hw_install,date_hw_expiry,date_hw_decomm,site_address_a,site_address_b,site_address_c,site_city,site_state,site_country,site_zip,site_rack,site_notes,poc_1_name,poc_1_email,poc_1_phone_a,poc_1_phone_b,poc_1_cell,poc_1_screen,poc_1_notes,poc_2_name,poc_2_email,poc_2_phone_a,poc_2_phone_b,poc_2_cell,poc_2_screen,poc_2_notes,hostid) VALUES ('Type','Type (Full details)','Name','Alias','OS','OS (Full details)','OS (Short)','Serial number A','Serial number B','Tag','Asset tag','MAC address A','MAC address B','Hardware','Hardware (Full details)','Software','Software (Full details)','Software application A','Software application B','Software application C','Software application D','Software application E','Contact','Location','Location latitud','Location longitu','Notes','Chassis','Model','HW architecture','Vendor','Contract number','Installer name','Deployment status','URL A','URL B','URL C','Host networks','Host subnet mask','Host router','OOB IP address','OOB subnet mask','OOB router','Date HW purchased','Date HW installed','Date HW maintenance expires','Date hw decommissioned','Site address A','Site address B','Site address C','Site city','Site state / province','Site country','Site ZIP / postal','Site rack location','Site notes','Primary POC name','Primary POC email','Primary POC phone A','Primary POC phone B','Primary POC cell','Primary POC screen name','Primary POC notes','Secondary POC name','Secondary POC email','Secondary POC phone A','Secondary POC phone B','Secondary POC cell','Secondary POC screen name','Secondary POC notes',10053);

-- delete Discovery Rule
INSERT INTO items (itemid, name, type, hostid, description, key_, delay, history, trends, status, value_type, trapper_hosts, units, logtimefmt, templateid, valuemapid, params, ipmi_sensor, authtype, username, password, publickey, privatekey, flags, interfaceid, posts, headers) VALUES (22188, 'delete Discovery Rule', 0, 10053, 'rule', 'key', '30s', '90d', '365d', 0, 0, '', '', '', NULL, NULL, '', '', 0, '', '', '', '', 1, 10021, '', '');

-- add some test items
-- first, one that references a non-existent user macro in the key and then references that key parameter in the item name using a positional reference
INSERT INTO items (itemid, name, type, hostid, description, key_, delay, history, trends, status, value_type, trapper_hosts, units, logtimefmt, templateid, valuemapid, params, ipmi_sensor, authtype, username, password, publickey, privatekey, flags, interfaceid, posts, headers) VALUES (23100, 'Item_referencing_a_non-existent_user_macro', 0, 10053, 'a. i am referencing a non-existent user macro $1', 'key[{$I_DONT_EXIST}]', '30s', '90d', '365d', 0, 0, '', '', '', NULL, NULL, '', '', 0, '', '', '', '', 0, 10021, '', '');
INSERT INTO items (itemid, name, type, hostid, description, key_, delay, history, trends, status, value_type, trapper_hosts, units, logtimefmt, templateid, valuemapid, params, ipmi_sensor, authtype, username, password, publickey, privatekey, flags, interfaceid, inventory_link, posts, headers) VALUES (23101, 'Item_populating_filed_Type', 0, 10053, 'i am populating filed Type', 'key.test.pop.type', '30s', '90d', '365d', 0, 0, '', '', '', NULL, NULL, '', '', 0, '', '', '', '', 0, 10021, 1, '', '');

-- test discovery rule
INSERT INTO drules (druleid, proxy_hostid, name, iprange, delay, nextcheck, status) VALUES (3, NULL, 'External network', '192.168.3.1-255', 600, 0, 0);

INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (6, 3, 9, 'system.uname', '', '10050', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (7, 3, 3, '', '', '21,1021', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (8, 3, 4, '', '', '80,8080', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (9, 3, 14, '', '', '443', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (10, 3, 12, '', '', '0', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (11, 3, 7, '', '', '143-145', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (12, 3, 1, '', '', '389', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (13, 3, 6, '', '', '119', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (14, 3, 5, '', '', '110', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (15, 3, 2, '', '', '25', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (16, 3, 10, 'ifIndex0', 'public', '161', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (17, 3, 11, 'ifInOut0', 'private1', '162', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (18, 3, 13, 'ifIn0', '', '161', 'private2', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (19, 3, 0, '', '', '22', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (20, 3, 8, '', '', '10000-20000', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (21, 3, 15, '', '', '23', '', 0, '', '', 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (22, 3, 9, 'agent.uname', '', '10050', '', 0, '', '', 0);

INSERT INTO drules (druleid, proxy_hostid, name, iprange, delay, nextcheck, status) VALUES (4, 20003, 'Discovery rule for update', '192.14.3.1-255', 600, 0, 0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (23, 4, 12, '', '', '0', '', 0, '', '', 0);
INSERT INTO drules (druleid, proxy_hostid, name, iprange, delay, nextcheck, status) VALUES (5, 20003, 'Disabled discovery rule for update', '192.15.3.1-255', 600, 0, 1);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (24, 5, 12, '', '', '0', '', 0, '', '', 0);
INSERT INTO drules (druleid, proxy_hostid, name, iprange, delay, nextcheck, status) VALUES (6, 20003, 'Discovery rule to check delete', '192.16.3.1-255', 600, 0, 1);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq) VALUES (25, 6, 12, '', '', '0', '', 0, '', '', 0);

-- Global macros
INSERT INTO globalmacro (globalmacroid, macro, value, description) VALUES (6,'{$DEFAULT_DELAY}','30','');
INSERT INTO globalmacro (globalmacroid, macro, value, description) VALUES (7,'{$LOCALIP}','127.0.0.1','Test description 2');
INSERT INTO globalmacro (globalmacroid, macro, value, description) VALUES (8,'{$DEFAULT_LINUX_IF}','eth0','');
INSERT INTO globalmacro (globalmacroid, macro, value, description) VALUES (9,'{$0123456789012345678901234567890123456789012345678901234567890}','012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789012345678901234','');
INSERT INTO globalmacro (globalmacroid, macro, value, description) VALUES (10,'{$A}','Some text','');
INSERT INTO globalmacro (globalmacroid, macro, value, description) VALUES (11,'{$1}','Numeric macro','Test description 1');
INSERT INTO globalmacro (globalmacroid, macro, value, description) VALUES (12,'{$_}','Underscore','');
INSERT INTO globalmacro (globalmacroid, macro, value, description) VALUES (13,'{$WORKING_HOURS}','1-5,09:00-18:00','Test description 3');
INSERT INTO globalmacro (globalmacroid, macro, value, description, type) VALUES (14,'{$X_SECRET_2_SECRET}','This text should stay secret','This text should stay secret', 1);
INSERT INTO globalmacro (globalmacroid, macro, value, description, type) VALUES (15,'{$X_TEXT_2_SECRET}','This text should become secret','This text should become secret', 0);
INSERT INTO globalmacro (globalmacroid, macro, value, description, type) VALUES (16,'{$X_SECRET_2_TEXT}','This text should become visible','This text should become visible', 1);
INSERT INTO globalmacro (globalmacroid, macro, value, description, type) VALUES (17,'{$Y_SECRET_MACRO_REVERT}','Changes value and revert','' , 1);
INSERT INTO globalmacro (globalmacroid, macro, value, description, type) VALUES (18,'{$Y_SECRET_MACRO_2_TEXT_REVERT}','Change value and type and revert','' , 1);
INSERT INTO globalmacro (globalmacroid, macro, value, description, type) VALUES (19,'{$Z_GLOBAL_MACRO_2_RESOLVE}','Value 2 B resolved','' , 0);

-- Adding records into Auditlog

-- add user
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid) VALUES (500, 1, 1411543800, 0, 0, 'User alias [Admin] name [Admin] surname [Admin]', '192.168.3.38', 0);
-- update user
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid) VALUES (501, 1, 1411543800, 1, 0, 'User alias [Admin2] name [Admin2] surname [Admin2]', '192.168.3.38', 0);
-- delete user
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid) VALUES (502, 1, 1411543800, 2, 0, 'User alias [Admin2] name [Admin2] surname [Admin2]', '192.168.3.38', 0);
-- can check also block user (enable,disable)

-- add host
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (503, 1, 1411543800, 0, 4, '0', '192.168.3.32', 10054, 'H1');

-- update host
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (504, 1, 1411543800, 1, 4, '0', '192.168.3.32', 10054, 'H1 updated');

-- delete host
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (505, 1, 1411543800, 2, 4, '0', '192.168.3.32', 10054, 'H1 updated');

-- enable host, hosts.status: 1 => 0
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (506, 1, 1411543800, 1, 4, '0', '192.168.3.32', 10054, 'H1 updated');
INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (500, 506, 'hosts', 'status', '1', '0');

-- disable host, hosts.status: 0 => 1
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (507, 1, 1411543800, 1, 4, '0', '192.168.3.32', 10054, 'H1 updated');
INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (501, 507, 'hosts', 'status', '0', '1');

-- add hostgroup
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (508, 1, 1411543800, 0, 14, '0', '192.168.3.32', 6, 'HG1');

-- update hostgroup
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (509, 1, 1411543800, 1, 14, '0', '192.168.3.32', 6, 'HG1 updated');
INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (502, 509, 'groups', 'name', 'HG1', 'HG1 updated');

-- delete hostgroup
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (510, 1, 1411543800, 2, 14, '0', '192.168.3.32', 6, 'HG1 updated');

-- add item
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (511, 1, 1411543800, 0, 15, '0', '192.168.3.32', 22500, 'Item added');

-- update item
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (512, 1, 1411543800, 1, 15, '0', '192.168.3.32', 22500, 'Item updated');

-- disable item
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (513, 1, 1411543800, 1, 15, '0', '192.168.3.32', 22500, 'H1 updated:test_item');
INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (503, 513, 'items', 'status', '0', '1');

-- enable item
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (514, 1, 1411543800, 1, 15, '0', '192.168.3.32', 22500, 'H1 updated:test_item');
INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (504, 514, 'items', 'status', '1', '0');

-- delete item
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (515, 1, 1411543800, 2, 15, 'Item [agent.version] [22500] Host [H1]', '192.168.3.32', 22500, 'Item deleted');

-- add trigger
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (516, 1, 1411543800, 0, 13, '0', '192.168.3.32', 13000, 'Trigger1');

-- update trigger
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (517, 1, 1411543800, 0, 13, '0', '192.168.3.32', 13000, 'Trigger1');
INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (505, 517, '', 'description', 'Trigger1', 'Trigger1 updated');

-- disable trigger
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (518, 1, 1411543800, 1, 13, '0', '192.168.3.32', 13000, 'H1 updated:Trigger1');
INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (506, 518, 'triggers', 'status', '0', '1');

-- enable trigger
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (519, 1, 1411543800, 1, 13, '0', '192.168.3.32', 13000, 'H1 updated:Trigger1');
INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (507, 519, 'triggers', 'status', '1', '0');

-- TODO: delete trigger

-- add action
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (520, 1, 1411543800, 0, 5, 'Name: Action1', '192.168.3.32', 0, '');

-- update action
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (521, 1, 1411543800, 1, 5, 'Name: Action1 updated', '192.168.3.32', 0, '');

-- disable action
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (522, 1, 1411543800, 1, 5, 'Actions [11] disabled', '192.168.3.32', 0, '');

-- enable action
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (523, 1, 1411543800, 1, 5, 'Actions [11] enabled', '192.168.3.32', 0, '');

-- delete action
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (524, 1, 1411543800, 2, 5, 'Actions [11] deleted', '192.168.3.32', 11, 'Action deleted');

-- add application
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (525, 1, 1411543800, 0, 12, 'Application [App1 ] [177]', '192.168.3.32', 0, '');

-- update application
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (526, 1, 1411543800, 1, 12, 'Application [App1 updated ] []', '192.168.3.32', 0, '');

-- disable application  (work in the same way as update app- disable all items on this host), such records do not exist at this moment
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (527, 1, 1411543800, 1, 12, '0', '192.168.3.32', 22165, 'test_item');
INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (508, 527, 'items', 'status', '0', '1');

-- enable application (work in the same way as update app- disable all items on this host), such records do not exist at this moment
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (528, 1, 1411543800, 1, 12, '0', '192.168.3.32', 22165, 'test_item');
INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (509, 528, 'items', 'status', '1', '0');

-- delete application
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (529, 1, 1411543800, 2, 12, 'Application [App1] from host [H1]', '192.168.3.32', 0, '');

-- add graph
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (530, 1, 1411543800, 0, 6, 'Graph [graph1]', '192.168.3.32', 0, '');

-- update graph
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (531, 1, 1411543800, 1, 6, 'Graph [graph1 updated]', '192.168.3.32', 0, '');

-- delete graph, no records in the DB for this operation
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (532, 1, 1411543800, 2, 6, 'Graph ID [386] Graph [graph1]', '192.168.3.32', 0, '');

-- add image
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (533, 1, 1411543800, 0, 16, 'Image [1image] added', '192.168.3.32', 0, '');

-- update image
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (534, 1, 1411543800, 1, 16, 'Image [1image] updated', '192.168.3.32', 0, '');

-- delete image
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (535, 1, 1411543800, 2, 16, 'Image [1image] updated', '192.168.3.32', 0, '');

-- add globalmacro
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (536, 1, 1411543800, 0, 29, '0', '192.168.3.32', 9, '{$B}&nbsp;&rArr;&nbsp;abcd');

-- update globalmacro
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (537, 1, 1411543800, 1, 29, '0', '192.168.3.32', 9, '{$B}&nbsp;&rArr;&nbsp;xyz');

-- delete globalmacro
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (538, 1, 1411543800, 2, 29, '0', '192.168.3.32', 9, 'Array&nbsp;&rArr;&nbsp;xyz');

-- add valuemap
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (539, 1, 1411543800, 0, 17, 'Value map [testvaluemap1]', '192.168.3.32', 0, '');

-- update valuemap
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (540, 1, 1411543800, 1, 17, '0', '192.168.3.32', 0, '');

-- delete valuemap
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (541, 1, 1411543800, 2, 17, '0', '192.168.3.32', 0, '');

-- add maint period
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (542, 1, 1411543800, 0, 27, 'Name: Maintenance1', '192.168.3.32', 0, '');

-- update maint period
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (543, 1, 1411543800, 1, 27, 'Name: Maintenance2', '192.168.3.32', 0, '');

-- delete maint period
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (544, 1, 1411543800, 2, 27, 'Id [3] Name [Maintenance2]', '192.168.3.32', 0, '');

-- add service
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (545, 1, 1411543800, 0, 18, 'Name [service1] id [1]', '192.168.3.32', 0, '');

-- update service
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (546, 1, 1411543800, 1, 18, 'Name [service1] id [1]', '192.168.3.32', 0, '');

-- delete service
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (547, 1, 1411543800, 2, 18, 'Name [service1] id [1]', '192.168.3.32', 0, '');

-- add DRule
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (548, 1, 1411543800, 0, 23, '[10] drule1', '192.168.3.32', 0, '');

-- update DRule
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (549, 1, 1411543800, 1, 23, '[10] drule1-new', '192.168.3.32', 0, '');

-- delete DRule
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (550, 1, 1411543800, 2, 23, 'Discovery rule [10] drule1-new deleted', '192.168.3.32', 0, '');

-- disable DRule
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (551, 1, 1411543800, 1, 23, 'Discovery rule [10] disabled', '192.168.3.32', 0, '');

-- enable DRule
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (552, 1, 1411543800, 1, 23, 'Discovery rule [10] enabled', '192.168.3.32', 0, '');

-- add map
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (553, 1, 1411543800, 0, 19, 'Test Map1', '192.168.3.32', 20, '');

-- update map
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (554, 1, 1411543800, 1, 19, 'Test Map2', '192.168.3.32', 20, '');

-- delete map
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (555, 1, 1411543800, 2, 19, '0', '192.168.3.32', 20, 'Test Map2');

-- add media type
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (556, 1, 1411543800, 0, 3, 'Media type [Media1]', '192.168.3.32', 0, '');

-- update media type
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (557, 1, 1411543800, 1, 3, 'Media type [Media2]', '192.168.3.32', 0, '');

-- disable media type
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (558, 1, 1411543800, 1, 3, 'Media type [Media2]', '192.168.3.32', 0, '');

-- enable media type
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (559, 1, 1411543800, 1, 3, 'Media type [Media2]', '192.168.3.32', 0, '');

-- delete media type
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (560, 1, 1411543800, 2, 3, 'Media type [Media2]', '192.168.3.32', 0, '');

-- add proxy
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (564, 1, 1411543800, 0, 26, '[test_proxy1] [10054]', '192.168.3.32', 0, '');

-- update proxy
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (565, 1, 1411543800, 1, 26, '[test_proxy2] [10054]', '192.168.3.32', 0, '');

-- disable proxy - this will disable all hosts that are monitored by this proxy
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (566, 1, 1411543800, 1, 4, '0', '192.168.3.32', 10053, 'Test host');
INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (510, 566, 'hosts', 'status', '0', '1');

-- enable proxy - this will enable all hosts that are monitored by this proxy
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (567, 1, 1411543800, 1, 4, '0', '192.168.3.32', 10053, 'Test host');
INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (511, 567, 'hosts', 'status', '1', '0');

-- delete proxy
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (568, 1, 1411543800, 1, 4, '0', '192.168.3.32', 10053, 'Test host');

-- add web scenario
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (569, 1, 1411543800, 0, 22, 'Web scenario [Scenario1] [1] Host [Test host]', '192.168.3.32', 0, '');

-- update web scenario
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (570, 1, 1411543800, 1, 22, 'Web scenario [Scenario1] [1] Host [Test host]', '192.168.3.32', 0, '');

-- disable scenario
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (571, 1, 1411543800, 6, 22, 'Web scenario [Scenario1] [1] Host [Test host] disabled', '192.168.3.32', 0, '');

-- enable scenario
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (572, 1, 1411543800, 5, 22, 'Web scenario [Scenario1] [1] Host [Test host] activated', '192.168.3.32', 0, '');

-- delete scenario
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (573, 1, 1411543800, 2, 22, 'Web scenario [Scenario1] [1] Host [Test host]', '192.168.3.32', 0, '');

-- add screen
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (574, 1, 1411543800, 0, 20, 'Name [screen1]', '192.168.3.32', 0, '');

-- update screen
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (575, 1, 1411543800, 1, 20, 'Name [screen1]', '192.168.3.32', 0, '');

-- delete screen
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (576, 1, 1411543800, 2, 20, '0', '192.168.3.32', 24, 'screen1');

-- add script
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (577, 1, 1411543800, 0, 25, 'Name [script1] id [4]', '192.168.3.32', 0, '');

-- update script
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (578, 1, 1411543800, 1, 25, 'Name [script1] id [4]', '192.168.3.32', 0, '');

-- delete script
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (579, 1, 1411543800, 2, 25, 'Script [4]', '192.168.3.32', 0, '');

-- add slideshow
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (580, 1, 1411543800, 0, 24, 'Name Slideshow_4', '192.168.3.32', 0, '');

-- update slideshow
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (581, 1, 1411543800, 1, 24, 'Name Slideshow_4', '192.168.3.32', 0, '');

-- delete slideshow
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (582, 1, 1411543800, 2, 24, 'Name Slideshow_4', '192.168.3.32', 0, '');

-- add template
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (583, 1, 1411543800, 0, 30, '', '192.168.3.32', 10055, 'Test_template1');

-- update template
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (584, 1, 1411543800, 1, 30, '', '192.168.3.32', 10055, 'Test_template1');

-- delete template
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (585, 1, 1411543800, 2, 30, '0', '192.168.3.32', 10055, 'Test_template1');

-- updating record "Configuration of Zabbix" in the auditlog
INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (700, 1, 1411543800, 1, 2, 'Default theme "originalblue".; Event acknowledges "1".; Dr...', '192.168.3.32', 0, '');

-- adding test data to the 'alerts' table for testing Audit->Actions report
INSERT INTO events (eventid, source, object, objectid, clock, value, acknowledged, ns) VALUES (1, 0, 0, 13545, 1329724790, 1, 0, 0);

INSERT INTO alerts (alertid, actionid, eventid, userid, clock, mediatypeid, sendto, subject, message, status, retries, error, esc_step, alerttype, parameters) VALUES (1, 12, 1, 1, 1329724800, 1, 'igor.danoshaites@zabbix.com', 'PROBLEM: Value of item key1 > 5', 'Event at 2012.02.20 10:00:00 Hostname: H1 Value of item key1 > 5: PROBLEM Last value: 6', 1, 0, '', 1, 0, '');
INSERT INTO alerts (alertid, actionid, eventid, userid, clock, mediatypeid, sendto, subject, message, status, retries, error, esc_step, alerttype, parameters) VALUES (2, 12, 1, 1, 1329724810, 1, 'igor.danoshaites@zabbix.com', 'PROBLEM: Value of item key1 > 6', 'Event at 2012.02.20 10:00:10 Hostname: H1 Value of item key1 > 6: PROBLEM', 1, 0, '', 1, 0, '');
INSERT INTO alerts (alertid, actionid, eventid, userid, clock, mediatypeid, sendto, subject, message, status, retries, error, esc_step, alerttype, parameters) VALUES (3, 12, 1, 1, 1329724820, 1, 'igor.danoshaites@zabbix.com', 'PROBLEM: Value of item key1 > 7', 'Event at 2012.02.20 10:00:20 Hostname: H1 Value of item key1 > 7: PROBLEM', 1, 0, '', 1, 0, '');
INSERT INTO alerts (alertid, actionid, eventid, userid, clock, mediatypeid, sendto, subject, message, status, retries, error, esc_step, alerttype, parameters) VALUES (4, 12, 1, 1, 1329724830, 1, 'igor.danoshaites@zabbix.com', 'PROBLEM: Value of item key1 > 10', 'Event at 2012.02.20 10:00:30 Hostname: H1 Value of item key1 > 10: PROBLEM', 2, 0, 'Get value from agent failed: cannot connect to [[127.0.0.1]:10050]: [111] Connection refused', 1, 0, '');
INSERT INTO alerts (alertid, actionid, eventid, userid, clock, mediatypeid, sendto, subject, message, status, retries, error, esc_step, alerttype, parameters) VALUES (5, 12, 1, 1, 1329724840, 1, 'igor.danoshaites@zabbix.com', 'PROBLEM: Value of item key1 > 20', 'Event at 2012.02.20 10:00:40 Hostname: H1 Value of item key1 > 20: PROBLEM', 0, 0, 'Get value from agent failed: cannot connect to [[127.0.0.1]:10050]: [111] Connection refused', 1, 0, '');
INSERT INTO alerts (alertid, actionid, eventid, userid, clock, mediatypeid, sendto, subject, message, status, retries, error, esc_step, alerttype, parameters) VALUES (6, 12, 1, NULL, 1329724850, NULL, '', '', 'Command: H1:ls -la', 1, 0, '', 1, 1, '');
INSERT INTO alerts (alertid, actionid, eventid, userid, clock, mediatypeid, sendto, subject, message, status, retries, error, esc_step, alerttype, parameters) VALUES (7, 12, 1, NULL, 1329724860, NULL, '', '', 'Command: H1:ls -la', 1, 0, '', 1, 1, '');

-- deleting auditid from the ids table
-- delete from ids where table_name='auditlog' and field_name='auditid'

-- host, item, trigger  for testing macro resolving in trigger description
INSERT INTO hosts (hostid, host, name, status, description) VALUES (20006, 'Host for trigger description macros', 'Host for trigger description macros', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (90279, 20006, 4);
INSERT INTO interface (type, ip, dns, useip, port, main, hostid, interfaceid) VALUES (1, '127.0.0.1', '', '1', '10050', '1', 20006, 10025);
INSERT INTO items (itemid, name, key_, hostid, interfaceid, delay, value_type, params, description, posts, headers) VALUES (24338, 'item1', 'key1', 20006, 10025, '30s', 3, '', '', '', '');
INSERT INTO triggers (triggerid, description, value, state, lastchange, comments) VALUES (100029, 'trigger host.host:{HOST.HOST} | host.host2:{HOST.HOST2} | host.name:{HOST.NAME} | item.value:{ITEM.VALUE} | item.value1:{ITEM.VALUE1} | item.lastvalue:{ITEM.LASTVALUE} | host.ip:{HOST.IP} | host.dns:{HOST.DNS} | host.conn:{HOST.CONN}', 0, 1, '1339761311', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99946, 24338, 100029, 'last', '0');

-- inheritance testing
INSERT INTO hosts (hostid, host, name, status, description) VALUES (15000, 'Inheritance test template', 'Inheritance test template', 3, '');
INSERT INTO hosts (hostid, host, name, status, description) VALUES (15002, 'Inheritance test template 2', 'Inheritance test template 2', 3, '');
INSERT INTO hosts (hostid, host, name, status, description) VALUES (15015, 'Inheritance test template for unlink', 'Inheritance test template for unlink', 3, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (15000, 15000, 1);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (15002, 15002, 1);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (15015, 15015, 1);

INSERT INTO hosts (hostid, host, name, status, description) VALUES (15001, 'Template inheritance test host', 'Template inheritance test host', 0, '');
INSERT INTO interface (interfaceid, hostid, type, ip, useip, port, main) VALUES (15000, 15001, 1, '127.0.0.1', 1, '10051', 1);
INSERT INTO interface (interfaceid, hostid, type, ip, useip, port, main) VALUES (15001, 15001, 1, '127.0.0.2', 1, '10052', 0);
INSERT INTO interface (interfaceid, hostid, type, ip, useip, port, main) VALUES (15002, 15001, 2, '127.0.0.3', 1, '10053', 1);
INSERT INTO interface_snmp (interfaceid, version, bulk, community) values (15002, 2, 1, '{$SNMP_COMMUNITY}');
INSERT INTO interface (interfaceid, hostid, type, ip, useip, port, main) VALUES (15003, 15001, 3, '127.0.0.4', 1, '10054', 1);
INSERT INTO interface (interfaceid, hostid, type, ip, useip, port, main) VALUES (15004, 15001, 4, '127.0.0.5', 1, '10055', 1);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (15001, 15001, 4);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (15000, 15001, 15000);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (15001, 15001, 15002);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (15003, 15001, 15015);

-- testFormItem.LayoutCheck testInheritanceItem.SimpleUpdate
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, formula, params, description, posts, headers) VALUES (15000, 15000, 0, 'itemInheritance'     , 'key-item-inheritance-test', '30s', 3, 1, '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, formula, params, description, posts, headers) VALUES (15001, 15000, 0, 'testInheritanceItem1', 'test-inheritance-item1'   , '30s', 3, 1, '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, formula, params, description, posts, headers) VALUES (15002, 15000, 0, 'testInheritanceItem2', 'test-inheritance-item2'   , '30s', 3, 1, '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, formula, params, description, posts, headers) VALUES (15003, 15000, 0, 'testInheritanceItem3', 'test-inheritance-item3'   , '30s', 3, 1, '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, formula, params, description, posts, headers) VALUES (15004, 15000, 0, 'testInheritanceItem4', 'test-inheritance-item4'   , '30s', 3, 1, '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, posts, headers) VALUES (15093, 15000, 0, 'testInheritanceItemPreprocessing', 'test-inheritance-item-preprocessing'   , '30s', 3, '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, interfaceid, templateid, posts, headers) VALUES (15005, 15001, 0, 'itemInheritance'     , 'key-item-inheritance-test', '30s', 3, '', '', 15000, 15000, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, interfaceid, templateid, posts, headers) VALUES (15006, 15001, 0, 'testInheritanceItem1', 'test-inheritance-item1'   , '30s', 3, '', '', 15000, 15001, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, interfaceid, templateid, posts, headers) VALUES (15007, 15001, 0, 'testInheritanceItem2', 'test-inheritance-item2'   , '30s', 3, '', '', 15000, 15002, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, interfaceid, templateid, posts, headers) VALUES (15008, 15001, 0, 'testInheritanceItem3', 'test-inheritance-item3'   , '30s', 3, '', '', 15000, 15003, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, interfaceid, templateid, posts, headers) VALUES (15009, 15001, 0, 'testInheritanceItem4', 'test-inheritance-item4'   , '30s', 3, '', '', 15000, 15004, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, interfaceid, templateid, posts, headers) VALUES (15094, 15001, 0, 'testInheritanceItemPreprocessing', 'test-inheritance-item-preprocessing', '30s', 3, '', '', 15000, 15093, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, interfaceid, posts, headers)             VALUES (15010, 15001, 0, 'itemInheritanceTest' , 'key-test-inheritance'     , '30s', 3, '', '', 15000, '', '');

INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, posts, headers) VALUES (15079, 15002, 0, 'testInheritance'     , 'key-item-inheritance'     , '30s', 3, '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, interfaceid, templateid, posts, headers) VALUES (15080, 15001, 0, 'testInheritance'     , 'key-item-inheritance'     , '30s', 3, '', '', 15000, 15079, '', '');

-- testFormItem.Preprocessing Inheritance test template->testInheritanceItemPreprocessing
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (125,15093,1,1,'123');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (126,15093,2,2,'abc');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (127,15093,3,3,'def');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (128,15093,4,4,'1a2b3c');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (129,15093,5,5,'regular expression pattern
output formatting template');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (130,15093,6,6,'');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (131,15093,7,7,'');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (132,15093,8,8,'');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (133,15093,9,9,'');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (134,15093,10,11,'/document/item/value/text()');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (135,15093,11,12,'$.document.item.value parameter.');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (177,15093,12,13,'-5
3');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (178,15093,13,14,'regular expression pattern for matching');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (179,15093,14,15,'regular expression pattern for not matching');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (180,15093,15,16,'/json/path');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (181,15093,16,17,'/xml/path');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (182,15093,17,18,'regular expression pattern for error matching
test output');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (183,15093,18,20,'7');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (77000,15093,19,25,'1
2');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (77001,15093,20,24,'.
/
1');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (77002,15093,21,21,'test script');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (77003,15093,22,23,'metric');

-- Template inheritance test host->testInheritanceItemPreprocessing
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (136,15094,1,1,'123');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (137,15094,2,2,'abc');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (138,15094,3,3,'def');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (139,15094,4,4,'1a2b3c');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (140,15094,5,5,'regular expression pattern
output formatting template');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (141,15094,6,6,'');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (142,15094,7,7,'');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (143,15094,8,8,'');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (144,15094,9,9,'');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (145,15094,10,11,'/document/item/value/text()');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (146,15094,11,12,'$.document.item.value parameter.');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (170,15094,12,13,'-5
3');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (171,15094,13,14,'regular expression pattern for matching');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (172,15094,14,15,'regular expression pattern for not matching');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (173,15094,15,16,'/json/path');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (174,15094,16,17,'/xml/path');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (175,15094,17,18,'regular expression pattern for error matching
test output');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (176,15094,18,20,'7');

INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (777000,15094,19,25,'1
2');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (777001,15094,20,24,'.
/
1');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (777002,15094,21,21,'test script');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (777003,15094,22,23,'metric');

-- testFormTrigger.SimpleUpdate and testInheritanceTrigger.SimpleUpdate
INSERT INTO triggers (triggerid, expression, description, comments)             VALUES (99000, '{99729}=0', 'testInheritanceTrigger1', '');
INSERT INTO triggers (triggerid, expression, description, comments)             VALUES (99001, '{99730}=0', 'testInheritanceTrigger2', '');
INSERT INTO triggers (triggerid, expression, description, comments)             VALUES (99002, '{99731}=0', 'testInheritanceTrigger3', '');
INSERT INTO triggers (triggerid, expression, description, comments)             VALUES (99003, '{99732}=0', 'testInheritanceTrigger4', '');
INSERT INTO triggers (triggerid, expression, description, comments, templateid) VALUES (99004, '{99733}=0', 'testInheritanceTrigger1', '', 99000);
INSERT INTO triggers (triggerid, expression, description, comments, templateid) VALUES (99005, '{99734}=0', 'testInheritanceTrigger2', '', 99001);
INSERT INTO triggers (triggerid, expression, description, comments, templateid) VALUES (99006, '{99735}=0', 'testInheritanceTrigger3', '', 99002);
INSERT INTO triggers (triggerid, expression, description, comments, templateid) VALUES (99007, '{99736}=0', 'testInheritanceTrigger4', '', 99003);
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (99729, 99000, 15000, 'last', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (99730, 99001, 15000, 'last', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (99731, 99002, 15000, 'last', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (99732, 99003, 15000, 'last', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (99733, 99004, 15005, 'last', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (99734, 99005, 15005, 'last', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (99735, 99006, 15005, 'last', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (99736, 99007, 15005, 'last', '');

-- testFormGraph.LayoutCheck testInheritanceGraph.SimpleUpdate
INSERT INTO graphs (graphid, name)             VALUES (15000, 'testInheritanceGraph1');
INSERT INTO graphs (graphid, name)             VALUES (15001, 'testInheritanceGraph2');
INSERT INTO graphs (graphid, name)             VALUES (15002, 'testInheritanceGraph3');
INSERT INTO graphs (graphid, name)             VALUES (15003, 'testInheritanceGraph4');
INSERT INTO graphs (graphid, name, templateid) VALUES (15004, 'testInheritanceGraph1', 15000);
INSERT INTO graphs (graphid, name, templateid) VALUES (15005, 'testInheritanceGraph2', 15001);
INSERT INTO graphs (graphid, name, templateid) VALUES (15006, 'testInheritanceGraph3', 15002);
INSERT INTO graphs (graphid, name, templateid) VALUES (15007, 'testInheritanceGraph4', 15003);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color) VALUES (15000, 15000, 15001, 1, 1, 'FF5555');
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color) VALUES (15001, 15001, 15002, 1, 1, 'FF5555');
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color) VALUES (15002, 15002, 15003, 1, 1, 'FF5555');
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color) VALUES (15003, 15003, 15004, 1, 1, 'FF5555');
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color) VALUES (15004, 15004, 15006, 1, 1, 'FF5555');
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color) VALUES (15005, 15005, 15007, 1, 1, 'FF5555');
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color) VALUES (15006, 15006, 15008, 1, 1, 'FF5555');
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color) VALUES (15007, 15007, 15009, 1, 1, 'FF5555');

-- testInheritanceDiscoveryRule.LayoutCheck and testInheritanceDiscoveryRule.SimpleUpdate
-- testFormItemPrototype, testInheritanceItemPrototype etc. for all prototype testing
INSERT INTO items (itemid, hostid, type, name, key_, delay, trends, value_type, params, description, flags, posts, headers)                          VALUES (15011, 15000, 0, 'testInheritanceDiscoveryRule' , 'inheritance-discovery-rule' , 3600, 0, 4, '', '', 1, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, trends, value_type, params, description, flags, posts, headers)                          VALUES (15012, 15000, 0, 'testInheritanceDiscoveryRule1', 'discovery-rule-inheritance1', 3600, 0, 4, '', '', 1, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, trends, value_type, params, description, flags, posts, headers)                          VALUES (15013, 15000, 0, 'testInheritanceDiscoveryRule2', 'discovery-rule-inheritance2', 3600, 0, 4, '', '', 1, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, trends, value_type, params, description, flags, posts, headers)                          VALUES (15014, 15000, 0, 'testInheritanceDiscoveryRule3', 'discovery-rule-inheritance3', 3600, 0, 4, '', '', 1, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, trends, value_type, params, description, flags, posts, headers)                          VALUES (15015, 15000, 0, 'testInheritanceDiscoveryRule4', 'discovery-rule-inheritance4', 3600, 0, 4, '', '', 1, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, trends, value_type, params, description, flags, interfaceid, templateid, posts, headers) VALUES (15016, 15001, 0, 'testInheritanceDiscoveryRule' , 'inheritance-discovery-rule' , 3600, 0, 4, '', '', 1, 15000, 15011, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, trends, value_type, params, description, flags, interfaceid, templateid, posts, headers) VALUES (15017, 15001, 0, 'testInheritanceDiscoveryRule1', 'discovery-rule-inheritance1', 3600, 0, 4, '', '', 1, 15000, 15012, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, trends, value_type, params, description, flags, interfaceid, templateid, posts, headers) VALUES (15018, 15001, 0, 'testInheritanceDiscoveryRule2', 'discovery-rule-inheritance2', 3600, 0, 4, '', '', 1, 15000, 15013, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, trends, value_type, params, description, flags, interfaceid, templateid, posts, headers) VALUES (15019, 15001, 0, 'testInheritanceDiscoveryRule3', 'discovery-rule-inheritance3', 3600, 0, 4, '', '', 1, 15000, 15014, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, trends, value_type, params, description, flags, interfaceid, templateid, posts, headers) VALUES (15020, 15001, 0, 'testInheritanceDiscoveryRule4', 'discovery-rule-inheritance4', 3600, 0, 4, '', '', 1, 15000, 15015, '', '');

INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, flags, posts, headers)                          VALUES (15081, 15002, 0, 'testInheritanceDiscoveryRule5', 'discovery-rule-inheritance5', 3600, 4, '', '', 1, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, flags, interfaceid, templateid, posts, headers) VALUES (15082, 15001, 0, 'testInheritanceDiscoveryRule5', 'discovery-rule-inheritance5', 3600, 4, '', '', 1, 15000, 15081, '', '');

-- testInheritanceItemPrototype.SimpleUpdate and testInheritanceItemPrototype.SimpleCreate
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, formula, params, description, flags, posts, headers)                          VALUES (15021, 15000, 0, 'itemDiscovery'                , 'item-discovery-prototype', '30s', 3, 1, '', '', 2, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, formula, params, description, flags, posts, headers)                          VALUES (15022, 15000, 0, 'testInheritanceItemPrototype1', 'item-prototype-test1'    , '30s', 3, 1, '', '', 2, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, formula, params, description, flags, posts, headers)                          VALUES (15023, 15000, 0, 'testInheritanceItemPrototype2', 'item-prototype-test2'    , '30s', 3, 1, '', '', 2, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, formula, params, description, flags, posts, headers)                          VALUES (15024, 15000, 0, 'testInheritanceItemPrototype3', 'item-prototype-test3'    , '30s', 3, 1, '', '', 2, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, formula, params, description, flags, posts, headers)                          VALUES (15025, 15000, 0, 'testInheritanceItemPrototype4', 'item-prototype-test4'    , '30s', 3, 1, '', '', 2, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, flags, posts, headers)                          VALUES (15095, 15000, 0, 'testInheritanceItemPrototypePreprocessing', 'item-prototype-preprocessing'    , 30, 3,'', '', 2, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, flags, interfaceid, templateid, posts, headers) VALUES (15026, 15001, 0, 'itemDiscovery'                , 'item-discovery-prototype', '30s', 3, '', '', 2, 15000, 15021, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, flags, interfaceid, templateid, posts, headers) VALUES (15027, 15001, 0, 'testInheritanceItemPrototype1', 'item-prototype-test1'    , '30s', 3, '', '', 2, 15000, 15022, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, flags, interfaceid, templateid, posts, headers) VALUES (15028, 15001, 0, 'testInheritanceItemPrototype2', 'item-prototype-test2'    , '30s', 3, '', '', 2, 15000, 15023, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, flags, interfaceid, templateid, posts, headers) VALUES (15029, 15001, 0, 'testInheritanceItemPrototype3', 'item-prototype-test3'    , '30s', 3, '', '', 2, 15000, 15024, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, flags, interfaceid, templateid, posts, headers) VALUES (15030, 15001, 0, 'testInheritanceItemPrototype4', 'item-prototype-test4'    , '30s', 3, '', '', 2, 15000, 15025, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, flags, interfaceid, templateid, posts, headers) VALUES (15096, 15001, 0, 'testInheritanceItemPrototypePreprocessing', 'item-prototype-preprocessing'    , '30s', 3, '', '', 2, 15000, 15095, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (15021, 15021, 15011);
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (15022, 15022, 15011);
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (15023, 15023, 15011);
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (15024, 15024, 15011);
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (15025, 15025, 15011);
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (15026, 15026, 15016);
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (15027, 15027, 15016);
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (15028, 15028, 15016);
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (15029, 15029, 15016);
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (15030, 15030, 15016);
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (15031, 15095, 15011);
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (15032, 15096, 15016);

INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, flags, posts, headers)                          VALUES (15083, 15002, 0, 'testInheritanceItemPrototype5', 'item-prototype-test5'    , '30s', 3, '', '', 2, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, flags, interfaceid, templateid, posts, headers) VALUES (15084, 15001, 0, 'testInheritanceItemPrototype5', 'item-prototype-test5'    , '30s', 3, '', '', 2, 15000, 15083, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (15083, 15083, 15081);
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (15084, 15084, 15082);

-- testFormItemPrototype.Preprocessing
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (147,15095,1,1,'123');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (148,15095,2,2,'abc');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (149,15095,3,3,'def');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (150,15095,4,4,'1a2b3c');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (151,15095,5,5,'regular expression pattern
output formatting template');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (152,15095,6,6,'');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (153,15095,7,7,'');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (154,15095,8,8,'');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (155,15095,9,9,'');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (156,15095,10,11,'/document/item/value/text()');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (157,15095,11,12,'$.document.item.value parameter.');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (158,15096,1,1,'123');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (159,15096,2,2,'abc');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (160,15096,3,3,'def');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (161,15096,4,4,'1a2b3c');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (162,15096,5,5,'regular expression pattern
output formatting template');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (163,15096,6,6,'');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (164,15096,7,7,'');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (165,15096,8,8,'');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (166,15096,9,9,'');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (167,15096,10,11,'/document/item/value/text()');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params) VALUES (168,15096,11,12,'$.document.item.value parameter.');

-- testFormGraphPrototype.LayoutCheck and testInheritanceGraphPrototype.SimpleUpdate
INSERT INTO graphs (graphid, name, flags)             VALUES (15008, 'testInheritanceGraphPrototype1', 2);
INSERT INTO graphs (graphid, name, flags)             VALUES (15009, 'testInheritanceGraphPrototype2', 2);
INSERT INTO graphs (graphid, name, flags)             VALUES (15010, 'testInheritanceGraphPrototype3', 2);
INSERT INTO graphs (graphid, name, flags)             VALUES (15011, 'testInheritanceGraphPrototype4', 2);
INSERT INTO graphs (graphid, name, flags, templateid) VALUES (15012, 'testInheritanceGraphPrototype1', 2, 15008);
INSERT INTO graphs (graphid, name, flags, templateid) VALUES (15013, 'testInheritanceGraphPrototype2', 2, 15009);
INSERT INTO graphs (graphid, name, flags, templateid) VALUES (15014, 'testInheritanceGraphPrototype3', 2, 15010);
INSERT INTO graphs (graphid, name, flags, templateid) VALUES (15015, 'testInheritanceGraphPrototype4', 2, 15011);

-- testFormGraphPrototype.LayoutCheck and testInheritanceGraphPrototype.SimpleUpdate
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color) VALUES (15008, 15008, 15000, 1, 0, '9999FF');
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color) VALUES (15009, 15008, 15021, 1, 1, 'FF9999');
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color) VALUES (15010, 15009, 15000, 1, 0, '9999FF');
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color) VALUES (15011, 15009, 15021, 1, 1, 'FF9999');
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color) VALUES (15012, 15010, 15000, 1, 0, '9999FF');
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color) VALUES (15013, 15010, 15021, 1, 1, 'FF9999');
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color) VALUES (15014, 15011, 15000, 1, 0, '9999FF');
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color) VALUES (15015, 15011, 15021, 1, 1, 'FF9999');
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color) VALUES (15016, 15012, 15005, 1, 0, '9999FF');
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color) VALUES (15017, 15012, 15026, 1, 1, 'FF9999');
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color) VALUES (15018, 15013, 15005, 1, 0, '9999FF');
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color) VALUES (15019, 15013, 15026, 1, 1, 'FF9999');
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color) VALUES (15020, 15014, 15005, 1, 0, '9999FF');
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color) VALUES (15021, 15014, 15026, 1, 1, 'FF9999');
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color) VALUES (15022, 15015, 15005, 1, 0, '9999FF');
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color) VALUES (15023, 15015, 15026, 1, 1, 'FF9999');

-- testFormTriggerPrototype.LayoutCheck, testInheritanceTriggerPrototype.SimpleUpdate
INSERT INTO triggers (triggerid, expression, description, comments, flags)             VALUES (99008, '{99737}=0', 'testInheritanceTriggerPrototype1', '', 2);
INSERT INTO triggers (triggerid, expression, description, comments, flags)             VALUES (99009, '{99738}=0', 'testInheritanceTriggerPrototype2', '', 2);
INSERT INTO triggers (triggerid, expression, description, comments, flags)             VALUES (99010, '{99739}=0', 'testInheritanceTriggerPrototype3', '', 2);
INSERT INTO triggers (triggerid, expression, description, comments, flags)             VALUES (99011, '{99740}=0', 'testInheritanceTriggerPrototype4', '', 2);
INSERT INTO triggers (triggerid, expression, description, comments, flags, templateid) VALUES (99012, '{99741}=0', 'testInheritanceTriggerPrototype1', '', 2, 99008);
INSERT INTO triggers (triggerid, expression, description, comments, flags, templateid) VALUES (99013, '{99742}=0', 'testInheritanceTriggerPrototype2', '', 2, 99009);
INSERT INTO triggers (triggerid, expression, description, comments, flags, templateid) VALUES (99014, '{99743}=0', 'testInheritanceTriggerPrototype3', '', 2, 99010);
INSERT INTO triggers (triggerid, expression, description, comments, flags, templateid) VALUES (99015, '{99744}=0', 'testInheritanceTriggerPrototype4', '', 2, 99011);
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99737, 15021, 99008, 'last', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99738, 15021, 99009, 'last', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99739, 15021, 99010, 'last', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99740, 15021, 99011, 'last', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99741, 15026, 99012, 'last', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99742, 15026, 99013, 'last', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99743, 15026, 99014, 'last', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99744, 15026, 99015, 'last', '');

-- testInheritanceWeb.SimpleUpdate
INSERT INTO httptest (httptestid, name, delay, agent, hostid)             VALUES (15000, 'testInheritanceWeb1', '1m', 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)', 15000);
INSERT INTO httptest (httptestid, name, delay, agent, hostid)             VALUES (15001, 'testInheritanceWeb2', '1m', 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)', 15000);
INSERT INTO httptest (httptestid, name, delay, agent, hostid)             VALUES (15002, 'testInheritanceWeb3', '1m', 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)', 15000);
INSERT INTO httptest (httptestid, name, delay, agent, hostid)             VALUES (15003, 'testInheritanceWeb4', '1m', 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)', 15000);
INSERT INTO httptest (httptestid, name, delay, agent, hostid, templateid) VALUES (15004, 'testInheritanceWeb1', '1m', 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)', 15001, 15000);
INSERT INTO httptest (httptestid, name, delay, agent, hostid, templateid) VALUES (15005, 'testInheritanceWeb2', '1m', 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)', 15001, 15001);
INSERT INTO httptest (httptestid, name, delay, agent, hostid, templateid) VALUES (15006, 'testInheritanceWeb3', '1m', 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)', 15001, 15002);
INSERT INTO httptest (httptestid, name, delay, agent, hostid, templateid) VALUES (15007, 'testInheritanceWeb4', '1m', 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)', 15001, 15003);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, timeout, posts) VALUES (15000, 15000, 'testInheritanceWeb1', 1, 'testInheritanceWeb1', 15, '');
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, timeout, posts) VALUES (15001, 15001, 'testInheritanceWeb2', 1, 'testInheritanceWeb2', 15, '');
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, timeout, posts) VALUES (15002, 15002, 'testInheritanceWeb3', 1, 'testInheritanceWeb3', 15, '');
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, timeout, posts) VALUES (15003, 15003, 'testInheritanceWeb4', 1, 'testInheritanceWeb4', 15, '');
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, timeout, posts) VALUES (15004, 15004, 'testInheritanceWeb1', 1, 'testInheritanceWeb1', 15, '');
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, timeout, posts) VALUES (15005, 15005, 'testInheritanceWeb2', 1, 'testInheritanceWeb2', 15, '');
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, timeout, posts) VALUES (15006, 15006, 'testInheritanceWeb3', 1, 'testInheritanceWeb3', 15, '');
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, timeout, posts) VALUES (15007, 15007, 'testInheritanceWeb4', 1, 'testInheritanceWeb4', 15, '');

INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, posts, headers)             VALUES (15031, 15000, 9, 'Download speed for scenario "testInheritanceWeb1".', 'web.test.in[testInheritanceWeb1,,bps]'                      , 60, 0, 'Bps', '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, posts, headers)             VALUES (15032, 15000, 9, 'Failed step of scenario "testInheritanceWeb1".', 'web.test.fail[testInheritanceWeb1]'                         , 60, 3, ''   , '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, posts, headers)             VALUES (15033, 15000, 9, 'Last error message of scenario "testInheritanceWeb1".', 'web.test.error[testInheritanceWeb1]'                        , 60, 1, ''   , '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, posts, headers)             VALUES (15034, 15000, 9, 'Download speed for step "testInheritanceWeb1" of scenario "testInheritanceWeb1".', 'web.test.in[testInheritanceWeb1,testInheritanceWeb1,bps]'   , 60, 0, 'Bps', '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, posts, headers)             VALUES (15035, 15000, 9, 'Response time for step "testInheritanceWeb1" of scenario "testInheritanceWeb1".', 'web.test.time[testInheritanceWeb1,testInheritanceWeb1,resp]', 60, 0, 's'  , '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, posts, headers)             VALUES (15036, 15000, 9, 'Response code for step "testInheritanceWeb1" of scenario "testInheritanceWeb1".', 'web.test.rspcode[testInheritanceWeb1,testInheritanceWeb1]'  , 60, 3, ''   , '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, posts, headers)             VALUES (15037, 15000, 9, 'Download speed for scenario "testInheritanceWeb2".', 'web.test.in[testInheritanceWeb2,,bps]'                      , 60, 0, 'Bps', '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, posts, headers)             VALUES (15038, 15000, 9, 'Failed step of scenario "testInheritanceWeb2".', 'web.test.fail[testInheritanceWeb2]'                         , 60, 3, ''   , '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, posts, headers)             VALUES (15039, 15000, 9, 'Last error message of scenario "testInheritanceWeb2".', 'web.test.error[testInheritanceWeb2]'                        , 60, 1, ''   , '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, posts, headers)             VALUES (15040, 15000, 9, 'Download speed for step "testInheritanceWeb2" of scenario "testInheritanceWeb2".', 'web.test.in[testInheritanceWeb2,testInheritanceWeb2,bps]'   , 60, 0, 'Bps', '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, posts, headers)             VALUES (15041, 15000, 9, 'Response time for step "testInheritanceWeb2" of scenario "testInheritanceWeb2".', 'web.test.time[testInheritanceWeb2,testInheritanceWeb2,resp]', 60, 0, 's'  , '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, posts, headers)             VALUES (15042, 15000, 9, 'Response code for step "testInheritanceWeb2" of scenario "testInheritanceWeb2".', 'web.test.rspcode[testInheritanceWeb2,testInheritanceWeb2]'  , 60, 3, ''   , '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, posts, headers)             VALUES (15043, 15000, 9, 'Download speed for scenario "testInheritanceWeb3".', 'web.test.in[testInheritanceWeb3,,bps]'                      , 60, 0, 'Bps', '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, posts, headers)             VALUES (15044, 15000, 9, 'Failed step of scenario "testInheritanceWeb3".', 'web.test.fail[testInheritanceWeb3]'                         , 60, 3, ''   , '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, posts, headers)             VALUES (15045, 15000, 9, 'Last error message of scenario "testInheritanceWeb3".', 'web.test.error[testInheritanceWeb3]'                        , 60, 1, ''   , '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, posts, headers)             VALUES (15046, 15000, 9, 'Download speed for step "testInheritanceWeb3" of scenario "testInheritanceWeb3".', 'web.test.in[testInheritanceWeb3,testInheritanceWeb3,bps]'   , 60, 0, 'Bps', '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, posts, headers)             VALUES (15047, 15000, 9, 'Response time for step "testInheritanceWeb3" of scenario "testInheritanceWeb3".', 'web.test.time[testInheritanceWeb3,testInheritanceWeb3,resp]', 60, 0, 's'  , '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, posts, headers)             VALUES (15048, 15000, 9, 'Response code for step "testInheritanceWeb3" of scenario "testInheritanceWeb3".', 'web.test.rspcode[testInheritanceWeb3,testInheritanceWeb3]'  , 60, 3, ''   , '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, posts, headers)             VALUES (15049, 15000, 9, 'Download speed for scenario "testInheritanceWeb4".', 'web.test.in[testInheritanceWeb4,,bps]'                      , 60, 0, 'Bps', '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, posts, headers)             VALUES (15050, 15000, 9, 'Failed step of scenario "testInheritanceWeb4".', 'web.test.fail[testInheritanceWeb4]'                         , 60, 3, ''   , '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, posts, headers)             VALUES (15051, 15000, 9, 'Last error message of scenario "testInheritanceWeb4".', 'web.test.error[testInheritanceWeb4]'                        , 60, 1, ''   , '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, posts, headers)             VALUES (15052, 15000, 9, 'Download speed for step "testInheritanceWeb4" of scenario "testInheritanceWeb4".', 'web.test.in[testInheritanceWeb4,testInheritanceWeb4,bps]'   , 60, 0, 'Bps', '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, posts, headers)             VALUES (15053, 15000, 9, 'Response time for step "testInheritanceWeb4" of scenario "testInheritanceWeb4".', 'web.test.time[testInheritanceWeb4,testInheritanceWeb4,resp]', 60, 0, 's'  , '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, posts, headers)             VALUES (15054, 15000, 9, 'Response code for step "testInheritanceWeb4" of scenario "testInheritanceWeb4".', 'web.test.rspcode[testInheritanceWeb4,testInheritanceWeb4]'  , 60, 3, ''   , '', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, templateid, posts, headers) VALUES (15055, 15001, 9, 'Download speed for scenario "testInheritanceWeb1".', 'web.test.in[testInheritanceWeb1,,bps]'                      , 60, 0, 'Bps', '', '', 15031, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, templateid, posts, headers) VALUES (15056, 15001, 9, 'Failed step of scenario "testInheritanceWeb1".', 'web.test.fail[testInheritanceWeb1]'                         , 60, 3, ''   , '', '', 15032, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, templateid, posts, headers) VALUES (15057, 15001, 9, 'Last error message of scenario "testInheritanceWeb1".', 'web.test.error[testInheritanceWeb1]'                        , 60, 1, ''   , '', '', 15033, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, templateid, posts, headers) VALUES (15058, 15001, 9, 'Download speed for step "testInheritanceWeb1" of scenario "testInheritanceWeb1".', 'web.test.in[testInheritanceWeb1,testInheritanceWeb1,bps]'   , 60, 0, 'Bps', '', '', 15034, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, templateid, posts, headers) VALUES (15059, 15001, 9, 'Response time for step "testInheritanceWeb1" of scenario "testInheritanceWeb1".', 'web.test.time[testInheritanceWeb1,testInheritanceWeb1,resp]', 60, 0, 's'  , '', '', 15035, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, templateid, posts, headers) VALUES (15060, 15001, 9, 'Response code for step "testInheritanceWeb1" of scenario "testInheritanceWeb1".', 'web.test.rspcode[testInheritanceWeb1,testInheritanceWeb1]'  , 60, 3, ''   , '', '', 15036, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, templateid, posts, headers) VALUES (15061, 15001, 9, 'Download speed for scenario "testInheritanceWeb2".' , 'web.test.in[testInheritanceWeb2,,bps]'                      , 60, 0, 'Bps', '', '', 15037, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, templateid, posts, headers) VALUES (15062, 15001, 9, 'Failed step of scenario "testInheritanceWeb2".', 'web.test.fail[testInheritanceWeb2]'                         , 60, 3, ''   , '', '', 15038, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, templateid, posts, headers) VALUES (15063, 15001, 9, 'Last error message of scenario "testInheritanceWeb2".', 'web.test.error[testInheritanceWeb2]'                        , 60, 1, ''   , '', '', 15039, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, templateid, posts, headers) VALUES (15064, 15001, 9, 'Download speed for step "testInheritanceWeb2" of scenario "testInheritanceWeb2".', 'web.test.in[testInheritanceWeb2,testInheritanceWeb2,bps]'   , 60, 0, 'Bps', '', '', 15040, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, templateid, posts, headers) VALUES (15065, 15001, 9, 'Response time for step "testInheritanceWeb2" of scenario "testInheritanceWeb2".', 'web.test.time[testInheritanceWeb2,testInheritanceWeb2,resp]', 60, 0, 's'  , '', '', 15041, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, templateid, posts, headers) VALUES (15066, 15001, 9, 'Response code for step "testInheritanceWeb2" of scenario "testInheritanceWeb2".', 'web.test.rspcode[testInheritanceWeb2,testInheritanceWeb2]'  , 60, 3, ''   , '', '', 15042, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, templateid, posts, headers) VALUES (15067, 15001, 9, 'Download speed for scenario "testInheritanceWeb3".', 'web.test.in[testInheritanceWeb3,,bps]'                      , 60, 0, 'Bps', '', '', 15043, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, templateid, posts, headers) VALUES (15068, 15001, 9, 'Failed step of scenario "testInheritanceWeb3".', 'web.test.fail[testInheritanceWeb3]'                         , 60, 3, ''   , '', '', 15044, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, templateid, posts, headers) VALUES (15069, 15001, 9, 'Last error message of scenario "testInheritanceWeb3".', 'web.test.error[testInheritanceWeb3]'                        , 60, 1, ''   , '', '', 15045, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, templateid, posts, headers) VALUES (15070, 15001, 9, 'Download speed for step "testInheritanceWeb3" of scenario "testInheritanceWeb3".', 'web.test.in[testInheritanceWeb3,testInheritanceWeb3,bps]'   , 60, 0, 'Bps', '', '', 15046, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, templateid, posts, headers) VALUES (15071, 15001, 9, 'Response time for step "testInheritanceWeb3" of scenario "testInheritanceWeb3".', 'web.test.time[testInheritanceWeb3,testInheritanceWeb3,resp]', 60, 0, 's'  , '', '', 15047, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, templateid, posts, headers) VALUES (15072, 15001, 9, 'Response code for step "testInheritanceWeb3" of scenario "testInheritanceWeb3".', 'web.test.rspcode[testInheritanceWeb3,testInheritanceWeb3]'  , 60, 3, ''   , '', '', 15048, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, templateid, posts, headers) VALUES (15073, 15001, 9, 'Download speed for scenario "testInheritanceWeb4".' , 'web.test.in[testInheritanceWeb4,,bps]'                      , 60, 0, 'Bps', '', '', 15049, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, templateid, posts, headers) VALUES (15074, 15001, 9, 'Failed step of scenario "testInheritanceWeb4".', 'web.test.fail[testInheritanceWeb4]'                         , 60, 3, ''   , '', '', 15050, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, templateid, posts, headers) VALUES (15075, 15001, 9, 'Last error message of scenario "testInheritanceWeb4".', 'web.test.error[testInheritanceWeb4]'                        , 60, 1, ''   , '', '', 15051, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, templateid, posts, headers) VALUES (15076, 15001, 9, 'Download speed for step "testInheritanceWeb4" of scenario "testInheritanceWeb4".', 'web.test.in[testInheritanceWeb4,testInheritanceWeb4,bps]'   , 60, 0, 'Bps', '', '', 15052, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, templateid, posts, headers) VALUES (15077, 15001, 9, 'Response time for step "testInheritanceWeb4" of scenario "testInheritanceWeb4".', 'web.test.time[testInheritanceWeb4,testInheritanceWeb4,resp]', 60, 0, 's'  , '', '', 15053, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description, templateid, posts, headers) VALUES (15078, 15001, 9, 'Response code for step "testInheritanceWeb4" of scenario "testInheritanceWeb4".', 'web.test.rspcode[testInheritanceWeb4,testInheritanceWeb4]'  , 60, 3, ''   , '', '', 15054, '', '');
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15000, 15000, 15031, 2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15001, 15000, 15032, 3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15002, 15000, 15033, 4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15003, 15001, 15037, 2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15004, 15001, 15038, 3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15005, 15001, 15039, 4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15006, 15002, 15043, 2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15007, 15002, 15044, 3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15008, 15002, 15045, 4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15009, 15003, 15049, 2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15010, 15003, 15050, 3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15011, 15003, 15051, 4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15012, 15004, 15055, 2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15013, 15004, 15056, 3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15014, 15004, 15057, 4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15015, 15005, 15061, 2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15016, 15005, 15062, 3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15017, 15005, 15063, 4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15018, 15006, 15067, 2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15019, 15006, 15068, 3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15020, 15006, 15069, 4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15021, 15007, 15073, 2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15022, 15007, 15074, 3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (15023, 15007, 15075, 4);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15000, 15000, 15034, 2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15001, 15000, 15035, 1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15002, 15000, 15036, 0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15003, 15001, 15040, 2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15004, 15001, 15041, 1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15005, 15001, 15042, 0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15006, 15002, 15046, 2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15007, 15002, 15047, 1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15008, 15002, 15048, 0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15009, 15003, 15052, 2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15010, 15003, 15053, 1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15011, 15003, 15054, 0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15012, 15004, 15058, 2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15013, 15004, 15059, 1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15014, 15004, 15060, 0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15015, 15005, 15064, 2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15016, 15005, 15065, 1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15017, 15005, 15066, 0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15018, 15006, 15070, 2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15019, 15006, 15071, 1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15020, 15006, 15072, 0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15021, 15007, 15076, 2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15022, 15007, 15077, 1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (15023, 15007, 15078, 0);


-- create Form test template
INSERT INTO hosts (hostid, host, name, status, description) VALUES (40000, 'Form test template', 'Form test template', 3, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (40000, 40000, 1);

-- create Simple form test
INSERT INTO hosts (hostid, host, name, status, description) VALUES (40001, 'Simple form test host', 'Simple form test host', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (40001, 40001, 4);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (40000, 40001, 40000);

INSERT INTO applications (applicationid, hostid, name) VALUES (5000, 40001, 'Old Application 1');
INSERT INTO applications (applicationid, hostid, name) VALUES (5001, 40001, 'Old Application 2');
INSERT INTO applications (applicationid, hostid, name) VALUES (5002, 40001, 'App for replace 1');
INSERT INTO applications (applicationid, hostid, name) VALUES (5003, 40001, 'App for replace 2');
INSERT INTO applications (applicationid, hostid, name) VALUES (5004, 40001, 'App for remove 1');
INSERT INTO applications (applicationid, hostid, name) VALUES (5005, 40001, 'App for remove 2');

-- testFormItem interfaces
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, port) VALUES (40011, 40001, 1, 1, 1, '127.0.5.1', '10051');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, port) VALUES (40012, 40001, 1, 2, 1, '127.0.5.2', '10052');
INSERT INTO interface_snmp (interfaceid, version, bulk, community) values (40012, 2, 1, '{$SNMP_COMMUNITY}');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, port) VALUES (40013, 40001, 1, 3, 1, '127.0.5.3', '10053');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, port) VALUES (40014, 40001, 1, 4, 1, '127.0.5.4', '10054');

-- testFormItem.LayoutCheck testFormItem.SimpleUpdate
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, posts, headers) VALUES (99098, 0, 40001, 'testFormItem1', 'testFormItems', 'test-item-form1', 30, 40011, '', 1, '', '');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, posts, headers) VALUES (99099, 0, 40001, 'testFormItem2', 'testFormItems', 'test-item-form2', 30, 40011, '', 1, '', '');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, posts, headers) VALUES (99100, 0, 40001, 'testFormItem3', 'testFormItems', 'test-item-form3', 30, 40011, '', 1, '', '');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, posts, headers) VALUES (99101, 0, 40001, 'testFormItem4', 'testFormItems', 'test-item-form4', 30, 40011, '', 1, '', '');

-- testFormTrigger.SimpleCreate
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, history, trends, status, value_type, trapper_hosts, units, logtimefmt, templateid, valuemapid, params, ipmi_sensor, authtype, username, password, publickey, privatekey, flags, interfaceid, posts, headers) VALUES (99102, 0, 40001, 'testFormItem', 'testFormItems', 'test-item-reuse', '30s', '90d', '365d', 0, 0, '', '', '', NULL, NULL, '', '', 0, '', '', '', '', 0, 40011, '', '');

-- testFormTrigger.SimpleUpdate
INSERT INTO triggers (triggerid, expression, description, comments) VALUES (14000, '{14000}=0', 'testFormTrigger1', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (14000, 99102, 14000, 'last', '0');

INSERT INTO triggers (triggerid, expression, description, comments) VALUES (14001, '{14001}=0', 'testFormTrigger2', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (14001, 99102, 14001, 'last', '0');

INSERT INTO triggers (triggerid, expression, description, comments) VALUES (14002, '{14002}=0', 'testFormTrigger3', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (14002, 99102, 14002, 'last', '0');

INSERT INTO triggers (triggerid, expression, description, comments) VALUES (14003, '{14003}=0', 'testFormTrigger4', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (14003, 99102, 14003, 'last', '0');

-- testFormGraph.LayoutCheck testFormGraph.SimpleUpdate
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (300000,'testFormGraph1',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,0);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (300001,'testFormGraph2',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,0);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (300002,'testFormGraph3',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,0);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (300003,'testFormGraph4',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,0);

-- testFormGraph.LayoutCheck testFormGraph.SimpleUpdate
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (300000, 300000, 99102, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (300001, 300001, 99102, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (300002, 300002, 99102, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (300003, 300003, 99102, 1, 1, 'FF5555', 0, 2, 0);

-- testFormDiscoveryRule.SimpleUpdate
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description, interfaceid, posts, headers) VALUES ('testFormDiscoveryRule1', 'discovery-rule-form1', 40001, 4, 43700, 1,  50, '', '', 40011, '', '');
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description, interfaceid, posts, headers) VALUES ('testFormDiscoveryRule2', 'discovery-rule-form2', 40001, 4, 43701, 1,  50, '', '', 40011, '', '');
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description, interfaceid, posts, headers) VALUES ('testFormDiscoveryRule3', 'discovery-rule-form3', 40001, 4, 43702, 1,  50, '', '', 40011, '', '');
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description, interfaceid, posts, headers) VALUES ('testFormDiscoveryRule4', 'discovery-rule-form4', 40001, 4, 43703, 1,  50, '', '', 40011, '', '');

-- testFormItemPrototype.SimpleUpdate
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description, interfaceid, posts, headers) VALUES ('testFormDiscoveryRule', 'discovery-rule-form', 40001, 4, 133800, 1,  50, '', '', 40011, '', '');

-- testFormItemPrototype.SimpleUpdate
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description, interfaceid, posts, headers) VALUES ('testFormItemPrototype1', 'item-prototype-form1', 40001, 3, 23800, 2, 5, '', '', 40011, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (501, 23800, 133800);
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description, interfaceid, posts, headers) VALUES ('testFormItemPrototype2', 'item-prototype-form2', 40001, 3, 23801, 2, 5, '', '', 40011, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (502, 23801, 133800);
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description, interfaceid, posts, headers) VALUES ('testFormItemPrototype3', 'item-prototype-form3', 40001, 3, 23802, 2, 5, '', '', 40011, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (503, 23802, 133800);
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description, interfaceid, posts, headers) VALUES ('testFormItemPrototype4', 'item-prototype-form4', 40001, 3, 23803, 2, 5, '', '', 40011, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (504, 23803, 133800);

-- testFormTriggerPrototype.SimpleCreate
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description, interfaceid, posts, headers) VALUES ('testFormItemReuse', 'item-prototype-reuse', 40001, 3, 23804, 2, 5, '', '', 40011, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (505, 23804, 133800);

-- testFormTriggerPrototype.SimpleUpdate
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (99518,'{99947}=0','testFormTriggerPrototype1','',0,0,0,0,'','',NULL,0,0,2);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (99519,'{99948}=0','testFormTriggerPrototype2','',0,0,0,0,'','',NULL,0,0,2);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (99520,'{99949}=0','testFormTriggerPrototype3','',0,0,0,0,'','',NULL,0,0,2);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (99521,'{99950}=0','testFormTriggerPrototype4','',0,0,0,0,'','',NULL,0,0,2);
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (99947,23804,99518,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (99948,23804,99519,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (99949,23804,99520,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (99950,23804,99521,'last','0');

-- testFormGraphPrototype.LayoutCheck and testFormGraphPrototype.SimpleUpdate
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (600000,'testFormGraphPrototype1',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,2);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (600001,'testFormGraphPrototype2',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,2);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (600002,'testFormGraphPrototype3',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,2);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (600003,'testFormGraphPrototype4',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,2);

-- testFormGraphPrototype.LayoutCheck and testFormGraphPrototype.SimpleUpdate
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (600000, 600000, 99102, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (600001, 600000, 23804, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (600002, 600001, 99102, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (600003, 600001, 23804, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (600004, 600002, 99102, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (600005, 600002, 23804, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (600006, 600003, 99102, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (600007, 600003, 23804, 1, 1, 'FF5555', 0, 2, 0);

-- testFormWeb.SimpleUpdate
INSERT INTO httptest (httptestid, hostid, name, delay, status, agent) VALUES (94, 40001, 'testFormWeb1', '1m', 0, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
INSERT INTO httptest (httptestid, hostid, name, delay, status, agent) VALUES (95, 40001, 'testFormWeb2', '1m', 0, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
INSERT INTO httptest (httptestid, hostid, name, delay, status, agent) VALUES (96, 40001, 'testFormWeb3', '1m', 0, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
INSERT INTO httptest (httptestid, hostid, name, delay, status, agent) VALUES (97, 40001, 'testFormWeb4', '1m', 0, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, timeout, posts, required, status_codes) VALUES (94, 94, 'testFormWeb1', 1, 'testFormWeb1', 15, '', '', '');
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, timeout, posts, required, status_codes) VALUES (95, 95, 'testFormWeb2', 1, 'testFormWeb2', 15, '', '', '');
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, timeout, posts, required, status_codes) VALUES (96, 96, 'testFormWeb3', 1, 'testFormWeb3', 15, '', '', '');
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, timeout, posts, required, status_codes) VALUES (97, 97, 'testFormWeb4', 1, 'testFormWeb4', 15, '', '', '');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (23420,9,40001,'Download speed for scenario "testFormWeb1".','web.test.in[testFormWeb1,,bps]','60s','30d','90d',0,0,'','Bps','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (23421,9,40001,'Failed step of scenario "testFormWeb1".','web.test.fail[testFormWeb1]','60s','30d','90d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (23422,9,40001,'Last error message of scenario "testFormWeb1".','web.test.error[testFormWeb1]','60s','30d','90d',0,1,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (23423,9,40001,'Download speed for step "testFormWeb1" of scenario "testFormWeb1".','web.test.in[testFormWeb1,testFormWeb1,bps]','60s','30d','90d',0,0,'','Bps','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (23424,9,40001,'Response time for step "testFormWeb1" of scenario "testFormWeb1".','web.test.time[testFormWeb1,testFormWeb1,resp]','60s','30d','90d',0,0,'','s','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (23425,9,40001,'Response code for step "testFormWeb1" of scenario "testFormWeb1".','web.test.rspcode[testFormWeb1,testFormWeb1]','60s','30d','90d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (23426,9,40001,'Download speed for scenario "testFormWeb2".','web.test.in[testFormWeb2,,bps]','60s','30d','90d',0,0,'','Bps','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (23427,9,40001,'Failed step of scenario "testFormWeb2".','web.test.fail[testFormWeb2]','60s','30d','90d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (23428,9,40001,'Last error message of scenario "testFormWeb2".','web.test.error[testFormWeb2]','60s','30d','90d',0,1,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (23429,9,40001,'Download speed for step "testFormWeb2" of scenario "testFormWeb2".','web.test.in[testFormWeb2,testFormWeb2,bps]','60s','30d','90d',0,0,'','Bps','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (23430,9,40001,'Response time for step "testFormWeb2" of scenario "testFormWeb2".','web.test.time[testFormWeb2,testFormWeb2,resp]','60s','30d','90d',0,0,'','s','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (23431,9,40001,'Response code for step "testFormWeb2" of scenario "testFormWeb2".','web.test.rspcode[testFormWeb2,testFormWeb2]','60s','30d','90d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (23432,9,40001,'Download speed for scenario "testFormWeb3".','web.test.in[testFormWeb3,,bps]','60s',30,'90d',0,0,'','Bps','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (23433,9,40001,'Failed step of scenario "testFormWeb3".','web.test.fail[testFormWeb3]','60s','30d','90d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (23434,9,40001,'Last error message of scenario "testFormWeb3".','web.test.error[testFormWeb3]','60s','30d','90d',0,1,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (23435,9,40001,'Download speed for step "testFormWeb3" of scenario "testFormWeb3".','web.test.in[testFormWeb3,testFormWeb3,bps]','60s','30d','90d',0,0,'','Bps','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (23436,9,40001,'Response time for step "testFormWeb3" of scenario "testFormWeb3".','web.test.time[testFormWeb3,testFormWeb3,resp]','60s','30d','90d',0,0,'','s','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (23437,9,40001,'Response code for step "testFormWeb3" of scenario "testFormWeb3".','web.test.rspcode[testFormWeb3,testFormWeb3]','60s','30d','90d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (23438,9,40001,'Download speed for scenario "testFormWeb4".','web.test.in[testFormWeb4,,bps]','60s','30d','90d',0,0,'','Bps','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (23439,9,40001,'Failed step of scenario "testFormWeb4".','web.test.fail[testFormWeb4]','60s','30d','90d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (23440,9,40001,'Last error message of scenario "testFormWeb4".','web.test.error[testFormWeb4]','60s','30d','90d',0,1,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (23441,9,40001,'Download speed for step "testFormWeb4" of scenario "testFormWeb4".','web.test.in[testFormWeb4,testFormWeb4,bps]','60s','30d','90d',0,0,'','Bps','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (23442,9,40001,'Response time for step "testFormWeb4" of scenario "testFormWeb4".','web.test.time[testFormWeb4,testFormWeb4,resp]','60s','30d','90d',0,0,'','s','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (23443,9,40001,'Response code for step "testFormWeb4" of scenario "testFormWeb4".','web.test.rspcode[testFormWeb4,testFormWeb4]','60s','30d','90d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (910,94,23420,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (911,94,23421,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (912,94,23422,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (913,95,23426,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (914,95,23427,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (915,95,23428,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (916,96,23432,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (917,96,23433,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (918,96,23434,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (919,97,23438,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (920,97,23439,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (921,97,23440,4);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (910,94,23423,2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (911,94,23424,1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (912,94,23425,0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (913,95,23429,2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (914,95,23430,1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (915,95,23431,0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (916,96,23435,2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (917,96,23436,1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (918,96,23437,0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (919,97,23441,2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (920,97,23442,1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (921,97,23443,0);

-- testZBX6663.MassSelect
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50000, 'Template ZBX6663 First', 'Template ZBX6663 First', 3, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50000, 50000, 1);
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50002, 'Template ZBX6663 Second', 'Template ZBX6663 Second', 3, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50001, 50002, 1);
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50001, 'Host ZBX6663','Host ZBX6663', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50002, 50001, 4);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (50000, 50001, 50002);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (50002, 50000, 50002);
INSERT INTO interface (type, ip, dns, useip, port, main, hostid, interfaceid) VALUES (1, '127.0.7.1', '', '1', '10071', '1', 50001, 50015);
INSERT INTO applications (applicationid,hostid,name) VALUES (359,50000,'App ZBX6663 First');
INSERT INTO applications (applicationid,hostid,name) VALUES (362,50000,'App ZBX6663 Second');
INSERT INTO applications (applicationid,hostid,name) VALUES (360,50001,'App ZBX6663');
INSERT INTO applications (applicationid,hostid,name) VALUES (365,50001,'App ZBX6663 Second');
INSERT INTO applications (applicationid,hostid,name) VALUES (361,50002,'App ZBX6663 Second');
INSERT INTO application_template (application_templateid,applicationid,templateid) VALUES (50,365,361);
INSERT INTO application_template (application_templateid,applicationid,templateid) VALUES (51,362,361);
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40008,9,50000,'Download speed for scenario "$1".','web.test.in[Web ZBX6663 First,,bps]','60s','30d','90d',0,0,'','Bps','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40009,9,50000,'Failed step of scenario "$1".','web.test.fail[Web ZBX6663 First]','60s','30d','90d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40010,9,50000,'Last error message of scenario "$1".','web.test.error[Web ZBX6663 First]','60s','30d','90d',0,1,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40011,9,50000,'Download speed for step "$2" of scenario "$1".','web.test.in[Web ZBX6663 First,Web ZBX6663 First Step,bps]','60s','30d','90d',0,0,'','Bps','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40012,9,50000,'Response time for step "$2" of scenario "$1".','web.test.time[Web ZBX6663 First,Web ZBX6663 First Step,resp]','60s','30d','90d',0,0,'','s','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40013,9,50000,'Response code for step "$2" of scenario "$1".','web.test.rspcode[Web ZBX6663 First,Web ZBX6663 First Step]','60s','30d','90d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40014,9,50002,'Download speed for scenario "$1".','web.test.in[Web ZBX6663 Second,,bps]','60s','30d','90d',0,0,'','Bps','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40015,9,50002,'Failed step of scenario "$1".','web.test.fail[Web ZBX6663 Second]','60s','30d','90d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40016,9,50002,'Last error message of scenario "$1".','web.test.error[Web ZBX6663 Second]','60s','30d','90d',0,1,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40017,9,50002,'Download speed for step "$2" of scenario "$1".','web.test.in[Web ZBX6663 Second,Web ZBX6663 Second Step,bps]','60s','30d','90d',0,0,'','Bps','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40018,9,50002,'Response time for step "$2" of scenario "$1".','web.test.time[Web ZBX6663 Second,Web ZBX6663 Second Step,resp]','60s','30d','90d',0,0,'','s','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40019,9,50002,'Response code for step "$2" of scenario "$1".','web.test.rspcode[Web ZBX6663 Second,Web ZBX6663 Second Step]','60s','30d','90d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40020,9,50001,'Download speed for scenario "$1".','web.test.in[Web ZBX6663 Second,,bps]','60s','30d','90d',0,0,'','Bps','',40014,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40021,9,50001,'Failed step of scenario "$1".','web.test.fail[Web ZBX6663 Second]','60s','30d','90d',0,3,'','','',40015,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40022,9,50001,'Last error message of scenario "$1".','web.test.error[Web ZBX6663 Second]','60s','30d','90d',0,1,'','','',40016,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40023,9,50001,'Download speed for step "$2" of scenario "$1".','web.test.in[Web ZBX6663 Second,Web ZBX6663 Second Step,bps]','60s','30d','90d',0,0,'','Bps','',40017,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40024,9,50001,'Response time for step "$2" of scenario "$1".','web.test.time[Web ZBX6663 Second,Web ZBX6663 Second Step,resp]','60s','30d','90d',0,0,'','s','',40018,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40025,9,50001,'Response code for step "$2" of scenario "$1".','web.test.rspcode[Web ZBX6663 Second,Web ZBX6663 Second Step]','60s','30d','90d',0,3,'','','',40019,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40026,9,50000,'Download speed for scenario "$1".','web.test.in[Web ZBX6663 Second,,bps]','60s','30d','90d',0,0,'','Bps','',40014,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40027,9,50000,'Failed step of scenario "$1".','web.test.fail[Web ZBX6663 Second]','60s','30d','90d',0,3,'','','',040015,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40028,9,50000,'Last error message of scenario "$1".','web.test.error[Web ZBX6663 Second]','60s','30d','90d',0,1,'','','',40016,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40029,9,50000,'Download speed for step "$2" of scenario "$1".','web.test.in[Web ZBX6663 Second,Web ZBX6663 Second Step,bps]','60s','30d','90d',0,0,'','Bps','',40017,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40030,9,50000,'Response time for step "$2" of scenario "$1".','web.test.time[Web ZBX6663 Second,Web ZBX6663 Second Step,resp]','60s','30d','90d',0,0,'','s','',40018,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40031,9,50000,'Response code for step "$2" of scenario "$1".','web.test.rspcode[Web ZBX6663 Second,Web ZBX6663 Second Step]','60s','30d','90d',0,3,'','','',40019,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40032,9,50001,'Download speed for scenario "$1".','web.test.in[Web ZBX6663,,bps]','60s','30s','90d',0,0,'','Bps','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40033,9,50001,'Failed step of scenario "$1".','web.test.fail[Web ZBX6663]','60s','30d','90d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40034,9,50001,'Last error message of scenario "$1".','web.test.error[Web ZBX6663]','60s','30d','90d',0,1,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40035,9,50001,'Download speed for step "$2" of scenario "$1".','web.test.in[Web ZBX6663,Web ZBX6663 Step,bps]','60s','30d','90d',0,0,'','Bps','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40036,9,50001,'Response time for step "$2" of scenario "$1".','web.test.time[Web ZBX6663,Web ZBX6663 Step,resp]','60s','30d','90d',0,0,'','s','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40037,9,50001,'Response code for step "$2" of scenario "$1".','web.test.rspcode[Web ZBX6663,Web ZBX6663 Step]','60s','30d','90d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40038,0,50002,'Item ZBX6663 Second','item-ZBX6663-second','30s','90d','365d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40039,0,50001,'Item ZBX6663 Second','item-ZBX6663-second','30s','90d','365d',0,3,'','','',40038,NULL,'','',0,'','','','',0,50015,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40040,0,50000,'Item ZBX6663 Second','item-ZBX6663-second','30s','90d','365d',0,3,'','','',40038,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40041,0,50000,'Item ZBX6663 First','item-ZBX6663-first','30s','90d','365d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40042,0,50001,'Item ZBX6663','item-ZBX6663','30s','90d','365d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,50015,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40043,0,50001,'DiscoveryRule ZBX6663','drule-zbx6663','30s','90d','365d',0,4,'','','',NULL,NULL,'','',0,'','','','',1,50015,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40045,0,50002,'DiscoveryRule ZBX6663 Second','drule-ZBX6663-second','30s','90d','365d',0,4,'','','',NULL,NULL,'','',0,'','','','',1,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40046,0,50001,'DiscoveryRule ZBX6663 Second','drule-ZBX6663-second','30s','90d','365d',0,4,'','','',40045,NULL,'','',0,'','','','',1,50015,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40047,0,50000,'DiscoveryRule ZBX6663 Second','drule-ZBX6663-second','30s','90d','365d',0,4,'','','',40045,NULL,'','',0,'','','','',1,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40048,0,50002,'ItemProto ZBX6663 Second','item-proto-zbx6663-second','30s','90d','365d',0,3,'','','',NULL,NULL,'','',0,'','','','',2,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40049,0,50001,'ItemProto ZBX6663 Second','item-proto-zbx6663-second','30s','90d','365d',0,3,'','','',40048,NULL,'','',0,'','','','',2,50015,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40050,0,50000,'ItemProto ZBX6663 Second','item-proto-zbx6663-second','30s','90d','365d',0,3,'','','',40048,NULL,'','',0,'','','','',2,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40051,0,50000,'DiscoveryRule ZBX6663 First','drule-zbx6663-first','30s','90d','365d',0,4,'','','',NULL,NULL,'','',0,'','','','',1,NULL,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40052,0,50001,'ItemProto ZBX6663 HSecond','item-proto-zbx6663-hsecond','30s','90d','365d',0,3,'','','',NULL,NULL,'','',0,'','','','',2,50015,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40054,0,50000,'ItemProto ZBX6663 TSecond','item-proto-zbx6663-tsecond','30s','90d','365d',0,3,'','','',NULL,NULL,'','',0,'','','','',2,NULL,'',0,'30','','');
INSERT INTO item_discovery (itemdiscoveryid,itemid,parent_itemid,key_,lastcheck,ts_delete) VALUES (507,40048,40045,'',0,0);
INSERT INTO item_discovery (itemdiscoveryid,itemid,parent_itemid,key_,lastcheck,ts_delete) VALUES (508,40049,40046,'',0,0);
INSERT INTO item_discovery (itemdiscoveryid,itemid,parent_itemid,key_,lastcheck,ts_delete) VALUES (509,40050,40047,'',0,0);
INSERT INTO item_discovery (itemdiscoveryid,itemid,parent_itemid,key_,lastcheck,ts_delete) VALUES (510,40052,40046,'',0,0);
INSERT INTO item_discovery (itemdiscoveryid,itemid,parent_itemid,key_,lastcheck,ts_delete) VALUES (512,40054,40047,'',0,0);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (100008,'{100008}=0','Trigger ZBX6663 Second','',0,0,0,0,'','',NULL,0,0,0);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (100009,'{100009}=0','Trigger ZBX6663 Second','',0,0,0,0,'','',100008,0,0,0);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (100010,'{100010}=0','Trigger ZBX6663 Second','',0,0,0,0,'','',100008,0,0,0);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (100011,'{100011}=0','Trigger ZBX6663 First','',0,0,0,0,'','',NULL,0,0,0);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (100012,'{100012}=0','Trigger ZBX6663','',0,0,0,0,'','',NULL,0,0,0);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (100013,'{100013}=0','TriggerProto ZBX6663 TSecond','',0,0,0,0,'','',NULL,0,0,2);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (100014,'{100014}=0','TriggerProto ZBX6663 Second','',0,0,0,0,'','',NULL,0,0,2);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (100015,'{100015}=0','TriggerProto ZBX6663 Second','',0,0,0,0,'','',100014,0,0,2);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (100016,'{100016}=0','TriggerProto ZBX6663 Second','',0,0,0,0,'','',100014,0,0,2);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (100017,'{100017}=0','TriggerProto ZBX6663 HSecond','',0,0,0,0,'','',NULL,0,0,2);
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100008,40038,100008,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100009,40039,100009,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100010,40040,100010,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100011,40041,100011,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100012,40042,100012,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100013,40054,100013,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100014,40048,100014,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100015,40049,100015,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100016,40050,100016,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100017,40052,100017,'last','0');
INSERT INTO graphs (graphid,name,width,height,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype,show_legend,show_3d,percent_left,percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid,flags) VALUES (700008,'Graph ZBX6663',900,200,0.0000,100.0000,NULL,1,1,0,1,0,0.0000,0.0000,0,0,NULL,NULL,0);
INSERT INTO graphs (graphid,name,width,height,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype,show_legend,show_3d,percent_left,percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid,flags) VALUES (700009,'Graph ZBX6663 Second',900,200,0.0000,100.0000,NULL,1,1,0,1,0,0.0000,0.0000,0,0,NULL,NULL,0);
INSERT INTO graphs (graphid,name,width,height,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype,show_legend,show_3d,percent_left,percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid,flags) VALUES (700010,'Graph ZBX6663 Second',900,200,0.0000,100.0000,700009,1,1,0,1,0,0.0000,0.0000,0,0,NULL,NULL,0);
INSERT INTO graphs (graphid,name,width,height,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype,show_legend,show_3d,percent_left,percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid,flags) VALUES (700011,'Graph ZBX6663 Second',900,200,0.0000,100.0000,700009,1,1,0,1,0,0.0000,0.0000,0,0,NULL,NULL,0);
INSERT INTO graphs (graphid,name,width,height,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype,show_legend,show_3d,percent_left,percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid,flags) VALUES (700012,'Graph ZBX6663 First',900,200,0.0000,100.0000,NULL,1,1,0,1,0,0.0000,0.0000,0,0,NULL,NULL,0);
INSERT INTO graphs (graphid,name,width,height,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype,show_legend,show_3d,percent_left,percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid,flags) VALUES (700013,'GraphPrototype ZBX6663 Second',900,200,0.0000,100.0000,NULL,1,1,0,1,0,0.0000,0.0000,0,0,NULL,NULL,2);
INSERT INTO graphs (graphid,name,width,height,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype,show_legend,show_3d,percent_left,percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid,flags) VALUES (700014,'GraphPrototype ZBX6663 Second',900,200,0.0000,100.0000,700013,1,1,0,1,0,0.0000,0.0000,0,0,NULL,NULL,2);
INSERT INTO graphs (graphid,name,width,height,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype,show_legend,show_3d,percent_left,percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid,flags) VALUES (700015,'GraphPrototype ZBX6663 Second',900,200,0.0000,100.0000,700013,1,1,0,1,0,0.0000,0.0000,0,0,NULL,NULL,2);
INSERT INTO graphs (graphid,name,width,height,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype,show_legend,show_3d,percent_left,percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid,flags) VALUES (700016,'GraphProto ZBX6663 TSecond',900,200,0.0000,100.0000,NULL,1,1,0,1,0,0.0000,0.0000,0,0,NULL,NULL,2);
INSERT INTO graphs (graphid,name,width,height,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype,show_legend,show_3d,percent_left,percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid,flags) VALUES (700017,'GraphProto ZBX6663 HSecond',900,200,0.0000,100.0000,NULL,1,1,0,1,0,0.0000,0.0000,0,0,NULL,NULL,2);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700016,700008,40042,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700017,700009,40038,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700018,700010,40039,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700019,700011,40040,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700020,700012,40041,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700021,700013,40048,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700022,700014,40049,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700023,700015,40050,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700024,700016,40054,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700025,700017,40052,0,0,'C80000',0,2,0);
INSERT INTO httptest (httptestid, hostid, templateid, name, delay, status, agent) VALUES (98, 50000, NULL, 'Web ZBX6663 First', 60, 0, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
INSERT INTO httptest (httptestid, hostid, templateid, name, delay, status, agent) VALUES (99, 50002, NULL, 'Web ZBX6663 Second', 60, 0, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
INSERT INTO httptest (httptestid, hostid, templateid, name, delay, status, agent) VALUES (100, 50001, 99, 'Web ZBX6663 Second', 60, 0, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
INSERT INTO httptest (httptestid, hostid, templateid, name, delay, status, agent) VALUES (101, 50000, 99, 'Web ZBX6663 Second', 60, 0, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
INSERT INTO httptest (httptestid, hostid, templateid, name, delay, status, agent) VALUES (102, 50001, NULL, 'Web ZBX6663', 60, 0, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, timeout, posts, required, status_codes) VALUES (98, 98, 'Web ZBX6663 First Step', 1, 'Web ZBX6663 First Url', 15, '', '', '');
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, timeout, posts, required, status_codes) VALUES (99, 99, 'Web ZBX6663 Second Step', 1, 'Web ZBX6663 Second Url', 15, '', '', '');
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, timeout, posts, required, status_codes) VALUES (100, 100, 'Web ZBX6663 Second Step', 1, 'Web ZBX6663 Second Url', 15, '', '', '');
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, timeout, posts, required, status_codes) VALUES (101, 101, 'Web ZBX6663 Second Step', 1, 'Web ZBX6663 Second Url', 15, '', '', '');
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, timeout, posts, required, status_codes) VALUES (102, 102, 'Web ZBX6663 Step', 1, 'Web ZBX6663 Url', 15, '', '', '');
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (922,98,40008,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (923,98,40009,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (924,98,40010,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (925,99,40014,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (926,99,40015,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (927,99,40016,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (928,100,40020,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (929,100,40021,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (930,100,40022,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (931,101,40026,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (932,101,40027,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (933,101,40028,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (934,102,40032,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (935,102,40033,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (936,102,40034,4);

-- testZBX6648.eventsFilter
INSERT INTO hstgrp (groupid,name,internal) VALUES (50000,'ZBX6648 Group No Hosts',0);
INSERT INTO hstgrp (groupid,name,internal) VALUES (50001,'ZBX6648 Disabled Triggers',0);
INSERT INTO hstgrp (groupid,name,internal) VALUES (50002,'ZBX6648 Enabled Triggers',0);
INSERT INTO hstgrp (groupid,name,internal) VALUES (50003,'ZBX6648 All Triggers',0);
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50003, 'ZBX6648 Disabled Triggers Host', 'ZBX6648 Disabled Triggers Host', 0, '');
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50004, 'ZBX6648 Enabled Triggers Host', 'ZBX6648 Enabled Triggers Host', 0, '');
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50005, 'ZBX6648 All Triggers Host', 'ZBX6648 All Triggers Host', 0, '');
INSERT INTO hosts_groups (hostgroupid,hostid,groupid) VALUES (50003,50003,50001);
INSERT INTO hosts_groups (hostgroupid,hostid,groupid) VALUES (50004,50004,50002);
INSERT INTO hosts_groups (hostgroupid,hostid,groupid) VALUES (50005,50005,50003);
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) VALUES (50016,50003,1,1,1,'127.0.7.1','','10071');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) VALUES (50017,50004,1,1,1,'127.0.7.1','','10071');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) VALUES (50018,50005,1,1,1,'127.0.7.1','','10071');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40055,0,50003,'zbx6648 item disabled','zbx6648-item-disabled','30s','90d','365d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,50016,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40056,0,50004,'zbx6648 item enabled','zbx6648-item-enabled','30s','90d','365d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,50017,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40057,0,50005,'zbx6648 item all','zbx6648-item-all','30s','90d','365d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,50018,'',0,'30','','');
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (100018,'{100018}=0','zbx6648 trigger disabled','',1,0,0,0,'','',NULL,0,0,0);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (100019,'{100019}=0','zbx6648 trigger enabled','',0,0,0,0,'','',NULL,0,0,0);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (100020,'{100020}=0','zbx6648 trigger all enabled','',0,0,0,0,'','',NULL,0,0,0);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (100021,'{100021}=0','zbx6648 trigger all disabled','',1,0,0,0,'','',NULL,0,0,0);
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100018,40055,100018,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100019,40056,100019,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100020,40057,100020,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100021,40057,100021,'last','0');

-- testPageItems, testPageTriggers, testPageDiscoveryRules, testPageItemPrototype, testPageTriggerPrototype
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50006, 'Template-layout-test-001', 'Template-layout-test-001', 3, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50006, 50006, 1);
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50007, 'Host-layout-test-001', 'Host-layout-test-001', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50007, 50007, 4);
INSERT INTO interface (type, ip, dns, useip, port, main, hostid, interfaceid) VALUES (1, '127.0.7.1', '', '1', '10071', '1', 50007, 50019);
INSERT INTO interface (type, ip, dns, useip, port, main, hostid, interfaceid) VALUES (1, '127.0.7.1', '', '1', '10071', '1', 50006, 50020);
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40058,0,50006,'Discovery-rule-layout-test-001','drule-layout-test001','30s','90d','365d',1,4,'','','',NULL,NULL,'','',0,'','','','',1,NULL,'',0,'50d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40059,0,50007,'Discovery-rule-layout-test-002','drule-layout-test002','30s','90d','365d',0,4,'','','',NULL,NULL,'','',0,'','','','',1,NULL,'',0,'30','','');
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description,posts,headers) VALUES ('Item-proto-layout-test-001', 'item-proto-layout-test001', 50006, 3, 40060, 2, 5, '', '','','');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (513, 40060, 40058);
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description,posts,headers) VALUES ('Item-proto-layout-test-002', 'item-proto-layout-test002', 50007, 3, 40061, 2, 5, '', '','','');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (514, 40061, 40059);
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40062,0,50006,'Item-layout-test-001','item-layout-test-001','30s','90d','365d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,50020,'',0,'30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40063,0,50007,'Item-layout-test-002','item-layout-test-002','30s','90d','365d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,50019,'',0,'30','','');
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (100022,'{100022}=0','Trigger-proto-layout-test-001','',0,0,0,0,'','',NULL,0,0,2);
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100022,40060,100022,'last','0');
INSERT INTO triggers (triggerid, expression, description, comments, flags) VALUES (100023, '{100023}=0', 'Trigger-proto-layout-test-001', '', 2);
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100023, 40061 ,100023,'last',0);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (100024,'{100024}=0','Trigger-layout-test-001','',1,0,0,0,'','',NULL,0,0,0);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (100025,'{100025}=0','Trigger-layout-test-002','',0,0,0,0,'','',NULL,0,0,0);
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100024,40063,100024,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100025,40062,100025,'last','0');

-- testFormMap.ZBX6840
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50008, 'Host-map-test-zbx6840', 'Host-map-test-zbx6840', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50008, 50008, 4);
INSERT INTO interface (type, ip, dns, useip, port, main, hostid, interfaceid) VALUES (1, '127.0.7.1', '', '1', '10071', '1', 50008, 50021);
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,posts,headers) VALUES (40065,0,50008,'Item-layout-test-zbx6840','item-layout-test-002','30s','90d','365d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,50021,'',0,'30','','');
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (100026,'{100026}=0 and {100027}=0','Trigger-map-test-zbx6840','',0,0,0,0,'','',NULL,0,0,0);
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100026,40065,100026,'last','0');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100027,23287,100026,'last','0');
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, grid_size, grid_show, grid_align, label_format, label_type_host, label_type_hostgroup, label_type_trigger, label_type_map, label_type_image, label_string_host, label_string_hostgroup, label_string_trigger, label_string_map, label_string_image, iconmapid, expand_macros, severity_min, userid, private) VALUES (5, 'testZBX6840', 800, 600, NULL, 0, 0, 0, 0, 0, 0, 50, 1, 1, 0, 2, 2, 2, 2, 2, '', '', '', '', '', NULL, 0, 0, 1, 0);
INSERT INTO sysmaps_elements (selementid,sysmapid,elementid,elementtype,iconid_off,iconid_on,label,label_location,x,y,iconid_disabled,iconid_maintenance,elementsubtype,areatype,width,height,viewtype,use_iconmap) VALUES (8,5,10084,0,19,NULL,'Host element (Zabbix Server)',-1,413,268,NULL,NULL,0,0,200,200,0,0);
INSERT INTO sysmaps_elements (selementid,sysmapid,elementid,elementtype,iconid_off,iconid_on,label,label_location,x,y,iconid_disabled,iconid_maintenance,elementsubtype,areatype,width,height,viewtype,use_iconmap) VALUES (9,5,0,2,15,NULL,'Trigger element (zbx6840)',-1,213,218,NULL,NULL,0,0,200,200,0,0);
INSERT INTO sysmap_element_trigger (selement_triggerid, selementid, triggerid) VALUES (2,9,100026);

-- testPageHistory_CheckLayout

INSERT INTO hosts (hostid, host, name, status, description) VALUES (15003, 'testPageHistory_CheckLayout', 'testPageHistory_CheckLayout', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (15003, 15003, 4);
INSERT INTO interface (interfaceid, hostid, type, ip, useip, port, main) VALUES (15005, 15003, 1, '127.0.0.1', 1, '10050', 1);

INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history, trends, status, units, valuemapid, params, description, flags, posts, headers) VALUES (15085, 15003, 15005, 0, 3, 'item_testPageHistory_CheckLayout_Numeric_Unsigned', 'numeric_unsigned[item_testpagehistory_checklayout]', '30s', '90d', '365d', 0, '', NULL, '', '', 0, '', '');
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history, trends, status, units, valuemapid, params, description, flags, posts, headers) VALUES (15086, 15003, 15005, 0, 0, 'item_testPageHistory_CheckLayout_Numeric_Float'   , 'numeric_float[item_testpagehistory_checklayout]'   , '30s', '90d', '365d', 0, '', NULL, '', '', 0, '', '');
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history,         status,                    params, description, flags, posts, headers) VALUES (15087, 15003, 15005, 0, 1, 'item_testPageHistory_CheckLayout_Character'       , 'character[item_testpagehistory_checklayout]'       , '30s', '90d',      0,           '', 'http://zabbix.com https://www.zabbix.com/career https://www.zabbix.com/contact', 0, '', '');
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history,         status,                    params, description, flags, posts, headers) VALUES (15088, 15003, 15005, 0, 4, 'item_testPageHistory_CheckLayout_Text'            , 'text[item_testpagehistory_checklayout]'            , '30s', '90d',      0,           '', 'These urls should be clickable: https://zabbix.com https://www.zabbix.com/career', 0, '', '');
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history,         status,                    params, description, flags, posts, headers) VALUES (15089, 15003, 15005, 0, 2, 'item_testPageHistory_CheckLayout_Log'             , 'log[item_testpagehistory_checklayout]'             , '30s', '90d',      0,           '', '', 0, '', '');
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history,         status,                    params, description, flags, posts, headers) VALUES (15090, 15003, 15005, 0, 2, 'item_testPageHistory_CheckLayout_Log_2'           , 'log[item_testpagehistory_checklayout, 2]'          , '30s', '90d',      0,           '', 'Non-clickable description', 0, '', '');
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history,         status,                    params, description, flags, posts, headers) VALUES (15091, 15003, 15005, 0, 2, 'item_testPageHistory_CheckLayout_Eventlog'        , 'eventlog[item_testpagehistory_checklayout]'        , '30s', '90d',      0,           '', 'https://zabbix.com', 0, '', '');
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history,         status,                    params, description, flags, posts, headers) VALUES (15092, 15003, 15005, 0, 2, 'item_testPageHistory_CheckLayout_Eventlog_2'      , 'eventlog[item_testpagehistory_checklayout, 2]'     , '30s', '90d',      0,           '', 'The following url should be clickable: https://zabbix.com', 0, '', '');

-- testUrlUserPermissions
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page, url) VALUES (4, 'admin-zabbix', '$2y$10$HuvU0X0vGitK8YhwyxILbOVU6oxYNF.BqsOhaieVBvDiGlxgxriay', 0, 0, 'en_GB', 30, 2, 'default', 0, 0, 50, 'toptriggers.php');
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (6, 7, 4);
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (5, 'user-zabbix', '$2y$10$MZQTU3/7XsECy1DbQqvn/eaoPoMDgMYJ7Ml1wYon1dC0NfwM9E3zu', 0, 0, 'en_GB', 30, 1, 'default', 0, 0, 50);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (7, 7, 5);

-- testPageDashboard Favorites
INSERT INTO profiles (profileid,userid,idx,value_id,source,type) VALUES (1,1,'web.favorite.sysmapids',1,'sysmapid',1);
INSERT INTO profiles (profileid,userid,idx,value_id,source,type) VALUES (2,1,'web.favorite.graphids',1546,'graphid',1);
INSERT INTO profiles (profileid,userid,idx,value_id,source,type) VALUES (3,1,'web.favorite.screenids',16,'screenid',1);

-- testFormAdministrationUserGroups
INSERT INTO usrgrp (usrgrpid, name) VALUES (13, 'Selenium user group');
INSERT INTO usrgrp (usrgrpid, name) VALUES (14, 'Selenium user group in scripts');
INSERT INTO usrgrp (usrgrpid, name) VALUES (15, 'Selenium user group in configuration');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description) VALUES (5,'Selenium script','test',2,14,NULL,'selenium script description');
UPDATE config SET alert_usrgrpid = 15 WHERE configid = 1;

-- testPageApplication
INSERT INTO applications (applicationid,hostid,name) VALUES (99000,10084,'Selenium test application');
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history, status, params, description, flags, posts, headers) VALUES (99000, 10084, 1, 0, 2, 'Selenium item for testPageApplication','item-with-app', '30s', '90d', 0, '', '', 0, '', '');
INSERT INTO items_applications (itemappid,applicationid,itemid) VALUES (50000,99000,99000);

-- Disable warning if Zabbix server is down
UPDATE config SET server_check_interval = 0 WHERE configid = 1;
-- Super admin rows per page
UPDATE users SET rows_per_page = 100 WHERE userid = 1;

-- test data for testPageAdministrationGeneralIconMapping and testFormAdministrationGeneralIconMapping
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (100, 'Icon mapping one', 10);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (1, 100, 2, 1, 'expression one', 0);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (2, 100, 2, 1, 'expression two', 1);
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (101, 'Icon mapping for update', 15);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (3, 101, 5, 4, '(1!@#$%^-=2*)', 0);
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (102, 'Icon mapping testForm update expression', 16);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (4, 102, 6, 5, 'one more expression', 0);
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (103, 'Icon mapping to check delete functionality', 10);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (5, 103, 2, 1, 'expression 1', 0);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (6, 103, 2, 1, 'expression 2', 1);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (7, 103, 2, 1, 'expression 3', 2);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (8, 103, 2, 1, 'expression 4', 3);
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (104, 'used_by_map', 9);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (9, 104, 2, 1, 'This Icon map used by map', 0);
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, iconmapid, userid, private) VALUES (6, 'Map with icon mapping', 800, 600, NULL, 0, 0, 0, 1, 0, 0, 104, 1, 1);
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (105, 'Icon mapping to check clone functionality', 10);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (10, 105, 2, 1, 'expression 1 for clone', 0);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (11, 105, 2, 1, 'expression 2 for clone', 1);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (12, 105, 2, 1, 'expression 3 for clone', 2);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (13, 105, 2, 1, 'expression 4 for clone', 3);

-- Create two triggers with event
INSERT INTO triggers (description,expression,recovery_mode,type,url,priority,comments,manual_close,status,correlation_mode,recovery_expression,correlation_tag,triggerid) VALUES ('Test trigger to check tag filter on problem page','{100185}>100','0','0','','3','','1','0','0','','','99250');
INSERT INTO functions (functionid,triggerid,itemid,name,parameter) VALUES ('100185','99250','29192','avg','5m');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Service','abc','99250','97');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('service','abcdef','99250','98');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Database','','99250','99');
INSERT INTO events (eventid,source,object,objectid,clock,ns,value,name,severity) VALUES (92,0,0,99250,1603456428,128786843,1,'Test trigger to check tag filter on problem page',3);
INSERT INTO event_tag (eventtagid,eventid,tag,value) VALUES (90,92,'Service','abc'),(91,92,'service','abcdef'),(92,92,'Database',''),(98,92,'Tag4',''),(99,92,'Tag5','5');
INSERT INTO problem (eventid,source,object,objectid,clock,ns,name,severity) VALUES (92,0,0,99250,1603456428,128786843,'Test trigger to check tag filter on problem page',3);
INSERT INTO problem_tag (problemtagid,eventid,tag,value) VALUES (90,92,'Service','abc'),(91,92,'service','abcdef'),(92,92,'Database',''),(98,92,'Tag4',''),(99,92,'Tag5','5');

INSERT INTO triggers (description,expression,recovery_mode,type,url,priority,comments,manual_close,status,correlation_mode,recovery_expression,correlation_tag,triggerid) VALUES ('Test trigger with tag','{100186}>100','0','0','','2','','1','0','0','','','99251');
INSERT INTO functions (functionid,triggerid,itemid,name,parameter) VALUES ('100186','99251','29192','avg','5m');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Service','abc','99251','100');
INSERT INTO events (eventid,source,object,objectid,clock,ns,value,name,severity) VALUES (93,0,0,99251,1603466628,128786843,1,'Test trigger with tag',2);
INSERT INTO event_tag (eventtagid,eventid,tag,value) VALUES (93,93,'Service','abc');
INSERT INTO problem (eventid,source,object,objectid,clock,ns,name,severity) VALUES (93,0,0,99251,1603466628,128786843,'Test trigger with tag',2);
INSERT INTO problem_tag (problemtagid,eventid,tag,value) VALUES (93,93,'Service','abc');

-- Tag based permissions
INSERT INTO usrgrp (usrgrpid, name) VALUES (90, 'Selenium user group for tag permissions AAA');
INSERT INTO usrgrp (usrgrpid, name) VALUES (91, 'Selenium user group for tag permissions BBB');
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (90, 'Tag-user', '$2y$10$UpgaksQrfBNgJVTZ8Zy53eVE6gaRcGhh1WQZojBAw2GGGh3ZXIoSi', 0, 0, 'en_GB', 30, 1, 'default', 0, 0, 50);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (90, 90, 90);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (91, 91, 90);
-- Tag based permissions: host group, host, item, two triggers
INSERT INTO hstgrp (groupid, name, internal) VALUES (50004, 'Host group for tag permissions', 0);
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50009, 'Host for tag permissions', 'Host for tag permissions', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (90280, 50009, 50004);
INSERT INTO interface (type, ip, dns, useip, port, main, hostid, interfaceid) VALUES (1, '127.0.0.1', '', '1', '10050', '1', 50009, 50022);
INSERT INTO items (itemid, name, key_, hostid, interfaceid, delay, value_type, params, description, posts, headers) VALUES (40066, 'tag.item', 'tag.key', 50009, 50022, '30s', 3, '', '', '', '');
INSERT INTO triggers (triggerid, description, expression, value, state, lastchange, comments) VALUES (100027, 'Trigger for tag permissions MySQL', '{13083}=0', 0, 1, '1339761311', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100028, 40066, 100027, 'last', '0');
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (101, 'Service','MySQL', 100027);
INSERT INTO triggers (triggerid, description, expression, value, state, lastchange, comments) VALUES (100028, 'Trigger for tag permissions Oracle', '{13083}=0', 0, 1, '1339761311', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100029, 40066, 100028, 'last', '0');
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (102, 'Service','Oracle', 100028);
-- Tag based permissions: triggers problems events
INSERT INTO events (eventid,source,object,objectid,clock,ns,value,name) VALUES (94,0,0,100027,1603456528,128786843,1,'Trigger for tag permissions MySQL');
INSERT INTO event_tag (eventtagid,eventid,tag,value) VALUES (94,94,'Service','MySQL');
INSERT INTO problem (eventid,source,object,objectid,clock,ns,name) VALUES (94,0,0,100027,1603456528,128786843,'Trigger for tag permissions MySQL');
INSERT INTO problem_tag (problemtagid,eventid,tag,value) VALUES (94,94,'Service','MySQL');
INSERT INTO events (eventid,source,object,objectid,clock,ns,value,name) VALUES (95,0,0,100028,1603466728,128786843,1,'Trigger for tag permissions Oracle');
INSERT INTO event_tag (eventtagid,eventid,tag,value) VALUES (95,95,'Service','Oracle');
INSERT INTO problem (eventid,source,object,objectid,clock,ns,name) VALUES (95,0,0,100028,1603466728,128786843,'Trigger for tag permissions Oracle');
INSERT INTO problem_tag (problemtagid,eventid,tag,value) VALUES (95,95,'Service','Oracle');
-- Tag based permissions: Read-write permissions to host group
INSERT INTO rights (rightid,groupid,permission,id) VALUES (1,90,3,50004);
INSERT INTO rights (rightid,groupid,permission,id) VALUES (2,91,3,50004);

-- event correlation
INSERT INTO correlation (correlationid, name, description, evaltype, status, formula) VALUES (99000, 'Event correlation for delete', 'Test description delete', 0, 0, '');
INSERT INTO corr_condition (corr_conditionid, correlationid, type) VALUES (99000, 99000, 0);
INSERT INTO corr_condition_tag (corr_conditionid, tag) VALUES (99000, 'delete tag');
INSERT INTO corr_operation (corr_operationid, correlationid, type) VALUES (99000, 99000, 0);

INSERT INTO correlation (correlationid, name, description, evaltype, status, formula) VALUES (99001, 'Event correlation for update', 'Test description update', 0, 0, '');
INSERT INTO corr_condition (corr_conditionid, correlationid, type) VALUES (99001, 99001, 0);
INSERT INTO corr_condition_tag (corr_conditionid, tag) VALUES (99001, 'update tag');
INSERT INTO corr_operation (corr_operationid, correlationid, type) VALUES (99001, 99001, 0);

INSERT INTO correlation (correlationid, name, description, evaltype, status, formula) VALUES (99002, 'Event correlation for cancel', 'Test description cancel', 1, 1, '');
INSERT INTO corr_condition (corr_conditionid, correlationid, type) VALUES (99002, 99002, 0);
INSERT INTO corr_condition_tag (corr_conditionid, tag) VALUES (99002, 'cancel tag');
INSERT INTO corr_operation (corr_operationid, correlationid, type) VALUES (99002, 99002, 0);

INSERT INTO correlation (correlationid, name, description, evaltype, status, formula) VALUES (99003, 'Event correlation for clone', 'Test description clone', 0, 0, '');
INSERT INTO corr_condition (corr_conditionid, correlationid, type) VALUES (99003, 99003, 0);
INSERT INTO corr_condition_tag (corr_conditionid, tag) VALUES (99003, 'clone tag');
INSERT INTO corr_operation (corr_operationid, correlationid, type) VALUES (99003, 99003, 0);

-- host prototypes
INSERT INTO hosts (hostid, host, name, status, description, flags) VALUES (90001, 'Host for host prototype tests', 'Host for host prototype tests', 0, '', 0);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99000, 90001, 4);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50024,90001,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (name, key_, hostid, value_type, itemid, interfaceid, flags, delay, params, description, posts, headers) VALUES ('Discovery rule 1', 'key1', 90001, 4, 90001, 50024, 1, '30s', '', '', '', '');
INSERT INTO items (name, key_, hostid, value_type, itemid, interfaceid, flags, delay, params, description, posts, headers) VALUES ('Discovery rule 2', 'key2', 90001, 4, 90002, 50024, 1, '30s', '', '', '', '');
INSERT INTO items (name, key_, hostid, value_type, itemid, interfaceid, flags, delay, params, description, posts, headers) VALUES ('Discovery rule 3', 'key3', 90001, 4, 90003, 50024, 1, '30s', '', '', '', '');
INSERT INTO hosts (hostid, host, name, status, description, flags) VALUES (90002, 'Host prototype {#1}', 'Host prototype {#1}', 0, '', 2);
INSERT INTO hosts (hostid, host, name, status, description, flags) VALUES (90003, 'Host prototype {#2}', 'Host prototype {#2}', 1, '', 2);
INSERT INTO hosts (hostid, host, name, status, description, flags) VALUES (90004, 'Host prototype {#3}', 'Host prototype {#3}', 0, '', 2);
INSERT INTO hosts (hostid, host, name, status, description, flags) VALUES (90005, 'Host prototype {#4}', 'Host prototype {#4}', 0, '', 2);
INSERT INTO hosts (hostid, host, name, status, description, flags) VALUES (90006, 'Host prototype {#5}', 'Host prototype {#5}', 0, '', 2);
INSERT INTO hosts (hostid, host, name, status, description, flags) VALUES (90007, 'Host prototype {#6}', 'Host prototype {#6}', 0, '', 2);
INSERT INTO hosts (hostid, host, name, status, description, flags) VALUES (90008, 'Host prototype {#7}', 'Host prototype {#7}', 0, '', 2);
INSERT INTO hosts (hostid, host, name, status, description, flags) VALUES (90009, 'Host prototype {#8}', 'Host prototype {#8}', 0, '', 2);
INSERT INTO hosts (hostid, host, name, status, description, flags) VALUES (90010, 'Host prototype {#9}', 'Host prototype {#9}', 0, '', 2);
INSERT INTO hosts (hostid, host, name, status, description, flags) VALUES (90011, 'Host prototype {#10}', 'Host prototype {#10}', 0, '', 2);
INSERT INTO hosts (hostid, host, name, status, description, flags) VALUES (90012, 'Host prototype {#33}', 'Host prototype visible name', 0, '', 2);
INSERT INTO host_discovery (hostid, parent_itemid) VALUES (90002, 90001);
INSERT INTO host_discovery (hostid, parent_itemid) VALUES (90003, 90001);
INSERT INTO host_discovery (hostid, parent_itemid) VALUES (90004, 90001);
INSERT INTO host_discovery (hostid, parent_itemid) VALUES (90012, 90001);
INSERT INTO host_discovery (hostid, parent_itemid) VALUES (90005, 90002);
INSERT INTO host_discovery (hostid, parent_itemid) VALUES (90006, 90002);
INSERT INTO host_discovery (hostid, parent_itemid) VALUES (90007, 90002);
INSERT INTO host_discovery (hostid, parent_itemid) VALUES (90008, 90003);
INSERT INTO host_discovery (hostid, parent_itemid) VALUES (90009, 90003);
INSERT INTO host_discovery (hostid, parent_itemid) VALUES (90010, 90003);
INSERT INTO host_discovery (hostid, parent_itemid) VALUES (90011, 90003);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1000, 90002, '', 5, NULL);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1001, 90003, '', 5, NULL);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1019, 90003, '{#FSNAME}', NULL, NULL);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1020, 90012, '', 5, NULL);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1002, 90004, '', 5, NULL);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1003, 90005, '', 5, NULL);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1004, 90006, '', 5, NULL);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1005, 90007, '', 5, NULL);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1006, 90008, '', 5, NULL);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1007, 90009, '', 5, NULL);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1008, 90010, '', 5, NULL);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1009, 90011, '', 5, NULL);

INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (50003, 90003, 10001);

INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (99009, 90012, '{$PROTOYPE_MACRO_1}', 'Prototype macro value 1', 'Prototype macro description 1');
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (99010, 90012, '{$PROTOYPE_MACRO_2}', 'Prototype macro value 2', 'Prototype macro description 2');

-- testInheritanceHostPrototype
INSERT INTO hstgrp (groupid, name, internal) VALUES (50019, 'Inheritance test', 0);
INSERT INTO hosts (hostid, host, name, flags, templateid, description) VALUES (99000, 'testInheritanceHostPrototype {#TEST}', 'testInheritanceHostPrototype {#TEST}', 2, NULL, '');
INSERT INTO hosts (hostid, host, name, flags, templateid, description) VALUES (99001, 'testInheritanceHostPrototype {#TEST}', 'testInheritanceHostPrototype {#TEST}', 2, 99000, '');
INSERT INTO host_discovery (hostid, parent_hostid, parent_itemid, host, lastcheck, ts_delete) VALUES (99000, NULL, 15011, '', 0, 0);
INSERT INTO host_discovery (hostid, parent_hostid, parent_itemid, host, lastcheck, ts_delete) VALUES (99001, NULL, 15016, '', 0, 0);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1010, 99000, '', 50019, NULL);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1011, 99001, '', 50019, 1010);
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99006, 'Inheritance test template with host prototype', 'Inheritance test template with host prototype', 3, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99006, 99006, 50019);
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, formula, params, description, posts, headers, flags) VALUES (99083, 99006, 2, 'Discovery rule for host prototype test', 'key_test', '30s', 4, '', '', '', '', '', 1);
INSERT INTO hosts (hostid, host, name, status, description, flags) VALUES (99007, 'Host prototype for update {#TEST}', 'Host prototype for update {#TEST}', 0, '', 2);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1012, 99007, '', 50019, NULL);
INSERT INTO host_discovery (hostid, parent_hostid, parent_itemid, host, lastcheck, ts_delete) VALUES (99007, NULL, 99083, '', 0, 0);
INSERT INTO hosts (hostid, host, name, status, description, flags) VALUES (99009, 'Host prototype for delete {#TEST}', 'Host prototype for delete {#TEST}', 0, '', 2);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1013, 99009, '', 50019, NULL);
INSERT INTO host_discovery (hostid, parent_hostid, parent_itemid, host, lastcheck, ts_delete) VALUES (99009, NULL, 99083, '', 0, 0);
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99004, 'Host for inheritance host prototype tests', 'Host for inheritance host prototype tests', 0, '');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) VALUES (10026,99004,1,1,1,'127.0.0.1','','10050');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99004, 99004, 50019);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (15004, 99004, 99006);
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, formula, params, description, posts, headers, templateid, flags) VALUES (99084, 99004, 2, 'Discovery rule for host prototype test', 'key_test', '30s', 4, '', '', '', '', '', 99083, 1);
INSERT INTO hosts (hostid, host, name, status, description, templateid, flags) VALUES (99008, 'Host prototype for update {#TEST}', 'Host prototype for update {#TEST}', 0, '', 99007, 2);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1014, 99008, '', 50019, 1002);
INSERT INTO host_discovery (hostid, parent_hostid, parent_itemid, host, lastcheck, ts_delete) VALUES (99008, NULL, 99084, '', 0, 0);
INSERT INTO hosts (hostid, host, name, status, description, templateid, flags) VALUES (99010, 'Host prototype for delete {#TEST}', 'Host prototype for delete {#TEST}', 0, '', 99009, 2);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1015, 99010, '', 50019, 1004);
INSERT INTO host_discovery (hostid, parent_hostid, parent_itemid, host, lastcheck, ts_delete) VALUES (99010, NULL, 99084, '', 0, 0);

INSERT INTO hosts (hostid, host, name, status, description, flags) VALUES (99060, 'Host prototype for Clone {#TEST}', 'Host prototype for Clone {#TEST}', 1, '', 2);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1023, 99060, '', 50019, NULL);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1024, 99060, '{#GROUP_PROTO}',NULL, NULL);
INSERT INTO host_discovery (hostid, parent_hostid, parent_itemid, host, lastcheck, ts_delete) VALUES (99060, NULL, 99083, '', 0, 0);

INSERT INTO hosts (hostid, host, name, status, description, templateid, flags) VALUES (99055, 'Host prototype for Clone {#TEST}', 'Host prototype for Clone {#TEST}', 1, '', 99060, 2);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1025, 99055, '', 50019, 1024);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1026, 99055, '{#GROUP_PROTO}',NULL, 1023);
INSERT INTO host_discovery (hostid, parent_hostid, parent_itemid, host, lastcheck, ts_delete) VALUES (99055, NULL, 99084, '', 0, 0);

-- testFormItemHttpAgent
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50010, 'Host for different item types', 'Host for different items types', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (90281, 50010, 4);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50023,50010,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99004, 19, 50010, 'Http agent item form', '', 'http-item-form', 30, 50023, '', '', 'zabbix.com', '', '[{"user":"admin"}]','Content-Type: text/plain');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99005, 19, 50010, 'Http agent item for update', '', 'http-item-update', 30, 50023, '', '', 'zabbix.com', '', '[{"user":"admin"}]','Content-Type: text/plain');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, headers) VALUES (99006, 19, 50010, 'Http agent item for delete', '', 'http-item-delete', 30, 50023, '', '', 'zabbix.com', '', '');

-- testInheritanceApplication
INSERT INTO applications (applicationid, hostid, name) VALUES (99001, 15000, 'Inheritance application');
INSERT INTO applications (applicationid, hostid, name) VALUES (99002, 15001, 'Inheritance application');
INSERT INTO application_template (application_templateid, applicationid, templateid) VALUES (900, 99002, 99001);
INSERT INTO applications (applicationid, hostid, name) VALUES (99003, 15000, 'Inheritance application for delete without items');
INSERT INTO applications (applicationid, hostid, name) VALUES (99004, 15001, 'Inheritance application for delete without items');
INSERT INTO application_template (application_templateid, applicationid, templateid) VALUES (901, 99004, 99003);
INSERT INTO applications (applicationid, hostid, name) VALUES (99005, 15000, 'Inheritance application for delete with items');
INSERT INTO applications (applicationid, hostid, name) VALUES (99006, 15001, 'Inheritance application for delete with items');
INSERT INTO application_template (application_templateid, applicationid, templateid) VALUES (902, 99006, 99005);
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history, status, params, description, flags, posts, headers) VALUES (99085, 15001, 1, 0, 2, 'Item for testInheritanceApplication','item-with-inheritance-app', '30s', '90d', 0, '', '', 0, '', '');
INSERT INTO items_applications (itemappid, applicationid, itemid) VALUES (50001, 99006, 99085);
INSERT INTO applications (applicationid, hostid, name) VALUES (99007, 15000, 'Inheritance application for update');
INSERT INTO applications (applicationid, hostid, name) VALUES (99008, 15001, 'Inheritance application for update');
INSERT INTO application_template (application_templateid, applicationid, templateid) VALUES (903, 99008, 99007);
INSERT INTO applications (applicationid, hostid, name) VALUES (99009, 15001, 'Application on host');

-- testPageProblems_TagPriority
INSERT INTO triggers (description,expression,recovery_mode,type,url,priority,comments,manual_close,status,correlation_mode,recovery_expression,correlation_tag,triggerid) VALUES ('First test trigger with tag priority','{100181}>100','0','1','','2','','1','0','0','','','99252');
INSERT INTO functions (functionid,triggerid,itemid,name,parameter) VALUES ('100181','99252','29192','avg','5m');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Delta','d','99252','105');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Beta','b','99252','106');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Alpha','a','99252','107');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Gamma','g','99252','108');
INSERT INTO events (eventid,source,object,objectid,clock,ns,value,name,severity) VALUES (96,0,0,99252,1534495628,128786843,1,'First test trigger with tag priority',2);
INSERT INTO event_tag (eventtagid,eventid,tag,value) VALUES (100,96,'Delta','d');
INSERT INTO event_tag (eventtagid,eventid,tag,value) VALUES (101,96,'Beta','b');
INSERT INTO event_tag (eventtagid,eventid,tag,value) VALUES (102,96,'Alpha','a');
INSERT INTO event_tag (eventtagid,eventid,tag,value) VALUES (103,96,'Gamma','g');
INSERT INTO problem (eventid,source,object,objectid,clock,ns,name,severity) VALUES (96,0,0,99252,1534495628,128786843,'First test trigger with tag priority',2);
INSERT INTO problem_tag (problemtagid,eventid,tag,value) VALUES (100,96,'Delta','d');
INSERT INTO problem_tag (problemtagid,eventid,tag,value) VALUES (101,96,'Beta','b');
INSERT INTO problem_tag (problemtagid,eventid,tag,value) VALUES (102,96,'Alpha','a');
INSERT INTO problem_tag (problemtagid,eventid,tag,value) VALUES (103,96,'Gamma','g');

INSERT INTO triggers (description,expression,recovery_mode,type,url,priority,comments,manual_close,status,correlation_mode,recovery_expression,correlation_tag,triggerid) VALUES ('Second test trigger with tag priority','{100182}>100','0','1','','2','','1','0','0','','','99253');
INSERT INTO functions (functionid,triggerid,itemid,name,parameter) VALUES ('100182','99253','29192','avg','5m');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Zeta','z','99253','109');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Beta','b','99253','110');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Epsilon','e','99253','111');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Eta','e','99253','112');
INSERT INTO events (eventid,source,object,objectid,clock,ns,value,name,severity) VALUES (97,0,0,99253,1534495628,128786843,1,'Second test trigger with tag priority',2);
INSERT INTO event_tag (eventtagid,eventid,tag,value) VALUES (104,97,'Zeta','z');
INSERT INTO event_tag (eventtagid,eventid,tag,value) VALUES (105,97,'Beta','b');
INSERT INTO event_tag (eventtagid,eventid,tag,value) VALUES (106,97,'Epsilon','e');
INSERT INTO event_tag (eventtagid,eventid,tag,value) VALUES (107,97,'Eta','e');
INSERT INTO problem (eventid,source,object,objectid,clock,ns,name,severity) VALUES (97,0,0,99253,1534495628,128786843,'Second test trigger with tag priority',2);
INSERT INTO problem_tag (problemtagid,eventid,tag,value) VALUES (104,97,'Zeta','z');
INSERT INTO problem_tag (problemtagid,eventid,tag,value) VALUES (105,97,'Beta','b');
INSERT INTO problem_tag (problemtagid,eventid,tag,value) VALUES (106,97,'Epsilon','e');
INSERT INTO problem_tag (problemtagid,eventid,tag,value) VALUES (107,97,'Eta','e');

INSERT INTO triggers (description,expression,recovery_mode,type,url,priority,comments,manual_close,status,correlation_mode,recovery_expression,correlation_tag,triggerid) VALUES ('Third test trigger with tag priority','{100183}>100','0','1','','2','','1','0','0','','','99254');
INSERT INTO functions (functionid,triggerid,itemid,name,parameter) VALUES ('100183','99254','29192','avg','5m');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Kappa','k','99254','113');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Iota','i','99254','114');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Alpha','a','99254','115');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Theta','t','99254','116');
INSERT INTO events (eventid,source,object,objectid,clock,ns,value,name,severity) VALUES (98,0,0,99254,1534495628,128786843,1,'Third test trigger with tag priority',2);
INSERT INTO event_tag (eventtagid,eventid,tag,value) VALUES (108,98,'Kappa','k');
INSERT INTO event_tag (eventtagid,eventid,tag,value) VALUES (109,98,'Iota','i');
INSERT INTO event_tag (eventtagid,eventid,tag,value) VALUES (110,98,'Alpha','a');
INSERT INTO event_tag (eventtagid,eventid,tag,value) VALUES (111,98,'Theta','t');
INSERT INTO problem (eventid,source,object,objectid,clock,ns,name,severity) VALUES (98,0,0,99253,1534495628,128786843,'Third test trigger with tag priority',2);
INSERT INTO problem_tag (problemtagid,eventid,tag,value) VALUES (108,98,'Kappa','k');
INSERT INTO problem_tag (problemtagid,eventid,tag,value) VALUES (109,98,'Iota','i');
INSERT INTO problem_tag (problemtagid,eventid,tag,value) VALUES (110,98,'Alpha','a');
INSERT INTO problem_tag (problemtagid,eventid,tag,value) VALUES (111,98,'Theta','t');

INSERT INTO triggers (description,expression,recovery_mode,type,url,priority,comments,manual_close,status,correlation_mode,recovery_expression,correlation_tag,triggerid) VALUES ('Fourth test trigger with tag priority','{100184}>100','0','1','','2','','1','0','0','','','99255');
INSERT INTO functions (functionid,triggerid,itemid,name,parameter) VALUES ('100184','99255','29192','avg','5m');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Eta','e','99255','117');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Gamma','g','99255','118');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Theta','t','99255','119');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Delta','d','99255','120');
INSERT INTO events (eventid,source,object,objectid,clock,ns,value,name,severity) VALUES (99,0,0,99254,1534495628,128786843,1,'Fourth test trigger with tag priority',2);
INSERT INTO event_tag (eventtagid,eventid,tag,value) VALUES (112,99,'Eta','e');
INSERT INTO event_tag (eventtagid,eventid,tag,value) VALUES (113,99,'Gamma','g');
INSERT INTO event_tag (eventtagid,eventid,tag,value) VALUES (114,99,'Theta','t');
INSERT INTO event_tag (eventtagid,eventid,tag,value) VALUES (115,99,'Delta','t');
INSERT INTO problem (eventid,source,object,objectid,clock,ns,name,severity) VALUES (99,0,0,99253,1534495628,128786843,'Fourth test trigger with tag priority',2);
INSERT INTO problem_tag (problemtagid,eventid,tag,value) VALUES (112,99,'Eta','e');
INSERT INTO problem_tag (problemtagid,eventid,tag,value) VALUES (113,99,'Gamma','g');
INSERT INTO problem_tag (problemtagid,eventid,tag,value) VALUES (114,99,'Theta','t');
INSERT INTO problem_tag (problemtagid,eventid,tag,value) VALUES (115,99,'Delta','t');

-- Problem suppression test: host, item, trigger, maintenance, event, problem, tags
INSERT INTO hstgrp (groupid, name, internal) VALUES (50013, 'Host group for suppression', 0);
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99011, 'Host for suppression', 'Host for suppression', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99007, 99011, 50013);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50025,99011,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99087, 2, 99011, 'Trapper_for_suppression', '', 'trapper_sup', 30, NULL, '', '', '', '', '','');
INSERT INTO triggers (triggerid, description, expression, value, priority, state, lastchange, comments) VALUES (100031, 'Trigger_for_suppression', '{100031}>0', 1, 3, 0, '1535012391', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100031, 99087, 100031, 'last', '0');
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (104, 'SupTag','A', 100031);

INSERT INTO maintenances (maintenanceid, name, maintenance_type, description, active_since, active_till,tags_evaltype) VALUES (4,'Maintenance for suppression test',0,'',1534971600,2147378400,2);
INSERT INTO maintenances_hosts (maintenance_hostid, maintenanceid, hostid) VALUES (3,4,99011);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (12,0,1,0,0,1,43200,86399940,1535021880);
INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (12,4,12);
INSERT INTO maintenance_tag (maintenancetagid, maintenanceid, tag, operator,value) VALUES (3,4,'SupTag',2,'A');

INSERT INTO events (eventid,source,object,objectid,clock,ns,value,name,severity) VALUES (175,0,0,100031,1535012391,445429746,1,'Trigger_for_suppression',3);
INSERT INTO event_tag (eventtagid,eventid,tag,value) VALUES (200,175,'SupTag','A');
INSERT INTO event_suppress (event_suppressid,eventid,maintenanceid,suppress_until) VALUES (1,175,4,1621329420);
INSERT INTO problem (eventid,source,object,objectid,clock,ns,name,severity) VALUES (175,0,0,100031,1535012391,445429746,'Trigger_for_suppression',3);
INSERT INTO problem_tag (problemtagid,eventid,tag,value) VALUES (200,175,'SupTag','A');

-- testPageHostGraph
INSERT INTO hstgrp (groupid,name,internal) VALUES (50005,'Group for host graph check',0);
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99012, 'Host to check graph 1', 'Host to check graph 1', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99008, 99012, 50005);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50026,99012,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99007, 2, 99012, 'Item to check graph', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (700018,'Check graph 1',900,200,0.0,100.0,NULL,1,1,0,1,0,0.0,0.0,0,0,NULL,NULL,0);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (700019,'Check graph 2',900,200,0.0,100.0,NULL,1,1,0,1,0,0.0,0.0,0,0,NULL,NULL,0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700026, 700018, 99007, 0, 0, '1A7C11', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700027, 700019, 99007, 0, 0, '1A7C11', 0, 2, 0);
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99013, 'Host to delete graphs', 'Host to delete graphs', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99009, 99013, 50005);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50027,99013,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99008, 2, 99013, 'Item to delete graph', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (700020,'Delete graph 1',900,200,0.0,100.0,NULL,1,1,0,1,0,0.0,0.0,0,0,NULL,NULL,0);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (700021,'Delete graph 2',900,200,0.0,100.0,NULL,1,1,0,1,0,0.0,0.0,0,0,NULL,NULL,0);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (700022,'Delete graph 3',900,200,0.0,100.0,NULL,1,1,0,1,0,0.0,0.0,0,0,NULL,NULL,0);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (700023,'Delete graph 4',900,200,0.0,100.0,NULL,1,1,0,1,0,0.0,0.0,0,0,NULL,NULL,0);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (700024,'Delete graph 5',900,200,0.0,100.0,NULL,1,1,0,1,0,0.0,0.0,0,0,NULL,NULL,0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700028, 700020, 99008, 0, 0, '1A7C11', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700029, 700021, 99008, 0, 0, '1A7C11', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700030, 700022, 99008, 0, 0, '1A7C11', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700031, 700023, 99008, 0, 0, '1A7C11', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700032, 700024, 99008, 0, 0, '1A7C11', 0, 2, 0);
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99014, 'Empty template', 'Empty template', 3, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99010, 99014, 1);
INSERT INTO hstgrp (groupid,name,internal) VALUES (50006,'Empty group',0);
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99015, 'Empty host', 'Empty host', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99011, 99015, 50006);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50028,99015,1,1,1,'127.0.0.1','','10050');
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99016, 'Template to test graphs', 'Template to test graphs', 3, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99012, 99016, 1);
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99009, 2, 99016, 'Item to check graph', '', 'graph[2]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (700025,'Graph to check copy',900,200,0.0,100.0,NULL,1,1,0,1,0,0.0,0.0,0,0,NULL,NULL,0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700033, 700025, 99009, 0, 0, '1A7C11', 0, 2, 0);
INSERT INTO hstgrp (groupid,name,internal) VALUES (50007,'Group to copy graph',0);
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99017, 'Host with item and without graph 1', 'Host with item and without graph 1', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99013, 99017, 50007);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50029,99017,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99010, 2, 99017, 'Item', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99018, 'Host with item and without graph 2', 'Host with item and without graph 2', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99014, 99018, 50007);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50030,99018,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99011, 2, 99018, 'Item', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO hstgrp (groupid,name,internal) VALUES (50008,'Group to copy all graph',0);
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99019, 'Host with item to copy all graphs 1', 'Host with item to copy all graphs 1', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99015, 99019, 50008);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50031,99019,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99012, 2, 99019, 'Item', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99020, 'Host with item to copy all graphs 2', 'Host with item to copy all graphs 2', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99016, 99020, 50008);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50032,99020,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99013, 2, 99020, 'Item', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99021, 'Host to check graph 2', 'Host to check graph 2', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99017, 99021, 50005);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50033,99021,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99014, 2, 99021, 'Item to check graph', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99022, 'Template with item graph', 'Template with item graph', 3, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99018, 99022, 1);
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99015, 2, 99022, 'Item', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99023, 'Template with item graph for copy all graph', 'Template with item graph for copy all graph', 3, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99019, 99023, 1);
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99016, 2, 99023, 'Item', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99029, 'Template to copy graph to several templates 1', 'Template to copy graph to several templates 1', 3, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99020, 99029, 1);
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99022, 2, 99029, 'Item', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99030, 'Template to copy graph to several templates 2', 'Template to copy graph to several templates 2', 3, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99021, 99030, 1);
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99023, 2, 99030, 'Item', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99024, 'Host to check graph 3', 'Host to check graph 3', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99022, 99024, 50005);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50034,99024,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99017, 2, 99024, 'Item to check graph', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99025, 'Host to check graph 4', 'Host to check graph 4', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99023, 99025, 50005);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50035,99025,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99018, 2, 99025, 'Item to check graph', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99026, 'Host to check graph 5', 'Host to check graph 5', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99024, 99026, 50005);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50036,99026,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99019, 2, 99026, 'Item to check graph', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO hstgrp (groupid,name,internal) VALUES (50009,'Copy graph to several groups 1',0);
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99027, 'Host 1 from first group', 'Host 1 from first group', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99025, 99027, 50009);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50037,99027,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99020, 2, 99027, 'Item to check graph', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO hstgrp (groupid,name,internal) VALUES (50010,'Copy graph to several groups 2',0);
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99028, 'Host 1 from second group', 'Host 1 from second group', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99026, 99028, 50010);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50038,99028,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99021, 2, 99028, 'Item to check graph', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');

-- testPageTriggers tags filtering test
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99050, 'Host for trigger tags filtering', 'Host for trigger tags filtering', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99910, 99050, 4);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (55030,99050,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99090, 2, 99050, 'Trapper', '', 'trap', 30, NULL, '', '', '', '', '','');

INSERT INTO triggers (triggerid, description, expression, value, priority, state, lastchange, comments) VALUES (100060, 'First trigger for tag filtering', '{100060}>0', 0, 1, 0, '0', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100060, 99090, 100060, 'last', '');
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (130, 'TagA','A', 100060);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (131, 'TagB','b', 100060);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (132, 'TagD','d', 100060);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (133, 'TagG','g', 100060);

INSERT INTO triggers (triggerid, description, expression, value, priority, state, lastchange, comments) VALUES (100061, 'Second trigger for tag filtering', '{100061}>0', 0, 2, 0, '0', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100061, 99090, 100061, 'last', '');
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (134, 'TagB','b', 100061);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (135, 'TagE','e', 100061);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (136, 'TagE1','e', 100061);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (137, 'TagZ','z', 100061);

INSERT INTO triggers (triggerid, description, expression, value, priority, state, lastchange, comments) VALUES (100062, 'Third trigger for tag filtering', '{100062}>0', 0, 3, 0, '0', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100062, 99090, 100062, 'last', '');
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (138, 'TagA','a', 100062);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (139, 'TagI','i', 100062);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (140, 'TagK','k', 100062);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (141, 'TagT','t', 100062);

INSERT INTO triggers (triggerid, description, expression, value, priority, state, lastchange, comments) VALUES (100063, 'Fourth trigger for tag filtering', '{100063}>0', 0, 4, 0, '0', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100063, 99090, 100063, 'last', '');
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (142, 'TagD','d', 100063);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (143, 'TagE1','e', 100063);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (144, 'TagG','g', 100063);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (145, 'TagT','t', 100063);

INSERT INTO triggers (triggerid, description, expression, value, priority, state, lastchange, comments) VALUES (100064, 'Fifth trigger for tag filtering (no tags)', '{100064}>0', 0, 5, 0, '0', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100064, 99090, 100064, 'last', '');

-- testPageMonitoringOverview
INSERT INTO hstgrp (groupid, name, internal) VALUES (50011, 'Group to check Overview', 0);
INSERT INTO hstgrp (groupid, name, internal) VALUES (50012, 'Another group to check Overview', 0);
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50011, '1_Host_to_check_Monitoring_Overview', '1_Host_to_check_Monitoring_Overview', 0, '');
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50012, '3_Host_to_check_Monitoring_Overview', '3_Host_to_check_Monitoring_Overview', 0, '');
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50013, '4_Host_to_check_Monitoring_Overview', '4_Host_to_check_Monitoring_Overview', 0, '');
INSERT INTO host_inventory (type, type_full, name, alias, os, os_full, os_short, serialno_a, serialno_b, tag, asset_tag, macaddress_a, macaddress_b, hardware, hardware_full, software, software_full, software_app_a, software_app_b, software_app_c, software_app_d, software_app_e, contact, location, location_lat, location_lon, notes, chassis, model, hw_arch, vendor, contract_number, installer_name, deployment_status, url_a, url_b, url_c, host_networks, host_netmask, host_router, oob_ip, oob_netmask, oob_router, date_hw_purchase, date_hw_install, date_hw_expiry, date_hw_decomm, site_address_a, site_address_b, site_address_c, site_city, site_state, site_country, site_zip, site_rack, site_notes, poc_1_name, poc_1_email, poc_1_phone_a, poc_1_phone_b, poc_1_cell, poc_1_screen, poc_1_notes, poc_2_name, poc_2_email, poc_2_phone_a, poc_2_phone_b, poc_2_cell, poc_2_screen, poc_2_notes, hostid) VALUES ('Type', 'Type (Full details)', 'Name', 'Alias', 'OS', 'OS (Full details)', 'OS (Short)', 'Serial number A', 'Serial number B', 'Tag','Asset tag', 'MAC address A', 'MAC address B', 'Hardware', 'Hardware (Full details)', 'Software', 'Software (Full details)', 'Software application A', 'Software application B', 'Software application C', 'Software application D', 'Software application E', 'Contact', 'Location', 'Location latitud', 'Location longitu', 'Notes', 'Chassis', 'Model', 'HW architecture', 'Vendor', 'Contract number', 'Installer name', 'Deployment status', 'URL A', 'URL B', 'URL C', 'Host networks', 'Host subnet mask', 'Host router', 'OOB IP address', 'OOB subnet mask', 'OOB router', 'Date HW purchased', 'Date HW installed', 'Date HW maintenance expires', 'Date hw decommissioned', 'Site address A', 'Site address B', 'Site address C', 'Site city', 'Site state / province', 'Site country', 'Site ZIP / postal', 'Site rack location', 'Site notes', 'Primary POC name', 'Primary POC email', 'Primary POC phone A', 'Primary POC phone B', 'Primary POC cell', 'Primary POC screen name', 'Primary POC notes', 'Secondary POC name', 'Secondary POC email', 'Secondary POC phone A', 'Secondary POC phone B', 'Secondary POC cell', 'Secondary POC screen name', 'Secondary POC notes', 50012);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (90282, 50011, 50011);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (90283, 50012, 50011);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (90284, 50013, 50012);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50039,50011,1,1,1,'127.0.0.1','','10050');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50040,50012,1,1,1,'127.0.0.1','','10050');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50041,50013,1,1,1,'127.0.0.1','','10050');
INSERT INTO applications (applicationid, hostid, name) VALUES (99010, 50011, '1 application');
INSERT INTO applications (applicationid, hostid, name) VALUES (99011, 50011, '2 application');
INSERT INTO applications (applicationid, hostid, name) VALUES (99012, 50012, '3 application');
INSERT INTO applications (applicationid, hostid, name) VALUES (99013, 50013, '4 application');
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history, status, params, description, flags, posts, headers) VALUES (99086, 50011, 50039, 2, 3, '1_item','trap[1]', '30s', '90d', 0, '', '', 0, '', '');
INSERT INTO items_applications (itemappid, applicationid, itemid) VALUES (50002, 99010, 99086);
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history, status, params, description, flags, posts, headers) VALUES (99091, 50011, 50039, 2, 3, '2_item','trap[2]', '30s', '90d', 0, '', '', 0, '', '');
INSERT INTO items_applications (itemappid, applicationid, itemid) VALUES (50003, 99011, 99091);
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history, status, params, description, flags, posts, headers) VALUES (99088, 50012, 50040, 2, 3, '3_item','trap[3]', '30s', '90d', 0, '', '', 0, '', '');
INSERT INTO items_applications (itemappid, applicationid, itemid) VALUES (50004, 99012, 99088);
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history, status, params, description, flags, posts, headers) VALUES (99089, 50013, 50041, 2, 3, '4_item','trap[4]', '30s', '90d', 0, '', '', 0, '', '');
INSERT INTO items_applications (itemappid, applicationid, itemid) VALUES (50005, 99013, 99089);
INSERT INTO triggers (triggerid, description, expression, value, state, lastchange, comments, priority, url) VALUES (100032, '1_trigger_Not_classified', '{100032}>0', 1, 0, '1533555726', 'Macro should be resolved, host IP should be visible here: {HOST.CONN}', 0, 'tr_events.php?triggerid={TRIGGER.ID}&eventid={EVENT.ID}');
INSERT INTO triggers (triggerid, description, expression, value, state, lastchange, comments, priority) VALUES (100033, '1_trigger_Warning', '{100033}>0', 1, 0, '1533555726', 'The following url should be clickable: https://zabbix.com', 2);
INSERT INTO triggers (triggerid, description, expression, value, state, lastchange, comments, priority, url) VALUES (100034, '1_trigger_Average', '{100034}>0', 1, 0, '1533555726', 'https://zabbix.com', 3, 'tr_events.php?triggerid={TRIGGER.ID}&eventid={EVENT.ID}');
INSERT INTO triggers (triggerid, description, expression, value, state, lastchange, comments, priority, url) VALUES (100035, '1_trigger_High', '{100035}>0', 1, 0, '1533555726', 'Non-clickable description', 4, 'tr_events.php?triggerid={TRIGGER.ID}&eventid={EVENT.ID}');
INSERT INTO triggers (triggerid, description, expression, value, state, lastchange, comments, priority) VALUES (100036, '1_trigger_Disaster', '{100036}>0', 1, 0, '1533555726', '', 5);
INSERT INTO triggers (triggerid, description, expression, value, state, lastchange, comments, priority) VALUES (100037, '2_trigger_Information', '{100037}>0', 1, 0, '1533555726', 'http://zabbix.com https://www.zabbix.com/career https://www.zabbix.com/contact', 1);
INSERT INTO triggers (triggerid, description, expression, value, state, lastchange, comments, priority) VALUES (100038, '3_trigger_Average', '{100038}>0', 1, 0, '1533555726', 'Macro - resolved, URL - clickable: {HOST.NAME}, https://zabbix.com', 3);
INSERT INTO triggers (triggerid, description, expression, value, state, lastchange, comments, priority, url) VALUES (100039, '3_trigger_Disaster', '{100039}>0', 0, 0, '1533555726', '', 5, 'triggers.php?form=update&triggerid={TRIGGER.ID}');
INSERT INTO triggers (triggerid, description, expression, value, state, lastchange, comments, priority) VALUES (100040, '4_trigger_Average', '{100040}>0', 1, 0, '1533555726', '', 3);
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100032, 99086, 100032, 'last', '0');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100033, 99086, 100033, 'last', '0');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100034, 99086, 100034, 'last', '0');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100035, 99086, 100035, 'last', '0');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100036, 99086, 100036, 'last', '0');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100037, 99091, 100037, 'last', '0');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100038, 99088, 100038, 'last', '0');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100039, 99088, 100039, 'last', '0');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100040, 99089, 100040, 'last', '0');
INSERT INTO history_uint (itemid, clock, value, ns) VALUES (99086, 1533555726, 1, 726692808);
INSERT INTO history_uint (itemid, clock, value, ns) VALUES (99091, 1533555726, 2, 726692808);
INSERT INTO history_uint (itemid, clock, value, ns) VALUES (99088, 1533555726, 3, 726692808);
INSERT INTO history_uint (itemid, clock, value, ns) VALUES (99089, 1533555726, 4, 726692808);
INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (9000, 0, 0, 100032, 1533555726, 726692808, 1, '1_trigger_Not_classified', 0);
INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (9001, 0, 0, 100033, 1533555726, 726692808, 1, '1_trigger_Warning', 2);
INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (9002, 0, 0, 100034, 1533555726, 726692808, 1, '1_trigger_Average', 3);
INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (9003, 0, 0, 100035, 1533555726, 726692808, 1, '1_trigger_High', 4);
INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (9004, 0, 0, 100036, 1533555726, 726692808, 1, '1_trigger_Disaster', 5);
INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity, acknowledged) VALUES (9005, 0, 0, 100037, 1533555726, 726692808, 1, '2_trigger_Information', 1, 1);
INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity, acknowledged) VALUES (9006, 0, 0, 100038, 1533555726, 726692808, 1, '3_trigger_Average', 3, 1);
INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (9007, 0, 0, 100040, 1533555726, 726692808, 1, '4_trigger_Average', 3);
INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES ( 9000, 0, 0, 100032, 1533555726, 726692808, '1_trigger_Not_classified', 0);
INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES ( 9001, 0, 0, 100033, 1533555726, 726692808, '1_trigger_Warning', 2);
INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES ( 9002, 0, 0, 100034, 1533555726, 726692808, '1_trigger_Average', 3);
INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES ( 9003, 0, 0, 100035, 1533555726, 726692808, '1_trigger_High', 4);
INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES ( 9004, 0, 0, 100036, 1533555726, 726692808, '1_trigger_Disaster', 5);
INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity, acknowledged) VALUES ( 9005, 0, 0, 100037, 1533555726, 726692808, '2_trigger_Information', 1, 1);
INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity, acknowledged) VALUES ( 9006, 0, 0, 100038, 1533555726, 726692808, '3_trigger_Average', 3, 1);
INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity, acknowledged) VALUES ( 9007, 0, 0, 100040, 1533555726, 726692808, '4_trigger_Average', 3, 1);
INSERT INTO acknowledges (acknowledgeid, userid, eventid, clock, message, action, old_severity, new_severity) VALUES (1, 1, 9005, 1533629135, '1 acknowledged', 2, 0, 0);
INSERT INTO acknowledges (acknowledgeid, userid, eventid, clock, message, action, old_severity, new_severity) VALUES (2, 1, 9006, 1533629135, '2 acknowledged', 2, 0, 0);
INSERT INTO task (taskid, type, status, clock, ttl, proxy_hostid) VALUES (1, 4, 1, 1533631968, 0, NULL);
INSERT INTO task (taskid, type, status, clock, ttl, proxy_hostid) VALUES (2, 4, 1, 1533631968, 0, NULL);
INSERT INTO task_acknowledge (taskid, acknowledgeid) VALUES (1, 1);
INSERT INTO task_acknowledge (taskid, acknowledgeid) VALUES (2, 2);

-- Hosts with proxies for Hosts filtering test
INSERT INTO hosts (hostid, host, status, description) VALUES (99051, 'Proxy_1 for filter', 5, '');
INSERT INTO hosts (hostid, host, status, description) VALUES (99052, 'Proxy_2 for filter', 5, '');
INSERT INTO hosts (hostid, proxy_hostid, host, name, status, description) VALUES (99053, 99051, 'Host_1 with proxy', 'Host_1 with proxy', 0, '');
INSERT INTO hosts (hostid, proxy_hostid, host, name, status, description) VALUES (99054, 99052, 'Host_2 with proxy', 'Host_2 with proxy', 0, '');
INSERT INTO interface (interfaceid, hostid, type, ip, useip, port, main) VALUES (55031, 99053, 1, '127.0.0.1', 1, '10050', 1);
INSERT INTO interface (interfaceid, hostid, type, ip, useip, port, main) VALUES (55032, 99054, 1, '127.0.0.1', 1, '10050', 1);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99911, 99053, 4);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99912, 99054, 4);

-- Dashboard for problem hosts widget
INSERT INTO dashboard (dashboardid, name, userid, private) VALUES (100, 'Dashboard for Problem hosts widget', 1, 1);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (100, 100, 'problemhosts', '', 0, 0, 8, 8);
INSERT INTO profiles (profileid,userid,idx,value_id,source,type) VALUES (4, 1, 'web.dashbrd.dashboardid', 1, '', 1);

-- testPageAvailabilityReport SLA reports
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50014, 'SLA reports host', 'SLA reports host', 0, '');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, port) VALUES (50042, 50014, 1, 1, 1, '127.0.0.1', '10051');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50013, 50014, 4);
INSERT INTO items (itemid, type, hostid, name, key_, params, description, posts, headers) VALUES (40067, 2, 50014, 'Item A', 'A', '', '', '', '');
INSERT INTO items (itemid, type, hostid, name, key_, params, description, posts, headers) VALUES (40068, 2, 50014, 'Item B', 'B', '', '', '', '');
INSERT INTO items (itemid, type, hostid, name, key_, params, description, posts, headers) VALUES (40069, 2, 50014, 'Item C', 'C', '', '', '', '');
INSERT INTO triggers (triggerid, expression, description, comments) VALUES (100001, '{16028}=0', 'A trigger', '');
INSERT INTO triggers (triggerid, expression, description, comments) VALUES (100002, '{16029}=0', 'B trigger', '');
INSERT INTO triggers (triggerid, expression, description, comments) VALUES (100003, '{16030}=0', 'C trigger', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (16028, 40067, 100001,'last','0');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (16029, 40068, 100002,'last','0');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (16030, 40069, 100003,'last','0');

-- testPageTriggers triggers filtering
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99061, 'Inheritance template for triggers filtering', 'Inheritance template for triggers filtering', 3, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99913, 99061, 1);
INSERT INTO items (itemid, type, hostid, name, description, key_, interfaceid, params, posts, headers) VALUES (99092, 2, 99061, 'Inheritance item for triggers filtering', '', 'trap', NULL, '', '', '');
INSERT INTO triggers (triggerid, description, expression, priority, state, comments) VALUES (100065, 'Inheritance trigger with tags', '{100065}>0',3, 1, '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100065, 99092, 100065, 'last', '');
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (146, 'server','selenium', 100065);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (147, 'Street','dzelzavas', 100065);

INSERT INTO hosts (hostid, host, name, status, description) VALUES (99062, 'Host for triggers filtering', 'Host for triggers filtering', 0, '');
INSERT INTO hstgrp (groupid, name, internal) VALUES (50014,'Group to check triggers filtering',0);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99914, 99062, 50014);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (50004, 99062, 99061);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (55033, 99062, 1, 1, 1, '127.0.0.1', '', '10050');

INSERT INTO items (itemid, type, hostid, name, description, key_, interfaceid, params, posts, templateid, headers) VALUES (99093, 2, 99062, 'Inheritance item for triggers filtering', '', 'trap', NULL, '', '', 99092,'');
INSERT INTO items (itemid, type, hostid, name, description, key_, interfaceid, params, posts, headers) VALUES (99094, 2, 99062, 'Item for triggers filtering', '', 'trap1', NULL, '', '', '');

INSERT INTO triggers (triggerid, description, expression, value, comments, templateid, state, error) VALUES (100066, 'Inheritance trigger with tags', '{100067}=0', 1,'', 100065, 1, 'selenium trigger cannot be evaluated for some reason');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (100067, 100066, 99093, 'last', '');
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (148, 'server','selenium', 100066);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (149, 'Street','Dzelzavas', 100066);
INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (9008, 0, 0, 100066, 1535012391, 445429746,1, 'Inheritance trigger with tags', 3);
INSERT INTO event_tag (eventtagid, eventid, tag, value) VALUES (116, 9008, 'server', 'selenium');
INSERT INTO event_tag (eventtagid, eventid, tag, value) VALUES (117, 9008, 'Street', 'Dzelzavas');
INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES (9008, 0, 0, 100066, 1535012391, 445429746, 'Inheritance trigger with tags', 3);
INSERT INTO problem_tag (problemtagid, eventid, tag, value) VALUES (116, 9008, 'server', 'selenium');
INSERT INTO problem_tag (problemtagid, eventid, tag, value) VALUES (117, 9008, 'Street', 'Dzelzavas');
INSERT INTO triggers (triggerid, description, expression, status, value, priority, comments, state) VALUES (100067, 'Trigger disabled with tags', '{100067}>0', 1, 0, 3, '', 0);
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100068, 99094, 100067, 'last', '');
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (150, 'Street','Dzelzavas', 100067);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (151, 'country','latvia', 100067);
INSERT INTO trigger_depends (triggerdepid, triggerid_down, triggerid_up) VALUES (99000, 100066, 100067);
INSERT INTO triggers (triggerid, description, expression, status, value, priority, comments, state) VALUES (100070, 'Dependent trigger ONE', '{100067}>0', 0, 0, 4, '', 0);
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100071, 99094, 100070, 'last', '');
INSERT INTO trigger_depends (triggerdepid, triggerid_down, triggerid_up) VALUES (99001, 100070, 100067);

INSERT INTO items (itemid, type, hostid, name, description, key_, interfaceid, flags, params, posts, headers) VALUES (99095, 2, 99062, 'Discovery rule for triggers filtering', '', 'lld', NULL, 1,'','','');
INSERT INTO items (itemid, type, hostid, name, description, key_, interfaceid, flags, params, posts, headers) VALUES (99096, 2, 99062, 'Discovered item {#TEST}', '', 'lld[{#TEST}]', NULL, 2, '', '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid, lastcheck, ts_delete) VALUES (15085, 99096, 99095, 0, 0);
INSERT INTO items (itemid, type, hostid, name, description, key_, interfaceid, flags, params, posts, headers) VALUES (99097, 2, 99062, 'Discovered item one', '', 'lld[one]', NULL, 4, '', '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid, key_) values (15086, 99097, 99096, 'lld[one]');
INSERT INTO triggers (triggerid, description, expression, status, value, priority, comments, state, flags) VALUES (100068, 'Discovered trigger {#TEST}', '{100069}>0', 0, 0, 5, '', 0, 2);
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100069, 99096, 100068, 'last', '');
INSERT INTO triggers (triggerid, description, expression, status, value, priority, comments, state, flags) VALUES (100069, 'Discovered trigger one', '{100070}>0', 0, 0, 5, '', 0, 4);
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100070, 99097, 100069, 'last', '');
INSERT INTO trigger_discovery (triggerid, parent_triggerid) VALUES (100069, 100068);

-- testFormAdministrationMediaTypes
INSERT INTO media_type (mediatypeid, type, name, exec_path, status, script, description) VALUES (100, 1, 'Test script', 'Selenium test script', 1, '', '');

-- testFormUser
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, userid, private) VALUES (10, 'Public map with image', 800, 600, NULL, 0, 0, 1, 1, 1, 2, 1, 0);
INSERT INTO sysmaps_elements (selementid, sysmapid, elementid, elementtype, iconid_off, iconid_on, label, label_location, x, y, iconid_disabled, iconid_maintenance) VALUES (10,10,0,4,7,NULL,'Test phone icon',0,151,101,NULL,NULL);
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (91, 'http-auth-admin', '$2y$10$HuvU0X0vGitK8YhwyxILbOVU6oxYNF.BqsOhaieVBvDiGlxgxriay', 0, 0, 'en_GB', 30, 2, 'default', 0, 0, 50);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (92, 7, 91);

-- testHostAvailabilityWidget
INSERT INTO dashboard (dashboardid, name, userid, private) VALUES (101, 'Dashboard for Host availability widget', 1, 1);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (101, 101, 'hostavail', 'Reference HA widget', 0, 0, 6, 3);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (102, 101, 'hostavail', 'Reference HA widget to delete', 0, 3, 6, 3);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_groupid) VALUES (900, 102, 2, 'groupids', 0, 4);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (901, 102, 0, 'layout', 1);
INSERT INTO hstgrp (groupid,name,internal) VALUES (50015,'Group for Host availability widget',0);
INSERT INTO hstgrp (groupid,name,internal) VALUES (50016,'Group in maintenance for Host availability widget',0);

INSERT INTO maintenances (maintenanceid, name, maintenance_type, description, active_since, active_till,tags_evaltype) VALUES (5,'Maintenance for Host availability widget',0,'Maintenance for checking Show hosts in maintenance option in Host availability widget',1534971600,2147378400,0);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (14,0,1,0,0,1,43200,612406800,1534971600);
INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (14,5,14);
INSERT INTO maintenances_groups (maintenance_groupid, maintenanceid, groupid) VALUES (4,5,50016);

INSERT INTO hosts (hostid, host, name, status, available, ipmi_available, snmp_available, jmx_available, description) VALUES (99130, 'Not available host', 'Not available host', 0, 2, 2, 2, 2, 'Not available host for Host availability widget');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99050, 99130, 50015);
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55040, 99130, 1, '127.0.0.1', 'zabbixzabbixzabbix.com', '0', '10050', '1');
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55041, 99130, 2, '127.0.0.1', 'zabbixzabbixzabbix.com', '0', '10050', '1');
INSERT INTO interface_snmp (interfaceid, version, bulk, community) values (55041, 2, 1, '{$SNMP_COMMUNITY}');
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55042, 99130, 3, '127.0.0.1', 'zabbixzabbixzabbix.com', '0', '10050', '1');
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55043, 99130, 4, '127.0.0.1', 'zabbixzabbixzabbix.com', '0', '10050', '1');

INSERT INTO hosts (hostid, host, name, status, available, ipmi_available, snmp_available, jmx_available, description, maintenanceid, maintenance_status, maintenance_type, maintenance_from) VALUES (99131, 'Not available host in maintenance', 'Not available host in maintenance', 0, 2, 2, 2, 2, 'Not available host in maintenance for Host availability widget', 5, 1, 0, 1534971600);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99051, 99131, 50016);
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55044, 99131, 1, '127.0.0.1', 'zabbixzabbixzabbix.com', '0', '10050', '1');
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55045, 99131, 2, '127.0.0.1', 'zabbixzabbixzabbix.com', '0', '10050', '1');
INSERT INTO interface_snmp (interfaceid, version, bulk, community) values (55045, 2, 1, '{$SNMP_COMMUNITY}');
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55046, 99131, 3, '127.0.0.1', 'zabbixzabbixzabbix.com', '0', '10050', '1');
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55047, 99131, 4, '127.0.0.1', 'zabbixzabbixzabbix.com', '0', '10050', '1');

INSERT INTO hosts (hostid, host, name, status, available, description) VALUES (99132, 'Unknown host', 'Unknown host', 0, 0,'Unknown host for Host availability widget');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99052, 99132, 50015);
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55048, 99132, 1, '127.0.0.1', 'zabbixzabbixzabbix.com', '0', '10050', '1');

INSERT INTO hosts (hostid, host, name, status, available, description, maintenanceid, maintenance_status, maintenance_type, maintenance_from) VALUES (99133, 'Unknown host in maintenance', 'Unknown host in maintenance', 0, 0,'Unknown host for Host availability widget in maintenance', 5, 1, 0, 1534971600);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99053, 99133, 50016);
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55049, 99133, 1, '127.0.0.1', 'zabbixzabbixzabbix.com', '0', '10050', '1');

INSERT INTO hosts (hostid, host, name, status, available, ipmi_available, snmp_available, jmx_available, description) VALUES (99134, 'Available host', 'Available host', 0, 1, 1, 1, 1, 'Available host for Host availability widget');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99054, 99134, 50015);
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55050, 99134, 1, '127.0.0.1', '', '1', '10050', '1');
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55051, 99134, 2, '127.0.0.1', 'zabbixzabbixzabbix.com', '0', '10050', '1');
INSERT INTO interface_snmp (interfaceid, version, bulk, community) values (55051, 2, 1, '{$SNMP_COMMUNITY}');
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55052, 99134, 3, '127.0.0.1', 'zabbixzabbixzabbix.com', '0', '10050', '1');
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55053, 99134, 4, '127.0.0.1', 'zabbixzabbixzabbix.com', '0', '10050', '1');

INSERT INTO hosts (hostid, host, name, status, available, ipmi_available, snmp_available, jmx_available, description, maintenanceid, maintenance_status, maintenance_type, maintenance_from) VALUES (99135, 'Available host in maintenance', 'Available host in maintenance', 0, 1, 1, 1, 1, 'Available host in maintenance for Host availability widget', 5, 1, 0, 1534971600);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99055, 99135, 50016);
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55054, 99135, 1, '127.0.0.1', '', '1', '10050', '1');
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55055, 99135, 2, '127.0.0.1', '', '1', '10050', '1');
INSERT INTO interface_snmp (interfaceid, version, bulk, community) values (55055, 2, 1, '{$SNMP_COMMUNITY}');
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55056, 99135, 3, '127.0.0.1', '', '1', '10050', '1');
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55057, 99135, 4, '127.0.0.1', '', '1', '10050', '1');

-- testHostMacros
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (90100, 20006, '{$MACRO1}', '', '');
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (90101, 20006, '{$MACRO2}', '', '');

INSERT INTO hosts (hostid, host, name, status, description) VALUES (30010, 'Host for macros remove', 'Host for macros remove', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (90900, 30010, 4);
INSERT INTO interface (type, ip, dns, useip, port, main, hostid, interfaceid) VALUES (1, '127.0.0.1', '', '1', '10050', '1', 30010, 20030);
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (90102, 30010, '{$MACRO_FOR_REMOVE1}', '', '');
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (90103, 30010, '{$MACRO_FOR_REMOVE2}', '', '');

INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (90104, 40000, '{$TEMPLATE_MACRO1}', '', '');
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (90105, 40000, '{$TEMPLATE_MACRO2}', '', '');

INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (90106, 99016, '{$TEMPLATE_MACRO_FOR_REMOVE1}', '', '');
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (90107, 99016, '{$TEMPLATE_MACRO_FOR_REMOVE2}', '', '');

-- testPageTriggerUrl
INSERT INTO dashboard (dashboardid, name, userid, private) VALUES (102, 'Dashboard for Trigger overview widget', 1, 1);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (103, 102, 'trigover', 'Group to check Overview', 0, 0, 12, 7);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (902, 103, 0, 'style', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_groupid) VALUES (903, 103, 2, 'groupids', 0, 50011);
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200021, 'Screen to check trigger url', 1, 3, NULL, 1, 0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200021, 200021, 9, 50011, 500, 100, 0, 0, 1, 1, 0, 0, 0, 1, '', 0, 0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200022, 200021, 16, 50011, 500, 100, 0, 1, 1, 1, 25, 0, 0, 0, '', 0, 0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic, sort_triggers) VALUES (200023, 200021, 14, 50011, 500, 100, 0, 2, 1, 1, 25, 0, 0, 0, '', 0, 0);

-- Dashboard for graph widget
INSERT INTO dashboard (dashboardid, name, userid, private) VALUES (103, 'Dashboard for graph widgets', 1, 1);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (104, 103, 'svggraph', 'Test cases for update', 0, 0, 6, 5);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (105, 103, 'svggraph', 'Test cases for simple update and deletion', 6, 0, 6, 5);
-- widget "Test cases for simple update and deletion"
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90006, 105, 0, 'righty', 0, '');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90008, 105, 1, 'ds.hosts.0.0', 0, 'Host*');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90009, 105, 1, 'ds.items.0.0', 0, 'Available memory');
-- widget "Test cases for update"
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90011, 104, 0, 'ds.axisy.0', 1, '');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90012, 104, 0, 'ds.pointsize.0', 4, '');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90013, 104, 0, 'ds.transparency.0', 6, '');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90014, 104, 0, 'ds.type.0', 1, '');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90015, 104, 0, 'graph_time', 1, '');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90016, 104, 0, 'lefty', 0, '');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90017, 104, 0, 'legend_lines', 2, '');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90018, 104, 0, 'or.pointsize.0', 1, '');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90019, 104, 0, 'righty_units', 1, '');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90020, 104, 0, 'severities', 0, '');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90021, 104, 0, 'show_problems', 1, '');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90022, 104, 0, 'source', 2, '');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90024, 104, 1, 'ds.hosts.0.0', 0, 'update host');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90025, 104, 1, 'ds.items.0.0', 0, 'update item');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90026, 104, 1, 'ds.timeshift.0', 0, '1m');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90027, 104, 1, 'or.hosts.0.0', 0, 'override host');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90028, 104, 1, 'or.items.0.0', 0, 'override item');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90029, 104, 1, 'problemhosts.0', 0, 'ЗАББИКС Сервер');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90030, 104, 1, 'righty_max', 0, '5');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90031, 104, 1, 'righty_min', 0, '-2');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90032, 104, 1, 'righty_static_units', 0, 'KB');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90033, 104, 1, 'time_from', 0, 'now-10m');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90034, 104, 1, 'time_to', 0, 'now-5m');

-- testProblemsBySeverityWidget
INSERT INTO dashboard (dashboardid, name, userid, private) VALUES (104, 'Dashboard for Problems by severity', 1, 1);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (106, 104, 'problemsbysv', 'Reference widget', 0, 0, 12, 5);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (107, 104, 'problemsbysv', 'Reference PBS widget to delete', 12, 0, 6, 3);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (109, 104, 'problemsbysv', 'Totals reference PBS widget to delete',18, 0, 6, 3);
INSERT INTO widget_field (widgetid, widget_fieldid, type, name, value_int) VALUES (109, 137, 0, 'show_type', 1);
INSERT INTO widget_field (widgetid, widget_fieldid, type, name, value_int) VALUES (109, 138, 0, 'layout', 1);

-- testFormItemTest
INSERT INTO hosts (hostid, proxy_hostid, host, name, status, available, description) VALUES (99136, 20000, 'Test item host', 'Test item host', 0, 0,'Test item host for testing items');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (100999, 99136, 4);
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55070, 99136, 1, '127.0.0.1', 'Test1', '1', '10050', '1');
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55071, 99136, 2, '127.0.0.2', 'Test2', '1', '161', '1');
INSERT INTO interface_snmp (interfaceid, version, bulk, community) values (55071, 2, 1, '{$SNMP_COMMUNITY}');
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55072, 99136, 3, '127.0.0.3', 'Test3', '1', '623', '1');
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55073, 99136, 4, '127.0.0.4', 'Test4', '1', '12345', '1');

INSERT INTO items (itemid, type, hostid, name, description, key_, interfaceid, flags, params, posts, headers) VALUES (99142, 0, 99136, 'Master item', '', 'master', 55070, 0, '', '', '');
INSERT INTO items (itemid, type, hostid, name, description, key_, interfaceid, flags, params, posts, headers) VALUES (99294, 0, 99136, 'Test discovery rule', '', 'test', 55070, 1, '', '', '');

INSERT INTO hosts (hostid, host, name, status, available, description) VALUES (99137, 'Test Item Template', 'Test Item Template', 3, 0,'Template for testing items');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99982, 99137, 4);

INSERT INTO items (itemid, type, hostid, name, description, key_, interfaceid, flags, params, posts, headers) VALUES (99183, 2, 99137, 'Master item', '', 'master', NULL, 0, '', '', '');
INSERT INTO items (itemid, type, hostid, name, description, key_, interfaceid, flags, params, posts, headers) VALUES (99349, 0, 99137, 'Test discovery rule', '', 'test', NULL, 1, '', '', '');

-- testFormHostPrototypeMacros
INSERT INTO hosts (hostid, host, name, status, description, flags) VALUES (99200, 'Host prototype for macros {#UPDATE}', 'Host prototype for macros {#UPDATE}', 0, '', 2);
INSERT INTO host_discovery (hostid, parent_itemid) VALUES (99200, 90001);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (222090, 99200, '', 5, NULL);
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (99500, 99200, '{$UPDATE_MACRO_1}', 'Update macro value 1', 'Update macro description 1');
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (99501, 99200, '{$UPDATE_MACRO_2}', 'Update macro value 2', 'Update macro description 2');

INSERT INTO hosts (hostid, host, name, status, description, flags) VALUES (99201, 'Host prototype for macros {#DELETE}', 'Host prototype for macros {#DELETE}', 0, '', 2);
INSERT INTO host_discovery (hostid, parent_itemid) VALUES (99201, 90001);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (222091, 99201, '', 5, NULL);
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (99502, 99201, '{$DELETE_MACRO_1}', 'Delete macro value 1', 'Delete macro description 1');
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (99503, 99201, '{$DELETE_MACRO_2}', 'Delete macro value 2', 'Delete macro description 2');

INSERT INTO hosts (hostid, host, name, status, description, flags) VALUES (99205, 'Host prototype for Secret macros {#CREATE}', 'Host prototype for Secret macros {#CREATE}', 0, '', 2);
INSERT INTO host_discovery (hostid, parent_itemid) VALUES (99205, 90001);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (222092, 99205, '', 5, NULL);

INSERT INTO hosts (hostid, host, name, status, description, flags) VALUES (99206, 'Host prototype for Secret macros {#UPDATE}', 'Host prototype for Secret macros {#UPDATE}', 0, '', 2);
INSERT INTO host_discovery (hostid, parent_itemid) VALUES (99206, 90001);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (222093, 99206, '', 5, NULL);
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type) VALUES (99504, 99206, '{$PROTOTYPE_SECRET_2_SECRET}', 'This text should stay secret', 'Secret macro to me updated', 1);
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type) VALUES (99505, 99206, '{$PROTOTYPE_SECRET_2_TEXT}', 'This text should become visible', 'Secret macro to become visible', 1);
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type) VALUES (99506, 99206, '{$PROTOTYPE_TEXT_2_SECRET}', 'This text should become secret', 'Text macro to become secret', 0);
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type) VALUES (99507, 99206, '{$Z_HOST_PROTOTYPE_MACRO_REVERT}', 'Secret host value', 'Value change Revert', 1);
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type) VALUES (99508, 99206, '{$Z_HOST_PROTOTYPE_MACRO_2_TEXT_REVERT}', 'Secret host value 2', 'Value and type change revert', 1);

-- testFormAdministrationMediaTypeWebhook
INSERT INTO media_type (mediatypeid, type, name, status, script, description) VALUES (101, 4, 'Reference webhook', 0, 'return 0;', 'Reference webhook media type');
INSERT INTO media_type_param (mediatype_paramid, mediatypeid, name, value) VALUES (1000, 101, 'URL', '');
INSERT INTO media_type_param (mediatype_paramid, mediatypeid, name, value) VALUES (1001, 101, 'To', '{ALERT.SENDTO}');
INSERT INTO media_type_param (mediatype_paramid, mediatypeid, name, value) VALUES (1002, 101, 'Subject', '{ALERT.SUBJECT}');
INSERT INTO media_type_param (mediatype_paramid, mediatypeid, name, value) VALUES (1003, 101, 'Message', '{ALERT.MESSAGE}');
INSERT INTO media_type_param (mediatype_paramid, mediatypeid, name, value) VALUES (1004, 101, 'HTTPProxy', '');
INSERT INTO media_type (mediatypeid, type, name, status, script, description) VALUES (102, 4, 'Validation webhook', 0, 'return 0;', 'Reference webhook media type for validation tests');
INSERT INTO media_type_param (mediatype_paramid, mediatypeid, name, value) VALUES (1005, 102, 'URL', '');
INSERT INTO media_type_param (mediatype_paramid, mediatypeid, name, value) VALUES (1006, 102, 'To', '{ALERT.SENDTO}');
INSERT INTO media_type_param (mediatype_paramid, mediatypeid, name, value) VALUES (1007, 102, 'Subject', '{ALERT.SUBJECT}');
INSERT INTO media_type_param (mediatype_paramid, mediatypeid, name, value) VALUES (1008, 102, 'Message', '{ALERT.MESSAGE}');
INSERT INTO media_type_param (mediatype_paramid, mediatypeid, name, value) VALUES (1009, 102, 'HTTPProxy', '');
INSERT INTO media_type (mediatypeid, type, name, status, script, show_event_menu, event_menu_name, event_menu_url, description) VALUES (103, 4, 'Webhook to delete', 0, 'return 0;', 1, 'Unique webhook url', 'zabbix.php?action=mediatype.list&ddreset={EVENT.TAGS.webhook}', 'Webhook media type to be deleted');
INSERT INTO media_type_param (mediatype_paramid, mediatypeid, name, value) VALUES (1010, 103, 'Parameter name to be deleted', 'Parameter value to be deleted');
INSERT INTO media_type_param (mediatype_paramid, mediatypeid, name, value) VALUES (1011, 103, '2nd parameter name to be deleted', '2nd parameter value to be deleted');

-- testPageProblems_ProblemLinks
INSERT INTO media_type (mediatypeid, type, name, status, script, show_event_menu, event_menu_name, event_menu_url, description) VALUES (104, 4, 'URL test webhook', 0, 'return 0;', 1, 'Webhook url for all', 'zabbix.php?action=mediatype.edit&mediatypeid=101', 'Webhook media type for URL test');
INSERT INTO event_tag (eventtagid, eventid, tag, value) VALUES (201, 9003, 'webhook', '1');
INSERT INTO problem_tag (problemtagid, eventid, tag, value) VALUES (201, 9003, 'webhook', '1');

-- testDynamicItemWidgets
INSERT INTO hosts (hostid, host, name, description) VALUES (99202, 'Dynamic widgets H1', 'Dynamic widgets H1', '');
INSERT INTO hstgrp (groupid, name, internal) VALUES (50017, 'Dynamic widgets HG1 (H1 and H2)', 0);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99983, 99202, 50017);
INSERT INTO interface (interfaceid, hostid, main, type) VALUES (55074, 99202, 1, 1);
INSERT INTO items (itemid, type, hostid, name, key_, params, description, posts, headers) VALUES (99103, 2, 99202, 'Dynamic widgets H1I1', 'dynamic[1]', '', '', '', '');
INSERT INTO history (itemid, clock, value, ns) VALUES (99103, 1589983553, '11', 726692808);
INSERT INTO items (itemid, type, hostid, name, key_, params, description, posts, headers) VALUES (99104, 2, 99202, 'Dynamic widgets H1I2', 'dynamic[2]', '', '', '', '');
INSERT INTO history (itemid, clock, value, ns) VALUES (99104, 1589897100, '12', 726692808);
INSERT INTO graphs (graphid, name) VALUES (700026, 'Dynamic widgets H1 G1 (I1)');
INSERT INTO graphs_items (gitemid, graphid, itemid, sortorder) VALUES (700034, 700026, 99103, 0);
INSERT INTO graphs (graphid, name) VALUES (700027, 'Dynamic widgets H1 G2 (I2)');
INSERT INTO graphs_items (gitemid, graphid, itemid, sortorder) VALUES (700035, 700027, 99104, 0);
INSERT INTO graphs (graphid, name) VALUES (700028, 'Dynamic widgets H1 G3 (I1 and I2)');
INSERT INTO graphs_items (gitemid, graphid, itemid, sortorder) VALUES (700036, 700028, 99103, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, sortorder) VALUES (700037, 700028, 99104, 1);
INSERT INTO graphs (graphid, name) VALUES (700031,'Dynamic widgets H1 G4 (H1I1 and H3I1)');
INSERT INTO graphs_items (gitemid, graphid, itemid, sortorder) VALUES (700041, 700031, 99104, 0);
INSERT INTO items (itemid, type, hostid, name, key_, flags, params, description, posts, headers) VALUES (99107, 2, 99202, 'Dynamic widgets H1D1', 'dynamic.lld[1]', 1, '', '', '', '');
INSERT INTO items (itemid, type, hostid, name, key_, flags, params, description, posts, headers) VALUES (99108, 2, 99202, 'Dynamic widgets H1IP1', 'dynamic.ip[1]', 2, '', '', '', '');
INSERT INTO items (itemid, type, hostid, name, key_, flags, params, description, posts, headers) VALUES (99109, 2, 99202, 'Dynamic widgets H1IP2', 'dynamic.ip[2]', 2, '', '', '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (15087, 99108, 99107);
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (15088, 99109, 99107);
INSERT INTO graphs (graphid, name, flags) VALUES (700032, 'Dynamic widgets GP1 (IP1)', 2);
INSERT INTO graphs_items (gitemid, graphid, itemid, sortorder) VALUES (700042, 700032, 99108, 0);
INSERT INTO graphs (graphid, name, flags) VALUES (700033, 'Dynamic widgets GP2 (I1, IP1, H1I2)', 2);
INSERT INTO graphs_items (gitemid, graphid, itemid, sortorder) VALUES (700043, 700033, 99108, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, sortorder) VALUES (700044, 700033, 99103, 1);
INSERT INTO graphs_items (gitemid, graphid, itemid, sortorder) VALUES (700045, 700033, 99104, 2);
INSERT INTO graphs (graphid, name, flags) VALUES (700034, 'Dynamic widgets H1 GP3 (H1IP1)', 2);
INSERT INTO graphs_items (gitemid, graphid, itemid, sortorder) VALUES (700046, 700034, 99108, 0);
INSERT INTO graphs (graphid, name, flags) VALUES (700035, 'Dynamic widgets H1 GP4 (H1IP1 and H2I1)', 2);
INSERT INTO graphs_items (gitemid, graphid, itemid, sortorder) VALUES (700047, 700035, 99108, 0);

INSERT INTO hosts (hostid, host, name, status, description) VALUES (99203, 'Dynamic widgets H2', 'Dynamic widgets H2', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99984, 99203, 50017);
INSERT INTO interface (interfaceid, hostid, main, type) VALUES (55075, 99203, 1, 1);
INSERT INTO items (itemid, type, hostid, name, key_, params, description, posts, headers) VALUES (99105, 2, 99203, 'Dynamic widgets H2I1', 'dynamic[1]', '', '', '', '');
INSERT INTO history (itemid, clock, value, ns) VALUES (99105, 1589810700, '21', 726692808);
INSERT INTO graphs (graphid, name) VALUES (700029, 'Dynamic widgets H2 G1 (I1)');
INSERT INTO graphs_items (gitemid, graphid, itemid, sortorder) VALUES (700038, 700029, 99105, 0);
INSERT INTO items (itemid, type, hostid, name, key_, flags, params, description, posts, headers) VALUES (99110, 2, 99203, 'Dynamic widgets H2D1', 'dynamic.lld[1]', 1, '', '', '', '');
INSERT INTO items (itemid, type, hostid, name, key_, flags, params, description, posts, headers) VALUES (99111, 2, 99203, 'Dynamic widgets H2IP1', 'dynamic.ip[1]', 2, '', '', '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid) values (15089, 99111, 99110);
INSERT INTO graphs (graphid, name, flags) VALUES (700036, 'Dynamic widgets GP1 (IP1)', 2);
INSERT INTO graphs_items (gitemid, graphid, itemid, sortorder) VALUES (700048, 700036, 99111, 0);
INSERT INTO graphs (graphid, name, flags) VALUES (700037, 'Dynamic widgets GP2 (I1, IP1, H1I2)', 2);
INSERT INTO graphs_items (gitemid, graphid, itemid, sortorder) VALUES (700049, 700037, 99105, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, sortorder) VALUES (700050, 700037, 99111, 1);
INSERT INTO graphs (graphid, name, flags) VALUES (700038, 'Dynamic widgets H2 GP3 (H2IP1)', 2);
INSERT INTO graphs_items (gitemid, graphid, itemid, sortorder) VALUES (700051, 700038, 99111, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, sortorder) VALUES (700052, 700035, 99105, 1);

INSERT INTO hosts (hostid, host, name, status, description) VALUES (99204, 'Dynamic widgets H3', 'Dynamic widgets H3', 0, '');
INSERT INTO hstgrp (groupid, name, internal) VALUES (50018, 'Dynamic widgets HG2 (H3)', 0);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99985, 99204, 50018);
INSERT INTO interface (interfaceid, hostid, main, type) VALUES (55076, 99204, 1, 1);
INSERT INTO items (itemid, type, hostid, name, key_, params, description, posts, headers) VALUES (99106, 2, 99204, 'Dynamic widgets H3I1', 'dynamic[1]', '', '', '', '');
INSERT INTO history (itemid, clock, value, ns) VALUES (99106, 1589724300, '31', 726692808);
INSERT INTO graphs (graphid, name) VALUES (700030, 'Dynamic widgets H3 G1 (I1)');
INSERT INTO graphs_items (gitemid, graphid, itemid, sortorder) VALUES (700039, 700030, 99106, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, sortorder) VALUES (700040, 700031, 99106, 1);

INSERT INTO dashboard (dashboardid, name, userid, private) VALUES (105, 'Dashboard for Dynamic item', 1, 1);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (110, 105, 'graph', '', 0, 0, 8, 3);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (111, 105, 'graph', '', 8, 0, 8, 3);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (112, 105, 'graph', '', 16, 0, 8, 3);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90035, 110, 0, 'source_type', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_itemid) VALUES (90036, 110, 4, 'itemid', 0, 99104);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90037, 111, 0, 'source_type', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_itemid) VALUES (90038, 111, 4, 'itemid', 0, 99103);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90039, 111, 0, 'dynamic', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90040, 112, 0, 'source_type', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_itemid) VALUES (90041, 112, 4, 'itemid', 0, 99104);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90042, 112, 0, 'dynamic', 1);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (113, 105, 'graph', '', 0, 3, 8, 3);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (114, 105, 'graph', '', 8, 3, 8, 3);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (115, 105, 'graph', '', 16, 3, 8, 3);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (116, 105, 'graph', '', 0, 6, 10, 3);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (117, 105, 'graph', '', 10, 6, 10, 3);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_graphid) VALUES (90043, 113, 6, 'graphid', 0, 700027);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_graphid) VALUES (90044, 114, 6, 'graphid', 0, 700026);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90045, 114, 0, 'dynamic', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_graphid) VALUES (90046, 115, 6, 'graphid', 0, 700027);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90047, 115, 0, 'dynamic', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_graphid) VALUES (90048, 116, 6, 'graphid', 0, 700028);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90049, 116, 0, 'dynamic', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_graphid) VALUES (90050, 117, 6, 'graphid', 0, 700031);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90051, 117, 0, 'dynamic', 1);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (118, 105, 'plaintext', '', 0, 9, 8, 2);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (119, 105, 'plaintext', '', 8, 9, 8, 2);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (120, 105, 'plaintext', '', 16, 9, 8, 2);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (121, 105, 'plaintext', '', 0, 11, 8, 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_itemid) VALUES (90052, 118, 4, 'itemids', 0, 99104);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_itemid) VALUES (90053, 119, 4, 'itemids', 0, 99103);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90054, 119, 0, 'dynamic', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_itemid) VALUES (90055, 120, 4, 'itemids', 0, 99104);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90056, 120, 0, 'dynamic', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_itemid) VALUES (90057, 121, 4, 'itemids', 0, 99103);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_itemid) VALUES (90058, 121, 4, 'itemids', 0, 99104);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90059, 121, 0, 'dynamic', 1);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (122, 105, 'url', 'Dynamic URL', 0, 13, 11, 4);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90060, 122, 0, 'dynamic', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_str) VALUES (90061, 122, 1, 'url', 'iframe.php?name={HOST.NAME}');
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (123, 105, 'graphprototype', '', 0, 17, 9, 2);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (124, 105, 'graphprototype', '', 9, 17, 8, 2);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (125, 105, 'graphprototype', '', 17, 17, 7, 2);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (126, 105, 'graphprototype', '', 0, 19, 8, 2);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (127, 105, 'graphprototype', '', 8, 19, 9, 2);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (128, 105, 'graphprototype', '', 17, 19, 7, 2);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (129, 105, 'graphprototype', '', 0, 21, 11, 2);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (130, 105, 'graphprototype', '', 11, 21, 11, 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90062, 123, 0, 'source_type', 3);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_itemid) VALUES (90063, 123, 5, 'itemid', 0, 99109);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90064, 124, 0, 'dynamic', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90065, 124, 0, 'source_type', 3);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_itemid) VALUES (90066, 124, 5, 'itemid', 0, 99108);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90067, 125, 0, 'dynamic', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90068, 125, 0, 'source_type', 3);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_itemid) VALUES (90069, 125, 5, 'itemid', 0, 99109);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_graphid) VALUES (90070, 126, 7, 'graphid', 0, 700032);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90071, 127, 0, 'dynamic', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_graphid) VALUES (90072, 127, 7, 'graphid', 0, 700032);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90073, 128, 0, 'dynamic', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_graphid) VALUES (90074, 128, 7, 'graphid', 0, 700033);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90075, 129, 0, 'dynamic', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_graphid) VALUES (90076, 129, 7, 'graphid', 0, 700034);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90077, 130, 0, 'dynamic', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_graphid) VALUES (90078, 130, 7, 'graphid', 0, 700035);

-- testFormUserMedia
INSERT INTO media (mediaid, userid, mediatypeid, sendto, active, severity, period) VALUES (4,1,10,'test@jabber.com',0,16,'1-7,00:00-24:00');
INSERT INTO media (mediaid, userid, mediatypeid, sendto, active, severity, period) VALUES (5,1,12,'test_account',0,63,'6-7,09:00-18:00');

-- Dashboards and widgets for Copy widget tests
INSERT INTO dashboard (dashboardid, name, userid, private) VALUES (130, 'Dashboard for Copying widgets', 1, 1);
INSERT INTO dashboard (dashboardid, name, userid, private) VALUES (131, 'Dashboard for Paste widgets', 1, 1);

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (175, 131, 'navtree', 'Test copy Map navigation tree', 0, 0, 6, 4);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90479, 175, 1, 'reference', 0, 'FYKXG');
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (174, 131, 'clock', 'Test widget for replace', 6, 0, 13, 8);

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (150, 130, 'actionlog', 'Test copy Action log', 0, 0, 7, 6);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90250, 150, 0, 'rf_rate', 10);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90251, 150, 0, 'show_lines', 3);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90252, 150, 0, 'sort_triggers', 7);

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (151, 130, 'clock', 'Test copy Clock', 7, 0, 2, 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90253, 151, 0, 'rf_rate', 60);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90254, 151, 0, 'time_type', 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_itemid) VALUES (90412, 151, 4, 'itemid', 0, 29172);

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (152, 130, 'dataover', 'Test copy Data overview', 9, 0, 4, 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90255, 152, 0, 'rf_rate', 0);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90256, 152, 0, 'show_suppressed', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90257, 152, 0, 'style', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (90258, 152, 1, 'application', 0, '3 application');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_groupid) VALUES (90259, 152, 2, 'groupids', 0, 50011);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_hostid) VALUES (90260, 152, 3, 'hostids', 0, 50012);

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (153, 130, 'graph', 'Test copy classic Graph', 13, 0, 11, 6);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90261, 153, 0, 'dynamic', 10);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90262, 153, 0, 'rf_rate', 30);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90263, 153, 0, 'show_legend', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90264, 153, 0, 'source_type', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_itemid) VALUES (90265, 153, 4, 'itemid', 0, 99088);

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (154, 130, 'favgraphs', 'Test copy Favourite graphs', 7, 2, 2, 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90413, 154, 0, 'rf_rate', 30);

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (155, 130, 'favmaps', 'Test copy Favourite maps', 9, 2, 4, 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90266, 155, 0, 'rf_rate', 600);

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (156, 130, 'discovery', 'Test copy Discovery status', 9, 4, 4, 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90267, 156, 0, 'rf_rate', 900);

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (157, 130, 'favscreens', 'Test copy Favourite screens', 7, 4, 2, 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90268, 157, 0, 'rf_rate', 30);

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (158, 130, 'graphprototype', 'Test copy Graph prototype', 0, 6, 13, 4);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90269, 158, 0, 'columns', 3);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90270, 158, 0, 'rows', 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90271, 158, 0, 'dynamic', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90272, 158, 0, 'rf_rate', 30);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90273, 158, 0, 'show_legend', 0);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_graphid) VALUES (900401, 158, 7, 'graphid', 0, 600000);

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (159, 130, 'hostavail', 'Test copy Host availability', 13, 6, 5, 4);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90402, 159, 0, 'interface_type', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90403, 159, 0, 'interface_type', 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904004, 159, 0, 'interface_type', 3);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904005, 159, 0, 'layout', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904006, 159, 0, 'maintenance', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904007, 159, 0, 'rf_rate', 60);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_groupid) VALUES (904008, 159, 2, 'groupids', 0, 50013);

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (160, 130, 'map', 'Test copy Map', 18, 6, 6, 4);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (904009, 160, 1, 'reference', 0, 'OYKZW');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_sysmapid) VALUES (904010, 160, 8, 'sysmapid', 0, 3);

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (161, 130, 'navtree', 'Test copy Map navigation tree', 0, 10, 6, 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904011, 161, 0, 'navtree.order.2', 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904012, 161, 0, 'rf_rate', 60);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904013, 161, 0, 'show_unavailable', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (904014, 161, 1, 'navtree.name.1', 0, 'Map with icon mapping');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (904015, 161, 1, 'navtree.name.2', 0, 'Public map with image');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (904016, 161, 1, 'reference', 0, 'STZDI');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (904017, 161, 8, 'navtree.sysmapid.1', 0, 6);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (904018, 161, 8, 'navtree.sysmapid.2', 0, 10);

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (162, 130, 'map', 'Test copy Map from tree', 6, 10, 7, 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904019, 162, 0, 'rf_rate', 120);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904020, 162, 0, 'source_type', 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (904021, 162, 1, 'filter_widget_reference', 0, 'STZDI');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (904022, 162, 1, 'reference', 0, 'PVEYR');

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (163, 130, 'plaintext', 'Test copy plain text', 13, 10, 5, 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904023, 163, 0, 'dynamic', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904024, 163, 0, 'rf_rate', 0);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904025, 163, 0, 'show_as_html', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904026, 163, 0, 'show_lines', 12);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904027, 163, 0, 'style', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_itemid) VALUES (904028, 163, 4, 'itemids', 0, 29171);

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (164, 130, 'problemhosts', 'Test copy Problem hosts', 18, 10, 6, 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904029, 164, 0, 'evaltype', 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904030, 164, 0, 'ext_ack', 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904031, 164, 0, 'hide_empty_groups', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904032, 164, 0, 'rf_rate', 30);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904034, 164, 0, 'severities', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904035, 164, 0, 'severities', 3);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904036, 164, 0, 'severities', 5);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904037, 164, 0, 'show_suppressed', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904038, 164, 0, 'tags.operator.0', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (904039, 164, 1, 'problem', 0, 'Test');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (904040, 164, 1, 'tags.tag.0', 0, 'Tag1');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (904041, 164, 1, 'tags.value.0', 0, 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_groupid) VALUES (904042, 164, 2, 'exclude_groupids', 0, 50014);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_groupid) VALUES (904043, 164, 2, 'groupids', 0, 50011);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_itemid) VALUES (904044, 164, 4, 'itemids', 0, 29171);

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (165, 130, 'problems', 'Test copy Problems', 0, 12, 8, 6);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904045, 165, 0, 'evaltype', 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904046, 165, 0, 'rf_rate', 900);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90321, 165, 0, 'severities', 0);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90415, 165, 0, 'severities', 4);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90416, 165, 0, 'severities', 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904048, 165, 0, 'show', 3);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904049, 165, 0, 'show_lines', 12);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904050, 165, 0, 'show_opdata', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904051, 165, 0, 'show_suppressed', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904052, 165, 0, 'show_tags', 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904053, 165, 0, 'sort_triggers', 15);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904054, 165, 0, 'show_timeline', 0);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904055, 165, 0, 'tag_name_format', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904056, 165, 0, 'tags.operator.0', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904057, 165, 0, 'tags.operator.1', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904058, 165, 0, 'unacknowledged', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (904059, 165, 1, 'problem', 0, 'test2');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (904060, 165, 1, 'tags.value.0', 0, '2');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (904061, 165, 1, 'tags.value.1', 0, '33');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (904062, 165, 1, 'tag_priority', 0, '1,2');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (904063, 165, 1, 'tags.tag.0', 0, 'tag2');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (904064, 165, 1, 'tags.tag.1', 0, 'tagg33');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_groupid) VALUES (904065, 165, 2, 'exclude_groupids', 0, 50014);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_groupid) VALUES (904066, 165, 2, 'groupids', 0, 50005);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_hostid) VALUES (904067, 165, 3, 'hostids', 0, 99026);

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (166, 130, 'problems', 'Test copy Problems 2', 8, 12, 16, 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904068, 166, 0, 'rf_rate', 60);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904069, 166, 0, 'show', 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904070, 166, 0, 'show_lines', 5);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904071, 166, 0, 'show_opdata', 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904072, 166, 0, 'show_suppressed', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904073, 166, 0, 'show_tags', 3);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904074, 166, 0, 'show_timeline', 0);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904075, 166, 0, 'sort_triggers', 3);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904076, 166, 0, 'tag_name_format', 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904077, 166, 0, 'tags.operator.0', 0);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904078, 166, 0, 'tags.operator.1', 0);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904081, 166, 0, 'unacknowledged', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (904082, 166, 1, 'problem', 0, 'test4');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (904083, 166, 1, 'tags.value.0', 0, 3);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (904084, 166, 1, 'tags.value.1', 0, 44);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (904085, 166, 1, 'tag_priority', 0, 'test5, test6');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (904086, 166, 1, 'tags.tag.0', 0, 'tag3');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (904087, 166, 1, 'tags.tag.1', 0, 'tag44');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_groupid) VALUES (904088, 166, 2, 'exclude_groupids', 0, 50014);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_groupid) VALUES (904089, 166, 2, 'groupids', 0, 50006);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_hostid) VALUES (904090, 166, 3, 'hostids', 0, 99015);

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (167, 130, 'problemsbysv', 'Test copy Problems by severity', 8, 14, 16, 4);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904091, 167, 0, 'evaltype', 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904092, 167, 0, 'ext_ack', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904093, 167, 0, 'layout', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904094, 167, 0, 'rf_rate', 30);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904096, 167, 0, 'severities', 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904097, 167, 0, 'severities', 3);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904098, 167, 0, 'show_opdata', 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (904099, 167, 0, 'show_timeline', 0);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905000, 167, 0, 'show_type', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905001, 167, 0, 'tags.operator.0', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (905002, 167, 1, 'problem', 0, 'test problem');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (905003, 167, 1, 'tags.tag.0', 0, 'tag5');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (905004, 167, 1, 'tags.value.0', 0, 5);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_groupid) VALUES (905005, 167, 2, 'exclude_groupids', 0, 50008);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_groupid) VALUES (905006, 167, 2, 'groupids', 0, 50011);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_hostid) VALUES (905007, 167, 3, 'hostids', 0, 99012);

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (168, 130, 'systeminfo', 'Test copy System information', 0, 18, 7, 3);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905008, 168, 0, 'rf_rate', 30);

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (169, 130, 'trigover', 'Test copy Trigger overview', 7, 18, 17, 3);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905009, 169, 0, 'rf_rate', 120);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905010, 169, 0, 'show', 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905011, 169, 0, 'show_suppressed', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905012, 169, 0, 'style', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (905013, 169, 1, 'application', 0, 'Inventory');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_groupid) VALUES (905014, 169, 2, 'groupids', 0, 50011);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_hostid) VALUES (905015, 169, 3, 'hostids', 0, 99012);

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (170, 130, 'url', 'Test copy URL', 0, 21, 7, 3);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905016, 170, 0, 'dynamic', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905017, 170, 0, 'rf_rate', 120);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (905018, 170, 1, 'url', 0, 'https://www.zabbix.com/integrations');

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (171, 130, 'web', 'Test copy Web monitoring', 7, 21, 3, 3);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905019, 171, 0, 'maintenance', 0);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905020, 171, 0, 'rf_rate', 120);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_groupid) VALUES (905021, 171, 2, 'exclude_groupids', 0, 50008);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_groupid) VALUES (905022, 171, 2, 'groupids', 0, 50016);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_hostid) VALUES (905023, 171, 3, 'hostids', 0, 99133);

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (172, 130, 'problems', 'Test copy Problems 3', 10, 21, 14, 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905024, 172, 0, 'evaltype', 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905025, 172, 0, 'rf_rate', 60);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905026, 172, 0, 'severities', 0);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905027, 172, 0, 'severities', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905028, 172, 0, 'severities', 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905029, 172, 0, 'severities', 3);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905030, 172, 0, 'severities', 4);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905031, 172, 0, 'severities', 5);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905032, 172, 0, 'show', 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905033, 172, 0, 'show_lines', 5);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905034, 172, 0, 'show_opdata', 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905035, 172, 0, 'show_suppressed', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905036, 172, 0, 'show_tags', 3);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905037, 172, 0, 'sort_triggers', 3);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905038, 172, 0, 'tags.operator.0', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905039, 172, 0, 'tag_name_format', 2);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905040, 172, 0, 'unacknowledged', 1);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (905041, 172, 1, 'problem', 0, 'test5');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (905042, 172, 1, 'tag_priority', 0, 'test7, test8');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (905043, 172, 1, 'tags.tag.0', 0, 'tag9');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_str) VALUES (905044, 172, 1, 'tags.value.0', 0, '9');
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_groupid) VALUES (905045, 172, 2, 'exclude_groupids', 0, 50014);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_groupid) VALUES (905046, 172, 2, 'groupids', 0, 50006);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_hostid) VALUES (905047, 172, 3, 'hostids', 0, 99015);

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (173, 130, 'graphprototype', 'Test copy Graph prototype 2', 10, 23, 14, 5);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905048, 173, 0, 'columns', 20);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905049, 173, 0, 'rows', 5);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905050, 173, 0, 'dynamic', 0);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (905051, 173, 0, 'rf_rate', 600);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int) VALUES (90444, 173, 0, 'show_legend', 0);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_int, value_graphid) VALUES (905053, 173, 7, 'graphid', 0, 600000);

-- Dashboard for Graph Prototype widget
INSERT INTO dashboard (dashboardid, name, userid, private) VALUES (140, 'Dashboard for Graph Prototype widget', 1, 1);
INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (199, 140, 'graphprototype', 'Graph prototype widget for update', 0, 0, 16, 5);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_graphid) VALUES (905054, 199, 7, 'graphid', 600003);

INSERT INTO widget (widgetid, dashboardid, type, name, x, y, width, height) VALUES (200, 140, 'graphprototype', 'Graph prototype widget for delete', 0, 5, 16, 5);
INSERT INTO widget_field (widget_fieldid, widgetid, type, name, value_graphid) VALUES (905055, 200, 7, 'graphid', 600002);

INSERT INTO dashboard (dashboardid, name, userid, private) VALUES (141, 'Dashboard for Sceenshoting Graph Prototype widgets', 1, 1);

-- Overrides for LLD Overrides test
INSERT INTO lld_override (lld_overrideid, itemid, name, step, evaltype, stop) values (2000, 133800, 'Override for update 1', 1, 1, 0);
INSERT INTO lld_override (lld_overrideid, itemid, name, step, evaltype, stop) values (2001, 133800, 'Override for update 2', 2, 0, 0);

INSERT INTO lld_override_condition (lld_override_conditionid, lld_overrideid, operator, macro, value) values (3000, 2000, 8, '{#MACRO1}', 'test expression_1');
INSERT INTO lld_override_condition (lld_override_conditionid, lld_overrideid, operator, macro, value) values (3001, 2000, 9, '{#MACRO2}', 'test expression_2');

INSERT INTO lld_override_operation (lld_override_operationid, lld_overrideid, operationobject, operator, value) values (4000, 2000, 0, 0, 'test item pattern');
INSERT INTO lld_override_operation (lld_override_operationid, lld_overrideid, operationobject, operator, value) values (4001, 2000, 1, 1, 'test trigger pattern');
INSERT INTO lld_override_operation (lld_override_operationid, lld_overrideid, operationobject, operator, value) values (4002, 2001, 2, 8, 'test graph pattern');
INSERT INTO lld_override_operation (lld_override_operationid, lld_overrideid, operationobject, operator, value) values (4003, 2001, 3, 9, 'test host pattern');

INSERT INTO lld_override_opdiscover (lld_override_operationid, discover) values (4000, 0);
INSERT INTO lld_override_opdiscover (lld_override_operationid, discover) values (4002, 0);

INSERT INTO lld_override_ophistory (lld_override_operationid, history) values (4000, 0);

INSERT INTO lld_override_opinventory (lld_override_operationid, inventory_mode) values (4003, 1);

INSERT INTO lld_override_opperiod (lld_override_operationid, delay) values (4000, '1m;50s/1-7,00:00-24:00;wd1-5h9-18');

INSERT INTO lld_override_opseverity (lld_override_operationid, severity) values (4001, 2);

INSERT INTO lld_override_opstatus (lld_override_operationid, status) values (4000, 0);

INSERT INTO lld_override_optag (lld_override_optagid, lld_override_operationid, tag, value) values (300, 4001, 'tag1', 'value1');

INSERT INTO lld_override_optemplate (lld_override_optemplateid, lld_override_operationid, templateid) values (300, 4003, 99137);

INSERT INTO lld_override_optrends (lld_override_operationid, trends) values (4000, 0);

-- testFormHostMacros
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type) VALUES (99509, 99135, '{$SECRET_HOST_MACRO_REVERT}', 'Secret host value', 'Secret host macro description', 1);
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type) VALUES (99510, 99135, '{$SECRET_HOST_MACRO_2_TEXT_REVERT}', 'Secret host value 2 text', 'Secret host macro that will be changed to text', 1);
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type) VALUES (99511, 99135, '{$SECRET_HOST_MACRO_UPDATE_2_TEXT}', 'Secret host value 2 B updated', 'Secret host macro that is going to be updated', 1);
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type) VALUES (99512, 99135, '{$TEXT_HOST_MACRO_2_SECRET}', 'Text host macro value', 'Text host macro that is going to become secret', 0);
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type) VALUES (99513, 99135, '{$SECRET_HOST_MACRO_UPDATE}', 'Secret host macro value', 'Secret host macro that is going to stay secret', 1);
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type) VALUES (99514, 99135, '{$X_SECRET_HOST_MACRO_2_RESOLVE}', 'Value 2 B resolved', 'Host macro to be resolved', 0);
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type) VALUES (99515, 99011, '{$SECRET_HOST_MACRO}', 'some secret value', '', 1);
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type) VALUES (99516, 99011, '{$TEXT_HOST_MACRO}', 'some text value', '', 0);
INSERT INTO items (itemid, type, hostid, name, key_, interfaceid, params, description, posts, headers) VALUES (99112, 2, 99135, 'Macro value: {$X_SECRET_HOST_MACRO_2_RESOLVE}', 'trap', NULL, '', '', '', '');

-- testFormTemplateMacros
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type) VALUES (99517, 99137, '{$SECRET_TEMPLATE_MACRO_REVERT}', 'Secret template value', 'Secret template macro description', 1);
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type) VALUES (99518, 99137, '{$SECRET_TEMPLATE_MACRO_2_TEXT_REVERT}', 'Secret template value 2 text', 'Secret template macro that will be changed to text', 1);
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type) VALUES (99519, 99137, '{$SECRET_TEMPLATE_MACRO_UPDATE_2_TEXT}', 'Secret template value 2 B updated', 'Secret template macro that is going to be updated', 1);
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type) VALUES (99520, 99137, '{$TEXT_TEMPLATE_MACRO_2_SECRET}', 'Text template macro value', 'Text template macro that is going to become secret', 0);
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type) VALUES (99521, 99137, '{$SECRET_TEMPLATE_MACRO_UPDATE}', 'Secret template macro value', 'Secret template macro that is going to stay secret', 1);
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description, type) VALUES (99522, 99137, '{$X_SECRET_TEMPLATE_MACRO_2_RESOLVE}', 'Value 2 B resolved', 'Template macro to be resolved', 0);
INSERT INTO items (itemid, type, hostid, name, key_, interfaceid, params, description, posts, headers) VALUES (99113, 2, 99137, 'Macro value: {$X_SECRET_TEMPLATE_MACRO_2_RESOLVE}', 'trap', NULL, '', '', '', '');

-- testFormAdministrationGeneralMacros
INSERT INTO items (itemid, type, hostid, name, key_, interfaceid, params, description, posts, headers) VALUES (99114, 2, 99134, 'Macro value: {$Z_GLOBAL_MACRO_2_RESOLVE}', 'trap', NULL, '', '', '', '');

-- testFormAdministrationScripts
INSERT INTO scripts (scriptid, type, name, command, host_access, usrgrpid, groupid, description) VALUES (200, 0, 'Script for Update', 'test', 2, NULL, NULL, 'update description');
INSERT INTO scripts (scriptid, type, name, command, host_access, usrgrpid, groupid, description) VALUES (201, 0, 'Script for Clone','test', 2, NULL, NULL, 'clone description');
INSERT INTO scripts (scriptid, type, name, command, host_access, usrgrpid, groupid, description) VALUES (202, 0, 'Script for Delete','test', 2, NULL, NULL, 'delete description');
