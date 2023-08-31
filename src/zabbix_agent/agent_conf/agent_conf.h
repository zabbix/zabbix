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

#ifndef ZABBIX_AGENT_CONF_H
#define ZABBIX_AGENT_CONF_H

#include "cfg.h"
void	load_aliases(char **lines);
int	load_user_parameters(char **lines, char **err);
int	load_key_access_rule(const char *value, const struct cfg_line *cfg);
void	reload_user_parameters(unsigned char process_type, int process_num, const char *config_file,
		char **config_user_parameters);
#ifdef _WINDOWS
void	load_perf_counters(const char **def_lines, const char **eng_lines);
#endif

#ifdef _AIX
void	tl_version(void);
#endif

#endif /* ZABBIX_AGENT_CONF_H */
