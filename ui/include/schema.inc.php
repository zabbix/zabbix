<?php
return [
	'role' => [
		'key' => 'roleid',
		'fields' => [
			'roleid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'readonly' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'users' => [
		'key' => 'userid',
		'fields' => [
			'userid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'username' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 100,
				'default' => ''
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 100,
				'default' => ''
			],
			'surname' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 100,
				'default' => ''
			],
			'passwd' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 60,
				'default' => ''
			],
			'url' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'autologin' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'autologout' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '15m'
			],
			'lang' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 7,
				'default' => 'default'
			],
			'refresh' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '30s'
			],
			'theme' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => 'default'
			],
			'attempt_failed' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10
			],
			'attempt_ip' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 39,
				'default' => ''
			],
			'attempt_clock' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10
			],
			'rows_per_page' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => 50
			],
			'timezone' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 50,
				'default' => 'default'
			],
			'roleid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'role',
				'ref_field' => 'roleid'
			]
		]
	],
	'maintenances' => [
		'key' => 'maintenanceid',
		'fields' => [
			'maintenanceid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'maintenance_type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'description' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			],
			'active_since' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'active_till' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'tags_evaltype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'hosts' => [
		'key' => 'hostid',
		'fields' => [
			'hostid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'proxy_hostid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			],
			'host' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'lastaccess' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'ipmi_authtype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '-1'
			],
			'ipmi_privilege' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '2'
			],
			'ipmi_username' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 16,
				'default' => ''
			],
			'ipmi_password' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 20,
				'default' => ''
			],
			'maintenanceid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'maintenances',
				'ref_field' => 'maintenanceid'
			],
			'maintenance_status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'maintenance_type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'maintenance_from' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'flags' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'templateid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			],
			'description' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			],
			'tls_connect' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'tls_accept' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'tls_issuer' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 1024,
				'default' => ''
			],
			'tls_subject' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 1024,
				'default' => ''
			],
			'tls_psk_identity' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'tls_psk' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 512,
				'default' => ''
			],
			'proxy_address' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'auto_compress' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'discover' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'custom_interfaces' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'uuid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => ''
			]
		]
	],
	'hstgrp' => [
		'key' => 'groupid',
		'fields' => [
			'groupid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'internal' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'flags' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'uuid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => ''
			]
		]
	],
	'group_prototype' => [
		'key' => 'group_prototypeid',
		'fields' => [
			'group_prototypeid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'hostid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'groupid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hstgrp',
				'ref_field' => 'groupid'
			],
			'templateid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'group_prototype',
				'ref_field' => 'group_prototypeid'
			]
		]
	],
	'group_discovery' => [
		'key' => 'groupid',
		'fields' => [
			'groupid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hstgrp',
				'ref_field' => 'groupid'
			],
			'parent_group_prototypeid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'group_prototype',
				'ref_field' => 'group_prototypeid'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'lastcheck' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'ts_delete' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'drules' => [
		'key' => 'druleid',
		'fields' => [
			'druleid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'proxy_hostid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'iprange' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'delay' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => '1h'
			],
			'nextcheck' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'dchecks' => [
		'key' => 'dcheckid',
		'fields' => [
			'dcheckid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'druleid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'drules',
				'ref_field' => 'druleid'
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'key_' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'snmp_community' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'ports' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => '0'
			],
			'snmpv3_securityname' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'snmpv3_securitylevel' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'snmpv3_authpassphrase' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'snmpv3_privpassphrase' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'uniq' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'snmpv3_authprotocol' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'snmpv3_privprotocol' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'snmpv3_contextname' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'host_source' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'name_source' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'httptest' => [
		'key' => 'httptestid',
		'fields' => [
			'httptestid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'nextcheck' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'delay' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => '1m'
			],
			'status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'agent' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => 'Zabbix'
			],
			'authentication' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'http_user' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'http_password' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'hostid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			],
			'templateid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'httptest',
				'ref_field' => 'httptestid'
			],
			'http_proxy' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'retries' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'ssl_cert_file' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'ssl_key_file' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'ssl_key_password' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'verify_peer' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'verify_host' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'uuid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => ''
			]
		]
	],
	'httpstep' => [
		'key' => 'httpstepid',
		'fields' => [
			'httpstepid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'httptestid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'httptest',
				'ref_field' => 'httptestid'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'no' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'url' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'timeout' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => '15s'
			],
			'posts' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			],
			'required' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'status_codes' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'follow_redirects' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'retrieve_mode' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'post_type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'interface' => [
		'key' => 'interfaceid',
		'fields' => [
			'interfaceid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'hostid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			],
			'main' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'useip' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'ip' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => '127.0.0.1'
			],
			'dns' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'port' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => '10050'
			],
			'available' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'error' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'errors_from' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'disable_until' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'valuemap' => [
		'key' => 'valuemapid',
		'fields' => [
			'valuemapid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'hostid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'uuid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => ''
			]
		]
	],
	'items' => [
		'key' => 'itemid',
		'fields' => [
			'itemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'snmp_oid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 512,
				'default' => ''
			],
			'hostid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'key_' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'delay' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 1024,
				'default' => '0'
			],
			'history' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => '90d'
			],
			'trends' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => '365d'
			],
			'status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'value_type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'trapper_hosts' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'units' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'formula' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'logtimefmt' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'templateid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'valuemapid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'valuemap',
				'ref_field' => 'valuemapid'
			],
			'params' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_NCLOB,
				'default' => ''
			],
			'ipmi_sensor' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'authtype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'username' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'password' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'publickey' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'privatekey' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'flags' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'interfaceid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'interface',
				'ref_field' => 'interfaceid'
			],
			'description' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_NCLOB,
				'default' => ''
			],
			'inventory_link' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'lifetime' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => '30d'
			],
			'evaltype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'jmx_endpoint' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'master_itemid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'timeout' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => '3s'
			],
			'url' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'query_fields' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'posts' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_NCLOB,
				'default' => ''
			],
			'status_codes' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => '200'
			],
			'follow_redirects' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'post_type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'http_proxy' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'headers' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_NCLOB,
				'default' => ''
			],
			'retrieve_mode' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'request_method' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'output_format' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'ssl_cert_file' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'ssl_key_file' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'ssl_key_password' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'verify_peer' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'verify_host' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'allow_traps' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'discover' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'uuid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => ''
			]
		]
	],
	'httpstepitem' => [
		'key' => 'httpstepitemid',
		'fields' => [
			'httpstepitemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'httpstepid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'httpstep',
				'ref_field' => 'httpstepid'
			],
			'itemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'httptestitem' => [
		'key' => 'httptestitemid',
		'fields' => [
			'httptestitemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'httptestid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'httptest',
				'ref_field' => 'httptestid'
			],
			'itemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'media_type' => [
		'key' => 'mediatypeid',
		'fields' => [
			'mediatypeid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 100,
				'default' => ''
			],
			'smtp_server' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'smtp_helo' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'smtp_email' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'exec_path' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'gsm_modem' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'username' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'passwd' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'smtp_port' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '25'
			],
			'smtp_security' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'smtp_verify_peer' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'smtp_verify_host' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'smtp_authentication' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'exec_params' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'maxsessions' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'maxattempts' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '3'
			],
			'attempt_interval' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '10s'
			],
			'content_type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'script' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_NCLOB,
				'default' => ''
			],
			'timeout' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '30s'
			],
			'process_tags' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'show_event_menu' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'event_menu_url' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'event_menu_name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'description' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			]
		]
	],
	'media_type_param' => [
		'key' => 'mediatype_paramid',
		'fields' => [
			'mediatype_paramid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'mediatypeid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'media_type',
				'ref_field' => 'mediatypeid'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			]
		]
	],
	'media_type_message' => [
		'key' => 'mediatype_messageid',
		'fields' => [
			'mediatype_messageid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'mediatypeid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'media_type',
				'ref_field' => 'mediatypeid'
			],
			'eventsource' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10
			],
			'recovery' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10
			],
			'subject' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'message' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_NCLOB,
				'default' => ''
			]
		]
	],
	'usrgrp' => [
		'key' => 'usrgrpid',
		'fields' => [
			'usrgrpid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'gui_access' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'users_status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'debug_mode' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'users_groups' => [
		'key' => 'id',
		'fields' => [
			'id' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'usrgrpid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'usrgrp',
				'ref_field' => 'usrgrpid'
			],
			'userid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'users',
				'ref_field' => 'userid'
			]
		]
	],
	'scripts' => [
		'key' => 'scriptid',
		'fields' => [
			'scriptid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'command' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_NCLOB,
				'default' => ''
			],
			'host_access' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '2'
			],
			'usrgrpid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'usrgrp',
				'ref_field' => 'usrgrpid'
			],
			'groupid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hstgrp',
				'ref_field' => 'groupid'
			],
			'description' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			],
			'confirmation' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '5'
			],
			'execute_on' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '2'
			],
			'timeout' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '30s'
			],
			'scope' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'port' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'authtype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'username' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'password' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'publickey' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'privatekey' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'menu_path' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'script_param' => [
		'key' => 'script_paramid',
		'fields' => [
			'script_paramid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'scriptid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'scripts',
				'ref_field' => 'scriptid'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			]
		]
	],
	'actions' => [
		'key' => 'actionid',
		'fields' => [
			'actionid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'eventsource' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'evaltype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'esc_period' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => '1h'
			],
			'formula' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 1024,
				'default' => ''
			],
			'pause_suppressed' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'notify_if_canceled' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			]
		]
	],
	'operations' => [
		'key' => 'operationid',
		'fields' => [
			'operationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'actionid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'actions',
				'ref_field' => 'actionid'
			],
			'operationtype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'esc_period' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => '0'
			],
			'esc_step_from' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'esc_step_to' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'evaltype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'recovery' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'opmessage' => [
		'key' => 'operationid',
		'fields' => [
			'operationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'operations',
				'ref_field' => 'operationid'
			],
			'default_msg' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'subject' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'message' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			],
			'mediatypeid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'media_type',
				'ref_field' => 'mediatypeid'
			]
		]
	],
	'opmessage_grp' => [
		'key' => 'opmessage_grpid',
		'fields' => [
			'opmessage_grpid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'operationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'operations',
				'ref_field' => 'operationid'
			],
			'usrgrpid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'usrgrp',
				'ref_field' => 'usrgrpid'
			]
		]
	],
	'opmessage_usr' => [
		'key' => 'opmessage_usrid',
		'fields' => [
			'opmessage_usrid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'operationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'operations',
				'ref_field' => 'operationid'
			],
			'userid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'users',
				'ref_field' => 'userid'
			]
		]
	],
	'opcommand' => [
		'key' => 'operationid',
		'fields' => [
			'operationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'operations',
				'ref_field' => 'operationid'
			],
			'scriptid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'scripts',
				'ref_field' => 'scriptid'
			]
		]
	],
	'opcommand_hst' => [
		'key' => 'opcommand_hstid',
		'fields' => [
			'opcommand_hstid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'operationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'operations',
				'ref_field' => 'operationid'
			],
			'hostid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			]
		]
	],
	'opcommand_grp' => [
		'key' => 'opcommand_grpid',
		'fields' => [
			'opcommand_grpid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'operationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'operations',
				'ref_field' => 'operationid'
			],
			'groupid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hstgrp',
				'ref_field' => 'groupid'
			]
		]
	],
	'opgroup' => [
		'key' => 'opgroupid',
		'fields' => [
			'opgroupid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'operationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'operations',
				'ref_field' => 'operationid'
			],
			'groupid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hstgrp',
				'ref_field' => 'groupid'
			]
		]
	],
	'optemplate' => [
		'key' => 'optemplateid',
		'fields' => [
			'optemplateid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'operationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'operations',
				'ref_field' => 'operationid'
			],
			'templateid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			]
		]
	],
	'opconditions' => [
		'key' => 'opconditionid',
		'fields' => [
			'opconditionid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'operationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'operations',
				'ref_field' => 'operationid'
			],
			'conditiontype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'operator' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'conditions' => [
		'key' => 'conditionid',
		'fields' => [
			'conditionid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'actionid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'actions',
				'ref_field' => 'actionid'
			],
			'conditiontype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'operator' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'value2' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'config' => [
		'key' => 'configid',
		'fields' => [
			'configid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'work_period' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => '1-5,09:00-18:00'
			],
			'alert_usrgrpid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'usrgrp',
				'ref_field' => 'usrgrpid'
			],
			'default_theme' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => 'blue-theme'
			],
			'authentication_type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'ldap_host' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'ldap_port' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => 389
			],
			'ldap_base_dn' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'ldap_bind_dn' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'ldap_bind_password' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'ldap_search_attribute' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'discovery_groupid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hstgrp',
				'ref_field' => 'groupid'
			],
			'max_in_table' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '50'
			],
			'search_limit' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1000'
			],
			'severity_color_0' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => '97AAB3'
			],
			'severity_color_1' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => '7499FF'
			],
			'severity_color_2' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => 'FFC859'
			],
			'severity_color_3' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => 'FFA059'
			],
			'severity_color_4' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => 'E97659'
			],
			'severity_color_5' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => 'E45959'
			],
			'severity_name_0' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => 'Not classified'
			],
			'severity_name_1' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => 'Information'
			],
			'severity_name_2' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => 'Warning'
			],
			'severity_name_3' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => 'Average'
			],
			'severity_name_4' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => 'High'
			],
			'severity_name_5' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => 'Disaster'
			],
			'ok_period' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '5m'
			],
			'blink_period' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '2m'
			],
			'problem_unack_color' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => 'CC0000'
			],
			'problem_ack_color' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => 'CC0000'
			],
			'ok_unack_color' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => '009900'
			],
			'ok_ack_color' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => '009900'
			],
			'problem_unack_style' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'problem_ack_style' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'ok_unack_style' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'ok_ack_style' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'snmptrap_logging' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'server_check_interval' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '10'
			],
			'hk_events_mode' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'hk_events_trigger' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '365d'
			],
			'hk_events_internal' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '1d'
			],
			'hk_events_discovery' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '1d'
			],
			'hk_events_autoreg' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '1d'
			],
			'hk_services_mode' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'hk_services' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '365d'
			],
			'hk_audit_mode' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'hk_audit' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '365d'
			],
			'hk_sessions_mode' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'hk_sessions' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '365d'
			],
			'hk_history_mode' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'hk_history_global' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'hk_history' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '90d'
			],
			'hk_trends_mode' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'hk_trends_global' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'hk_trends' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '365d'
			],
			'default_inventory_mode' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '-1'
			],
			'custom_color' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'http_auth_enabled' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'http_login_form' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'http_strip_domains' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'http_case_sensitive' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'ldap_configured' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'ldap_case_sensitive' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'db_extension' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => ''
			],
			'autoreg_tls_accept' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'compression_status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'compress_older' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '7d'
			],
			'instanceid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => ''
			],
			'saml_auth_enabled' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'saml_idp_entityid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 1024,
				'default' => ''
			],
			'saml_sso_url' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'saml_slo_url' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'saml_username_attribute' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'saml_sp_entityid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 1024,
				'default' => ''
			],
			'saml_nameid_format' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'saml_sign_messages' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'saml_sign_assertions' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'saml_sign_authn_requests' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'saml_sign_logout_requests' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'saml_sign_logout_responses' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'saml_encrypt_nameid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'saml_encrypt_assertions' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'saml_case_sensitive' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'default_lang' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 5,
				'default' => 'en_US'
			],
			'default_timezone' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 50,
				'default' => 'system'
			],
			'login_attempts' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '5'
			],
			'login_block' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '30s'
			],
			'show_technical_errors' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'validate_uri_schemes' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'uri_valid_schemes' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => 'http,https,ftp,file,mailto,tel,ssh'
			],
			'x_frame_options' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => 'SAMEORIGIN'
			],
			'iframe_sandboxing_enabled' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'iframe_sandboxing_exceptions' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'max_overview_table_size' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '50'
			],
			'history_period' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '24h'
			],
			'period_default' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '1h'
			],
			'max_period' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '2y'
			],
			'socket_timeout' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '3s'
			],
			'connect_timeout' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '3s'
			],
			'media_type_test_timeout' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '65s'
			],
			'script_timeout' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '60s'
			],
			'item_test_timeout' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '60s'
			],
			'session_key' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => ''
			],
			'url' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'report_test_timeout' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '60s'
			],
			'dbversion_status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			],
			'hk_events_service' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '1d'
			],
			'passwd_min_length' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '8'
			],
			'passwd_check_rules' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '8'
			],
			'auditlog_enabled' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'ha_failover_delay' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => '1m'
			],
			'geomaps_tile_provider' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'geomaps_tile_url' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 1024,
				'default' => ''
			],
			'geomaps_max_zoom' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'geomaps_attribution' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 1024,
				'default' => ''
			]
		]
	],
	'triggers' => [
		'key' => 'triggerid',
		'fields' => [
			'triggerid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'expression' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'description' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'url' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'priority' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'lastchange' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'comments' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			],
			'error' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'templateid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'triggers',
				'ref_field' => 'triggerid'
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'state' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'flags' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'recovery_mode' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'recovery_expression' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'correlation_mode' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'correlation_tag' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'manual_close' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'opdata' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'discover' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'event_name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'uuid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => ''
			]
		]
	],
	'trigger_depends' => [
		'key' => 'triggerdepid',
		'fields' => [
			'triggerdepid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'triggerid_down' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'triggers',
				'ref_field' => 'triggerid'
			],
			'triggerid_up' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'triggers',
				'ref_field' => 'triggerid'
			]
		]
	],
	'functions' => [
		'key' => 'functionid',
		'fields' => [
			'functionid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'itemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'triggerid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'triggers',
				'ref_field' => 'triggerid'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 12,
				'default' => ''
			],
			'parameter' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => '0'
			]
		]
	],
	'graphs' => [
		'key' => 'graphid',
		'fields' => [
			'graphid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'width' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '900'
			],
			'height' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '200'
			],
			'yaxismin' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_FLOAT,
				'default' => '0'
			],
			'yaxismax' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_FLOAT,
				'default' => '100'
			],
			'templateid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'graphs',
				'ref_field' => 'graphid'
			],
			'show_work_period' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'show_triggers' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'graphtype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'show_legend' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'show_3d' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'percent_left' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_FLOAT,
				'default' => '0'
			],
			'percent_right' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_FLOAT,
				'default' => '0'
			],
			'ymin_type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'ymax_type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'ymin_itemid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'ymax_itemid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'flags' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'discover' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'uuid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => ''
			]
		]
	],
	'graphs_items' => [
		'key' => 'gitemid',
		'fields' => [
			'gitemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'graphid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'graphs',
				'ref_field' => 'graphid'
			],
			'itemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'drawtype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'sortorder' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'color' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => '009600'
			],
			'yaxisside' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'calc_fnc' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '2'
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'graph_theme' => [
		'key' => 'graphthemeid',
		'fields' => [
			'graphthemeid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'theme' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'backgroundcolor' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => ''
			],
			'graphcolor' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => ''
			],
			'gridcolor' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => ''
			],
			'maingridcolor' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => ''
			],
			'gridbordercolor' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => ''
			],
			'textcolor' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => ''
			],
			'highlightcolor' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => ''
			],
			'leftpercentilecolor' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => ''
			],
			'rightpercentilecolor' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => ''
			],
			'nonworktimecolor' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => ''
			],
			'colorpalette' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'globalmacro' => [
		'key' => 'globalmacroid',
		'fields' => [
			'globalmacroid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'macro' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'description' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'hostmacro' => [
		'key' => 'hostmacroid',
		'fields' => [
			'hostmacroid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'hostid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			],
			'macro' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'description' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'hosts_groups' => [
		'key' => 'hostgroupid',
		'fields' => [
			'hostgroupid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'hostid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			],
			'groupid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hstgrp',
				'ref_field' => 'groupid'
			]
		]
	],
	'hosts_templates' => [
		'key' => 'hosttemplateid',
		'fields' => [
			'hosttemplateid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'hostid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			],
			'templateid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			]
		]
	],
	'valuemap_mapping' => [
		'key' => 'valuemap_mappingid',
		'fields' => [
			'valuemap_mappingid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'valuemapid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'valuemap',
				'ref_field' => 'valuemapid'
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'newvalue' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'sortorder' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'media' => [
		'key' => 'mediaid',
		'fields' => [
			'mediaid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'userid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'users',
				'ref_field' => 'userid'
			],
			'mediatypeid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'media_type',
				'ref_field' => 'mediatypeid'
			],
			'sendto' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 1024,
				'default' => ''
			],
			'active' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'severity' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '63'
			],
			'period' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 1024,
				'default' => '1-7,00:00-24:00'
			]
		]
	],
	'rights' => [
		'key' => 'rightid',
		'fields' => [
			'rightid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'groupid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'usrgrp',
				'ref_field' => 'usrgrpid'
			],
			'permission' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'id' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hstgrp',
				'ref_field' => 'groupid'
			]
		]
	],
	'services' => [
		'key' => 'serviceid',
		'fields' => [
			'serviceid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '-1'
			],
			'algorithm' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'sortorder' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'weight' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'propagation_rule' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'propagation_value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'description' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			],
			'uuid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => ''
			],
			'created_at' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'services_links' => [
		'key' => 'linkid',
		'fields' => [
			'linkid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'serviceupid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'services',
				'ref_field' => 'serviceid'
			],
			'servicedownid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'services',
				'ref_field' => 'serviceid'
			]
		]
	],
	'icon_map' => [
		'key' => 'iconmapid',
		'fields' => [
			'iconmapid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'default_iconid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'images',
				'ref_field' => 'imageid'
			]
		]
	],
	'icon_mapping' => [
		'key' => 'iconmappingid',
		'fields' => [
			'iconmappingid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'iconmapid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'icon_map',
				'ref_field' => 'iconmapid'
			],
			'iconid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'images',
				'ref_field' => 'imageid'
			],
			'inventory_link' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'expression' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'sortorder' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'sysmaps' => [
		'key' => 'sysmapid',
		'fields' => [
			'sysmapid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'width' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '600'
			],
			'height' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '400'
			],
			'backgroundid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'images',
				'ref_field' => 'imageid'
			],
			'label_type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '2'
			],
			'label_location' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'highlight' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'expandproblem' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'markelements' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'show_unack' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'grid_size' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '50'
			],
			'grid_show' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'grid_align' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'label_format' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'label_type_host' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '2'
			],
			'label_type_hostgroup' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '2'
			],
			'label_type_trigger' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '2'
			],
			'label_type_map' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '2'
			],
			'label_type_image' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '2'
			],
			'label_string_host' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'label_string_hostgroup' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'label_string_trigger' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'label_string_map' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'label_string_image' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'iconmapid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'icon_map',
				'ref_field' => 'iconmapid'
			],
			'expand_macros' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'severity_min' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'userid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'users',
				'ref_field' => 'userid'
			],
			'private' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'show_suppressed' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'sysmaps_elements' => [
		'key' => 'selementid',
		'fields' => [
			'selementid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'sysmapid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'sysmaps',
				'ref_field' => 'sysmapid'
			],
			'elementid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'default' => '0'
			],
			'elementtype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'iconid_off' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'images',
				'ref_field' => 'imageid'
			],
			'iconid_on' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'images',
				'ref_field' => 'imageid'
			],
			'label' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'label_location' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '-1'
			],
			'x' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'y' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'iconid_disabled' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'images',
				'ref_field' => 'imageid'
			],
			'iconid_maintenance' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'images',
				'ref_field' => 'imageid'
			],
			'elementsubtype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'areatype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'width' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '200'
			],
			'height' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '200'
			],
			'viewtype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'use_iconmap' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'evaltype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'sysmaps_links' => [
		'key' => 'linkid',
		'fields' => [
			'linkid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'sysmapid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'sysmaps',
				'ref_field' => 'sysmapid'
			],
			'selementid1' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'sysmaps_elements',
				'ref_field' => 'selementid'
			],
			'selementid2' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'sysmaps_elements',
				'ref_field' => 'selementid'
			],
			'drawtype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'color' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => '000000'
			],
			'label' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			]
		]
	],
	'sysmaps_link_triggers' => [
		'key' => 'linktriggerid',
		'fields' => [
			'linktriggerid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'linkid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'sysmaps_links',
				'ref_field' => 'linkid'
			],
			'triggerid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'triggers',
				'ref_field' => 'triggerid'
			],
			'drawtype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'color' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => '000000'
			]
		]
	],
	'sysmap_element_url' => [
		'key' => 'sysmapelementurlid',
		'fields' => [
			'sysmapelementurlid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'selementid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'sysmaps_elements',
				'ref_field' => 'selementid'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255
			],
			'url' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'sysmap_url' => [
		'key' => 'sysmapurlid',
		'fields' => [
			'sysmapurlid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'sysmapid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'sysmaps',
				'ref_field' => 'sysmapid'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255
			],
			'url' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'elementtype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'sysmap_user' => [
		'key' => 'sysmapuserid',
		'fields' => [
			'sysmapuserid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'sysmapid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'sysmaps',
				'ref_field' => 'sysmapid'
			],
			'userid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'users',
				'ref_field' => 'userid'
			],
			'permission' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '2'
			]
		]
	],
	'sysmap_usrgrp' => [
		'key' => 'sysmapusrgrpid',
		'fields' => [
			'sysmapusrgrpid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'sysmapid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'sysmaps',
				'ref_field' => 'sysmapid'
			],
			'usrgrpid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'usrgrp',
				'ref_field' => 'usrgrpid'
			],
			'permission' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '2'
			]
		]
	],
	'maintenances_hosts' => [
		'key' => 'maintenance_hostid',
		'fields' => [
			'maintenance_hostid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'maintenanceid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'maintenances',
				'ref_field' => 'maintenanceid'
			],
			'hostid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			]
		]
	],
	'maintenances_groups' => [
		'key' => 'maintenance_groupid',
		'fields' => [
			'maintenance_groupid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'maintenanceid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'maintenances',
				'ref_field' => 'maintenanceid'
			],
			'groupid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hstgrp',
				'ref_field' => 'groupid'
			]
		]
	],
	'timeperiods' => [
		'key' => 'timeperiodid',
		'fields' => [
			'timeperiodid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'timeperiod_type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'every' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'month' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'dayofweek' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'day' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'start_time' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'period' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'start_date' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'maintenances_windows' => [
		'key' => 'maintenance_timeperiodid',
		'fields' => [
			'maintenance_timeperiodid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'maintenanceid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'maintenances',
				'ref_field' => 'maintenanceid'
			],
			'timeperiodid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'timeperiods',
				'ref_field' => 'timeperiodid'
			]
		]
	],
	'regexps' => [
		'key' => 'regexpid',
		'fields' => [
			'regexpid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'test_string' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			]
		]
	],
	'expressions' => [
		'key' => 'expressionid',
		'fields' => [
			'expressionid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'regexpid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'regexps',
				'ref_field' => 'regexpid'
			],
			'expression' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'expression_type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'exp_delimiter' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 1,
				'default' => ''
			],
			'case_sensitive' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'ids' => [
		'key' => 'table_name,field_name',
		'fields' => [
			'table_name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'field_name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'nextid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			]
		]
	],
	'alerts' => [
		'key' => 'alertid',
		'fields' => [
			'alertid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'actionid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'actions',
				'ref_field' => 'actionid'
			],
			'eventid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'events',
				'ref_field' => 'eventid'
			],
			'userid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'users',
				'ref_field' => 'userid'
			],
			'clock' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'mediatypeid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'media_type',
				'ref_field' => 'mediatypeid'
			],
			'sendto' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 1024,
				'default' => ''
			],
			'subject' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'message' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_NCLOB,
				'default' => ''
			],
			'status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'retries' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'error' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'esc_step' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'alerttype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'p_eventid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'events',
				'ref_field' => 'eventid'
			],
			'acknowledgeid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'acknowledges',
				'ref_field' => 'acknowledgeid'
			],
			'parameters' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_NCLOB,
				'default' => '{}'
			]
		]
	],
	'history' => [
		'key' => 'itemid,clock,ns',
		'fields' => [
			'itemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'clock' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_FLOAT,
				'default' => '0.0000'
			],
			'ns' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'history_uint' => [
		'key' => 'itemid,clock,ns',
		'fields' => [
			'itemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'clock' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_UINT,
				'length' => 20,
				'default' => '0'
			],
			'ns' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'history_str' => [
		'key' => 'itemid,clock,ns',
		'fields' => [
			'itemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'clock' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'ns' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'history_log' => [
		'key' => 'itemid,clock,ns',
		'fields' => [
			'itemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'clock' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'timestamp' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'source' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'severity' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_NCLOB,
				'default' => ''
			],
			'logeventid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'ns' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'history_text' => [
		'key' => 'itemid,clock,ns',
		'fields' => [
			'itemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'clock' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_NCLOB,
				'default' => ''
			],
			'ns' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'proxy_history' => [
		'key' => 'id',
		'fields' => [
			'id' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_UINT,
				'length' => 20
			],
			'itemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'clock' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'timestamp' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'source' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'severity' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_NCLOB,
				'default' => ''
			],
			'logeventid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'ns' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'state' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'lastlogsize' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_UINT,
				'length' => 20,
				'default' => '0'
			],
			'mtime' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'flags' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'write_clock' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'proxy_dhistory' => [
		'key' => 'id',
		'fields' => [
			'id' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_UINT,
				'length' => 20
			],
			'clock' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'druleid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'drules',
				'ref_field' => 'druleid'
			],
			'ip' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 39,
				'default' => ''
			],
			'port' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'dcheckid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'dchecks',
				'ref_field' => 'dcheckid'
			],
			'dns' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'events' => [
		'key' => 'eventid',
		'fields' => [
			'eventid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'source' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'object' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'objectid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'default' => '0'
			],
			'clock' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'acknowledged' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'ns' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'severity' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'trends' => [
		'key' => 'itemid,clock',
		'fields' => [
			'itemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'clock' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'num' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'value_min' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_FLOAT,
				'default' => '0.0000'
			],
			'value_avg' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_FLOAT,
				'default' => '0.0000'
			],
			'value_max' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_FLOAT,
				'default' => '0.0000'
			]
		]
	],
	'trends_uint' => [
		'key' => 'itemid,clock',
		'fields' => [
			'itemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'clock' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'num' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'value_min' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_UINT,
				'length' => 20,
				'default' => '0'
			],
			'value_avg' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_UINT,
				'length' => 20,
				'default' => '0'
			],
			'value_max' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_UINT,
				'length' => 20,
				'default' => '0'
			]
		]
	],
	'acknowledges' => [
		'key' => 'acknowledgeid',
		'fields' => [
			'acknowledgeid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'userid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'users',
				'ref_field' => 'userid'
			],
			'eventid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'events',
				'ref_field' => 'eventid'
			],
			'clock' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'message' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'action' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'old_severity' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'new_severity' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'auditlog' => [
		'key' => 'auditid',
		'fields' => [
			'auditid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CUID,
				'length' => 25
			],
			'userid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'username' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 100,
				'default' => ''
			],
			'clock' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'ip' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 39,
				'default' => ''
			],
			'action' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'resourcetype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'resourceid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'resource_cuid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_CUID,
				'length' => 25
			],
			'resourcename' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'recordsetid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CUID,
				'length' => 25
			],
			'details' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_NCLOB,
				'default' => ''
			]
		]
	],
	'service_alarms' => [
		'key' => 'servicealarmid',
		'fields' => [
			'servicealarmid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'serviceid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'services',
				'ref_field' => 'serviceid'
			],
			'clock' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '-1'
			]
		]
	],
	'autoreg_host' => [
		'key' => 'autoreg_hostid',
		'fields' => [
			'autoreg_hostid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'proxy_hostid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			],
			'host' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'listen_ip' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 39,
				'default' => ''
			],
			'listen_port' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'listen_dns' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'host_metadata' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'flags' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'tls_accepted' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			]
		]
	],
	'proxy_autoreg_host' => [
		'key' => 'id',
		'fields' => [
			'id' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_UINT,
				'length' => 20
			],
			'clock' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'host' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'listen_ip' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 39,
				'default' => ''
			],
			'listen_port' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'listen_dns' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'host_metadata' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'flags' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'tls_accepted' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			]
		]
	],
	'dhosts' => [
		'key' => 'dhostid',
		'fields' => [
			'dhostid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'druleid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'drules',
				'ref_field' => 'druleid'
			],
			'status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'lastup' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'lastdown' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'dservices' => [
		'key' => 'dserviceid',
		'fields' => [
			'dserviceid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'dhostid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'dhosts',
				'ref_field' => 'dhostid'
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'port' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'lastup' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'lastdown' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'dcheckid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'dchecks',
				'ref_field' => 'dcheckid'
			],
			'ip' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 39,
				'default' => ''
			],
			'dns' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'escalations' => [
		'key' => 'escalationid',
		'fields' => [
			'escalationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'actionid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'actions',
				'ref_field' => 'actionid'
			],
			'triggerid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'triggers',
				'ref_field' => 'triggerid'
			],
			'eventid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'events',
				'ref_field' => 'eventid'
			],
			'r_eventid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'events',
				'ref_field' => 'eventid'
			],
			'nextcheck' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'esc_step' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'itemid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'acknowledgeid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'acknowledges',
				'ref_field' => 'acknowledgeid'
			],
			'servicealarmid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'service_alarms',
				'ref_field' => 'servicealarmid'
			],
			'serviceid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'services',
				'ref_field' => 'serviceid'
			]
		]
	],
	'globalvars' => [
		'key' => 'globalvarid',
		'fields' => [
			'globalvarid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'snmp_lastsize' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_UINT,
				'length' => 20,
				'default' => '0'
			]
		]
	],
	'graph_discovery' => [
		'key' => 'graphid',
		'fields' => [
			'graphid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'graphs',
				'ref_field' => 'graphid'
			],
			'parent_graphid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'graphs',
				'ref_field' => 'graphid'
			],
			'lastcheck' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'ts_delete' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'host_inventory' => [
		'key' => 'hostid',
		'fields' => [
			'hostid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			],
			'inventory_mode' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'type_full' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'alias' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'os' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'os_full' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'os_short' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'serialno_a' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'serialno_b' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'tag' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'asset_tag' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'macaddress_a' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'macaddress_b' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'hardware' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'hardware_full' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			],
			'software' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'software_full' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			],
			'software_app_a' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'software_app_b' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'software_app_c' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'software_app_d' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'software_app_e' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'contact' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			],
			'location' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			],
			'location_lat' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 16,
				'default' => ''
			],
			'location_lon' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 16,
				'default' => ''
			],
			'notes' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			],
			'chassis' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'model' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'hw_arch' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => ''
			],
			'vendor' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'contract_number' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'installer_name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'deployment_status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'url_a' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'url_b' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'url_c' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'host_networks' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			],
			'host_netmask' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 39,
				'default' => ''
			],
			'host_router' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 39,
				'default' => ''
			],
			'oob_ip' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 39,
				'default' => ''
			],
			'oob_netmask' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 39,
				'default' => ''
			],
			'oob_router' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 39,
				'default' => ''
			],
			'date_hw_purchase' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'date_hw_install' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'date_hw_expiry' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'date_hw_decomm' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'site_address_a' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'site_address_b' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'site_address_c' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'site_city' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'site_state' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'site_country' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'site_zip' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'site_rack' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'site_notes' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			],
			'poc_1_name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'poc_1_email' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'poc_1_phone_a' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'poc_1_phone_b' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'poc_1_cell' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'poc_1_screen' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'poc_1_notes' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			],
			'poc_2_name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'poc_2_email' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'poc_2_phone_a' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'poc_2_phone_b' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'poc_2_cell' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'poc_2_screen' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'poc_2_notes' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			]
		]
	],
	'housekeeper' => [
		'key' => 'housekeeperid',
		'fields' => [
			'housekeeperid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'tablename' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'field' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'value'
			]
		]
	],
	'images' => [
		'key' => 'imageid',
		'fields' => [
			'imageid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'imagetype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => '0'
			],
			'image' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_BLOB,
				'length' => 2048,
				'default' => ''
			]
		]
	],
	'item_discovery' => [
		'key' => 'itemdiscoveryid',
		'fields' => [
			'itemdiscoveryid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'itemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'parent_itemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'key_' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'lastcheck' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'ts_delete' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'host_discovery' => [
		'key' => 'hostid',
		'fields' => [
			'hostid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			],
			'parent_hostid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			],
			'parent_itemid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'host' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'lastcheck' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'ts_delete' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'interface_discovery' => [
		'key' => 'interfaceid',
		'fields' => [
			'interfaceid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'interface',
				'ref_field' => 'interfaceid'
			],
			'parent_interfaceid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'interface',
				'ref_field' => 'interfaceid'
			]
		]
	],
	'profiles' => [
		'key' => 'profileid',
		'fields' => [
			'profileid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'userid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'users',
				'ref_field' => 'userid'
			],
			'idx' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 96,
				'default' => ''
			],
			'idx2' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'default' => '0'
			],
			'value_id' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'default' => '0'
			],
			'value_int' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'value_str' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_NCLOB,
				'default' => ''
			],
			'source' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 96,
				'default' => ''
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'sessions' => [
		'key' => 'sessionid',
		'fields' => [
			'sessionid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => ''
			],
			'userid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'users',
				'ref_field' => 'userid'
			],
			'lastaccess' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'trigger_discovery' => [
		'key' => 'triggerid',
		'fields' => [
			'triggerid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'triggers',
				'ref_field' => 'triggerid'
			],
			'parent_triggerid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'triggers',
				'ref_field' => 'triggerid'
			],
			'lastcheck' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'ts_delete' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'item_condition' => [
		'key' => 'item_conditionid',
		'fields' => [
			'item_conditionid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'itemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'operator' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '8'
			],
			'macro' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'item_rtdata' => [
		'key' => 'itemid',
		'fields' => [
			'itemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'lastlogsize' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_UINT,
				'length' => 20,
				'default' => '0'
			],
			'state' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'mtime' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'error' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			]
		]
	],
	'opinventory' => [
		'key' => 'operationid',
		'fields' => [
			'operationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'operations',
				'ref_field' => 'operationid'
			],
			'inventory_mode' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'trigger_tag' => [
		'key' => 'triggertagid',
		'fields' => [
			'triggertagid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'triggerid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'triggers',
				'ref_field' => 'triggerid'
			],
			'tag' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'event_tag' => [
		'key' => 'eventtagid',
		'fields' => [
			'eventtagid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'eventid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'events',
				'ref_field' => 'eventid'
			],
			'tag' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'problem' => [
		'key' => 'eventid',
		'fields' => [
			'eventid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'events',
				'ref_field' => 'eventid'
			],
			'source' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'object' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'objectid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'default' => '0'
			],
			'clock' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'ns' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'r_eventid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'events',
				'ref_field' => 'eventid'
			],
			'r_clock' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'r_ns' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'correlationid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'correlation',
				'ref_field' => 'correlationid'
			],
			'userid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'users',
				'ref_field' => 'userid'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'acknowledged' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'severity' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'problem_tag' => [
		'key' => 'problemtagid',
		'fields' => [
			'problemtagid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'eventid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'problem',
				'ref_field' => 'eventid'
			],
			'tag' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'tag_filter' => [
		'key' => 'tag_filterid',
		'fields' => [
			'tag_filterid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'usrgrpid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'usrgrp',
				'ref_field' => 'usrgrpid'
			],
			'groupid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hstgrp',
				'ref_field' => 'groupid'
			],
			'tag' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'event_recovery' => [
		'key' => 'eventid',
		'fields' => [
			'eventid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'events',
				'ref_field' => 'eventid'
			],
			'r_eventid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'events',
				'ref_field' => 'eventid'
			],
			'c_eventid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'events',
				'ref_field' => 'eventid'
			],
			'correlationid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'correlation',
				'ref_field' => 'correlationid'
			],
			'userid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'users',
				'ref_field' => 'userid'
			]
		]
	],
	'correlation' => [
		'key' => 'correlationid',
		'fields' => [
			'correlationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'description' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			],
			'evaltype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'formula' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'corr_condition' => [
		'key' => 'corr_conditionid',
		'fields' => [
			'corr_conditionid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'correlationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'correlation',
				'ref_field' => 'correlationid'
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'corr_condition_tag' => [
		'key' => 'corr_conditionid',
		'fields' => [
			'corr_conditionid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'corr_condition',
				'ref_field' => 'corr_conditionid'
			],
			'tag' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'corr_condition_group' => [
		'key' => 'corr_conditionid',
		'fields' => [
			'corr_conditionid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'corr_condition',
				'ref_field' => 'corr_conditionid'
			],
			'operator' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'groupid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hstgrp',
				'ref_field' => 'groupid'
			]
		]
	],
	'corr_condition_tagpair' => [
		'key' => 'corr_conditionid',
		'fields' => [
			'corr_conditionid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'corr_condition',
				'ref_field' => 'corr_conditionid'
			],
			'oldtag' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'newtag' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'corr_condition_tagvalue' => [
		'key' => 'corr_conditionid',
		'fields' => [
			'corr_conditionid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'corr_condition',
				'ref_field' => 'corr_conditionid'
			],
			'tag' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'operator' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'corr_operation' => [
		'key' => 'corr_operationid',
		'fields' => [
			'corr_operationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'correlationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'correlation',
				'ref_field' => 'correlationid'
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'task' => [
		'key' => 'taskid',
		'fields' => [
			'taskid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10
			],
			'status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'clock' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'ttl' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'proxy_hostid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			]
		]
	],
	'task_close_problem' => [
		'key' => 'taskid',
		'fields' => [
			'taskid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'task',
				'ref_field' => 'taskid'
			],
			'acknowledgeid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'acknowledges',
				'ref_field' => 'acknowledgeid'
			]
		]
	],
	'item_preproc' => [
		'key' => 'item_preprocid',
		'fields' => [
			'item_preprocid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'itemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'step' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'params' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_NCLOB,
				'default' => ''
			],
			'error_handler' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'error_handler_params' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'task_remote_command' => [
		'key' => 'taskid',
		'fields' => [
			'taskid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'task',
				'ref_field' => 'taskid'
			],
			'command_type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'execute_on' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'port' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'authtype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'username' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'password' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'publickey' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'privatekey' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'command' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_NCLOB,
				'default' => ''
			],
			'alertid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'alerts',
				'ref_field' => 'alertid'
			],
			'parent_taskid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'task',
				'ref_field' => 'taskid'
			],
			'hostid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			]
		]
	],
	'task_remote_command_result' => [
		'key' => 'taskid',
		'fields' => [
			'taskid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'task',
				'ref_field' => 'taskid'
			],
			'status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'parent_taskid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'task',
				'ref_field' => 'taskid'
			],
			'info' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			]
		]
	],
	'task_data' => [
		'key' => 'taskid',
		'fields' => [
			'taskid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'task',
				'ref_field' => 'taskid'
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'data' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_NCLOB,
				'default' => ''
			],
			'parent_taskid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'task',
				'ref_field' => 'taskid'
			]
		]
	],
	'task_result' => [
		'key' => 'taskid',
		'fields' => [
			'taskid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'task',
				'ref_field' => 'taskid'
			],
			'status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'parent_taskid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'task',
				'ref_field' => 'taskid'
			],
			'info' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_NCLOB,
				'default' => ''
			]
		]
	],
	'task_acknowledge' => [
		'key' => 'taskid',
		'fields' => [
			'taskid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'task',
				'ref_field' => 'taskid'
			],
			'acknowledgeid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'acknowledges',
				'ref_field' => 'acknowledgeid'
			]
		]
	],
	'sysmap_shape' => [
		'key' => 'sysmap_shapeid',
		'fields' => [
			'sysmap_shapeid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'sysmapid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'sysmaps',
				'ref_field' => 'sysmapid'
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'x' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'y' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'width' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '200'
			],
			'height' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '200'
			],
			'text' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			],
			'font' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '9'
			],
			'font_size' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '11'
			],
			'font_color' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => '000000'
			],
			'text_halign' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'text_valign' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'border_type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'border_width' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'border_color' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => '000000'
			],
			'background_color' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 6,
				'default' => ''
			],
			'zindex' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'sysmap_element_trigger' => [
		'key' => 'selement_triggerid',
		'fields' => [
			'selement_triggerid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'selementid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'sysmaps_elements',
				'ref_field' => 'selementid'
			],
			'triggerid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'triggers',
				'ref_field' => 'triggerid'
			]
		]
	],
	'httptest_field' => [
		'key' => 'httptest_fieldid',
		'fields' => [
			'httptest_fieldid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'httptestid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'httptest',
				'ref_field' => 'httptestid'
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			]
		]
	],
	'httpstep_field' => [
		'key' => 'httpstep_fieldid',
		'fields' => [
			'httpstep_fieldid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'httpstepid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'httpstep',
				'ref_field' => 'httpstepid'
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			]
		]
	],
	'dashboard' => [
		'key' => 'dashboardid',
		'fields' => [
			'dashboardid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255
			],
			'userid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'users',
				'ref_field' => 'userid'
			],
			'private' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'templateid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			],
			'display_period' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '30'
			],
			'auto_start' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'uuid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 32,
				'default' => ''
			]
		]
	],
	'dashboard_user' => [
		'key' => 'dashboard_userid',
		'fields' => [
			'dashboard_userid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'dashboardid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'dashboard',
				'ref_field' => 'dashboardid'
			],
			'userid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'users',
				'ref_field' => 'userid'
			],
			'permission' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '2'
			]
		]
	],
	'dashboard_usrgrp' => [
		'key' => 'dashboard_usrgrpid',
		'fields' => [
			'dashboard_usrgrpid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'dashboardid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'dashboard',
				'ref_field' => 'dashboardid'
			],
			'usrgrpid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'usrgrp',
				'ref_field' => 'usrgrpid'
			],
			'permission' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '2'
			]
		]
	],
	'dashboard_page' => [
		'key' => 'dashboard_pageid',
		'fields' => [
			'dashboard_pageid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'dashboardid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'dashboard',
				'ref_field' => 'dashboardid'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'display_period' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'sortorder' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'widget' => [
		'key' => 'widgetid',
		'fields' => [
			'widgetid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'x' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'y' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'width' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'height' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '2'
			],
			'view_mode' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'dashboard_pageid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'dashboard_page',
				'ref_field' => 'dashboard_pageid'
			]
		]
	],
	'widget_field' => [
		'key' => 'widget_fieldid',
		'fields' => [
			'widget_fieldid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'widgetid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'widget',
				'ref_field' => 'widgetid'
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'value_int' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'value_str' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'value_groupid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hstgrp',
				'ref_field' => 'groupid'
			],
			'value_hostid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			],
			'value_itemid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'value_graphid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'graphs',
				'ref_field' => 'graphid'
			],
			'value_sysmapid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'sysmaps',
				'ref_field' => 'sysmapid'
			],
			'value_serviceid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'services',
				'ref_field' => 'serviceid'
			],
			'value_slaid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'sla',
				'ref_field' => 'slaid'
			]
		]
	],
	'task_check_now' => [
		'key' => 'taskid',
		'fields' => [
			'taskid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'task',
				'ref_field' => 'taskid'
			],
			'itemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			]
		]
	],
	'event_suppress' => [
		'key' => 'event_suppressid',
		'fields' => [
			'event_suppressid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'eventid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'events',
				'ref_field' => 'eventid'
			],
			'maintenanceid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'maintenances',
				'ref_field' => 'maintenanceid'
			],
			'suppress_until' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'maintenance_tag' => [
		'key' => 'maintenancetagid',
		'fields' => [
			'maintenancetagid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'maintenanceid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'maintenances',
				'ref_field' => 'maintenanceid'
			],
			'tag' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'operator' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '2'
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'lld_macro_path' => [
		'key' => 'lld_macro_pathid',
		'fields' => [
			'lld_macro_pathid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'itemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'lld_macro' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'path' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'host_tag' => [
		'key' => 'hosttagid',
		'fields' => [
			'hosttagid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'hostid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			],
			'tag' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'config_autoreg_tls' => [
		'key' => 'autoreg_tlsid',
		'fields' => [
			'autoreg_tlsid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'tls_psk_identity' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128,
				'default' => ''
			],
			'tls_psk' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 512,
				'default' => ''
			]
		]
	],
	'module' => [
		'key' => 'moduleid',
		'fields' => [
			'moduleid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'id' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'relative_path' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'config' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			]
		]
	],
	'interface_snmp' => [
		'key' => 'interfaceid',
		'fields' => [
			'interfaceid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'interface',
				'ref_field' => 'interfaceid'
			],
			'version' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '2'
			],
			'bulk' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'community' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'securityname' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'securitylevel' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'authpassphrase' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'privpassphrase' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'authprotocol' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'privprotocol' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'contextname' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'lld_override' => [
		'key' => 'lld_overrideid',
		'fields' => [
			'lld_overrideid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'itemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'step' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'evaltype' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'formula' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'stop' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'lld_override_condition' => [
		'key' => 'lld_override_conditionid',
		'fields' => [
			'lld_override_conditionid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'lld_overrideid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'lld_override',
				'ref_field' => 'lld_overrideid'
			],
			'operator' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '8'
			],
			'macro' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'lld_override_operation' => [
		'key' => 'lld_override_operationid',
		'fields' => [
			'lld_override_operationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'lld_overrideid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'lld_override',
				'ref_field' => 'lld_overrideid'
			],
			'operationobject' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'operator' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'lld_override_opstatus' => [
		'key' => 'lld_override_operationid',
		'fields' => [
			'lld_override_operationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'lld_override_operation',
				'ref_field' => 'lld_override_operationid'
			],
			'status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'lld_override_opdiscover' => [
		'key' => 'lld_override_operationid',
		'fields' => [
			'lld_override_operationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'lld_override_operation',
				'ref_field' => 'lld_override_operationid'
			],
			'discover' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'lld_override_opperiod' => [
		'key' => 'lld_override_operationid',
		'fields' => [
			'lld_override_operationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'lld_override_operation',
				'ref_field' => 'lld_override_operationid'
			],
			'delay' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 1024,
				'default' => '0'
			]
		]
	],
	'lld_override_ophistory' => [
		'key' => 'lld_override_operationid',
		'fields' => [
			'lld_override_operationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'lld_override_operation',
				'ref_field' => 'lld_override_operationid'
			],
			'history' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => '90d'
			]
		]
	],
	'lld_override_optrends' => [
		'key' => 'lld_override_operationid',
		'fields' => [
			'lld_override_operationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'lld_override_operation',
				'ref_field' => 'lld_override_operationid'
			],
			'trends' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => '365d'
			]
		]
	],
	'lld_override_opseverity' => [
		'key' => 'lld_override_operationid',
		'fields' => [
			'lld_override_operationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'lld_override_operation',
				'ref_field' => 'lld_override_operationid'
			],
			'severity' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'lld_override_optag' => [
		'key' => 'lld_override_optagid',
		'fields' => [
			'lld_override_optagid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'lld_override_operationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'lld_override_operation',
				'ref_field' => 'lld_override_operationid'
			],
			'tag' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'lld_override_optemplate' => [
		'key' => 'lld_override_optemplateid',
		'fields' => [
			'lld_override_optemplateid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'lld_override_operationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'lld_override_operation',
				'ref_field' => 'lld_override_operationid'
			],
			'templateid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'hosts',
				'ref_field' => 'hostid'
			]
		]
	],
	'lld_override_opinventory' => [
		'key' => 'lld_override_operationid',
		'fields' => [
			'lld_override_operationid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'lld_override_operation',
				'ref_field' => 'lld_override_operationid'
			],
			'inventory_mode' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'trigger_queue' => [
		'key' => 'trigger_queueid',
		'fields' => [
			'trigger_queueid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'objectid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'clock' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'ns' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'item_parameter' => [
		'key' => 'item_parameterid',
		'fields' => [
			'item_parameterid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'itemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			]
		]
	],
	'role_rule' => [
		'key' => 'role_ruleid',
		'fields' => [
			'role_ruleid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'roleid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'role',
				'ref_field' => 'roleid'
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'value_int' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'value_str' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'value_moduleid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'module',
				'ref_field' => 'moduleid'
			],
			'value_serviceid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'services',
				'ref_field' => 'serviceid'
			]
		]
	],
	'token' => [
		'key' => 'tokenid',
		'fields' => [
			'tokenid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 64,
				'default' => ''
			],
			'description' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			],
			'userid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'users',
				'ref_field' => 'userid'
			],
			'token' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 128
			],
			'lastaccess' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'expires_at' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'created_at' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'creator_userid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'users',
				'ref_field' => 'userid'
			]
		]
	],
	'item_tag' => [
		'key' => 'itemtagid',
		'fields' => [
			'itemtagid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'itemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'items',
				'ref_field' => 'itemid'
			],
			'tag' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'httptest_tag' => [
		'key' => 'httptesttagid',
		'fields' => [
			'httptesttagid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'httptestid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'httptest',
				'ref_field' => 'httptestid'
			],
			'tag' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'sysmaps_element_tag' => [
		'key' => 'selementtagid',
		'fields' => [
			'selementtagid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'selementid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'sysmaps_elements',
				'ref_field' => 'selementid'
			],
			'tag' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'operator' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'report' => [
		'key' => 'reportid',
		'fields' => [
			'reportid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'userid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'users',
				'ref_field' => 'userid'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'description' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			],
			'status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'dashboardid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'dashboard',
				'ref_field' => 'dashboardid'
			],
			'period' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'cycle' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'weekdays' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'start_time' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'active_since' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'active_till' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'state' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'lastsent' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'info' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 2048,
				'default' => ''
			]
		]
	],
	'report_param' => [
		'key' => 'reportparamid',
		'fields' => [
			'reportparamid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'reportid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'report',
				'ref_field' => 'reportid'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			]
		]
	],
	'report_user' => [
		'key' => 'reportuserid',
		'fields' => [
			'reportuserid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'reportid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'report',
				'ref_field' => 'reportid'
			],
			'userid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'users',
				'ref_field' => 'userid'
			],
			'exclude' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'access_userid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'users',
				'ref_field' => 'userid'
			]
		]
	],
	'report_usrgrp' => [
		'key' => 'reportusrgrpid',
		'fields' => [
			'reportusrgrpid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'reportid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'report',
				'ref_field' => 'reportid'
			],
			'usrgrpid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'usrgrp',
				'ref_field' => 'usrgrpid'
			],
			'access_userid' => [
				'null' => true,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'users',
				'ref_field' => 'userid'
			]
		]
	],
	'service_problem_tag' => [
		'key' => 'service_problem_tagid',
		'fields' => [
			'service_problem_tagid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'serviceid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'services',
				'ref_field' => 'serviceid'
			],
			'tag' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'operator' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'service_problem' => [
		'key' => 'service_problemid',
		'fields' => [
			'service_problemid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'eventid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'problem',
				'ref_field' => 'eventid'
			],
			'serviceid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'services',
				'ref_field' => 'serviceid'
			],
			'severity' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'service_tag' => [
		'key' => 'servicetagid',
		'fields' => [
			'servicetagid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'serviceid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'services',
				'ref_field' => 'serviceid'
			],
			'tag' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'service_status_rule' => [
		'key' => 'service_status_ruleid',
		'fields' => [
			'service_status_ruleid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'serviceid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'services',
				'ref_field' => 'serviceid'
			],
			'type' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'limit_value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'limit_status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'new_status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'ha_node' => [
		'key' => 'ha_nodeid',
		'fields' => [
			'ha_nodeid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CUID,
				'length' => 25
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'address' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'port' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '10051'
			],
			'lastaccess' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'ha_sessionid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CUID,
				'length' => 25,
				'default' => ''
			]
		]
	],
	'sla' => [
		'key' => 'slaid',
		'fields' => [
			'slaid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'period' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'slo' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_FLOAT,
				'default' => '99.9'
			],
			'effective_date' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'timezone' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 50,
				'default' => 'UTC'
			],
			'status' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '1'
			],
			'description' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_TEXT,
				'default' => ''
			]
		]
	],
	'sla_schedule' => [
		'key' => 'sla_scheduleid',
		'fields' => [
			'sla_scheduleid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'slaid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'sla',
				'ref_field' => 'slaid'
			],
			'period_from' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'period_to' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'sla_excluded_downtime' => [
		'key' => 'sla_excluded_downtimeid',
		'fields' => [
			'sla_excluded_downtimeid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'slaid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'sla',
				'ref_field' => 'slaid'
			],
			'name' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'period_from' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'period_to' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	],
	'sla_service_tag' => [
		'key' => 'sla_service_tagid',
		'fields' => [
			'sla_service_tagid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'slaid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20,
				'ref_table' => 'sla',
				'ref_field' => 'slaid'
			],
			'tag' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			],
			'operator' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'value' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_CHAR,
				'length' => 255,
				'default' => ''
			]
		]
	],
	'dbversion' => [
		'key' => 'dbversionid',
		'fields' => [
			'dbversionid' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_ID,
				'length' => 20
			],
			'mandatory' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			],
			'optional' => [
				'null' => false,
				'type' => DB::FIELD_TYPE_INT,
				'length' => 10,
				'default' => '0'
			]
		]
	]
];
