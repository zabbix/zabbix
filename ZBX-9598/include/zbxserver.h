/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#ifndef ZABBIX_ZBXSERVER_H
#define ZABBIX_ZBXSERVER_H

#include "common.h"
#include "db.h"
#include "dbcache.h"
#include "zbxjson.h"

#define MACRO_TYPE_MESSAGE_NORMAL	0x00000001
#define MACRO_TYPE_MESSAGE_RECOVERY	0x00000002
#define MACRO_TYPE_TRIGGER_URL		0x00000004
#define MACRO_TYPE_TRIGGER_EXPRESSION	0x00000008
#define MACRO_TYPE_TRIGGER_DESCRIPTION	0x00000010	/* name */
#define MACRO_TYPE_TRIGGER_COMMENTS	0x00000020	/* description */
#define MACRO_TYPE_ITEM_KEY		0x00000040
#define MACRO_TYPE_ITEM_EXPRESSION	0x00000080
#define MACRO_TYPE_INTERFACE_ADDR	0x00000100
#define MACRO_TYPE_INTERFACE_ADDR_DB	0x00000200
#define MACRO_TYPE_COMMON		0x00000400
#define MACRO_TYPE_PARAMS_FIELD		0x00000800
#define MACRO_TYPE_SCRIPT		0x00001000
#define MACRO_TYPE_SNMP_OID		0x00002000
#define MACRO_TYPE_HTTPTEST_FIELD	0x00004000
#define MACRO_TYPE_LLD_FILTER		0x00008000
#define MACRO_TYPE_ALERT		0x00010000
#define MACRO_TYPE_TRIGGER_TAG		0x00020000

#define STR_CONTAINS_MACROS(str)	(NULL != strchr(str, '{'))

void	get_functionids(zbx_vector_uint64_t *functionids, const char *expression);

int	evaluate_function(char *value, DC_ITEM *item, const char *function, const char *parameters, time_t now,
		char **error);

int	substitute_simple_macros(zbx_uint64_t *actionid, const DB_EVENT *event, DB_EVENT *r_event, zbx_uint64_t *userid,
		zbx_uint64_t *hostid, DC_HOST *dc_host, DC_ITEM *dc_item, DB_ALERT *alert, char **data, int macro_type,
		char *error, int maxerrlen);

void	evaluate_expressions(zbx_vector_ptr_t *triggers);

void	zbx_format_value(char *value, size_t max_len, zbx_uint64_t valuemapid,
		const char *units, unsigned char value_type);

/* lld macro context */
#define ZBX_MACRO_ANY		(ZBX_TOKEN_LLD_MACRO | ZBX_TOKEN_USER_MACRO)
#define ZBX_MACRO_NUMERIC	(ZBX_MACRO_ANY | ZBX_TOKEN_NUMERIC)
#define ZBX_MACRO_SIMPLE	(ZBX_MACRO_ANY | ZBX_TOKEN_SIMPLE_MACRO)
#define ZBX_MACRO_FUNC		(ZBX_MACRO_ANY | ZBX_TOKEN_FUNC_MACRO)

int	substitute_lld_macros(char **data, struct zbx_json_parse *jp_row, int flags, const char **func_macros,
		char *error, size_t max_error_len);
int	substitute_key_macros(char **data, zbx_uint64_t *hostid, DC_ITEM *dc_item, struct zbx_json_parse *jp_row,
		int macro_type, char *error, size_t mexerrlen);

#endif
