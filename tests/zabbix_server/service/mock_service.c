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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"
#include "zbxalgo.h"
#include "zbxself.h"

#include "mock_service.h"

zbx_uint64_t __wrap_DCget_nextid(const char *table_name, int num);
void	*__wrap_zbx_add_event(unsigned char source, unsigned char object, zbx_uint64_t objectid,
		const zbx_timespec_t *timespec, int value, const char *trigger_description,
		const char *trigger_expression, const char *trigger_recovery_expression, unsigned char trigger_priority,
		unsigned char trigger_type, const zbx_vector_ptr_t *trigger_tags,
		unsigned char trigger_correlation_mode, const char *trigger_correlation_tag,
		unsigned char trigger_value, const char *trigger_opdata, const char *event_name, const char *error);
int	__wrap_zbx_process_events(zbx_vector_ptr_t *trigger_diff, zbx_vector_uint64_t *triggerids_lock);
void	__wrap_zbx_clean_events(void);
int	__wrap_zbx_interface_availability_is_set(const void *ia);


/* stubs to satisfy hard link dependenceies */

int	get_process_info_by_thread(int local_server_num, unsigned char *local_process_type, int *local_process_num);

int	CONFIG_SERVICEMAN_SYNC_FREQUENCY = 0;

pid_t	*threads;
int	threads_num;

void	update_selfmon_counter(unsigned char state)
{
	ZBX_UNUSED(state);
}

int	get_process_info_by_thread(int local_server_num, unsigned char *local_process_type, int *local_process_num)
{
	ZBX_UNUSED(local_server_num);
	ZBX_UNUSED(local_process_type);
	ZBX_UNUSED(local_process_num);
	return 0;
}

int	MAIN_ZABBIX_ENTRY(int flags)
{
	ZBX_UNUSED(flags);
	return 0;
}

/* service tree mock */

typedef struct
{
	zbx_hashset_t	services;
}
zbx_mock_service_cache_t;

static zbx_mock_service_cache_t	cache;

static zbx_hash_t	service_hash_func(const void *d)
{
	const zbx_service_t	*s = (const zbx_service_t *)d;

	return ZBX_DEFAULT_STRING_HASH_FUNC(s->name);
}

static int	service_compare_func(const void *d1, const void *d2)
{
	const zbx_service_t	*s1 = (const zbx_service_t *)d1;
	const zbx_service_t	*s2 = (const zbx_service_t *)d2;

	return strcmp(s1->name, s2->name);
}

zbx_service_t	*mock_get_service(const char *name)
{
	zbx_service_t	service_local;

	service_local.name = (char *)name;
	return zbx_hashset_search(&cache.services, &service_local);
}

void	mock_init_service_cache(const char *path)
{
	zbx_mock_handle_t	hservices, hservice, hchildren, hparents, hname, hevents, hevent, halgo, hweight, hprop,
				hrules, hrule;
	int			service_num = 0;
	zbx_mock_error_t	err;
	zbx_service_t		*service, service_local, *child, *parent;
	const char		*value;
	zbx_hashset_iter_t	iter;

	zbx_hashset_create(&cache.services, 100, service_hash_func, service_compare_func);

	/* load service objects in cache */

	hservices = zbx_mock_get_parameter_handle(path);
	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hservices, &hservice))))
	{
		if (ZBX_MOCK_SUCCESS != err)
			fail_msg("cannot read service #%d", service_num);

		memset(&service_local, 0, sizeof(zbx_service_t));
		service_local.name = zbx_strdup(NULL, zbx_mock_get_object_member_string(hservice, "name"));
		service = (zbx_service_t *)zbx_hashset_insert(&cache.services, &service_local, sizeof(service_local));

		zbx_vector_ptr_create(&service->children);
		zbx_vector_ptr_create(&service->parents);
		zbx_vector_ptr_create(&service->service_problem_tags);
		zbx_vector_ptr_create(&service->service_problems);
		zbx_vector_ptr_create(&service->status_rules);
		zbx_vector_ptr_create(&service->tags);

		service->status = zbx_mock_get_object_member_int(hservice, "status");

		if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hservice, "algorithm", &halgo))
		{
			if (ZBX_MOCK_SUCCESS != zbx_mock_string(halgo, &value))
				fail_msg("cannot read service '%s' algorithm", service->name);

			if (0 == strcmp(value, "MIN"))
				service->algorithm = ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ONE;
			else if (0 == strcmp(value, "MAX"))
				service->algorithm = ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ALL;
			else if (0 == strcmp(value, "OK"))
				service->algorithm = ZBX_SERVICE_STATUS_CALC_SET_OK;
			else
				fail_msg("unknown service '%s' algorithm '%s'", service->name, value);
		}
		else
			service->algorithm = ZBX_SERVICE_STATUS_CALC_MOST_CRITICAL_ONE;

		if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hservice, "weight", &hweight))
		{
			if (ZBX_MOCK_SUCCESS != zbx_mock_int(hweight, &service->weight))
				fail_msg("cannot read service '%s' weight", service->name);
		}

		if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hservice, "propagation", &hprop))
		{
			value = zbx_mock_get_object_member_string(hprop, "action");

			if (0 == strcmp(value, "SET"))
			{
				service->propagation_rule = ZBX_SERVICE_STATUS_PROPAGATION_FIXED;
				service->propagation_value = zbx_mock_get_object_member_int(hprop, "value");
			}
			else if (0 == strcmp(value, "KEEP"))
			{
				service->propagation_rule = ZBX_SERVICE_STATUS_PROPAGATION_AS_IS;
			}
			else if (0 == strcmp(value, "INCREASE"))
			{
				service->propagation_rule = ZBX_SERVICE_STATUS_PROPAGATION_INCREASE;
				service->propagation_value = zbx_mock_get_object_member_int(hprop, "value");
			}
			else if (0 == strcmp(value, "DECREASE"))
			{
				service->propagation_rule = ZBX_SERVICE_STATUS_PROPAGATION_DECREASE;
				service->propagation_value = zbx_mock_get_object_member_int(hprop, "value");
			}
			else if (0 == strcmp(value, "IGNORE"))
				service->propagation_rule = ZBX_SERVICE_STATUS_PROPAGATION_IGNORE;
			else
				fail_msg("unknown service '%s' propagation action '%s'", service->name, value);
		}

		if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hservice, "rules", &hrules))
		{
			zbx_service_rule_t	*rule;

			while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hrules, &hrule))))
			{
				if (ZBX_MOCK_SUCCESS != err)
					fail_msg("cannot read service '%s' status rules", service->name);

				rule = (zbx_service_rule_t *)zbx_malloc(NULL, sizeof(zbx_service_rule_t));
				memset(rule, 0, sizeof(zbx_service_rule_t));
				value = zbx_mock_get_object_member_string(hrule, "type");
				if (0 == strcmp(value, "N_GE"))
					rule->type = ZBX_SERVICE_STATUS_RULE_TYPE_N_GE;
				else if (0 == strcmp(value, "NP_GE"))
					rule->type = ZBX_SERVICE_STATUS_RULE_TYPE_NP_GE;
				else if (0 == strcmp(value, "N_LT"))
					rule->type = ZBX_SERVICE_STATUS_RULE_TYPE_N_L;
				else if (0 == strcmp(value, "NP_LT"))
					rule->type = ZBX_SERVICE_STATUS_RULE_TYPE_NP_L;
				else if (0 == strcmp(value, "W_GE"))
					rule->type = ZBX_SERVICE_STATUS_RULE_TYPE_W_GE;
				else if (0 == strcmp(value, "WP_GE"))
					rule->type = ZBX_SERVICE_STATUS_RULE_TYPE_WP_GE;
				else if (0 == strcmp(value, "W_LT"))
					rule->type = ZBX_SERVICE_STATUS_RULE_TYPE_W_L;
				else if (0 == strcmp(value, "WP_LT"))
					rule->type = ZBX_SERVICE_STATUS_RULE_TYPE_WP_L;
				else
					fail_msg("unsupported service '%s' rule type '%s'", service->name, value);
				rule->limit_status = zbx_mock_get_object_member_int(hrule, "limit");
				rule->limit_value = zbx_mock_get_object_member_int(hrule, "value");
				rule->new_status = zbx_mock_get_object_member_int(hrule, "status");
				zbx_vector_ptr_append(&service->status_rules, rule);
			}
		}

		if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hservice, "events", &hevents))
		{
			zbx_service_problem_t	*problem;

			while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hevents, &hevent))))
			{
				if (ZBX_MOCK_SUCCESS != err)
					fail_msg("cannot read service '%s' events", service->name);

				problem = (zbx_service_problem_t *)zbx_malloc(NULL, sizeof(zbx_service_problem_t));
				memset(problem, 0, sizeof(zbx_service_problem_t));
				problem->eventid = zbx_mock_get_object_member_uint64(hevent, "id");
				problem->severity = zbx_mock_get_object_member_int(hevent, "severity");
				zbx_vector_ptr_append(&service->service_problems, problem);
			}
		}

		service_num++;
	}

	/* set service relations */

	hservices = zbx_mock_get_parameter_handle(path);
	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hservices, &hservice))))
	{
		if (ZBX_MOCK_SUCCESS != err)
			fail_msg("cannot read service");

		if (NULL == (service = mock_get_service(zbx_mock_get_object_member_string(hservice, "name"))))
			fail_msg("failed to cache service '%s'", zbx_mock_get_object_member_string(hservice, "name"));

		if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hservice, "children", &hchildren))
		{
			while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hchildren, &hname))))
			{
				if (ZBX_MOCK_SUCCESS != err || ZBX_MOCK_SUCCESS != zbx_mock_string(hname, &value))
					fail_msg("cannot read service '%s' children", service->name);

				if (NULL == (child = mock_get_service(value)))
				{
					fail_msg("cannot set service '%s' child '%s': no such service", service->name,
							value);
				}

				zbx_vector_ptr_append(&service->children, child);
				zbx_vector_ptr_append(&child->parents, service);
			}
		}

		if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hservice, "parents", &hparents))
		{
			while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hparents, &hname))))
			{
				if (ZBX_MOCK_SUCCESS != err || ZBX_MOCK_SUCCESS != zbx_mock_string(hname, &value))
					fail_msg("cannot read service '%s' parents", service->name);

				if (NULL == (parent = mock_get_service(value)))
				{
					fail_msg("cannot set service '%s' parent '%s': no such service", service->name,
							value);
				}

				zbx_vector_ptr_append(&service->parents, parent);
				zbx_vector_ptr_append(&parent->children, service);
			}
		}

		service_num++;
	}

	zbx_hashset_iter_reset(&cache.services, &iter);

	/* remove duplicate parent/children references */
	while (NULL != (service = (zbx_service_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_ptr_sort(&service->parents, ZBX_DEFAULT_PTR_COMPARE_FUNC);
		zbx_vector_ptr_uniq(&service->parents, ZBX_DEFAULT_PTR_COMPARE_FUNC);

		zbx_vector_ptr_sort(&service->children, ZBX_DEFAULT_PTR_COMPARE_FUNC);
		zbx_vector_ptr_uniq(&service->children, ZBX_DEFAULT_PTR_COMPARE_FUNC);
	}
}

void	mock_destroy_service_cache(void)
{
	zbx_hashset_iter_t	iter;
	zbx_service_t		*service;

	zbx_hashset_iter_reset(&cache.services, &iter);

	while (NULL != (service = (zbx_service_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_ptr_destroy(&service->children);
		zbx_vector_ptr_destroy(&service->parents);
		zbx_vector_ptr_destroy(&service->service_problem_tags);
		zbx_vector_ptr_destroy(&service->tags);
		zbx_vector_ptr_clear_ext(&service->service_problems, zbx_ptr_free);
		zbx_vector_ptr_destroy(&service->service_problems);
		zbx_vector_ptr_clear_ext(&service->status_rules, zbx_ptr_free);
		zbx_vector_ptr_destroy(&service->status_rules);

		zbx_free(service->name);
	}

	zbx_hashset_destroy(&cache.services);
}

/* function stubs to cut off library dependencies */

zbx_uint64_t	__wrap_DCget_nextid(const char *table_name, int num)
{
	ZBX_UNUSED(table_name);
	ZBX_UNUSED(num);

	return 0;
}

void	*__wrap_zbx_add_event(unsigned char source, unsigned char object, zbx_uint64_t objectid,
		const zbx_timespec_t *timespec, int value, const char *trigger_description,
		const char *trigger_expression, const char *trigger_recovery_expression, unsigned char trigger_priority,
		unsigned char trigger_type, const zbx_vector_ptr_t *trigger_tags,
		unsigned char trigger_correlation_mode, const char *trigger_correlation_tag,
		unsigned char trigger_value, const char *trigger_opdata, const char *event_name, const char *error)
{
	ZBX_UNUSED(source);
	ZBX_UNUSED(object);
	ZBX_UNUSED(objectid);
	ZBX_UNUSED(timespec);
	ZBX_UNUSED(value);
	ZBX_UNUSED(trigger_description);
	ZBX_UNUSED(trigger_expression);
	ZBX_UNUSED(trigger_recovery_expression);
	ZBX_UNUSED(trigger_priority);
	ZBX_UNUSED(trigger_type);
	ZBX_UNUSED(trigger_tags);
	ZBX_UNUSED(trigger_correlation_mode);
	ZBX_UNUSED(trigger_correlation_tag);
	ZBX_UNUSED(trigger_value);
	ZBX_UNUSED(trigger_opdata);
	ZBX_UNUSED(event_name);
	ZBX_UNUSED(error);

	return NULL;
}

int	__wrap_zbx_process_events(zbx_vector_ptr_t *trigger_diff, zbx_vector_uint64_t *triggerids_lock)
{
	ZBX_UNUSED(trigger_diff);
	ZBX_UNUSED(triggerids_lock);

	return 0;
}

void	__wrap_zbx_clean_events(void)
{
}

int	__wrap_zbx_interface_availability_is_set(const void *ia)
{
	ZBX_UNUSED(ia);

	return FAIL;
}


