/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#ifndef ZABBIX_ITEM_PREPROC_H
#define ZABBIX_ITEM_PREPROC_H

#include "zbxembed.h"
#include "zbxtime.h"

int	zbx_item_preproc_convert_value_to_numeric(zbx_variant_t *value_num, const zbx_variant_t *value,
		unsigned char value_type, char **errmsg);

int	item_preproc_convert_value(zbx_variant_t *value, unsigned char type, char **errmsg);

int	item_preproc_multiplier_variant(unsigned char value_type, zbx_variant_t *value, const char *params,
		char **errmsg);
int	item_preproc_trim(zbx_variant_t *value, int op_type, const char *params, char **errmsg);
int	item_preproc_delta(unsigned char value_type, zbx_variant_t *value, const zbx_timespec_t *ts,
		int op_type, zbx_variant_t *history_value, zbx_timespec_t *history_ts, char **errmsg);
int	item_preproc_regsub_op(zbx_variant_t *value, const char *params, char **errmsg);
int	item_preproc_2dec(zbx_variant_t *value, int op_type, char **errmsg);
int	item_preproc_validate_range(unsigned char value_type, const zbx_variant_t *value, const char *params,
		char **errmsg);
int	item_preproc_validate_regex(const zbx_variant_t *value, const char *params, char **error);
int	item_preproc_validate_not_regex(const zbx_variant_t *value, const char *params, char **error);
int	item_preproc_get_error_from_json(const zbx_variant_t *value, const char *params, char **error);
int	item_preproc_get_error_from_xml(const zbx_variant_t *value, const char *params, char **error);
int	item_preproc_get_error_from_regex(const zbx_variant_t *value, const char *params, char **error);
int	item_preproc_throttle_value(zbx_variant_t *value, const zbx_timespec_t *ts,
		zbx_variant_t *history_value, zbx_timespec_t *history_ts);
int	item_preproc_throttle_timed_value(zbx_variant_t *value, const zbx_timespec_t *ts, const char *params,
		zbx_variant_t *history_value, zbx_timespec_t *history_ts, char **errmsg);
int	item_preproc_script(zbx_es_t *es, zbx_variant_t *value, const char *params, zbx_variant_t *bytecode,
		const char *config_source_ip, char **errmsg);
int	item_preproc_csv_to_json(zbx_variant_t *value, const char *params, char **errmsg);
int	item_preproc_xml_to_json(zbx_variant_t *value, char **errmsg);
int	item_preproc_str_replace(zbx_variant_t *value, const char *params, char **errmsg);
int	item_preproc_check_error_regex(const zbx_variant_t *value, const char *params, char **error);

#endif
