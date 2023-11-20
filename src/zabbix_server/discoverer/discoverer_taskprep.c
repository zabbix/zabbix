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

#include "discoverer_taskprep.h"

#include "zbxexpression.h"
#include "zbx_discoverer_constants.h"

#define ZBX_DISCOVERER_IPRANGE_LIMIT	(1 << 16)

static void	dcheck_copy(const zbx_dc_dcheck_t *src, zbx_dc_dcheck_t *dst)
{
	dst->dcheckid = src->dcheckid;
	dst->druleid = src->druleid;
	dst->key_ = zbx_strdup(NULL, src->key_);
	dst->ports = zbx_strdup(NULL, src->ports);
	dst->uniq = src->uniq;
	dst->type = src->type;
	dst->allow_redirect = src->allow_redirect;
	dst->timeout = src->timeout;

	if (SVC_SNMPv1 == src->type || SVC_SNMPv2c == src->type || SVC_SNMPv3 == src->type)
	{
		dst->snmp_community = zbx_strdup(NULL, src->snmp_community);
		dst->snmpv3_securityname = zbx_strdup(NULL, src->snmpv3_securityname);
		dst->snmpv3_securitylevel = src->snmpv3_securitylevel;
		dst->snmpv3_authpassphrase = zbx_strdup(NULL, src->snmpv3_authpassphrase);
		dst->snmpv3_privpassphrase = zbx_strdup(NULL, src->snmpv3_privpassphrase);
		dst->snmpv3_authprotocol = src->snmpv3_authprotocol;
		dst->snmpv3_privprotocol = src->snmpv3_privprotocol;
		dst->snmpv3_contextname = zbx_strdup(NULL, src->snmpv3_contextname);
	}
}

static zbx_dc_dcheck_t	*dcheck_clone_get(zbx_dc_dcheck_t *dcheck, zbx_vector_dc_dcheck_ptr_t *dchecks_common)
{
	zbx_dc_dcheck_t	*dcheck_ptr, dcheck_cmp = {.dcheckid = dcheck->dcheckid};
	int		idx;

	if (FAIL != (idx = zbx_vector_dc_dcheck_ptr_search(dchecks_common, &dcheck_cmp,
							ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
	{
		return dchecks_common->values[idx];
	}

	dcheck_ptr = (zbx_dc_dcheck_t*)zbx_malloc(NULL, sizeof(zbx_dc_dcheck_t));
	dcheck_copy(dcheck, dcheck_ptr);

	if (SVC_SNMPv1 == dcheck_ptr->type || SVC_SNMPv2c == dcheck_ptr->type ||
			SVC_SNMPv3 == dcheck_ptr->type)
	{
		zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
				NULL, NULL, NULL, NULL, &dcheck_ptr->snmp_community,
				ZBX_MACRO_TYPE_COMMON, NULL, 0);
		zbx_substitute_key_macros(&dcheck_ptr->key_, NULL, NULL, NULL, NULL,
				ZBX_MACRO_TYPE_SNMP_OID, NULL, 0);

		if (SVC_SNMPv3 == dcheck_ptr->type)
		{
			zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL,
					NULL, NULL, NULL, NULL, NULL, NULL,
					&dcheck_ptr->snmpv3_securityname, ZBX_MACRO_TYPE_COMMON, NULL,
					0);
			zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL,
					NULL, NULL, NULL, NULL, NULL, NULL,
					&dcheck_ptr->snmpv3_authpassphrase, ZBX_MACRO_TYPE_COMMON, NULL,
					0);
			zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL,
					NULL, NULL, NULL, NULL, NULL, NULL,
					&dcheck_ptr->snmpv3_privpassphrase, ZBX_MACRO_TYPE_COMMON, NULL,
					0);
			zbx_substitute_simple_macros_unmasked(NULL, NULL, NULL, NULL, NULL, NULL,
					NULL, NULL, NULL, NULL, NULL, NULL,
					&dcheck_ptr->snmpv3_contextname, ZBX_MACRO_TYPE_COMMON, NULL,
					0);
		}
	}

	zbx_vector_dc_dcheck_ptr_append(dchecks_common, dcheck_ptr);

	return dcheck_ptr;
}

static int	dcheck_is_async(zbx_dc_dcheck_t *dcheck)
{
	switch(dcheck->type)
	{
	case SVC_AGENT:
	case SVC_ICMPPING:
	case SVC_SNMPv1:
	case SVC_SNMPv2c:
	case SVC_SNMPv3:
	case SVC_TCP:
	case SVC_SMTP:
	case SVC_FTP:
	case SVC_POP:
	case SVC_NNTP:
	case SVC_IMAP:
	case SVC_HTTP:
	case SVC_SSH:
		return SUCCEED;
	default:
		return FAIL;
	}
}

static zbx_uint64_t	process_check_range(const zbx_dc_drule_t *drule, zbx_dc_dcheck_t *dcheck,
		zbx_vector_iprange_t *ipranges, unsigned char *need_resolve, zbx_hashset_t *tasks)
{
	zbx_discoverer_task_t	task_local, *task;
	zbx_vector_portrange_t	port_ranges;
	zbx_task_range_t	range_cmp = {.id = 0 };
	int			port = 0, checks_count = 0;

	if (SVC_ICMPPING != dcheck->type)
	{
		zbx_vector_portrange_create(&port_ranges);
		dcheck_port_ranges_get(dcheck->ports, &port_ranges);

		while (SUCCEED == zbx_portrange_uniq_next(port_ranges.values, port_ranges.values_num, &port))
		{
			checks_count++;
		}

		zbx_vector_portrange_destroy(&port_ranges);
	}
	else
		checks_count = 1;

	task_local.addr_type = DISCOVERY_ADDR_RANGE;
	task_local.addr.range = &range_cmp;
	task_local.port = 0;
	zbx_vector_dc_dcheck_ptr_create(&task_local.dchecks);
	zbx_vector_dc_dcheck_ptr_append(&task_local.dchecks, dcheck);

	if (NULL != (task = zbx_hashset_search(tasks, &task_local)))
	{
		zbx_vector_dc_dcheck_ptr_destroy(&task_local.dchecks);

		if (FAIL == zbx_vector_dc_dcheck_ptr_search(&task->dchecks, dcheck,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC))
		{
			zbx_vector_dc_dcheck_ptr_append(&task->dchecks, dcheck);
			task->addr.range->state.checks_per_ip += checks_count;
		}
	}
	else
	{
		task_local.addr.range = (zbx_task_range_t *)zbx_malloc(NULL, sizeof(zbx_task_range_t));
		memset(task_local.addr.range, 0, sizeof(zbx_task_range_t));
		task_local.addr.range->ipranges = ipranges;
		task_local.addr.range->state.count = DISCOVERER_JOB_TASKS_SKIP_LIMIT;
		task_local.addr.range->state.checks_per_ip += checks_count;
		task_local.unique_dcheckid = drule->unique_dcheckid;
		task_local.resolve_dns = *need_resolve;
		zbx_hashset_insert(tasks, &task_local, sizeof(zbx_discoverer_task_t));
	}

	if (1 == *need_resolve)
		*need_resolve = 0;

	return checks_count;
}

static zbx_uint64_t	process_check(const zbx_dc_drule_t *drule, zbx_dc_dcheck_t *dcheck, const char *ip,
		unsigned char *need_resolve, zbx_uint64_t *queue_capacity, zbx_hashset_t *tasks)
{
	int			port = 0;
	zbx_uint64_t		checks_count = 0;
	zbx_vector_portrange_t	port_ranges;

	zbx_vector_portrange_create(&port_ranges);
	dcheck_port_ranges_get(dcheck->ports, &port_ranges);

	while (SUCCEED == zbx_portrange_uniq_next(port_ranges.values, port_ranges.values_num, &port))
	{
		zbx_discoverer_task_t	task_local, *task;

		task_local.addr_type = DISCOVERY_ADDR_IP;
		task_local.addr.ip = (char *)ip;
		task_local.port = (unsigned short)port;

		if (NULL == (task = zbx_hashset_search(tasks, &task_local)))
		{
			task_local.addr.ip = zbx_strdup(NULL, ip);
			task_local.unique_dcheckid = drule->unique_dcheckid;
			task_local.resolve_dns = *need_resolve;

			if (1 == *need_resolve)
				*need_resolve = 0;

			zbx_vector_dc_dcheck_ptr_create(&task_local.dchecks);
			zbx_vector_dc_dcheck_ptr_append(&task_local.dchecks, dcheck);
			zbx_hashset_insert(tasks, &task_local, sizeof(zbx_discoverer_task_t));
		}
		else
		{
			zbx_vector_dc_dcheck_ptr_append(&task->dchecks, dcheck);
		}

		(*queue_capacity)--;
		checks_count++;
	}

	zbx_vector_portrange_destroy(&port_ranges);

	return checks_count;
}

static zbx_uint64_t	process_checks(const zbx_dc_drule_t *drule, char *ip, int unique, unsigned char *need_resolve,
		zbx_uint64_t *queue_capacity, zbx_hashset_t *tasks, zbx_vector_dc_dcheck_ptr_t *dchecks_common,
		zbx_vector_iprange_t *ipranges)
{
	int		i;
	zbx_uint64_t	checks_count = 0;

	for (i = 0; i < drule->dchecks.values_num; i++)
	{
		zbx_dc_dcheck_t	*dcheck_common, *dcheck = (zbx_dc_dcheck_t*)drule->dchecks.values[i];

		if (0 == *queue_capacity)
			break;

		if (0 != drule->unique_dcheckid &&
				((1 == unique && drule->unique_dcheckid != dcheck->dcheckid) ||
				(0 == unique && drule->unique_dcheckid == dcheck->dcheckid)))
		{
			continue;
		}

		dcheck_common = dcheck_clone_get(dcheck, dchecks_common);

		if (SUCCEED == dcheck_is_async(dcheck))
		{
			(*queue_capacity)--;
			checks_count += process_check_range(drule, dcheck_common, ipranges, need_resolve, tasks);
		}
		else
		{
			checks_count += process_check(drule, dcheck_common, ip, need_resolve, queue_capacity, tasks);
		}
	}

	return checks_count;
}

static void	process_rangetask_copy(zbx_discoverer_task_t *task, zbx_task_range_t *range, zbx_hashset_t *tasks)
{
	zbx_discoverer_task_t	*task_out = zbx_hashset_insert(tasks, task, sizeof(zbx_discoverer_task_t));

	zbx_vector_dc_dcheck_ptr_create(&task_out->dchecks);
	zbx_vector_dc_dcheck_ptr_append_array(&task_out->dchecks, task->dchecks.values, task->dchecks.values_num);
	task_out->addr.range = (zbx_task_range_t *)zbx_malloc(NULL, sizeof(zbx_task_range_t));
	*task_out->addr.range = *range;
	task->addr.range->id++;
}

static void	process_task_range_split(zbx_hashset_t *tasks_src, zbx_hashset_t *tasks_dst)
{
	zbx_discoverer_task_t	*task;
	zbx_hashset_iter_t	iter;
	zbx_vector_portrange_t	port_ranges;
	int			total = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks_src:%d tasks_dst:%d", __func__, tasks_src->num_data,
			tasks_dst->num_data);

	zbx_vector_portrange_create(&port_ranges);
	zbx_hashset_iter_reset(tasks_src, &iter);

	while (NULL != (task = (zbx_discoverer_task_t*)zbx_hashset_iter_next(&iter)))
	{
		zbx_task_range_t	range;

		if (DISCOVERY_ADDR_IP == task->addr_type || SVC_SNMPv3 == task->dchecks.values[0]->type)
		{
			zbx_hashset_insert(tasks_dst, task, sizeof(zbx_discoverer_task_t));
			zbx_hashset_iter_remove(&iter);
			continue;
		}

		task->addr.range->state.count = 0;
		range = *task->addr.range;

		while (SUCCEED == zbx_iprange_uniq_iter(task->addr.range->ipranges->values,
				task->addr.range->ipranges->values_num, &task->addr.range->state.index_ip,
				task->addr.range->state.ipaddress))

		{

			for (; task->addr.range->state.dcheck_index < task->dchecks.values_num;
					task->addr.range->state.dcheck_index++)
			{
				zbx_dc_dcheck_t	*dcheck = task->dchecks.values[task->addr.range->state.dcheck_index];

				dcheck_port_ranges_get(dcheck->ports, &port_ranges);

				while (SUCCEED == zbx_portrange_uniq_iter(port_ranges.values, port_ranges.values_num,
						&task->addr.range->state.index_port, &task->addr.range->state.port))
				{
					if (DISCOVERER_JOB_TASKS_INPROGRESS_MAX == range.state.count)
					{
						process_rangetask_copy(task, &range, tasks_dst);
						range = *task->addr.range;
					}

					range.state.count++;
					total++;
				}

				task->addr.range->state.port = 0;
				zbx_vector_portrange_clear(&port_ranges);
			}

			task->addr.range->state.dcheck_index = 0;
		}

		if (0 != range.state.count)
			process_rangetask_copy(task, &range, tasks_dst);

		discoverer_task_clear(task);
		zbx_hashset_iter_remove(&iter);
	}

	zbx_vector_portrange_destroy(&port_ranges);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() tasks_src:%d tasks_dst:%d total:%d", __func__, tasks_src->num_data,
			tasks_dst->num_data, total);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process single discovery rule                                     *
 *                                                                            *
 ******************************************************************************/
void	process_rule(zbx_dc_drule_t *drule, zbx_uint64_t *queue_capacity, zbx_hashset_t *tasks,
		zbx_hashset_t *check_counts, zbx_vector_dc_dcheck_ptr_t *dchecks_common, zbx_vector_iprange_t *ipranges)
{
	zbx_hashset_t		tasks_local, *tasks_ptr;
	char			ip[ZBX_INTERFACE_IP_LEN_MAX], *comma, *start = drule->iprange;
	int			i = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() rule:'%s' range:'%s'", __func__, drule->name, drule->iprange);

	for (i = 0; '\0' != start[i]; start[i] == ',' ? i++ : *start++);

	zbx_vector_iprange_reserve(ipranges, i);

	for (start = drule->iprange; '\0' != *start;)
	{
		zbx_iprange_t	ipr;

		if (NULL != (comma = strchr(start, ',')))
			*comma = '\0';

		zabbix_log(LOG_LEVEL_DEBUG, "%s() range:'%s'", __func__, start);

		if (SUCCEED != zbx_iprange_parse(&ipr, start))
		{
			zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s\": wrong format of IP range \"%s\"",
					drule->name, start);
			goto next;
		}

		if (ZBX_DISCOVERER_IPRANGE_LIMIT < zbx_iprange_volume(&ipr))
		{
			zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s\": IP range \"%s\" exceeds %d address limit",
					drule->name, start, ZBX_DISCOVERER_IPRANGE_LIMIT);
			goto next;
		}
#ifndef HAVE_IPV6
		if (ZBX_IPRANGE_V6 == ipr.addr_type)
		{
			zabbix_log(LOG_LEVEL_WARNING, "discovery rule \"%s\": encountered IP range \"%s\","
					" but IPv6 support not compiled in", drule->name, start);
			goto next;
		}
#endif
		zbx_vector_iprange_append(ipranges, ipr);
next:
		if (NULL != comma)
		{
			*comma = ',';
			start = comma + 1;
		}
		else
			break;
	}

	zbx_hashset_create(&tasks_local, 1, discoverer_task_hash, discoverer_task_compare);
	tasks_ptr = 0 == drule->concurrency_max ? &tasks_local : tasks;
	*ip = '\0';

	while (SUCCEED == zbx_iprange_uniq_next(ipranges->values, ipranges->values_num, ip, sizeof(ip)))
	{
		unsigned char	need_resolve = 1;
		zbx_uint64_t	checks_count = 0;

		zabbix_log(LOG_LEVEL_DEBUG, "%s() ip:'%s'", __func__, ip);

		if (0 != drule->unique_dcheckid)
		{
			checks_count = process_checks(drule, ip, 1, &need_resolve, queue_capacity, tasks,
					dchecks_common, ipranges);
		}

		checks_count += process_checks(drule, ip, 0, &need_resolve, queue_capacity, tasks_ptr, dchecks_common,
				ipranges);

		if (0 == *queue_capacity)
			goto out;

		if (0 < checks_count)
		{
			zbx_discoverer_check_count_t	*check_count, cmp;

			cmp.druleid = drule->druleid;
			zbx_strlcpy(cmp.ip, ip, sizeof(cmp.ip));
			cmp.count = 0;

			check_count = zbx_hashset_insert(check_counts, &cmp, sizeof(zbx_discoverer_check_count_t));
			check_count->count += checks_count;
		}
	}

	if (0 == drule->concurrency_max)
		process_task_range_split(&tasks_local, tasks);
out:
	if (0 != tasks_local.num_data)
	{
		zbx_discoverer_task_t	*task;
		zbx_hashset_iter_t	iter;

		zbx_hashset_iter_reset(&tasks_local, &iter);

		while (NULL != (task = (zbx_discoverer_task_t*)zbx_hashset_iter_next(&iter)))
			discoverer_task_clear(task);
	}

	zbx_hashset_destroy(&tasks_local);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() tasks:%d check_counts(ip):%d", __func__, tasks->num_data,
			check_counts->num_data);
}

