/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "dbschema.h"
#include "common.h"

const ZBX_TABLE	tables[] = {

#if defined(HAVE_ORACLE)
#	define ZBX_TYPE_SHORTTEXT_LEN	2048
#else
#	define ZBX_TYPE_SHORTTEXT_LEN	65535
#endif

#define ZBX_TYPE_LONGTEXT_LEN	0
#define ZBX_TYPE_TEXT_LEN	65535

	{"role",	"roleid",	0,
		{
		{"roleid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"readonly",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		"name"
	},
	{"users",	"userid",	0,
		{
		{"userid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"username",	"",	NULL,	NULL,	100,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	100,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"surname",	"",	NULL,	NULL,	100,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"passwd",	"",	NULL,	NULL,	60,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"url",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"autologin",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"autologout",	"15m",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"lang",	"default",	NULL,	NULL,	7,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"refresh",	"30s",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"theme",	"default",	NULL,	NULL,	128,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"attempt_failed",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"attempt_ip",	"",	NULL,	NULL,	39,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"attempt_clock",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"rows_per_page",	"50",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"timezone",	"default",	NULL,	NULL,	50,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"roleid",	NULL,	"role",	"roleid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{0}
		},
		"username"
	},
	{"maintenances",	"maintenanceid",	0,
		{
		{"maintenanceid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	128,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"maintenance_type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"description",	"",	NULL,	NULL,	ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL,	0},
		{"active_since",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"active_till",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"tags_evaltype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		"name"
	},
	{"hosts",	"hostid",	0,
		{
		{"hostid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"proxy_hostid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	0,	0},
		{"host",	"",	NULL,	NULL,	128,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"lastaccess",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"ipmi_authtype",	"-1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"ipmi_privilege",	"2",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"ipmi_username",	"",	NULL,	NULL,	16,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"ipmi_password",	"",	NULL,	NULL,	20,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"maintenanceid",	NULL,	"maintenances",	"maintenanceid",	0,	ZBX_TYPE_ID,	0,	0},
		{"maintenance_status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"maintenance_type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"maintenance_from",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	128,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"flags",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"templateid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"description",	"",	NULL,	NULL,	ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL,	0},
		{"tls_connect",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"tls_accept",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"tls_issuer",	"",	NULL,	NULL,	1024,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"tls_subject",	"",	NULL,	NULL,	1024,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"tls_psk_identity",	"",	NULL,	NULL,	128,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"tls_psk",	"",	NULL,	NULL,	512,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"proxy_address",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"auto_compress",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"discover",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"custom_interfaces",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"uuid",	"",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"hstgrp",	"groupid",	0,
		{
		{"groupid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"internal",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"flags",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"uuid",	"",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"group_prototype",	"group_prototypeid",	0,
		{
		{"group_prototypeid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"hostid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"name",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"groupid",	NULL,	"hstgrp",	"groupid",	0,	ZBX_TYPE_ID,	0,	0},
		{"templateid",	NULL,	"group_prototype",	"group_prototypeid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{0}
		},
		NULL
	},
	{"group_discovery",	"groupid",	0,
		{
		{"groupid",	NULL,	"hstgrp",	"groupid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"parent_group_prototypeid",	NULL,	"group_prototype",	"group_prototypeid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"lastcheck",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"ts_delete",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"drules",	"druleid",	0,
		{
		{"druleid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"proxy_hostid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	0,	0},
		{"name",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"iprange",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"delay",	"1h",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"nextcheck",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		"name"
	},
	{"dchecks",	"dcheckid",	0,
		{
		{"dcheckid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"druleid",	NULL,	"drules",	"druleid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL | ZBX_PROXY,	ZBX_FK_CASCADE_DELETE},
		{"type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"key_",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"snmp_community",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"ports",	"0",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"snmpv3_securityname",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"snmpv3_securitylevel",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"snmpv3_authpassphrase",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"snmpv3_privpassphrase",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"uniq",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"snmpv3_authprotocol",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"snmpv3_privprotocol",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"snmpv3_contextname",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"host_source",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"name_source",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{0}
		},
		NULL
	},
	{"httptest",	"httptestid",	0,
		{
		{"httptestid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"nextcheck",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"delay",	"1m",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"agent",	"Zabbix",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"authentication",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"http_user",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"http_password",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"hostid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL | ZBX_PROXY,	ZBX_FK_CASCADE_DELETE},
		{"templateid",	NULL,	"httptest",	"httptestid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"http_proxy",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"retries",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"ssl_cert_file",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"ssl_key_file",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"ssl_key_password",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"verify_peer",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"verify_host",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"uuid",	"",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		"hostid,name"
	},
	{"httpstep",	"httpstepid",	0,
		{
		{"httpstepid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"httptestid",	NULL,	"httptest",	"httptestid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL | ZBX_PROXY,	ZBX_FK_CASCADE_DELETE},
		{"name",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"no",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"url",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"timeout",	"15s",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"posts",	"",	NULL,	NULL,	ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"required",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"status_codes",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"follow_redirects",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"retrieve_mode",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"post_type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{0}
		},
		NULL
	},
	{"interface",	"interfaceid",	0,
		{
		{"interfaceid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"hostid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL | ZBX_PROXY,	ZBX_FK_CASCADE_DELETE},
		{"main",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"type",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"useip",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"ip",	"127.0.0.1",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"dns",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"port",	"10050",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"available",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"error",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"errors_from",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"disable_until",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"valuemap",	"valuemapid",	0,
		{
		{"valuemapid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"hostid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"name",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"uuid",	"",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		"hostid,name"
	},
	{"items",	"itemid",	0,
		{
		{"itemid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"snmp_oid",	"",	NULL,	NULL,	512,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"hostid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL | ZBX_PROXY,	ZBX_FK_CASCADE_DELETE},
		{"name",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"key_",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"delay",	"0",	NULL,	NULL,	1024,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"history",	"90d",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"trends",	"365d",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"value_type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"trapper_hosts",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"units",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"formula",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"logtimefmt",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"templateid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"valuemapid",	NULL,	"valuemap",	"valuemapid",	0,	ZBX_TYPE_ID,	0,	0},
		{"params",	"",	NULL,	NULL,	ZBX_TYPE_TEXT_LEN,	ZBX_TYPE_TEXT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"ipmi_sensor",	"",	NULL,	NULL,	128,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"authtype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"username",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"password",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"publickey",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"privatekey",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"flags",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"interfaceid",	NULL,	"interface",	"interfaceid",	0,	ZBX_TYPE_ID,	ZBX_PROXY,	0},
		{"description",	"",	NULL,	NULL,	ZBX_TYPE_TEXT_LEN,	ZBX_TYPE_TEXT,	ZBX_NOTNULL,	0},
		{"inventory_link",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"lifetime",	"30d",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"evaltype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"jmx_endpoint",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"master_itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	ZBX_PROXY,	ZBX_FK_CASCADE_DELETE},
		{"timeout",	"3s",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"url",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"query_fields",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"posts",	"",	NULL,	NULL,	ZBX_TYPE_TEXT_LEN,	ZBX_TYPE_TEXT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"status_codes",	"200",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"follow_redirects",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"post_type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"http_proxy",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"headers",	"",	NULL,	NULL,	ZBX_TYPE_TEXT_LEN,	ZBX_TYPE_TEXT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"retrieve_mode",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"request_method",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"output_format",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"ssl_cert_file",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"ssl_key_file",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"ssl_key_password",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"verify_peer",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"verify_host",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"allow_traps",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"discover",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"uuid",	"",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"httpstepitem",	"httpstepitemid",	0,
		{
		{"httpstepitemid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"httpstepid",	NULL,	"httpstep",	"httpstepid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL | ZBX_PROXY,	ZBX_FK_CASCADE_DELETE},
		{"itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL | ZBX_PROXY,	ZBX_FK_CASCADE_DELETE},
		{"type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{0}
		},
		"httpstepid,itemid"
	},
	{"httptestitem",	"httptestitemid",	0,
		{
		{"httptestitemid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"httptestid",	NULL,	"httptest",	"httptestid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL | ZBX_PROXY,	ZBX_FK_CASCADE_DELETE},
		{"itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL | ZBX_PROXY,	ZBX_FK_CASCADE_DELETE},
		{"type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{0}
		},
		"httptestid,itemid"
	},
	{"media_type",	"mediatypeid",	0,
		{
		{"mediatypeid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	100,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"smtp_server",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"smtp_helo",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"smtp_email",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"exec_path",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"gsm_modem",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"username",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"passwd",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"smtp_port",	"25",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"smtp_security",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"smtp_verify_peer",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"smtp_verify_host",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"smtp_authentication",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"exec_params",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"maxsessions",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"maxattempts",	"3",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"attempt_interval",	"10s",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"content_type",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"script",	"",	NULL,	NULL,	ZBX_TYPE_TEXT_LEN,	ZBX_TYPE_TEXT,	ZBX_NOTNULL,	0},
		{"timeout",	"30s",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"process_tags",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"show_event_menu",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"event_menu_url",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"event_menu_name",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"description",	"",	NULL,	NULL,	ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL,	0},
		{0}
		},
		"name"
	},
	{"media_type_param",	"mediatype_paramid",	0,
		{
		{"mediatype_paramid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"mediatypeid",	NULL,	"media_type",	"mediatypeid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"name",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"media_type_message",	"mediatype_messageid",	0,
		{
		{"mediatype_messageid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"mediatypeid",	NULL,	"media_type",	"mediatypeid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"eventsource",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"recovery",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"subject",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"message",	"",	NULL,	NULL,	ZBX_TYPE_TEXT_LEN,	ZBX_TYPE_TEXT,	ZBX_NOTNULL,	0},
		{0}
		},
		"mediatypeid,eventsource,recovery"
	},
	{"usrgrp",	"usrgrpid",	0,
		{
		{"usrgrpid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"gui_access",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"users_status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"debug_mode",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"userdirectoryid",	"NULL",	"userdirectory",	"userdirectoryid",	0,	ZBX_TYPE_ID,	0 ,	0},
		{0}
		},
		"name"
	},
	{"users_groups",	"id",	0,
		{
		{"id",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"usrgrpid",	NULL,	"usrgrp",	"usrgrpid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"userid",	NULL,	"users",	"userid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{0}
		},
		"usrgrpid,userid"
	},
	{"scripts",	"scriptid",	0,
		{
		{"scriptid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"command",	"",	NULL,	NULL,	ZBX_TYPE_TEXT_LEN,	ZBX_TYPE_TEXT,	ZBX_NOTNULL,	0},
		{"host_access",	"2",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"usrgrpid",	NULL,	"usrgrp",	"usrgrpid",	0,	ZBX_TYPE_ID,	0,	0},
		{"groupid",	NULL,	"hstgrp",	"groupid",	0,	ZBX_TYPE_ID,	0,	0},
		{"description",	"",	NULL,	NULL,	ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL,	0},
		{"confirmation",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"type",	"5",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"execute_on",	"2",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"timeout",	"30s",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"scope",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"port",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"authtype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"username",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"password",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"publickey",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"privatekey",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"menu_path",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		"name"
	},
	{"script_param",	"script_paramid",	0,
		{
		{"script_paramid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"scriptid",	NULL,	"scripts",	"scriptid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"name",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		"scriptid,name"
	},
	{"actions",	"actionid",	0,
		{
		{"actionid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"eventsource",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"evaltype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"esc_period",	"1h",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"formula",	"",	NULL,	NULL,	1024,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"pause_suppressed",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"notify_if_canceled",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		"name"
	},
	{"operations",	"operationid",	0,
		{
		{"operationid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"actionid",	NULL,	"actions",	"actionid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"operationtype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"esc_period",	"0",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"esc_step_from",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"esc_step_to",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"evaltype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"recovery",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"opmessage",	"operationid",	0,
		{
		{"operationid",	NULL,	"operations",	"operationid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"default_msg",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"subject",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"message",	"",	NULL,	NULL,	ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL,	0},
		{"mediatypeid",	NULL,	"media_type",	"mediatypeid",	0,	ZBX_TYPE_ID,	0,	0},
		{0}
		},
		NULL
	},
	{"opmessage_grp",	"opmessage_grpid",	0,
		{
		{"opmessage_grpid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"operationid",	NULL,	"operations",	"operationid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"usrgrpid",	NULL,	"usrgrp",	"usrgrpid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{0}
		},
		"operationid,usrgrpid"
	},
	{"opmessage_usr",	"opmessage_usrid",	0,
		{
		{"opmessage_usrid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"operationid",	NULL,	"operations",	"operationid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"userid",	NULL,	"users",	"userid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{0}
		},
		"operationid,userid"
	},
	{"opcommand",	"operationid",	0,
		{
		{"operationid",	NULL,	"operations",	"operationid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"scriptid",	NULL,	"scripts",	"scriptid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"opcommand_hst",	"opcommand_hstid",	0,
		{
		{"opcommand_hstid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"operationid",	NULL,	"operations",	"operationid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"hostid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	0,	0},
		{0}
		},
		NULL
	},
	{"opcommand_grp",	"opcommand_grpid",	0,
		{
		{"opcommand_grpid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"operationid",	NULL,	"operations",	"operationid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"groupid",	NULL,	"hstgrp",	"groupid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"opgroup",	"opgroupid",	0,
		{
		{"opgroupid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"operationid",	NULL,	"operations",	"operationid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"groupid",	NULL,	"hstgrp",	"groupid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{0}
		},
		"operationid,groupid"
	},
	{"optemplate",	"optemplateid",	0,
		{
		{"optemplateid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"operationid",	NULL,	"operations",	"operationid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"templateid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{0}
		},
		"operationid,templateid"
	},
	{"opconditions",	"opconditionid",	0,
		{
		{"opconditionid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"operationid",	NULL,	"operations",	"operationid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"conditiontype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"operator",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"conditions",	"conditionid",	0,
		{
		{"conditionid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"actionid",	NULL,	"actions",	"actionid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"conditiontype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"operator",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"value2",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"config",	"configid",	0,
		{
		{"configid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"work_period",	"1-5,09:00-18:00",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"alert_usrgrpid",	NULL,	"usrgrp",	"usrgrpid",	0,	ZBX_TYPE_ID,	0,	0},
		{"default_theme",	"blue-theme",	NULL,	NULL,	128,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"authentication_type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"discovery_groupid",	NULL,	"hstgrp",	"groupid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"max_in_table",	"50",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"search_limit",	"1000",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"severity_color_0",	"97AAB3",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"severity_color_1",	"7499FF",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"severity_color_2",	"FFC859",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"severity_color_3",	"FFA059",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"severity_color_4",	"E97659",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"severity_color_5",	"E45959",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"severity_name_0",	"Not classified",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"severity_name_1",	"Information",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"severity_name_2",	"Warning",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"severity_name_3",	"Average",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"severity_name_4",	"High",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"severity_name_5",	"Disaster",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"ok_period",	"5m",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"blink_period",	"2m",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"problem_unack_color",	"CC0000",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"problem_ack_color",	"CC0000",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"ok_unack_color",	"009900",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"ok_ack_color",	"009900",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"problem_unack_style",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"problem_ack_style",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"ok_unack_style",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"ok_ack_style",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"snmptrap_logging",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"server_check_interval",	"10",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"hk_events_mode",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"hk_events_trigger",	"365d",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"hk_events_internal",	"1d",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"hk_events_discovery",	"1d",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"hk_events_autoreg",	"1d",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"hk_services_mode",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"hk_services",	"365d",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"hk_audit_mode",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"hk_audit",	"365d",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"hk_sessions_mode",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"hk_sessions",	"365d",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"hk_history_mode",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"hk_history_global",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"hk_history",	"90d",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"hk_trends_mode",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"hk_trends_global",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"hk_trends",	"365d",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"default_inventory_mode",	"-1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"custom_color",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"http_auth_enabled",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"http_login_form",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"http_strip_domains",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"http_case_sensitive",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"ldap_configured",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"ldap_case_sensitive",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"db_extension",	"",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"autoreg_tls_accept",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"compression_status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"compress_older",	"7d",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"instanceid",	"",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"saml_auth_enabled",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"saml_idp_entityid",	"",	NULL,	NULL,	1024,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"saml_sso_url",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"saml_slo_url",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"saml_username_attribute",	"",	NULL,	NULL,	128,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"saml_sp_entityid",	"",	NULL,	NULL,	1024,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"saml_nameid_format",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"saml_sign_messages",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"saml_sign_assertions",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"saml_sign_authn_requests",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"saml_sign_logout_requests",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"saml_sign_logout_responses",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"saml_encrypt_nameid",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"saml_encrypt_assertions",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"saml_case_sensitive",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"default_lang",	"en_US",	NULL,	NULL,	5,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"default_timezone",	"system",	NULL,	NULL,	50,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"login_attempts",	"5",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"login_block",	"30s",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"show_technical_errors",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"validate_uri_schemes",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"uri_valid_schemes",	"http,https,ftp,file,mailto,tel,ssh",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"x_frame_options",	"SAMEORIGIN",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"iframe_sandboxing_enabled",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"iframe_sandboxing_exceptions",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"max_overview_table_size",	"50",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"history_period",	"24h",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"period_default",	"1h",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"max_period",	"2y",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"socket_timeout",	"3s",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"connect_timeout",	"3s",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"media_type_test_timeout",	"65s",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"script_timeout",	"60s",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"item_test_timeout",	"60s",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"session_key",	"",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"url",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"report_test_timeout",	"60s",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"dbversion_status",	"",	NULL,	NULL,	ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL,	0},
		{"hk_events_service",	"1d",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"passwd_min_length",	"8",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"passwd_check_rules",	"8",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"auditlog_enabled",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"ha_failover_delay",	"1m",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"geomaps_tile_provider",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"geomaps_tile_url",	"",	NULL,	NULL,	1024,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"geomaps_max_zoom",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"geomaps_attribution",	"",	NULL,	NULL,	1024,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"vault_provider",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"ldap_userdirectoryid",	"NULL ",	"userdirectory",	"userdirectoryid",	0,	ZBX_TYPE_ID,	0,	0},
		{0}
		},
		NULL
	},
	{"triggers",	"triggerid",	0,
		{
		{"triggerid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"expression",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"description",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"url",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"value",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"priority",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"lastchange",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"comments",	"",	NULL,	NULL,	ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL,	0},
		{"error",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"templateid",	NULL,	"triggers",	"triggerid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"state",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"flags",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"recovery_mode",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"recovery_expression",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"correlation_mode",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"correlation_tag",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"manual_close",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"opdata",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"discover",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"event_name",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"uuid",	"",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"trigger_depends",	"triggerdepid",	0,
		{
		{"triggerdepid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"triggerid_down",	NULL,	"triggers",	"triggerid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"triggerid_up",	NULL,	"triggers",	"triggerid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{0}
		},
		"triggerid_down,triggerid_up"
	},
	{"functions",	"functionid",	0,
		{
		{"functionid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"triggerid",	NULL,	"triggers",	"triggerid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"name",	"",	NULL,	NULL,	12,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"parameter",	"0",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"graphs",	"graphid",	0,
		{
		{"graphid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	128,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"width",	"900",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"height",	"200",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"yaxismin",	"0",	NULL,	NULL,	0,	ZBX_TYPE_FLOAT,	ZBX_NOTNULL,	0},
		{"yaxismax",	"100",	NULL,	NULL,	0,	ZBX_TYPE_FLOAT,	ZBX_NOTNULL,	0},
		{"templateid",	NULL,	"graphs",	"graphid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"show_work_period",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"show_triggers",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"graphtype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"show_legend",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"show_3d",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"percent_left",	"0",	NULL,	NULL,	0,	ZBX_TYPE_FLOAT,	ZBX_NOTNULL,	0},
		{"percent_right",	"0",	NULL,	NULL,	0,	ZBX_TYPE_FLOAT,	ZBX_NOTNULL,	0},
		{"ymin_type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"ymax_type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"ymin_itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	0,	0},
		{"ymax_itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	0,	0},
		{"flags",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"discover",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"uuid",	"",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"graphs_items",	"gitemid",	0,
		{
		{"gitemid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"graphid",	NULL,	"graphs",	"graphid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"drawtype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"sortorder",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"color",	"009600",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"yaxisside",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"calc_fnc",	"2",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"graph_theme",	"graphthemeid",	0,
		{
		{"graphthemeid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"theme",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"backgroundcolor",	"",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"graphcolor",	"",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"gridcolor",	"",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"maingridcolor",	"",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"gridbordercolor",	"",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"textcolor",	"",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"highlightcolor",	"",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"leftpercentilecolor",	"",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"rightpercentilecolor",	"",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"nonworktimecolor",	"",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"colorpalette",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		"theme"
	},
	{"globalmacro",	"globalmacroid",	0,
		{
		{"globalmacroid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"macro",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"value",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"description",	"",	NULL,	NULL,	ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL,	0},
		{"type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{0}
		},
		"macro"
	},
	{"hostmacro",	"hostmacroid",	0,
		{
		{"hostmacroid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"hostid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL | ZBX_PROXY,	ZBX_FK_CASCADE_DELETE},
		{"macro",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"value",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"description",	"",	NULL,	NULL,	ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL,	0},
		{"type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{0}
		},
		"hostid,macro"
	},
	{"hosts_groups",	"hostgroupid",	0,
		{
		{"hostgroupid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"hostid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"groupid",	NULL,	"hstgrp",	"groupid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{0}
		},
		"hostid,groupid"
	},
	{"hosts_templates",	"hosttemplateid",	0,
		{
		{"hosttemplateid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"hostid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL | ZBX_PROXY,	ZBX_FK_CASCADE_DELETE},
		{"templateid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL | ZBX_PROXY,	ZBX_FK_CASCADE_DELETE},
		{"link_type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{0}
		},
		"hostid,templateid"
	},
	{"valuemap_mapping",	"valuemap_mappingid",	0,
		{
		{"valuemap_mappingid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"valuemapid",	NULL,	"valuemap",	"valuemapid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"value",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"newvalue",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"sortorder",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		"valuemapid,value,type"
	},
	{"media",	"mediaid",	0,
		{
		{"mediaid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"userid",	NULL,	"users",	"userid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"mediatypeid",	NULL,	"media_type",	"mediatypeid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"sendto",	"",	NULL,	NULL,	1024,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"active",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"severity",	"63",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"period",	"1-7,00:00-24:00",	NULL,	NULL,	1024,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"rights",	"rightid",	0,
		{
		{"rightid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"groupid",	NULL,	"usrgrp",	"usrgrpid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"permission",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"id",	NULL,	"hstgrp",	"groupid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{0}
		},
		NULL
	},
	{"services",	"serviceid",	0,
		{
		{"serviceid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	128,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"status",	"-1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"algorithm",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"sortorder",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"weight",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"propagation_rule",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"propagation_value",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"description",	"",	NULL,	NULL,	ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL,	0},
		{"uuid",	"",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"created_at",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"services_links",	"linkid",	0,
		{
		{"linkid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"serviceupid",	NULL,	"services",	"serviceid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"servicedownid",	NULL,	"services",	"serviceid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{0}
		},
		"serviceupid,servicedownid"
	},
	{"icon_map",	"iconmapid",	0,
		{
		{"iconmapid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"default_iconid",	NULL,	"images",	"imageid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{0}
		},
		"name"
	},
	{"icon_mapping",	"iconmappingid",	0,
		{
		{"iconmappingid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"iconmapid",	NULL,	"icon_map",	"iconmapid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"iconid",	NULL,	"images",	"imageid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"inventory_link",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"expression",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"sortorder",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"sysmaps",	"sysmapid",	0,
		{
		{"sysmapid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	128,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"width",	"600",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"height",	"400",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"backgroundid",	NULL,	"images",	"imageid",	0,	ZBX_TYPE_ID,	0,	0},
		{"label_type",	"2",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"label_location",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"highlight",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"expandproblem",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"markelements",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"show_unack",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"grid_size",	"50",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"grid_show",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"grid_align",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"label_format",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"label_type_host",	"2",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"label_type_hostgroup",	"2",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"label_type_trigger",	"2",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"label_type_map",	"2",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"label_type_image",	"2",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"label_string_host",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"label_string_hostgroup",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"label_string_trigger",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"label_string_map",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"label_string_image",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"iconmapid",	NULL,	"icon_map",	"iconmapid",	0,	ZBX_TYPE_ID,	0,	0},
		{"expand_macros",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"severity_min",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"userid",	NULL,	"users",	"userid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"private",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"show_suppressed",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		"name"
	},
	{"sysmaps_elements",	"selementid",	0,
		{
		{"selementid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"sysmapid",	NULL,	"sysmaps",	"sysmapid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"elementid",	"0",	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"elementtype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"iconid_off",	NULL,	"images",	"imageid",	0,	ZBX_TYPE_ID,	0,	0},
		{"iconid_on",	NULL,	"images",	"imageid",	0,	ZBX_TYPE_ID,	0,	0},
		{"label",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"label_location",	"-1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"x",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"y",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"iconid_disabled",	NULL,	"images",	"imageid",	0,	ZBX_TYPE_ID,	0,	0},
		{"iconid_maintenance",	NULL,	"images",	"imageid",	0,	ZBX_TYPE_ID,	0,	0},
		{"elementsubtype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"areatype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"width",	"200",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"height",	"200",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"viewtype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"use_iconmap",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"evaltype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"sysmaps_links",	"linkid",	0,
		{
		{"linkid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"sysmapid",	NULL,	"sysmaps",	"sysmapid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"selementid1",	NULL,	"sysmaps_elements",	"selementid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"selementid2",	NULL,	"sysmaps_elements",	"selementid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"drawtype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"color",	"000000",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"label",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"sysmaps_link_triggers",	"linktriggerid",	0,
		{
		{"linktriggerid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"linkid",	NULL,	"sysmaps_links",	"linkid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"triggerid",	NULL,	"triggers",	"triggerid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"drawtype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"color",	"000000",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		"linkid,triggerid"
	},
	{"sysmap_element_url",	"sysmapelementurlid",	0,
		{
		{"sysmapelementurlid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"selementid",	NULL,	"sysmaps_elements",	"selementid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"name",	NULL,	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"url",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		"selementid,name"
	},
	{"sysmap_url",	"sysmapurlid",	0,
		{
		{"sysmapurlid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"sysmapid",	NULL,	"sysmaps",	"sysmapid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"name",	NULL,	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"url",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"elementtype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		"sysmapid,name"
	},
	{"sysmap_user",	"sysmapuserid",	0,
		{
		{"sysmapuserid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"sysmapid",	NULL,	"sysmaps",	"sysmapid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"userid",	NULL,	"users",	"userid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"permission",	"2",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		"sysmapid,userid"
	},
	{"sysmap_usrgrp",	"sysmapusrgrpid",	0,
		{
		{"sysmapusrgrpid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"sysmapid",	NULL,	"sysmaps",	"sysmapid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"usrgrpid",	NULL,	"usrgrp",	"usrgrpid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"permission",	"2",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		"sysmapid,usrgrpid"
	},
	{"maintenances_hosts",	"maintenance_hostid",	0,
		{
		{"maintenance_hostid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"maintenanceid",	NULL,	"maintenances",	"maintenanceid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"hostid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{0}
		},
		"maintenanceid,hostid"
	},
	{"maintenances_groups",	"maintenance_groupid",	0,
		{
		{"maintenance_groupid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"maintenanceid",	NULL,	"maintenances",	"maintenanceid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"groupid",	NULL,	"hstgrp",	"groupid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{0}
		},
		"maintenanceid,groupid"
	},
	{"timeperiods",	"timeperiodid",	0,
		{
		{"timeperiodid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"timeperiod_type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"every",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"month",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"dayofweek",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"day",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"start_time",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"period",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"start_date",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"maintenances_windows",	"maintenance_timeperiodid",	0,
		{
		{"maintenance_timeperiodid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"maintenanceid",	NULL,	"maintenances",	"maintenanceid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"timeperiodid",	NULL,	"timeperiods",	"timeperiodid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{0}
		},
		"maintenanceid,timeperiodid"
	},
	{"regexps",	"regexpid",	0,
		{
		{"regexpid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	128,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"test_string",	"",	NULL,	NULL,	ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL,	0},
		{0}
		},
		"name"
	},
	{"expressions",	"expressionid",	0,
		{
		{"expressionid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"regexpid",	NULL,	"regexps",	"regexpid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL | ZBX_PROXY,	ZBX_FK_CASCADE_DELETE},
		{"expression",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"expression_type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"exp_delimiter",	"",	NULL,	NULL,	1,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"case_sensitive",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{0}
		},
		NULL
	},
	{"ids",	"table_name,field_name",	0,
		{
		{"table_name",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"field_name",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"nextid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"alerts",	"alertid",	0,
		{
		{"alertid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"actionid",	NULL,	"actions",	"actionid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"eventid",	NULL,	"events",	"eventid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"userid",	NULL,	"users",	"userid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"clock",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"mediatypeid",	NULL,	"media_type",	"mediatypeid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"sendto",	"",	NULL,	NULL,	1024,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"subject",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"message",	"",	NULL,	NULL,	ZBX_TYPE_TEXT_LEN,	ZBX_TYPE_TEXT,	ZBX_NOTNULL,	0},
		{"status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"retries",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"error",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"esc_step",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"alerttype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"p_eventid",	NULL,	"events",	"eventid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"acknowledgeid",	NULL,	"acknowledges",	"acknowledgeid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"parameters",	"{}",	NULL,	NULL,	ZBX_TYPE_TEXT_LEN,	ZBX_TYPE_TEXT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"history",	"itemid,clock,ns",	0,
		{
		{"itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"clock",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"value",	"0.0000",	NULL,	NULL,	0,	ZBX_TYPE_FLOAT,	ZBX_NOTNULL,	0},
		{"ns",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"history_uint",	"itemid,clock,ns",	0,
		{
		{"itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"clock",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"value",	"0",	NULL,	NULL,	0,	ZBX_TYPE_UINT,	ZBX_NOTNULL,	0},
		{"ns",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"history_str",	"itemid,clock,ns",	0,
		{
		{"itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"clock",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"ns",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"history_log",	"itemid,clock,ns",	0,
		{
		{"itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"clock",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"timestamp",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"source",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"severity",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	ZBX_TYPE_TEXT_LEN,	ZBX_TYPE_TEXT,	ZBX_NOTNULL,	0},
		{"logeventid",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"ns",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"history_text",	"itemid,clock,ns",	0,
		{
		{"itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"clock",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	ZBX_TYPE_TEXT_LEN,	ZBX_TYPE_TEXT,	ZBX_NOTNULL,	0},
		{"ns",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"proxy_history",	"id",	0,
		{
		{"id",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_UINT,	ZBX_NOTNULL,	0},
		{"itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"clock",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"timestamp",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"source",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"severity",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	ZBX_TYPE_LONGTEXT_LEN,	ZBX_TYPE_LONGTEXT,	ZBX_NOTNULL,	0},
		{"logeventid",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"ns",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"state",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"lastlogsize",	"0",	NULL,	NULL,	0,	ZBX_TYPE_UINT,	ZBX_NOTNULL,	0},
		{"mtime",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"flags",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"write_clock",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"proxy_dhistory",	"id",	0,
		{
		{"id",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_UINT,	ZBX_NOTNULL,	0},
		{"clock",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"druleid",	NULL,	"drules",	"druleid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"ip",	"",	NULL,	NULL,	39,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"port",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"dcheckid",	NULL,	"dchecks",	"dcheckid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"dns",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"events",	"eventid",	0,
		{
		{"eventid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"source",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"object",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"objectid",	"0",	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"clock",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"value",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"acknowledged",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"ns",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"severity",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"trends",	"itemid,clock",	0,
		{
		{"itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"clock",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"num",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"value_min",	"0.0000",	NULL,	NULL,	0,	ZBX_TYPE_FLOAT,	ZBX_NOTNULL,	0},
		{"value_avg",	"0.0000",	NULL,	NULL,	0,	ZBX_TYPE_FLOAT,	ZBX_NOTNULL,	0},
		{"value_max",	"0.0000",	NULL,	NULL,	0,	ZBX_TYPE_FLOAT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"trends_uint",	"itemid,clock",	0,
		{
		{"itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"clock",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"num",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"value_min",	"0",	NULL,	NULL,	0,	ZBX_TYPE_UINT,	ZBX_NOTNULL,	0},
		{"value_avg",	"0",	NULL,	NULL,	0,	ZBX_TYPE_UINT,	ZBX_NOTNULL,	0},
		{"value_max",	"0",	NULL,	NULL,	0,	ZBX_TYPE_UINT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"acknowledges",	"acknowledgeid",	0,
		{
		{"acknowledgeid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"userid",	NULL,	"users",	"userid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"eventid",	NULL,	"events",	"eventid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"clock",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"message",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"action",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"old_severity",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"new_severity",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"auditlog",	"auditid",	0,
		{
		{"auditid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_CUID,	ZBX_NOTNULL,	0},
		{"userid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	0,	0},
		{"username",	"",	NULL,	NULL,	100,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"clock",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"ip",	"",	NULL,	NULL,	39,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"action",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"resourcetype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"resourceid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	0,	0},
		{"resource_cuid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_CUID,	0,	0},
		{"resourcename",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"recordsetid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_CUID,	ZBX_NOTNULL,	0},
		{"details",	"",	NULL,	NULL,	ZBX_TYPE_LONGTEXT_LEN,	ZBX_TYPE_LONGTEXT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"service_alarms",	"servicealarmid",	0,
		{
		{"servicealarmid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"serviceid",	NULL,	"services",	"serviceid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"clock",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"value",	"-1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"autoreg_host",	"autoreg_hostid",	0,
		{
		{"autoreg_hostid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"proxy_hostid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"host",	"",	NULL,	NULL,	128,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"listen_ip",	"",	NULL,	NULL,	39,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"listen_port",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"listen_dns",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"host_metadata",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"flags",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"tls_accepted",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"proxy_autoreg_host",	"id",	0,
		{
		{"id",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_UINT,	ZBX_NOTNULL,	0},
		{"clock",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"host",	"",	NULL,	NULL,	128,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"listen_ip",	"",	NULL,	NULL,	39,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"listen_port",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"listen_dns",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"host_metadata",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"flags",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"tls_accepted",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"dhosts",	"dhostid",	0,
		{
		{"dhostid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"druleid",	NULL,	"drules",	"druleid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"lastup",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"lastdown",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"dservices",	"dserviceid",	0,
		{
		{"dserviceid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"dhostid",	NULL,	"dhosts",	"dhostid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"value",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"port",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"lastup",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"lastdown",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"dcheckid",	NULL,	"dchecks",	"dcheckid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"ip",	"",	NULL,	NULL,	39,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"dns",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		"dcheckid,ip,port"
	},
	{"escalations",	"escalationid",	0,
		{
		{"escalationid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"actionid",	NULL,	"actions",	"actionid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"triggerid",	NULL,	"triggers",	"triggerid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"eventid",	NULL,	"events",	"eventid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"r_eventid",	NULL,	"events",	"eventid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"nextcheck",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"esc_step",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"acknowledgeid",	NULL,	"acknowledges",	"acknowledgeid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"servicealarmid",	NULL,	"service_alarms",	"servicealarmid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"serviceid",	NULL,	"services",	"serviceid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{0}
		},
		"triggerid,itemid,serviceid,escalationid"
	},
	{"globalvars",	"globalvarid",	0,
		{
		{"globalvarid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"snmp_lastsize",	"0",	NULL,	NULL,	0,	ZBX_TYPE_UINT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"graph_discovery",	"graphid",	0,
		{
		{"graphid",	NULL,	"graphs",	"graphid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"parent_graphid",	NULL,	"graphs",	"graphid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"lastcheck",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"ts_delete",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"host_inventory",	"hostid",	0,
		{
		{"hostid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"inventory_mode",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"type",	"",	NULL,	NULL,	64,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"type_full",	"",	NULL,	NULL,	64,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"name",	"",	NULL,	NULL,	128,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"alias",	"",	NULL,	NULL,	128,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"os",	"",	NULL,	NULL,	128,			ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"os_full",	"",	NULL,	NULL,	255,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"os_short",	"",	NULL,	NULL,	128,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"serialno_a",	"",	NULL,	NULL,	64,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"serialno_b",	"",	NULL,	NULL,	64,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"tag",	"",	NULL,	NULL,	64,			ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"asset_tag",	"",	NULL,	NULL,	64,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"macaddress_a",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"macaddress_b",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"hardware",	"",	NULL,	NULL,	255,		ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"hardware_full",	"",	NULL,	NULL,		ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"software",	"",	NULL,	NULL,	255,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"software_full",	"",	NULL,	NULL,		ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"software_app_a",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"software_app_b",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"software_app_c",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"software_app_d",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"software_app_e",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"contact",	"",	NULL,	NULL,			ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"location",	"",	NULL,	NULL,			ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"location_lat",	"",	NULL,	NULL,	16,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"location_lon",	"",	NULL,	NULL,	16,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"notes",	"",	NULL,	NULL,			ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"chassis",	"",	NULL,	NULL,	64,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"model",	"",	NULL,	NULL,	64,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"hw_arch",	"",	NULL,	NULL,	32,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"vendor",	"",	NULL,	NULL,	64,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"contract_number",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"installer_name",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"deployment_status",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"url_a",	"",	NULL,	NULL,	255,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"url_b",	"",	NULL,	NULL,	255,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"url_c",	"",	NULL,	NULL,	255,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"host_networks",	"",	NULL,	NULL,		ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL,	0},
		{"host_netmask",	"",	NULL,	NULL,	39,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"host_router",	"",	NULL,	NULL,	39,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"oob_ip",	"",	NULL,	NULL,	39,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"oob_netmask",	"",	NULL,	NULL,	39,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"oob_router",	"",	NULL,	NULL,	39,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"date_hw_purchase",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"date_hw_install",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"date_hw_expiry",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"date_hw_decomm",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"site_address_a",	"",	NULL,	NULL,	128,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"site_address_b",	"",	NULL,	NULL,	128,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"site_address_c",	"",	NULL,	NULL,	128,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"site_city",	"",	NULL,	NULL,	128,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"site_state",	"",	NULL,	NULL,	64,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"site_country",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"site_zip",	"",	NULL,	NULL,	64,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"site_rack",	"",	NULL,	NULL,	128,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"site_notes",	"",	NULL,	NULL,			ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"poc_1_name",	"",	NULL,	NULL,	128,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"poc_1_email",	"",	NULL,	NULL,	128,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"poc_1_phone_a",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"poc_1_phone_b",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"poc_1_cell",	"",	NULL,	NULL,	64,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"poc_1_screen",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"poc_1_notes",	"",	NULL,	NULL,			ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL,	0},
		{"poc_2_name",	"",	NULL,	NULL,	128,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"poc_2_email",	"",	NULL,	NULL,	128,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"poc_2_phone_a",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"poc_2_phone_b",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"poc_2_cell",	"",	NULL,	NULL,	64,		ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"poc_2_screen",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"poc_2_notes",	"",	NULL,	NULL,			ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{0}
		},
		NULL
	},
	{"housekeeper",	"housekeeperid",	0,
		{
		{"housekeeperid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"tablename",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"field",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"value",	NULL,	"items",	"value",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{0}
		},
		NULL
	},
	{"images",	"imageid",	0,
		{
		{"imageid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"imagetype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"name",	"0",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"image",	"",	NULL,	NULL,	0,	ZBX_TYPE_BLOB,	ZBX_NOTNULL,	0},
		{0}
		},
		"name"
	},
	{"item_discovery",	"itemdiscoveryid",	0,
		{
		{"itemdiscoveryid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"parent_itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"key_",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"lastcheck",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"ts_delete",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		"itemid,parent_itemid"
	},
	{"host_discovery",	"hostid",	0,
		{
		{"hostid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"parent_hostid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	0,	0},
		{"parent_itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	0,	0},
		{"host",	"",	NULL,	NULL,	128,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"lastcheck",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"ts_delete",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"interface_discovery",	"interfaceid",	0,
		{
		{"interfaceid",	NULL,	"interface",	"interfaceid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"parent_interfaceid",	NULL,	"interface",	"interfaceid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{0}
		},
		NULL
	},
	{"profiles",	"profileid",	0,
		{
		{"profileid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"userid",	NULL,	"users",	"userid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"idx",	"",	NULL,	NULL,	96,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"idx2",	"0",	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"value_id",	"0",	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"value_int",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"value_str",	"",	NULL,	NULL,	ZBX_TYPE_TEXT_LEN,	ZBX_TYPE_TEXT,	ZBX_NOTNULL,	0},
		{"source",	"",	NULL,	NULL,	96,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"sessions",	"sessionid",	0,
		{
		{"sessionid",	"",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"userid",	NULL,	"users",	"userid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"lastaccess",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"trigger_discovery",	"triggerid",	0,
		{
		{"triggerid",	NULL,	"triggers",	"triggerid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"parent_triggerid",	NULL,	"triggers",	"triggerid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"lastcheck",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"ts_delete",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"item_condition",	"item_conditionid",	0,
		{
		{"item_conditionid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"operator",	"8",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"macro",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"item_rtdata",	"itemid",	0,
		{
		{"itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"lastlogsize",	"0",	NULL,	NULL,	0,	ZBX_TYPE_UINT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"state",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"mtime",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"error",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"opinventory",	"operationid",	0,
		{
		{"operationid",	NULL,	"operations",	"operationid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"inventory_mode",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"trigger_tag",	"triggertagid",	0,
		{
		{"triggertagid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"triggerid",	NULL,	"triggers",	"triggerid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"tag",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"event_tag",	"eventtagid",	0,
		{
		{"eventtagid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"eventid",	NULL,	"events",	"eventid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"tag",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"problem",	"eventid",	0,
		{
		{"eventid",	NULL,	"events",	"eventid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"source",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"object",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"objectid",	"0",	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"clock",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"ns",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"r_eventid",	NULL,	"events",	"eventid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"r_clock",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"r_ns",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"correlationid",	NULL,	"correlation",	"correlationid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"userid",	NULL,	"users",	"userid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"name",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"acknowledged",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"severity",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"problem_tag",	"problemtagid",	0,
		{
		{"problemtagid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"eventid",	NULL,	"problem",	"eventid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"tag",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"tag_filter",	"tag_filterid",	0,
		{
		{"tag_filterid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"usrgrpid",	NULL,	"usrgrp",	"usrgrpid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL | 0 ,	ZBX_FK_CASCADE_DELETE},
		{"groupid",	NULL,	"hstgrp",	"groupid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"tag",	" ",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"value",	" ",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"event_recovery",	"eventid",	0,
		{
		{"eventid",	NULL,	"events",	"eventid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"r_eventid",	NULL,	"events",	"eventid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"c_eventid",	NULL,	"events",	"eventid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"correlationid",	NULL,	"correlation",	"correlationid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"userid",	NULL,	"users",	"userid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{0}
		},
		NULL
	},
	{"correlation",	"correlationid",	0,
		{
		{"correlationid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"description",	"",	NULL,	NULL,	ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL,	0},
		{"evaltype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"formula",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		"name"
	},
	{"corr_condition",	"corr_conditionid",	0,
		{
		{"corr_conditionid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"correlationid",	NULL,	"correlation",	"correlationid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"corr_condition_tag",	"corr_conditionid",	0,
		{
		{"corr_conditionid",	NULL,	"corr_condition",	"corr_conditionid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"tag",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"corr_condition_group",	"corr_conditionid",	0,
		{
		{"corr_conditionid",	NULL,	"corr_condition",	"corr_conditionid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"operator",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"groupid",	NULL,	"hstgrp",	"groupid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"corr_condition_tagpair",	"corr_conditionid",	0,
		{
		{"corr_conditionid",	NULL,	"corr_condition",	"corr_conditionid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"oldtag",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"newtag",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"corr_condition_tagvalue",	"corr_conditionid",	0,
		{
		{"corr_conditionid",	NULL,	"corr_condition",	"corr_conditionid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"tag",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"operator",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"corr_operation",	"corr_operationid",	0,
		{
		{"corr_operationid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"correlationid",	NULL,	"correlation",	"correlationid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"task",	"taskid",	0,
		{
		{"taskid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"type",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"clock",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"ttl",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"proxy_hostid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{0}
		},
		NULL
	},
	{"task_close_problem",	"taskid",	0,
		{
		{"taskid",	NULL,	"task",	"taskid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"acknowledgeid",	NULL,	"acknowledges",	"acknowledgeid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{0}
		},
		NULL
	},
	{"item_preproc",	"item_preprocid",	0,
		{
		{"item_preprocid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL | ZBX_PROXY,	ZBX_FK_CASCADE_DELETE},
		{"step",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"params",	"",	NULL,	NULL,	ZBX_TYPE_TEXT_LEN,	ZBX_TYPE_TEXT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"error_handler",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"error_handler_params",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{0}
		},
		NULL
	},
	{"task_remote_command",	"taskid",	0,
		{
		{"taskid",	NULL,	"task",	"taskid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"command_type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"execute_on",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"port",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"authtype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"username",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"password",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"publickey",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"privatekey",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"command",	"",	NULL,	NULL,	ZBX_TYPE_TEXT_LEN,	ZBX_TYPE_TEXT,	ZBX_NOTNULL,	0},
		{"alertid",	NULL,	"alerts",	"alertid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"parent_taskid",	NULL,	"task",	"taskid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"hostid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{0}
		},
		NULL
	},
	{"task_remote_command_result",	"taskid",	0,
		{
		{"taskid",	NULL,	"task",	"taskid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"parent_taskid",	NULL,	"task",	"taskid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"info",	"",	NULL,	NULL,	ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"task_data",	"taskid",	0,
		{
		{"taskid",	NULL,	"task",	"taskid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"data",	"",	NULL,	NULL,	ZBX_TYPE_TEXT_LEN,	ZBX_TYPE_TEXT,	ZBX_NOTNULL,	0},
		{"parent_taskid",	NULL,	"task",	"taskid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{0}
		},
		NULL
	},
	{"task_result",	"taskid",	0,
		{
		{"taskid",	NULL,	"task",	"taskid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"parent_taskid",	NULL,	"task",	"taskid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"info",	"",	NULL,	NULL,	ZBX_TYPE_TEXT_LEN,	ZBX_TYPE_TEXT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"task_acknowledge",	"taskid",	0,
		{
		{"taskid",	NULL,	"task",	"taskid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"acknowledgeid",	NULL,	"acknowledges",	"acknowledgeid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{0}
		},
		NULL
	},
	{"sysmap_shape",	"sysmap_shapeid",	0,
		{
		{"sysmap_shapeid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"sysmapid",	NULL,	"sysmaps",	"sysmapid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"x",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"y",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"width",	"200",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"height",	"200",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"text",	"",	NULL,	NULL,	ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL,	0},
		{"font",	"9",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"font_size",	"11",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"font_color",	"000000",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"text_halign",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"text_valign",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"border_type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"border_width",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"border_color",	"000000",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"background_color",	"",	NULL,	NULL,	6,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"zindex",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"sysmap_element_trigger",	"selement_triggerid",	0,
		{
		{"selement_triggerid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"selementid",	NULL,	"sysmaps_elements",	"selementid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"triggerid",	NULL,	"triggers",	"triggerid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{0}
		},
		"selementid,triggerid"
	},
	{"httptest_field",	"httptest_fieldid",	0,
		{
		{"httptest_fieldid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"httptestid",	NULL,	"httptest",	"httptestid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL | ZBX_PROXY,	ZBX_FK_CASCADE_DELETE},
		{"type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"name",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"value",	"",	NULL,	NULL,	ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{0}
		},
		NULL
	},
	{"httpstep_field",	"httpstep_fieldid",	0,
		{
		{"httpstep_fieldid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"httpstepid",	NULL,	"httpstep",	"httpstepid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL | ZBX_PROXY,	ZBX_FK_CASCADE_DELETE},
		{"type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"name",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"value",	"",	NULL,	NULL,	ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{0}
		},
		NULL
	},
	{"dashboard",	"dashboardid",	0,
		{
		{"dashboardid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"name",	NULL,	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"userid",	NULL,	"users",	"userid",	0,	ZBX_TYPE_ID,	0,	0},
		{"private",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"templateid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"display_period",	"30",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"auto_start",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"uuid",	"",	NULL,	NULL,	32,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"dashboard_user",	"dashboard_userid",	0,
		{
		{"dashboard_userid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"dashboardid",	NULL,	"dashboard",	"dashboardid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"userid",	NULL,	"users",	"userid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"permission",	"2",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		"dashboardid,userid"
	},
	{"dashboard_usrgrp",	"dashboard_usrgrpid",	0,
		{
		{"dashboard_usrgrpid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"dashboardid",	NULL,	"dashboard",	"dashboardid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"usrgrpid",	NULL,	"usrgrp",	"usrgrpid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"permission",	"2",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		"dashboardid,usrgrpid"
	},
	{"dashboard_page",	"dashboard_pageid",	0,
		{
		{"dashboard_pageid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"dashboardid",	NULL,	"dashboard",	"dashboardid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"name",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"display_period",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"sortorder",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"widget",	"widgetid",	0,
		{
		{"widgetid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"type",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"x",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"y",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"width",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"height",	"2",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"view_mode",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"dashboard_pageid",	NULL,	"dashboard_page",	"dashboard_pageid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{0}
		},
		NULL
	},
	{"widget_field",	"widget_fieldid",	0,
		{
		{"widget_fieldid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"widgetid",	NULL,	"widget",	"widgetid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"value_int",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"value_str",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"value_groupid",	NULL,	"hstgrp",	"groupid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"value_hostid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"value_itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"value_graphid",	NULL,	"graphs",	"graphid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"value_sysmapid",	NULL,	"sysmaps",	"sysmapid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"value_serviceid",	NULL,	"services",	"serviceid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"value_slaid",	NULL,	"sla",	"slaid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{0}
		},
		NULL
	},
	{"task_check_now",	"taskid",	0,
		{
		{"taskid",	NULL,	"task",	"taskid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{0}
		},
		NULL
	},
	{"event_suppress",	"event_suppressid",	0,
		{
		{"event_suppressid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"eventid",	NULL,	"events",	"eventid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"maintenanceid",	NULL,	"maintenances",	"maintenanceid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"suppress_until",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		"eventid,maintenanceid"
	},
	{"maintenance_tag",	"maintenancetagid",	0,
		{
		{"maintenancetagid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"maintenanceid",	NULL,	"maintenances",	"maintenanceid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"tag",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"operator",	"2",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"lld_macro_path",	"lld_macro_pathid",	0,
		{
		{"lld_macro_pathid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"lld_macro",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"path",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		"itemid,lld_macro"
	},
	{"host_tag",	"hosttagid",	0,
		{
		{"hosttagid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"hostid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"tag",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"config_autoreg_tls",	"autoreg_tlsid",	0,
		{
		{"autoreg_tlsid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"tls_psk_identity",	"",	NULL,	NULL,	128,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"tls_psk",	"",	NULL,	NULL,	512,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{0}
		},
		"tls_psk_identity"
	},
	{"module",	"moduleid",	0,
		{
		{"moduleid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"id",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"relative_path",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"config",	"",	NULL,	NULL,	ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"interface_snmp",	"interfaceid",	0,
		{
		{"interfaceid",	NULL,	"interface",	"interfaceid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"version",	"2",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"bulk",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"community",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"securityname",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"securitylevel",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"authpassphrase",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"privpassphrase",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"authprotocol",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"privprotocol",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"contextname",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{0}
		},
		NULL
	},
	{"lld_override",	"lld_overrideid",	0,
		{
		{"lld_overrideid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"name",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"step",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"evaltype",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"formula",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"stop",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		"itemid,name"
	},
	{"lld_override_condition",	"lld_override_conditionid",	0,
		{
		{"lld_override_conditionid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"lld_overrideid",	NULL,	"lld_override",	"lld_overrideid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"operator",	"8",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"macro",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"lld_override_operation",	"lld_override_operationid",	0,
		{
		{"lld_override_operationid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"lld_overrideid",	NULL,	"lld_override",	"lld_overrideid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"operationobject",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"operator",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"lld_override_opstatus",	"lld_override_operationid",	0,
		{
		{"lld_override_operationid",	NULL,	"lld_override_operation",	"lld_override_operationid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"lld_override_opdiscover",	"lld_override_operationid",	0,
		{
		{"lld_override_operationid",	NULL,	"lld_override_operation",	"lld_override_operationid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"discover",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"lld_override_opperiod",	"lld_override_operationid",	0,
		{
		{"lld_override_operationid",	NULL,	"lld_override_operation",	"lld_override_operationid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"delay",	"0",	NULL,	NULL,	1024,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"lld_override_ophistory",	"lld_override_operationid",	0,
		{
		{"lld_override_operationid",	NULL,	"lld_override_operation",	"lld_override_operationid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"history",	"90d",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"lld_override_optrends",	"lld_override_operationid",	0,
		{
		{"lld_override_operationid",	NULL,	"lld_override_operation",	"lld_override_operationid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"trends",	"365d",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"lld_override_opseverity",	"lld_override_operationid",	0,
		{
		{"lld_override_operationid",	NULL,	"lld_override_operation",	"lld_override_operationid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"severity",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"lld_override_optag",	"lld_override_optagid",	0,
		{
		{"lld_override_optagid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"lld_override_operationid",	NULL,	"lld_override_operation",	"lld_override_operationid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"tag",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"lld_override_optemplate",	"lld_override_optemplateid",	0,
		{
		{"lld_override_optemplateid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"lld_override_operationid",	NULL,	"lld_override_operation",	"lld_override_operationid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"templateid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{0}
		},
		"lld_override_operationid,templateid"
	},
	{"lld_override_opinventory",	"lld_override_operationid",	0,
		{
		{"lld_override_operationid",	NULL,	"lld_override_operation",	"lld_override_operationid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"inventory_mode",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"trigger_queue",	"trigger_queueid",	0,
		{
		{"trigger_queueid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"objectid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"clock",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"ns",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"item_parameter",	"item_parameterid",	0,
		{
		{"item_parameterid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL | ZBX_PROXY,	ZBX_FK_CASCADE_DELETE},
		{"name",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{"value",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL | ZBX_PROXY,	0},
		{0}
		},
		NULL
	},
	{"role_rule",	"role_ruleid",	0,
		{
		{"role_ruleid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"roleid",	NULL,	"role",	"roleid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"value_int",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"value_str",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"value_moduleid",	NULL,	"module",	"moduleid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{"value_serviceid",	NULL,	"services",	"serviceid",	0,	ZBX_TYPE_ID,	0,	ZBX_FK_CASCADE_DELETE},
		{0}
		},
		NULL
	},
	{"token",	"tokenid",	0,
		{
		{"tokenid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	64,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"description",	"",	NULL,	NULL,	ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL,	0},
		{"userid",	NULL,	"users",	"userid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"token",	NULL,	NULL,	NULL,	128,	ZBX_TYPE_CHAR,	0,	0},
		{"lastaccess",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"expires_at",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"created_at",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"creator_userid",	NULL,	"users",	"userid",	0,	ZBX_TYPE_ID,	0,	0},
		{0}
		},
		"token"
	},
	{"item_tag",	"itemtagid",	0,
		{
		{"itemtagid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"itemid",	NULL,	"items",	"itemid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"tag",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"httptest_tag",	"httptesttagid",	0,
		{
		{"httptesttagid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"httptestid",	NULL,	"httptest",	"httptestid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"tag",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"sysmaps_element_tag",	"selementtagid",	0,
		{
		{"selementtagid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"selementid",	NULL,	"sysmaps_elements",	"selementid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"tag",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"operator",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"report",	"reportid",	0,
		{
		{"reportid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"userid",	NULL,	"users",	"userid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"name",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"description",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"dashboardid",	NULL,	"dashboard",	"dashboardid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"period",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"cycle",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"weekdays",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"start_time",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"active_since",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"active_till",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"state",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"lastsent",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"info",	"",	NULL,	NULL,	2048,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		"name"
	},
	{"report_param",	"reportparamid",	0,
		{
		{"reportparamid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"reportid",	NULL,	"report",	"reportid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"name",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"report_user",	"reportuserid",	0,
		{
		{"reportuserid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"reportid",	NULL,	"report",	"reportid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"userid",	NULL,	"users",	"userid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"exclude",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"access_userid",	NULL,	"users",	"userid",	0,	ZBX_TYPE_ID,	0,	0},
		{0}
		},
		NULL
	},
	{"report_usrgrp",	"reportusrgrpid",	0,
		{
		{"reportusrgrpid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"reportid",	NULL,	"report",	"reportid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"usrgrpid",	NULL,	"usrgrp",	"usrgrpid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"access_userid",	NULL,	"users",	"userid",	0,	ZBX_TYPE_ID,	0,	0},
		{0}
		},
		NULL
	},
	{"service_problem_tag",	"service_problem_tagid",	0,
		{
		{"service_problem_tagid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"serviceid",	NULL,	"services",	"serviceid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"tag",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"operator",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"service_problem",	"service_problemid",	0,
		{
		{"service_problemid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"eventid",	NULL,	"problem",	"eventid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"serviceid",	NULL,	"services",	"serviceid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"severity",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"service_tag",	"servicetagid",	0,
		{
		{"servicetagid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"serviceid",	NULL,	"services",	"serviceid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"tag",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"service_status_rule",	"service_status_ruleid",	0,
		{
		{"service_status_ruleid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"serviceid",	NULL,	"services",	"serviceid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"type",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"limit_value",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"limit_status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"new_status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"ha_node",	"ha_nodeid",	0,
		{
		{"ha_nodeid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_CUID,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"address",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"port",	"10051",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"lastaccess",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"status",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"ha_sessionid",	"",	NULL,	NULL,	0,	ZBX_TYPE_CUID,	ZBX_NOTNULL,	0},
		{0}
		},
		"name"
	},
	{"sla",	"slaid",	0,
		{
		{"slaid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"period",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"slo",	"99.9",	NULL,	NULL,	0,	ZBX_TYPE_FLOAT,	ZBX_NOTNULL,	0},
		{"effective_date",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"timezone",	"UTC",	NULL,	NULL,	50,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"status",	"1",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"description",	"",	NULL,	NULL,	ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL,	0},
		{0}
		},
		"name"
	},
	{"sla_schedule",	"sla_scheduleid",	0,
		{
		{"sla_scheduleid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"slaid",	NULL,	"sla",	"slaid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"period_from",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"period_to",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"sla_excluded_downtime",	"sla_excluded_downtimeid",	0,
		{
		{"sla_excluded_downtimeid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"slaid",	NULL,	"sla",	"slaid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"name",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"period_from",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"period_to",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"sla_service_tag",	"sla_service_tagid",	0,
		{
		{"sla_service_tagid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"slaid",	NULL,	"sla",	"slaid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"tag",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"operator",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"value",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"host_rtdata",	"hostid",	0,
		{
		{"hostid",	NULL,	"hosts",	"hostid",	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	ZBX_FK_CASCADE_DELETE},
		{"active_available",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"userdirectory",	"userdirectoryid",	0,
		{
		{"userdirectoryid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"name",	"",	NULL,	NULL,	128,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"description",	"",	NULL,	NULL,	ZBX_TYPE_SHORTTEXT_LEN,	ZBX_TYPE_SHORTTEXT,	ZBX_NOTNULL,	0},
		{"host",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"port",	"389",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"base_dn",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"bind_dn",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"bind_password",	"",	NULL,	NULL,	128,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"search_attribute",	"",	NULL,	NULL,	128,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{"start_tls",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"search_filter",	"",	NULL,	NULL,	255,	ZBX_TYPE_CHAR,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{"dbversion",	"dbversionid",	0,
		{
		{"dbversionid",	NULL,	NULL,	NULL,	0,	ZBX_TYPE_ID,	ZBX_NOTNULL,	0},
		{"mandatory",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{"optional",	"0",	NULL,	NULL,	0,	ZBX_TYPE_INT,	ZBX_NOTNULL,	0},
		{0}
		},
		NULL
	},
	{0}

#undef ZBX_TYPE_LONGTEXT_LEN
#undef ZBX_TYPE_SHORTTEXT_LEN

};
#if defined(HAVE_SQLITE3)
const char	*const db_schema = "\
CREATE TABLE role (\n\
roleid bigint  NOT NULL,\n\
name varchar(255) DEFAULT '' NOT NULL,\n\
type integer DEFAULT '0' NOT NULL,\n\
readonly integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (roleid)\n\
);\n\
CREATE UNIQUE INDEX role_1 ON role (name);\n\
CREATE TABLE users (\n\
userid bigint  NOT NULL,\n\
username varchar(100) DEFAULT '' NOT NULL,\n\
name varchar(100) DEFAULT '' NOT NULL,\n\
surname varchar(100) DEFAULT '' NOT NULL,\n\
passwd varchar(60) DEFAULT '' NOT NULL,\n\
url varchar(255) DEFAULT '' NOT NULL,\n\
autologin integer DEFAULT '0' NOT NULL,\n\
autologout varchar(32) DEFAULT '15m' NOT NULL,\n\
lang varchar(7) DEFAULT 'default' NOT NULL,\n\
refresh varchar(32) DEFAULT '30s' NOT NULL,\n\
theme varchar(128) DEFAULT 'default' NOT NULL,\n\
attempt_failed integer DEFAULT 0 NOT NULL,\n\
attempt_ip varchar(39) DEFAULT '' NOT NULL,\n\
attempt_clock integer DEFAULT 0 NOT NULL,\n\
rows_per_page integer DEFAULT 50 NOT NULL,\n\
timezone varchar(50) DEFAULT 'default' NOT NULL,\n\
roleid bigint  NOT NULL REFERENCES role (roleid) ON DELETE CASCADE,\n\
PRIMARY KEY (userid)\n\
);\n\
CREATE UNIQUE INDEX users_1 ON users (username);\n\
CREATE TABLE maintenances (\n\
maintenanceid bigint  NOT NULL,\n\
name varchar(128) DEFAULT '' NOT NULL,\n\
maintenance_type integer DEFAULT '0' NOT NULL,\n\
description text DEFAULT '' NOT NULL,\n\
active_since integer DEFAULT '0' NOT NULL,\n\
active_till integer DEFAULT '0' NOT NULL,\n\
tags_evaltype integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (maintenanceid)\n\
);\n\
CREATE INDEX maintenances_1 ON maintenances (active_since,active_till);\n\
CREATE UNIQUE INDEX maintenances_2 ON maintenances (name);\n\
CREATE TABLE hosts (\n\
hostid bigint  NOT NULL,\n\
proxy_hostid bigint  NULL REFERENCES hosts (hostid),\n\
host varchar(128) DEFAULT '' NOT NULL,\n\
status integer DEFAULT '0' NOT NULL,\n\
lastaccess integer DEFAULT '0' NOT NULL,\n\
ipmi_authtype integer DEFAULT '-1' NOT NULL,\n\
ipmi_privilege integer DEFAULT '2' NOT NULL,\n\
ipmi_username varchar(16) DEFAULT '' NOT NULL,\n\
ipmi_password varchar(20) DEFAULT '' NOT NULL,\n\
maintenanceid bigint  NULL REFERENCES maintenances (maintenanceid),\n\
maintenance_status integer DEFAULT '0' NOT NULL,\n\
maintenance_type integer DEFAULT '0' NOT NULL,\n\
maintenance_from integer DEFAULT '0' NOT NULL,\n\
name varchar(128) DEFAULT '' NOT NULL,\n\
flags integer DEFAULT '0' NOT NULL,\n\
templateid bigint  NULL REFERENCES hosts (hostid) ON DELETE CASCADE,\n\
description text DEFAULT '' NOT NULL,\n\
tls_connect integer DEFAULT '1' NOT NULL,\n\
tls_accept integer DEFAULT '1' NOT NULL,\n\
tls_issuer varchar(1024) DEFAULT '' NOT NULL,\n\
tls_subject varchar(1024) DEFAULT '' NOT NULL,\n\
tls_psk_identity varchar(128) DEFAULT '' NOT NULL,\n\
tls_psk varchar(512) DEFAULT '' NOT NULL,\n\
proxy_address varchar(255) DEFAULT '' NOT NULL,\n\
auto_compress integer DEFAULT '1' NOT NULL,\n\
discover integer DEFAULT '0' NOT NULL,\n\
custom_interfaces integer DEFAULT '0' NOT NULL,\n\
uuid varchar(32) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (hostid)\n\
);\n\
CREATE INDEX hosts_1 ON hosts (host);\n\
CREATE INDEX hosts_2 ON hosts (status);\n\
CREATE INDEX hosts_3 ON hosts (proxy_hostid);\n\
CREATE INDEX hosts_4 ON hosts (name);\n\
CREATE INDEX hosts_5 ON hosts (maintenanceid);\n\
CREATE TABLE hstgrp (\n\
groupid bigint  NOT NULL,\n\
name varchar(255) DEFAULT '' NOT NULL,\n\
internal integer DEFAULT '0' NOT NULL,\n\
flags integer DEFAULT '0' NOT NULL,\n\
uuid varchar(32) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (groupid)\n\
);\n\
CREATE INDEX hstgrp_1 ON hstgrp (name);\n\
CREATE TABLE group_prototype (\n\
group_prototypeid bigint  NOT NULL,\n\
hostid bigint  NOT NULL REFERENCES hosts (hostid) ON DELETE CASCADE,\n\
name varchar(255) DEFAULT '' NOT NULL,\n\
groupid bigint  NULL REFERENCES hstgrp (groupid),\n\
templateid bigint  NULL REFERENCES group_prototype (group_prototypeid) ON DELETE CASCADE,\n\
PRIMARY KEY (group_prototypeid)\n\
);\n\
CREATE INDEX group_prototype_1 ON group_prototype (hostid);\n\
CREATE TABLE group_discovery (\n\
groupid bigint  NOT NULL REFERENCES hstgrp (groupid) ON DELETE CASCADE,\n\
parent_group_prototypeid bigint  NOT NULL REFERENCES group_prototype (group_prototypeid),\n\
name varchar(64) DEFAULT '' NOT NULL,\n\
lastcheck integer DEFAULT '0' NOT NULL,\n\
ts_delete integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (groupid)\n\
);\n\
CREATE TABLE drules (\n\
druleid bigint  NOT NULL,\n\
proxy_hostid bigint  NULL REFERENCES hosts (hostid),\n\
name varchar(255) DEFAULT '' NOT NULL,\n\
iprange varchar(2048) DEFAULT '' NOT NULL,\n\
delay varchar(255) DEFAULT '1h' NOT NULL,\n\
nextcheck integer DEFAULT '0' NOT NULL,\n\
status integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (druleid)\n\
);\n\
CREATE INDEX drules_1 ON drules (proxy_hostid);\n\
CREATE UNIQUE INDEX drules_2 ON drules (name);\n\
CREATE TABLE dchecks (\n\
dcheckid bigint  NOT NULL,\n\
druleid bigint  NOT NULL REFERENCES drules (druleid) ON DELETE CASCADE,\n\
type integer DEFAULT '0' NOT NULL,\n\
key_ varchar(2048) DEFAULT '' NOT NULL,\n\
snmp_community varchar(255) DEFAULT '' NOT NULL,\n\
ports varchar(255) DEFAULT '0' NOT NULL,\n\
snmpv3_securityname varchar(64) DEFAULT '' NOT NULL,\n\
snmpv3_securitylevel integer DEFAULT '0' NOT NULL,\n\
snmpv3_authpassphrase varchar(64) DEFAULT '' NOT NULL,\n\
snmpv3_privpassphrase varchar(64) DEFAULT '' NOT NULL,\n\
uniq integer DEFAULT '0' NOT NULL,\n\
snmpv3_authprotocol integer DEFAULT '0' NOT NULL,\n\
snmpv3_privprotocol integer DEFAULT '0' NOT NULL,\n\
snmpv3_contextname varchar(255) DEFAULT '' NOT NULL,\n\
host_source integer DEFAULT '1' NOT NULL,\n\
name_source integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (dcheckid)\n\
);\n\
CREATE INDEX dchecks_1 ON dchecks (druleid,host_source,name_source);\n\
CREATE TABLE httptest (\n\
httptestid bigint  NOT NULL,\n\
name varchar(64) DEFAULT '' NOT NULL,\n\
nextcheck integer DEFAULT '0' NOT NULL,\n\
delay varchar(255) DEFAULT '1m' NOT NULL,\n\
status integer DEFAULT '0' NOT NULL,\n\
agent varchar(255) DEFAULT 'Zabbix' NOT NULL,\n\
authentication integer DEFAULT '0' NOT NULL,\n\
http_user varchar(64) DEFAULT '' NOT NULL,\n\
http_password varchar(64) DEFAULT '' NOT NULL,\n\
hostid bigint  NOT NULL REFERENCES hosts (hostid) ON DELETE CASCADE,\n\
templateid bigint  NULL REFERENCES httptest (httptestid) ON DELETE CASCADE,\n\
http_proxy varchar(255) DEFAULT '' NOT NULL,\n\
retries integer DEFAULT '1' NOT NULL,\n\
ssl_cert_file varchar(255) DEFAULT '' NOT NULL,\n\
ssl_key_file varchar(255) DEFAULT '' NOT NULL,\n\
ssl_key_password varchar(64) DEFAULT '' NOT NULL,\n\
verify_peer integer DEFAULT '0' NOT NULL,\n\
verify_host integer DEFAULT '0' NOT NULL,\n\
uuid varchar(32) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (httptestid)\n\
);\n\
CREATE UNIQUE INDEX httptest_2 ON httptest (hostid,name);\n\
CREATE INDEX httptest_3 ON httptest (status);\n\
CREATE INDEX httptest_4 ON httptest (templateid);\n\
CREATE TABLE httpstep (\n\
httpstepid bigint  NOT NULL,\n\
httptestid bigint  NOT NULL REFERENCES httptest (httptestid) ON DELETE CASCADE,\n\
name varchar(64) DEFAULT '' NOT NULL,\n\
no integer DEFAULT '0' NOT NULL,\n\
url varchar(2048) DEFAULT '' NOT NULL,\n\
timeout varchar(255) DEFAULT '15s' NOT NULL,\n\
posts text DEFAULT '' NOT NULL,\n\
required varchar(255) DEFAULT '' NOT NULL,\n\
status_codes varchar(255) DEFAULT '' NOT NULL,\n\
follow_redirects integer DEFAULT '1' NOT NULL,\n\
retrieve_mode integer DEFAULT '0' NOT NULL,\n\
post_type integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (httpstepid)\n\
);\n\
CREATE INDEX httpstep_1 ON httpstep (httptestid);\n\
CREATE TABLE interface (\n\
interfaceid bigint  NOT NULL,\n\
hostid bigint  NOT NULL REFERENCES hosts (hostid) ON DELETE CASCADE,\n\
main integer DEFAULT '0' NOT NULL,\n\
type integer DEFAULT '1' NOT NULL,\n\
useip integer DEFAULT '1' NOT NULL,\n\
ip varchar(64) DEFAULT '127.0.0.1' NOT NULL,\n\
dns varchar(255) DEFAULT '' NOT NULL,\n\
port varchar(64) DEFAULT '10050' NOT NULL,\n\
available integer DEFAULT '0' NOT NULL,\n\
error varchar(2048) DEFAULT '' NOT NULL,\n\
errors_from integer DEFAULT '0' NOT NULL,\n\
disable_until integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (interfaceid)\n\
);\n\
CREATE INDEX interface_1 ON interface (hostid,type);\n\
CREATE INDEX interface_2 ON interface (ip,dns);\n\
CREATE INDEX interface_3 ON interface (available);\n\
CREATE TABLE valuemap (\n\
valuemapid bigint  NOT NULL,\n\
hostid bigint  NOT NULL REFERENCES hosts (hostid) ON DELETE CASCADE,\n\
name varchar(64) DEFAULT '' NOT NULL,\n\
uuid varchar(32) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (valuemapid)\n\
);\n\
CREATE UNIQUE INDEX valuemap_1 ON valuemap (hostid,name);\n\
CREATE TABLE items (\n\
itemid bigint  NOT NULL,\n\
type integer DEFAULT '0' NOT NULL,\n\
snmp_oid varchar(512) DEFAULT '' NOT NULL,\n\
hostid bigint  NOT NULL REFERENCES hosts (hostid) ON DELETE CASCADE,\n\
name varchar(255) DEFAULT '' NOT NULL,\n\
key_ varchar(2048) DEFAULT '' NOT NULL,\n\
delay varchar(1024) DEFAULT '0' NOT NULL,\n\
history varchar(255) DEFAULT '90d' NOT NULL,\n\
trends varchar(255) DEFAULT '365d' NOT NULL,\n\
status integer DEFAULT '0' NOT NULL,\n\
value_type integer DEFAULT '0' NOT NULL,\n\
trapper_hosts varchar(255) DEFAULT '' NOT NULL,\n\
units varchar(255) DEFAULT '' NOT NULL,\n\
formula varchar(255) DEFAULT '' NOT NULL,\n\
logtimefmt varchar(64) DEFAULT '' NOT NULL,\n\
templateid bigint  NULL REFERENCES items (itemid) ON DELETE CASCADE,\n\
valuemapid bigint  NULL REFERENCES valuemap (valuemapid),\n\
params text DEFAULT '' NOT NULL,\n\
ipmi_sensor varchar(128) DEFAULT '' NOT NULL,\n\
authtype integer DEFAULT '0' NOT NULL,\n\
username varchar(64) DEFAULT '' NOT NULL,\n\
password varchar(64) DEFAULT '' NOT NULL,\n\
publickey varchar(64) DEFAULT '' NOT NULL,\n\
privatekey varchar(64) DEFAULT '' NOT NULL,\n\
flags integer DEFAULT '0' NOT NULL,\n\
interfaceid bigint  NULL REFERENCES interface (interfaceid),\n\
description text DEFAULT '' NOT NULL,\n\
inventory_link integer DEFAULT '0' NOT NULL,\n\
lifetime varchar(255) DEFAULT '30d' NOT NULL,\n\
evaltype integer DEFAULT '0' NOT NULL,\n\
jmx_endpoint varchar(255) DEFAULT '' NOT NULL,\n\
master_itemid bigint  NULL REFERENCES items (itemid) ON DELETE CASCADE,\n\
timeout varchar(255) DEFAULT '3s' NOT NULL,\n\
url varchar(2048) DEFAULT '' NOT NULL,\n\
query_fields varchar(2048) DEFAULT '' NOT NULL,\n\
posts text DEFAULT '' NOT NULL,\n\
status_codes varchar(255) DEFAULT '200' NOT NULL,\n\
follow_redirects integer DEFAULT '1' NOT NULL,\n\
post_type integer DEFAULT '0' NOT NULL,\n\
http_proxy varchar(255) DEFAULT '' NOT NULL,\n\
headers text DEFAULT '' NOT NULL,\n\
retrieve_mode integer DEFAULT '0' NOT NULL,\n\
request_method integer DEFAULT '0' NOT NULL,\n\
output_format integer DEFAULT '0' NOT NULL,\n\
ssl_cert_file varchar(255) DEFAULT '' NOT NULL,\n\
ssl_key_file varchar(255) DEFAULT '' NOT NULL,\n\
ssl_key_password varchar(64) DEFAULT '' NOT NULL,\n\
verify_peer integer DEFAULT '0' NOT NULL,\n\
verify_host integer DEFAULT '0' NOT NULL,\n\
allow_traps integer DEFAULT '0' NOT NULL,\n\
discover integer DEFAULT '0' NOT NULL,\n\
uuid varchar(32) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (itemid)\n\
);\n\
CREATE INDEX items_1 ON items (hostid,key_);\n\
CREATE INDEX items_3 ON items (status);\n\
CREATE INDEX items_4 ON items (templateid);\n\
CREATE INDEX items_5 ON items (valuemapid);\n\
CREATE INDEX items_6 ON items (interfaceid);\n\
CREATE INDEX items_7 ON items (master_itemid);\n\
CREATE INDEX items_8 ON items (key_);\n\
CREATE TABLE httpstepitem (\n\
httpstepitemid bigint  NOT NULL,\n\
httpstepid bigint  NOT NULL REFERENCES httpstep (httpstepid) ON DELETE CASCADE,\n\
itemid bigint  NOT NULL REFERENCES items (itemid) ON DELETE CASCADE,\n\
type integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (httpstepitemid)\n\
);\n\
CREATE UNIQUE INDEX httpstepitem_1 ON httpstepitem (httpstepid,itemid);\n\
CREATE INDEX httpstepitem_2 ON httpstepitem (itemid);\n\
CREATE TABLE httptestitem (\n\
httptestitemid bigint  NOT NULL,\n\
httptestid bigint  NOT NULL REFERENCES httptest (httptestid) ON DELETE CASCADE,\n\
itemid bigint  NOT NULL REFERENCES items (itemid) ON DELETE CASCADE,\n\
type integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (httptestitemid)\n\
);\n\
CREATE UNIQUE INDEX httptestitem_1 ON httptestitem (httptestid,itemid);\n\
CREATE INDEX httptestitem_2 ON httptestitem (itemid);\n\
CREATE TABLE media_type (\n\
mediatypeid bigint  NOT NULL,\n\
type integer DEFAULT '0' NOT NULL,\n\
name varchar(100) DEFAULT '' NOT NULL,\n\
smtp_server varchar(255) DEFAULT '' NOT NULL,\n\
smtp_helo varchar(255) DEFAULT '' NOT NULL,\n\
smtp_email varchar(255) DEFAULT '' NOT NULL,\n\
exec_path varchar(255) DEFAULT '' NOT NULL,\n\
gsm_modem varchar(255) DEFAULT '' NOT NULL,\n\
username varchar(255) DEFAULT '' NOT NULL,\n\
passwd varchar(255) DEFAULT '' NOT NULL,\n\
status integer DEFAULT '0' NOT NULL,\n\
smtp_port integer DEFAULT '25' NOT NULL,\n\
smtp_security integer DEFAULT '0' NOT NULL,\n\
smtp_verify_peer integer DEFAULT '0' NOT NULL,\n\
smtp_verify_host integer DEFAULT '0' NOT NULL,\n\
smtp_authentication integer DEFAULT '0' NOT NULL,\n\
exec_params varchar(255) DEFAULT '' NOT NULL,\n\
maxsessions integer DEFAULT '1' NOT NULL,\n\
maxattempts integer DEFAULT '3' NOT NULL,\n\
attempt_interval varchar(32) DEFAULT '10s' NOT NULL,\n\
content_type integer DEFAULT '1' NOT NULL,\n\
script text DEFAULT '' NOT NULL,\n\
timeout varchar(32) DEFAULT '30s' NOT NULL,\n\
process_tags integer DEFAULT '0' NOT NULL,\n\
show_event_menu integer DEFAULT '0' NOT NULL,\n\
event_menu_url varchar(2048) DEFAULT '' NOT NULL,\n\
event_menu_name varchar(255) DEFAULT '' NOT NULL,\n\
description text DEFAULT '' NOT NULL,\n\
PRIMARY KEY (mediatypeid)\n\
);\n\
CREATE UNIQUE INDEX media_type_1 ON media_type (name);\n\
CREATE TABLE media_type_param (\n\
mediatype_paramid bigint  NOT NULL,\n\
mediatypeid bigint  NOT NULL REFERENCES media_type (mediatypeid) ON DELETE CASCADE,\n\
name varchar(255) DEFAULT '' NOT NULL,\n\
value varchar(2048) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (mediatype_paramid)\n\
);\n\
CREATE INDEX media_type_param_1 ON media_type_param (mediatypeid);\n\
CREATE TABLE media_type_message (\n\
mediatype_messageid bigint  NOT NULL,\n\
mediatypeid bigint  NOT NULL REFERENCES media_type (mediatypeid) ON DELETE CASCADE,\n\
eventsource integer  NOT NULL,\n\
recovery integer  NOT NULL,\n\
subject varchar(255) DEFAULT '' NOT NULL,\n\
message text DEFAULT '' NOT NULL,\n\
PRIMARY KEY (mediatype_messageid)\n\
);\n\
CREATE UNIQUE INDEX media_type_message_1 ON media_type_message (mediatypeid,eventsource,recovery);\n\
CREATE TABLE usrgrp (\n\
usrgrpid bigint  NOT NULL,\n\
name varchar(64) DEFAULT '' NOT NULL,\n\
gui_access integer DEFAULT '0' NOT NULL,\n\
users_status integer DEFAULT '0' NOT NULL,\n\
debug_mode integer DEFAULT '0' NOT NULL,\n\
userdirectoryid bigint DEFAULT NULL NULL REFERENCES userdirectory (userdirectoryid),\n\
PRIMARY KEY (usrgrpid)\n\
);\n\
CREATE UNIQUE INDEX usrgrp_1 ON usrgrp (name);\n\
CREATE INDEX usrgrp_2 ON usrgrp (userdirectoryid);\n\
CREATE TABLE users_groups (\n\
id bigint  NOT NULL,\n\
usrgrpid bigint  NOT NULL REFERENCES usrgrp (usrgrpid) ON DELETE CASCADE,\n\
userid bigint  NOT NULL REFERENCES users (userid) ON DELETE CASCADE,\n\
PRIMARY KEY (id)\n\
);\n\
CREATE UNIQUE INDEX users_groups_1 ON users_groups (usrgrpid,userid);\n\
CREATE INDEX users_groups_2 ON users_groups (userid);\n\
CREATE TABLE scripts (\n\
scriptid bigint  NOT NULL,\n\
name varchar(255) DEFAULT '' NOT NULL,\n\
command text DEFAULT '' NOT NULL,\n\
host_access integer DEFAULT '2' NOT NULL,\n\
usrgrpid bigint  NULL REFERENCES usrgrp (usrgrpid),\n\
groupid bigint  NULL REFERENCES hstgrp (groupid),\n\
description text DEFAULT '' NOT NULL,\n\
confirmation varchar(255) DEFAULT '' NOT NULL,\n\
type integer DEFAULT '5' NOT NULL,\n\
execute_on integer DEFAULT '2' NOT NULL,\n\
timeout varchar(32) DEFAULT '30s' NOT NULL,\n\
scope integer DEFAULT '1' NOT NULL,\n\
port varchar(64) DEFAULT '' NOT NULL,\n\
authtype integer DEFAULT '0' NOT NULL,\n\
username varchar(64) DEFAULT '' NOT NULL,\n\
password varchar(64) DEFAULT '' NOT NULL,\n\
publickey varchar(64) DEFAULT '' NOT NULL,\n\
privatekey varchar(64) DEFAULT '' NOT NULL,\n\
menu_path varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (scriptid)\n\
);\n\
CREATE INDEX scripts_1 ON scripts (usrgrpid);\n\
CREATE INDEX scripts_2 ON scripts (groupid);\n\
CREATE UNIQUE INDEX scripts_3 ON scripts (name);\n\
CREATE TABLE script_param (\n\
script_paramid bigint  NOT NULL,\n\
scriptid bigint  NOT NULL REFERENCES scripts (scriptid) ON DELETE CASCADE,\n\
name varchar(255) DEFAULT '' NOT NULL,\n\
value varchar(2048) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (script_paramid)\n\
);\n\
CREATE UNIQUE INDEX script_param_1 ON script_param (scriptid,name);\n\
CREATE TABLE actions (\n\
actionid bigint  NOT NULL,\n\
name varchar(255) DEFAULT '' NOT NULL,\n\
eventsource integer DEFAULT '0' NOT NULL,\n\
evaltype integer DEFAULT '0' NOT NULL,\n\
status integer DEFAULT '0' NOT NULL,\n\
esc_period varchar(255) DEFAULT '1h' NOT NULL,\n\
formula varchar(1024) DEFAULT '' NOT NULL,\n\
pause_suppressed integer DEFAULT '1' NOT NULL,\n\
notify_if_canceled integer DEFAULT '1' NOT NULL,\n\
PRIMARY KEY (actionid)\n\
);\n\
CREATE INDEX actions_1 ON actions (eventsource,status);\n\
CREATE UNIQUE INDEX actions_2 ON actions (name);\n\
CREATE TABLE operations (\n\
operationid bigint  NOT NULL,\n\
actionid bigint  NOT NULL REFERENCES actions (actionid) ON DELETE CASCADE,\n\
operationtype integer DEFAULT '0' NOT NULL,\n\
esc_period varchar(255) DEFAULT '0' NOT NULL,\n\
esc_step_from integer DEFAULT '1' NOT NULL,\n\
esc_step_to integer DEFAULT '1' NOT NULL,\n\
evaltype integer DEFAULT '0' NOT NULL,\n\
recovery integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (operationid)\n\
);\n\
CREATE INDEX operations_1 ON operations (actionid);\n\
CREATE TABLE opmessage (\n\
operationid bigint  NOT NULL REFERENCES operations (operationid) ON DELETE CASCADE,\n\
default_msg integer DEFAULT '1' NOT NULL,\n\
subject varchar(255) DEFAULT '' NOT NULL,\n\
message text DEFAULT '' NOT NULL,\n\
mediatypeid bigint  NULL REFERENCES media_type (mediatypeid),\n\
PRIMARY KEY (operationid)\n\
);\n\
CREATE INDEX opmessage_1 ON opmessage (mediatypeid);\n\
CREATE TABLE opmessage_grp (\n\
opmessage_grpid bigint  NOT NULL,\n\
operationid bigint  NOT NULL REFERENCES operations (operationid) ON DELETE CASCADE,\n\
usrgrpid bigint  NOT NULL REFERENCES usrgrp (usrgrpid),\n\
PRIMARY KEY (opmessage_grpid)\n\
);\n\
CREATE UNIQUE INDEX opmessage_grp_1 ON opmessage_grp (operationid,usrgrpid);\n\
CREATE INDEX opmessage_grp_2 ON opmessage_grp (usrgrpid);\n\
CREATE TABLE opmessage_usr (\n\
opmessage_usrid bigint  NOT NULL,\n\
operationid bigint  NOT NULL REFERENCES operations (operationid) ON DELETE CASCADE,\n\
userid bigint  NOT NULL REFERENCES users (userid),\n\
PRIMARY KEY (opmessage_usrid)\n\
);\n\
CREATE UNIQUE INDEX opmessage_usr_1 ON opmessage_usr (operationid,userid);\n\
CREATE INDEX opmessage_usr_2 ON opmessage_usr (userid);\n\
CREATE TABLE opcommand (\n\
operationid bigint  NOT NULL REFERENCES operations (operationid) ON DELETE CASCADE,\n\
scriptid bigint  NOT NULL REFERENCES scripts (scriptid),\n\
PRIMARY KEY (operationid)\n\
);\n\
CREATE INDEX opcommand_1 ON opcommand (scriptid);\n\
CREATE TABLE opcommand_hst (\n\
opcommand_hstid bigint  NOT NULL,\n\
operationid bigint  NOT NULL REFERENCES operations (operationid) ON DELETE CASCADE,\n\
hostid bigint  NULL REFERENCES hosts (hostid),\n\
PRIMARY KEY (opcommand_hstid)\n\
);\n\
CREATE INDEX opcommand_hst_1 ON opcommand_hst (operationid);\n\
CREATE INDEX opcommand_hst_2 ON opcommand_hst (hostid);\n\
CREATE TABLE opcommand_grp (\n\
opcommand_grpid bigint  NOT NULL,\n\
operationid bigint  NOT NULL REFERENCES operations (operationid) ON DELETE CASCADE,\n\
groupid bigint  NOT NULL REFERENCES hstgrp (groupid),\n\
PRIMARY KEY (opcommand_grpid)\n\
);\n\
CREATE INDEX opcommand_grp_1 ON opcommand_grp (operationid);\n\
CREATE INDEX opcommand_grp_2 ON opcommand_grp (groupid);\n\
CREATE TABLE opgroup (\n\
opgroupid bigint  NOT NULL,\n\
operationid bigint  NOT NULL REFERENCES operations (operationid) ON DELETE CASCADE,\n\
groupid bigint  NOT NULL REFERENCES hstgrp (groupid),\n\
PRIMARY KEY (opgroupid)\n\
);\n\
CREATE UNIQUE INDEX opgroup_1 ON opgroup (operationid,groupid);\n\
CREATE INDEX opgroup_2 ON opgroup (groupid);\n\
CREATE TABLE optemplate (\n\
optemplateid bigint  NOT NULL,\n\
operationid bigint  NOT NULL REFERENCES operations (operationid) ON DELETE CASCADE,\n\
templateid bigint  NOT NULL REFERENCES hosts (hostid),\n\
PRIMARY KEY (optemplateid)\n\
);\n\
CREATE UNIQUE INDEX optemplate_1 ON optemplate (operationid,templateid);\n\
CREATE INDEX optemplate_2 ON optemplate (templateid);\n\
CREATE TABLE opconditions (\n\
opconditionid bigint  NOT NULL,\n\
operationid bigint  NOT NULL REFERENCES operations (operationid) ON DELETE CASCADE,\n\
conditiontype integer DEFAULT '0' NOT NULL,\n\
operator integer DEFAULT '0' NOT NULL,\n\
value varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (opconditionid)\n\
);\n\
CREATE INDEX opconditions_1 ON opconditions (operationid);\n\
CREATE TABLE conditions (\n\
conditionid bigint  NOT NULL,\n\
actionid bigint  NOT NULL REFERENCES actions (actionid) ON DELETE CASCADE,\n\
conditiontype integer DEFAULT '0' NOT NULL,\n\
operator integer DEFAULT '0' NOT NULL,\n\
value varchar(255) DEFAULT '' NOT NULL,\n\
value2 varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (conditionid)\n\
);\n\
CREATE INDEX conditions_1 ON conditions (actionid);\n\
CREATE TABLE config (\n\
configid bigint  NOT NULL,\n\
work_period varchar(255) DEFAULT '1-5,09:00-18:00' NOT NULL,\n\
alert_usrgrpid bigint  NULL REFERENCES usrgrp (usrgrpid),\n\
default_theme varchar(128) DEFAULT 'blue-theme' NOT NULL,\n\
authentication_type integer DEFAULT '0' NOT NULL,\n\
discovery_groupid bigint  NOT NULL REFERENCES hstgrp (groupid),\n\
max_in_table integer DEFAULT '50' NOT NULL,\n\
search_limit integer DEFAULT '1000' NOT NULL,\n\
severity_color_0 varchar(6) DEFAULT '97AAB3' NOT NULL,\n\
severity_color_1 varchar(6) DEFAULT '7499FF' NOT NULL,\n\
severity_color_2 varchar(6) DEFAULT 'FFC859' NOT NULL,\n\
severity_color_3 varchar(6) DEFAULT 'FFA059' NOT NULL,\n\
severity_color_4 varchar(6) DEFAULT 'E97659' NOT NULL,\n\
severity_color_5 varchar(6) DEFAULT 'E45959' NOT NULL,\n\
severity_name_0 varchar(32) DEFAULT 'Not classified' NOT NULL,\n\
severity_name_1 varchar(32) DEFAULT 'Information' NOT NULL,\n\
severity_name_2 varchar(32) DEFAULT 'Warning' NOT NULL,\n\
severity_name_3 varchar(32) DEFAULT 'Average' NOT NULL,\n\
severity_name_4 varchar(32) DEFAULT 'High' NOT NULL,\n\
severity_name_5 varchar(32) DEFAULT 'Disaster' NOT NULL,\n\
ok_period varchar(32) DEFAULT '5m' NOT NULL,\n\
blink_period varchar(32) DEFAULT '2m' NOT NULL,\n\
problem_unack_color varchar(6) DEFAULT 'CC0000' NOT NULL,\n\
problem_ack_color varchar(6) DEFAULT 'CC0000' NOT NULL,\n\
ok_unack_color varchar(6) DEFAULT '009900' NOT NULL,\n\
ok_ack_color varchar(6) DEFAULT '009900' NOT NULL,\n\
problem_unack_style integer DEFAULT '1' NOT NULL,\n\
problem_ack_style integer DEFAULT '1' NOT NULL,\n\
ok_unack_style integer DEFAULT '1' NOT NULL,\n\
ok_ack_style integer DEFAULT '1' NOT NULL,\n\
snmptrap_logging integer DEFAULT '1' NOT NULL,\n\
server_check_interval integer DEFAULT '10' NOT NULL,\n\
hk_events_mode integer DEFAULT '1' NOT NULL,\n\
hk_events_trigger varchar(32) DEFAULT '365d' NOT NULL,\n\
hk_events_internal varchar(32) DEFAULT '1d' NOT NULL,\n\
hk_events_discovery varchar(32) DEFAULT '1d' NOT NULL,\n\
hk_events_autoreg varchar(32) DEFAULT '1d' NOT NULL,\n\
hk_services_mode integer DEFAULT '1' NOT NULL,\n\
hk_services varchar(32) DEFAULT '365d' NOT NULL,\n\
hk_audit_mode integer DEFAULT '1' NOT NULL,\n\
hk_audit varchar(32) DEFAULT '365d' NOT NULL,\n\
hk_sessions_mode integer DEFAULT '1' NOT NULL,\n\
hk_sessions varchar(32) DEFAULT '365d' NOT NULL,\n\
hk_history_mode integer DEFAULT '1' NOT NULL,\n\
hk_history_global integer DEFAULT '0' NOT NULL,\n\
hk_history varchar(32) DEFAULT '90d' NOT NULL,\n\
hk_trends_mode integer DEFAULT '1' NOT NULL,\n\
hk_trends_global integer DEFAULT '0' NOT NULL,\n\
hk_trends varchar(32) DEFAULT '365d' NOT NULL,\n\
default_inventory_mode integer DEFAULT '-1' NOT NULL,\n\
custom_color integer DEFAULT '0' NOT NULL,\n\
http_auth_enabled integer DEFAULT '0' NOT NULL,\n\
http_login_form integer DEFAULT '0' NOT NULL,\n\
http_strip_domains varchar(2048) DEFAULT '' NOT NULL,\n\
http_case_sensitive integer DEFAULT '1' NOT NULL,\n\
ldap_configured integer DEFAULT '0' NOT NULL,\n\
ldap_case_sensitive integer DEFAULT '1' NOT NULL,\n\
db_extension varchar(32) DEFAULT '' NOT NULL,\n\
autoreg_tls_accept integer DEFAULT '1' NOT NULL,\n\
compression_status integer DEFAULT '0' NOT NULL,\n\
compress_older varchar(32) DEFAULT '7d' NOT NULL,\n\
instanceid varchar(32) DEFAULT '' NOT NULL,\n\
saml_auth_enabled integer DEFAULT '0' NOT NULL,\n\
saml_idp_entityid varchar(1024) DEFAULT '' NOT NULL,\n\
saml_sso_url varchar(2048) DEFAULT '' NOT NULL,\n\
saml_slo_url varchar(2048) DEFAULT '' NOT NULL,\n\
saml_username_attribute varchar(128) DEFAULT '' NOT NULL,\n\
saml_sp_entityid varchar(1024) DEFAULT '' NOT NULL,\n\
saml_nameid_format varchar(2048) DEFAULT '' NOT NULL,\n\
saml_sign_messages integer DEFAULT '0' NOT NULL,\n\
saml_sign_assertions integer DEFAULT '0' NOT NULL,\n\
saml_sign_authn_requests integer DEFAULT '0' NOT NULL,\n\
saml_sign_logout_requests integer DEFAULT '0' NOT NULL,\n\
saml_sign_logout_responses integer DEFAULT '0' NOT NULL,\n\
saml_encrypt_nameid integer DEFAULT '0' NOT NULL,\n\
saml_encrypt_assertions integer DEFAULT '0' NOT NULL,\n\
saml_case_sensitive integer DEFAULT '0' NOT NULL,\n\
default_lang varchar(5) DEFAULT 'en_US' NOT NULL,\n\
default_timezone varchar(50) DEFAULT 'system' NOT NULL,\n\
login_attempts integer DEFAULT '5' NOT NULL,\n\
login_block varchar(32) DEFAULT '30s' NOT NULL,\n\
show_technical_errors integer DEFAULT '0' NOT NULL,\n\
validate_uri_schemes integer DEFAULT '1' NOT NULL,\n\
uri_valid_schemes varchar(255) DEFAULT 'http,https,ftp,file,mailto,tel,ssh' NOT NULL,\n\
x_frame_options varchar(255) DEFAULT 'SAMEORIGIN' NOT NULL,\n\
iframe_sandboxing_enabled integer DEFAULT '1' NOT NULL,\n\
iframe_sandboxing_exceptions varchar(255) DEFAULT '' NOT NULL,\n\
max_overview_table_size integer DEFAULT '50' NOT NULL,\n\
history_period varchar(32) DEFAULT '24h' NOT NULL,\n\
period_default varchar(32) DEFAULT '1h' NOT NULL,\n\
max_period varchar(32) DEFAULT '2y' NOT NULL,\n\
socket_timeout varchar(32) DEFAULT '3s' NOT NULL,\n\
connect_timeout varchar(32) DEFAULT '3s' NOT NULL,\n\
media_type_test_timeout varchar(32) DEFAULT '65s' NOT NULL,\n\
script_timeout varchar(32) DEFAULT '60s' NOT NULL,\n\
item_test_timeout varchar(32) DEFAULT '60s' NOT NULL,\n\
session_key varchar(32) DEFAULT '' NOT NULL,\n\
url varchar(255) DEFAULT '' NOT NULL,\n\
report_test_timeout varchar(32) DEFAULT '60s' NOT NULL,\n\
dbversion_status text DEFAULT '' NOT NULL,\n\
hk_events_service varchar(32) DEFAULT '1d' NOT NULL,\n\
passwd_min_length integer DEFAULT '8' NOT NULL,\n\
passwd_check_rules integer DEFAULT '8' NOT NULL,\n\
auditlog_enabled integer DEFAULT '1' NOT NULL,\n\
ha_failover_delay varchar(32) DEFAULT '1m' NOT NULL,\n\
geomaps_tile_provider varchar(255) DEFAULT '' NOT NULL,\n\
geomaps_tile_url varchar(1024) DEFAULT '' NOT NULL,\n\
geomaps_max_zoom integer DEFAULT '0' NOT NULL,\n\
geomaps_attribution varchar(1024) DEFAULT '' NOT NULL,\n\
vault_provider integer DEFAULT '0' NOT NULL,\n\
ldap_userdirectoryid bigint DEFAULT NULL  NULL REFERENCES userdirectory (userdirectoryid),\n\
PRIMARY KEY (configid)\n\
);\n\
CREATE INDEX config_1 ON config (alert_usrgrpid);\n\
CREATE INDEX config_2 ON config (discovery_groupid);\n\
CREATE INDEX config_3 ON config (ldap_userdirectoryid);\n\
CREATE TABLE triggers (\n\
triggerid bigint  NOT NULL,\n\
expression varchar(2048) DEFAULT '' NOT NULL,\n\
description varchar(255) DEFAULT '' NOT NULL,\n\
url varchar(255) DEFAULT '' NOT NULL,\n\
status integer DEFAULT '0' NOT NULL,\n\
value integer DEFAULT '0' NOT NULL,\n\
priority integer DEFAULT '0' NOT NULL,\n\
lastchange integer DEFAULT '0' NOT NULL,\n\
comments text DEFAULT '' NOT NULL,\n\
error varchar(2048) DEFAULT '' NOT NULL,\n\
templateid bigint  NULL REFERENCES triggers (triggerid) ON DELETE CASCADE,\n\
type integer DEFAULT '0' NOT NULL,\n\
state integer DEFAULT '0' NOT NULL,\n\
flags integer DEFAULT '0' NOT NULL,\n\
recovery_mode integer DEFAULT '0' NOT NULL,\n\
recovery_expression varchar(2048) DEFAULT '' NOT NULL,\n\
correlation_mode integer DEFAULT '0' NOT NULL,\n\
correlation_tag varchar(255) DEFAULT '' NOT NULL,\n\
manual_close integer DEFAULT '0' NOT NULL,\n\
opdata varchar(255) DEFAULT '' NOT NULL,\n\
discover integer DEFAULT '0' NOT NULL,\n\
event_name varchar(2048) DEFAULT '' NOT NULL,\n\
uuid varchar(32) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (triggerid)\n\
);\n\
CREATE INDEX triggers_1 ON triggers (status);\n\
CREATE INDEX triggers_2 ON triggers (value,lastchange);\n\
CREATE INDEX triggers_3 ON triggers (templateid);\n\
CREATE TABLE trigger_depends (\n\
triggerdepid bigint  NOT NULL,\n\
triggerid_down bigint  NOT NULL REFERENCES triggers (triggerid) ON DELETE CASCADE,\n\
triggerid_up bigint  NOT NULL REFERENCES triggers (triggerid) ON DELETE CASCADE,\n\
PRIMARY KEY (triggerdepid)\n\
);\n\
CREATE UNIQUE INDEX trigger_depends_1 ON trigger_depends (triggerid_down,triggerid_up);\n\
CREATE INDEX trigger_depends_2 ON trigger_depends (triggerid_up);\n\
CREATE TABLE functions (\n\
functionid bigint  NOT NULL,\n\
itemid bigint  NOT NULL REFERENCES items (itemid) ON DELETE CASCADE,\n\
triggerid bigint  NOT NULL REFERENCES triggers (triggerid) ON DELETE CASCADE,\n\
name varchar(12) DEFAULT '' NOT NULL,\n\
parameter varchar(255) DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (functionid)\n\
);\n\
CREATE INDEX functions_1 ON functions (triggerid);\n\
CREATE INDEX functions_2 ON functions (itemid,name,parameter);\n\
CREATE TABLE graphs (\n\
graphid bigint  NOT NULL,\n\
name varchar(128) DEFAULT '' NOT NULL,\n\
width integer DEFAULT '900' NOT NULL,\n\
height integer DEFAULT '200' NOT NULL,\n\
yaxismin DOUBLE PRECISION DEFAULT '0' NOT NULL,\n\
yaxismax DOUBLE PRECISION DEFAULT '100' NOT NULL,\n\
templateid bigint  NULL REFERENCES graphs (graphid) ON DELETE CASCADE,\n\
show_work_period integer DEFAULT '1' NOT NULL,\n\
show_triggers integer DEFAULT '1' NOT NULL,\n\
graphtype integer DEFAULT '0' NOT NULL,\n\
show_legend integer DEFAULT '1' NOT NULL,\n\
show_3d integer DEFAULT '0' NOT NULL,\n\
percent_left DOUBLE PRECISION DEFAULT '0' NOT NULL,\n\
percent_right DOUBLE PRECISION DEFAULT '0' NOT NULL,\n\
ymin_type integer DEFAULT '0' NOT NULL,\n\
ymax_type integer DEFAULT '0' NOT NULL,\n\
ymin_itemid bigint  NULL REFERENCES items (itemid),\n\
ymax_itemid bigint  NULL REFERENCES items (itemid),\n\
flags integer DEFAULT '0' NOT NULL,\n\
discover integer DEFAULT '0' NOT NULL,\n\
uuid varchar(32) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (graphid)\n\
);\n\
CREATE INDEX graphs_1 ON graphs (name);\n\
CREATE INDEX graphs_2 ON graphs (templateid);\n\
CREATE INDEX graphs_3 ON graphs (ymin_itemid);\n\
CREATE INDEX graphs_4 ON graphs (ymax_itemid);\n\
CREATE TABLE graphs_items (\n\
gitemid bigint  NOT NULL,\n\
graphid bigint  NOT NULL REFERENCES graphs (graphid) ON DELETE CASCADE,\n\
itemid bigint  NOT NULL REFERENCES items (itemid) ON DELETE CASCADE,\n\
drawtype integer DEFAULT '0' NOT NULL,\n\
sortorder integer DEFAULT '0' NOT NULL,\n\
color varchar(6) DEFAULT '009600' NOT NULL,\n\
yaxisside integer DEFAULT '0' NOT NULL,\n\
calc_fnc integer DEFAULT '2' NOT NULL,\n\
type integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (gitemid)\n\
);\n\
CREATE INDEX graphs_items_1 ON graphs_items (itemid);\n\
CREATE INDEX graphs_items_2 ON graphs_items (graphid);\n\
CREATE TABLE graph_theme (\n\
graphthemeid bigint  NOT NULL,\n\
theme varchar(64) DEFAULT '' NOT NULL,\n\
backgroundcolor varchar(6) DEFAULT '' NOT NULL,\n\
graphcolor varchar(6) DEFAULT '' NOT NULL,\n\
gridcolor varchar(6) DEFAULT '' NOT NULL,\n\
maingridcolor varchar(6) DEFAULT '' NOT NULL,\n\
gridbordercolor varchar(6) DEFAULT '' NOT NULL,\n\
textcolor varchar(6) DEFAULT '' NOT NULL,\n\
highlightcolor varchar(6) DEFAULT '' NOT NULL,\n\
leftpercentilecolor varchar(6) DEFAULT '' NOT NULL,\n\
rightpercentilecolor varchar(6) DEFAULT '' NOT NULL,\n\
nonworktimecolor varchar(6) DEFAULT '' NOT NULL,\n\
colorpalette varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (graphthemeid)\n\
);\n\
CREATE UNIQUE INDEX graph_theme_1 ON graph_theme (theme);\n\
CREATE TABLE globalmacro (\n\
globalmacroid bigint  NOT NULL,\n\
macro varchar(255) DEFAULT '' NOT NULL,\n\
value varchar(2048) DEFAULT '' NOT NULL,\n\
description text DEFAULT '' NOT NULL,\n\
type integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (globalmacroid)\n\
);\n\
CREATE UNIQUE INDEX globalmacro_1 ON globalmacro (macro);\n\
CREATE TABLE hostmacro (\n\
hostmacroid bigint  NOT NULL,\n\
hostid bigint  NOT NULL REFERENCES hosts (hostid) ON DELETE CASCADE,\n\
macro varchar(255) DEFAULT '' NOT NULL,\n\
value varchar(2048) DEFAULT '' NOT NULL,\n\
description text DEFAULT '' NOT NULL,\n\
type integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (hostmacroid)\n\
);\n\
CREATE UNIQUE INDEX hostmacro_1 ON hostmacro (hostid,macro);\n\
CREATE TABLE hosts_groups (\n\
hostgroupid bigint  NOT NULL,\n\
hostid bigint  NOT NULL REFERENCES hosts (hostid) ON DELETE CASCADE,\n\
groupid bigint  NOT NULL REFERENCES hstgrp (groupid) ON DELETE CASCADE,\n\
PRIMARY KEY (hostgroupid)\n\
);\n\
CREATE UNIQUE INDEX hosts_groups_1 ON hosts_groups (hostid,groupid);\n\
CREATE INDEX hosts_groups_2 ON hosts_groups (groupid);\n\
CREATE TABLE hosts_templates (\n\
hosttemplateid bigint  NOT NULL,\n\
hostid bigint  NOT NULL REFERENCES hosts (hostid) ON DELETE CASCADE,\n\
templateid bigint  NOT NULL REFERENCES hosts (hostid) ON DELETE CASCADE,\n\
link_type integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (hosttemplateid)\n\
);\n\
CREATE UNIQUE INDEX hosts_templates_1 ON hosts_templates (hostid,templateid);\n\
CREATE INDEX hosts_templates_2 ON hosts_templates (templateid);\n\
CREATE TABLE valuemap_mapping (\n\
valuemap_mappingid bigint  NOT NULL,\n\
valuemapid bigint  NOT NULL REFERENCES valuemap (valuemapid) ON DELETE CASCADE,\n\
value varchar(64) DEFAULT '' NOT NULL,\n\
newvalue varchar(64) DEFAULT '' NOT NULL,\n\
type integer DEFAULT '0' NOT NULL,\n\
sortorder integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (valuemap_mappingid)\n\
);\n\
CREATE UNIQUE INDEX valuemap_mapping_1 ON valuemap_mapping (valuemapid,value,type);\n\
CREATE TABLE media (\n\
mediaid bigint  NOT NULL,\n\
userid bigint  NOT NULL REFERENCES users (userid) ON DELETE CASCADE,\n\
mediatypeid bigint  NOT NULL REFERENCES media_type (mediatypeid) ON DELETE CASCADE,\n\
sendto varchar(1024) DEFAULT '' NOT NULL,\n\
active integer DEFAULT '0' NOT NULL,\n\
severity integer DEFAULT '63' NOT NULL,\n\
period varchar(1024) DEFAULT '1-7,00:00-24:00' NOT NULL,\n\
PRIMARY KEY (mediaid)\n\
);\n\
CREATE INDEX media_1 ON media (userid);\n\
CREATE INDEX media_2 ON media (mediatypeid);\n\
CREATE TABLE rights (\n\
rightid bigint  NOT NULL,\n\
groupid bigint  NOT NULL REFERENCES usrgrp (usrgrpid) ON DELETE CASCADE,\n\
permission integer DEFAULT '0' NOT NULL,\n\
id bigint  NOT NULL REFERENCES hstgrp (groupid) ON DELETE CASCADE,\n\
PRIMARY KEY (rightid)\n\
);\n\
CREATE INDEX rights_1 ON rights (groupid);\n\
CREATE INDEX rights_2 ON rights (id);\n\
CREATE TABLE services (\n\
serviceid bigint  NOT NULL,\n\
name varchar(128) DEFAULT '' NOT NULL,\n\
status integer DEFAULT '-1' NOT NULL,\n\
algorithm integer DEFAULT '0' NOT NULL,\n\
sortorder integer DEFAULT '0' NOT NULL,\n\
weight integer DEFAULT '0' NOT NULL,\n\
propagation_rule integer DEFAULT '0' NOT NULL,\n\
propagation_value integer DEFAULT '0' NOT NULL,\n\
description text DEFAULT '' NOT NULL,\n\
uuid varchar(32) DEFAULT '' NOT NULL,\n\
created_at integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (serviceid)\n\
);\n\
CREATE TABLE services_links (\n\
linkid bigint  NOT NULL,\n\
serviceupid bigint  NOT NULL REFERENCES services (serviceid) ON DELETE CASCADE,\n\
servicedownid bigint  NOT NULL REFERENCES services (serviceid) ON DELETE CASCADE,\n\
PRIMARY KEY (linkid)\n\
);\n\
CREATE INDEX services_links_1 ON services_links (servicedownid);\n\
CREATE UNIQUE INDEX services_links_2 ON services_links (serviceupid,servicedownid);\n\
CREATE TABLE icon_map (\n\
iconmapid bigint  NOT NULL,\n\
name varchar(64) DEFAULT '' NOT NULL,\n\
default_iconid bigint  NOT NULL REFERENCES images (imageid),\n\
PRIMARY KEY (iconmapid)\n\
);\n\
CREATE UNIQUE INDEX icon_map_1 ON icon_map (name);\n\
CREATE INDEX icon_map_2 ON icon_map (default_iconid);\n\
CREATE TABLE icon_mapping (\n\
iconmappingid bigint  NOT NULL,\n\
iconmapid bigint  NOT NULL REFERENCES icon_map (iconmapid) ON DELETE CASCADE,\n\
iconid bigint  NOT NULL REFERENCES images (imageid),\n\
inventory_link integer DEFAULT '0' NOT NULL,\n\
expression varchar(64) DEFAULT '' NOT NULL,\n\
sortorder integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (iconmappingid)\n\
);\n\
CREATE INDEX icon_mapping_1 ON icon_mapping (iconmapid);\n\
CREATE INDEX icon_mapping_2 ON icon_mapping (iconid);\n\
CREATE TABLE sysmaps (\n\
sysmapid bigint  NOT NULL,\n\
name varchar(128) DEFAULT '' NOT NULL,\n\
width integer DEFAULT '600' NOT NULL,\n\
height integer DEFAULT '400' NOT NULL,\n\
backgroundid bigint  NULL REFERENCES images (imageid),\n\
label_type integer DEFAULT '2' NOT NULL,\n\
label_location integer DEFAULT '0' NOT NULL,\n\
highlight integer DEFAULT '1' NOT NULL,\n\
expandproblem integer DEFAULT '1' NOT NULL,\n\
markelements integer DEFAULT '0' NOT NULL,\n\
show_unack integer DEFAULT '0' NOT NULL,\n\
grid_size integer DEFAULT '50' NOT NULL,\n\
grid_show integer DEFAULT '1' NOT NULL,\n\
grid_align integer DEFAULT '1' NOT NULL,\n\
label_format integer DEFAULT '0' NOT NULL,\n\
label_type_host integer DEFAULT '2' NOT NULL,\n\
label_type_hostgroup integer DEFAULT '2' NOT NULL,\n\
label_type_trigger integer DEFAULT '2' NOT NULL,\n\
label_type_map integer DEFAULT '2' NOT NULL,\n\
label_type_image integer DEFAULT '2' NOT NULL,\n\
label_string_host varchar(255) DEFAULT '' NOT NULL,\n\
label_string_hostgroup varchar(255) DEFAULT '' NOT NULL,\n\
label_string_trigger varchar(255) DEFAULT '' NOT NULL,\n\
label_string_map varchar(255) DEFAULT '' NOT NULL,\n\
label_string_image varchar(255) DEFAULT '' NOT NULL,\n\
iconmapid bigint  NULL REFERENCES icon_map (iconmapid),\n\
expand_macros integer DEFAULT '0' NOT NULL,\n\
severity_min integer DEFAULT '0' NOT NULL,\n\
userid bigint  NOT NULL REFERENCES users (userid),\n\
private integer DEFAULT '1' NOT NULL,\n\
show_suppressed integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (sysmapid)\n\
);\n\
CREATE UNIQUE INDEX sysmaps_1 ON sysmaps (name);\n\
CREATE INDEX sysmaps_2 ON sysmaps (backgroundid);\n\
CREATE INDEX sysmaps_3 ON sysmaps (iconmapid);\n\
CREATE TABLE sysmaps_elements (\n\
selementid bigint  NOT NULL,\n\
sysmapid bigint  NOT NULL REFERENCES sysmaps (sysmapid) ON DELETE CASCADE,\n\
elementid bigint DEFAULT '0' NOT NULL,\n\
elementtype integer DEFAULT '0' NOT NULL,\n\
iconid_off bigint  NULL REFERENCES images (imageid),\n\
iconid_on bigint  NULL REFERENCES images (imageid),\n\
label varchar(2048) DEFAULT '' NOT NULL,\n\
label_location integer DEFAULT '-1' NOT NULL,\n\
x integer DEFAULT '0' NOT NULL,\n\
y integer DEFAULT '0' NOT NULL,\n\
iconid_disabled bigint  NULL REFERENCES images (imageid),\n\
iconid_maintenance bigint  NULL REFERENCES images (imageid),\n\
elementsubtype integer DEFAULT '0' NOT NULL,\n\
areatype integer DEFAULT '0' NOT NULL,\n\
width integer DEFAULT '200' NOT NULL,\n\
height integer DEFAULT '200' NOT NULL,\n\
viewtype integer DEFAULT '0' NOT NULL,\n\
use_iconmap integer DEFAULT '1' NOT NULL,\n\
evaltype integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (selementid)\n\
);\n\
CREATE INDEX sysmaps_elements_1 ON sysmaps_elements (sysmapid);\n\
CREATE INDEX sysmaps_elements_2 ON sysmaps_elements (iconid_off);\n\
CREATE INDEX sysmaps_elements_3 ON sysmaps_elements (iconid_on);\n\
CREATE INDEX sysmaps_elements_4 ON sysmaps_elements (iconid_disabled);\n\
CREATE INDEX sysmaps_elements_5 ON sysmaps_elements (iconid_maintenance);\n\
CREATE TABLE sysmaps_links (\n\
linkid bigint  NOT NULL,\n\
sysmapid bigint  NOT NULL REFERENCES sysmaps (sysmapid) ON DELETE CASCADE,\n\
selementid1 bigint  NOT NULL REFERENCES sysmaps_elements (selementid) ON DELETE CASCADE,\n\
selementid2 bigint  NOT NULL REFERENCES sysmaps_elements (selementid) ON DELETE CASCADE,\n\
drawtype integer DEFAULT '0' NOT NULL,\n\
color varchar(6) DEFAULT '000000' NOT NULL,\n\
label varchar(2048) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (linkid)\n\
);\n\
CREATE INDEX sysmaps_links_1 ON sysmaps_links (sysmapid);\n\
CREATE INDEX sysmaps_links_2 ON sysmaps_links (selementid1);\n\
CREATE INDEX sysmaps_links_3 ON sysmaps_links (selementid2);\n\
CREATE TABLE sysmaps_link_triggers (\n\
linktriggerid bigint  NOT NULL,\n\
linkid bigint  NOT NULL REFERENCES sysmaps_links (linkid) ON DELETE CASCADE,\n\
triggerid bigint  NOT NULL REFERENCES triggers (triggerid) ON DELETE CASCADE,\n\
drawtype integer DEFAULT '0' NOT NULL,\n\
color varchar(6) DEFAULT '000000' NOT NULL,\n\
PRIMARY KEY (linktriggerid)\n\
);\n\
CREATE UNIQUE INDEX sysmaps_link_triggers_1 ON sysmaps_link_triggers (linkid,triggerid);\n\
CREATE INDEX sysmaps_link_triggers_2 ON sysmaps_link_triggers (triggerid);\n\
CREATE TABLE sysmap_element_url (\n\
sysmapelementurlid bigint  NOT NULL,\n\
selementid bigint  NOT NULL REFERENCES sysmaps_elements (selementid) ON DELETE CASCADE,\n\
name varchar(255)  NOT NULL,\n\
url varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (sysmapelementurlid)\n\
);\n\
CREATE UNIQUE INDEX sysmap_element_url_1 ON sysmap_element_url (selementid,name);\n\
CREATE TABLE sysmap_url (\n\
sysmapurlid bigint  NOT NULL,\n\
sysmapid bigint  NOT NULL REFERENCES sysmaps (sysmapid) ON DELETE CASCADE,\n\
name varchar(255)  NOT NULL,\n\
url varchar(255) DEFAULT '' NOT NULL,\n\
elementtype integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (sysmapurlid)\n\
);\n\
CREATE UNIQUE INDEX sysmap_url_1 ON sysmap_url (sysmapid,name);\n\
CREATE TABLE sysmap_user (\n\
sysmapuserid bigint  NOT NULL,\n\
sysmapid bigint  NOT NULL REFERENCES sysmaps (sysmapid) ON DELETE CASCADE,\n\
userid bigint  NOT NULL REFERENCES users (userid) ON DELETE CASCADE,\n\
permission integer DEFAULT '2' NOT NULL,\n\
PRIMARY KEY (sysmapuserid)\n\
);\n\
CREATE UNIQUE INDEX sysmap_user_1 ON sysmap_user (sysmapid,userid);\n\
CREATE TABLE sysmap_usrgrp (\n\
sysmapusrgrpid bigint  NOT NULL,\n\
sysmapid bigint  NOT NULL REFERENCES sysmaps (sysmapid) ON DELETE CASCADE,\n\
usrgrpid bigint  NOT NULL REFERENCES usrgrp (usrgrpid) ON DELETE CASCADE,\n\
permission integer DEFAULT '2' NOT NULL,\n\
PRIMARY KEY (sysmapusrgrpid)\n\
);\n\
CREATE UNIQUE INDEX sysmap_usrgrp_1 ON sysmap_usrgrp (sysmapid,usrgrpid);\n\
CREATE TABLE maintenances_hosts (\n\
maintenance_hostid bigint  NOT NULL,\n\
maintenanceid bigint  NOT NULL REFERENCES maintenances (maintenanceid) ON DELETE CASCADE,\n\
hostid bigint  NOT NULL REFERENCES hosts (hostid) ON DELETE CASCADE,\n\
PRIMARY KEY (maintenance_hostid)\n\
);\n\
CREATE UNIQUE INDEX maintenances_hosts_1 ON maintenances_hosts (maintenanceid,hostid);\n\
CREATE INDEX maintenances_hosts_2 ON maintenances_hosts (hostid);\n\
CREATE TABLE maintenances_groups (\n\
maintenance_groupid bigint  NOT NULL,\n\
maintenanceid bigint  NOT NULL REFERENCES maintenances (maintenanceid) ON DELETE CASCADE,\n\
groupid bigint  NOT NULL REFERENCES hstgrp (groupid) ON DELETE CASCADE,\n\
PRIMARY KEY (maintenance_groupid)\n\
);\n\
CREATE UNIQUE INDEX maintenances_groups_1 ON maintenances_groups (maintenanceid,groupid);\n\
CREATE INDEX maintenances_groups_2 ON maintenances_groups (groupid);\n\
CREATE TABLE timeperiods (\n\
timeperiodid bigint  NOT NULL,\n\
timeperiod_type integer DEFAULT '0' NOT NULL,\n\
every integer DEFAULT '1' NOT NULL,\n\
month integer DEFAULT '0' NOT NULL,\n\
dayofweek integer DEFAULT '0' NOT NULL,\n\
day integer DEFAULT '0' NOT NULL,\n\
start_time integer DEFAULT '0' NOT NULL,\n\
period integer DEFAULT '0' NOT NULL,\n\
start_date integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (timeperiodid)\n\
);\n\
CREATE TABLE maintenances_windows (\n\
maintenance_timeperiodid bigint  NOT NULL,\n\
maintenanceid bigint  NOT NULL REFERENCES maintenances (maintenanceid) ON DELETE CASCADE,\n\
timeperiodid bigint  NOT NULL REFERENCES timeperiods (timeperiodid) ON DELETE CASCADE,\n\
PRIMARY KEY (maintenance_timeperiodid)\n\
);\n\
CREATE UNIQUE INDEX maintenances_windows_1 ON maintenances_windows (maintenanceid,timeperiodid);\n\
CREATE INDEX maintenances_windows_2 ON maintenances_windows (timeperiodid);\n\
CREATE TABLE regexps (\n\
regexpid bigint  NOT NULL,\n\
name varchar(128) DEFAULT '' NOT NULL,\n\
test_string text DEFAULT '' NOT NULL,\n\
PRIMARY KEY (regexpid)\n\
);\n\
CREATE UNIQUE INDEX regexps_1 ON regexps (name);\n\
CREATE TABLE expressions (\n\
expressionid bigint  NOT NULL,\n\
regexpid bigint  NOT NULL REFERENCES regexps (regexpid) ON DELETE CASCADE,\n\
expression varchar(255) DEFAULT '' NOT NULL,\n\
expression_type integer DEFAULT '0' NOT NULL,\n\
exp_delimiter varchar(1) DEFAULT '' NOT NULL,\n\
case_sensitive integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (expressionid)\n\
);\n\
CREATE INDEX expressions_1 ON expressions (regexpid);\n\
CREATE TABLE ids (\n\
table_name varchar(64) DEFAULT '' NOT NULL,\n\
field_name varchar(64) DEFAULT '' NOT NULL,\n\
nextid bigint  NOT NULL,\n\
PRIMARY KEY (table_name,field_name)\n\
);\n\
CREATE TABLE alerts (\n\
alertid bigint  NOT NULL,\n\
actionid bigint  NOT NULL REFERENCES actions (actionid) ON DELETE CASCADE,\n\
eventid bigint  NOT NULL REFERENCES events (eventid) ON DELETE CASCADE,\n\
userid bigint  NULL REFERENCES users (userid) ON DELETE CASCADE,\n\
clock integer DEFAULT '0' NOT NULL,\n\
mediatypeid bigint  NULL REFERENCES media_type (mediatypeid) ON DELETE CASCADE,\n\
sendto varchar(1024) DEFAULT '' NOT NULL,\n\
subject varchar(255) DEFAULT '' NOT NULL,\n\
message text DEFAULT '' NOT NULL,\n\
status integer DEFAULT '0' NOT NULL,\n\
retries integer DEFAULT '0' NOT NULL,\n\
error varchar(2048) DEFAULT '' NOT NULL,\n\
esc_step integer DEFAULT '0' NOT NULL,\n\
alerttype integer DEFAULT '0' NOT NULL,\n\
p_eventid bigint  NULL REFERENCES events (eventid) ON DELETE CASCADE,\n\
acknowledgeid bigint  NULL REFERENCES acknowledges (acknowledgeid) ON DELETE CASCADE,\n\
parameters text DEFAULT '{}' NOT NULL,\n\
PRIMARY KEY (alertid)\n\
);\n\
CREATE INDEX alerts_1 ON alerts (actionid);\n\
CREATE INDEX alerts_2 ON alerts (clock);\n\
CREATE INDEX alerts_3 ON alerts (eventid);\n\
CREATE INDEX alerts_4 ON alerts (status);\n\
CREATE INDEX alerts_5 ON alerts (mediatypeid);\n\
CREATE INDEX alerts_6 ON alerts (userid);\n\
CREATE INDEX alerts_7 ON alerts (p_eventid);\n\
CREATE INDEX alerts_8 ON alerts (acknowledgeid);\n\
CREATE TABLE history (\n\
itemid bigint  NOT NULL,\n\
clock integer DEFAULT '0' NOT NULL,\n\
value DOUBLE PRECISION DEFAULT '0.0000' NOT NULL,\n\
ns integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (itemid,clock,ns)\n\
);\n\
CREATE TABLE history_uint (\n\
itemid bigint  NOT NULL,\n\
clock integer DEFAULT '0' NOT NULL,\n\
value bigint DEFAULT '0' NOT NULL,\n\
ns integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (itemid,clock,ns)\n\
);\n\
CREATE TABLE history_str (\n\
itemid bigint  NOT NULL,\n\
clock integer DEFAULT '0' NOT NULL,\n\
value varchar(255) DEFAULT '' NOT NULL,\n\
ns integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (itemid,clock,ns)\n\
);\n\
CREATE TABLE history_log (\n\
itemid bigint  NOT NULL,\n\
clock integer DEFAULT '0' NOT NULL,\n\
timestamp integer DEFAULT '0' NOT NULL,\n\
source varchar(64) DEFAULT '' NOT NULL,\n\
severity integer DEFAULT '0' NOT NULL,\n\
value text DEFAULT '' NOT NULL,\n\
logeventid integer DEFAULT '0' NOT NULL,\n\
ns integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (itemid,clock,ns)\n\
);\n\
CREATE TABLE history_text (\n\
itemid bigint  NOT NULL,\n\
clock integer DEFAULT '0' NOT NULL,\n\
value text DEFAULT '' NOT NULL,\n\
ns integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (itemid,clock,ns)\n\
);\n\
CREATE TABLE proxy_history (\n\
id integer  NOT NULL PRIMARY KEY AUTOINCREMENT,\n\
itemid bigint  NOT NULL,\n\
clock integer DEFAULT '0' NOT NULL,\n\
timestamp integer DEFAULT '0' NOT NULL,\n\
source varchar(64) DEFAULT '' NOT NULL,\n\
severity integer DEFAULT '0' NOT NULL,\n\
value text DEFAULT '' NOT NULL,\n\
logeventid integer DEFAULT '0' NOT NULL,\n\
ns integer DEFAULT '0' NOT NULL,\n\
state integer DEFAULT '0' NOT NULL,\n\
lastlogsize bigint DEFAULT '0' NOT NULL,\n\
mtime integer DEFAULT '0' NOT NULL,\n\
flags integer DEFAULT '0' NOT NULL,\n\
write_clock integer DEFAULT '0' NOT NULL\n\
);\n\
CREATE INDEX proxy_history_1 ON proxy_history (clock);\n\
CREATE TABLE proxy_dhistory (\n\
id integer  NOT NULL PRIMARY KEY AUTOINCREMENT,\n\
clock integer DEFAULT '0' NOT NULL,\n\
druleid bigint  NOT NULL,\n\
ip varchar(39) DEFAULT '' NOT NULL,\n\
port integer DEFAULT '0' NOT NULL,\n\
value varchar(255) DEFAULT '' NOT NULL,\n\
status integer DEFAULT '0' NOT NULL,\n\
dcheckid bigint  NULL,\n\
dns varchar(255) DEFAULT '' NOT NULL\n\
);\n\
CREATE INDEX proxy_dhistory_1 ON proxy_dhistory (clock);\n\
CREATE INDEX proxy_dhistory_2 ON proxy_dhistory (druleid);\n\
CREATE TABLE events (\n\
eventid bigint  NOT NULL,\n\
source integer DEFAULT '0' NOT NULL,\n\
object integer DEFAULT '0' NOT NULL,\n\
objectid bigint DEFAULT '0' NOT NULL,\n\
clock integer DEFAULT '0' NOT NULL,\n\
value integer DEFAULT '0' NOT NULL,\n\
acknowledged integer DEFAULT '0' NOT NULL,\n\
ns integer DEFAULT '0' NOT NULL,\n\
name varchar(2048) DEFAULT '' NOT NULL,\n\
severity integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (eventid)\n\
);\n\
CREATE INDEX events_1 ON events (source,object,objectid,clock);\n\
CREATE INDEX events_2 ON events (source,object,clock);\n\
CREATE TABLE trends (\n\
itemid bigint  NOT NULL,\n\
clock integer DEFAULT '0' NOT NULL,\n\
num integer DEFAULT '0' NOT NULL,\n\
value_min DOUBLE PRECISION DEFAULT '0.0000' NOT NULL,\n\
value_avg DOUBLE PRECISION DEFAULT '0.0000' NOT NULL,\n\
value_max DOUBLE PRECISION DEFAULT '0.0000' NOT NULL,\n\
PRIMARY KEY (itemid,clock)\n\
);\n\
CREATE TABLE trends_uint (\n\
itemid bigint  NOT NULL,\n\
clock integer DEFAULT '0' NOT NULL,\n\
num integer DEFAULT '0' NOT NULL,\n\
value_min bigint DEFAULT '0' NOT NULL,\n\
value_avg bigint DEFAULT '0' NOT NULL,\n\
value_max bigint DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (itemid,clock)\n\
);\n\
CREATE TABLE acknowledges (\n\
acknowledgeid bigint  NOT NULL,\n\
userid bigint  NOT NULL REFERENCES users (userid) ON DELETE CASCADE,\n\
eventid bigint  NOT NULL REFERENCES events (eventid) ON DELETE CASCADE,\n\
clock integer DEFAULT '0' NOT NULL,\n\
message varchar(2048) DEFAULT '' NOT NULL,\n\
action integer DEFAULT '0' NOT NULL,\n\
old_severity integer DEFAULT '0' NOT NULL,\n\
new_severity integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (acknowledgeid)\n\
);\n\
CREATE INDEX acknowledges_1 ON acknowledges (userid);\n\
CREATE INDEX acknowledges_2 ON acknowledges (eventid);\n\
CREATE INDEX acknowledges_3 ON acknowledges (clock);\n\
CREATE TABLE auditlog (\n\
auditid varchar(25)  NOT NULL,\n\
userid bigint  NULL,\n\
username varchar(100) DEFAULT '' NOT NULL,\n\
clock integer DEFAULT '0' NOT NULL,\n\
ip varchar(39) DEFAULT '' NOT NULL,\n\
action integer DEFAULT '0' NOT NULL,\n\
resourcetype integer DEFAULT '0' NOT NULL,\n\
resourceid bigint  NULL,\n\
resource_cuid varchar(25)  NULL,\n\
resourcename varchar(255) DEFAULT '' NOT NULL,\n\
recordsetid varchar(25)  NOT NULL,\n\
details text DEFAULT '' NOT NULL,\n\
PRIMARY KEY (auditid)\n\
);\n\
CREATE INDEX auditlog_1 ON auditlog (userid,clock);\n\
CREATE INDEX auditlog_2 ON auditlog (clock);\n\
CREATE INDEX auditlog_3 ON auditlog (resourcetype,resourceid);\n\
CREATE TABLE service_alarms (\n\
servicealarmid bigint  NOT NULL,\n\
serviceid bigint  NOT NULL REFERENCES services (serviceid) ON DELETE CASCADE,\n\
clock integer DEFAULT '0' NOT NULL,\n\
value integer DEFAULT '-1' NOT NULL,\n\
PRIMARY KEY (servicealarmid)\n\
);\n\
CREATE INDEX service_alarms_1 ON service_alarms (serviceid,clock);\n\
CREATE INDEX service_alarms_2 ON service_alarms (clock);\n\
CREATE TABLE autoreg_host (\n\
autoreg_hostid bigint  NOT NULL,\n\
proxy_hostid bigint  NULL REFERENCES hosts (hostid) ON DELETE CASCADE,\n\
host varchar(128) DEFAULT '' NOT NULL,\n\
listen_ip varchar(39) DEFAULT '' NOT NULL,\n\
listen_port integer DEFAULT '0' NOT NULL,\n\
listen_dns varchar(255) DEFAULT '' NOT NULL,\n\
host_metadata varchar(255) DEFAULT '' NOT NULL,\n\
flags integer DEFAULT '0' NOT NULL,\n\
tls_accepted integer DEFAULT '1' NOT NULL,\n\
PRIMARY KEY (autoreg_hostid)\n\
);\n\
CREATE INDEX autoreg_host_1 ON autoreg_host (host);\n\
CREATE INDEX autoreg_host_2 ON autoreg_host (proxy_hostid);\n\
CREATE TABLE proxy_autoreg_host (\n\
id integer  NOT NULL PRIMARY KEY AUTOINCREMENT,\n\
clock integer DEFAULT '0' NOT NULL,\n\
host varchar(128) DEFAULT '' NOT NULL,\n\
listen_ip varchar(39) DEFAULT '' NOT NULL,\n\
listen_port integer DEFAULT '0' NOT NULL,\n\
listen_dns varchar(255) DEFAULT '' NOT NULL,\n\
host_metadata varchar(255) DEFAULT '' NOT NULL,\n\
flags integer DEFAULT '0' NOT NULL,\n\
tls_accepted integer DEFAULT '1' NOT NULL\n\
);\n\
CREATE INDEX proxy_autoreg_host_1 ON proxy_autoreg_host (clock);\n\
CREATE TABLE dhosts (\n\
dhostid bigint  NOT NULL,\n\
druleid bigint  NOT NULL REFERENCES drules (druleid) ON DELETE CASCADE,\n\
status integer DEFAULT '0' NOT NULL,\n\
lastup integer DEFAULT '0' NOT NULL,\n\
lastdown integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (dhostid)\n\
);\n\
CREATE INDEX dhosts_1 ON dhosts (druleid);\n\
CREATE TABLE dservices (\n\
dserviceid bigint  NOT NULL,\n\
dhostid bigint  NOT NULL REFERENCES dhosts (dhostid) ON DELETE CASCADE,\n\
value varchar(255) DEFAULT '' NOT NULL,\n\
port integer DEFAULT '0' NOT NULL,\n\
status integer DEFAULT '0' NOT NULL,\n\
lastup integer DEFAULT '0' NOT NULL,\n\
lastdown integer DEFAULT '0' NOT NULL,\n\
dcheckid bigint  NOT NULL REFERENCES dchecks (dcheckid) ON DELETE CASCADE,\n\
ip varchar(39) DEFAULT '' NOT NULL,\n\
dns varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (dserviceid)\n\
);\n\
CREATE UNIQUE INDEX dservices_1 ON dservices (dcheckid,ip,port);\n\
CREATE INDEX dservices_2 ON dservices (dhostid);\n\
CREATE TABLE escalations (\n\
escalationid bigint  NOT NULL,\n\
actionid bigint  NOT NULL,\n\
triggerid bigint  NULL,\n\
eventid bigint  NULL,\n\
r_eventid bigint  NULL,\n\
nextcheck integer DEFAULT '0' NOT NULL,\n\
esc_step integer DEFAULT '0' NOT NULL,\n\
status integer DEFAULT '0' NOT NULL,\n\
itemid bigint  NULL,\n\
acknowledgeid bigint  NULL,\n\
servicealarmid bigint  NULL,\n\
serviceid bigint  NULL,\n\
PRIMARY KEY (escalationid)\n\
);\n\
CREATE UNIQUE INDEX escalations_1 ON escalations (triggerid,itemid,serviceid,escalationid);\n\
CREATE INDEX escalations_2 ON escalations (eventid);\n\
CREATE INDEX escalations_3 ON escalations (nextcheck);\n\
CREATE TABLE globalvars (\n\
globalvarid bigint  NOT NULL,\n\
snmp_lastsize bigint DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (globalvarid)\n\
);\n\
CREATE TABLE graph_discovery (\n\
graphid bigint  NOT NULL REFERENCES graphs (graphid) ON DELETE CASCADE,\n\
parent_graphid bigint  NOT NULL REFERENCES graphs (graphid),\n\
lastcheck integer DEFAULT '0' NOT NULL,\n\
ts_delete integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (graphid)\n\
);\n\
CREATE INDEX graph_discovery_1 ON graph_discovery (parent_graphid);\n\
CREATE TABLE host_inventory (\n\
hostid bigint  NOT NULL REFERENCES hosts (hostid) ON DELETE CASCADE,\n\
inventory_mode integer DEFAULT '0' NOT NULL,\n\
type varchar(64) DEFAULT '' NOT NULL,\n\
type_full varchar(64) DEFAULT '' NOT NULL,\n\
name varchar(128) DEFAULT '' NOT NULL,\n\
alias varchar(128) DEFAULT '' NOT NULL,\n\
os varchar(128) DEFAULT '' NOT NULL,\n\
os_full varchar(255) DEFAULT '' NOT NULL,\n\
os_short varchar(128) DEFAULT '' NOT NULL,\n\
serialno_a varchar(64) DEFAULT '' NOT NULL,\n\
serialno_b varchar(64) DEFAULT '' NOT NULL,\n\
tag varchar(64) DEFAULT '' NOT NULL,\n\
asset_tag varchar(64) DEFAULT '' NOT NULL,\n\
macaddress_a varchar(64) DEFAULT '' NOT NULL,\n\
macaddress_b varchar(64) DEFAULT '' NOT NULL,\n\
hardware varchar(255) DEFAULT '' NOT NULL,\n\
hardware_full text DEFAULT '' NOT NULL,\n\
software varchar(255) DEFAULT '' NOT NULL,\n\
software_full text DEFAULT '' NOT NULL,\n\
software_app_a varchar(64) DEFAULT '' NOT NULL,\n\
software_app_b varchar(64) DEFAULT '' NOT NULL,\n\
software_app_c varchar(64) DEFAULT '' NOT NULL,\n\
software_app_d varchar(64) DEFAULT '' NOT NULL,\n\
software_app_e varchar(64) DEFAULT '' NOT NULL,\n\
contact text DEFAULT '' NOT NULL,\n\
location text DEFAULT '' NOT NULL,\n\
location_lat varchar(16) DEFAULT '' NOT NULL,\n\
location_lon varchar(16) DEFAULT '' NOT NULL,\n\
notes text DEFAULT '' NOT NULL,\n\
chassis varchar(64) DEFAULT '' NOT NULL,\n\
model varchar(64) DEFAULT '' NOT NULL,\n\
hw_arch varchar(32) DEFAULT '' NOT NULL,\n\
vendor varchar(64) DEFAULT '' NOT NULL,\n\
contract_number varchar(64) DEFAULT '' NOT NULL,\n\
installer_name varchar(64) DEFAULT '' NOT NULL,\n\
deployment_status varchar(64) DEFAULT '' NOT NULL,\n\
url_a varchar(255) DEFAULT '' NOT NULL,\n\
url_b varchar(255) DEFAULT '' NOT NULL,\n\
url_c varchar(255) DEFAULT '' NOT NULL,\n\
host_networks text DEFAULT '' NOT NULL,\n\
host_netmask varchar(39) DEFAULT '' NOT NULL,\n\
host_router varchar(39) DEFAULT '' NOT NULL,\n\
oob_ip varchar(39) DEFAULT '' NOT NULL,\n\
oob_netmask varchar(39) DEFAULT '' NOT NULL,\n\
oob_router varchar(39) DEFAULT '' NOT NULL,\n\
date_hw_purchase varchar(64) DEFAULT '' NOT NULL,\n\
date_hw_install varchar(64) DEFAULT '' NOT NULL,\n\
date_hw_expiry varchar(64) DEFAULT '' NOT NULL,\n\
date_hw_decomm varchar(64) DEFAULT '' NOT NULL,\n\
site_address_a varchar(128) DEFAULT '' NOT NULL,\n\
site_address_b varchar(128) DEFAULT '' NOT NULL,\n\
site_address_c varchar(128) DEFAULT '' NOT NULL,\n\
site_city varchar(128) DEFAULT '' NOT NULL,\n\
site_state varchar(64) DEFAULT '' NOT NULL,\n\
site_country varchar(64) DEFAULT '' NOT NULL,\n\
site_zip varchar(64) DEFAULT '' NOT NULL,\n\
site_rack varchar(128) DEFAULT '' NOT NULL,\n\
site_notes text DEFAULT '' NOT NULL,\n\
poc_1_name varchar(128) DEFAULT '' NOT NULL,\n\
poc_1_email varchar(128) DEFAULT '' NOT NULL,\n\
poc_1_phone_a varchar(64) DEFAULT '' NOT NULL,\n\
poc_1_phone_b varchar(64) DEFAULT '' NOT NULL,\n\
poc_1_cell varchar(64) DEFAULT '' NOT NULL,\n\
poc_1_screen varchar(64) DEFAULT '' NOT NULL,\n\
poc_1_notes text DEFAULT '' NOT NULL,\n\
poc_2_name varchar(128) DEFAULT '' NOT NULL,\n\
poc_2_email varchar(128) DEFAULT '' NOT NULL,\n\
poc_2_phone_a varchar(64) DEFAULT '' NOT NULL,\n\
poc_2_phone_b varchar(64) DEFAULT '' NOT NULL,\n\
poc_2_cell varchar(64) DEFAULT '' NOT NULL,\n\
poc_2_screen varchar(64) DEFAULT '' NOT NULL,\n\
poc_2_notes text DEFAULT '' NOT NULL,\n\
PRIMARY KEY (hostid)\n\
);\n\
CREATE TABLE housekeeper (\n\
housekeeperid bigint  NOT NULL,\n\
tablename varchar(64) DEFAULT '' NOT NULL,\n\
field varchar(64) DEFAULT '' NOT NULL,\n\
value bigint  NOT NULL,\n\
PRIMARY KEY (housekeeperid)\n\
);\n\
CREATE TABLE images (\n\
imageid bigint  NOT NULL,\n\
imagetype integer DEFAULT '0' NOT NULL,\n\
name varchar(64) DEFAULT '0' NOT NULL,\n\
image longblob DEFAULT '' NOT NULL,\n\
PRIMARY KEY (imageid)\n\
);\n\
CREATE UNIQUE INDEX images_1 ON images (name);\n\
CREATE TABLE item_discovery (\n\
itemdiscoveryid bigint  NOT NULL,\n\
itemid bigint  NOT NULL REFERENCES items (itemid) ON DELETE CASCADE,\n\
parent_itemid bigint  NOT NULL REFERENCES items (itemid) ON DELETE CASCADE,\n\
key_ varchar(2048) DEFAULT '' NOT NULL,\n\
lastcheck integer DEFAULT '0' NOT NULL,\n\
ts_delete integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (itemdiscoveryid)\n\
);\n\
CREATE UNIQUE INDEX item_discovery_1 ON item_discovery (itemid,parent_itemid);\n\
CREATE INDEX item_discovery_2 ON item_discovery (parent_itemid);\n\
CREATE TABLE host_discovery (\n\
hostid bigint  NOT NULL REFERENCES hosts (hostid) ON DELETE CASCADE,\n\
parent_hostid bigint  NULL REFERENCES hosts (hostid),\n\
parent_itemid bigint  NULL REFERENCES items (itemid),\n\
host varchar(128) DEFAULT '' NOT NULL,\n\
lastcheck integer DEFAULT '0' NOT NULL,\n\
ts_delete integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (hostid)\n\
);\n\
CREATE TABLE interface_discovery (\n\
interfaceid bigint  NOT NULL REFERENCES interface (interfaceid) ON DELETE CASCADE,\n\
parent_interfaceid bigint  NOT NULL REFERENCES interface (interfaceid) ON DELETE CASCADE,\n\
PRIMARY KEY (interfaceid)\n\
);\n\
CREATE TABLE profiles (\n\
profileid bigint  NOT NULL,\n\
userid bigint  NOT NULL REFERENCES users (userid) ON DELETE CASCADE,\n\
idx varchar(96) DEFAULT '' NOT NULL,\n\
idx2 bigint DEFAULT '0' NOT NULL,\n\
value_id bigint DEFAULT '0' NOT NULL,\n\
value_int integer DEFAULT '0' NOT NULL,\n\
value_str text DEFAULT '' NOT NULL,\n\
source varchar(96) DEFAULT '' NOT NULL,\n\
type integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (profileid)\n\
);\n\
CREATE INDEX profiles_1 ON profiles (userid,idx,idx2);\n\
CREATE INDEX profiles_2 ON profiles (userid,profileid);\n\
CREATE TABLE sessions (\n\
sessionid varchar(32) DEFAULT '' NOT NULL,\n\
userid bigint  NOT NULL REFERENCES users (userid) ON DELETE CASCADE,\n\
lastaccess integer DEFAULT '0' NOT NULL,\n\
status integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (sessionid)\n\
);\n\
CREATE INDEX sessions_1 ON sessions (userid,status,lastaccess);\n\
CREATE TABLE trigger_discovery (\n\
triggerid bigint  NOT NULL REFERENCES triggers (triggerid) ON DELETE CASCADE,\n\
parent_triggerid bigint  NOT NULL REFERENCES triggers (triggerid),\n\
lastcheck integer DEFAULT '0' NOT NULL,\n\
ts_delete integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (triggerid)\n\
);\n\
CREATE INDEX trigger_discovery_1 ON trigger_discovery (parent_triggerid);\n\
CREATE TABLE item_condition (\n\
item_conditionid bigint  NOT NULL,\n\
itemid bigint  NOT NULL REFERENCES items (itemid) ON DELETE CASCADE,\n\
operator integer DEFAULT '8' NOT NULL,\n\
macro varchar(64) DEFAULT '' NOT NULL,\n\
value varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (item_conditionid)\n\
);\n\
CREATE INDEX item_condition_1 ON item_condition (itemid);\n\
CREATE TABLE item_rtdata (\n\
itemid bigint  NOT NULL REFERENCES items (itemid) ON DELETE CASCADE,\n\
lastlogsize bigint DEFAULT '0' NOT NULL,\n\
state integer DEFAULT '0' NOT NULL,\n\
mtime integer DEFAULT '0' NOT NULL,\n\
error varchar(2048) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (itemid)\n\
);\n\
CREATE TABLE opinventory (\n\
operationid bigint  NOT NULL REFERENCES operations (operationid) ON DELETE CASCADE,\n\
inventory_mode integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (operationid)\n\
);\n\
CREATE TABLE trigger_tag (\n\
triggertagid bigint  NOT NULL,\n\
triggerid bigint  NOT NULL REFERENCES triggers (triggerid) ON DELETE CASCADE,\n\
tag varchar(255) DEFAULT '' NOT NULL,\n\
value varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (triggertagid)\n\
);\n\
CREATE INDEX trigger_tag_1 ON trigger_tag (triggerid);\n\
CREATE TABLE event_tag (\n\
eventtagid bigint  NOT NULL,\n\
eventid bigint  NOT NULL REFERENCES events (eventid) ON DELETE CASCADE,\n\
tag varchar(255) DEFAULT '' NOT NULL,\n\
value varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (eventtagid)\n\
);\n\
CREATE INDEX event_tag_1 ON event_tag (eventid);\n\
CREATE TABLE problem (\n\
eventid bigint  NOT NULL REFERENCES events (eventid) ON DELETE CASCADE,\n\
source integer DEFAULT '0' NOT NULL,\n\
object integer DEFAULT '0' NOT NULL,\n\
objectid bigint DEFAULT '0' NOT NULL,\n\
clock integer DEFAULT '0' NOT NULL,\n\
ns integer DEFAULT '0' NOT NULL,\n\
r_eventid bigint  NULL REFERENCES events (eventid) ON DELETE CASCADE,\n\
r_clock integer DEFAULT '0' NOT NULL,\n\
r_ns integer DEFAULT '0' NOT NULL,\n\
correlationid bigint  NULL,\n\
userid bigint  NULL,\n\
name varchar(2048) DEFAULT '' NOT NULL,\n\
acknowledged integer DEFAULT '0' NOT NULL,\n\
severity integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (eventid)\n\
);\n\
CREATE INDEX problem_1 ON problem (source,object,objectid);\n\
CREATE INDEX problem_2 ON problem (r_clock);\n\
CREATE INDEX problem_3 ON problem (r_eventid);\n\
CREATE TABLE problem_tag (\n\
problemtagid bigint  NOT NULL,\n\
eventid bigint  NOT NULL REFERENCES problem (eventid) ON DELETE CASCADE,\n\
tag varchar(255) DEFAULT '' NOT NULL,\n\
value varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (problemtagid)\n\
);\n\
CREATE INDEX problem_tag_1 ON problem_tag (eventid,tag,value);\n\
CREATE TABLE tag_filter (\n\
tag_filterid bigint  NOT NULL,\n\
usrgrpid bigint  NOT NULL REFERENCES usrgrp (usrgrpid) ON DELETE CASCADE,\n\
groupid bigint  NOT NULL REFERENCES hstgrp (groupid) ON DELETE CASCADE,\n\
tag varchar(255) DEFAULT ''  NOT NULL,\n\
value varchar(255) DEFAULT ''  NOT NULL,\n\
PRIMARY KEY (tag_filterid)\n\
);\n\
CREATE TABLE event_recovery (\n\
eventid bigint  NOT NULL REFERENCES events (eventid) ON DELETE CASCADE,\n\
r_eventid bigint  NOT NULL REFERENCES events (eventid) ON DELETE CASCADE,\n\
c_eventid bigint  NULL REFERENCES events (eventid) ON DELETE CASCADE,\n\
correlationid bigint  NULL,\n\
userid bigint  NULL,\n\
PRIMARY KEY (eventid)\n\
);\n\
CREATE INDEX event_recovery_1 ON event_recovery (r_eventid);\n\
CREATE INDEX event_recovery_2 ON event_recovery (c_eventid);\n\
CREATE TABLE correlation (\n\
correlationid bigint  NOT NULL,\n\
name varchar(255) DEFAULT '' NOT NULL,\n\
description text DEFAULT '' NOT NULL,\n\
evaltype integer DEFAULT '0' NOT NULL,\n\
status integer DEFAULT '0' NOT NULL,\n\
formula varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (correlationid)\n\
);\n\
CREATE INDEX correlation_1 ON correlation (status);\n\
CREATE UNIQUE INDEX correlation_2 ON correlation (name);\n\
CREATE TABLE corr_condition (\n\
corr_conditionid bigint  NOT NULL,\n\
correlationid bigint  NOT NULL REFERENCES correlation (correlationid) ON DELETE CASCADE,\n\
type integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (corr_conditionid)\n\
);\n\
CREATE INDEX corr_condition_1 ON corr_condition (correlationid);\n\
CREATE TABLE corr_condition_tag (\n\
corr_conditionid bigint  NOT NULL REFERENCES corr_condition (corr_conditionid) ON DELETE CASCADE,\n\
tag varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (corr_conditionid)\n\
);\n\
CREATE TABLE corr_condition_group (\n\
corr_conditionid bigint  NOT NULL REFERENCES corr_condition (corr_conditionid) ON DELETE CASCADE,\n\
operator integer DEFAULT '0' NOT NULL,\n\
groupid bigint  NOT NULL REFERENCES hstgrp (groupid),\n\
PRIMARY KEY (corr_conditionid)\n\
);\n\
CREATE INDEX corr_condition_group_1 ON corr_condition_group (groupid);\n\
CREATE TABLE corr_condition_tagpair (\n\
corr_conditionid bigint  NOT NULL REFERENCES corr_condition (corr_conditionid) ON DELETE CASCADE,\n\
oldtag varchar(255) DEFAULT '' NOT NULL,\n\
newtag varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (corr_conditionid)\n\
);\n\
CREATE TABLE corr_condition_tagvalue (\n\
corr_conditionid bigint  NOT NULL REFERENCES corr_condition (corr_conditionid) ON DELETE CASCADE,\n\
tag varchar(255) DEFAULT '' NOT NULL,\n\
operator integer DEFAULT '0' NOT NULL,\n\
value varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (corr_conditionid)\n\
);\n\
CREATE TABLE corr_operation (\n\
corr_operationid bigint  NOT NULL,\n\
correlationid bigint  NOT NULL REFERENCES correlation (correlationid) ON DELETE CASCADE,\n\
type integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (corr_operationid)\n\
);\n\
CREATE INDEX corr_operation_1 ON corr_operation (correlationid);\n\
CREATE TABLE task (\n\
taskid bigint  NOT NULL,\n\
type integer  NOT NULL,\n\
status integer DEFAULT '0' NOT NULL,\n\
clock integer DEFAULT '0' NOT NULL,\n\
ttl integer DEFAULT '0' NOT NULL,\n\
proxy_hostid bigint  NULL REFERENCES hosts (hostid) ON DELETE CASCADE,\n\
PRIMARY KEY (taskid)\n\
);\n\
CREATE INDEX task_1 ON task (status,proxy_hostid);\n\
CREATE TABLE task_close_problem (\n\
taskid bigint  NOT NULL REFERENCES task (taskid) ON DELETE CASCADE,\n\
acknowledgeid bigint  NOT NULL,\n\
PRIMARY KEY (taskid)\n\
);\n\
CREATE TABLE item_preproc (\n\
item_preprocid bigint  NOT NULL,\n\
itemid bigint  NOT NULL REFERENCES items (itemid) ON DELETE CASCADE,\n\
step integer DEFAULT '0' NOT NULL,\n\
type integer DEFAULT '0' NOT NULL,\n\
params text DEFAULT '' NOT NULL,\n\
error_handler integer DEFAULT '0' NOT NULL,\n\
error_handler_params varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (item_preprocid)\n\
);\n\
CREATE INDEX item_preproc_1 ON item_preproc (itemid,step);\n\
CREATE TABLE task_remote_command (\n\
taskid bigint  NOT NULL REFERENCES task (taskid) ON DELETE CASCADE,\n\
command_type integer DEFAULT '0' NOT NULL,\n\
execute_on integer DEFAULT '0' NOT NULL,\n\
port integer DEFAULT '0' NOT NULL,\n\
authtype integer DEFAULT '0' NOT NULL,\n\
username varchar(64) DEFAULT '' NOT NULL,\n\
password varchar(64) DEFAULT '' NOT NULL,\n\
publickey varchar(64) DEFAULT '' NOT NULL,\n\
privatekey varchar(64) DEFAULT '' NOT NULL,\n\
command text DEFAULT '' NOT NULL,\n\
alertid bigint  NULL,\n\
parent_taskid bigint  NOT NULL,\n\
hostid bigint  NOT NULL,\n\
PRIMARY KEY (taskid)\n\
);\n\
CREATE TABLE task_remote_command_result (\n\
taskid bigint  NOT NULL REFERENCES task (taskid) ON DELETE CASCADE,\n\
status integer DEFAULT '0' NOT NULL,\n\
parent_taskid bigint  NOT NULL,\n\
info text DEFAULT '' NOT NULL,\n\
PRIMARY KEY (taskid)\n\
);\n\
CREATE TABLE task_data (\n\
taskid bigint  NOT NULL REFERENCES task (taskid) ON DELETE CASCADE,\n\
type integer DEFAULT '0' NOT NULL,\n\
data text DEFAULT '' NOT NULL,\n\
parent_taskid bigint  NOT NULL,\n\
PRIMARY KEY (taskid)\n\
);\n\
CREATE TABLE task_result (\n\
taskid bigint  NOT NULL REFERENCES task (taskid) ON DELETE CASCADE,\n\
status integer DEFAULT '0' NOT NULL,\n\
parent_taskid bigint  NOT NULL,\n\
info text DEFAULT '' NOT NULL,\n\
PRIMARY KEY (taskid)\n\
);\n\
CREATE INDEX task_result_1 ON task_result (parent_taskid);\n\
CREATE TABLE task_acknowledge (\n\
taskid bigint  NOT NULL REFERENCES task (taskid) ON DELETE CASCADE,\n\
acknowledgeid bigint  NOT NULL,\n\
PRIMARY KEY (taskid)\n\
);\n\
CREATE TABLE sysmap_shape (\n\
sysmap_shapeid bigint  NOT NULL,\n\
sysmapid bigint  NOT NULL REFERENCES sysmaps (sysmapid) ON DELETE CASCADE,\n\
type integer DEFAULT '0' NOT NULL,\n\
x integer DEFAULT '0' NOT NULL,\n\
y integer DEFAULT '0' NOT NULL,\n\
width integer DEFAULT '200' NOT NULL,\n\
height integer DEFAULT '200' NOT NULL,\n\
text text DEFAULT '' NOT NULL,\n\
font integer DEFAULT '9' NOT NULL,\n\
font_size integer DEFAULT '11' NOT NULL,\n\
font_color varchar(6) DEFAULT '000000' NOT NULL,\n\
text_halign integer DEFAULT '0' NOT NULL,\n\
text_valign integer DEFAULT '0' NOT NULL,\n\
border_type integer DEFAULT '0' NOT NULL,\n\
border_width integer DEFAULT '1' NOT NULL,\n\
border_color varchar(6) DEFAULT '000000' NOT NULL,\n\
background_color varchar(6) DEFAULT '' NOT NULL,\n\
zindex integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (sysmap_shapeid)\n\
);\n\
CREATE INDEX sysmap_shape_1 ON sysmap_shape (sysmapid);\n\
CREATE TABLE sysmap_element_trigger (\n\
selement_triggerid bigint  NOT NULL,\n\
selementid bigint  NOT NULL REFERENCES sysmaps_elements (selementid) ON DELETE CASCADE,\n\
triggerid bigint  NOT NULL REFERENCES triggers (triggerid) ON DELETE CASCADE,\n\
PRIMARY KEY (selement_triggerid)\n\
);\n\
CREATE UNIQUE INDEX sysmap_element_trigger_1 ON sysmap_element_trigger (selementid,triggerid);\n\
CREATE TABLE httptest_field (\n\
httptest_fieldid bigint  NOT NULL,\n\
httptestid bigint  NOT NULL REFERENCES httptest (httptestid) ON DELETE CASCADE,\n\
type integer DEFAULT '0' NOT NULL,\n\
name varchar(255) DEFAULT '' NOT NULL,\n\
value text DEFAULT '' NOT NULL,\n\
PRIMARY KEY (httptest_fieldid)\n\
);\n\
CREATE INDEX httptest_field_1 ON httptest_field (httptestid);\n\
CREATE TABLE httpstep_field (\n\
httpstep_fieldid bigint  NOT NULL,\n\
httpstepid bigint  NOT NULL REFERENCES httpstep (httpstepid) ON DELETE CASCADE,\n\
type integer DEFAULT '0' NOT NULL,\n\
name varchar(255) DEFAULT '' NOT NULL,\n\
value text DEFAULT '' NOT NULL,\n\
PRIMARY KEY (httpstep_fieldid)\n\
);\n\
CREATE INDEX httpstep_field_1 ON httpstep_field (httpstepid);\n\
CREATE TABLE dashboard (\n\
dashboardid bigint  NOT NULL,\n\
name varchar(255)  NOT NULL,\n\
userid bigint  NULL REFERENCES users (userid),\n\
private integer DEFAULT '1' NOT NULL,\n\
templateid bigint  NULL REFERENCES hosts (hostid) ON DELETE CASCADE,\n\
display_period integer DEFAULT '30' NOT NULL,\n\
auto_start integer DEFAULT '1' NOT NULL,\n\
uuid varchar(32) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (dashboardid)\n\
);\n\
CREATE INDEX dashboard_1 ON dashboard (userid);\n\
CREATE INDEX dashboard_2 ON dashboard (templateid);\n\
CREATE TABLE dashboard_user (\n\
dashboard_userid bigint  NOT NULL,\n\
dashboardid bigint  NOT NULL REFERENCES dashboard (dashboardid) ON DELETE CASCADE,\n\
userid bigint  NOT NULL REFERENCES users (userid) ON DELETE CASCADE,\n\
permission integer DEFAULT '2' NOT NULL,\n\
PRIMARY KEY (dashboard_userid)\n\
);\n\
CREATE UNIQUE INDEX dashboard_user_1 ON dashboard_user (dashboardid,userid);\n\
CREATE TABLE dashboard_usrgrp (\n\
dashboard_usrgrpid bigint  NOT NULL,\n\
dashboardid bigint  NOT NULL REFERENCES dashboard (dashboardid) ON DELETE CASCADE,\n\
usrgrpid bigint  NOT NULL REFERENCES usrgrp (usrgrpid) ON DELETE CASCADE,\n\
permission integer DEFAULT '2' NOT NULL,\n\
PRIMARY KEY (dashboard_usrgrpid)\n\
);\n\
CREATE UNIQUE INDEX dashboard_usrgrp_1 ON dashboard_usrgrp (dashboardid,usrgrpid);\n\
CREATE TABLE dashboard_page (\n\
dashboard_pageid bigint  NOT NULL,\n\
dashboardid bigint  NOT NULL REFERENCES dashboard (dashboardid) ON DELETE CASCADE,\n\
name varchar(255) DEFAULT '' NOT NULL,\n\
display_period integer DEFAULT '0' NOT NULL,\n\
sortorder integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (dashboard_pageid)\n\
);\n\
CREATE INDEX dashboard_page_1 ON dashboard_page (dashboardid);\n\
CREATE TABLE widget (\n\
widgetid bigint  NOT NULL,\n\
type varchar(255) DEFAULT '' NOT NULL,\n\
name varchar(255) DEFAULT '' NOT NULL,\n\
x integer DEFAULT '0' NOT NULL,\n\
y integer DEFAULT '0' NOT NULL,\n\
width integer DEFAULT '1' NOT NULL,\n\
height integer DEFAULT '2' NOT NULL,\n\
view_mode integer DEFAULT '0' NOT NULL,\n\
dashboard_pageid bigint  NOT NULL REFERENCES dashboard_page (dashboard_pageid) ON DELETE CASCADE,\n\
PRIMARY KEY (widgetid)\n\
);\n\
CREATE INDEX widget_1 ON widget (dashboard_pageid);\n\
CREATE TABLE widget_field (\n\
widget_fieldid bigint  NOT NULL,\n\
widgetid bigint  NOT NULL REFERENCES widget (widgetid) ON DELETE CASCADE,\n\
type integer DEFAULT '0' NOT NULL,\n\
name varchar(255) DEFAULT '' NOT NULL,\n\
value_int integer DEFAULT '0' NOT NULL,\n\
value_str varchar(255) DEFAULT '' NOT NULL,\n\
value_groupid bigint  NULL REFERENCES hstgrp (groupid) ON DELETE CASCADE,\n\
value_hostid bigint  NULL REFERENCES hosts (hostid) ON DELETE CASCADE,\n\
value_itemid bigint  NULL REFERENCES items (itemid) ON DELETE CASCADE,\n\
value_graphid bigint  NULL REFERENCES graphs (graphid) ON DELETE CASCADE,\n\
value_sysmapid bigint  NULL REFERENCES sysmaps (sysmapid) ON DELETE CASCADE,\n\
value_serviceid bigint  NULL REFERENCES services (serviceid) ON DELETE CASCADE,\n\
value_slaid bigint  NULL REFERENCES sla (slaid) ON DELETE CASCADE,\n\
PRIMARY KEY (widget_fieldid)\n\
);\n\
CREATE INDEX widget_field_1 ON widget_field (widgetid);\n\
CREATE INDEX widget_field_2 ON widget_field (value_groupid);\n\
CREATE INDEX widget_field_3 ON widget_field (value_hostid);\n\
CREATE INDEX widget_field_4 ON widget_field (value_itemid);\n\
CREATE INDEX widget_field_5 ON widget_field (value_graphid);\n\
CREATE INDEX widget_field_6 ON widget_field (value_sysmapid);\n\
CREATE INDEX widget_field_7 ON widget_field (value_serviceid);\n\
CREATE INDEX widget_field_8 ON widget_field (value_slaid);\n\
CREATE TABLE task_check_now (\n\
taskid bigint  NOT NULL REFERENCES task (taskid) ON DELETE CASCADE,\n\
itemid bigint  NOT NULL,\n\
PRIMARY KEY (taskid)\n\
);\n\
CREATE TABLE event_suppress (\n\
event_suppressid bigint  NOT NULL,\n\
eventid bigint  NOT NULL REFERENCES events (eventid) ON DELETE CASCADE,\n\
maintenanceid bigint  NULL REFERENCES maintenances (maintenanceid) ON DELETE CASCADE,\n\
suppress_until integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (event_suppressid)\n\
);\n\
CREATE UNIQUE INDEX event_suppress_1 ON event_suppress (eventid,maintenanceid);\n\
CREATE INDEX event_suppress_2 ON event_suppress (suppress_until);\n\
CREATE INDEX event_suppress_3 ON event_suppress (maintenanceid);\n\
CREATE TABLE maintenance_tag (\n\
maintenancetagid bigint  NOT NULL,\n\
maintenanceid bigint  NOT NULL REFERENCES maintenances (maintenanceid) ON DELETE CASCADE,\n\
tag varchar(255) DEFAULT '' NOT NULL,\n\
operator integer DEFAULT '2' NOT NULL,\n\
value varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (maintenancetagid)\n\
);\n\
CREATE INDEX maintenance_tag_1 ON maintenance_tag (maintenanceid);\n\
CREATE TABLE lld_macro_path (\n\
lld_macro_pathid bigint  NOT NULL,\n\
itemid bigint  NOT NULL REFERENCES items (itemid) ON DELETE CASCADE,\n\
lld_macro varchar(255) DEFAULT '' NOT NULL,\n\
path varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (lld_macro_pathid)\n\
);\n\
CREATE UNIQUE INDEX lld_macro_path_1 ON lld_macro_path (itemid,lld_macro);\n\
CREATE TABLE host_tag (\n\
hosttagid bigint  NOT NULL,\n\
hostid bigint  NOT NULL REFERENCES hosts (hostid) ON DELETE CASCADE,\n\
tag varchar(255) DEFAULT '' NOT NULL,\n\
value varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (hosttagid)\n\
);\n\
CREATE INDEX host_tag_1 ON host_tag (hostid);\n\
CREATE TABLE config_autoreg_tls (\n\
autoreg_tlsid bigint  NOT NULL,\n\
tls_psk_identity varchar(128) DEFAULT '' NOT NULL,\n\
tls_psk varchar(512) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (autoreg_tlsid)\n\
);\n\
CREATE UNIQUE INDEX config_autoreg_tls_1 ON config_autoreg_tls (tls_psk_identity);\n\
CREATE TABLE module (\n\
moduleid bigint  NOT NULL,\n\
id varchar(255) DEFAULT '' NOT NULL,\n\
relative_path varchar(255) DEFAULT '' NOT NULL,\n\
status integer DEFAULT '0' NOT NULL,\n\
config text DEFAULT '' NOT NULL,\n\
PRIMARY KEY (moduleid)\n\
);\n\
CREATE TABLE interface_snmp (\n\
interfaceid bigint  NOT NULL REFERENCES interface (interfaceid) ON DELETE CASCADE,\n\
version integer DEFAULT '2' NOT NULL,\n\
bulk integer DEFAULT '1' NOT NULL,\n\
community varchar(64) DEFAULT '' NOT NULL,\n\
securityname varchar(64) DEFAULT '' NOT NULL,\n\
securitylevel integer DEFAULT '0' NOT NULL,\n\
authpassphrase varchar(64) DEFAULT '' NOT NULL,\n\
privpassphrase varchar(64) DEFAULT '' NOT NULL,\n\
authprotocol integer DEFAULT '0' NOT NULL,\n\
privprotocol integer DEFAULT '0' NOT NULL,\n\
contextname varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (interfaceid)\n\
);\n\
CREATE TABLE lld_override (\n\
lld_overrideid bigint  NOT NULL,\n\
itemid bigint  NOT NULL REFERENCES items (itemid) ON DELETE CASCADE,\n\
name varchar(255) DEFAULT '' NOT NULL,\n\
step integer DEFAULT '0' NOT NULL,\n\
evaltype integer DEFAULT '0' NOT NULL,\n\
formula varchar(255) DEFAULT '' NOT NULL,\n\
stop integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (lld_overrideid)\n\
);\n\
CREATE UNIQUE INDEX lld_override_1 ON lld_override (itemid,name);\n\
CREATE TABLE lld_override_condition (\n\
lld_override_conditionid bigint  NOT NULL,\n\
lld_overrideid bigint  NOT NULL REFERENCES lld_override (lld_overrideid) ON DELETE CASCADE,\n\
operator integer DEFAULT '8' NOT NULL,\n\
macro varchar(64) DEFAULT '' NOT NULL,\n\
value varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (lld_override_conditionid)\n\
);\n\
CREATE INDEX lld_override_condition_1 ON lld_override_condition (lld_overrideid);\n\
CREATE TABLE lld_override_operation (\n\
lld_override_operationid bigint  NOT NULL,\n\
lld_overrideid bigint  NOT NULL REFERENCES lld_override (lld_overrideid) ON DELETE CASCADE,\n\
operationobject integer DEFAULT '0' NOT NULL,\n\
operator integer DEFAULT '0' NOT NULL,\n\
value varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (lld_override_operationid)\n\
);\n\
CREATE INDEX lld_override_operation_1 ON lld_override_operation (lld_overrideid);\n\
CREATE TABLE lld_override_opstatus (\n\
lld_override_operationid bigint  NOT NULL REFERENCES lld_override_operation (lld_override_operationid) ON DELETE CASCADE,\n\
status integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (lld_override_operationid)\n\
);\n\
CREATE TABLE lld_override_opdiscover (\n\
lld_override_operationid bigint  NOT NULL REFERENCES lld_override_operation (lld_override_operationid) ON DELETE CASCADE,\n\
discover integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (lld_override_operationid)\n\
);\n\
CREATE TABLE lld_override_opperiod (\n\
lld_override_operationid bigint  NOT NULL REFERENCES lld_override_operation (lld_override_operationid) ON DELETE CASCADE,\n\
delay varchar(1024) DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (lld_override_operationid)\n\
);\n\
CREATE TABLE lld_override_ophistory (\n\
lld_override_operationid bigint  NOT NULL REFERENCES lld_override_operation (lld_override_operationid) ON DELETE CASCADE,\n\
history varchar(255) DEFAULT '90d' NOT NULL,\n\
PRIMARY KEY (lld_override_operationid)\n\
);\n\
CREATE TABLE lld_override_optrends (\n\
lld_override_operationid bigint  NOT NULL REFERENCES lld_override_operation (lld_override_operationid) ON DELETE CASCADE,\n\
trends varchar(255) DEFAULT '365d' NOT NULL,\n\
PRIMARY KEY (lld_override_operationid)\n\
);\n\
CREATE TABLE lld_override_opseverity (\n\
lld_override_operationid bigint  NOT NULL REFERENCES lld_override_operation (lld_override_operationid) ON DELETE CASCADE,\n\
severity integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (lld_override_operationid)\n\
);\n\
CREATE TABLE lld_override_optag (\n\
lld_override_optagid bigint  NOT NULL,\n\
lld_override_operationid bigint  NOT NULL REFERENCES lld_override_operation (lld_override_operationid) ON DELETE CASCADE,\n\
tag varchar(255) DEFAULT '' NOT NULL,\n\
value varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (lld_override_optagid)\n\
);\n\
CREATE INDEX lld_override_optag_1 ON lld_override_optag (lld_override_operationid);\n\
CREATE TABLE lld_override_optemplate (\n\
lld_override_optemplateid bigint  NOT NULL,\n\
lld_override_operationid bigint  NOT NULL REFERENCES lld_override_operation (lld_override_operationid) ON DELETE CASCADE,\n\
templateid bigint  NOT NULL REFERENCES hosts (hostid),\n\
PRIMARY KEY (lld_override_optemplateid)\n\
);\n\
CREATE UNIQUE INDEX lld_override_optemplate_1 ON lld_override_optemplate (lld_override_operationid,templateid);\n\
CREATE INDEX lld_override_optemplate_2 ON lld_override_optemplate (templateid);\n\
CREATE TABLE lld_override_opinventory (\n\
lld_override_operationid bigint  NOT NULL REFERENCES lld_override_operation (lld_override_operationid) ON DELETE CASCADE,\n\
inventory_mode integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (lld_override_operationid)\n\
);\n\
CREATE TABLE trigger_queue (\n\
trigger_queueid bigint  NOT NULL,\n\
objectid bigint  NOT NULL,\n\
type integer DEFAULT '0' NOT NULL,\n\
clock integer DEFAULT '0' NOT NULL,\n\
ns integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (trigger_queueid)\n\
);\n\
CREATE TABLE item_parameter (\n\
item_parameterid bigint  NOT NULL,\n\
itemid bigint  NOT NULL REFERENCES items (itemid) ON DELETE CASCADE,\n\
name varchar(255) DEFAULT '' NOT NULL,\n\
value varchar(2048) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (item_parameterid)\n\
);\n\
CREATE INDEX item_parameter_1 ON item_parameter (itemid);\n\
CREATE TABLE role_rule (\n\
role_ruleid bigint  NOT NULL,\n\
roleid bigint  NOT NULL REFERENCES role (roleid) ON DELETE CASCADE,\n\
type integer DEFAULT '0' NOT NULL,\n\
name varchar(255) DEFAULT '' NOT NULL,\n\
value_int integer DEFAULT '0' NOT NULL,\n\
value_str varchar(255) DEFAULT '' NOT NULL,\n\
value_moduleid bigint  NULL REFERENCES module (moduleid) ON DELETE CASCADE,\n\
value_serviceid bigint  NULL REFERENCES services (serviceid) ON DELETE CASCADE,\n\
PRIMARY KEY (role_ruleid)\n\
);\n\
CREATE INDEX role_rule_1 ON role_rule (roleid);\n\
CREATE INDEX role_rule_2 ON role_rule (value_moduleid);\n\
CREATE INDEX role_rule_3 ON role_rule (value_serviceid);\n\
CREATE TABLE token (\n\
tokenid bigint  NOT NULL,\n\
name varchar(64) DEFAULT '' NOT NULL,\n\
description text DEFAULT '' NOT NULL,\n\
userid bigint  NOT NULL REFERENCES users (userid) ON DELETE CASCADE,\n\
token varchar(128)  NULL,\n\
lastaccess integer DEFAULT '0' NOT NULL,\n\
status integer DEFAULT '0' NOT NULL,\n\
expires_at integer DEFAULT '0' NOT NULL,\n\
created_at integer DEFAULT '0' NOT NULL,\n\
creator_userid bigint  NULL REFERENCES users (userid),\n\
PRIMARY KEY (tokenid)\n\
);\n\
CREATE INDEX token_1 ON token (name);\n\
CREATE UNIQUE INDEX token_2 ON token (userid,name);\n\
CREATE UNIQUE INDEX token_3 ON token (token);\n\
CREATE INDEX token_4 ON token (creator_userid);\n\
CREATE TABLE item_tag (\n\
itemtagid bigint  NOT NULL,\n\
itemid bigint  NOT NULL REFERENCES items (itemid) ON DELETE CASCADE,\n\
tag varchar(255) DEFAULT '' NOT NULL,\n\
value varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (itemtagid)\n\
);\n\
CREATE INDEX item_tag_1 ON item_tag (itemid);\n\
CREATE TABLE httptest_tag (\n\
httptesttagid bigint  NOT NULL,\n\
httptestid bigint  NOT NULL REFERENCES httptest (httptestid) ON DELETE CASCADE,\n\
tag varchar(255) DEFAULT '' NOT NULL,\n\
value varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (httptesttagid)\n\
);\n\
CREATE INDEX httptest_tag_1 ON httptest_tag (httptestid);\n\
CREATE TABLE sysmaps_element_tag (\n\
selementtagid bigint  NOT NULL,\n\
selementid bigint  NOT NULL REFERENCES sysmaps_elements (selementid) ON DELETE CASCADE,\n\
tag varchar(255) DEFAULT '' NOT NULL,\n\
value varchar(255) DEFAULT '' NOT NULL,\n\
operator integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (selementtagid)\n\
);\n\
CREATE INDEX sysmaps_element_tag_1 ON sysmaps_element_tag (selementid);\n\
CREATE TABLE report (\n\
reportid bigint  NOT NULL,\n\
userid bigint  NOT NULL REFERENCES users (userid) ON DELETE CASCADE,\n\
name varchar(255) DEFAULT '' NOT NULL,\n\
description varchar(2048) DEFAULT '' NOT NULL,\n\
status integer DEFAULT '0' NOT NULL,\n\
dashboardid bigint  NOT NULL REFERENCES dashboard (dashboardid) ON DELETE CASCADE,\n\
period integer DEFAULT '0' NOT NULL,\n\
cycle integer DEFAULT '0' NOT NULL,\n\
weekdays integer DEFAULT '0' NOT NULL,\n\
start_time integer DEFAULT '0' NOT NULL,\n\
active_since integer DEFAULT '0' NOT NULL,\n\
active_till integer DEFAULT '0' NOT NULL,\n\
state integer DEFAULT '0' NOT NULL,\n\
lastsent integer DEFAULT '0' NOT NULL,\n\
info varchar(2048) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (reportid)\n\
);\n\
CREATE UNIQUE INDEX report_1 ON report (name);\n\
CREATE TABLE report_param (\n\
reportparamid bigint  NOT NULL,\n\
reportid bigint  NOT NULL REFERENCES report (reportid) ON DELETE CASCADE,\n\
name varchar(255) DEFAULT '' NOT NULL,\n\
value text DEFAULT '' NOT NULL,\n\
PRIMARY KEY (reportparamid)\n\
);\n\
CREATE INDEX report_param_1 ON report_param (reportid);\n\
CREATE TABLE report_user (\n\
reportuserid bigint  NOT NULL,\n\
reportid bigint  NOT NULL REFERENCES report (reportid) ON DELETE CASCADE,\n\
userid bigint  NOT NULL REFERENCES users (userid) ON DELETE CASCADE,\n\
exclude integer DEFAULT '0' NOT NULL,\n\
access_userid bigint  NULL REFERENCES users (userid),\n\
PRIMARY KEY (reportuserid)\n\
);\n\
CREATE INDEX report_user_1 ON report_user (reportid);\n\
CREATE TABLE report_usrgrp (\n\
reportusrgrpid bigint  NOT NULL,\n\
reportid bigint  NOT NULL REFERENCES report (reportid) ON DELETE CASCADE,\n\
usrgrpid bigint  NOT NULL REFERENCES usrgrp (usrgrpid) ON DELETE CASCADE,\n\
access_userid bigint  NULL REFERENCES users (userid),\n\
PRIMARY KEY (reportusrgrpid)\n\
);\n\
CREATE INDEX report_usrgrp_1 ON report_usrgrp (reportid);\n\
CREATE TABLE service_problem_tag (\n\
service_problem_tagid bigint  NOT NULL,\n\
serviceid bigint  NOT NULL REFERENCES services (serviceid) ON DELETE CASCADE,\n\
tag varchar(255) DEFAULT '' NOT NULL,\n\
operator integer DEFAULT '0' NOT NULL,\n\
value varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (service_problem_tagid)\n\
);\n\
CREATE INDEX service_problem_tag_1 ON service_problem_tag (serviceid);\n\
CREATE TABLE service_problem (\n\
service_problemid bigint  NOT NULL,\n\
eventid bigint  NOT NULL REFERENCES problem (eventid) ON DELETE CASCADE,\n\
serviceid bigint  NOT NULL REFERENCES services (serviceid) ON DELETE CASCADE,\n\
severity integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (service_problemid)\n\
);\n\
CREATE INDEX service_problem_1 ON service_problem (eventid);\n\
CREATE INDEX service_problem_2 ON service_problem (serviceid);\n\
CREATE TABLE service_tag (\n\
servicetagid bigint  NOT NULL,\n\
serviceid bigint  NOT NULL REFERENCES services (serviceid) ON DELETE CASCADE,\n\
tag varchar(255) DEFAULT '' NOT NULL,\n\
value varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (servicetagid)\n\
);\n\
CREATE INDEX service_tag_1 ON service_tag (serviceid);\n\
CREATE TABLE service_status_rule (\n\
service_status_ruleid bigint  NOT NULL,\n\
serviceid bigint  NOT NULL REFERENCES services (serviceid) ON DELETE CASCADE,\n\
type integer DEFAULT '0' NOT NULL,\n\
limit_value integer DEFAULT '0' NOT NULL,\n\
limit_status integer DEFAULT '0' NOT NULL,\n\
new_status integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (service_status_ruleid)\n\
);\n\
CREATE INDEX service_status_rule_1 ON service_status_rule (serviceid);\n\
CREATE TABLE ha_node (\n\
ha_nodeid varchar(25)  NOT NULL,\n\
name varchar(255) DEFAULT '' NOT NULL,\n\
address varchar(255) DEFAULT '' NOT NULL,\n\
port integer DEFAULT '10051' NOT NULL,\n\
lastaccess integer DEFAULT '0' NOT NULL,\n\
status integer DEFAULT '0' NOT NULL,\n\
ha_sessionid varchar(25) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (ha_nodeid)\n\
);\n\
CREATE UNIQUE INDEX ha_node_1 ON ha_node (name);\n\
CREATE INDEX ha_node_2 ON ha_node (status,lastaccess);\n\
CREATE TABLE sla (\n\
slaid bigint  NOT NULL,\n\
name varchar(255) DEFAULT '' NOT NULL,\n\
period integer DEFAULT '0' NOT NULL,\n\
slo DOUBLE PRECISION DEFAULT '99.9' NOT NULL,\n\
effective_date integer DEFAULT '0' NOT NULL,\n\
timezone varchar(50) DEFAULT 'UTC' NOT NULL,\n\
status integer DEFAULT '1' NOT NULL,\n\
description text DEFAULT '' NOT NULL,\n\
PRIMARY KEY (slaid)\n\
);\n\
CREATE UNIQUE INDEX sla_1 ON sla (name);\n\
CREATE TABLE sla_schedule (\n\
sla_scheduleid bigint  NOT NULL,\n\
slaid bigint  NOT NULL REFERENCES sla (slaid) ON DELETE CASCADE,\n\
period_from integer DEFAULT '0' NOT NULL,\n\
period_to integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (sla_scheduleid)\n\
);\n\
CREATE INDEX sla_schedule_1 ON sla_schedule (slaid);\n\
CREATE TABLE sla_excluded_downtime (\n\
sla_excluded_downtimeid bigint  NOT NULL,\n\
slaid bigint  NOT NULL REFERENCES sla (slaid) ON DELETE CASCADE,\n\
name varchar(255) DEFAULT '' NOT NULL,\n\
period_from integer DEFAULT '0' NOT NULL,\n\
period_to integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (sla_excluded_downtimeid)\n\
);\n\
CREATE INDEX sla_excluded_downtime_1 ON sla_excluded_downtime (slaid);\n\
CREATE TABLE sla_service_tag (\n\
sla_service_tagid bigint  NOT NULL,\n\
slaid bigint  NOT NULL REFERENCES sla (slaid) ON DELETE CASCADE,\n\
tag varchar(255) DEFAULT '' NOT NULL,\n\
operator integer DEFAULT '0' NOT NULL,\n\
value varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (sla_service_tagid)\n\
);\n\
CREATE INDEX sla_service_tag_1 ON sla_service_tag (slaid);\n\
CREATE TABLE host_rtdata (\n\
hostid bigint  NOT NULL REFERENCES hosts (hostid) ON DELETE CASCADE,\n\
active_available integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (hostid)\n\
);\n\
CREATE TABLE userdirectory (\n\
userdirectoryid bigint  NOT NULL,\n\
name varchar(128) DEFAULT '' NOT NULL,\n\
description text DEFAULT '' NOT NULL,\n\
host varchar(255) DEFAULT '' NOT NULL,\n\
port integer DEFAULT '389' NOT NULL,\n\
base_dn varchar(255) DEFAULT '' NOT NULL,\n\
bind_dn varchar(255) DEFAULT '' NOT NULL,\n\
bind_password varchar(128) DEFAULT '' NOT NULL,\n\
search_attribute varchar(128) DEFAULT '' NOT NULL,\n\
start_tls integer DEFAULT '0' NOT NULL,\n\
search_filter varchar(255) DEFAULT '' NOT NULL,\n\
PRIMARY KEY (userdirectoryid)\n\
);\n\
CREATE TABLE dbversion (\n\
dbversionid bigint  NOT NULL,\n\
mandatory integer DEFAULT '0' NOT NULL,\n\
optional integer DEFAULT '0' NOT NULL,\n\
PRIMARY KEY (dbversionid)\n\
);\n\
INSERT INTO dbversion VALUES ('1','6010023','6010023');\n\
";
const char	*const db_schema_fkeys[] = {
	NULL
};
#else	/* HAVE_SQLITE3 */
const char	*const db_schema = NULL;
#endif	/* not HAVE_SQLITE3 */
