-- Test data for API tests
-- applications
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50009, 'API Host', 'API Host', 0, '');
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50010, 'API Template', 'API Template', 3, '');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (50022,50009,1,1,1,'127.0.0.1','','10050');
INSERT INTO groups (groupid,name,internal) VALUES (50012,'Api group for hosts',0);
INSERT INTO groups (groupid,name,internal) VALUES (50013,'Api group for templates',0);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50009, 50009, 50012);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50011, 50010, 50013);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (50003, 50009, 50010);
INSERT INTO applications (applicationid,hostid,name) VALUES (366,50009,'API application');
INSERT INTO applications (applicationid,hostid,name) VALUES (367,50009,'API host application for update');
INSERT INTO applications (applicationid,hostid,name) VALUES (368,10093,'API template application for update');
INSERT INTO applications (applicationid,hostid,name) VALUES (369,50010,'API templated application');
INSERT INTO applications (applicationid,hostid,name) VALUES (370,50009,'API templated application');
INSERT INTO applications (applicationid,hostid,name) VALUES (371,50009,'API application delete');
INSERT INTO applications (applicationid,hostid,name) VALUES (372,50009,'API application delete2');
INSERT INTO applications (applicationid,hostid,name) VALUES (373,50009,'API application delete3');
INSERT INTO applications (applicationid,hostid,name) VALUES (374,50009,'API application delete4');
INSERT INTO applications (applicationid,hostid,name) VALUES (376,10084,'API application for Zabbix server');
INSERT INTO application_template (application_templateid,applicationid,templateid) VALUES (52,370,369);
-- discovered application
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags) VALUES (40066, 50009, 50022, 0, 2,'API discovery rule','vfs.fs.discovery',30,90,0,'','',1);
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags) VALUES (40067, 50009, 50022, 0, 2,'API discovery item','vfs.fs.size[{#FSNAME},free]',30,90,0,'','',2);
INSERT INTO items (itemid,hostid,interfaceid,type,value_type,name,key_,delay,history,status,params,description,flags) VALUES (40068, 50009, 50022, 0, 2,'API discovery item','vfs.fs.size[/,free]',30,90,0,'','',4);
INSERT INTO item_discovery (itemdiscoveryid,itemid,parent_itemid,key_) VALUES (15085,40067,40066,'vfs.fs.size[{#FSNAME},free]');
INSERT INTO item_discovery (itemdiscoveryid,itemid,parent_itemid,key_) VALUES (15086,40068,40067,'vfs.fs.size[{#FSNAME},free]');
INSERT INTO applications (applicationid,hostid,name,flags) VALUES (375,50009,'API discovery application',4);
INSERT INTO application_prototype (application_prototypeid,itemid,name) VALUES (2,40066,'API discovery application');
INSERT INTO application_discovery (application_discoveryid,applicationid,application_prototypeid,name) VALUES (1,375,2,'API discovery application');
INSERT INTO items_applications (itemappid,applicationid,itemid) VALUES (6000,375,40068);
INSERT INTO item_application_prototype (item_application_prototypeid,application_prototypeid,itemid) VALUES (2,2,40067);

-- valuemap
INSERT INTO valuemaps (valuemapid,name) VALUES (18,'Api value map for update');
INSERT INTO valuemaps (valuemapid,name) VALUES (19,'Api value map for update with mappings');
INSERT INTO valuemaps (valuemapid,name) VALUES (20,'Api value map delete');
INSERT INTO valuemaps (valuemapid,name) VALUES (21,'Api value map delete2');
INSERT INTO valuemaps (valuemapid,name) VALUES (22,'Api value map delete3');
INSERT INTO valuemaps (valuemapid,name) VALUES (23,'Api value map delete4');
INSERT INTO mappings (mappingid,valuemapid,value,newvalue) VALUES (166,19,'One','Online');
INSERT INTO mappings (mappingid,valuemapid,value,newvalue) VALUES (167,19,'Two','Offline');
INSERT INTO mappings (mappingid,valuemapid,value,newvalue) VALUES (168,21,'Three','Other');
INSERT INTO mappings (mappingid,valuemapid,value,newvalue) VALUES (169,22,'Four','Unknown');
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (4, 'zabbix-admin', '5fce1b3e34b520afeffb37ce08c7cd66', 0, 0, 'en_GB', 30, 2, 'default', 0, 0, 50);
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (5, 'zabbix-user', '5fce1b3e34b520afeffb37ce08c7cd66', 0, 0, 'en_GB', 30, 1, 'default', 0, 0, 50);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (6, 8, 4);

-- host groups
INSERT INTO groups (groupid,name,internal) VALUES (50005,'Api host group for update',0);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50010, 50009, 50005);
INSERT INTO groups (groupid,name,internal) VALUES (50006,'Api host group for update internal',1);
INSERT INTO groups (groupid,name,internal) VALUES (50007,'Api host group delete internal',1);
INSERT INTO groups (groupid,name,internal) VALUES (50008,'Api host group delete',0);
INSERT INTO groups (groupid,name,internal) VALUES (50009,'Api host group delete2',0);
INSERT INTO groups (groupid,name,internal) VALUES (50010,'Api host group delete3',0);
INSERT INTO groups (groupid,name,internal) VALUES (50011,'Api host group delete4',0);
-- discovered host groups
INSERT INTO hosts (hostid, host, name, status, flags, description) VALUES (50011, 'Api host prototype {#FSNAME}', 'Api host prototype {#FSNAME}', 0, 2, '');
INSERT INTO groups (groupid,name,internal) VALUES (50014,'Api group for host prototype',0);
INSERT INTO host_discovery (hostid,parent_hostid,parent_itemid) VALUES (50011,NULL,40066);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (8, 50011, 'Api discovery group {#HV.NAME}', NULL, NULL);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (9, 50011, '', 50014, NULL);
INSERT INTO groups (groupid,name,internal,flags) VALUES (50015,'Api discovery group {#HV.NAME}',0,4);
INSERT INTO group_discovery (groupid, parent_group_prototypeid, name) VALUES (50015, 8, 'Api discovery group {#HV.NAME}');

-- user group
INSERT INTO usrgrp (usrgrpid, name) VALUES (13, 'Api user group for update');
INSERT INTO usrgrp (usrgrpid, name) VALUES (14, 'Api user group for update with user and rights');
INSERT INTO usrgrp (usrgrpid, name) VALUES (15, 'Api user group with one user');
INSERT INTO usrgrp (usrgrpid, name) VALUES (16, 'Api user group delete');
INSERT INTO usrgrp (usrgrpid, name) VALUES (17, 'Api user group delete1');
INSERT INTO usrgrp (usrgrpid, name) VALUES (18, 'Api user group delete2');
INSERT INTO usrgrp (usrgrpid, name) VALUES (19, 'Api user group delete3');
INSERT INTO usrgrp (usrgrpid, name) VALUES (20, 'Api user group in actions');
INSERT INTO usrgrp (usrgrpid, name) VALUES (21, 'Api user group in scripts');
INSERT INTO usrgrp (usrgrpid, name) VALUES (22, 'Api user group in configuration');
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (6, 'user-in-one-group', '5fce1b3e34b520afeffb37ce08c7cd66', 0, 0, 'en_GB', 30, 2, 'default', 0, 0, 50);
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (7, 'user-in-two-groups', '5fce1b3e34b520afeffb37ce08c7cd66', 0, 0, 'en_GB', 30, 2, 'default', 0, 0, 50);
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (8, 'api-user', '5fce1b3e34b520afeffb37ce08c7cd66', 0, 0, 'en_GB', 30, 2, 'default', 0, 0, 50);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (8, 14, 4);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (9, 15, 6);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (10, 16, 7);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (11, 17, 7);
INSERT INTO rights (rightid, groupid, permission, id) VALUES (2, 14, 3, 50012);
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period, def_shortdata, def_longdata, r_shortdata, r_longdata) VALUES (16,'Api action',0,0,0,60,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}','{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}');
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (31, 16, 0, 0, 1, 1, 0);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (31, 1, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', NULL);
INSERT INTO opmessage_grp (opmessage_grpid, operationid, usrgrpid) VALUES (21, 31, 20);
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description) VALUES (5,'Api script','test',2,21,NULL,'api script description');
UPDATE config SET alert_usrgrpid = 22 WHERE configid = 1;

-- users
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (9, 'api-user-for-update', '5fce1b3e34b520afeffb37ce08c7cd66', 0, 900, 'en_GB', 30, 2, 'default', 0, 0, 50);
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (10, 'api-user-delete', '5fce1b3e34b520afeffb37ce08c7cd66', 0, 900, 'en_GB', 30, 2, 'default', 0, 0, 50);
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (11, 'api-user-delete1', '5fce1b3e34b520afeffb37ce08c7cd66', 0, 900, 'en_GB', 30, 2, 'default', 0, 0, 50);
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (12, 'api-user-delete2', '5fce1b3e34b520afeffb37ce08c7cd66', 0, 900, 'en_GB', 30, 2, 'default', 0, 0, 50);
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (13, 'api-user-action', '5fce1b3e34b520afeffb37ce08c7cd66', 0, 900, 'en_GB', 30, 2, 'default', 0, 0, 50);
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (14, 'api-user-map', '5fce1b3e34b520afeffb37ce08c7cd66', 0, 900, 'en_GB', 30, 2, 'default', 0, 0, 50);
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (15, 'api-user-screen', '5fce1b3e34b520afeffb37ce08c7cd66', 0, 900, 'en_GB', 30, 2, 'default', 0, 0, 50);
INSERT INTO users (userid, alias, passwd, autologin, autologout, lang, refresh, type, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (16, 'api-user-slideshow', '5fce1b3e34b520afeffb37ce08c7cd66', 0, 900, 'en_GB', 30, 2, 'default', 0, 0, 50);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (12, 14, 9);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (13, 14, 10);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (14, 14, 11);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (15, 14, 12);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (16, 9, 13);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (17, 14, 14);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (18, 14, 15);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (19, 14, 16);
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period, def_shortdata, def_longdata, r_shortdata, r_longdata) VALUES (17,'Api action with user',0,0,0,60,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}','{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}');
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (32, 17, 0, 0, 1, 1, 0);
INSERT INTO opmessage (operationid, default_msg, subject, message, mediatypeid) VALUES (32, 1, '{TRIGGER.NAME}: {TRIGGER.STATUS}', '{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}{TRIGGER.URL}', NULL);
INSERT INTO opmessage_usr (opmessage_usrid, operationid, userid) VALUES (4, 32, 13);
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, userid, private) VALUES (6, 'Api map', 800, 600, NULL, 0, 0, 1, 1, 1, 2, 14, 0);
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200021, 'Api screen', 1, 1, NULL, 15, 0);
INSERT INTO slideshows (slideshowid, name, delay, userid, private) VALUES (200004, 'Api slide show', 10, 16, 0);
INSERT INTO screens (screenid, name, hsize, vsize, templateid, userid, private) VALUES (200022, 'Api screen for slide show', 1, 1, NULL, 1, 0);
INSERT INTO slides (slideid, slideshowid, screenid, step, delay) VALUES (200012, 200004, 200022, 0, 0);

-- scripts
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50013, 'API disabled host', 'API disabled host', 1, '');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (50024,50013,1,1,1,'127.0.0.1','','10050');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50013, 50013, 50012);
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50012, 'API Host for read permissions', 'API Host for read permissions', 0, '');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (50023,50012,1,1,1,'127.0.0.1','','10050');
INSERT INTO groups (groupid,name,internal) VALUES (50016,'Api group with read permissions',0);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50012, 50012, 50016);
INSERT INTO rights (rightid, groupid, permission, id) VALUES (3, 14, 2, 50016);
INSERT INTO hosts (hostid, host, name, status, description) VALUES (50014, 'API Host for deny permissions', 'API Host for deny permissions', 0, '');
INSERT INTO interface (interfaceid,hostid,main,type,useip,ip,dns,port) values (50025,50014,1,1,1,'127.0.0.1','','10050');
INSERT INTO groups (groupid,name,internal) VALUES (50017,'Api group with deny permissions',0);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50014, 50014, 50017);
INSERT INTO rights (rightid, groupid, permission, id) VALUES (4, 14, 0, 50017);
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description) VALUES (6,'Api script for update one','/sbin/shutdown -r',2,NULL,NULL,'');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description) VALUES (7,'Api script for update two','/sbin/shutdown -r',2,NULL,NULL,'');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description) VALUES (8,'Api script for delete','/sbin/shutdown -r',2,NULL,NULL,'');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description) VALUES (9,'Api script for delete1','/sbin/shutdown -r',2,NULL,NULL,'');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description) VALUES (10,'Api script for delete2','/sbin/shutdown -r',2,NULL,NULL,'');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description) VALUES (11,'Api script in action','/sbin/shutdown -r',2,NULL,NULL,'');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description) VALUES (12,'Api script with user group','/sbin/shutdown -r',2,7,NULL,'');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description) VALUES (13,'Api script with host group','/sbin/shutdown -r',2,NULL,4,'');
INSERT INTO scripts (scriptid, name, command, host_access, usrgrpid, groupid, description) VALUES (14,'Api script with write permissions for the host group','/sbin/shutdown -r',3,NULL,NULL,'');
INSERT INTO actions (actionid, name, eventsource, evaltype, status, esc_period, def_shortdata, def_longdata, r_shortdata, r_longdata) VALUES (18,'Api action with script',0,0,0,60,'{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}\r\nLast value: {ITEM.LASTVALUE}','{TRIGGER.NAME}: {TRIGGER.STATUS}','{TRIGGER.NAME}: {TRIGGER.STATUS}Last value: {ITEM.LASTVALUE}');
INSERT INTO operations (operationid, actionid, operationtype, esc_period, esc_step_from, esc_step_to, evaltype) VALUES (33, 18, 1, 0, 1, 1, 0);
INSERT INTO opcommand_hst (opcommand_hstid, operationid, hostid) VALUES (4, 33, NULL);
INSERT INTO opcommand (operationid, type, scriptid, execute_on, port, authtype, username, password, publickey, privatekey, command) VALUES (33, 4, 11, 0, '', 0, '', '', '', '', '');

-- global macro
INSERT INTO globalmacro (globalmacroid, macro, value) VALUES (13,'{$API_MACRO_FOR_UPDATE1}','update');
INSERT INTO globalmacro (globalmacroid, macro, value) VALUES (14,'{$API_MACRO_FOR_UPDATE2}','update');
INSERT INTO globalmacro (globalmacroid, macro, value) VALUES (15,'{$API_MACRO_FOR_DELETE}','abc');
INSERT INTO globalmacro (globalmacroid, macro, value) VALUES (16,'{$API_MACRO_FOR_DELETE1}','1');
INSERT INTO globalmacro (globalmacroid, macro, value) VALUES (17,'{$API_MACRO_FOR_DELETE2}','2');

-- icon map
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (1,'Api icon map',2);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (1,1,2,1,'api icon map expression',0);
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (2,'Api icon map for update1',2);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (2,2,2,1,'api expression for update1',0);
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (3,'Api icon map for update2',2);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (3,3,2,1,'api expression for update2',0);
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (4,'Api icon map for delete',2);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (4,4,2,1,'api expression for delete',0);
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (5,'Api icon map for delete1',2);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (5,5,2,1,'api expression for delete1',0);
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (6,'Api icon map for delete2',2);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (6,6,2,1,'api expression for delete2',0);
INSERT INTO icon_map (iconmapid, name, default_iconid) VALUES (7,'Api iconmap in map',2);
INSERT INTO icon_mapping (iconmappingid, iconmapid, iconid, inventory_link, expression, sortorder) VALUES (7,7,7,1,'api expression',0);
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, iconmapid, userid, private) VALUES (7, 'Map with iconmap', 800, 600, NULL, 0, 0, 1, 1, 1, 2, 7, 1, 0);
