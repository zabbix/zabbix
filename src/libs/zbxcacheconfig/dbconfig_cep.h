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
	char	*name;
}
zbx_cep_args_name_t;

typedef struct
{
	char	*tag;
}
zbx_cep_args_tag_name_t;

typedef struct
{
	char	*tag;
	char	*value;
}
zbx_cep_args_tag_value_t;

typedef struct
{
	char	*old_tag;
	char	*new_tag;
}
zbx_cep_args_tag_pair_t;

typedef struct
{
	int	level;
}
zbx_cep_args_severity_t;

typedef struct
{
	char	*period;
}
zbx_cep_args_time_period_t;

typedef struct
{
	zbx_uint64_t	cep_operation_tagid;
	int		operator;
	char		*tag;
	char		*value;
}
zbx_cep_operation_tag_t;

ZBX_VECTOR_DECL(cep_operation_tag, zbx_cep_operation_tag_t)

typedef union
{
	zbx_cep_args_name_t		set_name;
	zbx_cep_args_severity_t		set_severity;
	zbx_cep_args_tag_value_t	add_tag;
	zbx_cep_args_tag_value_t	set_tag;
	zbx_cep_args_tag_value_t	set_tag_value;
	zbx_cep_args_tag_name_t		increase_tag_value;
	zbx_cep_args_tag_name_t		decrease_tag_value;
	zbx_cep_args_tag_name_t		remove_tag;
	zbx_cep_args_tag_pair_t		rename_tag;
}
zbx_cep_operation_args_t;

typedef struct
{
	zbx_uint64_t			operationid;
	int				type;
	int				execute_when;
	int				evaltype;
	int				sortorder;
	zbx_cep_operation_args_t	args;
	zbx_vector_cep_operation_tag_t	tags;
}
zbx_cep_operation_t;

ZBX_VECTOR_DECL(cep_operation, zbx_cep_operation_t)

/* keep union names in sync with CEP condition defines */
typedef union
{
	zbx_cep_args_name_t		event_name;
	zbx_cep_args_tag_name_t		tag_name;
	zbx_cep_args_tag_value_t	tag_value;
	zbx_cep_args_severity_t		severity;
	zbx_cep_args_name_t		host;
	zbx_cep_args_name_t		host_group;
	zbx_cep_args_time_period_t	time_period;
}
zbx_cep_condition_args_t;

typedef struct
{
	zbx_uint64_t			conditionid;
	int				type;
	int				operator;
	zbx_cep_condition_args_t	args;
}
zbx_cep_condition_t;

ZBX_VECTOR_DECL(cep_condition, zbx_cep_condition_t)

typedef union
{
	zbx_cep_args_tag_pair_t		tag_pair;
	zbx_cep_args_tag_name_t		old_tag;
	zbx_cep_args_tag_value_t	old_tag_value;
}
zbx_cep_window_condition_args_t;

typedef struct
{
	zbx_uint64_t			conditionid;
	int				type;
	int				operator;
	zbx_cep_window_condition_args_t	args;
}
zbx_cep_window_condition_t;

ZBX_VECTOR_DECL(cep_window_condition, zbx_cep_window_condition_t)

typedef struct
{
	int		type;
	int		evaltype;
	zbx_uint32_t	group_by;
	char		*duration;
	char		*capacity;
	char		*formula;
	char		*event_count_tag;
	char		*script;
	char		*group_tag;

	zbx_vector_cep_window_condition_t	conditions;
}
zbx_cep_window_t;

struct zbx_cep_rule
{
	zbx_uint64_t		ruleid;
	int			evaltype;
	char			*formula;
	int			status;
	int			stop;
	int			sortorder;

	zbx_cep_window_t	*window;

	zbx_vector_cep_condition_t	conditions;
	zbx_vector_cep_operation_t	operations;

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
	zbx_hashset_t		condition_rel;
	zbx_hashset_t		window_condition_rel;
	zbx_hashset_t		operation_rel;
	zbx_hashset_t		operation_tag_rel;

	zbx_uint64_t		revision;
	zbx_atomic_int_t	rules_num;
}
zbx_cep_config_t;

zbx_cep_config_t	*cep_config_create(void);
void	cep_config_destroy(zbx_cep_config_t *cep_config);

void	cep_config_sync(zbx_dbsync_t *rule_sync, zbx_dbsync_t *condition_sync, zbx_dbsync_t *window_sync,
		zbx_dbsync_t *window_condition_sync, zbx_dbsync_t *operation_sync, zbx_dbsync_t *operation_tag_sync,
		zbx_uint64_t revision);

#endif
