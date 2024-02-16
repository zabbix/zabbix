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
#include "discoverer_int.h"
#include "zbxdbhigh.h"
#include "zbxip.h"

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

static zbx_uint64_t	process_check_range(const zbx_dc_drule_t *drule, zbx_dc_dcheck_t *dcheck,
		zbx_vector_iprange_t *ipranges, zbx_hashset_t *tasks, zbx_hashset_t *tasks_local)
{
	zbx_discoverer_task_t	task_local, *task;
	zbx_vector_portrange_t	port_ranges;
	zbx_hashset_t		*tasks_ptr;
	int			port = ZBX_PORTRANGE_INIT_PORT;
	unsigned int		checks_count = 0;

	if (SVC_ICMPPING != dcheck->type)
	{
		zbx_vector_portrange_create(&port_ranges);
		dcheck_port_ranges_get(dcheck->ports, &port_ranges);

		while (SUCCEED == zbx_portrange_uniq_next(port_ranges.values, port_ranges.values_num, &port))
			checks_count++;

		if (0 != port_ranges.values_num)
			port = port_ranges.values[0].from;	/* get value of first port in range */

		zbx_vector_portrange_destroy(&port_ranges);
	}
	else
		checks_count = 1;

	task_local.range.id = 0;
	zbx_vector_dc_dcheck_ptr_create(&task_local.dchecks);
	zbx_vector_dc_dcheck_ptr_append(&task_local.dchecks, dcheck);

	tasks_ptr = (0 == drule->concurrency_max && SUCCEED == dcheck_is_async(dcheck) && SVC_SNMPv3 != dcheck->type) ?
			tasks_local : tasks;

	if (NULL != (task = zbx_hashset_search(tasks_ptr, &task_local)))
	{
		zbx_vector_dc_dcheck_ptr_destroy(&task_local.dchecks);

		if (FAIL == zbx_vector_dc_dcheck_ptr_search(&task->dchecks, dcheck, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC))
		{
			zbx_vector_dc_dcheck_ptr_append(&task->dchecks, dcheck);
			task->range.state.checks_per_ip += checks_count;
		}
	}
	else
	{
		memset(&task_local.range, 0, sizeof(zbx_task_range_t));
		task_local.range.ipranges = ipranges;
		task_local.range.state.checks_per_ip = checks_count;
		task_local.unique_dcheckid = drule->unique_dcheckid;
		task_local.range.state.port = port;
		zbx_iprange_first(task_local.range.ipranges->values, task_local.range.state.ipaddress);

		zbx_hashset_insert(tasks_ptr, &task_local, sizeof(zbx_discoverer_task_t));
	}

	return (zbx_uint64_t)checks_count;
}

static zbx_uint64_t	process_checks(const zbx_dc_drule_t *drule, int unique, zbx_hashset_t *tasks,
		zbx_hashset_t *tasks_local, zbx_vector_dc_dcheck_ptr_t *dchecks_common, zbx_vector_iprange_t *ipranges)
{
	int		i;
	zbx_uint64_t	checks_count = 0;

	for (i = 0; i < drule->dchecks.values_num; i++)
	{
		zbx_dc_dcheck_t	*dcheck_common, *dcheck = (zbx_dc_dcheck_t*)drule->dchecks.values[i];

		if (0 != drule->unique_dcheckid &&
				((1 == unique && drule->unique_dcheckid != dcheck->dcheckid) ||
				(0 == unique && drule->unique_dcheckid == dcheck->dcheckid)))
		{
			continue;
		}

		dcheck_common = dcheck_clone_get(dcheck, dchecks_common);
		checks_count += process_check_range(drule, dcheck_common, ipranges, tasks, tasks_local);
	}

	return checks_count;
}

static void	process_rangetask_copy(zbx_discoverer_task_t *task, zbx_task_range_t *range, zbx_hashset_t *tasks)
{
	zbx_discoverer_task_t	*task_out = zbx_hashset_insert(tasks, task, sizeof(zbx_discoverer_task_t));

	zbx_vector_dc_dcheck_ptr_create(&task_out->dchecks);
	zbx_vector_dc_dcheck_ptr_append_array(&task_out->dchecks, task->dchecks.values, task->dchecks.values_num);
	task_out->range = *range;
	task->range.id++;
}

static void	process_task_range_split(zbx_hashset_t *tasks_src, zbx_hashset_t *tasks_dst)
{
	zbx_discoverer_task_t	*task;
	zbx_hashset_iter_t	iter;
	zbx_vector_portrange_t	port_ranges;
	int			total = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks_src:%d tasks_dst:%d", __func__, tasks_src->num_data, tasks_dst->num_data);

	zbx_vector_portrange_create(&port_ranges);
	zbx_hashset_iter_reset(tasks_src, &iter);

	while (NULL != (task = (zbx_discoverer_task_t*)zbx_hashset_iter_next(&iter)))
	{
		zbx_task_range_t	range;

		task->range.state.count = 0;
		range = task->range;
		task->range.state.port = ZBX_PORTRANGE_INIT_PORT;
		memset(task->range.state.ipaddress, 0, sizeof(task->range.state.ipaddress));

		while (SUCCEED == zbx_iprange_uniq_iter(task->range.ipranges->values,
				task->range.ipranges->values_num, &task->range.state.index_ip,
				task->range.state.ipaddress))
		{
			for (; task->range.state.index_dcheck < task->dchecks.values_num;
					task->range.state.index_dcheck++)
			{
				zbx_dc_dcheck_t	*dcheck = task->dchecks.values[task->range.state.index_dcheck];

				dcheck_port_ranges_get(dcheck->ports, &port_ranges);

				while (SUCCEED == zbx_portrange_uniq_iter(port_ranges.values, port_ranges.values_num,
						&task->range.state.index_port, &task->range.state.port))
				{
					if (DISCOVERER_JOB_TASKS_INPROGRESS_MAX == range.state.count)
					{
						process_rangetask_copy(task, &range, tasks_dst);
						range = task->range;
					}

					range.state.count++;
					total++;
				}

				task->range.state.port = ZBX_PORTRANGE_INIT_PORT;
				zbx_vector_portrange_clear(&port_ranges);
			}

			task->range.state.index_dcheck = 0;
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

static void	process_task_range_count(zbx_hashset_t *tasks, unsigned int ips_num)
{
	zbx_discoverer_task_t	*task;
	zbx_hashset_iter_t	iter;

	if (0 == ips_num)
		return;

	zbx_hashset_iter_reset(tasks, &iter);

	while (NULL != (task = (zbx_discoverer_task_t*)zbx_hashset_iter_next(&iter)))
	{
		task->range.state.count = task->range.state.checks_per_ip * ips_num;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: process single discovery rule                                     *
 *                                                                            *
 ******************************************************************************/
void	process_rule(zbx_dc_drule_t *drule, zbx_hashset_t *tasks, zbx_hashset_t *check_counts,
		zbx_vector_dc_dcheck_ptr_t *dchecks_common, zbx_vector_iprange_t *ipranges)
{
	zbx_hashset_t	tasks_local;
	zbx_uint64_t	checks_count = 0;
	char		ip[ZBX_INTERFACE_IP_LEN_MAX], *comma, *start = drule->iprange;
	unsigned int	uniq_ips_num = 0;
	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() rule:'%s' range:'%s'", __func__, drule->name, drule->iprange);

	/* i = 1 to guarantee at least 1 iprange */
	for (i = 1; NULL != (start = strchr(start, ',')); i++, start++);

	zbx_vector_iprange_reserve(ipranges, (size_t)i);

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
		if (ZBX_IPRANGE_V6 == ipr.type)
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

	if (0 != drule->unique_dcheckid)
		checks_count = process_checks(drule, 1, tasks, &tasks_local, dchecks_common, ipranges);

	checks_count += process_checks(drule, 0, tasks, &tasks_local, dchecks_common, ipranges);

	if (0 == checks_count)
		goto out;

	*ip = '\0';

	while (SUCCEED == zbx_iprange_uniq_next(ipranges->values, ipranges->values_num, ip, sizeof(ip)))
	{
		zbx_discoverer_check_count_t	dcc;

		dcc.druleid = drule->druleid;
		zbx_strlcpy(dcc.ip, ip, sizeof(dcc.ip));
		dcc.count = checks_count;
		zbx_hashset_insert(check_counts, &dcc, sizeof(zbx_discoverer_check_count_t));
		uniq_ips_num++;
	}

	process_task_range_count(tasks, uniq_ips_num);

	if (0 != tasks_local.num_data)
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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() drule:" ZBX_FS_UI64 " tasks:%d check_counts(ip):%d checks_count:"
			ZBX_FS_UI64, __func__, drule->druleid, tasks->num_data, check_counts->num_data, checks_count);
}
