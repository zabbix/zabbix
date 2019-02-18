-- Disable "Zabbix Server" host
UPDATE hosts SET status=1 WHERE host='Zabbix server';

-- testLowLevelDiscovery
INSERT INTO hosts (hostid, host, name, status) VALUES (20001, 'discovery', 'Host for discovery tests', 1);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (90001, 20001, 4);
INSERT INTO interface(interfaceid, hostid, main, type) VALUES (30001, 20001, 1, 1);
INSERT INTO items (itemid, type, hostid, name, key_, value_type, templateid, valuemapid, flags, interfaceid) VALUES (80001, 2, 20001, 'Trapper discovery', 'item_discovery', 4, NULL, NULL, 1, 30001);
INSERT INTO items (itemid, type, hostid, name, key_, value_type, templateid, valuemapid, flags, interfaceid) VALUES (80002, 2, 20001, 'Item: {#KEY}', 'trap[{#KEY}]', 4, NULL, NULL, 2, 30001);
INSERT INTO item_discovery(itemdiscoveryid, itemid, parent_itemid) VALUES (10001, 80002, 80001);

-- testDataCollection
INSERT INTO hosts (hostid, host, name, status) VALUES (20002, 'agent', 'Host for agent tests', 1);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (90002, 20002, 4);
INSERT INTO interface(interfaceid, hostid, main, type) VALUES (30002, 20002, 1, 1);
INSERT INTO items (itemid, type, hostid, name, key_, value_type, templateid, valuemapid, interfaceid, delay) VALUES (80003, 7, 20002, 'Agent ping', 'agent.ping', 3, NULL, NULL, NULL, '1s');
INSERT INTO items (itemid, hostid, name, key_, value_type, templateid, valuemapid, interfaceid, delay) VALUES (80004, 20002, 'Agent hostname', 'agent.hostname', 4, NULL, NULL, 30001, '1s');
INSERT INTO hosts (hostid, host, name, status) VALUES (20003, 'custom_agent', 'Host for custom agent tests', 1);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (90003, 20003, 4);
INSERT INTO interface(interfaceid, hostid, main, type) VALUES (30003, 20003, 1, 1);
INSERT INTO items (itemid, type, hostid, name, key_, value_type, templateid, valuemapid, interfaceid, delay) VALUES (80005, 7, 20003, 'Custom metric 1', 'custom.metric', 4, NULL, NULL, NULL, '5s');
INSERT INTO items (itemid, type, hostid, name, key_, value_type, templateid, valuemapid, interfaceid, delay) VALUES (80006, 7, 20003, 'Custom metric 2', 'custom.metric[custom]', 4, NULL, NULL, NULL, '10s');

INSERT INTO hosts (hostid, host, status) VALUES (20004, 'proxy', 5);
INSERT INTO hosts (hostid, proxy_hostid, host, name, status) VALUES (20005, 20004, 'proxy_agent', 'Host for proxy tests', 1);
INSERT INTO hosts_groups (hostgroupid, hostid, groupid) VALUES (90004, 20005, 4);
INSERT INTO interface(interfaceid, hostid, main, type) VALUES (30004, 20005, 1, 1);
INSERT INTO items (itemid, type, hostid, name, key_, value_type, templateid, valuemapid, interfaceid, delay) VALUES (80007, 7, 20005, 'Agent ping', 'agent.ping', 3, NULL, NULL, NULL, '1s');
INSERT INTO items (itemid, hostid, name, key_, value_type, templateid, valuemapid, interfaceid, delay) VALUES (80008, 20005, 'Agent hostname', 'agent.hostname', 4, NULL, NULL, 30004, '1s');
