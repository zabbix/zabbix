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

#ifndef ZABBIX_SERVICE_MANAGER_IMPL_H
#define ZABBIX_SERVICE_MANAGER_IMPL_H

#include "zbxalgo.h"
#include "zbxtime.h"

#define ZBX_SERVICE_STATUS_OK		-1

#define ZBX_SERVICE_STATUS_PROPAGATION_AS_IS	0
#define ZBX_SERVICE_STATUS_PROPAGATION_INCREASE	1
#define ZBX_SERVICE_STATUS_PROPAGATION_DECREASE	2
#define ZBX_SERVICE_STATUS_PROPAGATION_IGNORE	3
#define ZBX_SERVICE_STATUS_PROPAGATION_FIXED	4

#define ZBX_SERVICE_STATUS_RULE_TYPE_N_GE	0
#define ZBX_SERVICE_STATUS_RULE_TYPE_NP_GE	1
#define ZBX_SERVICE_STATUS_RULE_TYPE_N_L	2
#define ZBX_SERVICE_STATUS_RULE_TYPE_NP_L	3
#define ZBX_SERVICE_STATUS_RULE_TYPE_W_GE	4
#define ZBX_SERVICE_STATUS_RULE_TYPE_WP_GE	5
#define ZBX_SERVICE_STATUS_RULE_TYPE_W_L	6
#define ZBX_SERVICE_STATUS_RULE_TYPE_WP_L	7

typedef struct
{
	zbx_uint64_t	eventid;
	zbx_uint64_t	service_problemid;
	zbx_uint64_t	serviceid;
	int		severity;
	zbx_timespec_t	ts;
}
zbx_service_problem_t;

ZBX_PTR_VECTOR_DECL(service_problem_ptr, zbx_service_problem_t *)

void	zbx_service_problem_free(zbx_service_problem_t *service_problem);

typedef struct
{
	zbx_uint64_t	servicetagid;
	zbx_uint64_t	serviceid;
	char		*name;
	char		*value;
	int		revision;
}
zbx_service_tag_t;

ZBX_PTR_VECTOR_DECL(service_tag_ptr, zbx_service_tag_t *)

typedef struct
{
	zbx_uint64_t	service_ruleid;
	int		type;
	int		limit_value;
	int		limit_status;
	int		new_status;
	int		revision;
}
zbx_service_rule_t;

ZBX_PTR_VECTOR_DECL(service_rule_ptr, zbx_service_rule_t *)
void	zbx_service_rule_free(zbx_service_rule_t *service_rule);

typedef struct zbx_service_s zbx_service_t;

typedef struct
{
	zbx_uint64_t	service_problem_tagid;
	zbx_uint64_t	current_eventid;
	zbx_service_t	*service;
	char		*tag;
	char		*value;
	int		op;
	int		revision;
}
zbx_service_problem_tag_t;

ZBX_PTR_VECTOR_DECL(service_problem_tag_ptr, zbx_service_problem_tag_t *)
ZBX_PTR_VECTOR_DECL(service_ptr, zbx_service_t *)

struct zbx_service_s
{
	zbx_uint64_t				serviceid;
	zbx_vector_service_tag_ptr_t		tags;
	zbx_vector_service_problem_ptr_t	service_problems;
	zbx_vector_service_problem_tag_ptr_t	service_problem_tags;
	zbx_vector_service_ptr_t		children;
	zbx_vector_service_ptr_t		parents;
	zbx_vector_service_rule_ptr_t		status_rules;
	char					*name;
	int					status;
	int					algorithm;
	int					revision;
	int					weight;
	int					propagation_rule;
	int					propagation_value;
};

/* status update queue items */
typedef struct
{
	/* the update source id */
	zbx_uint64_t	sourceid;
	/* the servicealarmid that was assigned when flushing alarms */
	zbx_uint64_t	servicealarmid;
	/* the new status */
	int		status;
	/* timestamp */
	int		clock;
}
zbx_status_update_t;

/* service update queue items */
typedef struct
{
	const zbx_service_t	*service;
	int			old_status;
	zbx_timespec_t		ts;

	/* the last status update source */
	zbx_status_update_t	*alarm;
}
zbx_service_update_t;

ZBX_PTR_VECTOR_DECL(service_update_ptr, zbx_service_update_t *)

typedef struct
{
	zbx_uint64_t	conditionid;
	zbx_uint64_t	actionid;

	unsigned char	conditiontype;
	unsigned char	op;
	char		*value;
	char		*value2;

	int		revision;
}
zbx_service_action_condition_t;

ZBX_PTR_VECTOR_DECL(service_action_condition_ptr, zbx_service_action_condition_t *)

typedef struct
{
	zbx_uint64_t					actionid;
	unsigned char					evaltype;
	char						*formula;
	zbx_vector_service_action_condition_ptr_t	conditions;

	int						revision;
}
zbx_service_action_t;

ZBX_PTR_VECTOR_DECL(service_action_ptr, zbx_service_action_t *)

int	service_get_status(const zbx_service_t	*service, int *status);
int	service_get_main_status(const zbx_service_t *service);
int	service_get_rule_status(const zbx_service_t *service, const zbx_service_rule_t *rule);
void	service_get_rootcause_eventids(const zbx_service_t *parent, zbx_vector_uint64_t *eventids);

#endif
