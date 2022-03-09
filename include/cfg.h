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

#ifndef ZABBIX_CFG_H
#define ZABBIX_CFG_H

#include "zbxalgo.h"

#define	TYPE_INT		0
#define	TYPE_STRING		1
#define	TYPE_MULTISTRING	2
#define	TYPE_UINT64		3
#define	TYPE_STRING_LIST	4
#define	TYPE_CUSTOM		5

#define	PARM_OPT	0
#define	PARM_MAND	1

/* config file parsing options */
#define	ZBX_CFG_FILE_REQUIRED	0
#define	ZBX_CFG_FILE_OPTIONAL	1

#define	ZBX_CFG_NOT_STRICT	0
#define	ZBX_CFG_STRICT		1

#define	ZBX_CFG_EXIT_FAILURE	0
#define	ZBX_CFG_NO_EXIT_FAILURE	1

#define ZBX_PROXY_HEARTBEAT_FREQUENCY_MAX	SEC_PER_HOUR
#define ZBX_PROXY_LASTACCESS_UPDATE_FREQUENCY	5

extern char	*CONFIG_FILE;
extern char	*CONFIG_LOG_TYPE_STR;
extern int	CONFIG_LOG_TYPE;
extern char	*CONFIG_LOG_FILE;
extern int	CONFIG_LOG_FILE_SIZE;
extern int	CONFIG_ALLOW_ROOT;
extern int	CONFIG_TIMEOUT;

struct cfg_line
{
	const char	*parameter;
	void		*variable;
	int		type;
	int		mandatory;
	zbx_uint64_t	min;
	zbx_uint64_t	max;
};

typedef struct
{
	char		*ip;
	unsigned short	port;
}
zbx_addr_t;

typedef struct
{
	int	(*cfg_custom_parameter_parser_func)(const char *value, const struct cfg_line *cfg);
}	cfg_custom_parameter_parser_t;

void	zbx_addr_copy(zbx_vector_ptr_t *addr_to, const zbx_vector_ptr_t *addr_from);
void	zbx_addr_free(zbx_addr_t *addr);

int	parse_cfg_file(const char *cfg_file, struct cfg_line *cfg, int optional, int strict, int noexit);

int	check_cfg_feature_int(const char *parameter, int value, const char *feature);
int	check_cfg_feature_str(const char *parameter, const char *value, const char *feature);

typedef int	(*add_serveractive_host_f)(const zbx_vector_ptr_t *addrs, zbx_vector_str_t *hostnames, void *data);
int	zbx_set_data_destination_hosts(char *str, unsigned short port, const char *name, add_serveractive_host_f cb,
		zbx_vector_str_t *hostnames, void *data, char **error);

#endif
