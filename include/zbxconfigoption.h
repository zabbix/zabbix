/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#ifndef ZABBIX_ZBXCONFIGOPTION_H
#define ZABBIX_ZBXCONFIGOPTION_H

#include "zbxalgo.h"

typedef struct
{
	char	*name;
	char	*value;
}
zbx_config_option_t;

ZBX_VECTOR_DECL(config_option, zbx_config_option_t)

zbx_config_option_t	zbx_config_option_str(const char *name, const char *value);
zbx_config_option_t	zbx_config_option_int(const char *name, int value);
const char		*zbx_config_option_value(const zbx_config_option_t *options, int options_num, const char *name);

ssize_t	zbx_config_option_parse_param(const char *text);
int	zbx_config_option_parse_options(const char *text, zbx_vector_config_option_t *options, char **error);
void	zbx_config_option_clear_options(zbx_config_option_t *options, int options_num);

#endif
