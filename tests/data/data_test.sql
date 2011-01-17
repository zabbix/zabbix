-- Activate Zabbix Server
UPDATE hosts SET status=0 WHERE host='Zabbix Server';

-- New media types
INSERT INTO media_type (mediatypeid, type, description, smtp_server, smtp_helo, smtp_email, exec_path, gsm_modem, username, passwd) VALUES (4,100,'SMS via IP','','','','0','','test','test');

-- More medias for user 'Admin'
INSERT INTO media (mediaid, userid, mediatypeid, sendto, active, severity, period) VALUES (1,1,1,'test@zabbix.com',0,63,'1-7,00:00-23:59;');
INSERT INTO media (mediaid, userid, mediatypeid, sendto, active, severity, period) VALUES (2,1,1,'test2@zabbix.com',1,60,'1-7,00:00-23:59;');
INSERT INTO media (mediaid, userid, mediatypeid, sendto, active, severity, period) VALUES (3,1,3,'123456789',0,32,'1-7,00:00-23:59;');
INSERT INTO media (mediaid, userid, mediatypeid, sendto, active, severity, period) VALUES (4,1,2,'test@jabber.com',0,16,'1-7,00:00-23:59;');
INSERT INTO media (mediaid, userid, mediatypeid, sendto, active, severity, period) VALUES (5,1,4,'test_account',0,63,'6-7,09:00-17:59;');

-- More user scripts
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation) VALUES (3,'Reboot','/sbin/shutdown -r',3,7,4,'This command reboots server.','Do you really want to reboot it?');

-- Add proxies
INSERT INTO hosts (hostid, proxy_hostid, host, status, disable_until, error, available, errors_from, lastaccess, ipmi_authtype, ipmi_privilege, ipmi_username, ipmi_password, ipmi_disable_until, ipmi_available, snmp_disable_until, snmp_available, maintenanceid, maintenance_status, maintenance_type, maintenance_from, ipmi_errors_from, snmp_errors_from, ipmi_error, snmp_error) VALUES (10047,NULL,'Active proxy 1',5,0,'',0,0,0,0,2,'','',0,0,0,0,NULL,0,0,0,0,0,'','');
INSERT INTO hosts (hostid, proxy_hostid, host, status, disable_until, error, available, errors_from, lastaccess, ipmi_authtype, ipmi_privilege, ipmi_username, ipmi_password, ipmi_disable_until, ipmi_available, snmp_disable_until, snmp_available, maintenanceid, maintenance_status, maintenance_type, maintenance_from, ipmi_errors_from, snmp_errors_from, ipmi_error, snmp_error) VALUES (10048,NULL,'Active proxy 2',5,0,'',0,0,0,0,2,'','',0,0,0,0,NULL,0,0,0,0,0,'','');
INSERT INTO hosts (hostid, proxy_hostid, host, status, disable_until, error, available, errors_from, lastaccess, ipmi_authtype, ipmi_privilege, ipmi_username, ipmi_password, ipmi_disable_until, ipmi_available, snmp_disable_until, snmp_available, maintenanceid, maintenance_status, maintenance_type, maintenance_from, ipmi_errors_from, snmp_errors_from, ipmi_error, snmp_error) VALUES (10049,NULL,'Active proxy 3',5,0,'',0,0,0,0,2,'','',0,0,0,0,NULL,0,0,0,0,0,'','');
INSERT INTO hosts (hostid, proxy_hostid, host, status, disable_until, error, available, errors_from, lastaccess, ipmi_authtype, ipmi_privilege, ipmi_username, ipmi_password, ipmi_disable_until, ipmi_available, snmp_disable_until, snmp_available, maintenanceid, maintenance_status, maintenance_type, maintenance_from, ipmi_errors_from, snmp_errors_from, ipmi_error, snmp_error) VALUES (10050,NULL,'Passive proxy 1',6,0,'',0,0,0,0,2,'','',0,0,0,0,NULL,0,0,0,0,0,'','');
INSERT INTO hosts (hostid, proxy_hostid, host, status, disable_until, error, available, errors_from, lastaccess, ipmi_authtype, ipmi_privilege, ipmi_username, ipmi_password, ipmi_disable_until, ipmi_available, snmp_disable_until, snmp_available, maintenanceid, maintenance_status, maintenance_type, maintenance_from, ipmi_errors_from, snmp_errors_from, ipmi_error, snmp_error) VALUES (10051,NULL,'Passive proxy 2',6,0,'',0,0,0,0,2,'','',0,0,0,0,NULL,0,0,0,0,0,'','');
INSERT INTO hosts (hostid, proxy_hostid, host, status, disable_until, error, available, errors_from, lastaccess, ipmi_authtype, ipmi_privilege, ipmi_username, ipmi_password, ipmi_disable_until, ipmi_available, snmp_disable_until, snmp_available, maintenanceid, maintenance_status, maintenance_type, maintenance_from, ipmi_errors_from, snmp_errors_from, ipmi_error, snmp_error) VALUES (10052,NULL,'Passive proxy 3',6,0,'',0,0,0,0,2,'','',0,0,0,0,NULL,0,0,0,0,0,'','');

INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) VALUES (10018,10050,1,0,1,'127.0.0.1','proxy1.zabbix.com','10051');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) VALUES (10019,10051,1,0,1,'127.0.0.1','proxy2.zabbix.com','10333');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) VALUES (10020,10052,1,0,0,'127.0.0.1','proxy3.zabbix.com','10051');

-- Add Trigger Actions
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period, def_shortdata, def_longdata, recovery_msg, r_shortdata, r_longdata) VALUES (4,'Simple action',0,0,0,0,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}\r\n\r\n{TRIGGER.URL}',0,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}');
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period, def_shortdata, def_longdata, recovery_msg, r_shortdata, r_longdata) VALUES (5,'Trigger action 1',0,0,0,3600,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}\r\n\r\n{TRIGGER.URL}',0,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}');
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period, def_shortdata, def_longdata, recovery_msg, r_shortdata, r_longdata) VALUES (6,'Trigger action 2',0,0,0,60,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}\r\n\r\n{TRIGGER.URL}',1,'Recovery: {TRIGGER.NAME}: {TRIGGER.STATUS}','Recovery: {TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}{TRIGGER.URL}');
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period, def_shortdata, def_longdata, recovery_msg, r_shortdata, r_longdata) VALUES (7,'Trigger action 3',0,0,0,60,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}\r\n\r\n{TRIGGER.URL}',1,'Recovery: {TRIGGER.NAME}: {TRIGGER.STATUS}','Recovery: {TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}{TRIGGER.URL}');
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period, def_shortdata, def_longdata, recovery_msg, r_shortdata, r_longdata) VALUES (8,'Trigger action 4',0,0,1,60,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}\r\n\r\n{TRIGGER.URL}',1,'Recovery: {TRIGGER.NAME}: {TRIGGER.STATUS}','Recovery: {TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}{TRIGGER.URL}');

INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (8,4,5,0,'1');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (9,5,5,0,'1');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (10,6,5,0,'1');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (11,6,15,0,'MySQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (12,6,15,2,'MYSQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (13,6,15,3,'PostgreSQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (14,6,0,0,'3');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (15,6,0,1,'4');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (16,6,13,0,'10001');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (17,6,13,1,'10002');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (18,6,1,0,'10017');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (19,6,1,1,'10017');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (20,6,2,0,'12786');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (21,6,2,1,'12771');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (22,6,3,2,'Oracle');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (23,6,3,3,'DB2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (24,6,4,0,'1');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (25,6,4,1,'2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (26,6,4,5,'3');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (27,6,4,6,'4');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (28,6,4,0,'5');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (29,6,5,0,'0');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (30,6,6,4,'1-7,00:00-23:59');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (31,6,6,7,'6-7,08:00-17:59');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (32,6,16,4,'');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (33,6,16,7,'');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (34,7,5,0,'1');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (35,7,15,0,'MySQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (36,7,15,2,'MYSQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (37,7,15,3,'PostgreSQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (38,7,0,0,'3');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (39,7,0,1,'4');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (40,7,13,0,'10001');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (41,7,13,1,'10002');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (42,7,1,0,'10017');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (43,7,1,1,'10017');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (44,7,2,0,'12786');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (45,7,2,1,'12771');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (46,7,3,2,'Oracle');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (47,7,3,3,'DB2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (48,7,4,0,'1');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (49,7,4,1,'2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (50,7,4,5,'3');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (51,7,4,6,'4');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (52,7,4,0,'5');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (53,7,5,0,'0');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (54,7,6,4,'1-7,00:00-23:59');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (55,7,6,7,'6-7,08:00-17:59');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (56,7,16,4,'');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (57,7,16,7,'');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (58,8,5,0,'1');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (59,8,15,0,'MySQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (60,8,15,2,'MYSQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (61,8,15,3,'PostgreSQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (62,8,0,0,'3');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (63,8,0,1,'4');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (64,8,13,0,'10001');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (65,8,13,1,'10002');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (66,8,1,0,'10017');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (67,8,1,1,'10017');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (68,8,2,0,'12786');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (69,8,2,1,'12771');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (70,8,3,2,'Oracle');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (71,8,3,3,'DB2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (72,8,4,0,'1');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (73,8,4,1,'2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (74,8,4,5,'3');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (75,8,4,6,'4');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (76,8,4,0,'5');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (77,8,5,0,'0');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (78,8,6,4,'1-7,00:00-23:59');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (79,8,6,7,'6-7,08:00-17:59');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (80,8,16,4,'');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (81,8,16,7,'');

INSERT INTO operations (operationid, actionid, operationtype, object, objectid, shortdata, longdata, esc_period, esc_step_from, esc_step_to, default_msg, evaltype, mediatypeid) VALUES (7,4,0,1,2,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}',0,1,1,1,0,NULL);
INSERT INTO operations (operationid, actionid, operationtype, object, objectid, shortdata, longdata, esc_period, esc_step_from, esc_step_to, default_msg, evaltype, mediatypeid) VALUES (8,5,0,1,2,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}',0,1,1,1,0,NULL);
INSERT INTO operations (operationid, actionid, operationtype, object, objectid, shortdata, longdata, esc_period, esc_step_from, esc_step_to, default_msg, evaltype, mediatypeid) VALUES (9,6,0,1,2,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}',0,1,1,1,0,NULL);
INSERT INTO operations (operationid, actionid, operationtype, object, objectid, shortdata, longdata, esc_period, esc_step_from, esc_step_to, default_msg, evaltype, mediatypeid) VALUES (10,7,0,1,2,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}',0,1,1,1,0,NULL);
INSERT INTO operations (operationid, actionid, operationtype, object, objectid, shortdata, longdata, esc_period, esc_step_from, esc_step_to, default_msg, evaltype, mediatypeid) VALUES (11,7,0,1,3,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}',3600,2,2,1,0,1);
INSERT INTO operations (operationid, actionid, operationtype, object, objectid, shortdata, longdata, esc_period, esc_step_from, esc_step_to, default_msg, evaltype, mediatypeid) VALUES (12,7,0,0,1,'Custom: {TRIGGER.NAME}: {TRIGGER.STATUS}','Custom: {TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}',0,5,6,0,0,1);
INSERT INTO operations (operationid, actionid, operationtype, object, objectid, shortdata, longdata, esc_period, esc_step_from, esc_step_to, default_msg, evaltype, mediatypeid) VALUES (13,8,0,1,2,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}',0,1,1,1,0,NULL);
INSERT INTO operations (operationid, actionid, operationtype, object, objectid, shortdata, longdata, esc_period, esc_step_from, esc_step_to, default_msg, evaltype, mediatypeid) VALUES (14,8,0,1,3,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}',3600,2,2,1,0,1);
INSERT INTO operations (operationid, actionid, operationtype, object, objectid, shortdata, longdata, esc_period, esc_step_from, esc_step_to, default_msg, evaltype, mediatypeid) VALUES (15,8,0,0,1,'Custom: {TRIGGER.NAME}: {TRIGGER.STATUS}','Custom: {TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}',0,5,6,0,0,1);
INSERT INTO operations (operationid, actionid, operationtype, object, objectid, shortdata, longdata, esc_period, esc_step_from, esc_step_to, default_msg, evaltype, mediatypeid) VALUES (16,8,1,0,0,'','{HOSTNAME}:/sbin/shutdown -r',0,20,0,0,0,NULL);

INSERT INTO opconditions (opconditionid, operationid, conditiontype, operator, value) VALUES (1,11,14,0,'0');
INSERT INTO opconditions (opconditionid, operationid, conditiontype, operator, value) VALUES (2,11,14,0,'1');
INSERT INTO opconditions (opconditionid, operationid, conditiontype, operator, value) VALUES (3,12,14,0,'0');
INSERT INTO opconditions (opconditionid, operationid, conditiontype, operator, value) VALUES (4,14,14,0,'0');
INSERT INTO opconditions (opconditionid, operationid, conditiontype, operator, value) VALUES (5,14,14,0,'1');
INSERT INTO opconditions (opconditionid, operationid, conditiontype, operator, value) VALUES (6,15,14,0,'0');
INSERT INTO opconditions (opconditionid, operationid, conditiontype, operator, value) VALUES (7,16,14,0,'0');

-- Add auto-registration actions
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period, def_shortdata, def_longdata, recovery_msg, r_shortdata, r_longdata) VALUES (9,'Autoregistration action 1',2,0,0,0,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}\r\n\r\n{TRIGGER.URL}',0,'','');
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period, def_shortdata, def_longdata, recovery_msg, r_shortdata, r_longdata) VALUES (10,'Autoregistration action 2',2,0,1,0,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}\r\n\r\n{TRIGGER.URL}',0,'','');

INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (82,9,22,2,'MySQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (83,9,22,3,'DB2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (84,9,20,0,'10047');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (85,9,20,1,'10048');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (86,10,22,2,'MySQL');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (87,10,22,3,'DB2');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (88,10,20,0,'10047');
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value) VALUES (89,10,20,1,'10048');

INSERT INTO operations (operationid, actionid, operationtype, object, objectid, shortdata, longdata, esc_period, esc_step_from, esc_step_to, default_msg, evaltype, mediatypeid) VALUES (17,9,0,1,1,'Special: {TRIGGER.NAME}: {TRIGGER.STATUS}','Special: {TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}',0,1,1,0,0,NULL);
INSERT INTO operations (operationid, actionid, operationtype, object, objectid, shortdata, longdata, esc_period, esc_step_from, esc_step_to, default_msg, evaltype, mediatypeid) VALUES (18,9,0,1,4,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}',0,1,1,1,0,4);
INSERT INTO operations (operationid, actionid, operationtype, object, objectid, shortdata, longdata, esc_period, esc_step_from, esc_step_to, default_msg, evaltype, mediatypeid) VALUES (19,9,1,0,0,'','{HOSTNAME}:echo TEST',0,1,1,0,0,NULL);
INSERT INTO operations (operationid, actionid, operationtype, object, objectid, shortdata, longdata, esc_period, esc_step_from, esc_step_to, default_msg, evaltype, mediatypeid) VALUES (20,9,2,0,0,'','',0,1,1,0,0,NULL);
INSERT INTO operations (operationid, actionid, operationtype, object, objectid, shortdata, longdata, esc_period, esc_step_from, esc_step_to, default_msg, evaltype, mediatypeid) VALUES (21,9,9,0,0,'','',0,1,1,0,0,NULL);
INSERT INTO operations (operationid, actionid, operationtype, object, objectid, shortdata, longdata, esc_period, esc_step_from, esc_step_to, default_msg, evaltype, mediatypeid) VALUES (22,9,4,0,5,'','',0,1,1,0,0,NULL);
INSERT INTO operations (operationid, actionid, operationtype, object, objectid, shortdata, longdata, esc_period, esc_step_from, esc_step_to, default_msg, evaltype, mediatypeid) VALUES (23,9,6,0,10002,'','',0,1,1,0,0,NULL);
INSERT INTO operations (operationid, actionid, operationtype, object, objectid, shortdata, longdata, esc_period, esc_step_from, esc_step_to, default_msg, evaltype, mediatypeid) VALUES (24,10,0,1,1,'Special: {TRIGGER.NAME}: {TRIGGER.STATUS}','Special: {TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}',0,1,1,0,0,NULL);
INSERT INTO operations (operationid, actionid, operationtype, object, objectid, shortdata, longdata, esc_period, esc_step_from, esc_step_to, default_msg, evaltype, mediatypeid) VALUES (25,10,0,1,4,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}',0,1,1,1,0,4);
INSERT INTO operations (operationid, actionid, operationtype, object, objectid, shortdata, longdata, esc_period, esc_step_from, esc_step_to, default_msg, evaltype, mediatypeid) VALUES (26,10,1,0,0,'','{HOSTNAME}:echo TEST',0,1,1,0,0,NULL);
INSERT INTO operations (operationid, actionid, operationtype, object, objectid, shortdata, longdata, esc_period, esc_step_from, esc_step_to, default_msg, evaltype, mediatypeid) VALUES (27,10,2,0,0,'','',0,1,1,0,0,NULL);
INSERT INTO operations (operationid, actionid, operationtype, object, objectid, shortdata, longdata, esc_period, esc_step_from, esc_step_to, default_msg, evaltype, mediatypeid) VALUES (28,10,9,0,0,'','',0,1,1,0,0,NULL);
INSERT INTO operations (operationid, actionid, operationtype, object, objectid, shortdata, longdata, esc_period, esc_step_from, esc_step_to, default_msg, evaltype, mediatypeid) VALUES (29,10,4,0,5,'','',0,1,1,0,0,NULL);
INSERT INTO operations (operationid, actionid, operationtype, object, objectid, shortdata, longdata, esc_period, esc_step_from, esc_step_to, default_msg, evaltype, mediatypeid) VALUES (30,10,6,0,10002,'','',0,1,1,0,0,NULL);

-- Add more screens
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (3,'Test screen (graph)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (4,'Test screen (clock)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (5,'Test screen (data overview, left align)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (6,'Test screen (history of actions)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (7,'Test screen (history of events)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (8,'Test screen (hosts info, horizontal align)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (9,'Test screen (hosts info, vertical align)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (10,'Test screen (map)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (11,'Test screen (plain text)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (12,'Test screen (screen)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (13,'Test screen (server info)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (14,'Test screen (simple graph)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (15,'Test screen (status of hostgroup triggers)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (16,'Test screen (status of host triggers)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (17,'Test screen (system status)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (18,'Test screen (triggers info, horizontal align)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (19,'Test screen (triggers overview, left align)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (20,'Test screen (triggers overview, top align)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (21,'Test screen (url)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (22,'Test screen (data overview, top align)',1,1,NULL);
INSERT INTO screens (screenid, name, hsize, vsize, templateid) VALUES (23,'Test screen (triggers info, vertical align)',1,1,NULL);

INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic) VALUES (8,3,0,2,500,100,0,0,0,0,0,0,0,0,'',0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic) VALUES (9,4,7,0,500,100,0,0,0,0,0,0,0,0,'',0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic) VALUES (10,5,10,4,500,100,0,0,0,0,0,0,0,0,'',0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic) VALUES (28,23,5,4,500,100,0,0,0,0,0,0,0,1,'',0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic) VALUES (11,6,12,0,500,100,0,0,0,0,25,0,0,0,'',0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic) VALUES (12,7,13,0,500,100,0,0,0,0,25,0,0,0,'',0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic) VALUES (13,8,4,4,500,100,0,0,0,0,0,0,0,0,'',0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic) VALUES (14,9,4,4,500,100,0,0,0,0,0,0,0,1,'',0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic) VALUES (15,10,2,2,500,100,0,0,0,0,0,0,0,0,'',0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic) VALUES (16,11,3,18484,500,100,0,0,0,0,25,0,0,0,'',0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic) VALUES (17,12,8,3,500,100,0,0,0,0,0,0,0,0,'',0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic) VALUES (18,13,6,0,500,100,0,0,0,0,0,0,0,0,'',0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic) VALUES (19,14,1,18443,500,100,0,0,0,0,0,0,0,0,'',0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic) VALUES (20,15,14,4,500,100,0,0,0,0,25,0,0,0,'',0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic) VALUES (21,16,16,10017,500,100,0,0,0,0,25,0,0,0,'',0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic) VALUES (22,17,15,0,500,100,0,0,0,0,0,0,0,0,'',0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic) VALUES (23,18,5,4,500,100,0,0,0,0,0,0,0,0,'',0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic) VALUES (24,19,9,4,500,100,0,0,0,0,0,0,0,0,'',0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic) VALUES (25,20,9,4,500,100,0,0,0,0,0,0,0,1,'',0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic) VALUES (26,21,11,0,500,500,0,0,0,0,0,0,0,0,'http://www.google.com',0);
INSERT INTO screens_items (screenitemid, screenid, resourcetype, resourceid, width, height, x, y, colspan, rowspan, elements, valign, halign, style, url, dynamic) VALUES (27,22,10,4,500,100,0,0,0,0,0,0,0,1,'',0);

-- Add slide shows
INSERT INTO slideshows (slideshowid, name, delay) VALUES (1,'Test slide show 1',10);
INSERT INTO slideshows (slideshowid, name, delay) VALUES (2,'Test slide show 2',10);
INSERT INTO slideshows (slideshowid, name, delay) VALUES (3,'Test slide show 3',900);

INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (1,1,4,0,0);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (2,1,5,1,0);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (3,2,4,0,0);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (4,2,5,1,0);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (5,2,22,2,15);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (6,2,3,3,20);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (7,3,4,0,0);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (8,3,5,1,0);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (9,3,22,2,15);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (10,3,3,3,20);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (11,3,6,4,60);

-- Add maintenance periods
INSERT INTO maintenances (maintenanceid, name, maintenance_type, description, active_since, active_till) VALUES (1,'Maintenance period 1 (data collection)',0,'Test description 1',1294760335,1294846735);
INSERT INTO maintenances (maintenanceid, name, maintenance_type, description, active_since, active_till) VALUES (2,'Maintenance period 2 (no data collection)',1,'Test description 1',1294760335,1294846735);

INSERT INTO maintenances_hosts (maintenance_hostid, maintenanceid, hostid) VALUES (1,1,10017);
INSERT INTO maintenances_hosts (maintenance_hostid, maintenanceid, hostid) VALUES (2,2,10017);

INSERT INTO maintenances_groups (maintenance_groupid, maintenanceid, groupid) VALUES (1,1,4);
INSERT INTO maintenances_groups (maintenance_groupid, maintenanceid, groupid) VALUES (2,2,4);

INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (1,0,1,0,0,1,43200,184200,1294760386);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (2,2,2,0,0,1,43200,93780,1294760409);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (3,3,2,0,85,1,85800,300,1294760438);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (4,4,0,1365,0,15,37500,183840,1294760473);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (5,4,1,2730,85,0,84600,1800,1294760575);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (6,0,1,0,0,1,43200,184200,1294760386);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (7,2,2,0,0,1,43200,93780,1294760409);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (8,3,2,0,85,1,85800,300,1294760438);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (9,4,0,1365,0,15,37500,183840,1294760473);
INSERT INTO timeperiods (timeperiodid, timeperiod_type, every, month, dayofweek, day, start_time, period, start_date) VALUES (10,4,1,2730,85,0,84600,1800,1294760575);

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

-- Add maps
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack) VALUES (3,'Test map 1',800,600,NULL,0,0,1,1,1,2);

INSERT INTO sysmaps_elements (selementid, sysmapid, elementid, elementtype, iconid_off, iconid_on, label, label_location, x, y, iconid_disabled, iconid_maintenance) VALUES (3,3,0,4,7,NULL,'Test phone icon',0,151,101,NULL,NULL);
INSERT INTO sysmaps_elements (selementid, sysmapid, elementid, elementtype, iconid_off, iconid_on, label, label_location, x, y, iconid_disabled, iconid_maintenance) VALUES (4,3,2,1,3,NULL,'Map element (Local network)',0,401,101,NULL,NULL);
INSERT INTO sysmaps_elements (selementid, sysmapid, elementid, elementtype, iconid_off, iconid_on, label, label_location, x, y, iconid_disabled, iconid_maintenance) VALUES (5,3,12788,2,15,NULL,'Trigger element (CPU load)',0,101,301,NULL,NULL);
INSERT INTO sysmaps_elements (selementid, sysmapid, elementid, elementtype, iconid_off, iconid_on, label, label_location, x, y, iconid_disabled, iconid_maintenance) VALUES (6,3,3,3,1,NULL,'Host group element (Windows servers)',0,301,351,NULL,NULL);
INSERT INTO sysmaps_elements (selementid, sysmapid, elementid, elementtype, iconid_off, iconid_on, label, label_location, x, y, iconid_disabled, iconid_maintenance) VALUES (7,3,10017,0,19,NULL,'Host element (Zabbix Server)',0,501,301,NULL,NULL);

INSERT INTO sysmaps_links (linkid, sysmapid, selementid1, selementid2, drawtype, color, label) VALUES (1,3,3,4,2,'00CC00','CPU load: {Zabbix Server:system.cpu.load[].last(0)}');
INSERT INTO sysmaps_links (linkid, sysmapid, selementid1, selementid2, drawtype, color, label) VALUES (2,3,3,5,0,'00CC00','');
INSERT INTO sysmaps_links (linkid, sysmapid, selementid1, selementid2, drawtype, color, label) VALUES (3,3,6,5,0,'00CC00','');
INSERT INTO sysmaps_links (linkid, sysmapid, selementid1, selementid2, drawtype, color, label) VALUES (4,3,7,6,0,'00CC00','');
INSERT INTO sysmaps_links (linkid, sysmapid, selementid1, selementid2, drawtype, color, label) VALUES (5,3,4,7,0,'00CC00','');
INSERT INTO sysmaps_links (linkid, sysmapid, selementid1, selementid2, drawtype, color, label) VALUES (6,3,4,5,0,'00CC00','');
INSERT INTO sysmaps_links (linkid, sysmapid, selementid1, selementid2, drawtype, color, label) VALUES (7,3,3,6,0,'00CC00','');
INSERT INTO sysmaps_links (linkid, sysmapid, selementid1, selementid2, drawtype, color, label) VALUES (8,3,7,3,0,'00CC00','');

INSERT INTO sysmaps_link_triggers (linktriggerid, linkid, triggerid, drawtype, color) VALUES (1,1,12779,4,'DD0000');

INSERT INTO sysmap_element_url (sysmapelementurlid, selementid, name, url) VALUES (1,4,'Zabbix home','www.zabbix.com');
INSERT INTO sysmap_element_url (sysmapelementurlid, selementid, name, url) VALUES (2,5,'www.wikipedia.org','www.wikipedia.org');

-- Host profiles
INSERT INTO hosts_profiles (hostid, devicetype, name, os, serialno, tag, macaddress, hardware, software, contact, location, notes) VALUES (10017,'Device type #1','Name #2','OS #3','SerialNo #4','Tag #5','MAC Address #6','Hardware #7','Software #7','Contact #8','Location #9','Notes #10');

INSERT INTO hosts_profiles_ext (hostid, device_alias, device_type, device_chassis, device_os, device_os_short, device_hw_arch, device_serial, device_model, device_tag, device_vendor, device_contract, device_who, device_status, device_app_01, device_app_02, device_app_03, device_app_04, device_app_05, device_url_1, device_url_2, device_url_3, device_networks, device_notes, device_hardware, device_software, ip_subnet_mask, ip_router, ip_macaddress, oob_ip, oob_subnet_mask, oob_router, date_hw_buy, date_hw_install, date_hw_expiry, date_hw_decomm, site_street_1, site_street_2, site_street_3, site_city, site_state, site_country, site_zip, site_rack, site_notes, poc_1_name, poc_1_email, poc_1_phone_1, poc_1_phone_2, poc_1_cell, poc_1_screen, poc_1_notes, poc_2_name, poc_2_email, poc_2_phone_1, poc_2_phone_2, poc_2_cell, poc_2_screen, poc_2_notes) VALUES (10017,'Alias','Device type','Device Chassis','OS (Full Details)','OS (Short)','HW Architecture','Serial Number','Model Number','Asset Tag','Device Vendor','Device Contract Number','Installer Name','Device Deployment Status','Software Application #1','Software Application #2','Software Application #3','Software Application #4','Software Application #5','URL #1','URL #2','URL #3','Device Port Connections','Device Notes','Device Hardware','Device Software','Host Subnet Mask','Host Router','Host MAC Address','OOB IP Address','OOB Subnet Mask','OOB Router','Date HW Purchased','Date HW Installed','Date HW Maintenance Expires','Date HW Decommissioned','Site Address 1','Site Address 2','Site Address 3','Site City','Site State / Province','Site Country','Site Zip / Postal','Site Rack Location','Site Notes','Primary POC Name','Primary POC Email','Primary POC Phone 1','Primary POC Phone 2','Primary POC Cell','Primary POC Screen Name','Primary POC Comments','Secondary POC Name','Secondary POC Email','Secondary POC Phone 1','Secondary POC Phone 2','Secondary POC Cell','Secondary POC Screen Name','Secondary POC Comments');
