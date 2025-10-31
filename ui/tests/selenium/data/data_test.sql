-- Activate Zabbix Server, set visible name and make it a more unique name
UPDATE hosts SET status=0,name='ЗАББИКС Сервер',host='Test host' WHERE host='Zabbix server';

-- inheritance testing
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (15000, 'Inheritance test template', 'Inheritance test template', 3, '', '');
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (15002, 'Inheritance test template 2', 'Inheritance test template 2', 3, '', '');
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (15015, 'Inheritance test template for unlink', 'Inheritance test template for unlink', 3, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (15000, 15000, 1);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (15002, 15002, 1);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (15015, 15015, 1);

INSERT INTO valuemap (valuemapid, hostid, name) VALUES (5701, 15000, 'Template value mapping');
INSERT INTO valuemap_mapping (valuemap_mappingid, valuemapid, value, newvalue, sortorder) VALUES (57001, 5701, 0, 'no', 0);
INSERT INTO valuemap_mapping (valuemap_mappingid, valuemapid, value, newvalue, sortorder) VALUES (57002, 5701, 1, 'yes', 1);

INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (15001, 'Template inheritance test host', 'Template inheritance test host', 0, '', '');
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
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, formula, params,query_fields, description, posts, headers) VALUES (15000, 15000, 0, 'itemInheritance'     , 'key-item-inheritance-test', '30s', 3, 1, '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, formula, params,query_fields, description, posts, headers) VALUES (15001, 15000, 0, 'testInheritanceItem1', 'test-inheritance-item1'   , '30s', 3, 1, '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, formula, params,query_fields, description, posts, headers) VALUES (15002, 15000, 0, 'testInheritanceItem2', 'test-inheritance-item2'   , '30s', 3, 1, '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, formula, params,query_fields, description, posts, headers) VALUES (15003, 15000, 0, 'testInheritanceItem3', 'test-inheritance-item3'   , '30s', 3, 1, '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, formula, params,query_fields, description, posts, headers) VALUES (15004, 15000, 0, 'testInheritanceItem4', 'test-inheritance-item4'   , '30s', 3, 1, '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params,query_fields, description, posts, headers) VALUES (15093, 15000, 0, 'testInheritanceItemPreprocessing', 'test-inheritance-item-preprocessing'   , '30s', 3, '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, interfaceid,query_fields, templateid, posts, headers) VALUES (15005, 15001, 0, 'itemInheritance'     , 'key-item-inheritance-test', '30s', 3, '', '', 15000,'', 15000, '', '');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15005, 'itemInheritance', 'ITEMINHERITANCE');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, interfaceid,query_fields, templateid, posts, headers) VALUES (15006, 15001, 0, 'testInheritanceItem1', 'test-inheritance-item1'   , '30s', 3, '', '', 15000,'', 15001, '', '');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15006, 'testInheritanceItem1', 'TESTINHERITANCEITEM1');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, interfaceid,query_fields, templateid, posts, headers) VALUES (15007, 15001, 0, 'testInheritanceItem2', 'test-inheritance-item2'   , '30s', 3, '', '', 15000,'', 15002, '', '');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15007, 'testInheritanceItem2', 'TESTINHERITANCEITEM2');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, interfaceid,query_fields, templateid, posts, headers) VALUES (15008, 15001, 0, 'testInheritanceItem3', 'test-inheritance-item3'   , '30s', 3, '', '', 15000,'', 15003, '', '');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15008, 'testInheritanceItem3', 'TESTINHERITANCEITEM3');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, interfaceid,query_fields, templateid, posts, headers) VALUES (15009, 15001, 0, 'testInheritanceItem4', 'test-inheritance-item4'   , '30s', 3, '', '', 15000,'', 15004, '', '');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15009, 'testInheritanceItem4', 'TESTINHERITANCEITEM4');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, interfaceid,query_fields, templateid, posts, headers) VALUES (15094, 15001, 0, 'testInheritanceItemPreprocessing', 'test-inheritance-item-preprocessing', '30s', 3, '', '', 15000,'', 15093, '', '');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15094, 'testInheritanceItemPreprocessing', 'TESTINHERITANCEITEMPREPROCESSING');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description,query_fields, interfaceid, posts, headers)             VALUES (15010, 15001, 0, 'itemInheritanceTest' , 'key-test-inheritance'     , '30s', 3, '', '','', 15000, '', '');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15010, 'itemInheritanceTest', 'ITEMINHERITANCETEST');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params,query_fields, description, posts, headers) VALUES (15079, 15002, 0, 'testInheritance'     , 'key-item-inheritance'     , '30s', 3, '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, interfaceid,query_fields, templateid, posts, headers) VALUES (15080, 15001, 0, 'testInheritance'     , 'key-item-inheritance'     , '30s', 3, '', '', 15000,'', 15079, '', '');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15080, 'testInheritance', 'TESTINHERITANCE');

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
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (99729, 99000, 15000, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (99730, 99001, 15000, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (99731, 99002, 15000, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (99732, 99003, 15000, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (99733, 99004, 15005, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (99734, 99005, 15005, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (99735, 99006, 15005, 'last', '$');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (99736, 99007, 15005, 'last', '$');

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
INSERT INTO items (itemid, hostid, type, name, key_, delay, trends, value_type, params, description,query_fields, flags, posts, headers)                          VALUES (15011, 15000, 0, 'testInheritanceDiscoveryRule' , 'inheritance-discovery-rule' , 3600, 0, 4, '', '','', 1, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, trends, value_type, params, description,query_fields, flags, posts, headers)                          VALUES (15012, 15000, 0, 'testInheritanceDiscoveryRule1', 'discovery-rule-inheritance1', 3600, 0, 4, '', '','', 1, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, trends, value_type, params, description,query_fields, flags, posts, headers)                          VALUES (15013, 15000, 0, 'testInheritanceDiscoveryRule2', 'discovery-rule-inheritance2', 3600, 0, 4, '', '','', 1, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, trends, value_type, params, description,query_fields, flags, posts, headers)                          VALUES (15014, 15000, 0, 'testInheritanceDiscoveryRule3', 'discovery-rule-inheritance3', 3600, 0, 4, '', '','', 1, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, trends, value_type, params, description,query_fields, flags, posts, headers)                          VALUES (15015, 15000, 0, 'testInheritanceDiscoveryRule4', 'discovery-rule-inheritance4', 3600, 0, 4, '', '','', 1, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, trends, value_type, params, description, flags, interfaceid,query_fields, templateid, posts, headers) VALUES (15016, 15001, 0, 'testInheritanceDiscoveryRule' , 'inheritance-discovery-rule' , 3600, 0, 4, '', '', 1, 15000,'', 15011, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, trends, value_type, params, description, flags, interfaceid,query_fields, templateid, posts, headers) VALUES (15017, 15001, 0, 'testInheritanceDiscoveryRule1', 'discovery-rule-inheritance1', 3600, 0, 4, '', '', 1, 15000,'', 15012, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, trends, value_type, params, description, flags, interfaceid,query_fields, templateid, posts, headers) VALUES (15018, 15001, 0, 'testInheritanceDiscoveryRule2', 'discovery-rule-inheritance2', 3600, 0, 4, '', '', 1, 15000,'', 15013, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, trends, value_type, params, description, flags, interfaceid,query_fields, templateid, posts, headers) VALUES (15019, 15001, 0, 'testInheritanceDiscoveryRule3', 'discovery-rule-inheritance3', 3600, 0, 4, '', '', 1, 15000,'', 15014, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, trends, value_type, params, description, flags, interfaceid,query_fields, templateid, posts, headers) VALUES (15020, 15001, 0, 'testInheritanceDiscoveryRule4', 'discovery-rule-inheritance4', 3600, 0, 4, '', '', 1, 15000,'', 15015, '', '');

INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description,query_fields, flags, posts, headers)                          VALUES (15081, 15002, 0, 'testInheritanceDiscoveryRule5', 'discovery-rule-inheritance5', 3600, 4, '', '','', 1, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, flags, interfaceid,query_fields, templateid, posts, headers) VALUES (15082, 15001, 0, 'testInheritanceDiscoveryRule5', 'discovery-rule-inheritance5', 3600, 4, '', '', 1, 15000,'', 15081, '', '');

-- testInheritanceItemPrototype.SimpleUpdate and testInheritanceItemPrototype.SimpleCreate
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, formula, params, description,query_fields, flags, posts, headers)                          VALUES (15021, 15000, 0, 'itemDiscovery'                , 'item-discovery-prototype[{#KEY}]', '30s', 3, 1, '', '','', 2, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, formula, params, description,query_fields, flags, posts, headers)                          VALUES (15022, 15000, 0, 'testInheritanceItemPrototype1', 'item-prototype-test1[{#KEY}]'    , '30s', 3, 1, '', '','', 2, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, formula, params, description,query_fields, flags, posts, headers)                          VALUES (15023, 15000, 0, 'testInheritanceItemPrototype2', 'item-prototype-test2[{#KEY}]'    , '30s', 3, 1, '', '','', 2, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, formula, params, description,query_fields, flags, posts, headers)                          VALUES (15024, 15000, 0, 'testInheritanceItemPrototype3', 'item-prototype-test3[{#KEY}]'    , '30s', 3, 1, '', '','', 2, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, formula, params, description,query_fields, flags, posts, headers)                          VALUES (15025, 15000, 0, 'testInheritanceItemPrototype4', 'item-prototype-test4[{#KEY}]'    , '30s', 3, 1, '', '','', 2, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description,query_fields, flags, posts, headers)                          VALUES (15095, 15000, 0, 'testInheritanceItemPrototypePreprocessing', 'item-prototype-preprocessing[{#KEY}]'    , 30, 3,'', '','', 2, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, flags, interfaceid,query_fields, templateid, posts, headers) VALUES (15026, 15001, 0, 'itemDiscovery'                , 'item-discovery-prototype[{#KEY}]', '30s', 3, '', '', 2, 15000,'', 15021, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, flags, interfaceid,query_fields, templateid, posts, headers) VALUES (15027, 15001, 0, 'testInheritanceItemPrototype1', 'item-prototype-test1[{#KEY}]'    , '30s', 3, '', '', 2, 15000,'', 15022, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, flags, interfaceid,query_fields, templateid, posts, headers) VALUES (15028, 15001, 0, 'testInheritanceItemPrototype2', 'item-prototype-test2[{#KEY}]'    , '30s', 3, '', '', 2, 15000,'', 15023, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, flags, interfaceid,query_fields, templateid, posts, headers) VALUES (15029, 15001, 0, 'testInheritanceItemPrototype3', 'item-prototype-test3[{#KEY}]'    , '30s', 3, '', '', 2, 15000,'', 15024, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, flags, interfaceid,query_fields, templateid, posts, headers) VALUES (15030, 15001, 0, 'testInheritanceItemPrototype4', 'item-prototype-test4[{#KEY}]'    , '30s', 3, '', '', 2, 15000,'', 15025, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, flags, interfaceid,query_fields, templateid, posts, headers) VALUES (15096, 15001, 0, 'testInheritanceItemPrototypePreprocessing', 'item-prototype-preprocessing[{#KEY}]', '30s', 3, '', '', 2, 15000,'', 15095, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, lldruleid) values (35021, 15021, 15011);
INSERT INTO item_discovery (itemdiscoveryid, itemid, lldruleid) values (35022, 15022, 15011);
INSERT INTO item_discovery (itemdiscoveryid, itemid, lldruleid) values (35023, 15023, 15011);
INSERT INTO item_discovery (itemdiscoveryid, itemid, lldruleid) values (35024, 15024, 15011);
INSERT INTO item_discovery (itemdiscoveryid, itemid, lldruleid) values (35025, 15025, 15011);
INSERT INTO item_discovery (itemdiscoveryid, itemid, lldruleid) values (35026, 15026, 15016);
INSERT INTO item_discovery (itemdiscoveryid, itemid, lldruleid) values (35027, 15027, 15016);
INSERT INTO item_discovery (itemdiscoveryid, itemid, lldruleid) values (35028, 15028, 15016);
INSERT INTO item_discovery (itemdiscoveryid, itemid, lldruleid) values (35029, 15029, 15016);
INSERT INTO item_discovery (itemdiscoveryid, itemid, lldruleid) values (35030, 15030, 15016);
INSERT INTO item_discovery (itemdiscoveryid, itemid, lldruleid) values (35031, 15095, 15011);
INSERT INTO item_discovery (itemdiscoveryid, itemid, lldruleid) values (35032, 15096, 15016);

INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description,query_fields, flags, posts, headers)                          VALUES (15083, 15002, 0, 'testInheritanceItemPrototype5', 'item-prototype-test5[{#KEY}]', '30s', 3, '', '','', 2, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, params, description, flags, interfaceid,query_fields, templateid, posts, headers) VALUES (15084, 15001, 0, 'testInheritanceItemPrototype5', 'item-prototype-test5[{#KEY}]', '30s', 3, '', '', 2, 15000,'', 15083, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, lldruleid) values (35083, 15083, 15081);
INSERT INTO item_discovery (itemdiscoveryid, itemid, lldruleid) values (35084, 15084, 15082);

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
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99737, 15021, 99008, 'last', '$');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99738, 15021, 99009, 'last', '$');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99739, 15021, 99010, 'last', '$');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99740, 15021, 99011, 'last', '$');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99741, 15026, 99012, 'last', '$');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99742, 15026, 99013, 'last', '$');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99743, 15026, 99014, 'last', '$');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (99744, 15026, 99015, 'last', '$');

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

INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params,query_fields, description, posts, headers)             VALUES (15031, 15000, 9, 'Download speed for scenario "testInheritanceWeb1".', 'web.test.in[testInheritanceWeb1,,bps]'                      , 60, 0, 'Bps', '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params,query_fields, description, posts, headers)             VALUES (15032, 15000, 9, 'Failed step of scenario "testInheritanceWeb1".', 'web.test.fail[testInheritanceWeb1]'                         , 60, 3, ''   , '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params,query_fields, description, posts, headers)             VALUES (15033, 15000, 9, 'Last error message of scenario "testInheritanceWeb1".', 'web.test.error[testInheritanceWeb1]'                        , 60, 1, ''   , '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params,query_fields, description, posts, headers)             VALUES (15034, 15000, 9, 'Download speed for step "testInheritanceWeb1" of scenario "testInheritanceWeb1".', 'web.test.in[testInheritanceWeb1,testInheritanceWeb1,bps]'   , 60, 0, 'Bps', '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params,query_fields, description, posts, headers)             VALUES (15035, 15000, 9, 'Response time for step "testInheritanceWeb1" of scenario "testInheritanceWeb1".', 'web.test.time[testInheritanceWeb1,testInheritanceWeb1,resp]', 60, 0, 's'  , '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params,query_fields, description, posts, headers)             VALUES (15036, 15000, 9, 'Response code for step "testInheritanceWeb1" of scenario "testInheritanceWeb1".', 'web.test.rspcode[testInheritanceWeb1,testInheritanceWeb1]'  , 60, 3, ''   , '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params,query_fields, description, posts, headers)             VALUES (15037, 15000, 9, 'Download speed for scenario "testInheritanceWeb2".', 'web.test.in[testInheritanceWeb2,,bps]'                      , 60, 0, 'Bps', '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params,query_fields, description, posts, headers)             VALUES (15038, 15000, 9, 'Failed step of scenario "testInheritanceWeb2".', 'web.test.fail[testInheritanceWeb2]'                         , 60, 3, ''   , '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params,query_fields, description, posts, headers)             VALUES (15039, 15000, 9, 'Last error message of scenario "testInheritanceWeb2".', 'web.test.error[testInheritanceWeb2]'                        , 60, 1, ''   , '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params,query_fields, description, posts, headers)             VALUES (15040, 15000, 9, 'Download speed for step "testInheritanceWeb2" of scenario "testInheritanceWeb2".', 'web.test.in[testInheritanceWeb2,testInheritanceWeb2,bps]'   , 60, 0, 'Bps', '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params,query_fields, description, posts, headers)             VALUES (15041, 15000, 9, 'Response time for step "testInheritanceWeb2" of scenario "testInheritanceWeb2".', 'web.test.time[testInheritanceWeb2,testInheritanceWeb2,resp]', 60, 0, 's'  , '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params,query_fields, description, posts, headers)             VALUES (15042, 15000, 9, 'Response code for step "testInheritanceWeb2" of scenario "testInheritanceWeb2".', 'web.test.rspcode[testInheritanceWeb2,testInheritanceWeb2]'  , 60, 3, ''   , '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params,query_fields, description, posts, headers)             VALUES (15043, 15000, 9, 'Download speed for scenario "testInheritanceWeb3".', 'web.test.in[testInheritanceWeb3,,bps]'                      , 60, 0, 'Bps', '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params,query_fields, description, posts, headers)             VALUES (15044, 15000, 9, 'Failed step of scenario "testInheritanceWeb3".', 'web.test.fail[testInheritanceWeb3]'                         , 60, 3, ''   , '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params,query_fields, description, posts, headers)             VALUES (15045, 15000, 9, 'Last error message of scenario "testInheritanceWeb3".', 'web.test.error[testInheritanceWeb3]'                        , 60, 1, ''   , '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params,query_fields, description, posts, headers)             VALUES (15046, 15000, 9, 'Download speed for step "testInheritanceWeb3" of scenario "testInheritanceWeb3".', 'web.test.in[testInheritanceWeb3,testInheritanceWeb3,bps]'   , 60, 0, 'Bps', '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params,query_fields, description, posts, headers)             VALUES (15047, 15000, 9, 'Response time for step "testInheritanceWeb3" of scenario "testInheritanceWeb3".', 'web.test.time[testInheritanceWeb3,testInheritanceWeb3,resp]', 60, 0, 's'  , '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params,query_fields, description, posts, headers)             VALUES (15048, 15000, 9, 'Response code for step "testInheritanceWeb3" of scenario "testInheritanceWeb3".', 'web.test.rspcode[testInheritanceWeb3,testInheritanceWeb3]'  , 60, 3, ''   , '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params,query_fields, description, posts, headers)             VALUES (15049, 15000, 9, 'Download speed for scenario "testInheritanceWeb4".', 'web.test.in[testInheritanceWeb4,,bps]'                      , 60, 0, 'Bps', '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params,query_fields, description, posts, headers)             VALUES (15050, 15000, 9, 'Failed step of scenario "testInheritanceWeb4".', 'web.test.fail[testInheritanceWeb4]'                         , 60, 3, ''   , '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params,query_fields, description, posts, headers)             VALUES (15051, 15000, 9, 'Last error message of scenario "testInheritanceWeb4".', 'web.test.error[testInheritanceWeb4]'                        , 60, 1, ''   , '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params,query_fields, description, posts, headers)             VALUES (15052, 15000, 9, 'Download speed for step "testInheritanceWeb4" of scenario "testInheritanceWeb4".', 'web.test.in[testInheritanceWeb4,testInheritanceWeb4,bps]'   , 60, 0, 'Bps', '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params,query_fields, description, posts, headers)             VALUES (15053, 15000, 9, 'Response time for step "testInheritanceWeb4" of scenario "testInheritanceWeb4".', 'web.test.time[testInheritanceWeb4,testInheritanceWeb4,resp]', 60, 0, 's'  , '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params,query_fields, description, posts, headers)             VALUES (15054, 15000, 9, 'Response code for step "testInheritanceWeb4" of scenario "testInheritanceWeb4".', 'web.test.rspcode[testInheritanceWeb4,testInheritanceWeb4]'  , 60, 3, ''   , '','', '', '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description,query_fields, templateid, posts, headers) VALUES (15055, 15001, 9, 'Download speed for scenario "testInheritanceWeb1".', 'web.test.in[testInheritanceWeb1,,bps]'                      , 60, 0, 'Bps', '', '','', 15031, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description,query_fields, templateid, posts, headers) VALUES (15056, 15001, 9, 'Failed step of scenario "testInheritanceWeb1".', 'web.test.fail[testInheritanceWeb1]'                         , 60, 3, ''   , '', '','', 15032, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description,query_fields, templateid, posts, headers) VALUES (15057, 15001, 9, 'Last error message of scenario "testInheritanceWeb1".', 'web.test.error[testInheritanceWeb1]'                        , 60, 1, ''   , '', '','', 15033, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description,query_fields, templateid, posts, headers) VALUES (15058, 15001, 9, 'Download speed for step "testInheritanceWeb1" of scenario "testInheritanceWeb1".', 'web.test.in[testInheritanceWeb1,testInheritanceWeb1,bps]'   , 60, 0, 'Bps', '', '','', 15034, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description,query_fields, templateid, posts, headers) VALUES (15059, 15001, 9, 'Response time for step "testInheritanceWeb1" of scenario "testInheritanceWeb1".', 'web.test.time[testInheritanceWeb1,testInheritanceWeb1,resp]', 60, 0, 's'  , '', '','', 15035, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description,query_fields, templateid, posts, headers) VALUES (15060, 15001, 9, 'Response code for step "testInheritanceWeb1" of scenario "testInheritanceWeb1".', 'web.test.rspcode[testInheritanceWeb1,testInheritanceWeb1]'  , 60, 3, ''   , '', '','', 15036, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description,query_fields, templateid, posts, headers) VALUES (15061, 15001, 9, 'Download speed for scenario "testInheritanceWeb2".' , 'web.test.in[testInheritanceWeb2,,bps]'                      , 60, 0, 'Bps', '', '','', 15037, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description,query_fields, templateid, posts, headers) VALUES (15062, 15001, 9, 'Failed step of scenario "testInheritanceWeb2".', 'web.test.fail[testInheritanceWeb2]'                         , 60, 3, ''   , '', '','', 15038, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description,query_fields, templateid, posts, headers) VALUES (15063, 15001, 9, 'Last error message of scenario "testInheritanceWeb2".', 'web.test.error[testInheritanceWeb2]'                        , 60, 1, ''   , '', '','', 15039, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description,query_fields, templateid, posts, headers) VALUES (15064, 15001, 9, 'Download speed for step "testInheritanceWeb2" of scenario "testInheritanceWeb2".', 'web.test.in[testInheritanceWeb2,testInheritanceWeb2,bps]'   , 60, 0, 'Bps', '', '','', 15040, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description,query_fields, templateid, posts, headers) VALUES (15065, 15001, 9, 'Response time for step "testInheritanceWeb2" of scenario "testInheritanceWeb2".', 'web.test.time[testInheritanceWeb2,testInheritanceWeb2,resp]', 60, 0, 's'  , '', '','', 15041, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description,query_fields, templateid, posts, headers) VALUES (15066, 15001, 9, 'Response code for step "testInheritanceWeb2" of scenario "testInheritanceWeb2".', 'web.test.rspcode[testInheritanceWeb2,testInheritanceWeb2]'  , 60, 3, ''   , '', '','', 15042, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description,query_fields, templateid, posts, headers) VALUES (15067, 15001, 9, 'Download speed for scenario "testInheritanceWeb3".', 'web.test.in[testInheritanceWeb3,,bps]'                      , 60, 0, 'Bps', '', '','', 15043, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description,query_fields, templateid, posts, headers) VALUES (15068, 15001, 9, 'Failed step of scenario "testInheritanceWeb3".', 'web.test.fail[testInheritanceWeb3]'                         , 60, 3, ''   , '', '','', 15044, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description,query_fields, templateid, posts, headers) VALUES (15069, 15001, 9, 'Last error message of scenario "testInheritanceWeb3".', 'web.test.error[testInheritanceWeb3]'                        , 60, 1, ''   , '', '','', 15045, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description,query_fields, templateid, posts, headers) VALUES (15070, 15001, 9, 'Download speed for step "testInheritanceWeb3" of scenario "testInheritanceWeb3".', 'web.test.in[testInheritanceWeb3,testInheritanceWeb3,bps]'   , 60, 0, 'Bps', '', '','', 15046, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description,query_fields, templateid, posts, headers) VALUES (15071, 15001, 9, 'Response time for step "testInheritanceWeb3" of scenario "testInheritanceWeb3".', 'web.test.time[testInheritanceWeb3,testInheritanceWeb3,resp]', 60, 0, 's'  , '', '','', 15047, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description,query_fields, templateid, posts, headers) VALUES (15072, 15001, 9, 'Response code for step "testInheritanceWeb3" of scenario "testInheritanceWeb3".', 'web.test.rspcode[testInheritanceWeb3,testInheritanceWeb3]'  , 60, 3, ''   , '', '','', 15048, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description,query_fields, templateid, posts, headers) VALUES (15073, 15001, 9, 'Download speed for scenario "testInheritanceWeb4".' , 'web.test.in[testInheritanceWeb4,,bps]'                      , 60, 0, 'Bps', '', '','', 15049, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description,query_fields, templateid, posts, headers) VALUES (15074, 15001, 9, 'Failed step of scenario "testInheritanceWeb4".', 'web.test.fail[testInheritanceWeb4]'                         , 60, 3, ''   , '', '','', 15050, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description,query_fields, templateid, posts, headers) VALUES (15075, 15001, 9, 'Last error message of scenario "testInheritanceWeb4".', 'web.test.error[testInheritanceWeb4]'                        , 60, 1, ''   , '', '','', 15051, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description,query_fields, templateid, posts, headers) VALUES (15076, 15001, 9, 'Download speed for step "testInheritanceWeb4" of scenario "testInheritanceWeb4".', 'web.test.in[testInheritanceWeb4,testInheritanceWeb4,bps]'   , 60, 0, 'Bps', '', '','', 15052, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description,query_fields, templateid, posts, headers) VALUES (15077, 15001, 9, 'Response time for step "testInheritanceWeb4" of scenario "testInheritanceWeb4".', 'web.test.time[testInheritanceWeb4,testInheritanceWeb4,resp]', 60, 0, 's'  , '', '','', 15053, '', '');
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, units, params, description,query_fields, templateid, posts, headers) VALUES (15078, 15001, 9, 'Response code for step "testInheritanceWeb4" of scenario "testInheritanceWeb4".', 'web.test.rspcode[testInheritanceWeb4,testInheritanceWeb4]'  , 60, 3, ''   , '', '','', 15054, '', '');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15055, 'Download speed for scenario "testInheritanceWeb1".', 'DOWNLOAD SPEED FOR SCENARIO "TESTINHERITANCEWEB1".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15056, 'Failed step of scenario "testInheritanceWeb1".', 'FAILED STEP OF SCENARIO "TESTINHERITANCEWEB1".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15057, 'Last error message of scenario "testInheritanceWeb1".', 'LAST ERROR MESSAGE OF SCENARIO "TESTINHERITANCEWEB1".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15058, 'Download speed for step "testInheritanceWeb1" of scenario "testInheritanceWeb1".', 'DOWNLOAD SPEED FOR STEP "TESTINHERITANCEWEB1" OF SCENARIO "TESTINHERITANCEWEB1".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15059, 'Response time for step "testInheritanceWeb1" of scenario "testInheritanceWeb1".', 'RESPONSE TIME FOR STEP "TESTINHERITANCEWEB1" OF SCENARIO "TESTINHERITANCEWEB1".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15060, 'Response code for step "testInheritanceWeb1" of scenario "testInheritanceWeb1".', 'RESPONSE CODE FOR STEP "TESTINHERITANCEWEB1" OF SCENARIO "TESTINHERITANCEWEB1".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15061, 'Download speed for scenario "testInheritanceWeb2".', 'DOWNLOAD SPEED FOR SCENARIO "TESTINHERITANCEWEB2".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15062, 'Failed step of scenario "testInheritanceWeb2".', 'FAILED STEP OF SCENARIO "TESTINHERITANCEWEB2".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15063, 'Last error message of scenario "testInheritanceWeb2".', 'LAST ERROR MESSAGE OF SCENARIO "TESTINHERITANCEWEB2".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15064, 'Download speed for step "testInheritanceWeb2" of scenario "testInheritanceWeb2".', 'DOWNLOAD SPEED FOR STEP "TESTINHERITANCEWEB2" OF SCENARIO "TESTINHERITANCEWEB2".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15065, 'Response time for step "testInheritanceWeb2" of scenario "testInheritanceWeb2".', 'RESPONSE TIME FOR STEP "TESTINHERITANCEWEB2" OF SCENARIO "TESTINHERITANCEWEB2".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15066, 'Response code for step "testInheritanceWeb2" of scenario "testInheritanceWeb2".', 'RESPONSE CODE FOR STEP "TESTINHERITANCEWEB2" OF SCENARIO "TESTINHERITANCEWEB2".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15067, 'Last error message of scenario "testInheritanceWeb2".', 'LAST ERROR MESSAGE OF SCENARIO "TESTINHERITANCEWEB2".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15068, 'Failed step of scenario "testInheritanceWeb3".', 'FAILED STEP OF SCENARIO "TESTINHERITANCEWEB3".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15069, 'Last error message of scenario "testInheritanceWeb3".', 'LAST ERROR MESSAGE OF SCENARIO "TESTINHERITANCEWEB3".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15070, 'Download speed for step "testInheritanceWeb3" of scenario "testInheritanceWeb3".', 'DOWNLOAD SPEED FOR STEP "TESTINHERITANCEWEB3" OF SCENARIO "TESTINHERITANCEWEB3".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15071, 'Response time for step "testInheritanceWeb3" of scenario "testInheritanceWeb3".', 'RESPONSE TIME FOR STEP "TESTINHERITANCEWEB3" OF SCENARIO "TESTINHERITANCEWEB3".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15072, 'Response code for step "testInheritanceWeb3" of scenario "testInheritanceWeb3".', 'RESPONSE CODE FOR STEP "TESTINHERITANCEWEB3" OF SCENARIO "TESTINHERITANCEWEB3".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15073, 'Download speed for scenario "testInheritanceWeb4".', 'DOWNLOAD SPEED FOR SCENARIO "TESTINHERITANCEWEB4".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15074, 'Failed step of scenario "testInheritanceWeb4".', 'FAILED STEP OF SCENARIO "TESTINHERITANCEWEB4".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15075, 'Last error message of scenario "testInheritanceWeb4".', 'LAST ERROR MESSAGE OF SCENARIO "TESTINHERITANCEWEB4".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15076, 'Download speed for step "testInheritanceWeb4" of scenario "testInheritanceWeb4".', 'DOWNLOAD SPEED FOR STEP "TESTINHERITANCEWEB4" OF SCENARIO "TESTINHERITANCEWEB4".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15077, 'Response time for step "testInheritanceWeb4" of scenario "testInheritanceWeb4".', 'RESPONSE TIME FOR STEP "TESTINHERITANCEWEB4" OF SCENARIO "TESTINHERITANCEWEB4".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15078, 'Response code for step "testInheritanceWeb4" of scenario "testInheritanceWeb4".', 'RESPONSE CODE FOR STEP "TESTINHERITANCEWEB4" OF SCENARIO "TESTINHERITANCEWEB4".');

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
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (40000, 'Form test template', 'Form test template', 3, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (40000, 40000, 1);

-- create Simple form test
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (40001, 'Simple form test host', 'Simple form test host', 0, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (40001, 40001, 4);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (40000, 40001, 40000);
INSERT INTO valuemap (valuemapid, hostid, name) VALUES (5601, 40001, 'Reference valuemap');
INSERT INTO valuemap_mapping (valuemap_mappingid, valuemapid, value, newvalue, sortorder) VALUES (56001, 5601, 1, 'One', 0);
INSERT INTO valuemap_mapping (valuemap_mappingid, valuemapid, value, newvalue, sortorder) VALUES (56002, 5601, 2, 'Two', 1);
INSERT INTO valuemap_mapping (valuemap_mappingid, valuemapid, value, newvalue, sortorder) VALUES (56003, 5601, 3, 'Three', 2);
INSERT INTO valuemap_mapping (valuemap_mappingid, valuemapid, value, newvalue, sortorder) VALUES (56004, 5601, 4, 'Four', 3);
INSERT INTO valuemap (valuemapid, hostid, name) VALUES (5602, 40001, 'Второй валъю мап');
INSERT INTO valuemap_mapping (valuemap_mappingid, valuemapid, value, newvalue, sortorder) VALUES (56005, 5602, 1, 'один', 0);
INSERT INTO valuemap_mapping (valuemap_mappingid, valuemapid, value, newvalue, sortorder) VALUES (56006, 5602, 2, 'два', 1);

-- testFormItem interfaces
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, port) VALUES (40011, 40001, 1, 1, 1, '127.0.5.1', '10051');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, port) VALUES (40012, 40001, 1, 2, 1, '127.0.5.2', '10052');
INSERT INTO interface_snmp (interfaceid, version, bulk, community) values (40012, 2, 1, '{$SNMP_COMMUNITY}');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, port) VALUES (40013, 40001, 1, 3, 1, '127.0.5.3', '10053');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, port) VALUES (40014, 40001, 1, 4, 1, '127.0.5.4', '10054');

-- testFormItem.LayoutCheck testFormItem.SimpleUpdate
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params,query_fields, formula, posts, headers) VALUES (99098, 0, 40001, 'testFormItem1', 'testFormItems', 'test-item-form1', 30, 40011, '','', 1, '', '');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params,query_fields, formula, posts, headers) VALUES (99099, 0, 40001, 'testFormItem2', 'testFormItems', 'test-item-form2', 30, 40011, '','', 1, '', '');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params,query_fields, formula, posts, headers) VALUES (99100, 0, 40001, 'testFormItem3', 'testFormItems', 'test-item-form3', 30, 40011, '','', 1, '', '');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params,query_fields, formula, posts, headers) VALUES (99101, 0, 40001, 'testFormItem4', 'testFormItems', 'test-item-form4', 30, 40011, '','', 1, '', '');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99098, 'testFormItem1', 'TESTFORMITEM1');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99099, 'testFormItem2', 'TESTFORMITEM2');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99100, 'testFormItem3', 'TESTFORMITEM3');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99101, 'testFormItem4', 'TESTFORMITEM4');

-- testFormTrigger.SimpleCreate
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, history, trends, status, value_type, trapper_hosts, units, logtimefmt, templateid, valuemapid, params, ipmi_sensor, authtype, username, password, publickey, privatekey, flags,query_fields, interfaceid, posts, headers) VALUES (99102, 0, 40001, 'testFormItem', 'testFormItems', 'test-item-reuse', '30s', '90d', '365d', 0, 0, '', '', '', NULL, NULL, '', '', 0, '', '', '', '', 0,'', 40011, '', '');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99102, 'testFormItem', 'TESTFORMITEM');

-- testFormTrigger.SimpleUpdate
INSERT INTO triggers (triggerid, expression, description, comments) VALUES (14000, '{14000}=0', 'testFormTrigger1', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (14000, 99102, 14000, 'last', '$,#1');

INSERT INTO triggers (triggerid, expression, description, comments) VALUES (14001, '{14001}=0', 'testFormTrigger2', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (14001, 99102, 14001, 'last', '$,#1');

INSERT INTO triggers (triggerid, expression, description, comments) VALUES (14002, '{14002}=0', 'testFormTrigger3', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (14002, 99102, 14002, 'last', '$,#1');

INSERT INTO triggers (triggerid, expression, description, comments) VALUES (14003, '{14003}=0', 'testFormTrigger4', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (14003, 99102, 14003, 'last', '$,#1');

-- testFormGraph.LayoutCheck testFormGraph.SimpleUpdate
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (300000,'testFormGraph1',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,0);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (300001,'testFormGraph2',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,0);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (300002,'testFormGraph3',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,0);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (300003,'testFormGraph4',900,200,0.0,100.0,NULL,1,0,1,1,0,0.0,0.0,1,1,NULL,NULL,0);

-- testFormGraph.LayoutCheck testFormGraph.SimpleUpdate
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (1300000, 300000, 99102, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (1300001, 300001, 99102, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (1300002, 300002, 99102, 1, 1, 'FF5555', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (1300003, 300003, 99102, 1, 1, 'FF5555', 0, 2, 0);

-- testFormDiscoveryRule.SimpleUpdate
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description,query_fields, interfaceid, posts, headers) VALUES ('testFormDiscoveryRule1', 'discovery-rule-form1', 40001, 4, 10080, 1,  50, '', '','', 40011, '', '');
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description,query_fields, interfaceid, posts, headers) VALUES ('testFormDiscoveryRule2', 'discovery-rule-form2', 40001, 4, 10081, 1,  50, '', '','', 40011, '', '');
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description,query_fields, interfaceid, posts, headers) VALUES ('testFormDiscoveryRule3', 'discovery-rule-form3', 40001, 4, 10082, 1,  50, '', '','', 40011, '', '');
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description,query_fields, interfaceid, posts, headers) VALUES ('testFormDiscoveryRule4', 'discovery-rule-form4', 40001, 4, 10083, 1,  50, '', '','', 40011, '', '');

-- testFormItemPrototype.SimpleUpdate
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description,query_fields, interfaceid, posts, headers) VALUES ('testFormDiscoveryRule', 'discovery-rule-form', 40001, 4, 133800, 1,  50, '', '','', 40011, '', '');

-- testFormItemPrototype.SimpleUpdate
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description,query_fields, interfaceid, posts, headers) VALUES ('testFormItemPrototype1', 'item-prototype-form1[{#KEY}]', 40001, 3, 23800, 2, 5, '', '','', 40011, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, lldruleid) values (39501, 23800, 133800);
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description,query_fields, interfaceid, posts, headers) VALUES ('testFormItemPrototype2', 'item-prototype-form2[{#KEY}]', 40001, 3, 23801, 2, 5, '', '','', 40011, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, lldruleid) values (39502, 23801, 133800);
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description,query_fields, interfaceid, posts, headers) VALUES ('testFormItemPrototype3', 'item-prototype-form3[{#KEY}]', 40001, 3, 23802, 2, 5, '', '','', 40011, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, lldruleid) values (39503, 23802, 133800);
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description,query_fields, interfaceid, posts, headers) VALUES ('testFormItemPrototype4', 'item-prototype-form4[{#KEY}]', 40001, 3, 23803, 2, 5, '', '','', 40011, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, lldruleid) values (39504, 23803, 133800);

-- testFormTriggerPrototype.SimpleCreate
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params, description,query_fields, interfaceid, posts, headers) VALUES ('testFormItemReuse', 'item-prototype-reuse[{#KEY}]', 40001, 3, 23804, 2, 5, '', '','', 40011, '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, lldruleid) values (39505, 23804, 133800);

-- testFormTriggerPrototype.SimpleUpdate
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (99518,'{99947}=0','testFormTriggerPrototype1','',0,0,0,0,'','',NULL,0,0,2);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (99519,'{99948}=0','testFormTriggerPrototype2','',0,0,0,0,'','',NULL,0,0,2);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (99520,'{99949}=0','testFormTriggerPrototype3','',0,0,0,0,'','',NULL,0,0,2);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (99521,'{99950}=0','testFormTriggerPrototype4','',0,0,0,0,'','',NULL,0,0,2);
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (99947,23804,99518,'last','$,#1');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (99948,23804,99519,'last','$,#2');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (99949,23804,99520,'last','$,#4');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (99950,23804,99521,'last','$,#1');

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

-- testZBX6663.MassSelect
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (50000, 'Template ZBX6663 First', 'Template ZBX6663 First', 3, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50000, 50000, 1);
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (50002, 'Template ZBX6663 Second', 'Template ZBX6663 Second', 3, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50001, 50002, 1);
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (50001, 'Host ZBX6663','Host ZBX6663', 0, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50002, 50001, 4);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (50000, 50001, 50002);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (50002, 50000, 50002);
INSERT INTO interface (type, ip, dns, useip, port, main, hostid, interfaceid) VALUES (1, '127.0.7.1', '', '1', '10071', '1', 50001, 50015);
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400080,9,50000,'Download speed for scenario "$1".','web.test.in[Web ZBX6663 First,,bps]','60s','30d','90d',0,0,'','Bps','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400090,9,50000,'Failed step of scenario "$1".','web.test.fail[Web ZBX6663 First]','60s','30d','90d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400100,9,50000,'Last error message of scenario "$1".','web.test.error[Web ZBX6663 First]','60s','30d','90d',0,1,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400110,9,50000,'Download speed for step "$2" of scenario "$1".','web.test.in[Web ZBX6663 First,Web ZBX6663 First Step,bps]','60s','30d','90d',0,0,'','Bps','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400120,9,50000,'Response time for step "$2" of scenario "$1".','web.test.time[Web ZBX6663 First,Web ZBX6663 First Step,resp]','60s','30d','90d',0,0,'','s','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400130,9,50000,'Response code for step "$2" of scenario "$1".','web.test.rspcode[Web ZBX6663 First,Web ZBX6663 First Step]','60s','30d','90d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400140,9,50002,'Download speed for scenario "$1".','web.test.in[Web ZBX6663 Second,,bps]','60s','30d','90d',0,0,'','Bps','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400150,9,50002,'Failed step of scenario "$1".','web.test.fail[Web ZBX6663 Second]','60s','30d','90d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400160,9,50002,'Last error message of scenario "$1".','web.test.error[Web ZBX6663 Second]','60s','30d','90d',0,1,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400170,9,50002,'Download speed for step "$2" of scenario "$1".','web.test.in[Web ZBX6663 Second,Web ZBX6663 Second Step,bps]','60s','30d','90d',0,0,'','Bps','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400180,9,50002,'Response time for step "$2" of scenario "$1".','web.test.time[Web ZBX6663 Second,Web ZBX6663 Second Step,resp]','60s','30d','90d',0,0,'','s','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400190,9,50002,'Response code for step "$2" of scenario "$1".','web.test.rspcode[Web ZBX6663 Second,Web ZBX6663 Second Step]','60s','30d','90d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400200,9,50001,'Download speed for scenario "$1".','web.test.in[Web ZBX6663 Second,,bps]','60s','30d','90d',0,0,'','Bps','',400140,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400210,9,50001,'Failed step of scenario "$1".','web.test.fail[Web ZBX6663 Second]','60s','30d','90d',0,3,'','','',400150,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400220,9,50001,'Last error message of scenario "$1".','web.test.error[Web ZBX6663 Second]','60s','30d','90d',0,1,'','','',400160,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400230,9,50001,'Download speed for step "$2" of scenario "$1".','web.test.in[Web ZBX6663 Second,Web ZBX6663 Second Step,bps]','60s','30d','90d',0,0,'','Bps','',400170,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400240,9,50001,'Response time for step "$2" of scenario "$1".','web.test.time[Web ZBX6663 Second,Web ZBX6663 Second Step,resp]','60s','30d','90d',0,0,'','s','',400180,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400250,9,50001,'Response code for step "$2" of scenario "$1".','web.test.rspcode[Web ZBX6663 Second,Web ZBX6663 Second Step]','60s','30d','90d',0,3,'','','',400190,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400260,9,50000,'Download speed for scenario "$1".','web.test.in[Web ZBX6663 Second,,bps]','60s','30d','90d',0,0,'','Bps','',400140,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400270,9,50000,'Failed step of scenario "$1".','web.test.fail[Web ZBX6663 Second]','60s','30d','90d',0,3,'','','',0400150,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400280,9,50000,'Last error message of scenario "$1".','web.test.error[Web ZBX6663 Second]','60s','30d','90d',0,1,'','','',400160,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400290,9,50000,'Download speed for step "$2" of scenario "$1".','web.test.in[Web ZBX6663 Second,Web ZBX6663 Second Step,bps]','60s','30d','90d',0,0,'','Bps','',400170,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400300,9,50000,'Response time for step "$2" of scenario "$1".','web.test.time[Web ZBX6663 Second,Web ZBX6663 Second Step,resp]','60s','30d','90d',0,0,'','s','',400180,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400310,9,50000,'Response code for step "$2" of scenario "$1".','web.test.rspcode[Web ZBX6663 Second,Web ZBX6663 Second Step]','60s','30d','90d',0,3,'','','',400190,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400320,9,50001,'Download speed for scenario "$1".','web.test.in[Web ZBX6663,,bps]','60s','30s','90d',0,0,'','Bps','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400330,9,50001,'Failed step of scenario "$1".','web.test.fail[Web ZBX6663]','60s','30d','90d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400340,9,50001,'Last error message of scenario "$1".','web.test.error[Web ZBX6663]','60s','30d','90d',0,1,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400350,9,50001,'Download speed for step "$2" of scenario "$1".','web.test.in[Web ZBX6663,Web ZBX6663 Step,bps]','60s','30d','90d',0,0,'','Bps','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400360,9,50001,'Response time for step "$2" of scenario "$1".','web.test.time[Web ZBX6663,Web ZBX6663 Step,resp]','60s','30d','90d',0,0,'','s','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400370,9,50001,'Response code for step "$2" of scenario "$1".','web.test.rspcode[Web ZBX6663,Web ZBX6663 Step]','60s','30d','90d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400380,0,50002,'Item ZBX6663 Second','item-ZBX6663-second','30s','90d','365d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400390,0,50001,'Item ZBX6663 Second','item-ZBX6663-second','30s','90d','365d',0,3,'','','',400380,NULL,'','',0,'','','','',0,50015,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400400,0,50000,'Item ZBX6663 Second','item-ZBX6663-second','30s','90d','365d',0,3,'','','',400380,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400410,0,50000,'Item ZBX6663 First','item-ZBX6663-first','30s','90d','365d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400420,0,50001,'Item ZBX6663','item-ZBX6663','30s','90d','365d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,50015,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400430,0,50001,'DiscoveryRule ZBX6663','drule-zbx6663','30s','90d','365d',0,4,'','','',NULL,NULL,'','',0,'','','','',1,50015,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400450,0,50002,'DiscoveryRule ZBX6663 Second','drule-ZBX6663-second','30s','90d','365d',0,4,'','','',NULL,NULL,'','',0,'','','','',1,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400460,0,50001,'DiscoveryRule ZBX6663 Second','drule-ZBX6663-second','30s','90d','365d',0,4,'','','',400450,NULL,'','',0,'','','','',1,50015,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400470,0,50000,'DiscoveryRule ZBX6663 Second','drule-ZBX6663-second','30s','90d','365d',0,4,'','','',400450,NULL,'','',0,'','','','',1,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400480,0,50002,'ItemProto ZBX6663 Second','item-proto-zbx6663-second[{#KEY}]','30s','90d','365d',0,3,'','','',NULL,NULL,'','',0,'','','','',2,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400490,0,50001,'ItemProto ZBX6663 Second','item-proto-zbx6663-second[{#KEY}]','30s','90d','365d',0,3,'','','',400480,NULL,'','',0,'','','','',2,50015,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400500,0,50000,'ItemProto ZBX6663 Second','item-proto-zbx6663-second[{#KEY}]','30s','90d','365d',0,3,'','','',400480,NULL,'','',0,'','','','',2,NULL,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400510,0,50000,'DiscoveryRule ZBX6663 First','drule-zbx6663-first','30s','90d','365d',0,4,'','','',NULL,NULL,'','',0,'','','','',1,NULL,'',0,'','3600','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400520,0,50001,'ItemProto ZBX6663 HSecond','item-proto-zbx6663-hsecond[{#KEY}]','30s','90d','365d',0,3,'','','',NULL,NULL,'','',0,'','','','',2,50015,'',0,'','30d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400540,0,50000,'ItemProto ZBX6663 TSecond','item-proto-zbx6663-tsecond[{#KEY}]','30s','90d','365d',0,3,'','','',NULL,NULL,'','',0,'','','','',2,NULL,'',0,'','30d','','');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (400200, 'Download speed for scenario "$1".', 'DOWNLOAD SPEED FOR SCENARIO "$1".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (400210, 'Failed step of scenario "$1".', 'FAILED STEP OF SCENARIO "$1".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (400220, 'Last error message of scenario "$1".', 'LAST ERROR MESSAGE OF SCENARIO "$1".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (400230, 'Download speed for step "$2" of scenario "$1".', 'DOWNLOAD SPEED FOR STEP "$2" OF SCENARIO "$1".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (400240, 'Response time for step "$2" of scenario "$1".', 'RESPONSE TIME FOR STEP "$2" OF SCENARIO "$1".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (400250, 'Response code for step "$2" of scenario "$1".', 'RESPONSE CODE FOR STEP "$2" OF SCENARIO "$1".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (400320, 'Download speed for scenario "$1".', 'DOWNLOAD SPEED FOR SCENARIO "$1".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (400330, 'Failed step of scenario "$1".', 'FAILED STEP OF SCENARIO "$1".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (400340, 'Last error message of scenario "$1".', 'LAST ERROR MESSAGE OF SCENARIO "$1".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (400350, 'Download speed for step "$2" of scenario "$1".', 'DOWNLOAD SPEED FOR STEP "$2" OF SCENARIO "$1".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (400360, 'Response time for step "$2" of scenario "$1".', 'RESPONSE TIME FOR STEP "$2" OF SCENARIO "$1".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (400370, 'Response code for step "$2" of scenario "$1".', 'RESPONSE CODE FOR STEP "$2" OF SCENARIO "$1".');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (400390, 'Item ZBX6663 Second', 'ITEM ZBX6663 SECOND');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (400420, 'Item ZBX6663', 'ITEM ZBX6663');

INSERT INTO item_discovery (itemdiscoveryid,itemid,lldruleid,key_,lastcheck,ts_delete) VALUES (39507,400480,400450,'',0,0);
INSERT INTO item_discovery (itemdiscoveryid,itemid,lldruleid,key_,lastcheck,ts_delete) VALUES (39508,400490,400460,'',0,0);
INSERT INTO item_discovery (itemdiscoveryid,itemid,lldruleid,key_,lastcheck,ts_delete) VALUES (39509,400500,400470,'',0,0);
INSERT INTO item_discovery (itemdiscoveryid,itemid,lldruleid,key_,lastcheck,ts_delete) VALUES (39510,400520,400460,'',0,0);
INSERT INTO item_discovery (itemdiscoveryid,itemid,lldruleid,key_,lastcheck,ts_delete) VALUES (39512,400540,400470,'',0,0);
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
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100008,400380,100008,'last','$,#1');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100009,400390,100009,'last','$,#1');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100010,400400,100010,'last','$,#1');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100011,400410,100011,'last','$,#1');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100012,400420,100012,'last','$,#1');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100013,400540,100013,'last','$,#1');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100014,400480,100014,'last','$,#1');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100015,400490,100015,'last','$,#1');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100016,400500,100016,'last','$,#1');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100017,400520,100017,'last','$,#1');
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
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700016,700008,400420,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700017,700009,400380,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700018,700010,400390,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700019,700011,400400,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700020,700012,400410,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700021,700013,400480,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700022,700014,400490,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700023,700015,400500,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700024,700016,400540,0,0,'C80000',0,2,0);
INSERT INTO graphs_items (gitemid,graphid,itemid,drawtype,sortorder,color,yaxisside,calc_fnc,type) VALUES (700025,700017,400520,0,0,'C80000',0,2,0);
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
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (922,98,400080,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (923,98,400090,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (924,98,400100,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (925,99,400140,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (926,99,400150,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (927,99,400160,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (928,100,400200,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (929,100,400210,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (930,100,400220,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (931,101,400260,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (932,101,400270,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (933,101,400280,4);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (934,102,400320,2);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (935,102,400330,3);
INSERT INTO httptestitem (httptestitemid,httptestid,itemid,type) VALUES (936,102,400340,4);

INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (922,98,400110,0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (923,98,400120,1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (924,98,400130,2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (925,99,400170,0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (926,99,400180,1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (927,99,400190,2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (928,100,400230,0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (929,100,400240,1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (930,100,400250,2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (931,101,400290,0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (932,101,400300,1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (933,101,400310,2);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (934,102,400350,0);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (935,102,400360,1);
INSERT INTO httpstepitem (httpstepitemid,httpstepid,itemid,type) VALUES (936,102,400370,2);

-- testPageItems, testPageTriggers, testPageDiscoveryRules, testPageItemPrototype, testPageTriggerPrototype
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (50006, 'Template-layout-test-001', 'Template-layout-test-001', 3, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50006, 50006, 1);
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (50007, 'Host-layout-test-001', 'Host-layout-test-001', 0, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50007, 50007, 4);
INSERT INTO interface (type, ip, dns, useip, port, main, hostid, interfaceid) VALUES (1, '127.0.7.1', '', '1', '10071', '1', 50007, 50019);
INSERT INTO interface (type, ip, dns, useip, port, main, hostid, interfaceid) VALUES (1, '127.0.7.1', '', '1', '10071', '1', 50006, 50020);
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400580,0,50006,'Discovery-rule-layout-test-001','drule-layout-test001','30s','90d','365d',1,4,'','','',NULL,NULL,'','',0,'','','','',1,NULL,'',0,'','50d','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400590,0,50007,'Discovery-rule-layout-test-002','drule-layout-test002','30s','90d','365d',0,4,'','','',NULL,NULL,'','',0,'','','','',1,NULL,'',0,'','30','','');
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params,query_fields, description,posts,headers) VALUES ('Item-proto-layout-test-001', 'item-proto-layout-test001', 50006, 3, 400600, 2, 5, '','', '','','');
INSERT INTO item_discovery (itemdiscoveryid, itemid, lldruleid) values (39513, 400600, 400580);
INSERT INTO items (name, key_, hostid, value_type, itemid, flags, delay, params,query_fields, description,posts,headers) VALUES ('Item-proto-layout-test-002', 'item-proto-layout-test002', 50007, 3, 400610, 2, 5, '','', '','','');
INSERT INTO item_discovery (itemdiscoveryid, itemid, lldruleid) values (39514, 400610, 400590);
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400620,0,50006,'Item-layout-test-001','item-layout-test-001','30s','90d','365d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,50020,'',0,'','30','','');
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400630,0,50007,'Item-layout-test-002','item-layout-test-002','30s','90d','365d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,50019,'{{$A}}',0,'','30','','');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (400630, 'Item-layout-test-002', 'ITEM-LAYOUT-TEST-002');
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (100022,'{100022}=0','Trigger-proto-layout-test-001','',0,0,0,0,'','',NULL,0,0,2);
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100022,400600,100022,'last','$,#1');
INSERT INTO triggers (triggerid, expression, description, comments, flags) VALUES (100023, '{100023}=0', 'Trigger-proto-layout-test-001', '', 2);
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100023, 400610, 100023,'last','$,#1');
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (100024,'{100024}=0','Trigger-layout-test-001','',1,0,0,0,'','',NULL,0,0,0);
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (100025,'{100025}=0','Trigger-layout-test-002','',0,0,0,0,'','',NULL,0,0,0);
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100024,400630,100024,'last','$,#1');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100025,400620,100025,'last','$,#1');

-- testFormMap.ZBX6840
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (50008, 'Host-map-test-zbx6840', 'Host-map-test-zbx6840', 0, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50008, 50008, 4);
INSERT INTO interface (type, ip, dns, useip, port, main, hostid, interfaceid) VALUES (1, '127.0.7.1', '', '1', '10071', '1', 50008, 50021);
INSERT INTO items (itemid,type,hostid,name,key_,delay,history,trends,status,value_type,trapper_hosts,units,logtimefmt,templateid,valuemapid,params,ipmi_sensor,authtype,username,password,publickey,privatekey,flags,interfaceid,description,inventory_link,query_fields,lifetime,posts,headers) VALUES (400650,0,50008,'Item-layout-test-zbx6840','item-layout-test-002','30s','90d','365d',0,3,'','','',NULL,NULL,'','',0,'','','','',0,50021,'',0,'','30','','');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (400650, 'Item-layout-test-zbx6840', 'ITEM-LAYOUT-TEST-ZBX6840');
INSERT INTO triggers (triggerid,expression,description,url,status,value,priority,lastchange,comments,error,templateid,type,state,flags) VALUES (100026,'{100026}=0 and {100027}=0','Trigger-map-test-zbx6840','',0,0,0,0,'','',NULL,0,0,0);
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100026,400650,100026,'last','$,#1');
INSERT INTO functions (functionid,itemid,triggerid,name,parameter) VALUES (100027,42237,100026,'last','$,#1');
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, grid_size, grid_show, grid_align, label_format, label_type_host, label_type_hostgroup, label_type_trigger, label_type_map, label_type_image, label_string_host, label_string_hostgroup, label_string_trigger, label_string_map, label_string_image, iconmapid, expand_macros, severity_min, userid, private) VALUES (5, 'testZBX6840', 800, 600, NULL, 0, 0, 0, 0, 0, 0, 50, 1, 1, 0, 2, 2, 2, 2, 2, '', '', '', '', '', NULL, 0, 0, 1, 0);
INSERT INTO sysmaps_elements (selementid,sysmapid,elementid,elementtype,iconid_off,iconid_on,label,label_location,x,y,iconid_disabled,iconid_maintenance,elementsubtype,areatype,width,height,viewtype,use_iconmap) VALUES (8,5,10084,0,19,NULL,'Host element (Zabbix Server)',-1,413,268,NULL,NULL,0,0,200,200,0,0);
INSERT INTO sysmaps_elements (selementid,sysmapid,elementid,elementtype,iconid_off,iconid_on,label,label_location,x,y,iconid_disabled,iconid_maintenance,elementsubtype,areatype,width,height,viewtype,use_iconmap) VALUES (9,5,0,2,15,NULL,'Trigger element (zbx6840)',-1,213,218,NULL,NULL,0,0,200,200,0,0);
INSERT INTO sysmap_element_trigger (selement_triggerid, selementid, triggerid) VALUES (2,9,100026);

-- testPageHistory_CheckLayout

INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (15003, 'testPageHistory_CheckLayout', 'testPageHistory_CheckLayout', 0, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (15003, 15003, 4);
INSERT INTO interface (interfaceid, hostid, type, ip, useip, port, main) VALUES (15005, 15003, 1, '127.0.0.1', 1, '10050', 1);

INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history, trends, status, units, valuemapid, params, description,query_fields, flags, posts, headers) VALUES (15085, 15003, 15005, 0, 3, 'item_testPageHistory_CheckLayout_Numeric_Unsigned', 'numeric_unsigned[item_testpagehistory_checklayout]', '30s', '90d', '365d', 0, '', NULL, '', '','', 0, '', '');
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history, trends, status, units, valuemapid, params, description,query_fields, flags, posts, headers) VALUES (15086, 15003, 15005, 0, 0, 'item_testPageHistory_CheckLayout_Numeric_Float'   , 'numeric_float[item_testpagehistory_checklayout]'   , '30s', '90d', '365d', 0, '', NULL, '', '','', 0, '', '');
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history,         status,                    params, description,query_fields, flags, posts, headers) VALUES (15087, 15003, 15005, 0, 1, 'item_testPageHistory_CheckLayout_Character'       , 'character[item_testpagehistory_checklayout]'       , '30s', '90d',      0,           '', 'http://zabbix.com https://www.zabbix.com/career https://www.zabbix.com/contact','', 0, '', '');
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history,         status,                    params, description,query_fields, flags, posts, headers) VALUES (15088, 15003, 15005, 0, 4, 'item_testPageHistory_CheckLayout_Text'            , 'text[item_testpagehistory_checklayout]'            , '30s', '90d',      0,           '', 'These urls should be clickable: https://zabbix.com https://www.zabbix.com/career','', 0, '', '');
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history,         status,                    params, description,query_fields, flags, posts, headers) VALUES (15089, 15003, 15005, 0, 2, 'item_testPageHistory_CheckLayout_Log'             , 'log[item_testpagehistory_checklayout]'             , '30s', '90d',      0,           '', '','', 0, '', '');
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history,         status,                    params, description,query_fields, flags, posts, headers) VALUES (15090, 15003, 15005, 0, 2, 'item_testPageHistory_CheckLayout_Log_2'           , 'log[item_testpagehistory_checklayout, 2]'          , '30s', '90d',      0,           '', 'Non-clickable description','', 0, '', '');
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history,         status,                    params, description,query_fields, flags, posts, headers) VALUES (15091, 15003, 15005, 0, 2, 'item_testPageHistory_CheckLayout_Eventlog'        , 'eventlog[item_testpagehistory_checklayout]'        , '30s', '90d',      0,           '', 'https://zabbix.com','', 0, '', '');
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history,         status,                    params, description,query_fields, flags, posts, headers) VALUES (15092, 15003, 15005, 0, 2, 'item_testPageHistory_CheckLayout_Eventlog_2'      , 'eventlog[item_testpagehistory_checklayout, 2]'     , '30s', '90d',      0,           '', 'The following url should be clickable: https://zabbix.com','', 0, '', '');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15085, 'item_testPageHistory_CheckLayout_Numeric_Unsigned', 'ITEM_TESTPAGEHISTORY_CHECKLAYOUT_NUMERIC_UNSIGNED');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15086, 'item_testPageHistory_CheckLayout_Numeric_Float', 'ITEM_TESTPAGEHISTORY_CHECKLAYOUT_NUMERIC_FLOAT');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15087, 'item_testPageHistory_CheckLayout_Character', 'ITEM_TESTPAGEHISTORY_CHECKLAYOUT_CHARACTER');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15088, 'item_testPageHistory_CheckLayout_Text', 'ITEM_TESTPAGEHISTORY_CHECKLAYOUT_TEXT');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15089, 'item_testPageHistory_CheckLayout_Log', 'ITEM_TESTPAGEHISTORY_CHECKLAYOUT_LOG');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15090, 'item_testPageHistory_CheckLayout_Log_2', 'ITEM_TESTPAGEHISTORY_CHECKLAYOUT_LOG_2');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15091, 'item_testPageHistory_CheckLayout_Eventlog', 'ITEM_TESTPAGEHISTORY_CHECKLAYOUT_EVENTLOG');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (15092, 'item_testPageHistory_CheckLayout_Eventlog_2', 'ITEM_TESTPAGEHISTORY_CHECKLAYOUT_EVENTLOG_2');

-- testFormFilterProblems, testFormFilterHosts
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (92, 'filter-create', '$2y$10$nA7hh4cZ5oHM.GgXPqzZ/e/vaD1LYcOi.3ZfulCjZV/9H4PFtIKnK', 0, 0, 'default', 30, 3, 'default', 0, 0, 50);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (106, 7, 92);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (93, 'filter-delete', '$2y$10$z9toljmutmrQqkrl6BZiGO2kvQNcfN4wY.Pi00CeyhFMwPRIYBt16', 0, 0, 'default', 30, 3, 'default', 0, 0, 50);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (107, 7, 93);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (94, 'filter-update', '$2y$10$rHPaFkVgIx.ceaZYTlMTiuH9HyCv5M/GXQkrCyQLcK2sdubp303ze', 0, 0, 'default', 30, 3, 'default', 0, 0, 50);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (108, 7, 94);

INSERT INTO profiles (profileid, userid, idx, idx2, value_id, value_int, value_str, type) VALUES (24, 93, 'web.monitoring.problem.properties', 0, 0, 0, '{"filter_name":""}', 3);
INSERT INTO profiles (profileid, userid, idx, idx2, value_id, value_int, value_str, type) VALUES (25, 93, 'web.monitoring.problem.properties', 1, 0, 0, '{"hostids":["10084"],"filter_name":"delete_problems_1"}', 3);
INSERT INTO profiles (profileid, userid, idx, idx2, value_id, value_int, value_str, type) VALUES (30, 93, 'web.monitoring.problem.properties', 2, 0, 0, '{"filter_name":"delete_problems_2"}', 3);
INSERT INTO profiles (profileid, userid, idx, idx2, value_id, value_int, value_str, type) VALUES (20, 93, 'web.monitoring.hosts.properties', 0, 0, 0, '{"filter_name":""}', 3);
INSERT INTO profiles (profileid, userid, idx, idx2, value_id, value_int, value_str, type) VALUES (21, 93, 'web.monitoring.hosts.properties', 1, 0, 0, '{"groupids":["4"],"filter_name":"delete_hosts_1"}', 3);
INSERT INTO profiles (profileid, userid, idx, idx2, value_id, value_int, value_str, type) VALUES (31, 93, 'web.monitoring.hosts.properties', 2, 0, 0, '{"filter_name":"delete_hosts_2"}', 3);

INSERT INTO profiles (profileid, userid, idx, idx2, value_id, value_int, value_str, type) VALUES (26, 94, 'web.monitoring.problem.properties', 0, 0, 0, '{"filter_name":""}', 3);
INSERT INTO profiles (profileid, userid, idx, idx2, value_id, value_int, value_str, type) VALUES (27, 94, 'web.monitoring.problem.properties', 1, 0, 0, '{"filter_name":"update_tab","filter_show_counter":1,"show_timeline":"0"}', 3);
INSERT INTO profiles (profileid, userid, idx, idx2, value_id, value_int, value_str, type) VALUES (28, 94, 'web.monitoring.hosts.properties', 0, 0, 0, '{"filter_name":""}', 3);
INSERT INTO profiles (profileid, userid, idx, idx2, value_id, value_int, value_str, type) VALUES (29, 94, 'web.monitoring.hosts.properties', 1, 0, 0, '{"filter_name":"update_tab","filter_show_counter":1}', 3);

-- testTimezone
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (9, 'test-timezone', '$2y$10$TUIJdrXgEUaoCmbOdhiLhe8kWc3M.EE.paOv0rC7bgSP2til3643O', 0, 0, 'default', 30, 3, 'default', 0, 0, 50);
INSERT INTO usrgrp (usrgrpid, name) VALUES (92, 'Test timezone');
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (105, 92, 9);

-- testUrlUserPermissions
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page, url) VALUES (40, 'admin-zabbix', '$2y$10$HuvU0X0vGitK8YhwyxILbOVU6oxYNF.BqsOhaieVBvDiGlxgxriay', 0, 0, 'en_US', 30, 2, 'default', 0, 0, 50, 'zabbix.php?action=toptriggers.list');
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (60, 7, 40);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (50, 'user-zabbix', '$2y$10$MZQTU3/7XsECy1DbQqvn/eaoPoMDgMYJ7Ml1wYon1dC0NfwM9E3zu', 0, 0, 'en_US', 30, 1, 'default', 0, 0, 50);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (70, 7, 50);

-- testFormAdministrationUserGroups
INSERT INTO usrgrp (usrgrpid, name) VALUES (130, 'Selenium user group');
INSERT INTO usrgrp (usrgrpid, name) VALUES (140, 'Selenium user group in scripts');
INSERT INTO usrgrp (usrgrpid, name) VALUES (150, 'Selenium user group in configuration');
INSERT INTO scripts (scriptid, type, name, command, host_access, usrgrpid, groupid, description, scope) VALUES (5, 0, 'Selenium script','test',2,140,NULL,'selenium script description', 1);
UPDATE settings SET value_usrgrpid = 150 WHERE name='alert_usrgrpid';

-- Disable warning if Zabbix server is down
UPDATE settings SET value_int= 0 WHERE name='server_check_interval';
-- Super admin rows per page
UPDATE users SET rows_per_page = 100 WHERE userid = 1;
-- Set default language to EN_gb to display the date/time in the 24-hour format
UPDATE settings SET value_str='en_GB' WHERE name='default_lang';

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
INSERT INTO functions (functionid,triggerid,itemid,name,parameter) VALUES ('100185','99250','42253','avg','$,5m');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Service','abc','99250','98997');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('service','abcdef','99250','98998');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Database','','99250','98999');
INSERT INTO events (eventid,source,object,objectid,clock,ns,value,name,severity) VALUES (92,0,0,99250,1603456428,128786843,1,'Test trigger to check tag filter on problem page',3);
INSERT INTO event_tag (eventtagid,eventid,tag,value) VALUES (90,92,'Service','abc'),(91,92,'service','abcdef'),(92,92,'Database',''),(98,92,'Tag4',''),(99,92,'Tag5','5');
INSERT INTO problem (eventid,source,object,objectid,clock,ns,name,severity) VALUES (92,0,0,99250,1603456428,128786843,'Test trigger to check tag filter on problem page',3);
INSERT INTO problem_tag (problemtagid,eventid,tag,value) VALUES (90,92,'Service','abc'),(91,92,'service','abcdef'),(92,92,'Database',''),(98,92,'Tag4',''),(99,92,'Tag5','5');

INSERT INTO triggers (description,expression,recovery_mode,type,url,priority,comments,manual_close,status,correlation_mode,recovery_expression,correlation_tag,triggerid) VALUES ('Test trigger with tag','{100186}>100','0','0','','2','','1','0','0','','','99251');
INSERT INTO functions (functionid,triggerid,itemid,name,parameter) VALUES ('100186','99251','42253','avg','$,5m');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Service','abc','99251','99000');
INSERT INTO events (eventid,source,object,objectid,clock,ns,value,name,severity) VALUES (93,0,0,99251,1603466628,128786843,1,'Test trigger with tag',2);
INSERT INTO event_tag (eventtagid,eventid,tag,value) VALUES (93,93,'Service','abc');
INSERT INTO problem (eventid,source,object,objectid,clock,ns,name,severity) VALUES (93,0,0,99251,1603466628,128786843,'Test trigger with tag',2);
INSERT INTO problem_tag (problemtagid,eventid,tag,value) VALUES (93,93,'Service','abc');

-- host prototypes
INSERT INTO hosts (hostid, host, name, status, description, flags, readme) VALUES (90001, 'Host for host prototype tests', 'Host for host prototype tests', 0, '', 0, '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99000, 90001, 4);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50024,90001,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (name, key_, hostid, value_type, itemid, interfaceid, flags, delay, params,query_fields, description, posts, headers) VALUES ('Discovery rule 1', 'key1', 90001, 4, 90001, 50024, 1, '30s', '','', '', '', '');
INSERT INTO items (name, key_, hostid, value_type, itemid, interfaceid, flags, delay, params,query_fields, description, posts, headers) VALUES ('Discovery rule 2', 'key2', 90001, 4, 90002, 50024, 1, '30s', '','', '', '', '');
INSERT INTO items (name, key_, hostid, value_type, itemid, interfaceid, flags, delay, params,query_fields, description, posts, headers) VALUES ('Discovery rule 3', 'key3', 90001, 4, 90003, 50024, 1, '30s', '','', '', '', '');
INSERT INTO hosts (hostid, host, name, status, description, flags, readme) VALUES (90002, 'Host prototype {#1}', 'Host prototype {#1}', 0, '', 2, '');
INSERT INTO hosts (hostid, host, name, status, description, flags, readme) VALUES (90003, 'Host prototype {#2}', 'Host prototype {#2}', 1, '', 2, '');
INSERT INTO hosts (hostid, host, name, status, description, flags, readme) VALUES (90004, 'Host prototype {#3}', 'Host prototype {#3}', 0, '', 2, '');
INSERT INTO hosts (hostid, host, name, status, description, flags, readme) VALUES (90005, 'Host prototype {#4}', 'Host prototype {#4}', 0, '', 2, '');
INSERT INTO hosts (hostid, host, name, status, description, flags, readme) VALUES (90006, 'Host prototype {#5}', 'Host prototype {#5}', 0, '', 2, '');
INSERT INTO hosts (hostid, host, name, status, description, flags, readme) VALUES (90007, 'Host prototype {#6}', 'Host prototype {#6}', 0, '', 2, '');
INSERT INTO hosts (hostid, host, name, status, description, flags, readme) VALUES (90008, 'Host prototype {#7}', 'Host prototype {#7}', 0, '', 2, '');
INSERT INTO hosts (hostid, host, name, status, description, flags, readme) VALUES (90009, 'Host prototype {#8}', 'Host prototype {#8}', 0, '', 2, '');
INSERT INTO hosts (hostid, host, name, status, description, flags, readme) VALUES (90010, 'Host prototype {#9}', 'Host prototype {#9}', 0, '', 2, '');
INSERT INTO hosts (hostid, host, name, status, description, flags, readme) VALUES (90011, 'Host prototype {#10}', 'Host prototype {#10}', 0, '', 2, '');
INSERT INTO hosts (hostid, host, name, status, description, flags, readme) VALUES (90012, 'Host prototype {#33}', 'Host prototype visible name', 0, '', 2, '');
INSERT INTO host_discovery (hostid, lldruleid) VALUES (90002, 90001);
INSERT INTO host_discovery (hostid, lldruleid) VALUES (90003, 90001);
INSERT INTO host_discovery (hostid, lldruleid) VALUES (90004, 90001);
INSERT INTO host_discovery (hostid, lldruleid) VALUES (90012, 90001);
INSERT INTO host_discovery (hostid, lldruleid) VALUES (90005, 90002);
INSERT INTO host_discovery (hostid, lldruleid) VALUES (90006, 90002);
INSERT INTO host_discovery (hostid, lldruleid) VALUES (90007, 90002);
INSERT INTO host_discovery (hostid, lldruleid) VALUES (90008, 90003);
INSERT INTO host_discovery (hostid, lldruleid) VALUES (90009, 90003);
INSERT INTO host_discovery (hostid, lldruleid) VALUES (90010, 90003);
INSERT INTO host_discovery (hostid, lldruleid) VALUES (90011, 90003);
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
INSERT INTO hstgrp (groupid, name, type) VALUES (50019, 'Inheritance test', 0);
INSERT INTO hstgrp (groupid, name, type) VALUES (50023, 'Inheritance test', 1);
INSERT INTO hosts (hostid, host, name, flags, templateid, description, readme) VALUES (99000, 'testInheritanceHostPrototype {#TEST}', 'testInheritanceHostPrototype {#TEST}', 2, NULL, '', '');
INSERT INTO hosts (hostid, host, name, flags, templateid, description, readme) VALUES (99001, 'testInheritanceHostPrototype {#TEST}', 'testInheritanceHostPrototype {#TEST}', 2, 99000, '', '');
INSERT INTO host_discovery (hostid, parent_hostid, lldruleid, host, lastcheck, ts_delete) VALUES (99000, NULL, 15011, '', 0, 0);
INSERT INTO host_discovery (hostid, parent_hostid, lldruleid, host, lastcheck, ts_delete) VALUES (99001, NULL, 15016, '', 0, 0);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1010, 99000, '', 50019, NULL);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1011, 99001, '', 50019, 1010);
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99006, 'Inheritance test template with host prototype', 'Inheritance test template with host prototype', 3, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99006, 99006, 50023);
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, formula, params, description, posts, headers,query_fields, flags) VALUES (99083, 99006, 2, 'Discovery rule for host prototype test', 'key_test', '30s', 4, '', '', '', '', '','', 1);
INSERT INTO hosts (hostid, host, name, status, description, flags, readme) VALUES (99007, 'Host prototype for update {#TEST}', 'Host prototype for update {#TEST}', 0, '', 2, '');
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1012, 99007, '', 50019, NULL);
INSERT INTO host_discovery (hostid, parent_hostid, lldruleid, host, lastcheck, ts_delete) VALUES (99007, NULL, 99083, '', 0, 0);
INSERT INTO hosts (hostid, host, name, status, description, flags, readme) VALUES (99009, 'Host prototype for delete {#TEST}', 'Host prototype for delete {#TEST}', 0, '', 2, '');
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1013, 99009, '', 50019, NULL);
INSERT INTO host_discovery (hostid, parent_hostid, lldruleid, host, lastcheck, ts_delete) VALUES (99009, NULL, 99083, '', 0, 0);
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99004, 'Host for inheritance host prototype tests', 'Host for inheritance host prototype tests', 0, '', '');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) VALUES (10026,99004,1,1,1,'127.0.0.1','','10050');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99004, 99004, 50019);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (15004, 99004, 99006);
INSERT INTO items (itemid, hostid, type, name, key_, delay, value_type, formula, params, description, posts, headers, templateid,query_fields, flags) VALUES (99084, 99004, 2, 'Discovery rule for host prototype test', 'key_test', '30s', 4, '', '', '', '', '', 99083,'', 1);
INSERT INTO hosts (hostid, host, name, status, description, templateid, flags, readme) VALUES (99008, 'Host prototype for update {#TEST}', 'Host prototype for update {#TEST}', 0, '', 99007, 2, '');
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1014, 99008, '', 50019, 1002);
INSERT INTO host_discovery (hostid, parent_hostid, lldruleid, host, lastcheck, ts_delete) VALUES (99008, NULL, 99084, '', 0, 0);
INSERT INTO hosts (hostid, host, name, status, description, templateid, flags, readme) VALUES (99010, 'Host prototype for delete {#TEST}', 'Host prototype for delete {#TEST}', 0, '', 99009, 2, '');
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1015, 99010, '', 50019, 1004);
INSERT INTO host_discovery (hostid, parent_hostid, lldruleid, host, lastcheck, ts_delete) VALUES (99010, NULL, 99084, '', 0, 0);

INSERT INTO hosts (hostid, host, name, status, description, flags, readme) VALUES (99060, 'Host prototype for Clone {#TEST}', 'Host prototype for Clone {#TEST}', 1, '', 2, '');
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1023, 99060, '', 50019, NULL);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1024, 99060, '{#GROUP_PROTO}',NULL, NULL);
INSERT INTO host_discovery (hostid, parent_hostid, lldruleid, host, lastcheck, ts_delete) VALUES (99060, NULL, 99083, '', 0, 0);

INSERT INTO hosts (hostid, host, name, status, description, templateid, flags, readme) VALUES (99055, 'Host prototype for Clone {#TEST}', 'Host prototype for Clone {#TEST}', 1, '', 99060, 2, '');
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1025, 99055, '', 50019, 1024);
INSERT INTO group_prototype (group_prototypeid, hostid, name, groupid, templateid) VALUES (1026, 99055, '{#GROUP_PROTO}',NULL, 1023);
INSERT INTO host_discovery (hostid, parent_hostid, lldruleid, host, lastcheck, ts_delete) VALUES (99055, NULL, 99084, '', 0, 0);

-- testPageProblems_TagPriority
INSERT INTO triggers (description,expression,recovery_mode,type,url,priority,comments,manual_close,status,correlation_mode,recovery_expression,correlation_tag,triggerid) VALUES ('First test trigger with tag priority','{100181}>100','0','1','','2','','1','0','0','','','99252');
INSERT INTO functions (functionid,triggerid,itemid,name,parameter) VALUES ('100181','99252','42253','avg','$,5m');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Delta','d','99252','99005');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Beta','b','99252','99006');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Alpha','a','99252','99007');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Gamma','g','99252','99008');
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
INSERT INTO functions (functionid,triggerid,itemid,name,parameter) VALUES ('100182','99253','42253','avg','$,5m');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Zeta','z','99253','99009');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Beta','b','99253','99010');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Epsilon','e','99253','99011');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Eta','e','99253','99012');
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
INSERT INTO functions (functionid,triggerid,itemid,name,parameter) VALUES ('100183','99254','42253','avg','$,5m');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Kappa','k','99254','99013');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Iota','i','99254','99014');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Alpha','a','99254','99015');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Theta','t','99254','99016');
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
INSERT INTO functions (functionid,triggerid,itemid,name,parameter) VALUES ('100184','99255','42253','avg','$,5m');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Eta','e','99255','99017');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Gamma','g','99255','99018');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Theta','t','99255','99019');
INSERT INTO trigger_tag (tag,value,triggerid,triggertagid) VALUES ('Delta','d','99255','99020');
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
INSERT INTO hstgrp (groupid, name, type) VALUES (50013, 'Host group for suppression', 0);
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99011, 'Host for suppression', 'Host for suppression', 0, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99007, 99011, 50013);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50025,99011,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99087, 2, 99011, 'Trapper_for_suppression', '', 'trapper_sup', 30, NULL, '', '', '', '', '','');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99087, 'Trapper_for_suppression', 'TRAPPER_FOR_SUPPRESSION');
INSERT INTO triggers (triggerid, description, expression, value, priority, state, lastchange, comments) VALUES (100031, 'Trigger_for_suppression', '{100031}>0', 1, 3, 0, '1535012391', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100031, 99087, 100031, 'last', '$,#1');
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (99004, 'SupTag','A', 100031);

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
INSERT INTO hstgrp (groupid,name,type) VALUES (50005,'Group for host graph check',0);
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99012, 'Host to check graph 1', 'Host to check graph 1', 0, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99008, 99012, 50005);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50026,99012,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99007, 2, 99012, 'Item to check graph', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99007, 'Item to check graph', 'ITEM TO CHECK GRAPH');
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (700018,'Check graph 1',900,200,0.0,100.0,NULL,1,1,0,1,0,0.0,0.0,0,0,NULL,NULL,0);
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (700019,'Check graph 2',900,200,0.0,100.0,NULL,1,1,0,1,0,0.0,0.0,0,0,NULL,NULL,0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700026, 700018, 99007, 0, 0, '1A7C11', 0, 2, 0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700027, 700019, 99007, 0, 0, '1A7C11', 0, 2, 0);
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99013, 'Host to delete graphs', 'Host to delete graphs', 0, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99009, 99013, 50005);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50027,99013,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99008, 2, 99013, 'Item to delete graph', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99008, 'Item to delete graph', 'ITEM TO DELETE GRAPH');
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
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99014, 'Empty template', 'Empty template', 3, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99010, 99014, 1);
INSERT INTO hstgrp (groupid,name,type) VALUES (50006,'Empty group',0);
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99015, 'Empty host', 'Empty host', 0, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99011, 99015, 50006);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50028,99015,1,1,1,'127.0.0.1','','10050');
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99016, 'Template to test graphs', 'Template to test graphs', 3, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99012, 99016, 1);
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99009, 2, 99016, 'Item to check graph', '', 'graph[2]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO graphs (graphid, name, width, height, yaxismin, yaxismax, templateid, show_work_period, show_triggers, graphtype, show_legend, show_3d, percent_left, percent_right, ymin_type, ymax_type, ymin_itemid, ymax_itemid, flags) VALUES (700025,'Graph to check copy',900,200,0.0,100.0,NULL,1,1,0,1,0,0.0,0.0,0,0,NULL,NULL,0);
INSERT INTO graphs_items (gitemid, graphid, itemid, drawtype, sortorder, color, yaxisside, calc_fnc, type) VALUES (700033, 700025, 99009, 0, 0, '1A7C11', 0, 2, 0);
INSERT INTO hstgrp (groupid,name,type) VALUES (50007,'Group to copy graph',0);
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99017, 'Host with item and without graph 1', 'Host with item and without graph 1', 0, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99013, 99017, 50007);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50029,99017,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99010, 2, 99017, 'Item', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99010, 'Item', 'ITEM');
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99018, 'Host with item and without graph 2', 'Host with item and without graph 2', 0, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99014, 99018, 50007);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50030,99018,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99011, 2, 99018, 'Item', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99011, 'Item', 'ITEM');
INSERT INTO hstgrp (groupid,name,type) VALUES (50008,'Group to copy all graph',0);
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99019, 'Host with item to copy all graphs 1', 'Host with item to copy all graphs 1', 0, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99015, 99019, 50008);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50031,99019,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99012, 2, 99019, 'Item', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99012, 'Item', 'ITEM');
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99020, 'Host with item to copy all graphs 2', 'Host with item to copy all graphs 2', 0, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99016, 99020, 50008);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50032,99020,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99013, 2, 99020, 'Item', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99013, 'Item', 'ITEM');
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99021, 'Host to check graph 2', 'Host to check graph 2', 0, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99017, 99021, 50005);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50033,99021,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99014, 2, 99021, 'Item to check graph', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99014, 'Item to check graph', 'ITEM TO CHECK GRAPH');
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99022, 'Template with item graph', 'Template with item graph', 3, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99018, 99022, 1);
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99015, 2, 99022, 'Item', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99023, 'Template with item graph for copy all graph', 'Template with item graph for copy all graph', 3, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99019, 99023, 1);
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99016, 2, 99023, 'Item', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99029, 'Template to copy graph to several templates 1', 'Template to copy graph to several templates 1', 3, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99020, 99029, 1);
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99022, 2, 99029, 'Item', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99030, 'Template to copy graph to several templates 2', 'Template to copy graph to several templates 2', 3, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99021, 99030, 1);
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99023, 2, 99030, 'Item', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99024, 'Host to check graph 3', 'Host to check graph 3', 0, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99022, 99024, 50005);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50034,99024,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99017, 2, 99024, 'Item to check graph', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99017, 'Item to check graph', 'ITEM TO CHECK GRAPH');
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99025, 'Host to check graph 4', 'Host to check graph 4', 0, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99023, 99025, 50005);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50035,99025,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99018, 2, 99025, 'Item to check graph', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99018, 'Item to check graph', 'ITEM TO CHECK GRAPH');
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99026, 'Host to check graph 5', 'Host to check graph 5', 0, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99024, 99026, 50005);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50036,99026,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99019, 2, 99026, 'Item to check graph', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99019, 'Item to check graph', 'ITEM TO CHECK GRAPH');
INSERT INTO hstgrp (groupid,name,type) VALUES (50009,'Copy graph to several groups 1',0);
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99027, 'Host 1 from first group', 'Host 1 from first group', 0, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99025, 99027, 50009);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50037,99027,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99020, 2, 99027, 'Item to check graph', '{$A}', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99020, 'Item to check graph', 'ITEM TO CHECK GRAPH');
INSERT INTO hstgrp (groupid,name,type) VALUES (50010,'Copy graph to several groups 2',0);
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99028, 'Host 1 from second group', 'Host 1 from second group', 0, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99026, 99028, 50010);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50038,99028,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99021, 2, 99028, 'Item to check graph', '', 'graph[1]', 0, NULL, '', '', 'zabbix.com', '', '','');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99021, 'Item to check graph', 'ITEM TO CHECK GRAPH');

-- testPageTriggers tags filtering test
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99050, 'Host for trigger tags filtering', 'Host for trigger tags filtering', 0, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99910, 99050, 4);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (55030,99050,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, type, hostid, name, description, key_, delay, interfaceid, params, formula, url, posts, query_fields, headers) VALUES (99090, 2, 99050, 'Trapper', '', 'trap', 30, NULL, '', '', '', '', '','');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99090, 'Trapper', 'TRAPPER');

INSERT INTO triggers (triggerid, description, expression, value, priority, state, lastchange, comments) VALUES (100060, 'First trigger for tag filtering', '{100060}>0', 0, 1, 0, '0', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100060, 99090, 100060, 'last', '$');
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (99030, 'TagA','A', 100060);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (99031, 'TagB','b', 100060);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (99032, 'TagD','d', 100060);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (99033, 'TagG','g', 100060);

INSERT INTO triggers (triggerid, description, expression, value, priority, state, lastchange, comments) VALUES (100061, 'Second trigger for tag filtering', '{100061}>0', 0, 2, 0, '0', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100061, 99090, 100061, 'last', '$');
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (99034, 'TagB','b', 100061);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (99035, 'TagE','e', 100061);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (99036, 'TagE1','e', 100061);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (99037, 'TagZ','z', 100061);

INSERT INTO triggers (triggerid, description, expression, value, priority, state, lastchange, comments) VALUES (100062, 'Third trigger for tag filtering', '{100062}>0', 0, 3, 0, '0', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100062, 99090, 100062, 'last', '$');
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (99038, 'TagA','a', 100062);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (99039, 'TagI','i', 100062);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (99040, 'TagK','k', 100062);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (99041, 'TagT','t', 100062);

INSERT INTO triggers (triggerid, description, expression, value, priority, state, lastchange, comments) VALUES (100063, 'Fourth trigger for tag filtering', '{100063}>0', 0, 4, 0, '0', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100063, 99090, 100063, 'last', '$');
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (99042, 'TagD','d', 100063);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (99043, 'TagE1','e', 100063);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (99044, 'TagG','g', 100063);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (99045, 'TagT','t', 100063);

INSERT INTO triggers (triggerid, description, expression, value, priority, state, lastchange, comments) VALUES (100064, 'Fifth trigger for tag filtering (no tags)', '{100064}>0', 0, 5, 0, '0', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100064, 99090, 100064, 'last', '$');

-- testDashboardHostAvailabilityWidget
INSERT INTO hstgrp (groupid, name, type) VALUES (50011, 'Group to check Overview', 0);
INSERT INTO hstgrp (groupid, name, type) VALUES (50012, 'Another group to check Overview', 0);
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (50011, '1_Host_to_check_Monitoring_Overview', '1_Host_to_check_Monitoring_Overview', 0, '', '');
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (50012, '3_Host_to_check_Monitoring_Overview', '3_Host_to_check_Monitoring_Overview', 0, '', '');
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (50013, '4_Host_to_check_Monitoring_Overview', '4_Host_to_check_Monitoring_Overview', 0, '', '');
INSERT INTO host_inventory (type, type_full, name, alias, os, os_full, os_short, serialno_a, serialno_b, tag, asset_tag, macaddress_a, macaddress_b, hardware, hardware_full, software, software_full, software_app_a, software_app_b, software_app_c, software_app_d, software_app_e, contact, location, location_lat, location_lon, notes, chassis, model, hw_arch, vendor, contract_number, installer_name, deployment_status, url_a, url_b, url_c, host_networks, host_netmask, host_router, oob_ip, oob_netmask, oob_router, date_hw_purchase, date_hw_install, date_hw_expiry, date_hw_decomm, site_address_a, site_address_b, site_address_c, site_city, site_state, site_country, site_zip, site_rack, site_notes, poc_1_name, poc_1_email, poc_1_phone_a, poc_1_phone_b, poc_1_cell, poc_1_screen, poc_1_notes, poc_2_name, poc_2_email, poc_2_phone_a, poc_2_phone_b, poc_2_cell, poc_2_screen, poc_2_notes, hostid) VALUES ('Type', 'Type (Full details)', 'Name', 'Alias', 'OS', 'OS (Full details)', 'OS (Short)', 'Serial number A', 'Serial number B', 'Tag','Asset tag', 'MAC address A', 'MAC address B', 'Hardware', 'Hardware (Full details)', 'Software', 'Software (Full details)', 'Software application A', 'Software application B', 'Software application C', 'Software application D', 'Software application E', 'Contact', 'Location', 'Location latitud', 'Location longitu', 'Notes', 'Chassis', 'Model', 'HW architecture', 'Vendor', 'Contract number', 'Installer name', 'Deployment status', 'URL A', 'URL B', 'URL C', 'Host networks', 'Host subnet mask', 'Host router', 'OOB IP address', 'OOB subnet mask', 'OOB router', 'Date HW purchased', 'Date HW installed', 'Date HW maintenance expires', 'Date hw decommissioned', 'Site address A', 'Site address B', 'Site address C', 'Site city', 'Site state / province', 'Site country', 'Site ZIP / postal', 'Site rack location', 'Site notes', 'Primary POC name', 'Primary POC email', 'Primary POC phone A', 'Primary POC phone B', 'Primary POC cell', 'Primary POC screen name', 'Primary POC notes', 'Secondary POC name', 'Secondary POC email', 'Secondary POC phone A', 'Secondary POC phone B', 'Secondary POC cell', 'Secondary POC screen name', 'Secondary POC notes', 50012);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (90282, 50011, 50011);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (90283, 50012, 50011);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (90284, 50013, 50012);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50039,50011,1,1,1,'127.0.0.1','','10050');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50040,50012,1,1,1,'127.0.0.1','','10050');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (50041,50013,1,1,1,'127.0.0.1','','10050');
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history, status, params, description,query_fields, flags, posts, headers) VALUES (99086, 50011, 50039, 2, 3, '1_item','trap[1]', '30s', '90d', 0, '', '','', 0, '', '');
INSERT INTO item_tag (itemtagid, itemid, tag, value) VALUES (99000, 99086, 'DataBase', 'mysql');
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history, status, params, description,query_fields, flags, posts, headers) VALUES (99091, 50011, 50039, 2, 3, '2_item','trap[2]', '30s', '90d', 0, '', '','', 0, '', '');
INSERT INTO item_tag (itemtagid, itemid, tag, value) VALUES (99001, 99091, 'DataBase', 'PostgreSQL');
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history, status, params, description,query_fields, flags, posts, headers) VALUES (99088, 50012, 50040, 2, 3, '3_item','trap[3]', '30s', '90d', 0, '', '','', 0, '', '');
INSERT INTO item_tag (itemtagid, itemid, tag, value) VALUES (99002, 99088, 'DataBase', 'Oracle');
INSERT INTO items (itemid, hostid, interfaceid, type, value_type, name, key_, delay, history, status, params, description, flags, posts, headers,query_fields, units) VALUES (99089, 50013, 50041, 2, 3, '4_item','trap[4]', '30s', '90d', 0, '', '', 0, '', '','', 'UNIT');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99086, '1_item', '1_ITEM');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99091, '2_item', '2_ITEM');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99088, '3_item', '3_ITEM');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99089, '4_item', '4_ITEM');
INSERT INTO item_tag (itemtagid, itemid, tag, value) VALUES (99003, 99089, 'DataBase', 'Oracle DB');
INSERT INTO triggers (triggerid, description, expression, value, state, lastchange, comments, priority, url) VALUES (100032, '1_trigger_Not_classified', '{100032}>0', 1, 0, '1533555726', 'Macro should be resolved, host IP should be visible here: {HOST.CONN}', 0, 'tr_events.php?triggerid={TRIGGER.ID}&eventid={EVENT.ID}');
INSERT INTO triggers (triggerid, description, expression, value, state, lastchange, comments, priority) VALUES (100033, '1_trigger_Warning', '{100033}>0', 1, 0, '1533555726', 'The following url should be clickable: https://zabbix.com', 2);
INSERT INTO triggers (triggerid, description, expression, value, state, lastchange, comments, priority, url) VALUES (100034, '1_trigger_Average', '{100034}>0', 1, 0, '1533555726', 'https://zabbix.com', 3, 'tr_events.php?triggerid={TRIGGER.ID}&eventid={EVENT.ID}');
INSERT INTO triggers (triggerid, description, expression, value, state, lastchange, comments, priority, url) VALUES (100035, '1_trigger_High', '{100035}>0', 1, 0, '1533555726', 'Non-clickable description', 4, 'tr_events.php?triggerid={TRIGGER.ID}&eventid={EVENT.ID}');
INSERT INTO triggers (triggerid, description, expression, value, state, lastchange, comments, priority) VALUES (100036, '1_trigger_Disaster', '{100036}>0', 1, 0, '1533555726', '', 5);
INSERT INTO triggers (triggerid, description, expression, value, state, lastchange, comments, priority) VALUES (100037, '2_trigger_Information', '{100037}>0', 1, 0, '1533555726', 'http://zabbix.com https://www.zabbix.com/career https://www.zabbix.com/contact', 1);
INSERT INTO triggers (triggerid, description, expression, value, state, lastchange, comments, priority) VALUES (100038, '3_trigger_Average', '{100038}>0', 1, 0, '1533555726', 'Macro - resolved, URL - clickable: {HOST.NAME}, https://zabbix.com', 3);
INSERT INTO triggers (triggerid, description, expression, value, state, lastchange, comments, priority, url) VALUES (100039, '3_trigger_Disaster', '{100039}>0', 0, 0, '1533555726', '', 5, 'triggers.php?form=update&triggerid={TRIGGER.ID}&context=host');
INSERT INTO triggers (triggerid, description, expression, value, state, lastchange, comments, priority) VALUES (100040, '4_trigger_Average', '{100040}>0', 1, 0, '1533555726', '', 3);
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100032, 99086, 100032, 'last', '$,#1');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100033, 99086, 100033, 'last', '$,#1');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100034, 99086, 100034, 'last', '$,#1');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100035, 99086, 100035, 'last', '$,#1');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100036, 99086, 100036, 'last', '$,#1');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100037, 99091, 100037, 'last', '$,#1');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100038, 99088, 100038, 'last', '$,#1');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100039, 99088, 100039, 'last', '$,#1');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100040, 99089, 100040, 'last', '$,#1');
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
INSERT INTO task (taskid, type, status, clock, ttl, proxyid) VALUES (1, 4, 1, 1533631968, 0, NULL);
INSERT INTO task (taskid, type, status, clock, ttl, proxyid) VALUES (2, 4, 1, 1533631968, 0, NULL);
INSERT INTO task_acknowledge (taskid, acknowledgeid) VALUES (1, 1);
INSERT INTO task_acknowledge (taskid, acknowledgeid) VALUES (2, 2);

-- testPageAvailabilityReport SLA reports
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (50014, 'SLA reports host', 'SLA reports host', 0, '', '');
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, port) VALUES (50042, 50014, 1, 1, 1, '127.0.0.1', '10051');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (50013, 50014, 4);
INSERT INTO items (itemid, type, hostid, name, key_, params,query_fields, description, posts, headers) VALUES (400670, 2, 50014, 'Item A', 'A', '','', '', '', '');
INSERT INTO items (itemid, type, hostid, name, key_, params,query_fields, description, posts, headers) VALUES (400680, 2, 50014, 'Item B', 'B', '','', '', '', '');
INSERT INTO items (itemid, type, hostid, name, key_, params,query_fields, description, posts, headers) VALUES (400690, 2, 50014, 'Item C', 'C', '','', '', '', '');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (400670, 'Item A', 'ITEM A');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (400680, 'Item B', 'ITEM B');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (400690, 'Item C', 'ITEM C');
INSERT INTO triggers (triggerid, expression, description, comments) VALUES (100001, '{16028}=0', 'A trigger', '');
INSERT INTO triggers (triggerid, expression, description, comments) VALUES (100002, '{16029}=0', 'B trigger', '');
INSERT INTO triggers (triggerid, expression, description, comments) VALUES (100003, '{16030}=0', 'C trigger', '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (16028, 400670, 100001,'last','$,#1');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (16029, 400680, 100002,'last','$,#1');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (16030, 400690, 100003,'last','$,#1');

-- testPageTriggers triggers filtering
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99061, 'Inheritance template for triggers filtering', 'Inheritance template for triggers filtering', 3, '', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99913, 99061, 1);
INSERT INTO items (itemid, type, hostid, name, description, key_, interfaceid,query_fields, params, posts, headers) VALUES (99092, 2, 99061, 'Inheritance item for triggers filtering', '', 'trap', NULL,'', '', '', '');
INSERT INTO triggers (triggerid, description, expression, priority, state, comments) VALUES (100065, 'Inheritance trigger with tags', '{100065}>0',3, 1, '');
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100065, 99092, 100065, 'last', '$');
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (99046, 'server','selenium', 100065);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (99047, 'Street','dzelzavas', 100065);

INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99062, 'Host for triggers filtering', 'Host for triggers filtering', 0, '', '');
INSERT INTO hstgrp (groupid, name, type) VALUES (50014,'Group to check triggers filtering',0);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99914, 99062, 50014);
INSERT INTO hosts_templates (hosttemplateid, hostid, templateid) VALUES (50004, 99062, 99061);
INSERT INTO interface (interfaceid, hostid, main, type, useip, ip, dns, port) values (55033, 99062, 1, 1, 1, '127.0.0.1', '', '10050');

INSERT INTO items (itemid, type, hostid, name, description, key_, interfaceid, params, posts,query_fields, templateid, headers) VALUES (99093, 2, 99062, 'Inheritance item for triggers filtering', '', 'trap', NULL, '', '','', 99092,'');
INSERT INTO items (itemid, type, hostid, name, description, key_, interfaceid,query_fields, params, posts, headers) VALUES (99094, 2, 99062, 'Item for triggers filtering', '', 'trap1', NULL,'', '', '', '');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99093, 'Inheritance item for triggers filtering', 'INHERITANCE ITEM FOR TRIGGERS FILTERING');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99094, 'Item for triggers filtering', 'ITEM FOR TRIGGERS FILTERING');

INSERT INTO triggers (triggerid, description, expression, value, comments, templateid, state, error) VALUES (100066, 'Inheritance trigger with tags', '{100067}=0', 1,'', 100065, 1, 'selenium trigger cannot be evaluated for some reason');
INSERT INTO functions (functionid, triggerid, itemid, name, parameter) VALUES (100067, 100066, 99093, 'last', '$');
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (99048, 'server','selenium', 100066);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (99049, 'Street','Dzelzavas', 100066);
INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (9008, 0, 0, 100066, 1535012391, 445429746,1, 'Inheritance trigger with tags', 3);
INSERT INTO event_tag (eventtagid, eventid, tag, value) VALUES (116, 9008, 'server', 'selenium');
INSERT INTO event_tag (eventtagid, eventid, tag, value) VALUES (117, 9008, 'Street', 'Dzelzavas');
INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES (9008, 0, 0, 100066, 1535012391, 445429746, 'Inheritance trigger with tags', 3);
INSERT INTO problem_tag (problemtagid, eventid, tag, value) VALUES (116, 9008, 'server', 'selenium');
INSERT INTO problem_tag (problemtagid, eventid, tag, value) VALUES (117, 9008, 'Street', 'Dzelzavas');
INSERT INTO triggers (triggerid, description, expression, status, value, priority, comments, state) VALUES (100067, 'Trigger disabled with tags', '{100067}>0', 1, 0, 3, '', 0);
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100068, 99094, 100067, 'last', '$');
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (99050, 'Street','Dzelzavas', 100067);
INSERT INTO trigger_tag (triggertagid, tag, value, triggerid) VALUES (99051, 'country','latvia', 100067);
INSERT INTO trigger_depends (triggerdepid, triggerid_down, triggerid_up) VALUES (99000, 100066, 100067);
INSERT INTO triggers (triggerid, description, expression, status, value, priority, comments, state) VALUES (100070, 'Dependent trigger ONE', '{100067}>0', 0, 0, 4, '', 0);
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100071, 99094, 100070, 'last', '$');
INSERT INTO trigger_depends (triggerdepid, triggerid_down, triggerid_up) VALUES (99001, 100070, 100067);

INSERT INTO items (itemid, type, hostid, name, description, key_, interfaceid, flags,query_fields, params, posts, headers) VALUES (99095, 2, 99062, 'Discovery rule for triggers filtering', '', 'lld', NULL, 1,'','','','');
INSERT INTO items (itemid, type, hostid, name, description, key_, interfaceid, flags,query_fields, params, posts, headers) VALUES (99096, 2, 99062, 'Discovered item {#TEST}', '', 'lld[{#TEST}]', NULL, 2,'', '', '', '');
INSERT INTO item_discovery (itemdiscoveryid, itemid, lldruleid, lastcheck, ts_delete) VALUES (35085, 99096, 99095, 0, 0);
INSERT INTO items (itemid, type, hostid, name, description, key_, interfaceid, flags,query_fields, params, posts, headers) VALUES (99097, 2, 99062, 'Discovered item one', '', 'lld[one]', NULL, 4,'', '', '', '');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99097, 'Discovered item one', 'DISCOVERED ITEM ONE');
INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid, key_) values (35086, 99097, 99096, 'lld[one]');
INSERT INTO triggers (triggerid, description, expression, status, value, priority, comments, state, flags) VALUES (100068, 'Discovered trigger {#TEST}', '{100069}>0', 0, 0, 5, '', 0, 2);
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100069, 99096, 100068, 'last', '$');
INSERT INTO triggers (triggerid, description, expression, status, value, priority, comments, state, flags) VALUES (100069, 'Discovered trigger one', '{100070}>0', 0, 0, 5, '', 0, 4);
INSERT INTO functions (functionid, itemid, triggerid, name, parameter) VALUES (100070, 99097, 100069, 'last', '$');
INSERT INTO trigger_discovery (triggerid, parent_triggerid) VALUES (100069, 100068);

-- testFormUser
INSERT INTO sysmaps (sysmapid, name, width, height, backgroundid, label_type, label_location, highlight, expandproblem, markelements, show_unack, userid, private) VALUES (10, 'Public map with image', 800, 600, NULL, 0, 0, 1, 1, 1, 2, 1, 0);
INSERT INTO sysmaps_elements (selementid, sysmapid, elementid, elementtype, iconid_off, iconid_on, label, label_location, x, y, iconid_disabled, iconid_maintenance) VALUES (10,10,0,4,7,NULL,'Test phone icon',0,151,101,NULL,NULL);
INSERT INTO users (userid, username, passwd, autologin, autologout, lang, refresh, roleid, theme, attempt_failed, attempt_clock, rows_per_page) VALUES (91, 'http-auth-admin', '$2y$10$HuvU0X0vGitK8YhwyxILbOVU6oxYNF.BqsOhaieVBvDiGlxgxriay', 0, 0, 'en_US', 30, 2, 'default', 0, 0, 50);
INSERT INTO users_groups (id, usrgrpid, userid) VALUES (92, 7, 91);

-- testFormItemTest
INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99136, 'Test item host', 'Test item host', 0, 'Test item host for testing items', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (100999, 99136, 4);
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main, available) VALUES (55070, 99136, 1, '127.0.0.1', 'Test1', '1', '10050', '1', 0);

INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55071, 99136, 2, '127.0.0.2', 'Test2', '1', '161', '1');
INSERT INTO interface_snmp (interfaceid, version, bulk, community) values (55071, 2, 1, '{$SNMP_COMMUNITY}');

INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55077, 99136, 2, '127.0.0.5', 'Test5', '1', '161', '0');
INSERT INTO interface_snmp (interfaceid, version, bulk, community) values (55077, 1, 1, '{$SNMP_COMMUNITY}');

INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55078, 99136, 2, '127.0.0.6', 'Test6', '1', '161', '0');
INSERT INTO interface_snmp (interfaceid, version, bulk, community, securityname, securitylevel, authpassphrase, privpassphrase, authprotocol, privprotocol, contextname) values (55078, 3, 1, '{$SNMP_COMMUNITY}', 'test_security_name', 2, '{$TEST}', 'test_privpassphrase', 1, 1, 'test_context');

INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55072, 99136, 3, '127.0.0.3', 'Test3', '1', '623', '1');
INSERT INTO interface (interfaceid, hostid, type, ip, dns, useip, port, main) VALUES (55073, 99136, 4, '127.0.0.4', 'Test4', '1', '12345', '1');

INSERT INTO items (itemid, type, hostid, name, description, key_, interfaceid, flags,query_fields, params, posts, headers) VALUES (99142, 0, 99136, 'Master item', '', 'master', 55070, 0,'', '', '', '');
INSERT INTO items (itemid, type, hostid, name, description, key_, interfaceid, flags,query_fields, params, posts, headers) VALUES (99294, 0, 99136, 'Test discovery rule', '', 'test', 55070, 1,'', '', '', '');
INSERT INTO item_rtname (itemid, name_resolved, name_resolved_upper) VALUES (99142, 'Master item', 'MASTER ITEM');

INSERT INTO hosts (hostid, host, name, status, description, readme) VALUES (99137, 'Test Item Template', 'Test Item Template', 3,'Template for testing items', '');
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (99982, 99137, 1);

INSERT INTO items (itemid, type, hostid, name, description, key_, interfaceid, flags,query_fields, params, posts, headers) VALUES (99183, 2, 99137, 'Master item', '', 'master', NULL, 0,'', '', '', '');
INSERT INTO items (itemid, type, hostid, name, description, key_, interfaceid, flags,query_fields, params, posts, headers) VALUES (99349, 0, 99137, 'Test discovery rule', '', 'test', NULL, 1,'', '', '', '');

-- testFormAdministrationMediaTypeWebhook
INSERT INTO media_type (mediatypeid, type, name, status, script, description) VALUES (111, 4, 'Reference webhook', 0, 'return 0;', 'Reference webhook media type');
INSERT INTO media_type_param (mediatype_paramid, mediatypeid, name, value) VALUES (1300, 111, 'URL', '');
INSERT INTO media_type_param (mediatype_paramid, mediatypeid, name, value) VALUES (1301, 111, 'To', '{ALERT.SENDTO}');
INSERT INTO media_type_param (mediatype_paramid, mediatypeid, name, value) VALUES (1302, 111, 'Subject', '{ALERT.SUBJECT}');
INSERT INTO media_type_param (mediatype_paramid, mediatypeid, name, value) VALUES (1303, 111, 'Message', '{ALERT.MESSAGE}');
INSERT INTO media_type_param (mediatype_paramid, mediatypeid, name, value) VALUES (1304, 111, 'HTTPProxy', '');
INSERT INTO media_type (mediatypeid, type, name, status, script, description) VALUES (102, 4, 'Validation webhook', 0, 'return 0;', 'Reference webhook media type for validation tests');
INSERT INTO media_type_param (mediatype_paramid, mediatypeid, name, value) VALUES (1305, 102, 'URL', '');
INSERT INTO media_type_param (mediatype_paramid, mediatypeid, name, value) VALUES (1306, 102, 'To', '{ALERT.SENDTO}');
INSERT INTO media_type_param (mediatype_paramid, mediatypeid, name, value) VALUES (1307, 102, 'Subject', '{ALERT.SUBJECT}');
INSERT INTO media_type_param (mediatype_paramid, mediatypeid, name, value) VALUES (1308, 102, 'Message', '{ALERT.MESSAGE}');
INSERT INTO media_type_param (mediatype_paramid, mediatypeid, name, value) VALUES (1309, 102, 'HTTPProxy', '');
INSERT INTO media_type (mediatypeid, type, name, status, script, show_event_menu, event_menu_name, event_menu_url, description) VALUES (103, 4, 'Webhook to delete', 0, 'return 0;', 1, 'Unique webhook url', 'zabbix.php?action=mediatype.list&ddreset={EVENT.TAGS.webhook}', 'Webhook media type to be deleted');
INSERT INTO media_type_param (mediatype_paramid, mediatypeid, name, value) VALUES (1310, 103, 'Parameter name to be deleted', 'Parameter value to be deleted');
INSERT INTO media_type_param (mediatype_paramid, mediatypeid, name, value) VALUES (1311, 103, '2nd parameter name to be deleted', '2nd parameter value to be deleted');

-- testPageProblems_ProblemLinks
INSERT INTO media_type (mediatypeid, type, name, status, script, show_event_menu, event_menu_name, event_menu_url, description) VALUES (104, 4, 'URL test webhook', 0, 'return 0;', 1, 'Webhook url for all', 'zabbix.php?action=mediatype.edit&mediatypeid=101', 'Webhook media type for URL test');
INSERT INTO event_tag (eventtagid, eventid, tag, value) VALUES (201, 9003, 'webhook', '1');
INSERT INTO problem_tag (problemtagid, eventid, tag, value) VALUES (201, 9003, 'webhook', '1');

-- Overrides for LLD Overrides test
INSERT INTO lld_override (lld_overrideid, itemid, name, step, evaltype, stop) values (5000, 133800, 'Override for update 1', 1, 1, 0);
INSERT INTO lld_override (lld_overrideid, itemid, name, step, evaltype, stop) values (5001, 133800, 'Override for update 2', 2, 0, 0);

INSERT INTO lld_override_condition (lld_override_conditionid, lld_overrideid, operator, macro, value) values (34000, 5000, 8, '{#MACRO1}', 'test expression_1');
INSERT INTO lld_override_condition (lld_override_conditionid, lld_overrideid, operator, macro, value) values (34001, 5000, 9, '{#MACRO2}', 'test expression_2');
INSERT INTO lld_override_condition (lld_override_conditionid, lld_overrideid, operator, macro, value) values (34002, 5000, 12, '{#MACRO3}', '');
INSERT INTO lld_override_condition (lld_override_conditionid, lld_overrideid, operator, macro, value) values (34003, 5000, 13, '{#MACRO4}', '');

INSERT INTO lld_override_operation (lld_override_operationid, lld_overrideid, operationobject, operator, value) values (40000, 5000, 0, 0, 'test item pattern');
INSERT INTO lld_override_operation (lld_override_operationid, lld_overrideid, operationobject, operator, value) values (40001, 5000, 1, 1, 'test trigger pattern');
INSERT INTO lld_override_operation (lld_override_operationid, lld_overrideid, operationobject, operator, value) values (40002, 5001, 2, 8, 'test graph pattern');
INSERT INTO lld_override_operation (lld_override_operationid, lld_overrideid, operationobject, operator, value) values (40003, 5001, 3, 9, 'test host pattern');

INSERT INTO lld_override_opdiscover (lld_override_operationid, discover) values (40000, 0);
INSERT INTO lld_override_opdiscover (lld_override_operationid, discover) values (40002, 0);

INSERT INTO lld_override_ophistory (lld_override_operationid, history) values (40000, 0);

INSERT INTO lld_override_opinventory (lld_override_operationid, inventory_mode) values (40003, 1);

INSERT INTO lld_override_opperiod (lld_override_operationid, delay) values (40000, '1m;50s/1-7,00:00-24:00;wd1-5h9-18');

INSERT INTO lld_override_opseverity (lld_override_operationid, severity) values (40001, 2);

INSERT INTO lld_override_opstatus (lld_override_operationid, status) values (40000, 0);

INSERT INTO lld_override_optag (lld_override_optagid, lld_override_operationid, tag, value) values (3000, 40001, 'tag1', 'value1');
INSERT INTO lld_override_optag (lld_override_optagid, lld_override_operationid, tag, value) values (3001, 40003, 'name1', 'value1');
INSERT INTO lld_override_optag (lld_override_optagid, lld_override_operationid, tag, value) values (3002, 40003, 'name2', 'value2');

INSERT INTO lld_override_optemplate (lld_override_optemplateid, lld_override_operationid, templateid) values (3000, 40003, 99137);

INSERT INTO lld_override_optrends (lld_override_operationid, trends) values (40000, 0);

UPDATE settings SET value_str='caf1c06dcf802728c4cfc24d645e1e73' WHERE name='session_key';

-- testPageHostPrototypes
INSERT INTO host_tag (hosttagid, hostid, tag, value) VALUES (9450, 90002, 'host_proto_tag_1', 'value1');
INSERT INTO host_tag (hosttagid, hostid, tag, value) VALUES (9451, 90002, 'host_proto_tag_2', 'value2');
