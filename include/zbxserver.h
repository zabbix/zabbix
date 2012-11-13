/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#ifndef ZABBIX_ZBXSERVER_H
#define ZABBIX_ZBXSERVER_H

#include "common.h"
#include "db.h"
#include "dbcache.h"
#include "zbxjson.h"

#define TRIGGER_EPSILON	0.000001

#define MACRO_TYPE_TRIGGER_DESCRIPTION	0x0001
#define MACRO_TYPE_MESSAGE_SUBJECT	0x0002
#define MACRO_TYPE_MESSAGE_BODY		0x0004
#define MACRO_TYPE_MESSAGE		0x0006	/* (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) */
#define MACRO_TYPE_TRIGGER_EXPRESSION	0x0008
#define MACRO_TYPE_TRIGGER_URL		0x0010
#define MACRO_TYPE_ITEM_KEY		0x0020
#define MACRO_TYPE_INTERFACE_ADDR	0x0040
#define MACRO_TYPE_INTERFACE_ADDR_DB	0x0080
#define MACRO_TYPE_INTERFACE_PORT	0x0100
#define MACRO_TYPE_FUNCTION_PARAMETER	0x0200
#define MACRO_TYPE_ITEM_FIELD		0x0400
#define MACRO_TYPE_PARAMS_FIELD		0x0800
#define MACRO_TYPE_SCRIPT		0x1000
#define MACRO_TYPE_ITEM_EXPRESSION	0x2000
#define MACRO_TYPE_LLD_LIFETIME		0x4000
#define MACRO_TYPE_SNMP_OID		0x8000

#define STR_CONTAINS_MACROS(str)	(NULL != strchr(str, '{'))

int	evaluate_function(char *value, DB_ITEM *item, const char *function, const char *parameters, time_t now);

int	substitute_simple_macros(DB_EVENT *event, zbx_uint64_t *hostid, DC_HOST *dc_host, DC_ITEM *dc_item,
		DB_ESCALATION *escalation, char **data, int macro_type, char *error, int maxerrlen);

void	evaluate_expressions(zbx_vector_ptr_t *triggers);
int	evaluate(double *value, char *exp, char *error, int maxerrlen);

void	zbx_format_value(char *value, size_t max_len, zbx_uint64_t valuemapid,
		const char *units, unsigned char value_type);

#define ZBX_MACRO_ANY		0
#define ZBX_MACRO_NUMERIC	1
int	substitute_discovery_macros(char **data, struct zbx_json_parse *jp_row, int flags,
		char *error, size_t max_error_len);
int	substitute_key_macros(char **data, zbx_uint64_t *hostid, DC_ITEM *dc_item, struct zbx_json_parse *jp_row,
		int macro_type, char *error, size_t mexerrlen);

#endif
