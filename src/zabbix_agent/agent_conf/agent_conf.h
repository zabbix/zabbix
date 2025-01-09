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

#ifndef ZABBIX_AGENT_CONF_H
#define ZABBIX_AGENT_CONF_H

#include "zbxcfg.h"
void	load_aliases(char **lines);
int	load_user_parameters(char **lines, char **err);
int	load_key_access_rule(const char *value, const zbx_cfg_line_t *cfg);
void	reload_user_parameters(unsigned char process_type, int process_num, const char *config_file);
#ifdef _WINDOWS
void	load_perf_counters(const char **def_lines, const char **eng_lines);
#endif

#ifdef _AIX
void	tl_version(void);
#endif

#endif /* ZABBIX_AGENT_CONF_H */
