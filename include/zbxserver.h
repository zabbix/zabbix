/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#ifndef ZABBIX_ZBXSERVER_H
#define ZABBIX_ZBXSERVER_H

#include "common.h"
#include "db.h"
#include "dbcache.h"
#include "zbxjson.h"

#define MACRO_TYPE_TRIGGER_DESCRIPTION	0x0001
#define MACRO_TYPE_MESSAGE_SUBJECT	0x0002
#define MACRO_TYPE_MESSAGE_BODY		0x0004
#define MACRO_TYPE_MESSAGE		0x0006	/* (MACRO_TYPE_MESSAGE_SUBJECT | MACRO_TYPE_MESSAGE_BODY) */
#define MACRO_TYPE_TRIGGER_EXPRESSION	0x0008
#define MACRO_TYPE_ITEM_KEY		0x0010
#define MACRO_TYPE_INTERFACE_ADDR	0x0020
#define MACRO_TYPE_INTERFACE_PORT	0x0040
#define MACRO_TYPE_FUNCTION_PARAMETER	0x0080
#define MACRO_TYPE_ITEM_FIELD		0x0100
#define MACRO_TYPE_SCRIPT		0x0200
#define MACRO_TYPE_ITEM_EXPRESSION	0x0400

int	evaluate_function(char *value, DB_ITEM *item, const char *function, const char *parameters, time_t now);

int	substitute_simple_macros(DB_EVENT *event, zbx_uint64_t *hostid, DC_HOST *dc_host,
		DB_ESCALATION *escalation, char **data, int macro_type, char *error, int maxerrlen);
void	substitute_macros(DB_EVENT *event, DB_ESCALATION *escalation, char **data);

int	evaluate_expression(int *result, char **expression, time_t now,
		zbx_uint64_t trigggerid, int trigger_value, char *error, int maxerrlen);
int	evaluate(double *value, char *exp, char *error, int maxerrlen);
void	substitute_discovery_macros(char **data, struct zbx_json_parse *jp_row);

#endif
