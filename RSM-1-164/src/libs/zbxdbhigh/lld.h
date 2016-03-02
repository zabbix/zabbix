/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

#ifndef ZABBIX_LLD_H
#define ZABBIX_LLD_H

#include "common.h"
#include "zbxjson.h"

int	lld_check_record(struct zbx_json_parse *jp_row, const char *f_macro, const char *f_regexp, ZBX_REGEXP *regexps,
		int regexps_num);
int	DBlld_get_item(zbx_uint64_t hostid, const char *tmpl_key, struct zbx_json_parse *jp_row, zbx_uint64_t *itemid);

void	DBlld_update_items(zbx_uint64_t hostid, zbx_uint64_t lld_ruleid, struct zbx_json_parse *jp_data,
		char **error, const char *f_macro, const char *f_regexp, ZBX_REGEXP *regexps, int regexps_num,
		unsigned short lifetime, int lastcheck);

void	DBlld_update_triggers(zbx_uint64_t hostid, zbx_uint64_t discovery_itemid, struct zbx_json_parse *jp_data,
		char **error, const char *f_macro, const char *f_regexp, ZBX_REGEXP *regexps, int regexps_num);

void	DBlld_update_graphs(zbx_uint64_t hostid, zbx_uint64_t discovery_itemid, struct zbx_json_parse *jp_data,
		char **error, const char *f_macro, const char *f_regexp, ZBX_REGEXP *regexps, int regexps_num);

#endif
