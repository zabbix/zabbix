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

#ifndef ZABBIX_EXPRESSION_H
#define ZABBIX_EXPRESSION_H

#include "zbxdbhigh.h"

/* DBget_item_value() */
#define ZBX_REQUEST_HOST_ID			101
#define ZBX_REQUEST_HOST_HOST			102
#define ZBX_REQUEST_HOST_NAME			103
#define ZBX_REQUEST_HOST_DESCRIPTION		104
#define ZBX_REQUEST_ITEM_ID			105
#define ZBX_REQUEST_ITEM_NAME			106
#define ZBX_REQUEST_ITEM_NAME_ORIG		107
#define ZBX_REQUEST_ITEM_KEY			108
#define ZBX_REQUEST_ITEM_KEY_ORIG		109
#define ZBX_REQUEST_ITEM_DESCRIPTION		110
#define ZBX_REQUEST_ITEM_DESCRIPTION_ORIG	111
#define ZBX_REQUEST_PROXY_NAME			112
#define ZBX_REQUEST_PROXY_DESCRIPTION		113
#define ZBX_REQUEST_ITEM_VALUETYPE		114
#define	ZBX_REQUEST_ITEM_ERROR			115

int	DBget_trigger_value(const ZBX_DB_TRIGGER *trigger, char **replace_to, int N_functionid, int request);
int	zbx_host_macro_index(const char *macro);

#endif
