-- Test data for API tests

-- Activate "Zabbix Server" host
UPDATE hosts SET status=0 WHERE host='Zabbix server';

-- host groups
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50009, 'API Host', 'API Host', 0, '');
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50010, 'API Template', 'API Template', 3, '');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (50022,50009,1,1,1,'127.0.0.1','','10050');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (50029,50009,1,2,1,'127.0.0.1','','161');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (50030,50009,1,4,1,'127.0.0.1','','12345');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (50031,50009,1,3,1,'127.0.0.1','','623');
INSERT INTO interface_snmp (interfaceid, version, bulk, community, securityname, securitylevel, authpassphrase, privpassphrase, authprotocol, privprotocol, contextname) VALUES (50029, 2, 1, '{$SNMP_COMMUNITY}', '', 0, '', '', 0, 0, '');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (50012,0,'34a832fc9add475290d1655a012b20ee','API group for hosts');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (50013,1,'df60e37bb99849a9817e9805c4496cae','API group for templates');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50009, 50009, 50012);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50011, 50010, 50013);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (50003, 50009, 50010);
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (400660, 50009, 50022, 0, 2,'API discovery rule','vfs.fs.discovery',30,90,0,'','',1,'','');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (50005,0,'c5ed6d1365b145c5b4f522832909b22e','API host group for update');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50010, 50009, 50005);
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (50006,0,'9cd829bbd9e94619a15694489aacb1cc','API host group for update internal');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (50007,0,'395e5c5d29cf4ffcbc07b1a649b64a1c','API host group delete internal');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (50008,0,'2ceb313566944cb3ab0ab2106b99f01c','API host group delete');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (50009,0,'f04424f8880d4bc8a3b3075d1e246224','API host group delete2');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (50010,0,'7a6aac19b9dc42a1afb7582a8e3d4283','API host group delete3');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (50011,0,'a93383e7641547fe9572f36e6937f9fb','API host group delete4');
-- discovered host groups
INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (50011, 'API host prototype {#FSNAME}', 'API host prototype {#FSNAME}', 0, 2, '');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (50014,0,'31e39dc724624c97b2e93caba8517465','API group for host prototype');
INSERT INTO host_discovery (hostid,parent_hostid,parent_itemid) VALUES (50011,NULL,400660);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (50108, 50011, 'API discovery group {#HV.NAME}', NULL, NULL);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (50109, 50011, '', 50014, NULL);
INSERT INTO hstgrp (groupid,type,uuid,name,flags) VALUES (50015,0,'de6322aebbae4f53acfc87747da877d8','API discovery group {#HV.NAME}',4);
INSERT INTO group_discovery (groupid, parent_group_prototypeid, name) VALUES (50015, 50108, 'API discovery group {#HV.NAME}');
-- host prototype for delete
INSERT INTO hosts (hostid, host, name, status, flags, description, custom_interfaces) VALUES (50015, 'API host prototype for delete {#FSNAME}', 'API host prototype for delete {#FSNAME}', 0, 2, '', 1);
INSERT INTO host_discovery (hostid,parent_hostid,parent_itemid) VALUES (50015,NULL,400660);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (50112, 50015, '', 50014, NULL);
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (50028,50015,1,2,1,'127.0.0.1','','10050');
INSERT INTO interface_snmp (interfaceid, version, bulk, community) values (50028, 2, 1, '{$SNMP_COMMUNITY}');
-- template groups
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (52001,1,'0017df112619495d833b7c22ff373fc1','API template group 1');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (52002,1,'9aa1b2ef98c24511a6f7247404882504','API template group 2');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (52003,1,'0ebed341e6694061b36aec53d0b3ea32','API template group 3');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (52004,1,'ba7a6cc0bf17407fbda874131f023678','API template group to delete');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (52005,1,'1785b8abeb844a85a76961f31a64a60d','API template group with template 1');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (52006,1,'77f66d8c2dae4802a6c73ca6acb4e1db','API template group with template 2');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (52007,1,'e006fa58254e4961b7f0437c919909e8','API template group to delete 2');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (52008,1,'720287feafa340278a5fd726b687c752','API template group to delete 3');
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50020, 'API Template 2', 'API Template 2', 3, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (55001, 50020, 52005);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (55002, 50020, 52006);

-- user group
INSERT INTO usrgrp (usrgrpid, name) VALUES (13, 'API user group for update');
INSERT INTO usrgrp (usrgrpid, name) VALUES (14, 'API user group for update with user and rights');
INSERT INTO usrgrp (usrgrpid, name) VALUES (15, 'API user group with one user');
INSERT INTO usrgrp (usrgrpid, name) VALUES (16, 'API user group delete');
INSERT INTO usrgrp (usrgrpid, name) VALUES (17, 'API user group delete1');
INSERT INTO usrgrp (usrgrpid, name) VALUES (18, 'API user group delete2');
INSERT INTO usrgrp (usrgrpid, name) VALUES (19, 'API user group delete3');
INSERT INTO usrgrp (usrgrpid, name) VALUES (20, 'API user group in actions');
INSERT INTO usrgrp (usrgrpid, name) VALUES (21, 'API user group in scripts');
INSERT INTO usrgrp (usrgrpid, name) VALUES (22, 'API user group in configuration');
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (4, 'zabbix-admin', '$2a$10$PmEcvov/w84R3sShOV4rX.xJd81bwgaK4o0SfoiSxop2ol7PPGsOi', 0, 0, 'en_US', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (5, 'zabbix-user', '$2a$10$w8oiYEgP3Fy4XuPIE5VCiO2j5snJEopKfTCYa3DC7bNL83ldKlPRS', 0, 0, 'en_US', '30s', 1, 'default', 0, 0, 50);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (6, 'user-in-one-group', '$2a$10$mTYvfZskz3369zQaYLogHuSUMQ11YSEOZtua2NFSL3/.T6kQ/bNaG', 0, 0, 'en_US', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (7, 'user-in-two-groups', '$2a$10$GiBCQXAPeTCPR9rEQ/YodOmE7mqvXjYwbEkZLGP7iWU/fzKcB9yF6', 0, 0, 'en_US', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (8, 'api-user', '$2a$10$NyZQvuelvUVqpCDYb7cOy.pEewNe9U0MK0ZIdjJeupYbgHU6G7Iea', 0, 0, 'en_US', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (6, 8, 4);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (8, 14, 4);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (9, 15, 6);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (10, 16, 7);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (11, 17, 7);
INSERT INTO rights (rightid, groupid, permission, id) VALUES (2, 14, 3, 50012);
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period) VALUES (16, 'API action', 0, 0, 0, 60);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (31, 16, 0, 0, 1, 1, 0);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (31, 0, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', NULL);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (21, 31, 20);
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (5, 'API script', 'test', 2, 21, NULL, 'api script description', '', 0, 2, '30s', 1, '', 0, '', '', '', '', '');
UPDATE config SET alert_usrgrpid = 22 WHERE configid = 1;

-- users
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (9, 'api-user-for-update', '$2a$10$dP76CSji4ozQxSxLQeUGc.sJgSPuwN8b4pjnKIoOeQXts2Wm86ige', 0, '15m', 'en_US', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (10, 'api-user-delete', '$2a$10$8ioYyO/Xkyhx64W.z0B3YONQ7.s2zqMRqhkYt/z6S9.MkqEYsWCOq', 0, '15m', 'en_US', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (11, 'api-user-delete1', '$2a$10$NU0MhxghxIbvCen5pBY.WuC9eYpqYS2mE8P6dQIMC00yhlalXhUWO', 0, '15m', 'en_US', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (12, 'api-user-delete2', '$2a$10$t.cDXioxmkgwEigzPU0aQejc8rAfjt6ZxY6WIllrN0IpEH4pp3I/K', 0, '15m', 'en_US', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (13, 'api-user-action', '$2a$10$w6u3jruB673s5A/Qrg7VZOFof/yuARrPQYpZk7xbSTw7O/wgSw9Sq', 0, '15m', 'en_US', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (14, 'api-user-map', '$2a$10$1uCgmg.SoVtN98NTt/815./E/mFIdJH2r3aF1RFY1QwmFVlnbCXTK', 0, '15m', 'en_US', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (15, 'api-user-for-unblock', '$2a$10$/a5lFsoEm56b01q1uAoM8ecSmazNhrYbidYeBibtRzUxbIgmIAvR.', 0, '15m', 'en_US', '30s', 2, 'default', 5, 0, 50);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (12, 14, 9);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (13, 14, 10);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (14, 14, 11);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (15, 14, 12);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (16, 9, 13);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (17, 14, 14);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (20, 14, 5);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (21, 13, 15);
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period) VALUES (17, 'API action with user', 0, 0, 0, 60);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (32, 17, 0, 0, 1, 1, 0);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (32, 0, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', NULL);
INSERT INTO opmessage_usr (opmessage_usrid, operationid, userid) VALUES (4, 32, 13);
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, userid, private) VALUES (6, 'API map', 800, 600, NULL, 0, 0, 1, 1, 1, 2, 14, 0);

-- valuemap
INSERT INTO valuemap (valuemapid,hostid,name) VALUES (1399,50009,'API value map for update');
INSERT INTO valuemap (valuemapid,hostid,name) VALUES (1400,50009,'API value map for update with mappings');
INSERT INTO valuemap (valuemapid,hostid,name) VALUES (1401,50009,'API value map delete');
INSERT INTO valuemap (valuemapid,hostid,name) VALUES (1402,50009,'API value map delete2');
INSERT INTO valuemap (valuemapid,hostid,name) VALUES (1403,50009,'API value map delete3');
INSERT INTO valuemap (valuemapid,hostid,name) VALUES (1404,50009,'API value map delete4');
INSERT INTO valuemap (valuemapid,hostid,name) VALUES (1405,50009,'API value duplicate');
INSERT INTO valuemap_mapping (valuemap_mappingid,valuemapid,value,newvalue) VALUES (99040,1399,'1','Unknown');
INSERT INTO valuemap_mapping (valuemap_mappingid,valuemapid,value,newvalue) VALUES (99050,1400,'One','Online');
INSERT INTO valuemap_mapping (valuemap_mappingid,valuemapid,value,newvalue) VALUES (99060,1400,'Two','Offline');
INSERT INTO valuemap_mapping (valuemap_mappingid,valuemapid,value,newvalue) VALUES (99070,1401,'1','Unknown');
INSERT INTO valuemap_mapping (valuemap_mappingid,valuemapid,value,newvalue) VALUES (99080,1402,'Three','Other');
INSERT INTO valuemap_mapping (valuemap_mappingid,valuemapid,value,newvalue) VALUES (99090,1403,'Four','Unknown');
INSERT INTO valuemap_mapping (valuemap_mappingid,valuemapid,value,newvalue) VALUES (99100,1404,'1','Unknown');
INSERT INTO valuemap_mapping (valuemap_mappingid,valuemapid,value,newvalue) VALUES (99110,1405,'1','Unknown');

-- scripts
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50013, 'API disabled host', 'API disabled host', 1, '');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (50024,50013,1,1,1,'127.0.0.1','','10050');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (90000,0,'07e8e62cba8343f7bf6cbfbd69c7c5d9','API group for disabled host');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50013, 50013, 90000);
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50012, 'API Host for read permissions', 'API Host for read permissions', 0, '');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (50023,50012,1,1,1,'127.0.0.1','','10050');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (50016,0,'2e684a6d9f22417d8d2ef286c9f86e97','API group with read permissions');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50012, 50012, 50016);
INSERT INTO rights (rightid, groupid, permission, id) VALUES (3, 14, 2, 50016);
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50014, 'API Host for deny permissions', 'API Host for deny permissions', 0, '');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (50025,50014,1,1,1,'127.0.0.1','','10050');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (50017,0,'1d53a0938db34c5f8e5116487e620477','API group with deny permissions');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50014, 50014, 50017);
INSERT INTO rights (rightid, groupid, permission, id) VALUES (4, 14, 0, 50017);
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (6, 'API script for update one',                             '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     0, 2, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (7, 'API script for update two',                             '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     0, 2, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (8, 'API script for delete',                                 '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     0, 2, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (9, 'API script for delete1',                                '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     0, 2, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (10, 'API script for delete2',                               '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     0, 2, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (11, 'API script in action',                                 '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     0, 2, '30s', 1, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (12, 'API script with user group',                           '/sbin/shutdown -r', 2, 7,    NULL, '',                 '',     0, 2, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (13, 'API script with host group',                           '/sbin/shutdown -r', 2, NULL, 4,    '',                 '',     0, 2, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (14, 'API script with write permissions for the host group', '/sbin/shutdown -r', 3, NULL, NULL, '',                 '',     0, 2, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (15, 'API script custom execute on agent (action scope)',    '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     0, 0, '30s', 1, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (16, 'API script custom execute on agent (host scope)',      '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     0, 0, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (17, 'API script custom execute on agent (event scope)',     '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     0, 0, '30s', 4, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (18, 'API script custom execute on server',                  '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     0, 1, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (19, 'API script custom execute on proxy',                   '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     0, 2, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (20, 'API script IPMI',                                      '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     1, 2, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (21, 'API script SSH password',                              '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     2, 2, '30s', 2, '123', 0, 'John', '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (22, 'API script SSH public key',                            '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     2, 2, '30s', 2, '123', 1, 'John', '', 'pub-k', 'priv-k', '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (23, 'API script Telnet',                                    '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     3, 2, '30s', 2, '123', 0, 'Jill', '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (24, 'API script Webhook no params',                         '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     5, 2, '10s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (25, 'API script Webhook with params',                       '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     5, 2, '25',  2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (26, 'API script Webhook with params to change',             '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     5, 2, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (27, 'API script custom for change to other one',            '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     0, 0, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (28, 'API script custom for change to other two',            '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     0, 0, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (29, 'API script custom for change to other three',          '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     0, 0, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (30, 'API script custom for change to other four',           '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     0, 0, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (31, 'API script custom for change to other five',           '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     0, 0, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (32, 'API script IPMI for change to other one',              '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     1, 2, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (33, 'API script IPMI for change to other two',              '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     1, 2, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (34, 'API script IPMI for change to other three',            '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     1, 2, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (35, 'API script IPMI for change to other four',             '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     1, 2, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (36, 'API script IPMI for change to other five',             '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     1, 2, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (37, 'API script SSH password for change to other one',      '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     2, 2, '30s', 2, '123', 0, 'John', '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (38, 'API script SSH password for change to other two',      '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     2, 2, '30s', 2, '123', 0, 'John', '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (39, 'API script SSH password for change to other three',    '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     2, 2, '30s', 2, '123', 0, 'John', '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (40, 'API script SSH password for change to other four',     '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     2, 2, '30s', 2, '123', 0, 'John', '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (41, 'API script SSH public key for change to other one',    '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     2, 2, '30s', 2, '123', 1, 'John', '', 'pub-k', 'priv-k', '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (42, 'API script SSH public key for change to other two',    '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     2, 2, '30s', 2, '123', 1, 'John', '', 'pub-k', 'priv-k', '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (43, 'API script SSH public key for change to other three',  '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     2, 2, '30s', 2, '123', 1, 'John', '', 'pub-k', 'priv-k', '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (44, 'API script SSH public key for change to other four',   '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     2, 2, '30s', 2, '123', 1, 'John', '', 'pub-k', 'priv-k', '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (45, 'API script Telnet for change to other one',            '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     3, 2, '30s', 2, '123', 0, 'Jill', '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (46, 'API script Telnet for change to other two',            '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     3, 2, '30s', 2, '123', 0, 'Jill', '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (47, 'API script Telnet for change to other three',          '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     3, 2, '30s', 2, '123', 0, 'Jill', '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (48, 'API script Telnet for change to other four',           '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     3, 2, '30s', 2, '123', 0, 'Jill', '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (49, 'API script Telnet for change to other five',           '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     3, 2, '30s', 2, '123', 0, 'Jill', '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (50, 'API script Webhook for change to other one',           '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     5, 2, '25',  2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (51, 'API script Webhook for change to other two',           '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     5, 2, '25',  2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (52, 'API script Webhook for change to other three',         '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     5, 2, '25',  2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (53, 'API script Webhook for change to other four',          '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     5, 2, '25',  2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (54, 'API script Webhook for change to other five',          '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     5, 2, '25',  2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (55, 'API scope update (action scope)',                      '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     0, 1, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (56, 'API scope update (host scope)',                        '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     0, 2, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (57, 'API scope update (event scope)',                       '/sbin/shutdown -r', 2, NULL, NULL, '',                 '',     0, 4, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (58, 'API scope reset to action',                            '/sbin/shutdown -r', 3, 7,    NULL, '',                 'text', 0, 2, '30s', 2, '',    0, '',     '', '',      '',       '/home');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (59, 'Script for Get1',                                      'test',              2, NULL, NULL, 'Get1 description', '',     5, 2, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (60, 'Script for Get2',                                      'test',              2, NULL, NULL, 'Get2 description', '',     1, 2, '30s', 2, '',    0, '',     '', '',      '',       '');
INSERT INTO script_param (script_paramid, scriptid, name, value) VALUES (1,  25, 'param 1',  '');
INSERT INTO script_param (script_paramid, scriptid, name, value) VALUES (2,  25, 'param 2',  'value 2');
INSERT INTO script_param (script_paramid, scriptid, name, value) VALUES (3,  25, 'param 3',  'value 3');
INSERT INTO script_param (script_paramid, scriptid, name, value) VALUES (4,  26, 'username', 'John');
INSERT INTO script_param (script_paramid, scriptid, name, value) VALUES (5,  26, 'password', 'Ada');
INSERT INTO script_param (script_paramid, scriptid, name, value) VALUES (6,  50, 'param 1',  'value 1');
INSERT INTO script_param (script_paramid, scriptid, name, value) VALUES (7,  51, 'param 1',  'value 1');
INSERT INTO script_param (script_paramid, scriptid, name, value) VALUES (8,  52, 'param 1',  'value 1');
INSERT INTO script_param (script_paramid, scriptid, name, value) VALUES (9,  53, 'param 1',  'value 1');
INSERT INTO script_param (script_paramid, scriptid, name, value) VALUES (10, 54, 'param 1',  'value 1');
INSERT INTO script_param (script_paramid, scriptid, name, value) VALUES (11, 59, 'param 1',  'value 1');
INSERT INTO script_param (script_paramid, scriptid, name, value) VALUES (12, 59, 'param 2',  'value 2');
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period) VALUES (18, 'API action with script', 0, 0, 0, 60);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (33, 18, 1, 0, 1, 1, 0);
INSERT INTO opcommand_hst (opcommand_hstid, operationid, hostid) VALUES (4, 33, NULL);
INSERT INTO opcommand (operationid, scriptid) VALUES (33, 11);

-- scripts / inherited hostgroups
INSERT INTO usrgrp (usrgrpid,name) VALUES (90000,'90000 Eur group write except one');
INSERT INTO users (userid,username,passwd,roleid) VALUES (90000,'90000','$2a$10$Hr7Z1FX/x9OPhdUu9.5CL.XyL9IKPiVcoxJgGbtIHc3.Svk/awB5q',2);
INSERT INTO users_groups (id,usrgrpid,userid) VALUES (90000,90000,90000);
INSERT INTO hosts (hostid,host,name,status,description) VALUES (90020,'90020','90020',0,'');
INSERT INTO hosts (hostid,host,name,status,description) VALUES (90021,'90021','90021',0,'');
INSERT INTO hosts (hostid,host,name,status,description) VALUES (90022,'90022','90022',0,'');
INSERT INTO hosts (hostid,host,name,status,description) VALUES (90023,'90023','90023',0,'');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (90020,0,'96258844beaf4c1f9528ca96b32f24de','90000Eur');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (90021,0,'53f820730464462ea00d258d71359947','90000Eur/LV');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (90022,0,'44da321defb8472387bcf4b64763581b','90000Eur/LV/Rix');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (90023,0,'a3665e88c2bb44da96e0b86671b9552f','90000Eur/LV/Skipped/Rix');
INSERT INTO rights (rightid,groupid,permission,id) VALUES (90000,90000,3,90020);
INSERT INTO rights (rightid,groupid,permission,id) VALUES (90001,90000,2,90021);
INSERT INTO rights (rightid,groupid,permission,id) VALUES (90002,90000,3,90022);
INSERT INTO rights (rightid,groupid,permission,id) VALUES (90003,90000,3,90023);
INSERT INTO hosts_groups (hostid,groupid,hostgroupid) VALUES (90020,90020,90020);
INSERT INTO hosts_groups (hostid,groupid,hostgroupid) VALUES (90021,90021,90021);
INSERT INTO hosts_groups (hostid,groupid,hostgroupid) VALUES (90022,90022,90022);
INSERT INTO hosts_groups (hostid,groupid,hostgroupid) VALUES (90023,90023,90023);
INSERT INTO scripts (scriptid, groupid, host_access, name, command, usrgrpid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (90020, 90020, 2, '90020-acc-read', 'date', NULL, '', '', 0, 2, '30s', 1, '', 0, '', '', '', '', '');
INSERT INTO scripts (scriptid, groupid, host_access, name, command, usrgrpid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (90021, 90021, 3, '90021-acc-write', 'date', NULL, '', '', 0, 2, '30s', 1, '', 0, '', '', '', '', '');
INSERT INTO scripts (scriptid, groupid, host_access, name, command, usrgrpid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (90023, 90023, 2, '90023-acc-read', 'date', NULL, '', '', 0, 2, '30s', 1, '', 0, '', '', '', '', '');

-- global macro
INSERT INTO globalmacro (globalmacroid, macro, value, description) VALUES (13,'{$API_MACRO_FOR_UPDATE1}','update','desc');
INSERT INTO globalmacro (globalmacroid, macro, value, description) VALUES (14,'{$API_MACRO_FOR_UPDATE2}','update','');
INSERT INTO globalmacro (globalmacroid, macro, value, description) VALUES (15,'{$API_MACRO_FOR_DELETE}','abc','');
INSERT INTO globalmacro (globalmacroid, macro, value, description) VALUES (16,'{$API_MACRO_FOR_DELETE1}','1','');
INSERT INTO globalmacro (globalmacroid, macro, value, description) VALUES (17,'{$API_MACRO_FOR_DELETE2}','2','');

-- host macro
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (1,90020,'{$HOST_MACRO_1}','value','description');
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (2,90020,'{$HOST_MACRO_2}','value','');

-- icon map
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (1,'API icon map',2);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (1,1,2,1,'api icon map expression',0);
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (2,'API icon map for update1',2);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (2,2,2,1,'api expression for update1',0);
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (3,'API icon map for update2',2);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (3,3,2,1,'api expression for update2',0);
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (4,'API icon map for delete',2);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (4,4,2,1,'api expression for delete',0);
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (5,'API icon map for delete1',2);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (5,5,2,1,'api expression for delete1',0);
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (6,'API icon map for delete2',2);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (6,6,2,1,'api expression for delete2',0);
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (7,'API iconmap in map',2);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (7,7,7,1,'api expression',0);
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, iconmapid, userid, private) VALUES (7, 'Map with iconmap', 800, 600, NULL, 0, 0, 1, 1, 1, 2, 7, 1, 0);

-- web scenarios
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15000, 'Api web scenario', 60, 'Zabbix', 50009);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15000, 15000, 'Api step', 1, 'http://api.com', '');
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15001, 'Api web scenario for update one', 60, 'Zabbix', 50009);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15001, 15001, 'Api step for update one', 1, 'http://api1.com', '');
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15002, 'Api web scenario for update two', 60, 'Zabbix', 50009);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15002, 15002, 'Api step for update two', 1, 'http://api2.com', '');
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15003, 'Api web scenario for delete0', 60, 'Zabbix', 50009);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15003, 15003, 'Api step for delete0', 1, 'http://api.com', '');
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15004, 'Api web scenario for delete1', 60, 'Zabbix', 50009);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15004, 15004, 'Api step for delete1', 1, 'http://api.com', '');
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15005, 'Api web scenario for delete2', 60, 'Zabbix', 50009);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15005, 15005, 'Api step for delete2', 1, 'http://api.com', '');
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15006, 'Api templated web scenario', 60, 'Zabbix', 50010);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15006, 15006, 'Api templated step', 1, 'http://api.com', '');
INSERT INTO httptest (httptestid, name, delay, agent, hostid, templateid) VALUES (15007, 'Api templated web scenario', 60, 'Zabbix', 50009, 15006);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15007, 15007, 'Api templated step', 1, 'http://api.com', '');
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15008, 'Api web scenario with read permissions', 60, 'Zabbix', 50012);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15008, 15008, 'Api step with read permissions', 1, 'http://api.com', '');
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15009, 'Api web with deny permissions', 60, 'Zabbix', 50014);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15009, 15009, 'Api step with deny permissions', 1, 'http://api.com', '');
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15010, 'Api web scenario for delete as zabbix-admin', 60, 'Zabbix', 50009);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15010, 15010, 'Api step for delete as zabbix-admin', 1, 'http://api.com', '');
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15011, 'Api web scenario for delete as zabbix-user', 60, 'Zabbix', 50009);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15011, 15011, 'Api step for delete as zabbix-user', 1, 'http://api.com', '');
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15012, 'Api web scenario for update having 1 step', 60, 'Zabbix', 50009);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15012, 15012, 'Api step for update having 1 step', 1, 'http://api.com', '');
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15013, 'Api web scenario for update having 2 steps', 60, 'Zabbix', 50009);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15013, 15013, 'Api step 1', 1, 'http://api.com', '');
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15014, 15013, 'Api step 2', 2, 'http://api.com', '');

-- web scenario for webitem update testing
INSERT INTO httptest (httptestid, name, delay, agent, hostid) VALUES (15015, 'Webtest key_name', 60, 'Zabbix', 50009);
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15015, 15015, 'Webstep name 1', 1, 'http://api.com', '');
INSERT INTO httpstep (httpstepid, httptestid, name, no, url, posts) VALUES (15016, 15015, 'Webstep name 2', 2, 'http://api.com', '');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (150151, 50009, 1, 9, 0,'Download speed for scenario "$1".','web.test.in[Webtest key_name,,bps]','2m','30d',0,'','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (150152, 50009, 1, 9, 3,'Failed step of scenario "$1".','web.test.fail[Webtest key_name]','2m','30d',0,'','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (150153, 50009, 1, 9, 1,'Last error message of scenario "$1".','web.test.error[Webtest key_name]','2m','30d',0,'','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (150154, 50009, 1, 9, 0,'Download speed for step "$2" of scenario "$1".','web.test.in[Webtest key_name,Webstep name 1,bps]','1m','30d',0,'','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (150155, 50009, 1, 9, 0,'Response time for step "$2" of scenario "$1".','web.test.time[Webtest key_name,Webstep name 1,resp]','1m','30d',0,'','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (150156, 50009, 1, 9, 3,'Response code for step "$2" of scenario "$1".','web.test.rspcode[Webtest key_name,Webstep name 1]','1m','30d',0,'','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (150157, 50009, 1, 9, 0,'Download speed for step "$2" of scenario "$1".','web.test.in[Webtest key_name,Webstep name 2,bps]','1m','30d',0,'','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (150158, 50009, 1, 9, 0,'Response time for step "$2" of scenario "$1".','web.test.time[Webtest key_name,Webstep name 2,resp]','1m','30d',0,'','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (150159, 50009, 1, 9, 3,'Response code for step "$2" of scenario "$1".','web.test.rspcode[Webtest key_name,Webstep name 2]','1m','30d',0,'','',0,'','');
INSERT INTO httptestitem (httptestitemid, httptestid, itemid, type) VALUES (150151, 15015, 150151, 2);
INSERT INTO httptestitem (httptestitemid, httptestid, itemid, type) VALUES (150152, 15015, 150152, 3);
INSERT INTO httptestitem (httptestitemid, httptestid, itemid, type) VALUES (150153, 15015, 150153, 4);
INSERT INTO httpstepitem (httpstepitemid, httpstepid, itemid, type) VALUES (150154, 15015, 150154, 2);
INSERT INTO httpstepitem (httpstepitemid, httpstepid, itemid, type) VALUES (150155, 15015, 150155, 1);
INSERT INTO httpstepitem (httpstepitemid, httpstepid, itemid, type) VALUES (150156, 15015, 150156, 0);
INSERT INTO httpstepitem (httpstepitemid, httpstepid, itemid, type) VALUES (150157, 15016, 150157, 2);
INSERT INTO httpstepitem (httpstepitemid, httpstepid, itemid, type) VALUES (150158, 15016, 150158, 1);
INSERT INTO httpstepitem (httpstepitemid, httpstepid, itemid, type) VALUES (150159, 15016, 150159, 0);

-- proxy
INSERT INTO hosts (hostid, host, status, description) VALUES (99000, 'Api active proxy for delete0', 5, '');
INSERT INTO hosts (hostid, host, status, description) VALUES (99001, 'Api active proxy for delete1', 5, '');
INSERT INTO hosts (hostid, host, status, description) VALUES (99002, 'Api passive proxy for delete', 6, '');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) VALUES (99002, 99002,1, 0, 1, '127.0.0.1', 'localhost', 10051);
INSERT INTO hosts (hostid, host, status, description) VALUES (99003, 'Api active proxy in action', 5, '');
INSERT INTO hosts (hostid, host, status, description) VALUES (99004, 'Api active proxy with host', 5, '');
INSERT INTO hosts (hostid, proxy_hostid, host, name, status, description) VALUES (99005, 99004,'API Host monitored with proxy', 'API Host monitored with proxy', 0, '');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (99003,99005,1,1,1,'127.0.0.1','','10050');
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period) VALUES (90, 'API action with proxy', 1, 0, 0, '1h');
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (90, 90, 0, 0, 1, 1, 0);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (90, 0, 'Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}', 'Discovery rule: {DISCOVERY.RULE.NAME}', NULL);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (90, 90, 7);
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value, value2) VALUES (90,90,20,0,99003,'');

-- sysmaps
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, userid, private) VALUES (10001, 'A', 800, 600, NULL, 0, 0, 1, 1, 1, 2, 1, 0);
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, userid, private) VALUES (10002, 'B', 800, 600, NULL, 0, 0, 1, 1, 1, 2, 1, 1);
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, userid, private) VALUES (10003, 'C', 800, 600, NULL, 0, 0, 1, 1, 1, 2, 1, 0);
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, userid, private) VALUES (10004, 'D', 800, 600, NULL, 0, 0, 1, 1, 1, 2, 1, 0);
INSERT INTO sysmap_user (sysmapuserid, sysmapid, userid, permission) VALUES (1, 10001, 5, 3);
INSERT INTO sysmap_user (sysmapuserid, sysmapid, userid, permission) VALUES (2, 10003, 5, 3);
INSERT INTO sysmap_user (sysmapuserid, sysmapid, userid, permission) VALUES (3, 10004, 5, 3);
INSERT INTO sysmaps_elements (selementid, sysmapid, elementid, elementtype, iconid_off, iconid_on, label, label_location, x, y, iconid_disabled, iconid_maintenance, elementsubtype, areatype, width, height, viewtype, use_iconmap) VALUES (7, 10001, 0, 4, 151, NULL, 'New element', -1, 189, 77, NULL, NULL, 0, 0, 200, 200, 0, 1);

-- disabled item and LLD rule
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (90000, 10084, 1, 0, 3,'Api disabled item','disabled.item','30d','90d',1,'','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (90001, 10084, 1, 0, 4,'Api disabled LLD rule','disabled.lld','30d','90d',1,'','',1,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (90002, 50013, 50024, 0, 3,'Api item in disabled host','disabled.host.item','30d','90d',0,'','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (90003, 50013, 50024, 0, 4,'Api LLD rule in disabled host','disabled.host.lld','30d','90d',0,'','',1,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (90004, 10084, 1, 0, 3,'Api item for different item types','types.item','1d','90d',0,'','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags,posts,headers) VALUES (90005, 10084, 1, 0, 4,'Api LLD rule for different types','types.lld','1d','90d',0,'','',1,'','');

-- interfaces
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (99004,10084,1,2,1,'127.0.0.1','','161');
INSERT INTO interface_snmp (interfaceid, version, bulk, community) values (99004, 2, 1, '{$SNMP_COMMUNITY}');

-- autoregistration action
INSERT INTO usrgrp (usrgrpid, name) VALUES (47, 'User group for action delete');
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (53, 'action-user', '$2a$10$gFL5ORa/Ml0VBDGraHI3tuE1WuiKOX8ef497bAfzNiSXUx4Vrrn.y', 0, 0, 'en_US', '30s', 1, 'default', 0, 0, 50);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (54, 'action-admin', '$2a$10$P8CZ/rs94pLp177hh27KheWKAKa6GXZLFhOE8ymd/QlEKT2FDngZe', 0, 0, 'en_US', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (87, 47, 53);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (88, 47, 54);
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period) VALUES (91, 'API Autoregistration action', 2, 0, 0, '1h');
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (91, 91, 0, 0, 1, 1, 0);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (91, 0, 'Autoregistration: {HOST.HOST}', 'Host name: {HOST.HOST}', NULL);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (91, 91, 47);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (92, 91, 0, 0, 1, 1, 0);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (92, 0, 'Problem: {EVENT.NAME}', 'Problem started at {EVENT.TIME} on {EVENT.DATE}\r\nProblem name: {EVENT.NAME}\r\nHost: {HOST.NAME}\r\nSeverity: {TRIGGER.SEVERITY}\r\n\r\nOriginal problem ID: {EVENT.ID}\r\n{TRIGGER.URL}', NULL);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (92, 92, 47);
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period) VALUES (93, 'API Action for deleting 2', 0, 0, 0, '1h');
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (93, 91, 0, 0, 1, 1, 0);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (93, 0, 'Problem: {EVENT.NAME}', 'Problem started at {EVENT.TIME} on {EVENT.DATE}\r\nProblem name: {EVENT.NAME}\r\nHost: {HOST.NAME}\r\nSeverity: {TRIGGER.SEVERITY}\r\n\r\nOriginal problem ID: {EVENT.ID}\r\n{TRIGGER.URL}', NULL);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (94, 93, 47);

-- event correlation
INSERT INTO correlation (correlationid, name, description, evaltype, status, formula) VALUES (99000, 'Event correlation for delete', 'Test description delete', 0, 0, '');
INSERT INTO corr_condition (corr_conditionid, correlationid, type) VALUES (99000, 99000, 0);
INSERT INTO corr_condition_tag (corr_conditionid, tag) VALUES (99000, 'delete tag');
INSERT INTO corr_operation (corr_operationid, correlationid, type) VALUES (99000, 99000, 0);

INSERT INTO correlation (correlationid, name, description, evaltype, status, formula) VALUES (99001, 'Event correlation for update', 'Test description update', 0, 0, '');
INSERT INTO corr_condition (corr_conditionid, correlationid, type) VALUES (99001, 99001, 0);
INSERT INTO corr_condition_tag (corr_conditionid, tag) VALUES (99001, 'update tag');
INSERT INTO corr_operation (corr_operationid, correlationid, type) VALUES (99001, 99001, 0);

INSERT INTO correlation (correlationid, name, description, evaltype, status, formula) VALUES (99002, 'Event correlation for cancel', 'Test description cancel', 1, 0, '');
INSERT INTO corr_condition (corr_conditionid, correlationid, type) VALUES (99002, 99002, 0);
INSERT INTO corr_condition_tag (corr_conditionid, tag) VALUES (99002, 'cancel tag');
INSERT INTO corr_operation (corr_operationid, correlationid, type) VALUES (99002, 99002, 0);

INSERT INTO correlation (correlationid, name, description, evaltype, status, formula) VALUES (99003, 'Event correlation for clone', 'Test description clone', 0, 0, '');
INSERT INTO corr_condition (corr_conditionid, correlationid, type) VALUES (99003, 99003, 0);
INSERT INTO corr_condition_tag (corr_conditionid, tag) VALUES (99003, 'clone tag');
INSERT INTO corr_operation (corr_operationid, correlationid, type) VALUES (99003, 99003, 0);

-- discovery rules
INSERT INTO hosts (hostid, host, status, description) VALUES (99006, 'Api active proxy for discovery', 5, '');
INSERT INTO drules (druleid, proxy_hostid, name, iprange, delay, nextcheck, status) VALUES (10,NULL,'API discovery rule for delete 1','192.168.0.1-254','1h',0,0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq, snmpv3_authprotocol, snmpv3_privprotocol, snmpv3_contextname) VALUES (10,10,4,'','','80','',0,'','',0,0,0,'');
INSERT INTO drules (druleid, proxy_hostid, name, iprange, delay, nextcheck, status) VALUES (11,99006,'API discovery rule for delete with proxy','192.168.0.1-254','1h',0,0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq, snmpv3_authprotocol, snmpv3_privprotocol, snmpv3_contextname) VALUES (11,11,9,'agent.ping','','10050','',0,'','',0,0,0,'');
INSERT INTO drules (druleid, proxy_hostid, name, iprange, delay, nextcheck, status) VALUES (12,NULL,'API discovery rule for delete 3','192.168.0.1-254','1h',0,0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq, snmpv3_authprotocol, snmpv3_privprotocol, snmpv3_contextname) VALUES (12,12,15,'','','23','',0,'','',0,0,0,'');
INSERT INTO drules (druleid, proxy_hostid, name, iprange, delay, nextcheck, status) VALUES (13,NULL,'API discovery rule for delete 4','192.168.0.1-254','1h',0,0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq, snmpv3_authprotocol, snmpv3_privprotocol, snmpv3_contextname) VALUES (13,13,3,'','','21','',0,'','',0,0,0,'');
INSERT INTO drules (druleid, proxy_hostid, name, iprange, delay, nextcheck, status) VALUES (14,NULL,'API discovery rule for delete 5','192.168.0.1-254','1h',0,0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq, snmpv3_authprotocol, snmpv3_privprotocol, snmpv3_contextname) VALUES (14,14,3,'','','21','',0,'','',0,0,0,'');
INSERT INTO drules (druleid, proxy_hostid, name, iprange, delay, nextcheck, status) VALUES (15,NULL,'API discovery rule used in action','192.168.0.1-254','1h',0,0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq, snmpv3_authprotocol, snmpv3_privprotocol, snmpv3_contextname) VALUES (15,15,3,'','','21','',0,'','',0,0,0,'');
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq, snmpv3_authprotocol, snmpv3_privprotocol, snmpv3_contextname) VALUES (16,15,9,'agent.ping','','10050','',0,'','',0,0,0,'');
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period) VALUES (95, 'API action for Discovery check', 1, 0, 0, '1h');
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (95, 95, 0, 0, 1, 1, 0);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (95, 0, 'Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}', 'Discovery rule: {DISCOVERY.RULE.NAME}', NULL);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (95, 95, 47);
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value, value2) VALUES (95,95,19,0,'16','');

INSERT INTO drules (druleid, proxy_hostid, name, iprange, delay, nextcheck, status) VALUES (16,NULL,'API discovery rule used in action 2','192.168.0.1-254','1h',0,0);
INSERT INTO dchecks (dcheckid, druleid, type, key_, snmp_community, ports, snmpv3_securityname, snmpv3_securitylevel, snmpv3_authpassphrase, snmpv3_privpassphrase, uniq, snmpv3_authprotocol, snmpv3_privprotocol, snmpv3_contextname) VALUES (17,16,0,'','','22','',0,'','',0,0,0,'');
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period) VALUES (96, 'API action for Discovery rule', 1, 0, 0, '1h');
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (96, 96, 0, 0, 1, 1, 0);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (96, 0, 'Discovery: {DISCOVERY.DEVICE.STATUS} {DISCOVERY.DEVICE.IPADDRESS}', 'Discovery rule: {DISCOVERY.RULE.NAME}', NULL);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (96, 96, 7);
INSERT INTO conditions (conditionid, actionid, conditiontype, operator, value, value2) VALUES (97,96,18,0,'16','');

-- dependent items: BEGIN
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (50040, 0, '77c2bc72084e4584ab27d8870ed0310d', 'dependent.items');
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (50041, 0, 'c83eaf69ae974070ad376b565d0278cf', 'dependent.items/hosts');
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (50042, 1, 'fe2d2ce81ba146e1806ce7669209e140', 'dependent.items');
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (50043, 1, 'ffca0d38323c4e22a9beb3da896800c4', 'dependent.items/templates');

-- dependent items: dependent.items.template.1
INSERT INTO hosts (hostid, host, name, status, description) VALUES (1001, 'dependent.items.template.1', 'dependent.items.template.1', 3, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (1001, 1001, 50043);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1001, 1001, 'master.item.1'                 ,  2, 'master.item.1'                 , 1, '90d', 0, NULL, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1002, 1001, 'dependent.item.1.1'            , 18, 'dependent.item.1.1'            , 1, '90d', 0, 1001, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1003, 1001, 'dependent.item.1.2'            , 18, 'dependent.item.1.2'            , 1, '90d', 0, 1001, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1004, 1001, 'dependent.item.1.1.1'          , 18, 'dependent.item.1.1.1'          , 1, '90d', 0, 1002, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1005, 1001, 'dependent.item.1.1.2'          , 18, 'dependent.item.1.1.2'          , 1, '90d', 0, 1002, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1006, 1001, 'dependent.item.1.2.1'          , 18, 'dependent.item.1.2.1'          , 1, '90d', 0, 1003, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1007, 1001, 'dependent.item.1.2.2'          , 18, 'dependent.item.1.2.2'          , 1, '90d', 0, 1003, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1008, 1001, 'dependent.item.1.1.1.1'        , 18, 'dependent.item.1.1.1.1'        , 1, '90d', 0, 1004, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1009, 1001, 'dependent.item.1.1.1.2'        , 18, 'dependent.item.1.1.1.2'        , 1, '90d', 0, 1004, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1010, 1001, 'dependent.item.1.1.2.1'        , 18, 'dependent.item.1.1.2.1'        , 1, '90d', 0, 1005, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1011, 1001, 'dependent.item.1.1.2.2'        , 18, 'dependent.item.1.1.2.2'        , 1, '90d', 0, 1005, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1012, 1001, 'dependent.item.1.2.1.1'        , 18, 'dependent.item.1.2.1.1'        , 1, '90d', 0, 1006, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1013, 1001, 'dependent.item.1.2.1.2'        , 18, 'dependent.item.1.2.1.2'        , 1, '90d', 0, 1006, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1014, 1001, 'dependent.item.1.2.2.1'        , 18, 'dependent.item.1.2.2.1'        , 1, '90d', 0, 1007, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1015, 1001, 'dependent.item.1.2.2.2'        , 18, 'dependent.item.1.2.2.2'        , 1, '90d', 0, 1007, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1016, 1001, 'trap.1'                        ,  2, 'trap.1'                        , 1, '90d', 0, NULL, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type,          status,                templateid, params, description, posts, headers, lifetime, flags) VALUES (1017, 1001, 'discovery.rule.1'              ,  2, 'discovery.rule.1'              , 4,        0,       NULL, '', '', '', '', '30d', 1);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1018, 1001, 'master.item.proto.1'           ,  2, 'master.item.proto.1'           , 1, '90d', 0, NULL, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1019, 1001, 'dependent.item.proto.1.1'      , 18, 'dependent.item.proto.1.1'      , 1, '90d', 0, 1018, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1020, 1001, 'dependent.item.proto.1.2'      , 18, 'dependent.item.proto.1.2'      , 1, '90d', 0, 1018, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1021, 1001, 'dependent.item.proto.1.1.1'    , 18, 'dependent.item.proto.1.1.1'    , 1, '90d', 0, 1019, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1022, 1001, 'dependent.item.proto.1.1.2'    , 18, 'dependent.item.proto.1.1.2'    , 1, '90d', 0, 1019, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1023, 1001, 'dependent.item.proto.1.2.1'    , 18, 'dependent.item.proto.1.2.1'    , 1, '90d', 0, 1020, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1024, 1001, 'dependent.item.proto.1.2.2'    , 18, 'dependent.item.proto.1.2.2'    , 1, '90d', 0, 1020, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1025, 1001, 'dependent.item.proto.1.1.1.1'  , 18, 'dependent.item.proto.1.1.1.1'  , 1, '90d', 0, 1021, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1026, 1001, 'dependent.item.proto.1.1.1.2'  , 18, 'dependent.item.proto.1.1.1.2'  , 1, '90d', 0, 1021, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1027, 1001, 'dependent.item.proto.1.1.2.1'  , 18, 'dependent.item.proto.1.1.2.1'  , 1, '90d', 0, 1022, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1028, 1001, 'dependent.item.proto.1.1.2.2'  , 18, 'dependent.item.proto.1.1.2.2'  , 1, '90d', 0, 1022, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1029, 1001, 'dependent.item.proto.1.2.1.1'  , 18, 'dependent.item.proto.1.2.1.1'  , 1, '90d', 0, 1023, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1030, 1001, 'dependent.item.proto.1.2.1.2'  , 18, 'dependent.item.proto.1.2.1.2'  , 1, '90d', 0, 1023, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1031, 1001, 'dependent.item.proto.1.2.2.1'  , 18, 'dependent.item.proto.1.2.2.1'  , 1, '90d', 0, 1024, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1032, 1001, 'dependent.item.proto.1.2.2.2'  , 18, 'dependent.item.proto.1.2.2.2'  , 1, '90d', 0, 1024, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1033, 1001, 'item.proto.1'                  ,  2, 'item.proto.1'                  , 1, '90d', 0, NULL, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type,          status, master_itemid, templateid, params, description, posts, headers, lifetime, flags) VALUES (1034, 1001, 'dependent.discovery.rule.1.1'  , 18, 'dependent.discovery.rule.1.1'  , 4,        0, 1001, NULL, '', '', '', '', '30d', 1);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (15001, 1017, 1018);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (15002, 1017, 1019);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (15003, 1017, 1020);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (15004, 1017, 1021);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (15005, 1017, 1022);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (15006, 1017, 1023);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (15007, 1017, 1024);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (15008, 1017, 1025);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (15009, 1017, 1026);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (15010, 1017, 1027);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (15011, 1017, 1028);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (15012, 1017, 1029);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (15013, 1017, 1030);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (15014, 1017, 1031);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (15015, 1017, 1032);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (15016, 1017, 1033);

-- dependent items: dependent.items.template.1.1
INSERT INTO hosts (hostid, host, name, status, description) VALUES (1002, 'dependent.items.template.1.1', 'dependent.items.template.1.1', 3, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (1002, 1002, 50043);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (1001, 1002, 1001);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1101, 1002, 'master.item.1'                 ,  2, 'master.item.1'                 , 1, '90d', 0, NULL, 1001, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1102, 1002, 'dependent.item.1.1'            , 18, 'dependent.item.1.1'            , 1, '90d', 0, 1101, 1002, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1103, 1002, 'dependent.item.1.2'            , 18, 'dependent.item.1.2'            , 1, '90d', 0, 1101, 1003, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1104, 1002, 'dependent.item.1.1.1'          , 18, 'dependent.item.1.1.1'          , 1, '90d', 0, 1102, 1004, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1105, 1002, 'dependent.item.1.1.2'          , 18, 'dependent.item.1.1.2'          , 1, '90d', 0, 1102, 1005, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1106, 1002, 'dependent.item.1.2.1'          , 18, 'dependent.item.1.2.1'          , 1, '90d', 0, 1103, 1006, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1107, 1002, 'dependent.item.1.2.2'          , 18, 'dependent.item.1.2.2'          , 1, '90d', 0, 1103, 1007, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1108, 1002, 'dependent.item.1.1.1.1'        , 18, 'dependent.item.1.1.1.1'        , 1, '90d', 0, 1104, 1008, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1109, 1002, 'dependent.item.1.1.1.2'        , 18, 'dependent.item.1.1.1.2'        , 1, '90d', 0, 1104, 1009, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1110, 1002, 'dependent.item.1.1.2.1'        , 18, 'dependent.item.1.1.2.1'        , 1, '90d', 0, 1105, 1010, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1111, 1002, 'dependent.item.1.1.2.2'        , 18, 'dependent.item.1.1.2.2'        , 1, '90d', 0, 1105, 1011, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1112, 1002, 'dependent.item.1.2.1.1'        , 18, 'dependent.item.1.2.1.1'        , 1, '90d', 0, 1106, 1012, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1113, 1002, 'dependent.item.1.2.1.2'        , 18, 'dependent.item.1.2.1.2'        , 1, '90d', 0, 1106, 1013, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1114, 1002, 'dependent.item.1.2.2.1'        , 18, 'dependent.item.1.2.2.1'        , 1, '90d', 0, 1107, 1014, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1115, 1002, 'dependent.item.1.2.2.2'        , 18, 'dependent.item.1.2.2.2'        , 1, '90d', 0, 1107, 1015, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1116, 1002, 'trap.1'                        ,  2, 'trap.1'                        , 1, '90d', 0, NULL, 1016, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type,          status,                templateid, params, description, posts, headers, lifetime, flags) VALUES (1117, 1002, 'discovery.rule.1'              ,  2, 'discovery.rule.1'              , 4,        0,       1017, '', '', '', '', '30d', 1);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1118, 1002, 'master.item.proto.1'           ,  2, 'master.item.proto.1'           , 1, '90d', 0, NULL, 1018, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1119, 1002, 'dependent.item.proto.1.1'      , 18, 'dependent.item.proto.1.1'      , 1, '90d', 0, 1118, 1019, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1120, 1002, 'dependent.item.proto.1.2'      , 18, 'dependent.item.proto.1.2'      , 1, '90d', 0, 1118, 1020, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1121, 1002, 'dependent.item.proto.1.1.1'    , 18, 'dependent.item.proto.1.1.1'    , 1, '90d', 0, 1119, 1021, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1122, 1002, 'dependent.item.proto.1.1.2'    , 18, 'dependent.item.proto.1.1.2'    , 1, '90d', 0, 1119, 1022, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1123, 1002, 'dependent.item.proto.1.2.1'    , 18, 'dependent.item.proto.1.2.1'    , 1, '90d', 0, 1120, 1023, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1124, 1002, 'dependent.item.proto.1.2.2'    , 18, 'dependent.item.proto.1.2.2'    , 1, '90d', 0, 1120, 1024, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1125, 1002, 'dependent.item.proto.1.1.1.1'  , 18, 'dependent.item.proto.1.1.1.1'  , 1, '90d', 0, 1121, 1025, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1126, 1002, 'dependent.item.proto.1.1.1.2'  , 18, 'dependent.item.proto.1.1.1.2'  , 1, '90d', 0, 1121, 1026, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1127, 1002, 'dependent.item.proto.1.1.2.1'  , 18, 'dependent.item.proto.1.1.2.1'  , 1, '90d', 0, 1122, 1027, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1128, 1002, 'dependent.item.proto.1.1.2.2'  , 18, 'dependent.item.proto.1.1.2.2'  , 1, '90d', 0, 1122, 1028, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1129, 1002, 'dependent.item.proto.1.2.1.1'  , 18, 'dependent.item.proto.1.2.1.1'  , 1, '90d', 0, 1123, 1029, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1130, 1002, 'dependent.item.proto.1.2.1.2'  , 18, 'dependent.item.proto.1.2.1.2'  , 1, '90d', 0, 1123, 1030, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1131, 1002, 'dependent.item.proto.1.2.2.1'  , 18, 'dependent.item.proto.1.2.2.1'  , 1, '90d', 0, 1124, 1031, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1132, 1002, 'dependent.item.proto.1.2.2.2'  , 18, 'dependent.item.proto.1.2.2.2'  , 1, '90d', 0, 1124, 1032, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1133, 1002, 'item.proto.1'                  ,  2, 'item.proto.1'                  , 1, '90d', 0, NULL, 1033, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type,          status, master_itemid, templateid, params, description, posts, headers, lifetime, flags) VALUES (1134, 1002, 'dependent.discovery.rule.1.1'  , 18, 'dependent.discovery.rule.1.1'  , 4,        0, 1101, NULL, '', '', '', '', '30d', 1);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (51010, 1117, 1118);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (51020, 1117, 1119);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (51030, 1117, 1120);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (51040, 1117, 1121);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (51050, 1117, 1122);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (51060, 1117, 1123);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (51070, 1117, 1124);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (51080, 1117, 1125);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (51090, 1117, 1126);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (51100, 1117, 1127);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (51110, 1117, 1128);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (51120, 1117, 1129);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (51130, 1117, 1130);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (51140, 1117, 1131);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (51150, 1117, 1132);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (51160, 1117, 1133);

-- dependent items: dependent.items.template.1.2
INSERT INTO hosts (hostid, host, name, status, description) VALUES (1003, 'dependent.items.template.1.2', 'dependent.items.template.1.2', 3, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (1003, 1003, 50043);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (1002, 1003, 1001);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1201, 1003, 'master.item.1'                 ,  2, 'master.item.1'                 , 1, '90d', 0, NULL, 1001, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1202, 1003, 'dependent.item.1.1'            , 18, 'dependent.item.1.1'            , 1, '90d', 0, 1201, 1002, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1203, 1003, 'dependent.item.1.2'            , 18, 'dependent.item.1.2'            , 1, '90d', 0, 1201, 1003, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1204, 1003, 'dependent.item.1.1.1'          , 18, 'dependent.item.1.1.1'          , 1, '90d', 0, 1202, 1004, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1205, 1003, 'dependent.item.1.1.2'          , 18, 'dependent.item.1.1.2'          , 1, '90d', 0, 1202, 1005, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1206, 1003, 'dependent.item.1.2.1'          , 18, 'dependent.item.1.2.1'          , 1, '90d', 0, 1203, 1006, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1207, 1003, 'dependent.item.1.2.2'          , 18, 'dependent.item.1.2.2'          , 1, '90d', 0, 1203, 1007, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1208, 1003, 'dependent.item.1.1.1.1'        , 18, 'dependent.item.1.1.1.1'        , 1, '90d', 0, 1204, 1008, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1209, 1003, 'dependent.item.1.1.1.2'        , 18, 'dependent.item.1.1.1.2'        , 1, '90d', 0, 1204, 1009, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1210, 1003, 'dependent.item.1.1.2.1'        , 18, 'dependent.item.1.1.2.1'        , 1, '90d', 0, 1205, 1010, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1211, 1003, 'dependent.item.1.1.2.2'        , 18, 'dependent.item.1.1.2.2'        , 1, '90d', 0, 1205, 1011, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1212, 1003, 'dependent.item.1.2.1.1'        , 18, 'dependent.item.1.2.1.1'        , 1, '90d', 0, 1206, 1012, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1213, 1003, 'dependent.item.1.2.1.2'        , 18, 'dependent.item.1.2.1.2'        , 1, '90d', 0, 1206, 1013, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1214, 1003, 'dependent.item.1.2.2.1'        , 18, 'dependent.item.1.2.2.1'        , 1, '90d', 0, 1207, 1014, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1215, 1003, 'dependent.item.1.2.2.2'        , 18, 'dependent.item.1.2.2.2'        , 1, '90d', 0, 1207, 1015, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1216, 1003, 'trap.1'                        ,  2, 'trap.1'                        , 1, '90d', 0, NULL, 1016, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type,          status,                templateid, params, description, posts, headers, lifetime, flags) VALUES (1217, 1003, 'discovery.rule.1'              ,  2, 'discovery.rule.1'              , 4,        0,       1017, '', '', '', '', '30d', 1);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1218, 1003, 'master.item.proto.1'           ,  2, 'master.item.proto.1'           , 1, '90d', 0, NULL, 1018, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1219, 1003, 'dependent.item.proto.1.1'      , 18, 'dependent.item.proto.1.1'      , 1, '90d', 0, 1218, 1019, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1220, 1003, 'dependent.item.proto.1.2'      , 18, 'dependent.item.proto.1.2'      , 1, '90d', 0, 1218, 1020, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1221, 1003, 'dependent.item.proto.1.1.1'    , 18, 'dependent.item.proto.1.1.1'    , 1, '90d', 0, 1219, 1021, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1222, 1003, 'dependent.item.proto.1.1.2'    , 18, 'dependent.item.proto.1.1.2'    , 1, '90d', 0, 1219, 1022, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1223, 1003, 'dependent.item.proto.1.2.1'    , 18, 'dependent.item.proto.1.2.1'    , 1, '90d', 0, 1220, 1023, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1224, 1003, 'dependent.item.proto.1.2.2'    , 18, 'dependent.item.proto.1.2.2'    , 1, '90d', 0, 1220, 1024, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1225, 1003, 'dependent.item.proto.1.1.1.1'  , 18, 'dependent.item.proto.1.1.1.1'  , 1, '90d', 0, 1221, 1025, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1226, 1003, 'dependent.item.proto.1.1.1.2'  , 18, 'dependent.item.proto.1.1.1.2'  , 1, '90d', 0, 1221, 1026, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1227, 1003, 'dependent.item.proto.1.1.2.1'  , 18, 'dependent.item.proto.1.1.2.1'  , 1, '90d', 0, 1222, 1027, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1228, 1003, 'dependent.item.proto.1.1.2.2'  , 18, 'dependent.item.proto.1.1.2.2'  , 1, '90d', 0, 1222, 1028, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1229, 1003, 'dependent.item.proto.1.2.1.1'  , 18, 'dependent.item.proto.1.2.1.1'  , 1, '90d', 0, 1223, 1029, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1230, 1003, 'dependent.item.proto.1.2.1.2'  , 18, 'dependent.item.proto.1.2.1.2'  , 1, '90d', 0, 1223, 1030, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1231, 1003, 'dependent.item.proto.1.2.2.1'  , 18, 'dependent.item.proto.1.2.2.1'  , 1, '90d', 0, 1224, 1031, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1232, 1003, 'dependent.item.proto.1.2.2.2'  , 18, 'dependent.item.proto.1.2.2.2'  , 1, '90d', 0, 1224, 1032, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1233, 1003, 'item.proto.1'                  ,  2, 'item.proto.1'                  , 1, '90d', 0, NULL, 1033, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type,          status, master_itemid, templateid, params, description, posts, headers, lifetime, flags) VALUES (1234, 1003, 'dependent.discovery.rule.1.1'  , 18, 'dependent.discovery.rule.1.1'  , 4,        0, 1201, NULL, '', '', '', '', '30d', 1);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (52010, 1217, 1218);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (52020, 1217, 1219);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (52030, 1217, 1220);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (52040, 1217, 1221);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (52050, 1217, 1222);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (52060, 1217, 1223);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (52070, 1217, 1224);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (52080, 1217, 1225);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (52090, 1217, 1226);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (52100, 1217, 1227);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (52110, 1217, 1228);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (52120, 1217, 1229);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (52130, 1217, 1230);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (52140, 1217, 1231);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (52150, 1217, 1232);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (52160, 1217, 1233);

-- dependent items: dependent.items.host.1
INSERT INTO hosts (hostid, host, name, status, description) VALUES (1004, 'dependent.items.host.1', 'dependent.items.host.1', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (1004, 1004, 50041);
INSERT INTO interface (interfaceid, hostid, type, ip, useip, port, main) VALUES (1001, 1004, 1, '127.0.0.1', 1, '10050', 1);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (1003, 1004, 1002);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1301, 1004, 'master.item.1'                 ,  2, 'master.item.1'                 , 1, '90d', 0, NULL, 1101, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1302, 1004, 'dependent.item.1.1'            , 18, 'dependent.item.1.1'            , 1, '90d', 0, 1301, 1102, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1303, 1004, 'dependent.item.1.2'            , 18, 'dependent.item.1.2'            , 1, '90d', 0, 1301, 1103, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1304, 1004, 'dependent.item.1.1.1'          , 18, 'dependent.item.1.1.1'          , 1, '90d', 0, 1302, 1104, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1305, 1004, 'dependent.item.1.1.2'          , 18, 'dependent.item.1.1.2'          , 1, '90d', 0, 1302, 1105, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1306, 1004, 'dependent.item.1.2.1'          , 18, 'dependent.item.1.2.1'          , 1, '90d', 0, 1303, 1106, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1307, 1004, 'dependent.item.1.2.2'          , 18, 'dependent.item.1.2.2'          , 1, '90d', 0, 1303, 1107, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1308, 1004, 'dependent.item.1.1.1.1'        , 18, 'dependent.item.1.1.1.1'        , 1, '90d', 0, 1304, 1108, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1309, 1004, 'dependent.item.1.1.1.2'        , 18, 'dependent.item.1.1.1.2'        , 1, '90d', 0, 1304, 1109, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1310, 1004, 'dependent.item.1.1.2.1'        , 18, 'dependent.item.1.1.2.1'        , 1, '90d', 0, 1305, 1110, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1311, 1004, 'dependent.item.1.1.2.2'        , 18, 'dependent.item.1.1.2.2'        , 1, '90d', 0, 1305, 1111, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1312, 1004, 'dependent.item.1.2.1.1'        , 18, 'dependent.item.1.2.1.1'        , 1, '90d', 0, 1306, 1112, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1313, 1004, 'dependent.item.1.2.1.2'        , 18, 'dependent.item.1.2.1.2'        , 1, '90d', 0, 1306, 1113, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1314, 1004, 'dependent.item.1.2.2.1'        , 18, 'dependent.item.1.2.2.1'        , 1, '90d', 0, 1307, 1114, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1315, 1004, 'dependent.item.1.2.2.2'        , 18, 'dependent.item.1.2.2.2'        , 1, '90d', 0, 1307, 1115, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1316, 1004, 'trap.1'                        ,  2, 'trap.1'                        , 1, '90d', 0, NULL, 1116, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type,          status,                templateid, params, description, posts, headers, lifetime, flags) VALUES (1317, 1004, 'discovery.rule.1'              ,  2, 'discovery.rule.1'              , 4,        0,       1117, '', '', '', '', '30d', 1);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1318, 1004, 'master.item.proto.1'           ,  2, 'master.item.proto.1'           , 1, '90d', 0, NULL, 1118, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1319, 1004, 'dependent.item.proto.1.1'      , 18, 'dependent.item.proto.1.1'      , 1, '90d', 0, 1318, 1119, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1320, 1004, 'dependent.item.proto.1.2'      , 18, 'dependent.item.proto.1.2'      , 1, '90d', 0, 1318, 1120, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1321, 1004, 'dependent.item.proto.1.1.1'    , 18, 'dependent.item.proto.1.1.1'    , 1, '90d', 0, 1319, 1121, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1322, 1004, 'dependent.item.proto.1.1.2'    , 18, 'dependent.item.proto.1.1.2'    , 1, '90d', 0, 1319, 1122, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1323, 1004, 'dependent.item.proto.1.2.1'    , 18, 'dependent.item.proto.1.2.1'    , 1, '90d', 0, 1320, 1123, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1324, 1004, 'dependent.item.proto.1.2.2'    , 18, 'dependent.item.proto.1.2.2'    , 1, '90d', 0, 1320, 1124, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1325, 1004, 'dependent.item.proto.1.1.1.1'  , 18, 'dependent.item.proto.1.1.1.1'  , 1, '90d', 0, 1321, 1125, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1326, 1004, 'dependent.item.proto.1.1.1.2'  , 18, 'dependent.item.proto.1.1.1.2'  , 1, '90d', 0, 1321, 1126, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1327, 1004, 'dependent.item.proto.1.1.2.1'  , 18, 'dependent.item.proto.1.1.2.1'  , 1, '90d', 0, 1322, 1127, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1328, 1004, 'dependent.item.proto.1.1.2.2'  , 18, 'dependent.item.proto.1.1.2.2'  , 1, '90d', 0, 1322, 1128, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1329, 1004, 'dependent.item.proto.1.2.1.1'  , 18, 'dependent.item.proto.1.2.1.1'  , 1, '90d', 0, 1323, 1129, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1330, 1004, 'dependent.item.proto.1.2.1.2'  , 18, 'dependent.item.proto.1.2.1.2'  , 1, '90d', 0, 1323, 1130, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1331, 1004, 'dependent.item.proto.1.2.2.1'  , 18, 'dependent.item.proto.1.2.2.1'  , 1, '90d', 0, 1324, 1131, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1332, 1004, 'dependent.item.proto.1.2.2.2'  , 18, 'dependent.item.proto.1.2.2.2'  , 1, '90d', 0, 1324, 1132, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1333, 1004, 'item.proto.1'                  ,  2, 'item.proto.1'                  , 1, '90d', 0, NULL, 1133, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type,          status, master_itemid, templateid, params, description, posts, headers, lifetime, flags) VALUES (1334, 1004, 'dependent.discovery.rule.1.1'  , 18, 'dependent.discovery.rule.1.1'  , 4,        0, 1301, NULL, '', '', '', '', '30d', 1);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (53010, 1317, 1318);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (53020, 1317, 1319);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (53030, 1317, 1320);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (53040, 1317, 1321);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (53050, 1317, 1322);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (53060, 1317, 1323);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (53070, 1317, 1324);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (53080, 1317, 1325);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (53090, 1317, 1326);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (53100, 1317, 1327);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (53110, 1317, 1328);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (53120, 1317, 1329);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (53130, 1317, 1330);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (53140, 1317, 1331);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (53150, 1317, 1332);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (53160, 1317, 1333);

-- dependent items: dependent.items.template.2
INSERT INTO hosts (hostid, host, name, status, description) VALUES (1005, 'dependent.items.template.2', 'dependent.items.template.2', 3, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (1005, 1005, 50043);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1401, 1005, 'master.item.1'                 ,  2, 'master.item.1'                 , 1, '90d', 0, NULL, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1402, 1005, 'dependent.item.1.1'            , 18, 'dependent.item.1.1'            , 1, '90d', 0, 1401, NULL, '', '', '', '');

-- dependent items: dependent.items.host.2
INSERT INTO hosts (hostid, host, name, status, description) VALUES (1006, 'dependent.items.host.2', 'dependent.items.host.2', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (1006, 1006, 50041);
INSERT INTO interface (interfaceid, hostid, type, ip, useip, port, main) VALUES (1002, 1006, 1, '127.0.0.1', 1, '10050', 1);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1501, 1006, 'dependent.item.1.1'            ,  2, 'dependent.item.1.1'            , 1, '90d', 0, NULL, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1502, 1006, 'dependent.item.1.1.1'          , 18, 'dependent.item.1.1.1'          , 1, '90d', 0, 1501, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1503, 1006, 'dependent.item.1.1.1.1'        , 18, 'dependent.item.1.1.1.1'        , 1, '90d', 0, 1502, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1504, 1006, 'dependent.item.1.1.1.1.1'      , 18, 'dependent.item.1.1.1.1.1'      , 1, '90d', 0, 1503, NULL, '', '', '', '');

-- dependent items: dependent.items.host.3
INSERT INTO hosts (hostid, host, name, status, description) VALUES (1007, 'dependent.items.host.3', 'dependent.items.host.3', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (1007, 1007, 50041);
INSERT INTO interface (interfaceid, hostid, type, ip, useip, port, main) VALUES (1003, 1007, 1, '127.0.0.1', 1, '10050', 1);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1601, 1007, 'dependent.item.1.1'            ,  2, 'dependent.item.1.1'            , 1, '90d', 0, NULL, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type,          status,                templateid, params, description, posts, headers, lifetime, flags) VALUES (1602, 1007, 'discovery.rule.1'              ,  2, 'discovery.rule.1'              , 4,        0,       NULL, '', '', '', '', '30d', 1);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1603, 1007, 'dependent.item.proto.1.1.1'    , 18, 'dependent.item.proto.1.1.1'    , 1, '90d', 0, 1601, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1604, 1007, 'dependent.item.proto.1.1.1.1'  , 18, 'dependent.item.proto.1.1.1.1'  , 1, '90d', 0, 1603, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (1605, 1007, 'dependent.item.proto.1.1.1.1.1', 18, 'dependent.item.proto.1.1.1.1.1', 1, '90d', 0, 1604, NULL, '', '', '', '',        2);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (56010, 1602, 1603);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (56020, 1602, 1604);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (56030, 1602, 1605);

-- dependent items: dependent.items.template.4
INSERT INTO hosts (hostid, host, name, status, description) VALUES (1008, 'dependent.items.template.4', 'dependent.items.template.4', 3, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (1008, 1008, 50043);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1701, 1008, 'item.1'                        ,  2, 'item.1'                        , 1, '90d', 0, NULL, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1702, 1008, 'item.2'                        ,  2, 'item.2'                        , 1, '90d', 0, NULL, NULL, '', '', '', '');

-- dependent items: dependent.items.host.4
INSERT INTO hosts (hostid, host, name, status, description) VALUES (1009, 'dependent.items.host.4', 'dependent.items.host.4', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (1009, 1009, 50041);
INSERT INTO interface (interfaceid, hostid, type, ip, useip, port, main) VALUES (1004, 1009, 1, '127.0.0.1', 1, '10050', 1);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (1004, 1009, 1008);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1801, 1009, 'item.1'                        ,  2, 'item.1'                        , 1, '90d', 0, NULL, 1701, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1802, 1009, 'item.2'                        ,  2, 'item.2'                        , 1, '90d', 0, NULL, 1702, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1803, 1009, 'item.3'                        , 18, 'item.3'                        , 1, '90d', 0, 1802, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1804, 1009, 'item.4'                        , 18, 'item.4'                        , 1, '90d', 0, 1803, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1805, 1009, 'item.5'                        , 18, 'item.5'                        , 1, '90d', 0, 1804, NULL, '', '', '', '');

-- dependent items: dependent.items.template.5
INSERT INTO hosts (hostid, host, name, status, description) VALUES (1010, 'dependent.items.template.5', 'dependent.items.template.5', 3, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (1010, 1010, 50043);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1901, 1010, 'item.1'                        ,  2, 'item.1'                        , 1, '90d', 0, NULL, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (1902, 1010, 'item.2'                        ,  2, 'item.2'                        , 1, '90d', 0, NULL, NULL, '', '', '', '');

-- dependent items: dependent.items.host.5
INSERT INTO hosts (hostid, host, name, status, description) VALUES (1011, 'dependent.items.host.5', 'dependent.items.host.5', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (1011, 1011, 50041);
INSERT INTO interface (interfaceid, hostid, type, ip, useip, port, main) VALUES (1005, 1011, 1, '127.0.0.1', 1, '10050', 1);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (1005, 1011, 1010);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (2001, 1011, 'item.1'                        ,  2, 'item.1'                        , 1, '90d', 0, NULL, 1901, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (2002, 1011, 'item.2'                        ,  2, 'item.2'                        , 1, '90d', 0, NULL, 1902, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type,          status,                templateid, params, description, posts, headers, lifetime, flags) VALUES (2003, 1011, 'discovery.rule.1'              ,  2, 'discovery.rule.1'              , 4,        0,       NULL, '', '', '', '', '30d', 1);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (2004, 1011, 'item.proto.3'                  , 18, 'item.proto.3'                  , 1, '90d', 0, 2002, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (2005, 1011, 'item.proto.4'                  , 18, 'item.proto.4'                  , 1, '90d', 0, 2004, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (2006, 1011, 'item.proto.5'                  , 18, 'item.proto.5'                  , 1, '90d', 0, 2005, NULL, '', '', '', '',        2);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (60010, 2003, 2004);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (60020, 2003, 2005);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (60030, 2003, 2006);

-- dependent items: dependent.items.template.6
INSERT INTO hosts (hostid, host, name, status, description) VALUES (1012, 'dependent.items.template.6', 'dependent.items.template.6', 3, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (1012, 1012, 50043);
INSERT INTO items (itemid, hostid, name, type, key_, value_type,          status,                templateid, params, description, posts, headers, lifetime, flags) VALUES (2101, 1012, 'discovery.rule.1'              ,  2, 'discovery.rule.1'              , 4,        0,       NULL, '', '', '', '', '30d', 1);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (2102, 1012, 'item.proto.1'                  ,  2, 'item.proto.1'                  , 1, '90d', 0, NULL, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (2103, 1012, 'item.proto.2'                  ,  2, 'item.proto.2'                  , 1, '90d', 0, NULL, NULL, '', '', '', '',        2);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (61010, 2101, 2102);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (61020, 2101, 2103);

-- dependent items: dependent.items.host.6
INSERT INTO hosts (hostid, host, name, status, description) VALUES (1013, 'dependent.items.host.6', 'dependent.items.host.6', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (1013, 1013, 50041);
INSERT INTO interface (interfaceid, hostid, type, ip, useip, port, main) VALUES (1006, 1013, 1, '127.0.0.1', 1, '10050', 1);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (1006, 1013, 1012);
INSERT INTO items (itemid, hostid, name, type, key_, value_type,          status,                templateid, params, description, posts, headers, lifetime, flags) VALUES (2201, 1013, 'discovery.rule.1'              ,  2, 'discovery.rule.1'              , 4,        0,       2101, '', '', '', '', '30d', 1);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (2202, 1013, 'item.proto.1'                  ,  2, 'item.proto.1'                  , 1, '90d', 0, NULL, 2102, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (2203, 1013, 'item.proto.2'                  ,  2, 'item.proto.2'                  , 1, '90d', 0, NULL, 2103, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (2204, 1013, 'item.proto.3'                  , 18, 'item.proto.3'                  , 1, '90d', 0, 2203, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (2205, 1013, 'item.proto.4'                  , 18, 'item.proto.4'                  , 1, '90d', 0, 2204, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (2206, 1013, 'item.proto.5'                  , 18, 'item.proto.5'                  , 1, '90d', 0, 2205, NULL, '', '', '', '',        2);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (62010, 2201, 2202);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (62020, 2201, 2203);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (62030, 2201, 2204);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (62040, 2201, 2205);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (62050, 2201, 2206);

-- dependent items: dependent.items.host.7
INSERT INTO hosts (hostid, host, name, status, description) VALUES (1014, 'dependent.items.host.7', 'dependent.items.host.7', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (1014, 1014, 50041);
INSERT INTO interface (interfaceid, hostid, type, ip, useip, port, main) VALUES (1007, 1014, 1, '127.0.0.1', 1, '10050', 1);
INSERT INTO items (itemid, hostid, name, type, key_, value_type,          status,                templateid, params, description, posts, headers, lifetime, flags) VALUES (2301, 1014, 'net.if.disvovery'              ,  2, 'net.if.discovery'              , 4,        0,       NULL, '', '', '', '', '30d', 1);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (2302, 1014, 'net.if[{$IFNAME}]'             ,  2, 'net.if[{#IFNAME}]'             , 1, '90d', 0, NULL, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (2303, 1014, 'net.if.in[{$IFNAME}]'          , 18, 'net.ifi.in[{#IFNAME}]'         , 1, '90d', 0, 2302, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (2304, 1014, 'net.if[eth0]'                  ,  2, 'net.if[eth0]'                  , 1, '90d', 0, NULL, NULL, '', '', '', '',        4);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (2305, 1014, 'net.if.in[eth0]'               , 18, 'net.ifi.in[eth0]'              , 1, '90d', 0, 2304, NULL, '', '', '', '',        4);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (63010, 2301, 2302);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (63020, 2301, 2303);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (63030, 2302, 2304);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (63040, 2303, 2305);

-- dependent items: dependent.items.host.8
INSERT INTO hosts (hostid, host, name, status, description) VALUES (1015, 'dependent.items.host.8', 'dependent.items.host.8', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (1015, 1015, 50041);
INSERT INTO interface (interfaceid, hostid, type, ip, useip, port, main) VALUES (1008, 1015, 1, '127.0.0.1', 1, '10050', 1);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (2401, 1015, 'master.item.1'                 ,  2, 'master.item.1'                 , 1, '90d', 0, NULL, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (2402, 1015, 'dependent.item.1.1'            , 18, 'dependent.item.1.1'            , 1, '90d', 0, 2401, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type,          status,                templateid, params, description, posts, headers, lifetime, flags) VALUES (2403, 1015, 'discovery.rule.1'              ,  2, 'discovery.rule.1'              , 4,        0,       NULL, '', '', '', '', '30d', 1);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (2404, 1015, 'master.item.proto.1'           ,  2, 'master.item.proto.1'           , 1, '90d', 0, NULL, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (2405, 1015, 'dependent.item.proto.1.1'      , 18, 'dependent.item.proto.1.1'      , 1, '90d', 0, 2404, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type,          status,                templateid, params, description, posts, headers, lifetime, flags) VALUES (2406, 1015, 'discovery.rule.2'              ,  2, 'discovery.rule.2'              , 4,        0,       NULL, '', '', '', '', '30d', 1);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (2407, 1015, 'master.item.proto.2'           ,  2, 'master.item.proto.2'           , 1, '90d', 0, NULL, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (2408, 1015, 'dependent.item.proto.2.1'      , 18, 'dependent.item.proto.2.1'      , 1, '90d', 0, 2407, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type,          status, master_itemid, templateid, params, description, posts, headers, lifetime, flags) VALUES (2409, 1015, 'dependent.discovery.rule.1.1'  , 18, 'dependent.discovery.rule.1.1'  , 4,        0, 2401, NULL, '', '', '', '', '30d', 1);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (64010, 2403, 2404);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (64020, 2403, 2405);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (64030, 2406, 2407);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (64040, 2406, 2408);

-- dependent items: dependent.items.host.9
INSERT INTO hosts (hostid, host, name, status, description) VALUES (1016, 'dependent.items.host.9', 'dependent.items.host.9', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (1016, 1016, 50041);
INSERT INTO interface (interfaceid, hostid, type, ip, useip, port, main) VALUES (1009, 1016, 1, '127.0.0.1', 1, '10050', 1);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (2501, 1016, 'master.item.1'                 ,  2, 'master.item.1'                 , 1, '90d', 0, NULL, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (2502, 1016, 'dependent.item.1.1'            , 18, 'dependent.item.1.1'            , 1, '90d', 0, 2501, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type,          status,                templateid, params, description, posts, headers, lifetime, flags) VALUES (2503, 1016, 'discovery.rule.1'              ,  2, 'discovery.rule.1'              , 4,        0,       NULL, '', '', '', '', '30d', 1);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (2504, 1016, 'master.item.proto.1'           ,  2, 'master.item.proto.1'           , 1, '90d', 0, NULL, NULL, '', '', '', '',        2);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers,           flags) VALUES (2505, 1016, 'dependent.item.proto.1.1'      , 18, 'dependent.item.proto.1.1'      , 1, '90d', 0, 2504, NULL, '', '', '', '',        2);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (65010, 2503, 2504);
INSERT INTO item_discovery (itemdiscoveryid, parent_itemid, itemid) VALUES (65020, 2503, 2505);
-- dependent items: END

-- testHostGroup_Delete maintenance constraint
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (62002, 0, 'f40b2a0aa36d404d8971cc6d5232497d', 'maintenance_has_only_group');
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (62003, 0, '589d36644b7742dc9fef13ac0625f38c', 'maintenance_has_group_and_host');
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (62004, 0, 'bf806430c23c422b8bdb5dc7f4af2ca6', 'maintenance_group_1');
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (62005, 0, '4136fdc8ad2a46af913f5fdb82a72d1f', 'maintenance_group_2');
INSERT INTO hosts (hostid, host, name, status, description) VALUES (61003, 'maintenance_has_group_and_host', 'maintenance_has_group_and_host', 0, '');
INSERT INTO maintenances (maintenanceid, name, description, active_since, active_till) VALUES (60002, 'maintenance_has_only_group', '', 1539723600, 1539810000);
INSERT INTO maintenances (maintenanceid, name, description, active_since, active_till) VALUES (60003, 'maintenance_has_group_and_host', '', 1539723600, 1539810000);
INSERT INTO maintenances (maintenanceid, name, description, active_since, active_till) VALUES (60005, 'maintenance_two_groups', '', 1539723600, 1539810000);
INSERT INTO maintenances_hosts (maintenance_hostid, maintenanceid, hostid) VALUES (2, 60003, 61003);
INSERT INTO maintenances_groups (maintenance_groupid, maintenanceid, groupid) VALUES (1, 60002, 62002);
INSERT INTO maintenances_groups (maintenance_groupid, maintenanceid, groupid) VALUES (2, 60003, 62003);
INSERT INTO maintenances_groups (maintenance_groupid, maintenanceid, groupid) VALUES (3, 60005, 62004);
INSERT INTO maintenances_groups (maintenance_groupid, maintenanceid, groupid) VALUES (4, 60005, 62005);
INSERT INTO timeperiods (timeperiodid) VALUES (2);
INSERT INTO timeperiods (timeperiodid) VALUES (3);
INSERT INTO timeperiods (timeperiodid) VALUES (4);
INSERT INTO timeperiods (timeperiodid) VALUES (5);
INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (2, 60002, 2);
INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (3, 60003, 3);
INSERT INTO maintenances_windows (maintenance_timeperiodid, maintenanceid, timeperiodid) VALUES (5, 60005, 5);

-- testItemDelete
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (50018, 0, '23178c337bcd476b8a052daabcaaf861', 'with_lld_discovery');
INSERT INTO hosts (hostid, host, name, status, description) VALUES (120004, 'with_lld_discovery', 'with_lld_discovery', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (120004, 120004, 50018);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) VALUES (2004, 120004, 1, 1, 1, '127.0.0.1', '', '10050');

INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers, flags) VALUES (400700, 2, 120004, 'discovery_rule', '', 'discovery', '0', NULL, '', '', '', '', '', '', 1);
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers, value_type, flags) VALUES (400710, 2, 120004, 'Item {#NAME}', '', 'item[{#NAME}]', '0', NULL, '', '', '', '', '', '', 3, 2);
INSERT INTO triggers (triggerid, expression, description, priority, flags, comments) VALUES (30001,'{99000}>0','Trigger {#NAME}', 2, 2, '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99000, 400710, 30001, 'last', '$');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid, key_) VALUES (14045, 400710, 400700, '');

INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers, value_type, flags) VALUES (400720, 2, 120004,' Item eth0', '', 'item[eth0]', '0', NULL, '', '', '', '', '', '', 3, 4);
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid, key_) VALUES (14046, 400720, 400710, 'item[{#NAME}]');
INSERT INTO triggers (triggerid, expression, description, priority, flags, comments, value) VALUES (30002,'{99001}>0','Trigger eth0', 2, 4, '', 1);
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99001, 400720, 30002, 'last', '$');
INSERT INTO trigger_discovery (triggerid, parent_triggerid) VALUES (30002, 30001);

INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers, value_type, flags, master_itemid) VALUES (400730, 18, 120004, 'Item_child {#NAME}', '', 'item_child[{#NAME}]', '0', NULL, '', '', '', '', '', '', 3, 2, 400710);
INSERT INTO triggers (triggerid, expression, description, priority, flags, comments) VALUES (30003,'{99002}>0','Trigger {#NAME}', 2, 2, '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99002, 400730, 30003, 'last', '$');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid, key_) VALUES (14047, 400730, 400700, '');

INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers, value_type, flags, master_itemid) VALUES (400740, 18, 120004,' Item_child eth0', '', 'item_child[eth0]', '0', NULL, '', '', '', '', '', '', 3, 4, 400720);
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid, key_) VALUES (14048, 400740, 400730, 'item[{#NAME}]');
INSERT INTO triggers (triggerid, expression, description, priority, flags, comments, value) VALUES (30004,'{99003}>0','Trigger eth0', 2, 4, '', 1);
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99003, 400740, 30004, 'last', '$');
INSERT INTO trigger_discovery (triggerid, parent_triggerid) VALUES (30004, 30003);

-- LLD rules
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,trends,status,params,description,flags,posts,headers) VALUES (110006,50009,50022,0,4,'API LLD rule 1','apilldrule1','30s','90d',0,0,'','',1,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,trends,status,params,description,flags,posts,headers) VALUES (110007,50009,50022,0,4,'API LLD rule 2','apilldrule2','30s','90d',0,0,'','',1,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,trends,status,params,description,flags,posts,headers) VALUES (110008,50009,50022,0,4,'API LLD rule 3','apilldrule3','30s','90d',0,0,'','',1,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,trends,status,params,description,flags,posts,headers) VALUES (110009,50009,50022,0,4,'API LLD rule 4','apilldrule4','30s','90d',0,0,'','',1,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,trends,status,params,description,flags,posts,headers) VALUES (110010,50010,null,0,4,'API Template LLD rule','apitemplatelldrule','30s','90d',0,0,'','',1,'','');
INSERT INTO items (itemid,hostid,interfaceid,templateid,type,value_type,name,key_,delay,history,trends,status,params,description,flags,posts,headers) VALUES (110011,50009,50022,110010,0,4,'API Template LLD rule','apitemplatelldrule','30s','90d',0,0,'','',1,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,trends,status,params,description,flags,posts,headers) VALUES (110012,50009,50022,0,4,'API LLD rule get LLD macro paths','apilldrulegetlldmacropaths','30s','90d',0,0,'','',1,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,trends,status,params,description,flags,posts,headers) VALUES (110013,50009,50022,0,4,'API LLD rule get preprocessing','apilldrulegetpreprocessing','30s','90d',0,0,'','',1,'','');

-- LLD macro paths
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (991,110006,'{#A}','$.list[:1].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (992,110006,'{#B}','$.list[:2].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (993,110006,'{#C}','$.list[:3].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (994,110006,'{#D}','$.list[:4].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (995,110006,'{#E}','$.list[:5].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (996,110007,'{#A}','$.list[:1].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (997,110007,'{#B}','$.list[:2].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (998,110007,'{#C}','$.list[:3].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (999,110008,'{#A}','$.list[:1].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (1000,110008,'{#B}','$.list[:2].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (1001,110008,'{#C}','$.list[:3].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (1002,110010,'{#A}','$.list[:1].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (1003,110010,'{#B}','$.list[:2].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (1004,110010,'{#C}','$.list[:3].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (1005,110011,'{#A}','$.list[:1].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (1006,110011,'{#B}','$.list[:2].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (1007,110011,'{#C}','$.list[:3].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (1008,110012,'{#A}','$.list[:1].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (1009,110012,'{#B}','$.list[:2].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (1010,110012,'{#C}','$.list[:3].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (1011,110012,'{#D}','$.list[:4].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (1012,110012,'{#E}','$.list[:5].type');

-- LLD preprocessing
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params,error_handler,error_handler_params) VALUES (9900,110006,1,5,'^abc$
123',0,'');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params,error_handler,error_handler_params) VALUES (9901,110006,2,5,'^def$
123',1,'');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params,error_handler,error_handler_params) VALUES (9902,110006,3,5,'^ghi$
123',2,'xxx');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params,error_handler,error_handler_params) VALUES (9903,110006,4,5,'^jkl$
123',3,'error');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params,error_handler,error_handler_params) VALUES (9904,110010,1,12,'$.path.to.node1',0,'');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params,error_handler,error_handler_params) VALUES (9905,110010,2,12,'$.path.to.node2',1,'');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params,error_handler,error_handler_params) VALUES (9906,110010,3,12,'$.path.to.node3',2,'xxx');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params,error_handler,error_handler_params) VALUES (9907,110010,4,12,'$.path.to.node4',3,'error');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params,error_handler,error_handler_params) VALUES (9908,110011,1,12,'$.path.to.node1',0,'');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params,error_handler,error_handler_params) VALUES (9909,110011,2,12,'$.path.to.node2',1,'');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params,error_handler,error_handler_params) VALUES (9910,110011,3,12,'$.path.to.node3',2,'xxx');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params,error_handler,error_handler_params) VALUES (9911,110011,4,12,'$.path.to.node4',3,'error');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params,error_handler,error_handler_params) VALUES (9912,110013,1,5,'^abc$
123',0,'');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params,error_handler,error_handler_params) VALUES (9913,110013,2,5,'^def$
123',1,'');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params,error_handler,error_handler_params) VALUES (9914,110013,3,5,'^ghi$
123',2,'xxx');
INSERT INTO item_preproc (item_preprocid,itemid,step,type,params,error_handler,error_handler_params) VALUES (9915,110013,4,5,'^jkl$
123',3,'error');

-- testtriggerfilter
insert into hstgrp (groupid,type,uuid,name) values (139000,0,'0a02d2e17dc04329acf4c74d535ae768','triggerstester');
insert into hstgrp (groupid,type,uuid,name) values (139003,1,'950a7dee672e4617b08e919359500520','triggerstester');
insert into hosts (hostid,host,name,status,description) values (130000,'triggerstester','triggerstester',0,'');
insert into hosts (hostid,host,name,status,description) values (131000,'triggerstestertmpl','triggerstestertmpl',3,'');
insert into hosts_groups (hostgroupid, hostid, groupid) values (139100, 130000, 139000);
insert into hosts_groups (hostgroupid, hostid, groupid) values (139200, 131000, 139003);
insert into items (itemid,hostid,type,name,key_,params,description,posts,headers) values (132000,130000,2,'triggerstesteritem','triggerstesteritem','','','','');
insert into items (itemid,hostid,type,name,key_,params,description,posts,headers) values (132001,131000,2,'triggerstesteritemtmpl','triggerstesteritemtmpl','','','','');
insert into items (itemid,hostid,type,name,key_,flags,params,description,posts,headers) values (132002,130000,2,'triggerstesteritemlld','triggerstesteritemlld',1,'','','','');
insert into items (itemid,hostid,type,name,key_,flags,params,description,posts,headers) values (132003,131000,2,'triggerstesteritemlldtmpl','triggerstesteritemlldtmpl',1,'','','','');
insert into items (itemid,hostid,type,name,key_,flags,params,description,posts,headers) values (132004,130000,2,'triggerstesteritemproto[{#T}]','triggerstesteritemproto[{#T}]',2,'','','','');
insert into items (itemid,hostid,type,name,key_,flags,params,description,posts,headers) values (132005,131000,2,'triggerstesteritemprototmpl[{#T}]','triggerstesteritemprototmpl[{#T}]',2,'','','','');
insert into item_discovery (itemdiscoveryid,itemid,parent_itemid,key_) values (138000,132004,132002,'triggerstesteritemproto[{#T}]');
insert into item_discovery (itemdiscoveryid,itemid,parent_itemid,key_) values (138001,132005,132003,'triggerstesteritemprototmpl[{#T}]');

insert into triggers (triggerid,expression,description,priority,comments) values (134000,'{135000}=0','triggerstester_t0',0,'');
insert into functions (functionid,itemid,triggerid,name,parameter) values (135000,132000,134000,'now','$,0');
insert into triggers (triggerid,expression,description,priority,comments) values (134001,'{135001}=0','triggerstester_t1',1,'');
insert into functions (functionid,itemid,triggerid,name,parameter) values (135001,132000,134001,'now','$,0');
insert into triggers (triggerid,expression,description,priority,comments) values (134002,'{135002}=0','triggerstester_t2',2,'');
insert into functions (functionid,itemid,triggerid,name,parameter) values (135002,132000,134002,'now','$,0');
insert into triggers (triggerid,expression,description,priority,comments) values (134003,'{135003}=0','triggerstester_t3',3,'');
insert into functions (functionid,itemid,triggerid,name,parameter) values (135003,132000,134003,'now','$,0');
insert into triggers (triggerid,expression,description,priority,comments) values (134004,'{135004}=0','triggerstester_t4',4,'');
insert into functions (functionid,itemid,triggerid,name,parameter) values (135004,132000,134004,'now','$,0');
insert into triggers (triggerid,expression,description,priority,comments) values (134005,'{135005}=0','triggerstester_t5',5,'');
insert into functions (functionid,itemid,triggerid,name,parameter) values (135005,132000,134005,'now','$,0');

insert into triggers (triggerid,expression,description,priority,flags,comments) values (134106,'{135106}=0','triggerstesterlld_t0',0,2,'');
insert into functions (functionid,itemid,triggerid,name,parameter) values (135106,132004,134106,'now','$,0');
-- discovered
INSERT INTO items (itemid,hostid,type,name,key_,flags,params,description,posts,headers) VALUES (132006,130000,2,'TriggersTesterItemLLDDiscovered[res1]','TriggersTesterItemLLDDiscovered[res1]',4,'','','','');
INSERT INTO triggers (triggerid,expression,description,priority,flags,comments) VALUES (134118,'{135118}=0','TriggersTesterLLDTmpl_T0[res1]',0,4,'');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (135118,132006,134118,'now','$,0');
INSERT INTO trigger_discovery (triggerid,parent_triggerid) VALUES (134118,134106);
insert into item_discovery (itemdiscoveryid,itemid,parent_itemid,key_) values (138002,132006,132004,'triggerstesteritemprototmpl[{#T}]');
-- T4 depends on T5 depends on T0 (LLD discovered version)
INSERT INTO trigger_depends (triggerdepid,triggerid_down,triggerid_up) VALUES (138888,134004,134005);
INSERT INTO trigger_depends (triggerdepid,triggerid_down,triggerid_up) VALUES (138889,134005,134118);

-- testDiscoveryRule
INSERT INTO hstgrp (groupid, type, uuid, name) values (1004, 0, '1dcc10f0362545648a94256ca3260e8a', 'testDiscoveryRule');

INSERT INTO hosts (hostid, host, name, status, description) VALUES (1017, 'test.discovery.rule.host.1', 'test.discovery.rule.host.1', 0, '');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (1010, 1017, 1, 1, 1, '127.0.0.1', '', '10050');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (1017, 1017, 1004);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (2600, 1017, 'item.1.1.1.1'                  ,  2, 'item.1.1.1.1'                  , 1, '90d', 0, NULL, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers, lifetime, flags) VALUES (2601, 1017, 'dependent.lld.1'               , 18, 'dependent.lld.1'               , 4, '90d', 0, 2600, NULL, '', '', '', '', '30d', 1);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (2602, 1017, 'item.1'                        ,  2, 'item.1'                        , 1, '90d', 0, NULL, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, trends, status, master_itemid, templateid, params, description, posts, headers, lifetime, flags) VALUES (2603, 1017, 'dependent.lld.2'               , 18, 'dependent.lld.2'               , 4, '90d', 0, 0, 2602, NULL, '', '', '', '', '30d', 1);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (2604, 1017, 'item.2'                        ,  2, 'item.2'                        , 1, '90d', 0, NULL, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers, lifetime, flags) VALUES (2605, 1017, 'dependent.lld.3'               , 18, 'dependent.lld.3'               , 4, '90d', 0, 2604, NULL, '', '', '', '', '30d', 1);

INSERT INTO hosts (hostid, host, name, status, description) VALUES (1018, 'test.discovery.rule.host.2', 'test.discovery.rule.host.2', 0, '');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (1011, 1018, 1, 1, 1, '127.0.0.1', '', '10050');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (1018, 1018, 1004);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (2606, 1018, 'item.1'                        ,  2, 'item.1'                        , 1, '90d', 0, NULL, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (2607, 1018, 'item.1.1'                      , 18, 'item.1.1'                      , 1, '90d', 0, 2606, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (2608, 1018, 'item.1.1.1'                    , 18, 'item.1.1.1'                    , 1, '90d', 0, 2607, NULL, '', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers                 ) VALUES (2609, 1018, 'item.1.1.1.1'                  , 18, 'item.1.1.1.1'                  , 1, '90d', 0, 2608, NULL, '', '', '', '');

-- testHistory
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (1005, 0, '0326c9db6487496b9beb6ca353cac1d0', 'history.get/hosts');

INSERT INTO hosts (hostid, host, name, status, description) VALUES (120005, 'history.get.host.1', 'history.get.host.1', 1, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (1019, 120005, 1005);
INSERT INTO interface (interfaceid, hostid, type, ip, useip, port, main) VALUES (1012, 120005, 1, '127.0.0.1', 1, '10050', 1);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers) VALUES (133758, 120005, 'item1', 2, 'item1', 3, '90d', 0, NULL, NULL, '', '', '', '');
INSERT INTO history_uint (itemid, clock, value, ns) VALUES
(133758, 1549350893, 1, 885479055),
(133758, 1549350907, 2, 762947342),
(133758, 1549350908, 3, 727124125),
(133758, 1549350909, 4, 710589839),
(133758, 1549350910, 5, 369715624),
(133758, 1549350910, 5, 738923458),
(133758, 1549350917, 5, 257150200),
(133758, 1549350917, 5, 762668985),
(133758, 1549350918, 5, 394517718),
(133758, 1549350922, 6, 347073267),
(133758, 1549350923, 7, 882834269),
(133758, 1549350926, 8, 410826674),
(133758, 1549350927, 9, 938887279),
(133758, 1549350944, 0, 730916425);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers) VALUES (133759, 120005, 'item2', 2, 'item2', 0, '90d', 0, NULL, NULL, '', '', '', '');
INSERT INTO history (itemid, clock, value, ns) VALUES
(133759, 1549350947, 0.0000, 441606890),
(133759, 1549350948, 0.0000, 544936503),
(133759, 1549350950, 0.0000, 866715049),
(133759, 1549350953, 1.0000, 154942891),
(133759, 1549350955, 1.0000, 719111385),
(133759, 1549350957, 1.0000, 594538048),
(133759, 1549350958, 1.5000, 594538048),
(133759, 1549350959, 1.0001, 594538048),
(133759, 1549350960, 1.5000, 594538048),
(133759, 1549350961, -1.0000, 594538048),
(133759, 1549350962, -1.5000, 594538048);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers) VALUES (133760, 120005, 'item3', 2, 'item3', 1, '90d', 0, NULL, NULL, '', '', '', '');
INSERT INTO history_str (itemid, clock, value, ns) VALUES
(133760, 1549350960, '1', 754460948),
(133760, 1549350962, '1', 919404393),
(133760, 1549350965, '1', 512878374);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers) VALUES (133761, 120005, 'item4', 2, 'item4', 2, '90d', 0, NULL, NULL, '', '', '', '');
INSERT INTO history_log (itemid, clock, timestamp, source, severity, value, logeventid, ns) VALUES
(133761, 1549350969, 0, '', 0, '1', 0, 506909535),
(133761, 1549350973, 0, '', 0, '2', 0, 336068358),
(133761, 1549350976, 0, '', 0, '3', 0, 2798098),
(133761, 1549350987, 0, '', 0, '4', 0, 755363307),
(133761, 1549350992, 0, '', 0, '5', 0, 242736233);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params, description, posts, headers) VALUES (133762, 120005, 'item5', 2, 'item5', 4, '90d', 0, NULL, NULL, '', '', '', '');
INSERT INTO history_text (itemid, clock, value, ns) VALUES
(133762, 1549350998, '1', 450920469),
(133762, 1549350999, '2', 882825407),
(133762, 1549351001, '3', 242835912);

-- Adding records into Auditlog
-- INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (9000, 1, 1582269000, 1, 4, '', '127.0.0.1', 10054, 'H1 updated');
-- INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (9000, 9000, 'hosts', 'status', '0', '1');
-- INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (9001, 1, 1582270260, 1, 4, '', '127.0.0.1', 10054, 'H1 updated');
-- INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (9001, 9001, 'hosts', 'status', '0', '1');
-- INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (9003, 1, 1582269120, 0, 6, 'Graph [graph1]', '::1', 0, '');
-- INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (9004, 4, 1582269180, 0, 19, 'Name [Audit Map]', '192.168.3.32', 0, '');
-- INSERT INTO auditlog (auditid, userid, clock, action, resourcetype, note, ip, resourceid, resourcename) VALUES (9005, 1, 1582269240, 1, 14, '', '192.168.3.32', 6, 'HG1 updated');
-- INSERT INTO auditlog_details (auditdetailid, auditid, table_name, field_name, oldvalue, newvalue) VALUES (9005, 9005, 'groups', 'name', 'HG1', 'HG1 updated');

-- LLD with overrides to delete
INSERT INTO items (itemid,type,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,formula,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,evaltype,jmx_endpoint,master_itemid,timeout,url,query_fields,posts,status_codes,follow_redirects,post_type,http_proxy,headers,retrieve_mode,request_method,output_format,ssl_cert_file,ssl_key_file,ssl_key_password,verify_peer,verify_host,allow_traps,discover) VALUES (133763,2,'',50009,'Overrides (delete)','overrides.delete','0','90d','0',0,4,'','','','',NULL,NULL,'','',0,'','','','',1,NULL,'',0,'30d',0,'',NULL,'3s','','','','200',1,0,'','',0,0,0,'','','',0,0,0,0);
INSERT INTO lld_override (lld_overrideid,itemid,name,step,evaltype,formula,stop) VALUES (10001,133763,'override',1,3,'{10001} or {10002} or {10003}',1);
INSERT INTO lld_override (lld_overrideid,itemid,name,step,evaltype,formula,stop) VALUES (10002,133763,'override 2',2,0,'',1);
INSERT INTO lld_override_condition (lld_override_conditionid,lld_overrideid,operator,macro,value) VALUES (10001,10001,8,'{#MACRO1}','d{3}$');
INSERT INTO lld_override_condition (lld_override_conditionid,lld_overrideid,operator,macro,value) VALUES (10002,10001,8,'{#MACRO2}','d{2}$');
INSERT INTO lld_override_condition (lld_override_conditionid,lld_overrideid,operator,macro,value) VALUES (10003,10001,8,'{#MACRO3}','d{1}$');
INSERT INTO lld_override_operation (lld_override_operationid,lld_overrideid,operationobject,operator,value) VALUES (10001,10001,0,3,'8');
INSERT INTO lld_override_operation (lld_override_operationid,lld_overrideid,operationobject,operator,value) VALUES (10002,10001,0,1,'wW');
INSERT INTO lld_override_operation (lld_override_operationid,lld_overrideid,operationobject,operator,value) VALUES (10003,10001,1,8,'^c+$');
INSERT INTO lld_override_operation (lld_override_operationid,lld_overrideid,operationobject,operator,value) VALUES (10004,10001,2,2,'123');
INSERT INTO lld_override_operation (lld_override_operationid,lld_overrideid,operationobject,operator,value) VALUES (10005,10001,3,0,'');
INSERT INTO lld_override_operation (lld_override_operationid,lld_overrideid,operationobject,operator,value) VALUES (10006,10002,0,0,'');
INSERT INTO lld_override_opdiscover (lld_override_operationid,discover) VALUES (10002,1);
INSERT INTO lld_override_opdiscover (lld_override_operationid,discover) VALUES (10003,1);
INSERT INTO lld_override_opdiscover (lld_override_operationid,discover) VALUES (10004,1);
INSERT INTO lld_override_opdiscover (lld_override_operationid,discover) VALUES (10005,1);
INSERT INTO lld_override_ophistory (lld_override_operationid,history) VALUES (10002,'92d');
INSERT INTO lld_override_opinventory (lld_override_operationid,inventory_mode) VALUES (10005,1);
INSERT INTO lld_override_opperiod (lld_override_operationid,delay) VALUES (10002,'1m;wd1-3h4-16;10s/1-5,00:00-20:00;5s/5-7,00:00-24:00');
INSERT INTO lld_override_opseverity (lld_override_operationid,severity) VALUES (10003,3);
INSERT INTO lld_override_opstatus (lld_override_operationid,status) VALUES (10001,1);
INSERT INTO lld_override_opstatus (lld_override_operationid,status) VALUES (10002,0);
INSERT INTO lld_override_opstatus (lld_override_operationid,status) VALUES (10003,1);
INSERT INTO lld_override_opstatus (lld_override_operationid,status) VALUES (10005,1);
INSERT INTO lld_override_optag (lld_override_optagid,lld_override_operationid,tag,value) VALUES (10001,10003,'tag1','value1');
INSERT INTO lld_override_optag (lld_override_optagid,lld_override_operationid,tag,value) VALUES (10002,10003,'tag2','value2');
INSERT INTO lld_override_optag (lld_override_optagid,lld_override_operationid,tag,value) VALUES (10003,10005,'tag1','value1');
INSERT INTO lld_override_optag (lld_override_optagid,lld_override_operationid,tag,value) VALUES (10004,10005,'tag2','value2');
INSERT INTO lld_override_optemplate (lld_override_optemplateid,lld_override_operationid,templateid) VALUES (10001,10005,10264);
INSERT INTO lld_override_optemplate (lld_override_optemplateid,lld_override_operationid,templateid) VALUES (10002,10005,10265);
INSERT INTO lld_override_optemplate (lld_override_optemplateid,lld_override_operationid,templateid) VALUES (10003,10005,50010);
INSERT INTO lld_override_optrends (lld_override_operationid,trends) VALUES (10002,'36d');
INSERT INTO lld_override_optrends (lld_override_operationid,trends) VALUES (10006,'5d');

-- LLD with overrides to copy
INSERT INTO items (itemid,type,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,formula,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,evaltype,jmx_endpoint,master_itemid,timeout,url,query_fields,posts,status_codes,follow_redirects,post_type,http_proxy,headers,retrieve_mode,request_method,output_format,ssl_cert_file,ssl_key_file,ssl_key_password,verify_peer,verify_host,allow_traps,discover) VALUES (133764,2,'',50009,'Overrides (copy)','overrides.copy','0','90d','0',0,4,'','','','',NULL,NULL,'','',0,'','','','',1,NULL,'',0,'30d',0,'',NULL,'3s','','','','200',1,0,'','',0,0,0,'','','',0,0,0,0);
INSERT INTO lld_override (lld_overrideid,itemid,name,step,evaltype,formula,stop) VALUES (10003,133764,'override',1,3,'{10004} or {10005} or {10006}',1);
INSERT INTO lld_override (lld_overrideid,itemid,name,step,evaltype,formula,stop) VALUES (10004,133764,'override 2',2,0,'',1);
INSERT INTO lld_override_condition (lld_override_conditionid,lld_overrideid,operator,macro,value) VALUES (10004,10003,8,'{#MACRO1}','d{3}$');
INSERT INTO lld_override_condition (lld_override_conditionid,lld_overrideid,operator,macro,value) VALUES (10005,10003,8,'{#MACRO2}','d{2}$');
INSERT INTO lld_override_condition (lld_override_conditionid,lld_overrideid,operator,macro,value) VALUES (10006,10003,8,'{#MACRO3}','d{1}$');
INSERT INTO lld_override_operation (lld_override_operationid,lld_overrideid,operationobject,operator,value) VALUES (10007,10003,0,3,'8');
INSERT INTO lld_override_operation (lld_override_operationid,lld_overrideid,operationobject,operator,value) VALUES (10008,10003,0,1,'wW');
INSERT INTO lld_override_operation (lld_override_operationid,lld_overrideid,operationobject,operator,value) VALUES (10009,10003,1,8,'^c+$');
INSERT INTO lld_override_operation (lld_override_operationid,lld_overrideid,operationobject,operator,value) VALUES (10010,10003,2,2,'123');
INSERT INTO lld_override_operation (lld_override_operationid,lld_overrideid,operationobject,operator,value) VALUES (10011,10003,3,0,'');
INSERT INTO lld_override_operation (lld_override_operationid,lld_overrideid,operationobject,operator,value) VALUES (10012,10004,0,0,'');
INSERT INTO lld_override_opdiscover (lld_override_operationid,discover) VALUES (10008,1);
INSERT INTO lld_override_opdiscover (lld_override_operationid,discover) VALUES (10009,1);
INSERT INTO lld_override_opdiscover (lld_override_operationid,discover) VALUES (10010,1);
INSERT INTO lld_override_opdiscover (lld_override_operationid,discover) VALUES (10011,1);
INSERT INTO lld_override_ophistory (lld_override_operationid,history) VALUES (10008,'92d');
INSERT INTO lld_override_opinventory (lld_override_operationid,inventory_mode) VALUES (10011,1);
INSERT INTO lld_override_opperiod (lld_override_operationid,delay) VALUES (10008,'1m;wd1-3h4-16;10s/1-5,00:00-20:00;5s/5-7,00:00-24:00');
INSERT INTO lld_override_opseverity (lld_override_operationid,severity) VALUES (10009,3);
INSERT INTO lld_override_opstatus (lld_override_operationid,status) VALUES (10007,1);
INSERT INTO lld_override_opstatus (lld_override_operationid,status) VALUES (10008,0);
INSERT INTO lld_override_opstatus (lld_override_operationid,status) VALUES (10009,1);
INSERT INTO lld_override_opstatus (lld_override_operationid,status) VALUES (10011,1);
INSERT INTO lld_override_optag (lld_override_optagid,lld_override_operationid,tag,value) VALUES (10005,10009,'tag1','value1');
INSERT INTO lld_override_optag (lld_override_optagid,lld_override_operationid,tag,value) VALUES (10006,10009,'tag2','value2');
INSERT INTO lld_override_optag (lld_override_optagid,lld_override_operationid,tag,value) VALUES (10007,10011,'tag1','value1');
INSERT INTO lld_override_optag (lld_override_optagid,lld_override_operationid,tag,value) VALUES (10008,10011,'tag2','value2');
INSERT INTO lld_override_optemplate (lld_override_optemplateid,lld_override_operationid,templateid) VALUES (10004,10011,10264);
INSERT INTO lld_override_optemplate (lld_override_optemplateid,lld_override_operationid,templateid) VALUES (10005,10011,10265);
INSERT INTO lld_override_optemplate (lld_override_optemplateid,lld_override_operationid,templateid) VALUES (10006,10011,50010);
INSERT INTO lld_override_optrends (lld_override_operationid,trends) VALUES (10008,'36d');
INSERT INTO lld_override_optrends (lld_override_operationid,trends) VALUES (10012,'5d');

-- LLD with overrides to update
INSERT INTO items (itemid,type,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,formula,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,evaltype,jmx_endpoint,master_itemid,timeout,url,query_fields,posts,status_codes,follow_redirects,post_type,http_proxy,headers,retrieve_mode,request_method,output_format,ssl_cert_file,ssl_key_file,ssl_key_password,verify_peer,verify_host,allow_traps,discover) VALUES (133765,2,'',50009,'Overrides (update)','overrides.update','0','90d','0',0,4,'','','','',NULL,NULL,'','',0,'','','','',1,NULL,'',0,'30d',0,'',NULL,'3s','','','','200',1,0,'','',0,0,0,'','','',0,0,0,0);
INSERT INTO lld_override (lld_overrideid,itemid,name,step,evaltype,formula,stop) VALUES (10005,133765,'override',1,3,'{10007} or {10008} or {10009}',1);
INSERT INTO lld_override (lld_overrideid,itemid,name,step,evaltype,formula,stop) VALUES (10006,133765,'override 2',2,0,'',1);
INSERT INTO lld_override_condition (lld_override_conditionid,lld_overrideid,operator,macro,value) VALUES (10007,10005,8,'{#MACRO1}','d{3}$');
INSERT INTO lld_override_condition (lld_override_conditionid,lld_overrideid,operator,macro,value) VALUES (10008,10005,8,'{#MACRO2}','d{2}$');
INSERT INTO lld_override_condition (lld_override_conditionid,lld_overrideid,operator,macro,value) VALUES (10009,10005,8,'{#MACRO3}','d{1}$');
INSERT INTO lld_override_operation (lld_override_operationid,lld_overrideid,operationobject,operator,value) VALUES (10013,10005,0,3,'8');
INSERT INTO lld_override_operation (lld_override_operationid,lld_overrideid,operationobject,operator,value) VALUES (10014,10005,0,1,'wW');
INSERT INTO lld_override_operation (lld_override_operationid,lld_overrideid,operationobject,operator,value) VALUES (10015,10005,1,8,'^c+$');
INSERT INTO lld_override_operation (lld_override_operationid,lld_overrideid,operationobject,operator,value) VALUES (10016,10005,2,2,'123');
INSERT INTO lld_override_operation (lld_override_operationid,lld_overrideid,operationobject,operator,value) VALUES (10017,10005,3,0,'');
INSERT INTO lld_override_operation (lld_override_operationid,lld_overrideid,operationobject,operator,value) VALUES (10018,10006,0,0,'');
INSERT INTO lld_override_opdiscover (lld_override_operationid,discover) VALUES (10014,1);
INSERT INTO lld_override_opdiscover (lld_override_operationid,discover) VALUES (10015,1);
INSERT INTO lld_override_opdiscover (lld_override_operationid,discover) VALUES (10016,1);
INSERT INTO lld_override_opdiscover (lld_override_operationid,discover) VALUES (10017,1);
INSERT INTO lld_override_ophistory (lld_override_operationid,history) VALUES (10014,'92d');
INSERT INTO lld_override_opinventory (lld_override_operationid,inventory_mode) VALUES (10017,1);
INSERT INTO lld_override_opperiod (lld_override_operationid,delay) VALUES (10014,'1m;wd1-3h4-16;10s/1-5,00:00-20:00;5s/5-7,00:00-24:00');
INSERT INTO lld_override_opseverity (lld_override_operationid,severity) VALUES (10015,3);
INSERT INTO lld_override_opstatus (lld_override_operationid,status) VALUES (10013,1);
INSERT INTO lld_override_opstatus (lld_override_operationid,status) VALUES (10014,0);
INSERT INTO lld_override_opstatus (lld_override_operationid,status) VALUES (10015,1);
INSERT INTO lld_override_opstatus (lld_override_operationid,status) VALUES (10017,1);
INSERT INTO lld_override_optag (lld_override_optagid,lld_override_operationid,tag,value) VALUES (10009,10015,'tag1','value1');
INSERT INTO lld_override_optag (lld_override_optagid,lld_override_operationid,tag,value) VALUES (10010,10015,'tag2','value2');
INSERT INTO lld_override_optag (lld_override_optagid,lld_override_operationid,tag,value) VALUES (10011,10017,'tag1','value1');
INSERT INTO lld_override_optag (lld_override_optagid,lld_override_operationid,tag,value) VALUES (10012,10017,'tag2','value2');
INSERT INTO lld_override_optemplate (lld_override_optemplateid,lld_override_operationid,templateid) VALUES (10007,10017,10264);
INSERT INTO lld_override_optemplate (lld_override_optemplateid,lld_override_operationid,templateid) VALUES (10008,10017,10265);
INSERT INTO lld_override_optemplate (lld_override_optemplateid,lld_override_operationid,templateid) VALUES (10009,10017,50010);
INSERT INTO lld_override_optrends (lld_override_operationid,trends) VALUES (10014,'36d');
INSERT INTO lld_override_optrends (lld_override_operationid,trends) VALUES (10018,'5d');

-- LLD with overrides and template constraint
INSERT INTO hosts (hostid,proxy_hostid,host,status,ipmi_authtype,ipmi_privilege,ipmi_username,ipmi_password,maintenanceid,maintenance_status,maintenance_type,maintenance_from,name,flags,templateid,description,tls_connect,tls_accept,tls_issuer,tls_subject,tls_psk_identity,tls_psk,proxy_address,auto_compress,discover) VALUES (131001,NULL,'Overrides template constraint',3,-1,2,'','',NULL,0,0,0,'Overrides template constaint',0,NULL,'',1,1,'','','','','',1,0);
INSERT INTO hstgrp (groupid,type,uuid,name,flags) VALUES (139001,1,'27e5744a60894c7c88c8df39573df06e','Overrides',0);
INSERT INTO hosts_groups (hostgroupid,hostid,groupid) VALUES (139201,131001,139001);
INSERT INTO items (itemid,type,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,formula,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,evaltype,jmx_endpoint,master_itemid,timeout,url,query_fields,posts,status_codes,follow_redirects,post_type,http_proxy,headers,retrieve_mode,request_method,output_format,ssl_cert_file,ssl_key_file,ssl_key_password,verify_peer,verify_host,allow_traps,discover) VALUES (133766,0,'',50009,'Overrides (template constraint)','overrides.template.constraint','1m','90d','0',0,4,'','','','',NULL,NULL,'','',0,'','','','',1,50022,'',0,'30d',0,'',NULL,'3s','','','','200',1,0,'','',0,0,0,'','','',0,0,0,0);
INSERT INTO lld_override (lld_overrideid,itemid,name,step,evaltype,formula,stop) VALUES (10007,133766,'Only template operation',1,0,'',0);
INSERT INTO lld_override (lld_overrideid,itemid,name,step,evaltype,formula,stop) VALUES (10008,133766,'Not only template operation',2,0,'',0);
INSERT INTO lld_override_operation (lld_override_operationid,lld_overrideid,operationobject,operator,value) VALUES (10019,10007,3,0,'');
INSERT INTO lld_override_operation (lld_override_operationid,lld_overrideid,operationobject,operator,value) VALUES (10020,10008,3,0,'');
INSERT INTO lld_override_opinventory (lld_override_operationid,inventory_mode) VALUES (10020,0);
INSERT INTO lld_override_optemplate (lld_override_optemplateid,lld_override_operationid,templateid) VALUES (10010,10019,131001);
INSERT INTO lld_override_optemplate (lld_override_optemplateid,lld_override_operationid,templateid) VALUES (10011,10020,131001);

-- graph portotype
INSERT INTO hstgrp (groupid,type,uuid,name,flags) VALUES (139002,0,'c8aa3b02b1af4b049fcf88be47e5f892','test_graph_prototype',0);
INSERT INTO hosts (hostid,proxy_hostid,host,status,ipmi_authtype,ipmi_privilege,ipmi_username,ipmi_password,maintenanceid,maintenance_status,maintenance_type,maintenance_from,name,flags,templateid,description,tls_connect,tls_accept,tls_issuer,tls_subject,tls_psk_identity,tls_psk,proxy_address,auto_compress,discover) VALUES (131002,NULL,'item',0,-1,2,'','',NULL,0,0,0,'item',0,NULL,'',1,1,'','','','','',1,0);
INSERT INTO hosts (hostid,proxy_hostid,host,status,ipmi_authtype,ipmi_privilege,ipmi_username,ipmi_password,maintenanceid,maintenance_status,maintenance_type,maintenance_from,name,flags,templateid,description,tls_connect,tls_accept,tls_issuer,tls_subject,tls_psk_identity,tls_psk,proxy_address,auto_compress,discover) VALUES (131003,NULL,'item_prototype',0,-1,2,'','',NULL,0,0,0,'item_prototype',0,NULL,'',1,1,'','','','','',1,0);
INSERT INTO hosts_groups (hostgroupid,hostid,groupid) VALUES (139203,131003,139002);
INSERT INTO hosts_groups (hostgroupid,hostid,groupid) VALUES (139202,131002,139002);
INSERT INTO items (itemid,type,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,formula,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,evaltype,jmx_endpoint,master_itemid,timeout,url,query_fields,posts,status_codes,follow_redirects,post_type,http_proxy,headers,retrieve_mode,request_method,output_format,ssl_cert_file,ssl_key_file,ssl_key_password,verify_peer,verify_host,allow_traps,discover) VALUES (133767,2,'',131003,'rule','a','0','90d','0',0,4,'','','','',NULL,NULL,'','',0,'','','','',1,NULL,'',0,'30d',0,'',NULL,'3s','','','','200',1,0,'','',0,0,0,'','','',0,0,0,0);
INSERT INTO items (itemid,type,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,formula,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,evaltype,jmx_endpoint,master_itemid,timeout,url,query_fields,posts,status_codes,follow_redirects,post_type,http_proxy,headers,retrieve_mode,request_method,output_format,ssl_cert_file,ssl_key_file,ssl_key_password,verify_peer,verify_host,allow_traps,discover) VALUES (133768,2,'',131003,'prototype','a[{#A}]','0','90d','365d',0,3,'','','','',NULL,NULL,'','',0,'','','','',2,NULL,'',0,'30d',0,'',NULL,'3s','','','','200',1,0,'','',0,0,0,'','','',0,0,0,0);
INSERT INTO items (itemid,type,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,formula,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,evaltype,jmx_endpoint,master_itemid,timeout,url,query_fields,posts,status_codes,follow_redirects,post_type,http_proxy,headers,retrieve_mode,request_method,output_format,ssl_cert_file,ssl_key_file,ssl_key_password,verify_peer,verify_host,allow_traps,discover) VALUES (133769,2,'',131002,'item','a','0','90d','365d',0,3,'','','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30d',0,'',NULL,'3s','','','','200',1,0,'','',0,0,0,'','','',0,0,0,0);
INSERT INTO item_discovery (itemdiscoveryid,itemid,parent_itemid,key_,lastcheck,ts_delete) VALUES (138003,133768,133767,'',0,0);
INSERT INTO graphs (graphid,name,width,height,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype,show_legend,show_3d,percent_left,percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid,flags,discover) VALUES (9000,'graph_prototype',900,200,0,100,NULL,1,1,0,1,0,0,0,0,0,NULL,NULL,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (170450,9000,133769,0,1,'F63100',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (170451,9000,133768,0,0,'1A7C11',0,2,0);

-- trigger permissions: BEGIN

INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (50101, 0, 'fe26656029d646128d7ae50b22d0d106', 'test-trigger-permissions-group-N');
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (50102, 0, '7d221858b46e4af09a25b4f4e8f1f027', 'test-trigger-permissions-group-D');
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (50103, 0, '5863042f931d4496b29c34d6fd9d3cd0', 'test-trigger-permissions-group-R');
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (50104, 0, 'a1a2176605f44cd8ac981717e45e461e', 'test-trigger-permissions-group-W');

INSERT INTO usrgrp (usrgrpid, name) VALUES (50101, 'test-trigger-permissions-user-group');
INSERT INTO users (userid, username, passwd, roleid) VALUES (50101, 'test-trigger-permissions-user', '$2y$10$VKVVejdnWSz08PPa0Xb9g.igAz.iWne3EaxXPX5WF8WsbrrA.lE4K', 1);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (50101, 50101, 50101);
INSERT INTO rights (rightid, groupid, id, permission) VALUES (50101, 50101, 50102, 0), (50102, 50101, 50103, 2), (50103, 50101, 50104, 3);

INSERT INTO hosts (hostid, host, name, status, description) VALUES (50101, 'test-trigger-permissions-host-N', 'test-trigger-permissions-host-N', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50101, 50101, 50101);
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (50101, 50101, 1, '127.0.0.1', '', 1, '10050', 1);
INSERT INTO items (itemid, hostid, interfaceid, name, type, key_, value_type, delay, history, trends, params, description, posts, headers, status) VALUES (50101, 50101, 50101, 'test-trigger-permissions-item-N', 0, 'test-trigger-permissions-item-N', 3, '1m', '90d', '365d', '', '', '', '', 0);
INSERT INTO item_rtdata (itemid) VALUES (50101);

INSERT INTO hosts (hostid, host, name, status, description) VALUES (50102, 'test-trigger-permissions-host-D', 'test-trigger-permissions-host-D', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50102, 50102, 50102);
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (50102, 50102, 1, '127.0.0.1', '', 1, '10050', 1);
INSERT INTO items (itemid, hostid, interfaceid, name, type, key_, value_type, delay, history, trends, params, description, posts, headers, status) VALUES (50102, 50102, 50102, 'test-trigger-permissions-item-D', 0, 'test-trigger-permissions-item-D', 3, '1m', '90d', '365d', '', '', '', '', 0);
INSERT INTO item_rtdata (itemid) VALUES (50102);

INSERT INTO hosts (hostid, host, name, status, description) VALUES (50103, 'test-trigger-permissions-host-R', 'test-trigger-permissions-host-R', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50103, 50103, 50103);
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (50103, 50103, 1, '127.0.0.1', '', 1, '10050', 1);
INSERT INTO items (itemid, hostid, interfaceid, name, type, key_, value_type, delay, history, trends, params, description, posts, headers, status) VALUES (50103, 50103, 50103, 'test-trigger-permissions-item-R', 0, 'test-trigger-permissions-item-R', 3, '1m', '90d', '365d', '', '', '', '', 0);
INSERT INTO item_rtdata (itemid) VALUES (50103);

INSERT INTO hosts (hostid, host, name, status, description) VALUES (50104, 'test-trigger-permissions-host-W', 'test-trigger-permissions-host-W', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50104, 50104, 50104);
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (50104, 50104, 1, '127.0.0.1', '', 1, '10050', 1);
INSERT INTO items (itemid, hostid, interfaceid, name, type, key_, value_type, delay, history, trends, params, description, posts, headers, status) VALUES (50104, 50104, 50104, 'test-trigger-permissions-item-W', 0, 'test-trigger-permissions-item-W', 3, '1m', '90d', '365d', '', '', '', '', 0);
INSERT INTO item_rtdata (itemid) VALUES (50104);

INSERT INTO hosts (hostid, host, name, status, description) VALUES (50105, 'test-trigger-permissions-host-ND', 'test-trigger-permissions-host-ND', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50105, 50105, 50101), (50106, 50105, 50102);
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (50105, 50105, 1, '127.0.0.1', '', 1, '10050', 1);
INSERT INTO items (itemid, hostid, interfaceid, name, type, key_, value_type, delay, history, trends, params, description, posts, headers, status) VALUES (50105, 50105, 50105, 'test-trigger-permissions-item-ND', 0, 'test-trigger-permissions-item-ND', 3, '1m', '90d', '365d', '', '', '', '', 0);
INSERT INTO item_rtdata (itemid) VALUES (50105);

INSERT INTO hosts (hostid, host, name, status, description) VALUES (50106, 'test-trigger-permissions-host-NR', 'test-trigger-permissions-host-NR', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50107, 50106, 50101), (50108, 50106, 50103);
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (50106, 50106, 1, '127.0.0.1', '', 1, '10050', 1);
INSERT INTO items (itemid, hostid, interfaceid, name, type, key_, value_type, delay, history, trends, params, description, posts, headers, status) VALUES (50106, 50106, 50106, 'test-trigger-permissions-item-NR', 0, 'test-trigger-permissions-item-NR', 3, '1m', '90d', '365d', '', '', '', '', 0);
INSERT INTO item_rtdata (itemid) VALUES (50106);

INSERT INTO hosts (hostid, host, name, status, description) VALUES (50107, 'test-trigger-permissions-host-NW', 'test-trigger-permissions-host-NW', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50109, 50107, 50101), (50110, 50107, 50104);
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (50107, 50107, 1, '127.0.0.1', '', 1, '10050', 1);
INSERT INTO items (itemid, hostid, interfaceid, name, type, key_, value_type, delay, history, trends, params, description, posts, headers, status) VALUES (50107, 50107, 50107, 'test-trigger-permissions-item-NW', 0, 'test-trigger-permissions-item-NW', 3, '1m', '90d', '365d', '', '', '', '', 0);
INSERT INTO item_rtdata (itemid) VALUES (50107);

INSERT INTO hosts (hostid, host, name, status, description) VALUES (50108, 'test-trigger-permissions-host-DR', 'test-trigger-permissions-host-DR', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50111, 50108, 50102), (50112, 50108, 50103);
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (50108, 50108, 1, '127.0.0.1', '', 1, '10050', 1);
INSERT INTO items (itemid, hostid, interfaceid, name, type, key_, value_type, delay, history, trends, params, description, posts, headers, status) VALUES (50108, 50108, 50108, 'test-trigger-permissions-item-DR', 0, 'test-trigger-permissions-item-DR', 3, '1m', '90d', '365d', '', '', '', '', 0);
INSERT INTO item_rtdata (itemid) VALUES (50108);

INSERT INTO hosts (hostid, host, name, status, description) VALUES (50109, 'test-trigger-permissions-host-DW', 'test-trigger-permissions-host-DW', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50113, 50109, 50102), (50114, 50109, 50104);
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (50109, 50109, 1, '127.0.0.1', '', 1, '10050', 1);
INSERT INTO items (itemid, hostid, interfaceid, name, type, key_, value_type, delay, history, trends, params, description, posts, headers, status) VALUES (50109, 50109, 50109, 'test-trigger-permissions-item-DW', 0, 'test-trigger-permissions-item-DW', 3, '1m', '90d', '365d', '', '', '', '', 0);
INSERT INTO item_rtdata (itemid) VALUES (50109);

INSERT INTO hosts (hostid, host, name, status, description) VALUES (50110, 'test-trigger-permissions-host-RW', 'test-trigger-permissions-host-RW', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50115, 50110, 50103), (50116, 50110, 50104);
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (50110, 50110, 1, '127.0.0.1', '', 1, '10050', 1);
INSERT INTO items (itemid, hostid, interfaceid, name, type, key_, value_type, delay, history, trends, params, description, posts, headers, status) VALUES (50110, 50110, 50110, 'test-trigger-permissions-item-RW', 0, 'test-trigger-permissions-item-RW', 3, '1m', '90d', '365d', '', '', '', '', 0);
INSERT INTO item_rtdata (itemid) VALUES (50110);

INSERT INTO hosts (hostid, host, name, status, description) VALUES (50111, 'test-trigger-permissions-host-NDR', 'test-trigger-permissions-host-NDR', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50117, 50111, 50101), (50118, 50111, 50102), (50119, 50111, 50103);
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (50111, 50111, 1, '127.0.0.1', '', 1, '10050', 1);
INSERT INTO items (itemid, hostid, interfaceid, name, type, key_, value_type, delay, history, trends, params, description, posts, headers, status) VALUES (50111, 50111, 50111, 'test-trigger-permissions-item-NDR', 0, 'test-trigger-permissions-item-NDR', 3, '1m', '90d', '365d', '', '', '', '', 0);
INSERT INTO item_rtdata (itemid) VALUES (50111);

INSERT INTO hosts (hostid, host, name, status, description) VALUES (50112, 'test-trigger-permissions-host-NDW', 'test-trigger-permissions-host-NDW', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50120, 50112, 50101), (50121, 50112, 50102), (50122, 50112, 50104);
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (50112, 50112, 1, '127.0.0.1', '', 1, '10050', 1);
INSERT INTO items (itemid, hostid, interfaceid, name, type, key_, value_type, delay, history, trends, params, description, posts, headers, status) VALUES (50112, 50112, 50112, 'test-trigger-permissions-item-NDW', 0, 'test-trigger-permissions-item-NDW', 3, '1m', '90d', '365d', '', '', '', '', 0);
INSERT INTO item_rtdata (itemid) VALUES (50112);

INSERT INTO hosts (hostid, host, name, status, description) VALUES (50113, 'test-trigger-permissions-host-NRW', 'test-trigger-permissions-host-NRW', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50123, 50113, 50101), (50124, 50113, 50103), (50125, 50113, 50104);
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (50113, 50113, 1, '127.0.0.1', '', 1, '10050', 1);
INSERT INTO items (itemid, hostid, interfaceid, name, type, key_, value_type, delay, history, trends, params, description, posts, headers, status) VALUES (50113, 50113, 50113, 'test-trigger-permissions-item-NRW', 0, 'test-trigger-permissions-item-NRW', 3, '1m', '90d', '365d', '', '', '', '', 0);
INSERT INTO item_rtdata (itemid) VALUES (50113);

INSERT INTO hosts (hostid, host, name, status, description) VALUES (50114, 'test-trigger-permissions-host-DRW', 'test-trigger-permissions-host-DRW', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50126, 50114, 50102), (50127, 50114, 50103), (50128, 50114, 50104);
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (50114, 50114, 1, '127.0.0.1', '', 1, '10050', 1);
INSERT INTO items (itemid, hostid, interfaceid, name, type, key_, value_type, delay, history, trends, params, description, posts, headers, status) VALUES (50114, 50114, 50114, 'test-trigger-permissions-item-DRW', 0, 'test-trigger-permissions-item-DRW', 3, '1m', '90d', '365d', '', '', '', '', 0);
INSERT INTO item_rtdata (itemid) VALUES (50114);

INSERT INTO hosts (hostid, host, name, status, description) VALUES (50115, 'test-trigger-permissions-host-NDRW', 'test-trigger-permissions-host-NDRW', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50129, 50115, 50101), (50130, 50115, 50102), (50131, 50115, 50103), (50132, 50115, 50104);
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (50115, 50115, 1, '127.0.0.1', '', 1, '10050', 1);
INSERT INTO items (itemid, hostid, interfaceid, name, type, key_, value_type, delay, history, trends, params, description, posts, headers, status) VALUES (50115, 50115, 50115, 'test-trigger-permissions-item-NDRW', 0, 'test-trigger-permissions-item-NDRW', 3, '1m', '90d', '365d', '', '', '', '', 0);
INSERT INTO item_rtdata (itemid) VALUES (50115);

INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50101, 'test-trigger-permissions-trigger-{N}', '{50101}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50101, 50101, 50101, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50102, 'test-trigger-permissions-trigger-{D}', '{50102}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50102, 50102, 50102, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50103, 'test-trigger-permissions-trigger-{R}', '{50103}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50103, 50103, 50103, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50104, 'test-trigger-permissions-trigger-{W}', '{50104}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50104, 50104, 50104, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50105, 'test-trigger-permissions-trigger-{N}-{D}', '{50105} or {50106}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50105, 50105, 50101, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50106, 50105, 50102, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50106, 'test-trigger-permissions-trigger-{N}-{R}', '{50107} or {50108}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50107, 50106, 50101, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50108, 50106, 50103, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50107, 'test-trigger-permissions-trigger-{N}-{W}', '{50109} or {50110}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50109, 50107, 50101, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50110, 50107, 50104, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50108, 'test-trigger-permissions-trigger-{D}-{R}', '{50111} or {50112}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50111, 50108, 50102, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50112, 50108, 50103, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50109, 'test-trigger-permissions-trigger-{D}-{W}', '{50113} or {50114}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50113, 50109, 50102, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50114, 50109, 50104, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50110, 'test-trigger-permissions-trigger-{R}-{W}', '{50115} or {50116}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50115, 50110, 50103, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50116, 50110, 50104, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50111, 'test-trigger-permissions-trigger-{N}-{D}-{R}', '{50117} or {50118} or {50119}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50117, 50111, 50101, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50118, 50111, 50102, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50119, 50111, 50103, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50112, 'test-trigger-permissions-trigger-{N}-{D}-{W}', '{50120} or {50121} or {50122}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50120, 50112, 50101, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50121, 50112, 50102, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50122, 50112, 50104, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50113, 'test-trigger-permissions-trigger-{N}-{R}-{W}', '{50123} or {50124} or {50125}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50123, 50113, 50101, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50124, 50113, 50103, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50125, 50113, 50104, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50114, 'test-trigger-permissions-trigger-{D}-{R}-{W}', '{50126} or {50127} or {50128}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50126, 50114, 50102, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50127, 50114, 50103, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50128, 50114, 50104, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50115, 'test-trigger-permissions-trigger-{N}-{D}-{R}-{W}', '{50129} or {50130} or {50131} or {50132}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50129, 50115, 50101, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50130, 50115, 50102, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50131, 50115, 50103, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50132, 50115, 50104, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50116, 'test-trigger-permissions-trigger-{ND}', '{50133}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50133, 50116, 50105, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50117, 'test-trigger-permissions-trigger-{NR}', '{50134}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50134, 50117, 50106, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50118, 'test-trigger-permissions-trigger-{NW}', '{50135}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50135, 50118, 50107, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50119, 'test-trigger-permissions-trigger-{DR}', '{50136}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50136, 50119, 50108, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50120, 'test-trigger-permissions-trigger-{DW}', '{50137}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50137, 50120, 50109, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50121, 'test-trigger-permissions-trigger-{RW}', '{50138}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50138, 50121, 50110, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50122, 'test-trigger-permissions-trigger-{NDR}', '{50139}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50139, 50122, 50111, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50123, 'test-trigger-permissions-trigger-{NDW}', '{50140}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50140, 50123, 50112, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50124, 'test-trigger-permissions-trigger-{NRW}', '{50141}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50141, 50124, 50113, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50125, 'test-trigger-permissions-trigger-{DRW}', '{50142}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50142, 50125, 50114, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50126, 'test-trigger-permissions-trigger-{NDRW}', '{50143}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50143, 50126, 50115, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50127, 'test-trigger-permissions-trigger-{N}-{ND}', '{50144} or {50145}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50144, 50127, 50101, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50145, 50127, 50105, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50128, 'test-trigger-permissions-trigger-{N}-{NR}', '{50146} or {50147}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50146, 50128, 50101, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50147, 50128, 50106, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50129, 'test-trigger-permissions-trigger-{N}-{NW}', '{50148} or {50149}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50148, 50129, 50101, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50149, 50129, 50107, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50130, 'test-trigger-permissions-trigger-{N}-{DR}', '{50150} or {50151}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50150, 50130, 50101, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50151, 50130, 50108, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50131, 'test-trigger-permissions-trigger-{N}-{DW}', '{50152} or {50153}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50152, 50131, 50101, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50153, 50131, 50109, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50132, 'test-trigger-permissions-trigger-{N}-{RW}', '{50154} or {50155}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50154, 50132, 50101, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50155, 50132, 50110, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50133, 'test-trigger-permissions-trigger-{N}-{NDR}', '{50156} or {50157}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50156, 50133, 50101, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50157, 50133, 50111, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50134, 'test-trigger-permissions-trigger-{N}-{NDW}', '{50158} or {50159}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50158, 50134, 50101, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50159, 50134, 50112, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50135, 'test-trigger-permissions-trigger-{N}-{NRW}', '{50160} or {50161}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50160, 50135, 50101, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50161, 50135, 50113, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50136, 'test-trigger-permissions-trigger-{N}-{DRW}', '{50162} or {50163}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50162, 50136, 50101, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50163, 50136, 50114, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50137, 'test-trigger-permissions-trigger-{N}-{NDRW}', '{50164} or {50165}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50164, 50137, 50101, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50165, 50137, 50115, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50138, 'test-trigger-permissions-trigger-{D}-{ND}', '{50166} or {50167}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50166, 50138, 50102, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50167, 50138, 50105, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50139, 'test-trigger-permissions-trigger-{D}-{NR}', '{50168} or {50169}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50168, 50139, 50102, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50169, 50139, 50106, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50140, 'test-trigger-permissions-trigger-{D}-{NW}', '{50170} or {50171}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50170, 50140, 50102, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50171, 50140, 50107, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50142, 'test-trigger-permissions-trigger-{D}-{DR}', '{50172} or {50173}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50172, 50142, 50102, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50173, 50142, 50108, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50143, 'test-trigger-permissions-trigger-{D}-{DW}', '{50174} or {50175}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50174, 50143, 50102, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50175, 50143, 50109, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50144, 'test-trigger-permissions-trigger-{D}-{RW}', '{50176} or {50177}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50176, 50144, 50102, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50177, 50144, 50110, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50145, 'test-trigger-permissions-trigger-{D}-{NDR}', '{50178} or {50179}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50178, 50145, 50102, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50179, 50145, 50111, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50146, 'test-trigger-permissions-trigger-{D}-{NDW}', '{50180} or {50181}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50180, 50146, 50102, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50181, 50146, 50112, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50147, 'test-trigger-permissions-trigger-{D}-{NRW}', '{50182} or {50183}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50182, 50147, 50102, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50183, 50147, 50113, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50148, 'test-trigger-permissions-trigger-{D}-{DRW}', '{50184} or {50185}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50184, 50148, 50102, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50185, 50148, 50114, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50149, 'test-trigger-permissions-trigger-{D}-{NDRW}', '{50186} or {50187}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50186, 50149, 50102, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50187, 50149, 50115, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50150, 'test-trigger-permissions-trigger-{R}-{ND}', '{50188} or {50189}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50188, 50150, 50103, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50189, 50150, 50105, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50151, 'test-trigger-permissions-trigger-{R}-{NR}', '{50190} or {50191}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50190, 50151, 50103, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50191, 50151, 50106, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50152, 'test-trigger-permissions-trigger-{R}-{NW}', '{50192} or {50193}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50192, 50152, 50103, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50193, 50152, 50107, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50153, 'test-trigger-permissions-trigger-{R}-{DR}', '{50194} or {50195}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50194, 50153, 50103, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50195, 50153, 50108, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50154, 'test-trigger-permissions-trigger-{R}-{DW}', '{50196} or {50197}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50196, 50154, 50103, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50197, 50154, 50109, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50155, 'test-trigger-permissions-trigger-{R}-{RW}', '{50198} or {50199}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50198, 50155, 50103, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50199, 50155, 50110, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50156, 'test-trigger-permissions-trigger-{R}-{NDR}', '{50200} or {50201}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50200, 50156, 50103, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50201, 50156, 50111, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50157, 'test-trigger-permissions-trigger-{R}-{NDW}', '{50202} or {50203}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50202, 50157, 50103, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50203, 50157, 50112, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50158, 'test-trigger-permissions-trigger-{R}-{NRW}', '{50204} or {50205}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50204, 50158, 50103, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50205, 50158, 50113, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50159, 'test-trigger-permissions-trigger-{R}-{DRW}', '{50206} or {50207}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50206, 50159, 50103, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50207, 50159, 50114, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50160, 'test-trigger-permissions-trigger-{R}-{NDRW}', '{50208} or {50209}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50208, 50160, 50103, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50209, 50160, 50115, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50161, 'test-trigger-permissions-trigger-{W}-{ND}', '{50210} or {50211}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50210, 50161, 50104, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50211, 50161, 50105, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50162, 'test-trigger-permissions-trigger-{W}-{NR}', '{50212} or {50213}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50212, 50162, 50104, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50213, 50162, 50106, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50163, 'test-trigger-permissions-trigger-{W}-{NW}', '{50214} or {50215}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50214, 50163, 50104, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50215, 50163, 50107, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50164, 'test-trigger-permissions-trigger-{W}-{DR}', '{50216} or {50217}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50216, 50164, 50104, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50217, 50164, 50108, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50165, 'test-trigger-permissions-trigger-{W}-{DW}', '{50218} or {50219}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50218, 50165, 50104, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50219, 50165, 50109, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50166, 'test-trigger-permissions-trigger-{W}-{RW}', '{50220} or {50221}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50220, 50166, 50104, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50221, 50166, 50110, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50167, 'test-trigger-permissions-trigger-{W}-{NDR}', '{50222} or {50223}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50222, 50167, 50104, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50223, 50167, 50111, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50168, 'test-trigger-permissions-trigger-{W}-{NDW}', '{50224} or {50225}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50224, 50168, 50104, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50225, 50168, 50112, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50169, 'test-trigger-permissions-trigger-{W}-{NRW}', '{50226} or {50227}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50226, 50169, 50104, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50227, 50169, 50113, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50170, 'test-trigger-permissions-trigger-{W}-{DRW}', '{50228} or {50229}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50228, 50170, 50104, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50229, 50170, 50114, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50171, 'test-trigger-permissions-trigger-{W}-{NDRW}', '{50230} or {50231}', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50230, 50171, 50104, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50231, 50171, 50115, 'last', '$');

-- trigger permissions: END

-- test discovered host groups after import parent host
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (50025, 0, '45d1ca90cd844dd98762e118ea3208fc', 'Master group');
INSERT INTO hstgrp (groupid, type, uuid, name, flags) VALUES (50026, 0, '5421d5c696c347478bee88fe39d2040f', 'host group discovered', 4);
INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (99010, 'Host having discovered hosts', 'Host having discovered hosts', 0, 0, '');
INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (99011, '{#VALUE}', '{#VALUE}', 0, 2, '');
INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (99012, 'discovered', 'discovered', 0, 4, '');
INSERT INTO items (itemid, type, hostid, name, key_, delay, history, trends, status, value_type, flags, params, description, posts, headers) VALUES (58735, 2, 99010, 'trap', 'trap', '0', '90d', '0', 0, 4, 1, '', '', '', '');
INSERT INTO group_prototype (group_prototypeid, hostid, name) VALUES (50110, 99011, 'host group {#VALUE}');
INSERT INTO group_prototype (group_prototypeid, hostid, groupid) VALUES (50111, 99011, 50025);
INSERT INTO group_discovery (groupid, parent_group_prototypeid, name) VALUES (50026, 50110, 'host group {#VALUE}');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50020, 99010, 50025);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50021, 99012, 50025);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50022, 99012, 50026);
INSERT INTO host_discovery (hostid, parent_itemid, host) VALUES (99011, 58735, '');
INSERT INTO host_discovery (hostid, parent_hostid, host) VALUES (99012, 99011, '{#VALUE}');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, port) VALUES (50026, 99010, 1, 1, 1, '127.0.0.1', '10050');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, port) VALUES (50027, 99012, 1, 1, 1, '127.0.0.1', '10050');
INSERT INTO interface_discovery (interfaceid, parent_interfaceid) VALUES (50027, 50026);

-- token
INSERT INTO token (tokenid, userid, name, description) VALUES (1, 2, 'test-token-exists', '');
INSERT INTO token (tokenid, userid, name, description) VALUES (2, 5, 'test-delete-1', '');
INSERT INTO token (tokenid, userid, name, description) VALUES (3, 4, 'test-delete-1', '');
INSERT INTO token (tokenid, userid, name, description) VALUES (4, 1, 'test-delete-1', '');
INSERT INTO token (tokenid, userid, name, description) VALUES (5, 5, 'test-delete-2', '');
INSERT INTO token (tokenid, userid, name, description) VALUES (6, 2, 'test-get-1', '');
INSERT INTO token (tokenid, userid, name, description) VALUES (7, 5, 'test-get-1', '');
INSERT INTO token (tokenid, userid, name, description) VALUES (8, 4, 'test-get-1', '');
INSERT INTO token (tokenid, userid, name, description) VALUES (9, 1, 'test-get-1', '');
INSERT INTO token (tokenid, userid, name, description) VALUES (10, 5, 'test-get-2', '');
-- original token string: "a26ddc6178485b5189b103e9775763bdc01e8d19fcbe6c7dea99ae2e2d50ae1a"
INSERT INTO token (tokenid, userid, name, token, description) VALUES (11, 5, 'test-token', '6e93df66b70c69588aeabe56b77e2c6ed0c2a6854d3a79ed8156dac61ed1bce530043b3afb9699336593832552e1f72564a9991a9ae48616b5ea0c639f3f0460', '');
INSERT INTO token (tokenid, userid, name, expires_at, description) VALUES (12, 5, 'test-expires', 123, '');
INSERT INTO token (tokenid, userid, name, description) VALUES (13, 12, 'test-1', '');
INSERT INTO token (tokenid, userid, name, description) VALUES (14, 12, 'test-2', '');
INSERT INTO token (tokenid, userid, name, description) VALUES (15, 1, 'update-super-admin-1', '');
INSERT INTO token (tokenid, userid, name, description) VALUES (16, 1, 'update-super-admin-2', '');
INSERT INTO token (tokenid, userid, name, description) VALUES (17, 5, 'update-user-1', '');
INSERT INTO token (tokenid, userid, name, description) VALUES (18, 5, 'update-user-2', '');
INSERT INTO token (tokenid, userid, name, description) VALUES (19, 5, 'update-user-3', '');
INSERT INTO token (tokenid, userid, name, description) VALUES (20, 5, 'update-user-4', '');
INSERT INTO token (tokenid, userid, name, description) VALUES (21, 5, 'update-user-5', '');
INSERT INTO token (tokenid, userid, name, description) VALUES (22, 5, 'update-user-6', '');
INSERT INTO users (userid,username,passwd,roleid) VALUES (20,'token-creator','$2a$10$tskhDKjeMa8h8zRCHkVSk.CPbZg./ERPgxsuwbFFP8HVh3oIbUo42',2);
INSERT INTO users_groups (id,usrgrpid,userid) VALUES (90020,90000,20);
INSERT INTO token (tokenid, userid, creator_userid, name, description) VALUES (23, 5, 20, 'delete-user-6', '');

-- test filtering by tags
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (50027, 0, '4d58962e533a4dbf9bfd1cb247f5b698', 'Group of hosts with wide usage of tags/Hosts');
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (50028, 1, '1179640da419439a812d9b0025349ad5', 'Group of hosts with wide usage of tags/Templates');
INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (99013, 'Host OS - Windows', 'Host OS - Windows', 0, 0, '');
INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (99014, 'Host Browser - Firefox', 'Host Browser - Firefox', 0, 0, '');
INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (99015, 'Host OS - Linux', 'Host OS - Linux', 0, 0, '');
INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (99016, 'Host Browser - Chrome', 'Host Browser - Chrome', 0, 0, '');
INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (99017, 'Host without tags', 'Host without tags', 0, 0, '');
INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (99018, 'Host with very general tags only', 'Host with very general tags only', 0, 0, '');
INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (99019, 'Host OS - Android', 'Host OS - Android', 0, 0, '');
INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (99020, 'Host Browser - IE', 'Host Browser - IE', 0, 0, '');
INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (99021, 'Host OS', 'Host OS', 0, 0, '');
INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (99022, 'Host OS - Mac', 'Host OS - Mac', 0, 0, '');
INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (99023, 'Host Browser', 'Host Browser', 0, 0, '');
INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (99024, 'Template OS - Windows', 'Template OS - Windows', 3, 0, '');
INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (99025, 'Template Browser - FF', 'Template Browser - FF', 3, 0, '');
INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (99026, 'Template OS - Ubuntu Bionic Beaver', 'Template OS - Ubuntu Bionic Beaver', 3, 0, '');
INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (99027, 'Workstation', 'Workstation', 3, 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50023, 99013, 50027);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50024, 99014, 50027);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50025, 99015, 50027);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50026, 99016, 50027);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50027, 99017, 50027);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50028, 99018, 50027);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50029, 99019, 50027);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50030, 99020, 50027);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50031, 99021, 50027);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50032, 99022, 50027);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50033, 99023, 50027);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50034, 99024, 50028);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50035, 99025, 50028);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50036, 99026, 50028);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50037, 99027, 50028);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (50004, 99013, 99024);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (50005, 99014, 99025);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (50006, 99015, 99026);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (50007, 99024, 99027);
INSERT INTO host_tag (hosttagid, hostid, tag, value) VALUES (1000, 99013, 'OS', 'Windows');
INSERT INTO host_tag (hosttagid, hostid, tag, value) VALUES (1001, 99014, 'Browser', 'Firefox');
INSERT INTO host_tag (hosttagid, hostid, tag, value) VALUES (1002, 99015, 'OS', 'Linux');
INSERT INTO host_tag (hosttagid, hostid, tag, value) VALUES (1003, 99016, 'Browser', 'Chrome');
INSERT INTO host_tag (hosttagid, hostid, tag, value) VALUES (1004, 99018, 'Other', '');
INSERT INTO host_tag (hosttagid, hostid, tag, value) VALUES (1005, 99019, 'OS', 'Android');
INSERT INTO host_tag (hosttagid, hostid, tag, value) VALUES (1006, 99020, 'Browser', 'IE');
INSERT INTO host_tag (hosttagid, hostid, tag, value) VALUES (1007, 99021, 'OS', '');
INSERT INTO host_tag (hosttagid, hostid, tag, value) VALUES (1008, 99022, 'OS', 'Mac');
INSERT INTO host_tag (hosttagid, hostid, tag, value) VALUES (1009, 99023, 'Browser', '');
INSERT INTO host_tag (hosttagid, hostid, tag, value) VALUES (1010, 99024, 'OS', 'Win7');
INSERT INTO host_tag (hosttagid, hostid, tag, value) VALUES (1011, 99025, 'Browser', 'FF');
INSERT INTO host_tag (hosttagid, hostid, tag, value) VALUES (1012, 99026, 'OS', 'Ubuntu Bionic Beaver');
INSERT INTO host_tag (hosttagid, hostid, tag, value) VALUES (1013, 99025, 'Webbrowser', 'Mozilla');
INSERT INTO host_tag (hosttagid, hostid, tag, value) VALUES (1014, 99027, 'office', 'Riga');
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history, status, params, description, posts, headers) VALUES (58736, 99013, NULL, 2, 3, 'Item', 'item', 0, 90, 0, '', '', '', '');
INSERT INTO triggers (triggerid, description, expression, comments, value) VALUES (50172, 'trigger1', '{50232}=1', '', '1');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50232, 50172, 58736, 'last', '$');
INSERT INTO trigger_tag (triggertagid, triggerid, tag, value) VALUES (10001, 50172, 'tag1', 'value1');
INSERT INTO trigger_tag (triggertagid, triggerid, tag, value) VALUES (10002, 50172, 'tag2', '');
INSERT INTO trigger_tag (triggertagid, triggerid, tag, value) VALUES (10003, 50172, 'tag3', 'value3');
INSERT INTO trigger_tag (triggertagid, triggerid, tag, value) VALUES (10004, 50172, 'tag3', 'value4');
INSERT INTO triggers (triggerid, description, expression, comments, value) VALUES (50173, 'trigger2', '{50233}=1', '', '1');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50233, 50173, 58736, 'last', '$');
INSERT INTO trigger_tag (triggertagid, triggerid, tag, value) VALUES (10005, 50173, 'tag1', 'value5');
INSERT INTO trigger_tag (triggertagid, triggerid, tag, value) VALUES (10006, 50173, 'tag2', 'value6');
INSERT INTO triggers (triggerid, description, expression, comments, value) VALUES (50174, 'trigger3', '{50234}=1', '', '1');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50234, 50174, 58736, 'last', '$');
INSERT INTO trigger_tag (triggertagid, triggerid, tag, value) VALUES (10007, 50174, 'tag1', 'value7');
INSERT INTO triggers (triggerid, description, expression, comments, value) VALUES (50175, 'trigger4', '{50235}=1', '', '1');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50235, 50175, 58736, 'last', '$');
INSERT INTO events (eventid, source, object, objectid, clock, value, acknowledged, ns, name, severity) VALUES (5000, 0, 0, 50172, 1610000000, 1, 0, 0, 'trigger1', 0);
INSERT INTO event_tag (eventtagid, eventid, tag, value) VALUES (1000, 5000, 'tag1', 'value1');
INSERT INTO event_tag (eventtagid, eventid, tag, value) VALUES (1001, 5000, 'tag2', '');
INSERT INTO event_tag (eventtagid, eventid, tag, value) VALUES (1002, 5000, 'tag3', 'value3');
INSERT INTO event_tag (eventtagid, eventid, tag, value) VALUES (1003, 5000, 'tag3', 'value4');
INSERT INTO event_tag (eventtagid, eventid, tag, value) VALUES (1004, 5000, 'OS', 'Windows');
INSERT INTO event_tag (eventtagid, eventid, tag, value) VALUES (1005, 5000, 'OS', 'Win7');
INSERT INTO problem (eventid, source, object, objectid, clock, ns, r_eventid, r_clock, r_ns, correlationid, userid, name, acknowledged, severity) VALUES (5000, 0, 0, 50172, 1610000000, 0, NULL, 0, 0, NULL, NULL, 'trigger1', 0, 0);
INSERT INTO problem_tag (problemtagid, eventid, tag, value) VALUES (1000, 5000, 'tag1', 'value1');
INSERT INTO problem_tag (problemtagid, eventid, tag, value) VALUES (1001, 5000, 'tag2', '');
INSERT INTO problem_tag (problemtagid, eventid, tag, value) VALUES (1002, 5000, 'tag3', 'value3');
INSERT INTO problem_tag (problemtagid, eventid, tag, value) VALUES (1003, 5000, 'tag3', 'value4');
INSERT INTO problem_tag (problemtagid, eventid, tag, value) VALUES (1004, 5000, 'OS', 'Windows');
INSERT INTO problem_tag (problemtagid, eventid, tag, value) VALUES (1005, 5000, 'OS', 'Win7');
INSERT INTO events (eventid, source, object, objectid, clock, value, acknowledged, ns, name, severity) VALUES (5001, 0, 0, 50173, 1610000000, 1, 0, 0, 'trigger2', 0);
INSERT INTO event_tag (eventtagid, eventid, tag, value) VALUES (1006, 5001, 'tag1', 'value5');
INSERT INTO event_tag (eventtagid, eventid, tag, value) VALUES (1007, 5001, 'tag2', 'value6');
INSERT INTO event_tag (eventtagid, eventid, tag, value) VALUES (1010, 5001, 'OS', 'Windows');
INSERT INTO event_tag (eventtagid, eventid, tag, value) VALUES (1011, 5001, 'OS', 'Win7');
INSERT INTO problem (eventid, source, object, objectid, clock, ns, r_eventid, r_clock, r_ns, correlationid, userid, name, acknowledged, severity) VALUES (5001, 0, 0, 50173, 1610000000, 0, NULL, 0, 0, NULL, NULL, 'trigger2', 0, 0);
INSERT INTO problem_tag (problemtagid, eventid, tag, value) VALUES (1006, 5001, 'tag1', 'value5');
INSERT INTO problem_tag (problemtagid, eventid, tag, value) VALUES (1007, 5001, 'tag2', 'value6');
INSERT INTO problem_tag (problemtagid, eventid, tag, value) VALUES (1010, 5001, 'OS', 'Windows');
INSERT INTO problem_tag (problemtagid, eventid, tag, value) VALUES (1011, 5001, 'OS', 'Win7');
INSERT INTO events (eventid, source, object, objectid, clock, value, acknowledged, ns, name, severity) VALUES (5002, 0, 0, 50174, 1610000000, 1, 0, 0, 'trigger3', 0);
INSERT INTO event_tag (eventtagid, eventid, tag, value) VALUES (1012, 5002, 'tag1', 'value7');
INSERT INTO event_tag (eventtagid, eventid, tag, value) VALUES (1013, 5002, 'OS', 'Windows');
INSERT INTO event_tag (eventtagid, eventid, tag, value) VALUES (1014, 5002, 'OS', 'Win7');
INSERT INTO problem (eventid, source, object, objectid, clock, ns, r_eventid, r_clock, r_ns, correlationid, userid, name, acknowledged, severity) VALUES (5002, 0, 0, 50174, 1610000000, 0, NULL, 0, 0, NULL, NULL, 'trigger3', 0, 0);
INSERT INTO problem_tag (problemtagid, eventid, tag, value) VALUES (1012, 5002, 'tag1', 'value7');
INSERT INTO problem_tag (problemtagid, eventid, tag, value) VALUES (1013, 5002, 'OS', 'Windows');
INSERT INTO problem_tag (problemtagid, eventid, tag, value) VALUES (1014, 5002, 'OS', 'Win7');
INSERT INTO events (eventid, source, object, objectid, clock, value, acknowledged, ns, name, severity) VALUES (5003, 0, 0, 50175, 1610000000, 1, 0, 0, 'trigger4', 0);
INSERT INTO event_tag (eventtagid, eventid, tag, value) VALUES (1015, 5003, 'OS', 'Windows');
INSERT INTO event_tag (eventtagid, eventid, tag, value) VALUES (1016, 5003, 'OS', 'Win7');
INSERT INTO problem (eventid, source, object, objectid, clock, ns, r_eventid, r_clock, r_ns, correlationid, userid, name, acknowledged, severity) VALUES (5003, 0, 0, 50175, 1610000000, 0, NULL, 0, 0, NULL, NULL, 'trigger4', 0, 0);
INSERT INTO problem_tag (problemtagid, eventid, tag, value) VALUES (1015, 5003, 'OS', 'Windows');
INSERT INTO problem_tag (problemtagid, eventid, tag, value) VALUES (1016, 5003, 'OS', 'Win7');

-- test trigger validation
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99028, 'Trigger validation test host', 'Trigger validation test host', 0, '');
INSERT INTO hosts (hostid, host, name, status, description) VALUES (99029, 'Trigger validation test template', 'Trigger validation test template', 3, '');
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (50029, 0, '7466f5cb569f48e89eef772c6e4baacf', 'Trigger validation test host group');
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (50030, 1, '38da8a76c4a742479292151c2e404dae', 'Trigger validation test host group');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50038, 99028, 50029);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50039, 99029, 50030);
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history, status, params, description, posts, headers) VALUES (58737, 99028, NULL, 2, 3, 'item', 'item', '1d', '90d', 0, '', '', '', '');
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history, status, params, description, posts, headers) VALUES (58738, 99029, NULL, 2, 3, 'item', 'item', '1d', '90d', 0, '', '', '', '');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50176, 'test-trigger-1', '{50236}=0', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50236, 50176, 58737, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50177, 'test-trigger-2', '{50237}=0', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50237, 50177, 58737, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50178, 'template-trigger', '{50238}=0', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50238, 50178, 58738, 'last', '$');

-- services
INSERT INTO services (serviceid, name, description) VALUES (1, 'API Service for delete', '');
INSERT INTO services (serviceid, name, description) VALUES (2, 'API Service for update', '');

-- high availability nodes
INSERT INTO ha_node (name,address,port,status,ha_nodeid) VALUES ('node1','192.168.1.5','10051','0','ckuo7i1nv00090sajelcon0su');
INSERT INTO ha_node (name,address,port,status,ha_nodeid) VALUES ('node2','192.168.1.6','10051','0','ckuo7i1nv000a0saj1fcdkeu4');
INSERT INTO ha_node (name,address,port,status,ha_nodeid) VALUES ('node3','192.168.1.7','10052','0','ckuo7i1nv000b0saj3j8hxm2b');
INSERT INTO ha_node (name,address,port,status,ha_nodeid) VALUES ('node4','192.168.1.8','10052','1','ckuo7i1nv000c0sajz85xcrtt');
INSERT INTO ha_node (name,address,port,status,ha_nodeid) VALUES ('node5','192.168.1.9','10053','1','ckuo7i1nv000d0sajd95y1b6x');
INSERT INTO ha_node (name,address,port,status,ha_nodeid) VALUES ('node6','192.168.1.10','10053','2','ckuo7i1nw000e0sajwfttc1mp');
INSERT INTO ha_node (name,address,port,status,ha_nodeid) VALUES ('node7','192.168.1.11','10053','2','ckuo7i1nw000f0sajtzv1c6v3');
INSERT INTO ha_node (name,address,port,status,ha_nodeid) VALUES ('node8','192.168.1.12','10051','1','ckuo7i1nw000g0sajjsjre7e3');
INSERT INTO ha_node (name,address,port,status,ha_nodeid) VALUES ('node-active','192.168.1.13','10051','3','ckuo7i1nw000h0sajj3l3hh8u');
