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

#include "discoverer_taskprep.h"

#include "discoverer_int.h"
#include "discoverer_queue.h"

#include "zbxdbhigh.h"
#include "zbxip.h"
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

static zbx_ds_dcheck_t	*dcheck_clone_get(zbx_dc_dcheck_t *dcheck, zbx_vector_ds_dcheck_ptr_t *ds_dchecks_common)
{
	zbx_ds_dcheck_t	*ds_dcheck, ds_dcheck_cmp = {.dcheck.dcheckid = dcheck->dcheckid};
	zbx_dc_dcheck_t	*dcheck_ptr;
	int		idx;

	if (FAIL != (idx = zbx_vector_ds_dcheck_ptr_search(ds_dchecks_common, &ds_dcheck_cmp,
			ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
	{
		return ds_dchecks_common->values[idx];
	}

	ds_dcheck = (zbx_ds_dcheck_t*)zbx_malloc(NULL, sizeof(zbx_ds_dcheck_t));
	dcheck_ptr = &ds_dcheck->dcheck;
	dcheck_copy(dcheck, dcheck_ptr);

	zbx_vector_portrange_create(&ds_dcheck->portranges);
	dcheck_port_ranges_get(ds_dcheck->dcheck.ports, &ds_dcheck->portranges);

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

	zbx_vector_ds_dcheck_ptr_append(ds_dchecks_common, ds_dcheck);

	return ds_dcheck;
}

static zbx_uint64_t	process_check_range(const zbx_dc_drule_t *drule, zbx_ds_dcheck_t *ds_dcheck,
		zbx_vector_iprange_t *ipranges, zbx_hashset_t *tasks)
{
	zbx_discoverer_task_t	task_local, *task;
	int			port = ZBX_PORTRANGE_INIT_PORT;
	unsigned int		checks_count = 0;

	if (SVC_ICMPPING != ds_dcheck->dcheck.type)
	{
		zbx_vector_portrange_t	*port_ranges = &ds_dcheck->portranges;

		while (SUCCEED == zbx_portrange_uniq_next(port_ranges->values, port_ranges->values_num, &port))
			checks_count++;

		if (0 != port_ranges->values_num)
			port = port_ranges->values[0].from;	/* get value of first port in range */
	}
	else
		checks_count = 1;

	task_local.range.id = 0;
	zbx_vector_ds_dcheck_ptr_create(&task_local.ds_dchecks);
	zbx_vector_ds_dcheck_ptr_append(&task_local.ds_dchecks, ds_dcheck);

	/* The net-snmplib limitation associated with the internal EnginID cache requires that the net-snmplib cache */
	/* be reset after each dcheck. That's why we put each snmpv3 dcheck into a separate task. */

	if (SVC_SNMPv3 != ds_dcheck->dcheck.type && NULL != (task = zbx_hashset_search(tasks, &task_local)))
	{
		zbx_vector_ds_dcheck_ptr_destroy(&task_local.ds_dchecks);

		if (FAIL == zbx_vector_ds_dcheck_ptr_search(&task->ds_dchecks, ds_dcheck,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC))
		{
			zbx_vector_ds_dcheck_ptr_append(&task->ds_dchecks, ds_dcheck);
			task->range.state.checks_per_ip += checks_count;
		}
	}
	else
	{
		memset(&task_local.range, 0, sizeof(zbx_task_range_t));
		task_local.range.id = SVC_SNMPv3 == ds_dcheck->dcheck.type ? ds_dcheck->dcheck.dcheckid : 0;
		task_local.range.ipranges = ipranges;
		task_local.range.state.checks_per_ip = checks_count;
		task_local.unique_dcheckid = drule->unique_dcheckid;
		task_local.range.state.port = port;
		zbx_iprange_first(task_local.range.ipranges->values, task_local.range.state.ipaddress);

		zbx_hashset_insert(tasks, &task_local, sizeof(zbx_discoverer_task_t));
	}

	return (zbx_uint64_t)checks_count;
}

static zbx_uint64_t	process_checks(const zbx_dc_drule_t *drule, int unique, zbx_hashset_t *tasks,
		zbx_vector_ds_dcheck_ptr_t *ds_dchecks_common, zbx_vector_iprange_t *ipranges)
{
	int		i;
	zbx_uint64_t	checks_count = 0;

	for (i = 0; i < drule->dchecks.values_num; i++)
	{
		zbx_dc_dcheck_t	*dcheck = (zbx_dc_dcheck_t*)drule->dchecks.values[i];
		zbx_ds_dcheck_t	*ds_dcheck_common;

		if (0 != drule->unique_dcheckid &&
				((1 == unique && drule->unique_dcheckid != dcheck->dcheckid) ||
				(0 == unique && drule->unique_dcheckid == dcheck->dcheckid)))
		{
			continue;
		}

		ds_dcheck_common = dcheck_clone_get(dcheck, ds_dchecks_common);
		checks_count += process_check_range(drule, ds_dcheck_common, ipranges, tasks);
	}

	return checks_count;
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
		zbx_vector_ds_dcheck_ptr_t *ds_dchecks_common, zbx_vector_iprange_t *ipranges,
		zbx_vector_discoverer_drule_error_t *drule_errors, zbx_vector_uint64_t *err_druleids)
{
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
		int		res, ip_first[ZBX_IPRANGE_GROUPS_V6], z[ZBX_IPRANGE_GROUPS_V6] = {0};

		if (NULL != (comma = strchr(start, ',')))
			*comma = '\0';

		zabbix_log(LOG_LEVEL_DEBUG, "%s() range:'%s'", __func__, start);

		if (SUCCEED == (res = zbx_iprange_parse(&ipr, start)))
			zbx_iprange_first(&ipr, ip_first);

		if (SUCCEED != res || 0 == memcmp(ip_first, z, sizeof(int) *
				(ZBX_IPRANGE_V4 == ipr.type ? ZBX_IPRANGE_GROUPS_V4 : ZBX_IPRANGE_GROUPS_V6)))
		{
			char	err[MAX_STRING_LEN];

			zbx_snprintf(err, sizeof(err), "Wrong format of IP range \"%s\"", start);
			discoverer_queue_append_error(drule_errors, drule->druleid, err);
			zbx_vector_uint64_append(err_druleids, drule->druleid);
			goto out;
		}

		if (ZBX_DISCOVERER_IPRANGE_LIMIT < zbx_iprange_volume(&ipr))
		{
			char	err[MAX_STRING_LEN];

			zbx_snprintf(err, sizeof(err), "IP range \"%s\" exceeds %d address limit", start,
					ZBX_DISCOVERER_IPRANGE_LIMIT);
			discoverer_queue_append_error(drule_errors, drule->druleid, err);
			zbx_vector_uint64_append(err_druleids, drule->druleid);
			goto out;
		}
#ifndef HAVE_IPV6
		if (ZBX_IPRANGE_V6 == ipr.type)
		{
			char	err[MAX_STRING_LEN];

			zbx_snprintf(err, sizeof(err), "Encountered IP range \"%s\","
					" but IPv6 support not compiled in", start);
			discoverer_queue_append_error(drule_errors, drule->druleid, err);
			zbx_vector_uint64_append(err_druleids, drule->druleid);
			goto out;
		}
#endif
		zbx_vector_iprange_append(ipranges, ipr);

		if (NULL != comma)
		{
			*comma = ',';
			start = comma + 1;
		}
		else
			break;
	}

	if (0 != drule->unique_dcheckid)
		checks_count = process_checks(drule, 1, tasks, ds_dchecks_common, ipranges);

	checks_count += process_checks(drule, 0, tasks, ds_dchecks_common, ipranges);

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
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() drule:" ZBX_FS_UI64 " tasks:%d check_counts(ip):%d checks_count:"
			ZBX_FS_UI64, __func__, drule->druleid, tasks->num_data, check_counts->num_data, checks_count);
}
