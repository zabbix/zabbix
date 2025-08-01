/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#ifndef ZABBIX_HISTORY_OPTION_H
#define ZABBIX_HISTORY_OPTION_H

#include "zbxhistory_provider.h"
#include "zbxtypes.h"
#include "zbxalgo.h"

ZBX_VECTOR_DECL(history_option, zbx_history_option_t)

zbx_history_option_t	history_option_str(const char *name, const char *value);
zbx_history_option_t	history_option_int(const char *name, int value);
const char	*history_option_value(const zbx_history_option_t *options, int options_num, const char *name);

int	history_provider_parse_options(const char *conf, char **name, zbx_vector_history_option_t *options,
		char **error);
void	history_options_clear(zbx_history_option_t *options, int options_num);
zbx_uint64_t	history_options_type_mask(zbx_history_option_t *options, int options_num, const char **value_types);

#endif

