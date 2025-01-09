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

#ifndef ZABBIX_PREPROC_SNMP_H
#define ZABBIX_PREPROC_SNMP_H

#include "pp_cache.h"
#include "zbxalgo.h"

#ifdef HAVE_NETSNMP
#	define SNMP_NO_DEBUGGING		/* disabling debugging messages from Net-SNMP library */
#	include <net-snmp/net-snmp-config.h>
#	include <net-snmp/net-snmp-includes.h>
#endif

typedef struct
{
	char	*field_name;
	char	*oid_prefix;
	int	format_flag;
}
zbx_snmp_walk_to_json_param_t;

ZBX_VECTOR_DECL(snmp_walk_to_json_param, zbx_snmp_walk_to_json_param_t)

typedef struct
{
	char		*key;
	char		*value;
}
zbx_snmp_walk_json_output_value_t;

ZBX_PTR_VECTOR_DECL(snmp_walk_to_json_output_val, zbx_snmp_walk_json_output_value_t *)

typedef enum
{
	ZBX_SNMP_TYPE_UNDEFINED,
	ZBX_SNMP_TYPE_HEX,
	ZBX_SNMP_TYPE_BITS,
	ZBX_SNMP_TYPE_STRING,
}
zbx_snmp_type_t;

typedef struct
{
	char		*oid;
	char		*value;
	zbx_snmp_type_t	type;
}
zbx_snmp_value_pair_t;

ZBX_PTR_VECTOR_DECL(snmp_value_pair, zbx_snmp_value_pair_t *)

typedef struct
{
	zbx_hashset_t	pairs;
}
zbx_snmp_value_cache_t;

typedef struct
{
	char				*key;
	zbx_vector_snmp_value_pair_t	values;
}
zbx_snmp_walk_json_output_obj_t;

int	zbx_snmp_value_cache_init(zbx_snmp_value_cache_t *cache, const char *data, char **error);
void	zbx_snmp_value_cache_clear(zbx_snmp_value_cache_t *cache);

int	item_preproc_snmp_walk_to_value(zbx_pp_cache_t *cache, zbx_variant_t *value, const char *params, char **errmsg);
int	item_preproc_snmp_walk_to_json(zbx_variant_t *value, const char *params, char **errmsg);
int	item_preproc_snmp_get_to_value(zbx_variant_t *value, const char *params, char **errmsg);

void	preproc_init_snmp(void);
void	preproc_shutdown_snmp(void);

#endif
