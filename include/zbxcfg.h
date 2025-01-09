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

#ifndef ZABBIX_CFG_H
#define ZABBIX_CFG_H

#include "zbxalgo.h"

#define	ZBX_CFG_TYPE_INT		0
#define	ZBX_CFG_TYPE_STRING		1
#define	ZBX_CFG_TYPE_MULTISTRING	2
#define	ZBX_CFG_TYPE_UINT64		3
#define	ZBX_CFG_TYPE_STRING_LIST	4
#define	ZBX_CFG_TYPE_CUSTOM		5

#define	ZBX_CONF_PARM_OPT	0
#define	ZBX_CONF_PARM_MAND	1

/* config file parsing options */
#define	ZBX_CFG_FILE_REQUIRED	0
#define	ZBX_CFG_FILE_OPTIONAL	1

#define	ZBX_CFG_NOT_STRICT	0
#define	ZBX_CFG_STRICT		1

#define	ZBX_CFG_EXIT_FAILURE	0
#define	ZBX_CFG_NO_EXIT_FAILURE	1

#define	ZBX_CFG_ENVVAR_USE	0
#define	ZBX_CFG_ENVVAR_IGNORE	1

#define ZBX_PROXY_HEARTBEAT_FREQUENCY_MAX	SEC_PER_HOUR
#define ZBX_PROXY_LASTACCESS_UPDATE_FREQUENCY	5

typedef struct
{
	const char	*parameter;
	void		*variable;
	int		type;
	int		mandatory;
	zbx_uint64_t	min;
	zbx_uint64_t	max;
}
zbx_cfg_line_t;

typedef struct
{
	char		*ip;
	unsigned short	port;
	zbx_uint64_t	revision;
}
zbx_addr_t;

ZBX_PTR_VECTOR_DECL(addr_ptr, zbx_addr_t *)

typedef struct
{
	int	(*cfg_custom_parameter_parser_func)(const char *value, const zbx_cfg_line_t *cfg);
}
zbx_cfg_custom_parameter_parser_t;

void	zbx_init_library_cfg(unsigned char program_type, const char *cfg_file);
void	zbx_cfg_set_process_num(int num);

void	zbx_addr_copy(zbx_vector_addr_ptr_t *addr_to, const zbx_vector_addr_ptr_t *addr_from);
void	zbx_addr_free(zbx_addr_t *addr);

int	zbx_parse_cfg_file(const char *cfg_file, zbx_cfg_line_t *cfg, int optional, int strict, int noexit, int noenv);

int	zbx_check_cfg_feature_int(const char *parameter, int value, const char *feature);
int	zbx_check_cfg_feature_str(const char *parameter, const char *value, const char *feature);

typedef int	(*add_serveractive_host_f)(const zbx_vector_addr_ptr_t *addrs, zbx_vector_str_t *hostnames, void *data);
int	zbx_set_data_destination_hosts(const char *str, unsigned short port, const char *name,
		add_serveractive_host_f cb, zbx_vector_str_t *hostnames, void *data, char **error);

#endif
