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

#ifndef ZABBIX_DBCONFIG_CEP_H
#define ZABBIX_DBCONFIG_CEP_H

#include "dbconfig.h"
#include "zbxcacheconfig.h"
#include "zbxtypes_ext.h"
#include "zbxalgo.h"

#define CEP_RULE_ENABLED	0
#define CEP_RULE_DISABLED	1

typedef struct
{
	zbx_uint64_t	conditionid;
	int		type;
	int		operator;
	char		*value1_str;
	char		*value2_str;
	int		value_int;
}
zbx_cep_condition_t;

ZBX_VECTOR_DECL(cep_condition, zbx_cep_condition_t)

typedef struct
{
	int	duration;
	int	capacity;
	int	evaltype;
	int	group_by;
	char	*formula;
	char	*symptom_tag_num;
	char	*script;
	char	*group_tag;
}
zbx_cep_window_t;

struct zbx_cep_rule
{
	zbx_uint64_t		ruleid;
	int			window_type;
	int			evaltype;
	char			*formula;
	int			status;
	int			stop;
	int			sortorder;

	zbx_vector_cep_condition_t	conditions;

	zbx_uint64_t		revision;
	zbx_atomic_uint32_t	refcount;
};

typedef struct
{
	zbx_uint64_t	ruleid;
	zbx_cep_rule_t	*rule;
}
zbx_cep_rule_ref_t;

struct zbx_cep_config_handle
{
	zbx_vector_cep_rule_ptr_t	rules;

	zbx_uint64_t			revision;
	zbx_atomic_uint32_t		refcount;
};

typedef struct zbx_cep_config_handle *zbx_cep_config_handle_t;

typedef struct
{
	pthread_mutex_t		lock;

	zbx_cep_config_handle_t	handle;

	zbx_hashset_t		rules;
	zbx_hashset_t		condition_rule_index;

	zbx_uint64_t		revision;
	zbx_atomic_int_t	rules_num;
}
zbx_cep_config_t;

zbx_cep_config_t	*cep_config_create(void);
void	cep_config_destroy(zbx_cep_config_t *cep_config);

void	cep_config_sync(zbx_dbsync_t *rule_sync, zbx_dbsync_t *condition_sync, zbx_uint64_t revision);

#endif
