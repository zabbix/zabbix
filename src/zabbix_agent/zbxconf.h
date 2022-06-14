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

#ifndef ZABBIX_ZBXCONF_H
#define ZABBIX_ZBXCONF_H

#include "cfg.h"

extern char	*CONFIG_HOSTS_ALLOWED;
extern char	*CONFIG_HOSTNAMES;
extern char	*CONFIG_HOSTNAME_ITEM;
extern char	*CONFIG_HOST_METADATA;
extern char	*CONFIG_HOST_METADATA_ITEM;
extern int	CONFIG_ENABLE_REMOTE_COMMANDS;
extern int	CONFIG_UNSAFE_USER_PARAMETERS;
extern int	CONFIG_LISTEN_PORT;
extern int	CONFIG_REFRESH_ACTIVE_CHECKS;
extern char	*CONFIG_LISTEN_IP;
extern int	CONFIG_LOG_LEVEL;
extern int	CONFIG_MAX_LINES_PER_SECOND;
extern char	**CONFIG_ALIASES;
extern char	**CONFIG_USER_PARAMETERS;
extern char	*CONFIG_LOAD_MODULE_PATH;
extern char	**CONFIG_LOAD_MODULE;
#ifdef _WINDOWS
extern char	**CONFIG_PERF_COUNTERS;
extern char	**CONFIG_PERF_COUNTERS_EN;
#endif
extern char	*CONFIG_USER;

extern unsigned int	configured_tls_connect_mode;
extern unsigned int	configured_tls_accept_modes;

extern char	*CONFIG_TLS_CONNECT;
extern char	*CONFIG_TLS_ACCEPT;
extern char	*CONFIG_TLS_CA_FILE;
extern char	*CONFIG_TLS_CRL_FILE;
extern char	*CONFIG_TLS_SERVER_CERT_ISSUER;
extern char	*CONFIG_TLS_SERVER_CERT_SUBJECT;
extern char	*CONFIG_TLS_CERT_FILE;
extern char	*CONFIG_TLS_KEY_FILE;
extern char	*CONFIG_TLS_PSK_IDENTITY;
extern char	*CONFIG_TLS_PSK_FILE;

void	load_aliases(char **lines);
int	load_user_parameters(char **lines, char **err);
int	load_key_access_rule(const char *value, const struct cfg_line *cfg);
void	reload_user_parameters(unsigned char process_type, int process_num);
#ifdef _WINDOWS
void	load_perf_counters(const char **def_lines, const char **eng_lines);
#endif

#ifdef _AIX
void	tl_version(void);
#endif

#endif /* ZABBIX_ZBXCONF_H */
