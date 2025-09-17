-- Test data for API tests

-- Activate "Zabbix Server" host
UPDATE hosts SET status=0 WHERE host='Zabbix server';

-- host groups
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (50009, 'API Host', 'API Host', 0, '', '');
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (50010, 'API Template', 'API Template', 3, '', '');
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (50012, 'API Host for read permissions', 'API Host for read permissions', 0, '', '');
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (50013, 'API disabled host', 'API disabled host', 1, '', '');
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (50014, 'API Host for deny permissions', 'API Host for deny permissions', 0, '', '');
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (90020, '90020', '90020', 0, '', '');
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (90021, '90021', '90021', 0, '', '');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (50022,50009,1,1,1,'127.0.0.1','','10050');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (50023,50012,1,1,1,'127.0.0.1','','10050');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (50024,50013,1,1,1,'127.0.0.1','','10050');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (50025,50014,1,1,1,'127.0.0.1','','10050');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (50029,50009,1,2,1,'127.0.0.1','','161');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (50030,50009,1,4,1,'127.0.0.1','','12345');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (50031,50009,1,3,1,'127.0.0.1','','623');
INSERT INTO interface_snmp (interfaceid, version, bulk, community, securityname, securitylevel, authpassphrase, privpassphrase, authprotocol, privprotocol, contextname) VALUES (50029, 2, 1, '{$SNMP_COMMUNITY}', '', 0, '', '', 0, 0, '');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (50012,0,'34a832fc9add475290d1655a012b20ee','API group for hosts');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (50013,1,'df60e37bb99849a9817e9805c4496cae','API group for templates');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (50016,0,'2e684a6d9f22417d8d2ef286c9f86e97','API group with read permissions');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (50017,0,'1d53a0938db34c5f8e5116487e620477','API group with deny permissions');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (90020,0,'96258844beaf4c1f9528ca96b32f24de','90000Eur');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (90021,0,'53f820730464462ea00d258d71359947','90000Eur/LV');
INSERT INTO usrgrp (usrgrpid,name) VALUES (90000,'90000 Eur group write except one');
INSERT INTO users (userid,username,passwd,roleid) VALUES (90000,'90000','$2a$10$Hr7Z1FX/x9OPhdUu9.5CL.XyL9IKPiVcoxJgGbtIHc3.Svk/awB5q',2);
INSERT INTO users_groups (id,usrgrpid,userid) VALUES (90000,90000,90000);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50009, 50009, 50012);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50011, 50010, 50013);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50012, 50012, 50016);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50014, 50014, 50017);
INSERT INTO hosts_groups (hostid,groupid,hostgroupid) VALUES (90020,90020,90020);
INSERT INTO hosts_groups (hostid,groupid,hostgroupid) VALUES (90021,90021,90021);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (50003, 50009, 50010);
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,query_fields,flags,posts,headers) VALUES (400660, 50009, 50022, 0, 2,'API discovery rule','vfs.fs.discovery',30,90,0,'','','',1,'','');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (50005,0,'c5ed6d1365b145c5b4f522832909b22e','API host group for update');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50010, 50009, 50005);
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (50006,0,'9cd829bbd9e94619a15694489aacb1cc','API host group for update internal');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (50007,0,'395e5c5d29cf4ffcbc07b1a649b64a1c','API host group delete internal');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (50008,0,'2ceb313566944cb3ab0ab2106b99f01c','API host group delete');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (50009,0,'f04424f8880d4bc8a3b3075d1e246224','API host group delete2');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (50010,0,'7a6aac19b9dc42a1afb7582a8e3d4283','API host group delete3');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (50011,0,'a93383e7641547fe9572f36e6937f9fb','API host group delete4');
-- discovered host groups
INSERT INTO hosts (hostid, host, name, status, flags, description, readme) VALUES (50011, 'API host prototype {#FSNAME}', 'API host prototype {#FSNAME}', 0, 2, '', '');
INSERT INTO hstgrp (groupid,type,uuid,name) VALUES (50014,0,'31e39dc724624c97b2e93caba8517465','API group for host prototype');
INSERT INTO host_discovery (hostid,parent_hostid,lldruleid) VALUES (50011,NULL,400660);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (50108, 50011, 'API discovery group {#HV.NAME}', NULL, NULL);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (50109, 50011, '', 50014, NULL);
INSERT INTO hstgrp (groupid,type,uuid,name,flags) VALUES (50015,0,'de6322aebbae4f53acfc87747da877d8','API discovery group {#HV.NAME}',4);
INSERT INTO group_discovery (groupdiscoveryid, groupid, parent_group_prototypeid, name) VALUES (1, 50015, 50108, 'API discovery group {#HV.NAME}');
-- host prototype for delete
INSERT INTO hosts (hostid, host, name, status, flags, description, custom_interfaces, readme) VALUES (50015, 'API host prototype for delete {#FSNAME}', 'API host prototype for delete {#FSNAME}', 0, 2, '', 1, '');
INSERT INTO host_discovery (hostid,parent_hostid,lldruleid) VALUES (50015,NULL,400660);
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
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (50020, 'API Template 2', 'API Template 2', 3, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (55001, 50020, 52005);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (55002, 50020, 52006);

-- user group
INSERT INTO usrgrp (usrgrpid, name) VALUES (14, 'API user group for update with user and rights');
INSERT INTO usrgrp (usrgrpid, name) VALUES (16, 'API user group delete');
INSERT INTO usrgrp (usrgrpid, name) VALUES (17, 'API user group delete1');
INSERT INTO usrgrp (usrgrpid, name) VALUES (18, 'API user group delete2');
INSERT INTO usrgrp (usrgrpid, name) VALUES (19, 'API user group delete3');
INSERT INTO usrgrp (usrgrpid, name) VALUES (20, 'API user group in actions');
INSERT INTO usrgrp (usrgrpid, name) VALUES (21, 'API user group in scripts');
INSERT INTO usrgrp (usrgrpid, name) VALUES (22, 'API user group in configuration');
INSERT INTO usrgrp (usrgrpid, name) VALUES (23, 'API user group for update');
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (4, 'zabbix-admin', '$2a$10$PmEcvov/w84R3sShOV4rX.xJd81bwgaK4o0SfoiSxop2ol7PPGsOi', 0, 0, 'en_US', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (5, 'zabbix-user', '$2a$10$w8oiYEgP3Fy4XuPIE5VCiO2j5snJEopKfTCYa3DC7bNL83ldKlPRS', 0, 0, 'en_US', '30s', 1, 'default', 0, 0, 50);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (7, 'user-in-two-groups', '$2a$10$GiBCQXAPeTCPR9rEQ/YodOmE7mqvXjYwbEkZLGP7iWU/fzKcB9yF6', 0, 0, 'en_US', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (8, 'api-user', '$2a$10$NyZQvuelvUVqpCDYb7cOy.pEewNe9U0MK0ZIdjJeupYbgHU6G7Iea', 0, 0, 'en_US', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (7, 8, 4);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (8, 14, 4);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (10, 16, 7);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (11, 17, 7);
INSERT INTO rights (rightid, groupid, permission, id) VALUES (2, 14, 3, 50012);
INSERT INTO rights (rightid, groupid, permission, id) VALUES (3, 14, 2, 50016);
INSERT INTO rights (rightid, groupid, permission, id) VALUES (4, 14, 0, 50017);
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period) VALUES (16, 'API action', 0, 0, 0, 60);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (31, 16, 0, 0, 1, 1, 0);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (31, 0, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', NULL);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (21, 31, 20);
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description, confirmation, type, execute_on, timeout, scope, port, authtype, username, password, publickey, privatekey, menu_path) VALUES (4, 'API script', 'test', 2, 21, NULL, 'api script description', '', 0, 2, '30s', 1, '', 0, '', '', '', '', '');
UPDATE settings SET value_usrgrpid = 22 WHERE name = 'alert_usrgrpid';

-- users
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (9, 'api-user-for-update', '$2a$10$dP76CSji4ozQxSxLQeUGc.sJgSPuwN8b4pjnKIoOeQXts2Wm86ige', 0, '15m', 'en_US', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (10, 'api-user-delete', '$2a$10$8ioYyO/Xkyhx64W.z0B3YONQ7.s2zqMRqhkYt/z6S9.MkqEYsWCOq', 0, '15m', 'en_US', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (11, 'api-user-delete1', '$2a$10$NU0MhxghxIbvCen5pBY.WuC9eYpqYS2mE8P6dQIMC00yhlalXhUWO', 0, '15m', 'en_US', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (12, 'api-user-delete2', '$2a$10$t.cDXioxmkgwEigzPU0aQejc8rAfjt6ZxY6WIllrN0IpEH4pp3I/K', 0, '15m', 'en_US', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (13, 'api-user-action', '$2a$10$w6u3jruB673s5A/Qrg7VZOFof/yuARrPQYpZk7xbSTw7O/wgSw9Sq', 0, '15m', 'en_US', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (14, 'api-user-map', '$2a$10$1uCgmg.SoVtN98NTt/815./E/mFIdJH2r3aF1RFY1QwmFVlnbCXTK', 0, '15m', 'en_US', '30s', 2, 'default', 0, 0, 50);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (15, 'api-user-for-unblock', '$2a$10$/a5lFsoEm56b01q1uAoM8ecSmazNhrYbidYeBibtRzUxbIgmIAvR.', 0, '15m', 'en_US', '30s', 2, 'default', 5, 0, 50);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (16, 'api-user-for-password-super-admin', '$2y$10$uh530zmzcd.PIsFjGTOkTuMsfdBAYwco219gbuwoX8ZJXNoTRJKva', 0, '15m', 'en_US', '30s', 3, 'default', 0, 0, 50);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (17, 'api-user-for-password-user', '$2y$10$qcklx4y/EpBt2nYNKOafq.69J7kwdyNhoh9WHdlA9zOhZmS2Im.9.', 0, '15m', 'en_US', '30s', 1, 'default', 0, 0, 50);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (12, 14, 9);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (13, 14, 10);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (14, 14, 11);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (15, 14, 12);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (16, 9, 13);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (17, 14, 14);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (20, 14, 5);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (21, 23, 15);
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period) VALUES (17, 'API action with user', 0, 0, 0, 60);
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (32, 17, 0, 0, 1, 1, 0);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (32, 0, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', NULL);
INSERT INTO opmessage_usr (opmessage_usrid, operationid, userid) VALUES (4, 32, 13);
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, userid, private) VALUES (6, 'API map', 800, 600, NULL, 0, 0, 1, 1, 1, 2, 14, 0);

-- valuemap
INSERT INTO valuemap (valuemapid,hostid,name) VALUES (5399,50009,'API value map for update');
INSERT INTO valuemap (valuemapid,hostid,name) VALUES (5400,50009,'API value map for update with mappings');
INSERT INTO valuemap (valuemapid,hostid,name) VALUES (5401,50009,'API value map delete');
INSERT INTO valuemap (valuemapid,hostid,name) VALUES (5402,50009,'API value map delete2');
INSERT INTO valuemap (valuemapid,hostid,name) VALUES (5403,50009,'API value map delete3');
INSERT INTO valuemap (valuemapid,hostid,name) VALUES (5404,50009,'API value map delete4');
INSERT INTO valuemap (valuemapid,hostid,name) VALUES (5405,50009,'API value duplicate');
INSERT INTO valuemap_mapping (valuemap_mappingid,valuemapid,value,newvalue) VALUES (99040,5399,'1','Unknown');
INSERT INTO valuemap_mapping (valuemap_mappingid,valuemapid,value,newvalue) VALUES (99050,5400,'One','Online');
INSERT INTO valuemap_mapping (valuemap_mappingid,valuemapid,value,newvalue) VALUES (99060,5400,'Two','Offline');
INSERT INTO valuemap_mapping (valuemap_mappingid,valuemapid,value,newvalue) VALUES (99070,5401,'1','Unknown');
INSERT INTO valuemap_mapping (valuemap_mappingid,valuemapid,value,newvalue) VALUES (99080,5402,'Three','Other');
INSERT INTO valuemap_mapping (valuemap_mappingid,valuemapid,value,newvalue) VALUES (99090,5403,'Four','Unknown');
INSERT INTO valuemap_mapping (valuemap_mappingid,valuemapid,value,newvalue) VALUES (99100,5404,'1','Unknown');
INSERT INTO valuemap_mapping (valuemap_mappingid,valuemapid,value,newvalue) VALUES (99110,5405,'1','Unknown');

-- global macro
INSERT INTO globalmacro (globalmacroid, macro, value, description) VALUES (13,'{$API_MACRO_FOR_UPDATE1}','update','desc');
INSERT INTO globalmacro (globalmacroid, macro, value, description) VALUES (14,'{$API_MACRO_FOR_UPDATE2}','update','');
INSERT INTO globalmacro (globalmacroid, macro, value, description) VALUES (15,'{$API_MACRO_FOR_DELETE}','abc','');
INSERT INTO globalmacro (globalmacroid, macro, value, description) VALUES (16,'{$API_MACRO_FOR_DELETE1}','1','');
INSERT INTO globalmacro (globalmacroid, macro, value, description) VALUES (17,'{$API_MACRO_FOR_DELETE2}','2','');

-- host macro
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (1,90020,'{$HOST_MACRO_1}','value','description');
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (2,90020,'{$HOST_MACRO_2}','value','');

-- host macro config
insert into hstgrp (groupid,type,uuid,name) values (140000,1,'4a3eacc0c4c2ed517aded53241e7630d ','Template group for macro config testing');
insert into hosts (hostid,host,name,status,description, wizard_ready, readme) values (140000,'macro config tmpl test noconf','macro config tmpl test noconf',3,'', 0, '');
insert into hosts (hostid,host,name,status,description, wizard_ready, readme) values (140001,'macro config tmpl test text update 1','macro config tmpl test text update 1',3,'', 0, '');
insert into hosts (hostid,host,name,status,description, wizard_ready, readme) values (140002,'macro config tmpl test text update 2','macro config tmpl test text update 2',3,'', 0, '');
insert into hosts (hostid,host,name,status,description, wizard_ready, readme) values (140003,'macro config tmpl test list update 1','macro config tmpl test list update 1',3,'', 0, '');
insert into hosts (hostid,host,name,status,description, wizard_ready, readme) values (140004,'macro config tmpl test list update 2','macro config tmpl test list update 2',3,'', 0, '');
insert into hosts (hostid,host,name,status,description, wizard_ready, readme) values (140005,'macro config tmpl test checkbox update 1','macro config tmpl test checkbox update 1',3,'', 0, '');
insert into hosts (hostid,host,name,status,description, wizard_ready, readme) values (140006,'macro config tmpl test checkbox update 2','macro config tmpl test checkbox update 2',3,'', 0, '');
insert into hosts (hostid,host,name,status,description, wizard_ready, readme) values (140007,'macro config tmpl test any delete','macro config tmpl test any delete',3,'', 0, '');
insert into hosts (hostid,host,name,status,description, wizard_ready, readme) values (140008,'macro config tmpl test priority','macro config tmpl test priority',3,'', 0, '');
insert into hosts_groups (hostgroupid, hostid, groupid) values (140000, 140000, 140000);
insert into hosts_groups (hostgroupid, hostid, groupid) values (140001, 140001, 140000);
insert into hosts_groups (hostgroupid, hostid, groupid) values (140002, 140002, 140000);
insert into hosts_groups (hostgroupid, hostid, groupid) values (140003, 140003, 140000);
insert into hosts_groups (hostgroupid, hostid, groupid) values (140004, 140004, 140000);
insert into hosts_groups (hostgroupid, hostid, groupid) values (140005, 140005, 140000);
insert into hosts_groups (hostgroupid, hostid, groupid) values (140006, 140006, 140000);
insert into hosts_groups (hostgroupid, hostid, groupid) values (140007, 140007, 140000);
insert into hosts_groups (hostgroupid, hostid, groupid) values (140008, 140008, 140000);
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (10000,140000,'{$TMPL_MACRO_1}','value_1','description_1');
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (10001,140001,'{$TMPL_MACRO_2}','value_2','description_2');
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (10002,140002,'{$TMPL_MACRO_3}','value_3','description_3');
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (10003,140003,'{$TMPL_MACRO_4}','value_4','description_4');
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (10004,140004,'{$TMPL_MACRO_5}','value_5','description_5');
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (10005,140005,'{$TMPL_MACRO_6}','value_6','description_6');
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (10006,140006,'{$TMPL_MACRO_7}','value_7','description_7');
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (10007,140007,'{$TMPL_MACRO_8}','value_8','description_8');
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (10008,140008,'{$TMPL_MACRO_8}','value_8','description_8');
INSERT INTO hostmacro (hostmacroid, hostid, macro, value, description) VALUES (10009,140008,'{$TMPL_MACRO_9}','value_9','description_9');
INSERT INTO hostmacro_config (hostmacroid, type, priority, section_name, label, description, required, regex, options) VALUES (10001,1,0,'','label_1','',0,'','');
INSERT INTO hostmacro_config (hostmacroid, type, priority, section_name, label, description, required, regex, options) VALUES (10002,1,0,'','label_2','description_2',1,'/^[a-zA-Z0-9]*$/','');
INSERT INTO hostmacro_config (hostmacroid, type, priority, section_name, label, description, required, regex, options) VALUES (10003,2,0,'','label_3','',0,'','[{"value":"option1","text":"Option 1"},{"value":"option2","text":"Option 2"},{"value":"","text":"Option 3"}]');
INSERT INTO hostmacro_config (hostmacroid, type, priority, section_name, label, description, required, regex, options) VALUES (10004,2,0,'','label_4','description_4',1,'','[{"value":"option1","text":"Option 1"}]');
INSERT INTO hostmacro_config (hostmacroid, type, priority, section_name, label, description, required, regex, options) VALUES (10005,3,0,'','label_5','',0,'','[{"checked":"1","unchecked":"0"}]');
INSERT INTO hostmacro_config (hostmacroid, type, priority, section_name, label, description, required, regex, options) VALUES (10006,3,0,'','label_6','description_6',0,'','[{"checked":"option1","unchecked":"option2"}]');
INSERT INTO hostmacro_config (hostmacroid, type, priority, section_name, label, description, required, regex, options) VALUES (10007,2,0,'','label_7','description_7',1,'','[{"value":"option1","text":"Option 1"}]');
INSERT INTO hostmacro_config (hostmacroid, type, priority, section_name, label, description, required, regex, options) VALUES (10008,1,0,'','label_8','description_8',0,'','');
INSERT INTO hostmacro_config (hostmacroid, type, priority, section_name, label, description, required, regex, options) VALUES (10009,1,0,'','label_9','description_9',0,'','');

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
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,query_fields,flags,posts,headers) VALUES (150143, 50009, 1, 9, 0,'Download speed for scenario "Api templated step".','web.test.in[Webtest key_name,,bps]','2m','30d',0,'','','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,query_fields,flags,posts,headers) VALUES (150144, 50009, 1, 9, 0,'Download speed for scenario "Api templated web scenario".','web.test.in[Webtest key_name,,bps]','2m','30d',0,'','','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,query_fields,flags,posts,headers) VALUES (150145, 50009, 1, 9, 0,'Download speed for scenario "Api step for delete2".','web.test.in[Webtest key_name,,bps]','2m','30d',0,'','','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,query_fields,flags,posts,headers) VALUES (150146, 50009, 1, 9, 0,'Download speed for scenario "Api web scenario for delete2".','web.test.in[Webtest key_name,,bps]','2m','30d',0,'','','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,query_fields,flags,posts,headers) VALUES (150147, 50009, 1, 9, 0,'Download speed for scenario "Api step for delete1".','web.test.in[Webtest key_name,,bps]','2m','30d',0,'','','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,query_fields,flags,posts,headers) VALUES (150148, 50009, 1, 9, 0,'Download speed for scenario "Api web scenario for delete1".','web.test.in[Webtest key_name,,bps]','2m','30d',0,'','','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,query_fields,flags,posts,headers) VALUES (150149, 50009, 1, 9, 0,'Download speed for scenario "Api step for delete0".','web.test.in[Webtest key_name,,bps]','2m','30d',0,'','','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,query_fields,flags,posts,headers) VALUES (150150, 50009, 1, 9, 0,'Download speed for scenario "Api web scenario for delete0".','web.test.in[Webtest key_name,,bps]','2m','30d',0,'','','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,query_fields,flags,posts,headers) VALUES (150151, 50009, 1, 9, 0,'Download speed for scenario "Webtest key_name".','web.test.in[Webtest key_name,,bps]','2m','30d',0,'','','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,query_fields,flags,posts,headers) VALUES (150152, 50009, 1, 9, 3,'Failed step of scenario "Webtest key_name".','web.test.fail[Webtest key_name]','2m','30d',0,'','','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,query_fields,flags,posts,headers) VALUES (150153, 50009, 1, 9, 1,'Last error message of scenario "Webtest key_name".','web.test.error[Webtest key_name]','2m','30d',0,'','','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,query_fields,flags,posts,headers) VALUES (150154, 50009, 1, 9, 0,'Download speed for step "Webstep name 1" of scenario "Webtest key_name".','web.test.in[Webtest key_name,Webstep name 1,bps]','1m','30d',0,'','','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,query_fields,flags,posts,headers) VALUES (150155, 50009, 1, 9, 0,'Response time for step "Webstep name 1" of scenario "Webtest key_name".','web.test.time[Webtest key_name,Webstep name 1,resp]','1m','30d',0,'','','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,query_fields,flags,posts,headers) VALUES (150156, 50009, 1, 9, 3,'Response code for step "Webstep name 1" of scenario "Webtest key_name".','web.test.rspcode[Webtest key_name,Webstep name 1]','1m','30d',0,'','','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,query_fields,flags,posts,headers) VALUES (150157, 50009, 1, 9, 0,'Download speed for step "Webstep name 2" of scenario "Webtest key_name".','web.test.in[Webtest key_name,Webstep name 2,bps]','1m','30d',0,'','','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,query_fields,flags,posts,headers) VALUES (150158, 50009, 1, 9, 0,'Response time for step "Webstep name 2" of scenario "Webtest key_name".','web.test.time[Webtest key_name,Webstep name 2,resp]','1m','30d',0,'','','',0,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,query_fields,flags,posts,headers) VALUES (150159, 50009, 1, 9, 3,'Response code for step "Webstep name 2" of scenario "Webtest key_name".','web.test.rspcode[Webtest key_name,Webstep name 2]','1m','30d',0,'','','',0,'','');
INSERT INTO httpstepitem (httpstepitemid, httpstepid, itemid, type) VALUES (150143, 15010, 150143, 2);
INSERT INTO httptestitem (httptestitemid, httptestid, itemid, type) VALUES (150144, 15010, 150144, 4);
INSERT INTO httpstepitem (httpstepitemid, httpstepid, itemid, type) VALUES (150145, 15005, 150145, 2);
INSERT INTO httptestitem (httptestitemid, httptestid, itemid, type) VALUES (150146, 15005, 150146, 4);
INSERT INTO httpstepitem (httpstepitemid, httpstepid, itemid, type) VALUES (150147, 15004, 150147, 2);
INSERT INTO httptestitem (httptestitemid, httptestid, itemid, type) VALUES (150148, 15004, 150148, 4);
INSERT INTO httpstepitem (httpstepitemid, httpstepid, itemid, type) VALUES (150149, 15003, 150149, 2);
INSERT INTO httptestitem (httptestitemid, httptestid, itemid, type) VALUES (150150, 15003, 150150, 4);
INSERT INTO httptestitem (httptestitemid, httptestid, itemid, type) VALUES (150151, 15015, 150151, 2);
INSERT INTO httptestitem (httptestitemid, httptestid, itemid, type) VALUES (150152, 15015, 150152, 3);
INSERT INTO httptestitem (httptestitemid, httptestid, itemid, type) VALUES (150153, 15015, 150153, 4);
INSERT INTO httpstepitem (httpstepitemid, httpstepid, itemid, type) VALUES (150154, 15015, 150154, 2);
INSERT INTO httpstepitem (httpstepitemid, httpstepid, itemid, type) VALUES (150155, 15015, 150155, 1);
INSERT INTO httpstepitem (httpstepitemid, httpstepid, itemid, type) VALUES (150156, 15015, 150156, 0);
INSERT INTO httpstepitem (httpstepitemid, httpstepid, itemid, type) VALUES (150157, 15016, 150157, 2);
INSERT INTO httpstepitem (httpstepitemid, httpstepid, itemid, type) VALUES (150158, 15016, 150158, 1);
INSERT INTO httpstepitem (httpstepitemid, httpstepid, itemid, type) VALUES (150159, 15016, 150159, 0);

-- sysmaps
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, userid, private) VALUES (10001, 'A', 800, 600, NULL, 0, 0, 1, 1, 1, 2, 1, 0);
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, userid, private) VALUES (10002, 'B', 800, 600, NULL, 0, 0, 1, 1, 1, 2, 1, 1);
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, userid, private) VALUES (10003, 'C', 800, 600, NULL, 0, 0, 1, 1, 1, 2, 1, 0);
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, userid, private) VALUES (10004, 'D', 800, 600, NULL, 0, 0, 1, 1, 1, 2, 1, 0);
INSERT INTO sysmap_user (sysmapuserid, sysmapid, userid, permission) VALUES (1, 10001, 5, 3);
INSERT INTO sysmap_user (sysmapuserid, sysmapid, userid, permission) VALUES (2, 10003, 5, 3);
INSERT INTO sysmap_user (sysmapuserid, sysmapid, userid, permission) VALUES (3, 10004, 5, 3);
INSERT INTO sysmaps_elements (selementid, sysmapid, elementid, elementtype, iconid_off, iconid_on, label, label_location, x, y, iconid_disabled, iconid_maintenance, elementsubtype, areatype, width, height, viewtype, use_iconmap) VALUES (7, 10001, 0, 4, 151, NULL, 'New element', -1, 189, 77, NULL, NULL, 0, 0, 200, 200, 0, 1);

-- interfaces
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (99004,10084,1,2,1,'127.0.0.1','','161');
INSERT INTO interface_snmp (interfaceid, version, bulk, community) values (99004, 2, 1, '{$SNMP_COMMUNITY}');

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

-- testHostGroup_Delete maintenance constraint
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (62002, 0, 'f40b2a0aa36d404d8971cc6d5232497d', 'maintenance_has_only_group');
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (62003, 0, '589d36644b7742dc9fef13ac0625f38c', 'maintenance_has_group_and_host');
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (62004, 0, 'bf806430c23c422b8bdb5dc7f4af2ca6', 'maintenance_group_1');
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (62005, 0, '4136fdc8ad2a46af913f5fdb82a72d1f', 'maintenance_group_2');
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (61003, 'maintenance_has_group_and_host', 'maintenance_has_group_and_host', 0, '', '');
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
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (120004, 'with_lld_discovery', 'with_lld_discovery', 0, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (120004, 120004, 50018);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) VALUES (2004, 120004, 1, 1, 1, '127.0.0.1', '', '10050');

INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers, flags) VALUES (400700, 2, 120004, 'discovery_rule', '', 'discovery', '0', NULL, '', '', '', '', '', '', 1);
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers, value_type, flags) VALUES (400710, 2, 120004, 'Item {#NAME}', '', 'item[{#NAME}]', '0', NULL, '', '', '', '', '', '', 3, 2);
INSERT INTO triggers (triggerid, expression, description, priority, flags, comments) VALUES (300001,'{99000}>0','Trigger {#NAME}', 2, 2, '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99000, 400710, 300001, 'last', '$');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid, key_) VALUES (34045, 400710, 400700, '');

INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers, value_type, flags) VALUES (400720, 2, 120004,' Item eth0', '', 'item[eth0]', '0', NULL, '', '', '', '', '', '', 3, 4);
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid, key_) VALUES (34046, 400720, 400710, 'item[{#NAME}]');
INSERT INTO triggers (triggerid, expression, description, priority, flags, comments, value) VALUES (300002,'{99001}>0','Trigger eth0', 2, 4, '', 1);
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99001, 400720, 300002, 'last', '$');
INSERT INTO trigger_discovery (triggerid, parent_triggerid) VALUES (300002, 300001);

INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers, value_type, flags, master_itemid) VALUES (400730, 18, 120004, 'Item_child {#NAME}', '', 'item_child[{#NAME}]', '0', NULL, '', '', '', '', '', '', 3, 2, 400710);
INSERT INTO triggers (triggerid, expression, description, priority, flags, comments) VALUES (300003,'{99002}>0','Trigger {#NAME}', 2, 2, '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99002, 400730, 300003, 'last', '$');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid, key_) VALUES (34047, 400730, 400700, '');

INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers, value_type, flags, master_itemid) VALUES (400740, 18, 120004,' Item_child eth0', '', 'item_child[eth0]', '0', NULL, '', '', '', '', '', '', 3, 4, 400720);
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid, key_) VALUES (34048, 400740, 400730, 'item[{#NAME}]');
INSERT INTO triggers (triggerid, expression, description, priority, flags, comments, value) VALUES (300004,'{99003}>0','Trigger eth0', 2, 4, '', 1);
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99003, 400740, 300004, 'last', '$');
INSERT INTO trigger_discovery (triggerid, parent_triggerid) VALUES (300004, 300003);

-- LLD rules
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,trends,status,params,description,query_fields,flags,posts,headers) VALUES (110006,50009,50022,0,4,'API LLD rule 1','apilldrule1','30s','90d','365d',0,'','','',1,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,trends,status,params,description,query_fields,flags,posts,headers) VALUES (110007,50009,50022,0,4,'API LLD rule 2','apilldrule2','30s','90d','365d',0,'','','',1,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,trends,status,params,description,query_fields,flags,posts,headers) VALUES (110008,50009,50022,0,4,'API LLD rule 3','apilldrule3','30s','90d','365d',0,'','','',1,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,trends,status,params,description,query_fields,flags,posts,headers) VALUES (110009,50009,50022,0,4,'API LLD rule 4','apilldrule4','30s','90d','365d',0,'','','',1,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,trends,status,params,description,query_fields,flags,posts,headers) VALUES (110010,50010,null,0,4,'API Template LLD rule','apitemplatelldrule','30s','90d','365d',0,'','','',1,'','');
INSERT INTO items (itemid,hostid,interfaceid,templateid,type,value_type,name,key_,delay,history,trends,status,params,description,query_fields,flags,posts,headers) VALUES (110011,50009,50022,110010,0,4,'API Template LLD rule','apitemplatelldrule','30s','90d','365d',0,'','','',1,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,trends,status,params,description,query_fields,flags,posts,headers) VALUES (110012,50009,50022,0,4,'API LLD rule get LLD macro paths','apilldrulegetlldmacropaths','30s','90d','365d',0,'','','',1,'','');
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,trends,status,params,description,query_fields,flags,posts,headers) VALUES (110013,50009,50022,0,4,'API LLD rule get preprocessing','apilldrulegetpreprocessing','30s','90d','365d',0,'','','',1,'','');

-- LLD macro paths
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (4991,110006,'{#A}','$.list[:1].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (4992,110006,'{#B}','$.list[:2].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (4993,110006,'{#C}','$.list[:3].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (4994,110006,'{#D}','$.list[:4].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (4995,110006,'{#E}','$.list[:5].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (4996,110007,'{#A}','$.list[:1].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (4997,110007,'{#B}','$.list[:2].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (4998,110007,'{#C}','$.list[:3].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (4999,110008,'{#A}','$.list[:1].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (5000,110008,'{#B}','$.list[:2].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (5001,110008,'{#C}','$.list[:3].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (5002,110010,'{#A}','$.list[:1].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (5003,110010,'{#B}','$.list[:2].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (5004,110010,'{#C}','$.list[:3].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (5005,110011,'{#A}','$.list[:1].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (5006,110011,'{#B}','$.list[:2].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (5007,110011,'{#C}','$.list[:3].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (5008,110012,'{#A}','$.list[:1].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (5009,110012,'{#B}','$.list[:2].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (5010,110012,'{#C}','$.list[:3].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (5011,110012,'{#D}','$.list[:4].type');
INSERT INTO lld_macro_path (lld_macro_pathid,itemid,lld_macro,path) VALUES (5012,110012,'{#E}','$.list[:5].type');

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
insert into hosts (hostid,host,name,status,description, readme) values (130000,'triggerstester','triggerstester',0,'', '');
insert into hosts (hostid,host,name,status,description, readme) values (131000,'triggerstestertmpl','triggerstestertmpl',3,'', '');
insert into hosts_groups (hostgroupid, hostid, groupid) values (139100, 130000, 139000);
insert into hosts_groups (hostgroupid, hostid, groupid) values (139200, 131000, 139003);
insert into items (itemid,hostid,type,name,key_,params,query_fields,description,posts,headers) values (132000,130000,2,'triggerstesteritem','triggerstesteritem','','','','','');
insert into items (itemid,hostid,type,name,key_,params,query_fields,description,posts,headers) values (132001,131000,2,'triggerstesteritemtmpl','triggerstesteritemtmpl','','','','','');
insert into items (itemid,hostid,type,name,key_,flags,params,query_fields,description,posts,headers) values (132002,130000,2,'triggerstesteritemlld','triggerstesteritemlld',1,'','','','','');
insert into items (itemid,hostid,type,name,key_,flags,params,query_fields,description,posts,headers) values (132003,131000,2,'triggerstesteritemlldtmpl','triggerstesteritemlldtmpl',1,'','','','','');
insert into items (itemid,hostid,type,name,key_,flags,params,query_fields,description,posts,headers) values (132004,130000,2,'triggerstesteritemproto[{#T}]','triggerstesteritemproto[{#T}]',2,'','','','','');
insert into items (itemid,hostid,type,name,key_,flags,params,query_fields,description,posts,headers) values (132005,131000,2,'triggerstesteritemprototmpl[{#T}]','triggerstesteritemprototmpl[{#T}]',2,'','','','','');
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
INSERT INTO items (itemid,hostid,type,name,key_,flags,params,query_fields,description,posts,headers) VALUES (132006,130000,2,'TriggersTesterItemLLDDiscovered[res1]','TriggersTesterItemLLDDiscovered[res1]',4,'','','','','');
INSERT INTO triggers (triggerid,expression,description,priority,flags,comments) VALUES (134118,'{135118}=0','TriggersTesterLLDTmpl_T0[res1]',0,4,'');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (135118,132006,134118,'now','$,0');
INSERT INTO trigger_discovery (triggerid,parent_triggerid) VALUES (134118,134106);
insert into item_discovery (itemdiscoveryid,itemid,parent_itemid,key_) values (138002,132006,132004,'triggerstesteritemprototmpl[{#T}]');
-- T4 depends on T5 depends on T0 (LLD discovered version)
INSERT INTO trigger_depends (triggerdepid,triggerid_down,triggerid_up) VALUES (138888,134004,134005);
INSERT INTO trigger_depends (triggerdepid,triggerid_down,triggerid_up) VALUES (138889,134005,134118);

-- testDiscoveryRule
INSERT INTO hstgrp (groupid, type, uuid, name) values (1004, 0, '1dcc10f0362545648a94256ca3260e8a', 'testDiscoveryRule');

INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (1017, 'test.discovery.rule.host.1', 'test.discovery.rule.host.1', 0, '', '');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (1010, 1017, 1, 1, 1, '127.0.0.1', '', '10050');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (1017, 1017, 1004);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params,query_fields, description, posts, headers                 ) VALUES (2600, 1017, 'item.1.1.1.1'                  ,  2, 'item.1.1.1.1'                  , 1, '90d', 0, NULL, NULL, '','', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, trends, status, master_itemid, templateid, params, description, posts, headers, lifetime, lifetime_type, enabled_lifetime, enabled_lifetime_type, query_fields, flags) VALUES (2601, 1017, 'dependent.lld.1'               , 18, 'dependent.lld.1'               , 4, '90d', '365d', 0, 2600, NULL, '', '', '', '', '30d', 0, '0', 2, '', 1);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params,query_fields, description, posts, headers                 ) VALUES (2604, 1017, 'item.2'                        ,  2, 'item.2'                        , 1, '90d', 0, NULL, NULL, '','', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, trends, status, master_itemid, templateid, params, description, posts, headers, lifetime, lifetime_type, enabled_lifetime, enabled_lifetime_type, query_fields, flags) VALUES (2605, 1017, 'dependent.lld.3'               , 18, 'dependent.lld.3'               , 4, '90d', '365d', 0, 2604, NULL, '', '', '', '', '30d', 0, '0', 2, '', 1);

INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (1018, 'test.discovery.rule.host.2', 'test.discovery.rule.host.2', 0, '', '');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (1011, 1018, 1, 1, 1, '127.0.0.1', '', '10050');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (1018, 1018, 1004);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params,query_fields, description, posts, headers                 ) VALUES (2606, 1018, 'item.1'                        ,  2, 'item.1'                        , 1, '90d', 0, NULL, NULL, '','', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params,query_fields, description, posts, headers                 ) VALUES (2607, 1018, 'item.1.1'                      , 18, 'item.1.1'                      , 1, '90d', 0, 2606, NULL, '','', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params,query_fields, description, posts, headers                 ) VALUES (2608, 1018, 'item.1.1.1'                    , 18, 'item.1.1.1'                    , 1, '90d', 0, 2607, NULL, '','', '', '', '');
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params,query_fields, description, posts, headers                 ) VALUES (2609, 1018, 'item.1.1.1.1'                  , 18, 'item.1.1.1.1'                  , 1, '90d', 0, 2608, NULL, '','', '', '', '');

-- testHistory
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (1005, 0, '0326c9db6487496b9beb6ca353cac1d0', 'history.get/hosts');

INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (120005, 'history.get.host.1', 'history.get.host.1', 1, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (1019, 120005, 1005);
INSERT INTO interface (interfaceid, hostid, type, ip, useip, port, main) VALUES (1012, 120005, 1, '127.0.0.1', 1, '10050', 1);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params,query_fields, description, posts, headers) VALUES (133758, 120005, 'item1', 2, 'item1', 3, '90d', 0, NULL, NULL, '','', '', '', '');
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
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params,query_fields, description, posts, headers) VALUES (133759, 120005, 'item2', 2, 'item2', 0, '90d', 0, NULL, NULL, '','', '', '', '');
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
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params,query_fields, description, posts, headers) VALUES (133760, 120005, 'item3', 2, 'item3', 1, '90d', 0, NULL, NULL, '','', '', '', '');
INSERT INTO history_str (itemid, clock, value, ns) VALUES
(133760, 1549350960, '1', 754460948),
(133760, 1549350962, '1', 919404393),
(133760, 1549350965, '1', 512878374);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params,query_fields, description, posts, headers) VALUES (133761, 120005, 'item4', 2, 'item4', 2, '90d', 0, NULL, NULL, '','', '', '', '');
INSERT INTO history_log (itemid, clock, timestamp, source, severity, value, logeventid, ns) VALUES
(133761, 1549350969, 0, '', 0, '1', 0, 506909535),
(133761, 1549350973, 0, '', 0, '2', 0, 336068358),
(133761, 1549350976, 0, '', 0, '3', 0, 2798098),
(133761, 1549350987, 0, '', 0, '4', 0, 755363307),
(133761, 1549350992, 0, '', 0, '5', 0, 242736233);
INSERT INTO items (itemid, hostid, name, type, key_, value_type, history, status, master_itemid, templateid, params,query_fields, description, posts, headers) VALUES (133762, 120005, 'item5', 2, 'item5', 4, '90d', 0, NULL, NULL, '','', '', '', '');
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
INSERT INTO items (itemid,type,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,formula,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,lifetime_type,enabled_lifetime,enabled_lifetime_type,evaltype,jmx_endpoint,master_itemid,timeout,url,query_fields,posts,status_codes,follow_redirects,post_type,http_proxy,headers,retrieve_mode,request_method,output_format,ssl_cert_file,ssl_key_file,ssl_key_password,verify_peer,verify_host,allow_traps,discover) VALUES (133763,2,'',50009,'Overrides (delete)','overrides.delete','0','90d','0',0,4,'','','','',NULL,NULL,'','',0,'','','','',1,NULL,'',0,'30d',0,'0',2,0,'',NULL,'','','','','200',1,0,'','',0,0,0,'','','',0,0,0,0);
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
INSERT INTO items (itemid,type,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,formula,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,lifetime_type,enabled_lifetime,enabled_lifetime_type,evaltype,jmx_endpoint,master_itemid,timeout,url,query_fields,posts,status_codes,follow_redirects,post_type,http_proxy,headers,retrieve_mode,request_method,output_format,ssl_cert_file,ssl_key_file,ssl_key_password,verify_peer,verify_host,allow_traps,discover) VALUES (133764,2,'',50009,'Overrides (copy)','overrides.copy','0','90d','0',0,4,'','','','',NULL,NULL,'','',0,'','','','',1,NULL,'',0,'30d',0,'0',2,0,'',NULL,'','','','','200',1,0,'','',0,0,0,'','','',0,0,0,0);
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
INSERT INTO items (itemid,type,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,formula,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,lifetime_type,enabled_lifetime,enabled_lifetime_type,evaltype,jmx_endpoint,master_itemid,timeout,url,query_fields,posts,status_codes,follow_redirects,post_type,http_proxy,headers,retrieve_mode,request_method,output_format,ssl_cert_file,ssl_key_file,ssl_key_password,verify_peer,verify_host,allow_traps,discover) VALUES (133765,2,'',50009,'Overrides (update)','overrides.update','0','90d','0',0,4,'','','','',NULL,NULL,'','',0,'','','','',1,NULL,'',0,'30d',0,'0',2,0,'',NULL,'','','','','200',1,0,'','',0,0,0,'','','',0,0,0,0);
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
INSERT INTO hosts (hostid,proxyid,host,status,ipmi_authtype,ipmi_privilege,ipmi_username,ipmi_password,maintenanceid,maintenance_status,maintenance_type,maintenance_from,name,flags,templateid,description,tls_connect,tls_accept,tls_issuer,tls_subject,tls_psk_identity,tls_psk,discover,readme) VALUES (131001,NULL,'Overrides template constraint',3,-1,2,'','',NULL,0,0,0,'Overrides template constraint',0,NULL,'',1,1,'','','','',0,'');
INSERT INTO hstgrp (groupid,type,uuid,name,flags) VALUES (139001,1,'27e5744a60894c7c88c8df39573df06e','Overrides',0);
INSERT INTO hosts_groups (hostgroupid,hostid,groupid) VALUES (139201,131001,139001);
INSERT INTO items (itemid,type,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,formula,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,lifetime_type,enabled_lifetime,enabled_lifetime_type,evaltype,jmx_endpoint,master_itemid,timeout,url,query_fields,posts,status_codes,follow_redirects,post_type,http_proxy,headers,retrieve_mode,request_method,output_format,ssl_cert_file,ssl_key_file,ssl_key_password,verify_peer,verify_host,allow_traps,discover) VALUES (133766,0,'',50009,'Overrides (template constraint)','overrides.template.constraint','1m','90d','0',0,4,'','','','',NULL,NULL,'','',0,'','','','',1,50022,'',0,'30d',0,'0',2,0,'',NULL,'','','','','200',1,0,'','',0,0,0,'','','',0,0,0,0);
INSERT INTO lld_override (lld_overrideid,itemid,name,step,evaltype,formula,stop) VALUES (10007,133766,'Only template operation',1,0,'',0);
INSERT INTO lld_override (lld_overrideid,itemid,name,step,evaltype,formula,stop) VALUES (10008,133766,'Not only template operation',2,0,'',0);
INSERT INTO lld_override_operation (lld_override_operationid,lld_overrideid,operationobject,operator,value) VALUES (10019,10007,3,0,'');
INSERT INTO lld_override_operation (lld_override_operationid,lld_overrideid,operationobject,operator,value) VALUES (10020,10008,3,0,'');
INSERT INTO lld_override_opinventory (lld_override_operationid,inventory_mode) VALUES (10020,0);
INSERT INTO lld_override_optemplate (lld_override_optemplateid,lld_override_operationid,templateid) VALUES (10010,10019,131001);
INSERT INTO lld_override_optemplate (lld_override_optemplateid,lld_override_operationid,templateid) VALUES (10011,10020,131001);

-- graph prototype
INSERT INTO hstgrp (groupid,type,uuid,name,flags) VALUES (139002,0,'c8aa3b02b1af4b049fcf88be47e5f892','test_graph_prototype',0);
INSERT INTO hosts (hostid,proxyid,host,status,ipmi_authtype,ipmi_privilege,ipmi_username,ipmi_password,maintenanceid,maintenance_status,maintenance_type,maintenance_from,name,flags,templateid,description,tls_connect,tls_accept,tls_issuer,tls_subject,tls_psk_identity,tls_psk,discover,readme) VALUES (131002,NULL,'item',0,-1,2,'','',NULL,0,0,0,'item',0,NULL,'',1,1,'','','','',0,'');
INSERT INTO hosts (hostid,proxyid,host,status,ipmi_authtype,ipmi_privilege,ipmi_username,ipmi_password,maintenanceid,maintenance_status,maintenance_type,maintenance_from,name,flags,templateid,description,tls_connect,tls_accept,tls_issuer,tls_subject,tls_psk_identity,tls_psk,discover,readme) VALUES (131003,NULL,'item_prototype',0,-1,2,'','',NULL,0,0,0,'item_prototype',0,NULL,'',1,1,'','','','',0,'');
INSERT INTO hosts_groups (hostgroupid,hostid,groupid) VALUES (139203,131003,139002);
INSERT INTO hosts_groups (hostgroupid,hostid,groupid) VALUES (139202,131002,139002);
INSERT INTO items (itemid,type,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,formula,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,lifetime_type,enabled_lifetime,enabled_lifetime_type,evaltype,jmx_endpoint,master_itemid,timeout,url,query_fields,posts,status_codes,follow_redirects,post_type,http_proxy,headers,retrieve_mode,request_method,output_format,ssl_cert_file,ssl_key_file,ssl_key_password,verify_peer,verify_host,allow_traps,discover) VALUES (133767,2,'',131003,'rule','a','0','90d','0',0,4,'','','','',NULL,NULL,'','',0,'','','','',1,NULL,'',0,'30d',0,'0',2,0,'',NULL,'','','','','200',1,0,'','',0,0,0,'','','',0,0,0,0);
INSERT INTO items (itemid,type,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,formula,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,lifetime_type,enabled_lifetime,enabled_lifetime_type,evaltype,jmx_endpoint,master_itemid,timeout,url,query_fields,posts,status_codes,follow_redirects,post_type,http_proxy,headers,retrieve_mode,request_method,output_format,ssl_cert_file,ssl_key_file,ssl_key_password,verify_peer,verify_host,allow_traps,discover) VALUES (133768,2,'',131003,'prototype','a[{#A}]','0','90d','365d',0,3,'','','','',NULL,NULL,'','',0,'','','','',2,NULL,'',0,'30d',0,'0',2,0,'',NULL,'','','','','200',1,0,'','',0,0,0,'','','',0,0,0,0);
INSERT INTO items (itemid,type,snmp_oid,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,formula,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,lifetime,lifetime_type,enabled_lifetime,enabled_lifetime_type,evaltype,jmx_endpoint,master_itemid,timeout,url,query_fields,posts,status_codes,follow_redirects,post_type,http_proxy,headers,retrieve_mode,request_method,output_format,ssl_cert_file,ssl_key_file,ssl_key_password,verify_peer,verify_host,allow_traps,discover) VALUES (133769,2,'',131002,'item','a','0','90d','365d',0,3,'','','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'30d',0,'0',2,0,'',NULL,'','','','','200',1,0,'','',0,0,0,'','','',0,0,0,0);
INSERT INTO item_discovery (itemdiscoveryid,itemid,parent_itemid,key_,lastcheck,ts_delete) VALUES (138003,133768,133767,'',0,0);
INSERT INTO graphs (graphid,name,width,height,yaxismin,yaxismax,templateid,show_work_period,show_triggers,graphtype,show_legend,show_3d,percent_left,percent_right,ymin_type,ymax_type,ymin_itemid,ymax_itemid,flags,discover) VALUES (9000,'graph_prototype',900,200,0,100,NULL,1,1,0,1,0,0,0,0,0,NULL,NULL,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (58000,9000,133769,0,1,'F63100',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (58001,9000,133768,0,0,'1A7C11',0,2,0);

-- test discovered host groups after import parent host
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (50025, 0, '45d1ca90cd844dd98762e118ea3208fc', 'Master group');
INSERT INTO hstgrp (groupid, type, uuid, name, flags) VALUES (50026, 0, '5421d5c696c347478bee88fe39d2040f', 'host group discovered', 4);
INSERT INTO hosts (hostid, host, name, status, flags, description, readme) VALUES (99010, 'Host having discovered hosts', 'Host having discovered hosts', 0, 0, '', '');
INSERT INTO hosts (hostid, host, name, status, flags, description, readme) VALUES (99011, '{#VALUE}', '{#VALUE}', 0, 2, '', '');
INSERT INTO hosts (hostid, host, name, status, flags, description, readme) VALUES (99012, 'discovered', 'discovered', 0, 4, '', '');
INSERT INTO items (itemid, type, hostid, name, key_, delay, history, trends, status, value_type, flags, params,query_fields, description, posts, headers) VALUES (158735, 2, 99010, 'trap', 'trap', '0', '90d', '0', 0, 4, 1, '','', '', '', '');
INSERT INTO group_prototype (group_prototypeid, hostid, name) VALUES (50110, 99011, 'host group {#VALUE}');
INSERT INTO group_prototype (group_prototypeid, hostid, groupid) VALUES (50111, 99011, 50025);
INSERT INTO group_discovery (groupdiscoveryid, groupid, parent_group_prototypeid, name) VALUES (2, 50026, 50110, 'host group {#VALUE}');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50020, 99010, 50025);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50021, 99012, 50025);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50022, 99012, 50026);
INSERT INTO host_discovery (hostid, lldruleid, host) VALUES (99011, 158735, '');
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

-- test trigger validation
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99013, 'Trigger validation test host', 'Trigger validation test host', 0, '', '');
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99014, 'Trigger validation test template', 'Trigger validation test template', 3, '', '');
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (50027, 0, '7466f5cb569f48e89eef772c6e4baacf', 'Trigger validation test host group');
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (50028, 1, '38da8a76c4a742479292151c2e404dae', 'Trigger validation test host group');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50023, 99013, 50027);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50024, 99014, 50028);
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history, status, params,query_fields, description, posts, headers) VALUES (158736, 99013, NULL, 2, 3, 'item', 'item', '1d', '90d', 0, '','', '', '', '');
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history, status, params,query_fields, description, posts, headers) VALUES (158737, 99014, NULL, 2, 3, 'item', 'item', '1d', '90d', 0, '','', '', '', '');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50176, 'test-trigger-1', '{50236}=0', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50236, 50176, 158736, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50177, 'test-trigger-2', '{50237}=0', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50237, 50177, 158736, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, comments) VALUES (50178, 'template-trigger', '{50238}=0', '');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (50238, 50178, 158737, 'last', '$');

-- services
INSERT INTO services (serviceid, name, description) VALUES (1, 'API Service for delete', '');
INSERT INTO services (serviceid, name, description) VALUES (2, 'API Service for update', '');

-- sla
INSERT INTO sla (slaid, name, period, slo, effective_date, timezone, status, description) VALUES (50038, 'Sla for delete 1', 0, 99.9999, 2147483637, 'Europe/Riga', 0, 'Pasta servera atjaunošana');
INSERT INTO sla (slaid, name, period, slo, effective_date, timezone, status, description) VALUES (50039, 'Sla for delete 2', 1, 99.9999, 2147483547, 'Europe/Riga', 1, 'Pasta servera atjaunošana');
INSERT INTO sla (slaid, name, period, slo, effective_date, timezone, status, description) VALUES (50040, 'Sla for delete 3', 2, 99.9999, 2147482647, 'Europe/Riga', 0, 'Pasta servera atjaunošana');
INSERT INTO sla (slaid, name, period, slo, effective_date, timezone, status, description) VALUES (50041, 'Sla for getSli', 2, 99.9999, 2147482647, 'Europe/Riga', 0, 'Pasta servera atjaunošana');

-- high availability nodes
INSERT INTO ha_node (name,address,port,status,ha_nodeid) VALUES ('node1','192.168.1.5','10051','0','ckuo7i1nv00090sajelcon0su');
INSERT INTO ha_node (name,address,port,status,ha_nodeid) VALUES ('node2','192.168.1.6','10051','0','ckuo7i1nv000a0saj1fcdkeu4');
INSERT INTO ha_node (name,address,port,status,ha_nodeid) VALUES ('node3','192.168.1.7','10052','0','ckuo7i1nv000b0saj3j8hxm2b');
INSERT INTO ha_node (name,address,port,status,ha_nodeid) VALUES ('node4','192.168.1.8','10052','1','ckuo7i1nv000c0sajz85xcrtt');
INSERT INTO ha_node (name,address,port,status,ha_nodeid) VALUES ('node5','192.168.1.9','10053','1','ckuo7i1nv000d0sajd95y1b6x');
INSERT INTO ha_node (name,address,port,status,ha_nodeid) VALUES ('node6','192.168.1.10','10053','2','ckuo7i1nw000e0sajwfttc1mp');
INSERT INTO ha_node (name,address,port,status,ha_nodeid) VALUES ('node7','192.168.1.11','10053','2','ckuo7i1nw000f0sajtzv1c6v3');
INSERT INTO ha_node (name,address,port,status,ha_nodeid) VALUES ('z-node','192.168.1.12','10051','1','ckuo7i1nw000g0sajjsjre7e3');
INSERT INTO ha_node (name,address,port,status,ha_nodeid) VALUES ('node-active','192.168.1.13','10051','3','ckuo7i1nw000h0sajj3l3hh8u');

-- binary value type
INSERT INTO hosts (hostid, host, name, status, flags, description, readme) VALUES (99015, 'Host for binary item', 'Host for binary item', 0, 0, '','');
INSERT INTO hstgrp (groupid, type, uuid, name) VALUES (50029, 0, '4d58962e533a4dbf9bfd1cb247f5b698', 'Host group for binary item');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50025, 99015, 50029);
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history, status, params,query_fields, description, posts, headers) VALUES (158738, 99015, 50022, 0, 0, 'master.for.binary', 'master.for.binary', '1d', '90d', 0, '','', '', '', '');
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history, status, params,query_fields, description, master_itemid, posts, headers) VALUES (158739, 99015, NULL, 18, 5, 'dependent.valuetype.binary', 'dependent.valuetype.binary', 0, 0, 0, '','', '', 158738, '', '');
INSERT INTO history_bin (itemid, clock, value, ns) VALUES (158739, 1549350962, 'This should be binary', 594538048);
