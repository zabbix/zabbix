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

#include "zbxdiscoverer.h"

#include "zbxlog.h"
#include "zbxcacheconfig.h"
#include "zbxicmpping.h"
#include "zbxdiscovery.h"
#include "zbxexpression.h"
#include "zbxself.h"
#include "zbxrtc.h"
#include "zbxnix.h"
#include "zbxnum.h"
#include "zbxtime.h"
#include "zbxip.h"
#include "zbxsysinfo.h"
#include "zbx_rtc_constants.h"
#include "discoverer_queue.h"
#include "discoverer_job.h"
#include "discoverer_async.h"
#include "zbx_discoverer_constants.h"
#include "discoverer_taskprep.h"
#include "discoverer_int.h"
#include "zbxtimekeeper.h"
#include "zbxalgo.h"
#include "zbxcomms.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxipcservice.h"
#include "zbxstr.h"
#include "zbxthreads.h"

#ifdef HAVE_NETSNMP
#	include "zbxpoller.h"
#endif

#ifdef HAVE_LDAP
#	include <ldap.h>
#endif

static ZBX_THREAD_LOCAL int	log_worker_id;
static zbx_get_progname_f	zbx_get_progname_cb = NULL;
static zbx_get_program_type_f	zbx_get_program_type_cb = NULL;

ZBX_PTR_VECTOR_IMPL(discoverer_services_ptr, zbx_discoverer_dservice_t*)
ZBX_PTR_VECTOR_IMPL(discoverer_results_ptr, zbx_discoverer_results_t*)
ZBX_PTR_VECTOR_IMPL(discoverer_jobs_ptr, zbx_discoverer_job_t*)

#define ZBX_DISCOVERER_STARTUP_TIMEOUT	30

static zbx_discoverer_manager_t		dmanager;

ZBX_VECTOR_IMPL(portrange, zbx_range_t)
ZBX_PTR_VECTOR_IMPL(ds_dcheck_ptr, zbx_ds_dcheck_t *)
ZBX_PTR_VECTOR_IMPL(discoverer_drule_error, zbx_discoverer_drule_error_t)

/******************************************************************************
 *                                                                            *
 * Purpose: clear job error                                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_discoverer_drule_error_free(zbx_discoverer_drule_error_t value)
{
	zbx_free(value.error);
}

static zbx_hash_t	discoverer_check_count_hash(const void *data)
{
	const zbx_discoverer_check_count_t	*count = (const zbx_discoverer_check_count_t *)data;
	zbx_hash_t				hash;

	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&count->druleid);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO(count->ip, strlen(count->ip), hash);

	return hash;
}

static int	discoverer_check_count_compare(const void *d1, const void *d2)
{
	const zbx_discoverer_check_count_t	*count1 = (const zbx_discoverer_check_count_t *)d1;
	const zbx_discoverer_check_count_t	*count2 = (const zbx_discoverer_check_count_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(count1->druleid, count2->druleid);

	return strcmp(count1->ip, count2->ip);
}

static zbx_hash_t	discoverer_result_hash(const void *data)
{
	const zbx_discoverer_results_t	*result = (const zbx_discoverer_results_t *)data;
	zbx_hash_t			hash;

	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&result->druleid);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO(result->ip, strlen(result->ip), hash);

	return hash;
}

static int	discoverer_result_compare(const void *d1, const void *d2)
{
	const zbx_discoverer_results_t	*r1 = (const zbx_discoverer_results_t *)d1;
	const zbx_discoverer_results_t	*r2 = (const zbx_discoverer_results_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(r1->druleid, r2->druleid);

	return strcmp(r1->ip, r2->ip);
}

void	discoverer_ds_dcheck_free(zbx_ds_dcheck_t *ds_dcheck)
{
	zbx_free(ds_dcheck->dcheck.key_);
	zbx_free(ds_dcheck->dcheck.ports);

	if (SVC_SNMPv1 == ds_dcheck->dcheck.type || SVC_SNMPv2c == ds_dcheck->dcheck.type ||
			SVC_SNMPv3 == ds_dcheck->dcheck.type)
	{
		zbx_free(ds_dcheck->dcheck.snmp_community);
		zbx_free(ds_dcheck->dcheck.snmpv3_securityname);
		zbx_free(ds_dcheck->dcheck.snmpv3_authpassphrase);
		zbx_free(ds_dcheck->dcheck.snmpv3_privpassphrase);
		zbx_free(ds_dcheck->dcheck.snmpv3_contextname);
	}

	zbx_vector_portrange_destroy(&ds_dcheck->portranges);
	zbx_free(ds_dcheck);
}

static int	discoverer_check_count_decrease(zbx_hashset_t *check_counts, zbx_uint64_t druleid, const char *ip,
		zbx_uint64_t count)
{
	zbx_discoverer_check_count_t	*check_count, cmp;

	cmp.druleid = druleid;
	zbx_strlcpy(cmp.ip, ip, sizeof(cmp.ip));

	if (NULL == (check_count = zbx_hashset_search(check_counts, &cmp)) || 0 == check_count->count)
		return FAIL;

	check_count->count -= count;

	return SUCCEED;
}

static int	discoverer_drule_check(zbx_hashset_t *check_counts, zbx_uint64_t druleid, const char *ip)
{
	return discoverer_check_count_decrease(check_counts, druleid, ip, 0);
}

static int	dcheck_get_timeout(unsigned char type, int *timeout_sec, char *error_val, size_t error_len)
{
	char	*tmt;
	int	ret;

	tmt = zbx_dc_get_global_item_type_timeout(type);

	zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
			NULL, NULL, &tmt, ZBX_MACRO_TYPE_COMMON, NULL, 0);

	ret = zbx_validate_item_timeout(tmt, timeout_sec, error_val, error_len);
	zbx_free(tmt);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if service is available                                    *
 *                                                                            *
 * Parameters: dcheck           - [IN] service type                           *
 *             ip               - [IN]                                        *
 *             port             - [IN]                                        *
 *             value            - [OUT]                                       *
 *             value_alloc      - [IN/OUT]                                    *
 *             error            - [OUT]                                       *
 *                                                                            *
 * Return value: SUCCEED - service is UP, FAIL - service not discovered       *
 *                                                                            *
 ******************************************************************************/
static int	discoverer_service(const zbx_dc_dcheck_t *dcheck, char *ip, int port, char **error)
{
	int		ret = SUCCEED;
	const char	*service = NULL;
	AGENT_RESULT	result;

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] In %s()", log_worker_id, __func__);

	zbx_init_agent_result(&result);

	switch (dcheck->type)
	{
		case SVC_LDAP:
#ifdef HAVE_LDAP
			service = "ldap";
#else
			ret = FAIL;
			*error = zbx_strdup(*error, "Support for LDAP checks was not compiled in.");
#endif
			break;
		default:
			ret = FAIL;
			*error = zbx_dsprintf(*error, "Unsupported check type %u.", dcheck->type);
			break;
	}

	if (SUCCEED == ret)
	{
		char	key[MAX_STRING_LEN];

		zbx_snprintf(key, sizeof(key), "net.tcp.service[%s,%s,%d]", service, ip, port);

		if (SUCCEED != zbx_execute_agent_check(key, 0, &result, dcheck->timeout) ||
				NULL == ZBX_GET_UI64_RESULT(&result) || 0 == result.ui64)
		{
			ret = FAIL;
		}
	}

	zbx_free_agent_result(&result);

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] End of %s() ret:%s", log_worker_id, __func__, zbx_result_string(ret));

	return ret;
}

static void	service_free(zbx_discoverer_dservice_t *service)
{
	zbx_free(service);
}

static void	results_clear(zbx_discoverer_results_t *result)
{
	zbx_free(result->ip);
	zbx_free(result->dnsname);
	zbx_vector_discoverer_services_ptr_clear_ext(&result->services, service_free);
	zbx_vector_discoverer_services_ptr_destroy(&result->services);
}

void	results_free(zbx_discoverer_results_t *result)
{
	results_clear(result);
	zbx_free(result);
}

void	dcheck_port_ranges_get(const char *ports, zbx_vector_portrange_t *ranges)
{
	char		buf[MAX_STRING_LEN / 8 + 1];
	const char	*start;

	zbx_strscpy(buf, ports);

	for (start = buf; '\0' != *start;)
	{
		char		*comma, *last_port;
		zbx_range_t	r;

		if (NULL != (comma = strchr(start, ',')))
			*comma = '\0';

		if (NULL != (last_port = strchr(start, '-')))
		{
			*last_port = '\0';
			r.from = atoi(start);
			r.to = atoi(last_port + 1);
			*last_port = '-';
		}
		else
			r.from = r.to = atoi(start);

		zbx_vector_portrange_append(ranges, r);

		if (NULL != comma)
		{
			*comma = ',';
			start = comma + 1;
		}
		else
			break;
	}
}

static int	process_services(void *handle, zbx_uint64_t druleid, zbx_db_dhost *dhost, const char *ip,
		const char *dns, time_t now, zbx_uint64_t unique_dcheckid,
		const zbx_vector_discoverer_services_ptr_t *services, zbx_add_event_func_t add_event_cb,
		zbx_discovery_update_service_func_t discovery_update_service_cb,
		zbx_discovery_update_service_down_func_t discovery_update_service_down_cb,
		zbx_discovery_find_host_func_t discovery_find_host_cb)
{
	int			host_status = -1;
	zbx_vector_uint64_t	dserviceids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&dserviceids);

	for (int i = 0; i < services->values_num; i++)
	{
		zbx_discoverer_dservice_t	*service = (zbx_discoverer_dservice_t *)services->values[i];

		if ((-1 == host_status || DOBJECT_STATUS_UP == service->status) && host_status != service->status)
			host_status = service->status;

		discovery_update_service_cb(handle, druleid, service->dcheckid, unique_dcheckid, dhost,
				ip, dns, service->port, service->status, service->value, now, &dserviceids,
				add_event_cb);

	}

	if (0 == services->values_num)
	{
		discovery_find_host_cb(druleid, ip, dhost);
		host_status = DOBJECT_STATUS_DOWN;
	}

	if (0 != dhost->dhostid)
		discovery_update_service_down_cb(dhost->dhostid, now, &dserviceids);

	zbx_vector_uint64_destroy(&dserviceids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return host_status;
}

/******************************************************************************
 *                                                                            *
 * Purpose: cleans dservices and dhosts not present in drule                  *
 *                                                                            *
 ******************************************************************************/
static void	discoverer_clean_services(zbx_uint64_t druleid)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*iprange = NULL;
	zbx_vector_uint64_t	keep_dhostids, del_dhostids, del_dserviceids;
	zbx_uint64_t		dhostid, dserviceid;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = zbx_db_select("select iprange from drules where druleid=" ZBX_FS_UI64, druleid);

	if (NULL != (row = zbx_db_fetch(result)))
		iprange = zbx_strdup(iprange, row[0]);

	zbx_db_free_result(result);

	if (NULL == iprange)
		goto out;

	zbx_vector_uint64_create(&keep_dhostids);
	zbx_vector_uint64_create(&del_dhostids);
	zbx_vector_uint64_create(&del_dserviceids);

	result = zbx_db_select(
			"select dh.dhostid,ds.dserviceid,ds.ip"
			" from dhosts dh"
				" left join dservices ds"
					" on dh.dhostid=ds.dhostid"
			" where dh.druleid=" ZBX_FS_UI64,
			druleid);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(dhostid, row[0]);

		if (SUCCEED == zbx_db_is_null(row[1]))
		{
			zbx_vector_uint64_append(&del_dhostids, dhostid);
		}
		else if (SUCCEED != zbx_ip_in_list(iprange, row[2]))
		{
			ZBX_STR2UINT64(dserviceid, row[1]);

			zbx_vector_uint64_append(&del_dhostids, dhostid);
			zbx_vector_uint64_append(&del_dserviceids, dserviceid);
		}
		else
			zbx_vector_uint64_append(&keep_dhostids, dhostid);
	}
	zbx_db_free_result(result);

	zbx_free(iprange);

	if (0 != del_dserviceids.values_num)
	{
		/* remove dservices */

		zbx_vector_uint64_sort(&del_dserviceids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from dservices where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "dserviceid",
				del_dserviceids.values, del_dserviceids.values_num);

		zbx_db_execute("%s", sql);

		/* remove dhosts */

		zbx_vector_uint64_sort(&keep_dhostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&keep_dhostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_vector_uint64_sort(&del_dhostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&del_dhostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		for (int i = 0; i < del_dhostids.values_num; i++)
		{
			dhostid = del_dhostids.values[i];

			if (FAIL != zbx_vector_uint64_bsearch(&keep_dhostids, dhostid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
				zbx_vector_uint64_remove_noorder(&del_dhostids, i--);
		}
	}

	if (0 != del_dhostids.values_num)
	{
		zbx_vector_uint64_sort(&del_dhostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from dhosts where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "dhostid",
				del_dhostids.values, del_dhostids.values_num);

		zbx_db_execute("%s", sql);
	}

	zbx_free(sql);

	zbx_vector_uint64_destroy(&del_dserviceids);
	zbx_vector_uint64_destroy(&del_dhostids);
	zbx_vector_uint64_destroy(&keep_dhostids);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	process_results_incompletecheckscount_remove(zbx_discoverer_manager_t *manager,
		zbx_vector_uint64_t *del_druleids)
{
	int	i;

	for (i = 0; i < del_druleids->values_num; i++)
	{
		zbx_hashset_iter_t		iter;
		zbx_discoverer_check_count_t	*dcc;

		zbx_hashset_iter_reset(&manager->incomplete_checks_count, &iter);

		while (NULL != (dcc = (zbx_discoverer_check_count_t *)zbx_hashset_iter_next(&iter)))
		{
			if (dcc->druleid == del_druleids->values[i])
				zbx_hashset_iter_remove(&iter);
		}
	}
}

static void	process_results_incompleteresult_remove(zbx_discoverer_manager_t *manager,
		zbx_vector_discoverer_drule_error_t *drule_errors)
{
	int	i;

	for (i = 0; i < drule_errors->values_num; i++)
	{
		zbx_hashset_iter_t		iter;
		zbx_discoverer_results_t	*dr;
		zbx_discoverer_check_count_t	*dcc;

		zbx_hashset_iter_reset(&manager->results, &iter);

		while (NULL != (dr = (zbx_discoverer_results_t *)zbx_hashset_iter_next(&iter)))
		{
			if (dr->druleid != drule_errors->values[i].druleid)
				continue;

			results_clear(dr);
			zbx_hashset_iter_remove(&iter);
		}

		zbx_hashset_iter_reset(&manager->incomplete_checks_count, &iter);

		while (NULL != (dcc = (zbx_discoverer_check_count_t *)zbx_hashset_iter_next(&iter)))
		{
			if (dcc->druleid == drule_errors->values[i].druleid)
				zbx_hashset_iter_remove(&iter);
		}
	}
}

static int	process_results(zbx_discoverer_manager_t *manager, zbx_vector_uint64_t *del_druleids,
		zbx_hashset_t *incomplete_druleids, zbx_uint64_t *unsaved_checks,
		zbx_vector_discoverer_drule_error_t *drule_errors, const zbx_events_funcs_t *events_cbs,
		zbx_discovery_open_func_t discovery_open_cb, zbx_discovery_close_func_t discovery_close_cb,
		zbx_discovery_update_host_func_t discovery_update_host_cb,
		zbx_discovery_update_service_func_t discovery_update_service_cb,
		zbx_discovery_update_service_down_func_t discovery_update_service_down_cb,
		zbx_discovery_find_host_func_t discovery_find_host_cb)
{
#define DISCOVERER_BATCH_RESULTS_NUM	1000
	zbx_uint64_t				res_check_total = 0,res_check_count = 0;
	zbx_vector_discoverer_results_ptr_t	results;
	zbx_discoverer_results_t		*result, *result_tmp;
	zbx_hashset_iter_t			iter;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() del_druleids:%d", __func__, del_druleids->values_num);

	zbx_vector_discoverer_results_ptr_create(&results);
	zbx_hashset_clear(incomplete_druleids);

	pthread_mutex_lock(&manager->results_lock);

	/* protection against returning values from removed revision of druleid */
	process_results_incompletecheckscount_remove(manager, del_druleids);

	zbx_hashset_iter_reset(&manager->results, &iter);

	while (NULL != (result = (zbx_discoverer_results_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_discoverer_check_count_t	*check_count, cmp;

		cmp.druleid = result->druleid;
		zbx_strlcpy(cmp.ip, result->ip, sizeof(cmp.ip));

		if (FAIL != zbx_vector_uint64_bsearch(del_druleids, cmp.druleid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
		{
			results_clear(result);
			zbx_hashset_iter_remove(&iter);
			continue;
		}

		res_check_total += (zbx_uint64_t)result->services.values_num;

		if (DISCOVERER_BATCH_RESULTS_NUM <= res_check_count ||
				(NULL != (check_count = zbx_hashset_search(&manager->incomplete_checks_count, &cmp)) &&
				0 != check_count->count))
		{
			zbx_hashset_insert(incomplete_druleids, &cmp.druleid, sizeof(zbx_uint64_t));
			continue;
		}

		res_check_count += (zbx_uint64_t)result->services.values_num;

		if (NULL != check_count)
			zbx_hashset_remove_direct(&manager->incomplete_checks_count, check_count);

		result_tmp = (zbx_discoverer_results_t*)zbx_malloc(NULL, sizeof(zbx_discoverer_results_t));
		memcpy(result_tmp, result, sizeof(zbx_discoverer_results_t));
		zbx_vector_discoverer_results_ptr_append(&results, result_tmp);
		zbx_hashset_iter_remove(&iter);
	}

	process_results_incompleteresult_remove(manager, drule_errors);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() results=%d checks:" ZBX_FS_UI64 "/" ZBX_FS_UI64 " del_druleids=%d"
			" incomplete_druleids=%d", __func__, results.values_num, res_check_count, res_check_total,
			del_druleids->values_num, incomplete_druleids->num_data);

	pthread_mutex_unlock(&manager->results_lock);

	if (0 != results.values_num)
	{
		void	*handle = discovery_open_cb();

		for (int i = 0; i < results.values_num; i++)
		{
			zbx_db_dhost	dhost;
			int		host_status;

			result = results.values[i];

			if (NULL == result->dnsname)
			{
				zabbix_log(LOG_LEVEL_WARNING,
						"Missing 'dnsname', result skipped (druleid=" ZBX_FS_UI64 ", ip: '%s')",
						result->druleid, result->ip);
				continue;
			}

			memset(&dhost, 0, sizeof(zbx_db_dhost));

			zbx_db_begin();

			host_status = process_services(handle, result->druleid, &dhost, result->ip, result->dnsname,
					result->now, result->unique_dcheckid, &result->services,
					events_cbs->add_event_cb, discovery_update_service_cb,
					discovery_update_service_down_cb, discovery_find_host_cb);

			discovery_update_host_cb(handle, result->druleid, &dhost, result->ip, result->dnsname,
					host_status, result->now, events_cbs->add_event_cb);

			if (NULL != events_cbs->process_events_cb)
				events_cbs->process_events_cb(NULL, NULL, NULL);

			if (NULL != events_cbs->clean_events_cb)
				events_cbs->clean_events_cb();

			zbx_db_commit();
		}

		discovery_close_cb(handle);
	}

	*unsaved_checks = res_check_total - res_check_count;

	zbx_vector_discoverer_results_ptr_clear_ext(&results, results_free);
	zbx_vector_discoverer_results_ptr_destroy(&results);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ret:%d", __func__,
			DISCOVERER_BATCH_RESULTS_NUM <= res_check_count ? 1 : 0);

	return DISCOVERER_BATCH_RESULTS_NUM <= res_check_count ? 1 : 0;
#undef DISCOVERER_BATCH_RESULTS_NUM
}

static void	process_job_finalize(zbx_vector_uint64_t *del_jobs, zbx_vector_discoverer_drule_error_t *drule_errors,
		zbx_hashset_t *incomplete_druleids, zbx_discovery_open_func_t discovery_open_cb,
		zbx_discovery_close_func_t discovery_close_cb,
		zbx_discovery_update_drule_func_t discovery_udpate_drule_cb)
{
	void	*handle;
	int	i;
	time_t	now;

	if (0 == del_jobs->values_num)
		return;

	/* multiple errors can duplicate druleid */
	zbx_vector_uint64_sort(del_jobs, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(del_jobs, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	now = time(NULL);
	handle = discovery_open_cb();

	for (i = del_jobs->values_num; i != 0; i--)
	{
		int				j;
		char				*err = NULL;
		zbx_discoverer_drule_error_t	derror = {.druleid = del_jobs->values[i - 1]};

		if (NULL != zbx_hashset_search(incomplete_druleids, &derror.druleid))
			continue;

		if (FAIL != (j = zbx_vector_discoverer_drule_error_search(drule_errors, derror,
				ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		{
			err = drule_errors->values[j].error;
			zbx_vector_discoverer_drule_error_remove(drule_errors, j);
		}

		discovery_udpate_drule_cb(handle, derror.druleid, err, now);
		zbx_free(err);
		zbx_vector_uint64_remove(del_jobs, i - 1);
	}

	discovery_close_cb(handle);
}

static int	drule_delay_get(const char *delay, char **delay_resolved, int *delay_int)
{
	int	ret;

	*delay_resolved = zbx_strdup(*delay_resolved, delay);
	zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
			delay_resolved, ZBX_MACRO_TYPE_COMMON, NULL, 0);

	if (SUCCEED != (ret = zbx_is_time_suffix(*delay_resolved, delay_int, ZBX_LENGTH_UNLIMITED)))
		*delay_int = ZBX_DEFAULT_INTERVAL;

	return ret;
}

static int	process_discovery(int *nextcheck, zbx_hashset_t *incomplete_druleids,
		zbx_vector_discoverer_jobs_ptr_t *jobs, zbx_hashset_t *check_counts,
		zbx_vector_discoverer_drule_error_t *drule_errors, zbx_vector_uint64_t *err_druleids)
{
	int				rule_count = 0, delay, i, tmt_simple = 0, tmt_agent = 0, tmt_snmp = 0;
	char				*delay_str = NULL;
	zbx_dc_um_handle_t		*um_handle;
	time_t				now, nextcheck_loc;
	zbx_vector_dc_drule_ptr_t	drules;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	now = time(NULL);

	zbx_vector_dc_drule_ptr_create(&drules);
	zbx_dc_drules_get(now, &drules, &nextcheck_loc);
	*nextcheck = 0 == nextcheck_loc ? FAIL : (int)nextcheck_loc;

	um_handle = zbx_dc_open_user_macros();

	for (int k = 0; ZBX_IS_RUNNING() && k < drules.values_num; k++)
	{
		zbx_hashset_t				tasks;
		zbx_hashset_iter_t			iter;
		zbx_discoverer_task_t			*task, *task_out;
		zbx_discoverer_job_t			*job, cmp;
		zbx_dc_drule_t				*drule = drules.values[k];
		zbx_vector_ds_dcheck_ptr_t	*ds_dchecks_common;
		zbx_vector_iprange_t			*ipranges;
		char					error[MAX_STRING_LEN];

		now = time(NULL);

		cmp.druleid = drule->druleid;
		discoverer_queue_lock(&dmanager.queue);
		i = zbx_vector_discoverer_jobs_ptr_bsearch(&dmanager.job_refs, &cmp,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		discoverer_queue_unlock(&dmanager.queue);

		if (FAIL != i || NULL != zbx_hashset_search(incomplete_druleids, &drule->druleid))
		{
			(void)drule_delay_get(drule->delay_str, &delay_str, &delay);
			goto next;
		}

		if (SUCCEED != drule_delay_get(drule->delay_str, &delay_str, &delay))
		{
			zbx_snprintf(error, sizeof(error), "Invalid update interval \"%s\".", delay_str);
			discoverer_queue_append_error(drule_errors, drule->druleid, error);
			zbx_vector_uint64_append(err_druleids, drule->druleid);
			goto next;
		}

		for (i = 0; i < drule->dchecks.values_num; i++)
		{
			zbx_dc_dcheck_t	*dcheck = (zbx_dc_dcheck_t*)drule->dchecks.values[i];
			char		err[MAX_STRING_LEN];

			if (SVC_AGENT == dcheck->type)
			{
				if (0 == tmt_agent && FAIL == dcheck_get_timeout(ITEM_TYPE_ZABBIX, &tmt_agent,
						err, sizeof(err)))
				{
					zbx_snprintf(error, sizeof(error), "Invalid global timeout for Zabbix Agent"
							" checks: \"%s\"", err);
					discoverer_queue_append_error(drule_errors, drule->druleid, error);
					zbx_vector_uint64_append(err_druleids, drule->druleid);
					goto next;
				}

				dcheck->timeout = tmt_agent;
			}
			else if (SVC_SNMPv1 == dcheck->type || SVC_SNMPv2c == dcheck->type ||
					SVC_SNMPv3 == dcheck->type)
			{
				if (0 == tmt_snmp && FAIL == dcheck_get_timeout(ITEM_TYPE_SNMP, &tmt_snmp,
						err, sizeof(err)))
				{
					zbx_snprintf(error, sizeof(error), "Invalid global timeout for SNMP checks"
							": \"%s\"", err);
					discoverer_queue_append_error(drule_errors, drule->druleid, error);
					zbx_vector_uint64_append(err_druleids, drule->druleid);
					goto next;
				}

				dcheck->timeout = tmt_snmp;
			}
			else
			{
				if (0 == tmt_simple && FAIL == dcheck_get_timeout(ITEM_TYPE_SIMPLE, &tmt_simple,
						err, sizeof(err)))
				{
					zbx_snprintf(error, sizeof(error), "Invalid global timeout for simple checks"
							": \"%s\"", err);
					discoverer_queue_append_error(drule_errors, drule->druleid, error);
					zbx_vector_uint64_append(err_druleids, drule->druleid);
					goto next;
				}

				dcheck->timeout = tmt_simple;
			}

			if (0 != dcheck->uniq)
			{
				drule->unique_dcheckid = dcheck->dcheckid;
				break;
			}
		}

		zbx_hashset_create(&tasks, 1, discoverer_task_hash, discoverer_task_compare);

		ds_dchecks_common = (zbx_vector_ds_dcheck_ptr_t *)zbx_malloc(NULL, sizeof(zbx_vector_dc_dcheck_ptr_t));
		zbx_vector_ds_dcheck_ptr_create(ds_dchecks_common);
		ipranges = (zbx_vector_iprange_t *)zbx_malloc(NULL, sizeof(zbx_vector_iprange_t));
		zbx_vector_iprange_create(ipranges);

		process_rule(drule, &tasks, check_counts, ds_dchecks_common, ipranges, drule_errors, err_druleids);

		if (0 != tasks.num_data)
		{
			job = discoverer_job_create(drule, ds_dchecks_common, ipranges);
			zbx_hashset_iter_reset(&tasks, &iter);

			while (NULL != (task = (zbx_discoverer_task_t*)zbx_hashset_iter_next(&iter)))
			{
				task_out = (zbx_discoverer_task_t*)zbx_malloc(NULL, sizeof(zbx_discoverer_task_t));
				memcpy(task_out, task, sizeof(zbx_discoverer_task_t));
				(void)zbx_list_append(&job->tasks, task_out, NULL);
			}

			zbx_vector_discoverer_jobs_ptr_append(jobs, job);
		}
		else
		{
			zbx_vector_ds_dcheck_ptr_clear_ext(ds_dchecks_common, discoverer_ds_dcheck_free);
			zbx_vector_ds_dcheck_ptr_destroy(ds_dchecks_common);
			zbx_free(ds_dchecks_common);
			zbx_vector_iprange_destroy(ipranges);
			zbx_free(ipranges);
		}

		zbx_hashset_destroy(&tasks);
		rule_count++;
next:
		if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_SERVER))
			discoverer_clean_services(drule->druleid);

		zbx_dc_drule_queue(now, drule->druleid, delay);
	}

	zbx_dc_close_user_macros(um_handle);
	zbx_free(delay_str);

	zbx_vector_dc_drule_ptr_clear_ext(&drules, zbx_discovery_drule_free);
	zbx_vector_dc_drule_ptr_destroy(&drules);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() rule_count:%d nextcheck:%d", __func__, rule_count, *nextcheck);

	return rule_count;	/* performance metric */
}

static void	discoverer_job_remove(zbx_discoverer_job_t *job)
{
	int			i;
	zbx_discoverer_job_t	cmp = {.druleid = job->druleid};

	if (FAIL != (i = zbx_vector_discoverer_jobs_ptr_bsearch(&dmanager.job_refs, &cmp,
			ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
	{
		zbx_vector_discoverer_jobs_ptr_remove(&dmanager.job_refs, i);
	}

	discoverer_job_free(job);
}

zbx_discoverer_dservice_t	*result_dservice_create(const unsigned short port,
		const zbx_uint64_t dcheckid)
{
	zbx_discoverer_dservice_t	*service;

	service = (zbx_discoverer_dservice_t *)zbx_malloc(NULL, sizeof(zbx_discoverer_dservice_t));
	service->dcheckid = dcheckid;
	service->port = port;
	*service->value = '\0';

	return service;
}

zbx_discoverer_results_t	*discoverer_result_create(zbx_uint64_t druleid, const zbx_uint64_t unique_dcheckid)
{
	zbx_discoverer_results_t	*result;

	result = (zbx_discoverer_results_t *)zbx_malloc(NULL, sizeof(zbx_discoverer_results_t));

	zbx_vector_discoverer_services_ptr_create(&result->services);

	result->druleid = druleid;
	result->unique_dcheckid = unique_dcheckid;
	result->ip = result->dnsname = NULL;
	result->now = time(NULL);
	result->processed_checks_per_ip = 0;

	return result;
}

static zbx_discoverer_results_t	*discoverer_results_host_reg(zbx_hashset_t *hr_dst, zbx_uint64_t druleid,
		zbx_uint64_t unique_dcheckid, char *ip)
{
	zbx_discoverer_results_t	*dst, src = {.druleid = druleid, .ip = ip};

	if (NULL == (dst = zbx_hashset_search(hr_dst, &src)))
	{
		dst = zbx_hashset_insert(hr_dst, &src, sizeof(zbx_discoverer_results_t));

		zbx_vector_discoverer_services_ptr_create(&dst->services);
		dst->ip = zbx_strdup(NULL, ip);
		dst->now = time(NULL);
		dst->unique_dcheckid = unique_dcheckid;
		dst->dnsname = zbx_strdup(NULL, "");
	}

	return dst;
}

ZBX_PTR_VECTOR_DECL(fping_host, zbx_fping_host_t)
ZBX_PTR_VECTOR_IMPL(fping_host, zbx_fping_host_t)

static int	discoverer_icmp_result_merge(zbx_hashset_t *incomplete_checks_count, zbx_hashset_t *results,
		const zbx_uint64_t druleid, const zbx_uint64_t dcheckid, const zbx_uint64_t unique_dcheckid,
		const zbx_vector_fping_host_t *hosts)
{
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] In %s()", log_worker_id, __func__);

	for (i = 0; i < hosts->values_num; i++)
	{
		zbx_discoverer_dservice_t	*service;
		zbx_discoverer_results_t	*result;
		zbx_fping_host_t		*h = &hosts->values[i];
		char				*ip = h->addr;

		if (FAIL == discoverer_check_count_decrease(incomplete_checks_count, druleid, ip, 1))
		{
			return FAIL;	/* config revision id was changed */
		}

		/* we must register at least 1 empty result per ip */
		result = discoverer_results_host_reg(results, druleid, unique_dcheckid, ip);

		if (0 == h->rcv)
			continue;

		if (NULL == result->dnsname || ('\0' == *result->dnsname && '\0' != *h->dnsname))
		{
			result->dnsname = zbx_strdup(result->dnsname, h->dnsname);
		}

		service = result_dservice_create(0, dcheckid);
		service->status = DOBJECT_STATUS_UP;
		zbx_vector_discoverer_services_ptr_append(&result->services, service);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] End of %s() results:%d", log_worker_id, __func__, hosts->values_num);

	return SUCCEED;
}

static int	discoverer_icmp(const zbx_uint64_t druleid, zbx_discoverer_task_t *task,
		const int dcheck_idx, int concurrency_max, int *stop, zbx_discoverer_queue_t *queue, char **error)
{
	char				err[ZBX_ITEM_ERROR_LEN_MAX], ip[ZBX_INTERFACE_IP_LEN_MAX];
	int				i, ret = SUCCEED, abort = SUCCEED;
	zbx_uint64_t			count = 0;
	zbx_vector_fping_host_t		hosts;
	const zbx_dc_dcheck_t		*dcheck = &task->ds_dchecks.values[dcheck_idx]->dcheck;
	zbx_fping_host_t		host;

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] In %s() ranges:%d range id:%d dcheck_idx:%d task state count:%d",
			log_worker_id, __func__, task->range.ipranges->values_num, task->range.id,
			dcheck_idx, task->range.state.count);

	zbx_vector_fping_host_create(&hosts);

	if (0 == concurrency_max)
		concurrency_max = queue->checks_per_worker_max;

	for (i = 0; i < task->range.ipranges->values_num; i++)
		count += zbx_iprange_volume(&task->range.ipranges->values[i]);

	zbx_vector_fping_host_reserve(&hosts, (size_t)hosts.values_num + (size_t)count);

	do
	{
		memset(&host, 0, sizeof(host));
		TASK_IP2STR(task, ip);
		task->range.state.count--;
		host.addr = zbx_strdup(NULL, ip);
		zbx_vector_fping_host_append(&hosts, host);

		if (concurrency_max > hosts.values_num)
			continue;

		if (SUCCEED != (ret = zbx_ping(&hosts.values[0], hosts.values_num, 3, 0, 0, dcheck->timeout * 1000,
				dcheck->allow_redirect, 1, err, sizeof(err))))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "[%d] %s() %d icmp checks failed with err:%s",
					log_worker_id, __func__, concurrency_max, err);
			*error = zbx_strdup(*error, err);
			break;
		}
		else
		{
			pthread_mutex_lock(&dmanager.results_lock);
			abort = discoverer_icmp_result_merge(&dmanager.incomplete_checks_count, &dmanager.results,
					druleid, dcheck->dcheckid, task->unique_dcheckid, &hosts);
			pthread_mutex_unlock(&dmanager.results_lock);
		}

		for (i = 0; i < hosts.values_num; i++)
		{
			zbx_str_free(hosts.values[i].addr);
			zbx_str_free(hosts.values[i].dnsname);
		}

		(void)discovery_pending_checks_count_decrease(queue, concurrency_max, 0,
				(zbx_uint64_t)hosts.values_num);
		zbx_vector_fping_host_clear(&hosts);
	}
	while (0 == *stop && SUCCEED == abort && 0 != task->range.state.count &&
			SUCCEED == zbx_iprange_uniq_iter(task->range.ipranges->values, task->range.ipranges->values_num,
			&task->range.state.index_ip, task->range.state.ipaddress));

	if (0 == *stop && 0 != hosts.values_num && ret == SUCCEED)
	{
		if (SUCCEED != (ret = zbx_ping(&hosts.values[0], hosts.values_num, 3, 0, 0, dcheck->timeout * 1000,
				dcheck->allow_redirect, 1, err, sizeof(err))))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "[%d] %s() %d icmp checks failed with err:%s", log_worker_id,
					__func__, concurrency_max, err);
			*error = zbx_strdup(*error, err);
		}
		else
		{
			pthread_mutex_lock(&dmanager.results_lock);
			(void)discoverer_icmp_result_merge(&dmanager.incomplete_checks_count, &dmanager.results,
					druleid, dcheck->dcheckid, task->unique_dcheckid, &hosts);
			pthread_mutex_unlock(&dmanager.results_lock);
		}
	}

	for (i = 0; i < hosts.values_num; i++)
	{
		zbx_str_free(hosts.values[i].addr);
		zbx_str_free(hosts.values[i].dnsname);
	}

	(void)discovery_pending_checks_count_decrease(queue, concurrency_max, 0, (zbx_uint64_t)hosts.values_num);
	zbx_vector_fping_host_destroy(&hosts);

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] End of %s() task state count:%d", log_worker_id, __func__,
			task->range.state.count);

	return ret;
}

static void	discoverer_results_move_value(zbx_discoverer_results_t *src, zbx_hashset_t *hr_dst)
{
	zbx_discoverer_results_t *dst;

	if (NULL == src->dnsname)
		src->dnsname = zbx_strdup(NULL, "");

	if (NULL == (dst = zbx_hashset_search(hr_dst, src)))
	{
		dst = zbx_hashset_insert(hr_dst, src, sizeof(zbx_discoverer_results_t));
		zbx_vector_discoverer_services_ptr_create(&dst->services);

		src->dnsname = NULL;
		src->ip = NULL;
	}
	else if ('\0' == *dst->dnsname && '\0' != *src->dnsname)
	{
		zbx_free(dst->dnsname);
		dst->dnsname = src->dnsname;
		src->dnsname = NULL;
	}

	zbx_vector_discoverer_services_ptr_append_array(&dst->services, src->services.values,
			src->services.values_num);
	zbx_vector_discoverer_services_ptr_clear(&src->services);
	results_free(src);
}

int	discoverer_results_partrange_merge(zbx_hashset_t *hr_dst, zbx_vector_discoverer_results_ptr_t *vr_src,
		zbx_discoverer_task_t *task, int force)
{
	int		i, ret = SUCCEED;
	zbx_uint64_t	druleid = task->ds_dchecks.values[0]->dcheck.druleid;

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] In %s() src:%d dst:%d", log_worker_id, __func__, vr_src->values_num,
			hr_dst->num_data);

	if (0 == force && 0 != vr_src->values_num)	/* checking that config revision id was changed */
	{
		zbx_discoverer_results_t	*src = vr_src->values[0];

		ret = discoverer_drule_check(&dmanager.incomplete_checks_count, druleid, src->ip);
	}

	for (i = vr_src->values_num - 1; i >= 0 && SUCCEED == ret; i--)
	{
		zbx_discoverer_results_t	*src = vr_src->values[i];

		if (0 == force && src->processed_checks_per_ip != task->range.state.checks_per_ip)
			continue;

		if (FAIL == (ret = discoverer_check_count_decrease(&dmanager.incomplete_checks_count, druleid,
				src->ip, src->processed_checks_per_ip)))
		{
			break;	/* config revision id was changed */
		}

		discoverer_results_move_value(src, hr_dst);
		zbx_vector_discoverer_results_ptr_remove(vr_src, i);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] End of %s() src:%d dst:%d", log_worker_id, __func__, vr_src->values_num,
			hr_dst->num_data);

	return ret;
}

static int	discoverer_net_check_icmp(zbx_uint64_t druleid, zbx_discoverer_task_t *task, int concurrency_max,
		int *stop, zbx_discoverer_queue_t *queue, char **error)
{
	int	i, ret = SUCCEED;

	for (i = task->range.state.index_dcheck; i < task->ds_dchecks.values_num && SUCCEED == ret &&
			0 != task->range.state.count; i++)
	{
		ret = discoverer_icmp(druleid, task, i, concurrency_max, stop, queue, error);
		task->range.state.index_ip = 0;
		zbx_iprange_first(task->range.ipranges->values, task->range.state.ipaddress);
	}

	if (FAIL == ret)
		(void)discovery_pending_checks_count_decrease(queue, concurrency_max, 0, task->range.state.count);

	return ret;
}

static int	discoverer_net_check_common(zbx_uint64_t druleid, zbx_discoverer_task_t *task, char **error)
{
	int				ret;
	char				dns[ZBX_INTERFACE_DNS_LEN_MAX];
	char				ip[ZBX_INTERFACE_IP_LEN_MAX];
	zbx_dc_dcheck_t			*dcheck = &task->ds_dchecks.values[task->range.state.index_dcheck]->dcheck;
	zbx_discoverer_dservice_t	*service = NULL;
	zbx_discoverer_results_t	*result = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "[%d] In %s() dchecks:%d key[0]:%s", log_worker_id, __func__,
			task->ds_dchecks.values_num, 0 != task->ds_dchecks.values_num ?
			task->ds_dchecks.values[0]->dcheck.key_ : "empty");

	TASK_IP2STR(task, ip);

	if (SUCCEED == discoverer_service(dcheck, ip, (unsigned short)task->range.state.port, error))
	{
		service = result_dservice_create((unsigned short)task->range.state.port, dcheck->dcheckid);
		service->status = DOBJECT_STATUS_UP;
		zbx_gethost_by_ip(ip, dns, sizeof(dns));
	}
	else if (NULL != *error)
	{
		ret = FAIL;
		goto err;
	}

	pthread_mutex_lock(&dmanager.results_lock);

	if (SUCCEED == discoverer_check_count_decrease(&dmanager.incomplete_checks_count, druleid, ip, 1))
	{
		/* we must register at least 1 empty result per ip */
		result = discoverer_results_host_reg(&dmanager.results, druleid, task->unique_dcheckid, ip);

		if (NULL != service)
		{
			if (NULL == result->dnsname || ('\0' == *result->dnsname && '\0' != *dns))
			{
				result->dnsname = zbx_strdup(result->dnsname, dns);
			}

			zbx_vector_discoverer_services_ptr_append(&result->services, service);
		}
	}
	else
		service_free(service);	/* drule revision has been changed or drule aborted */

	pthread_mutex_unlock(&dmanager.results_lock);
	ret = SUCCEED;
err:
	zabbix_log(LOG_LEVEL_DEBUG, "[%d] End of %s() ip:%s dresult services:%d rdns:%s", log_worker_id, __func__,
			ip, NULL != result ? result->services.values_num : -1, NULL != result ? result->dnsname : "");

	return ret;
}

int	dcheck_is_async(zbx_ds_dcheck_t *ds_dcheck)
{
	switch(ds_dcheck->dcheck.type)
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
		case SVC_HTTPS:
		case SVC_SSH:
		case SVC_TELNET:
			return SUCCEED;
		default:
			return FAIL;
	}
}

static void	*discoverer_worker_entry(void *net_check_worker)
{
	int			err;
	sigset_t		mask;
	zbx_discoverer_worker_t	*worker = (zbx_discoverer_worker_t*)net_check_worker;
	zbx_discoverer_queue_t	*queue = worker->queue;

	zabbix_log(LOG_LEVEL_INFORMATION, "thread started [%s #%d]",
			get_process_type_string(ZBX_PROCESS_TYPE_DISCOVERER), worker->worker_id);

	log_worker_id = worker->worker_id;
	sigemptyset(&mask);
	sigaddset(&mask, SIGQUIT);
	sigaddset(&mask, SIGALRM);
	sigaddset(&mask, SIGTERM);
	sigaddset(&mask, SIGUSR1);
	sigaddset(&mask, SIGUSR2);
	sigaddset(&mask, SIGHUP);
	sigaddset(&mask, SIGINT);

	if (0 > (err = pthread_sigmask(SIG_BLOCK, &mask, NULL)))
		zabbix_log(LOG_LEVEL_WARNING, "cannot block the signals: %s", zbx_strerror(err));

	zbx_init_icmpping_env(get_process_type_string(ZBX_PROCESS_TYPE_DISCOVERER), worker->worker_id);
	worker->stop = 0;

	discoverer_queue_lock(queue);
	discoverer_queue_register_worker(queue);

	while (0 == worker->stop)
	{
		char			*error = NULL;
		int			ret;
		zbx_discoverer_job_t	*job;

		if (NULL != (job = discoverer_queue_pop(queue)))
		{
			int			concurrency_max;
			unsigned char		dcheck_type;
			zbx_uint64_t		druleid;
			zbx_discoverer_task_t	*task;

			if (NULL == (task = discoverer_task_pop(job, queue->checks_per_worker_max)))
			{
				if (0 == job->workers_used)
				{
					zbx_vector_uint64_append(&queue->del_jobs, job->druleid);
					discoverer_job_remove(job);
				}
				else
					job->status = DISCOVERER_JOB_STATUS_REMOVING;

				continue;
			}

			if (FAIL == dcheck_is_async(task->ds_dchecks.values[0]))
				queue->pending_checks_count--;

			job->workers_used++;

			if (0 == job->concurrency_max || job->workers_used != job->concurrency_max ||
					SUCCEED == dcheck_is_async(task->ds_dchecks.values[0]))
			{
				discoverer_queue_push(queue, job);
				discoverer_queue_notify(queue);
			}
			else
				job->status = DISCOVERER_JOB_STATUS_WAITING;

			druleid = job->druleid;
			concurrency_max = job->concurrency_max;

			discoverer_queue_unlock(queue);

			/* process checks */

			zbx_timekeeper_update(worker->timekeeper, worker->worker_id - 1, ZBX_PROCESS_STATE_BUSY);

			if (FAIL == dcheck_is_async(task->ds_dchecks.values[0]))
			{
				ret = discoverer_net_check_common(druleid, task, &error);
			}
			else if (SVC_ICMPPING == GET_DTYPE(task))
			{
				ret = discoverer_net_check_icmp(druleid, task, concurrency_max, &worker->stop, queue,
						&error);
			}
			else
			{
				ret = discovery_net_check_range(druleid, task, concurrency_max, &worker->stop,
						&dmanager, log_worker_id, &error);
			}

			if (FAIL == ret)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "[%d] Discovery rule " ZBX_FS_UI64 " error:%s",
						worker->worker_id, druleid, ZBX_NULL2STR(error));
			}

			dcheck_type = GET_DTYPE(task);
			discoverer_task_free(task);
			zbx_timekeeper_update(worker->timekeeper, worker->worker_id - 1, ZBX_PROCESS_STATE_IDLE);

			/* proceed to the next job */

			discoverer_queue_lock(queue);
			job->workers_used--;

			if (NULL != error)
			{
				error = zbx_dsprintf(error, "'%s' checks failed: \"%s\"",
						zbx_dservice_type_string(dcheck_type), error);
				discoverer_job_abort(job, &queue->pending_checks_count, &queue->errors, error);
				zbx_free(error);
			}

			if (SVC_SNMPv3 == dcheck_type)
				queue->snmpv3_allowed_workers++;

			if (DISCOVERER_JOB_STATUS_WAITING == job->status)
			{
				job->status = DISCOVERER_JOB_STATUS_QUEUED;
				discoverer_queue_push(queue, job);
			}
			else if (DISCOVERER_JOB_STATUS_REMOVING == job->status && 0 == job->workers_used)
			{
				zbx_vector_uint64_append(&queue->del_jobs, job->druleid);
				discoverer_job_remove(job);
			}

			continue;
		}

		if (SUCCEED != discoverer_queue_wait(queue, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "[%d] %s", worker->worker_id, error);
			zbx_free(error);
			worker->stop = 1;
		}
	}

	discoverer_queue_deregister_worker(queue);
	discoverer_queue_unlock(queue);

	zabbix_log(LOG_LEVEL_INFORMATION, "thread stopped [%s #%d]",
			get_process_type_string(ZBX_PROCESS_TYPE_DISCOVERER), worker->worker_id);

	return (void*)0;
}

static int	discoverer_worker_init(zbx_discoverer_worker_t *worker, zbx_discoverer_queue_t *queue,
		zbx_timekeeper_t *timekeeper, void *func(void *), char **error)
{
	int	err;

	worker->flags = DISCOVERER_WORKER_INIT_NONE;
	worker->queue = queue;
	worker->timekeeper = timekeeper;
	worker->stop = 1;

	if (0 != (err = pthread_create(&worker->thread, NULL, func, (void *)worker)))
	{
		*error = zbx_dsprintf(NULL, "cannot create thread: %s", zbx_strerror(err));
		return FAIL;
	}

	worker->flags |= DISCOVERER_WORKER_INIT_THREAD;

	return SUCCEED;
}

static void	discoverer_worker_destroy(zbx_discoverer_worker_t *worker)
{
	if (0 != (worker->flags & DISCOVERER_WORKER_INIT_THREAD))
	{
		void	*dummy;

		pthread_join(worker->thread, &dummy);
	}

	worker->flags = DISCOVERER_WORKER_INIT_NONE;
}

static void	discoverer_worker_stop(zbx_discoverer_worker_t *worker)
{
	if (0 != (worker->flags & DISCOVERER_WORKER_INIT_THREAD))
		worker->stop = 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes libraries, called before creating worker threads      *
 *                                                                            *
 ******************************************************************************/
static void	discoverer_libs_init(void)
{
#ifdef HAVE_NETSNMP
	zbx_init_library_mt_snmp(zbx_get_progname_cb());
#endif
#ifdef HAVE_LIBCURL
	curl_global_init(CURL_GLOBAL_DEFAULT);
#endif
#ifdef HAVE_LDAP
	ldap_get_option(NULL, 0, NULL);
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: releases libraries resources                                      *
 *                                                                            *
 ******************************************************************************/
static void	discoverer_libs_destroy(void)
{
#ifdef HAVE_NETSNMP
	zbx_shutdown_library_mt_snmp(zbx_get_progname_cb());
#endif
#ifdef HAVE_LIBCURL
	curl_global_cleanup();
#endif
}

static int	discoverer_manager_init(zbx_discoverer_manager_t *manager, zbx_thread_discoverer_args *args_in,
		const zbx_thread_info_t *info, char **error)
{
#	define SNMPV3_WORKERS_MAX	1

	int		i, err, ret = FAIL, started_num = 0, checks_per_worker_max;
	time_t		time_start;
	struct timespec	poll_delay = {0, 1e8};
#ifdef	HAVE_GETRLIMIT
	struct rlimit	rlim;
#endif
	memset(manager, 0, sizeof(zbx_discoverer_manager_t));
	manager->config_timeout = args_in->config_timeout;
	manager->source_ip = args_in->config_source_ip;
	manager->progname = args_in->zbx_get_progname_cb_arg();
	manager->process_type = info->process_type;
#ifdef	HAVE_GETRLIMIT
	if (0 == getrlimit(RLIMIT_NOFILE, &rlim))
	{
		/* we will consume not more than 3/5 of all FD */
		checks_per_worker_max = ((int)rlim.rlim_cur / 5 * 3) / args_in->workers_num;

		if (0 == checks_per_worker_max)
		{
			*error = zbx_dsprintf(NULL, "cannot initialize maximum number of concurrent checks per worker,"
					" user limit of file descriptors is insufficient");
			return FAIL;
		}

		if (DISCOVERER_JOB_TASKS_INPROGRESS_MAX > checks_per_worker_max)
		{
			zabbix_log(LOG_LEVEL_WARNING, "for a discovery process with %d workers, the user limit of %d"
					" file descriptors is insufficient. The maximum number of concurrent checks"
					" per worker has been reduced to %d", args_in->workers_num,
					(int)rlim.rlim_cur, checks_per_worker_max);
		}
		else if (DISCOVERER_JOB_TASKS_INPROGRESS_MAX < checks_per_worker_max)
		{
			checks_per_worker_max = DISCOVERER_JOB_TASKS_INPROGRESS_MAX;
		}
	}
	else
#endif
		checks_per_worker_max = DISCOVERER_JOB_TASKS_INPROGRESS_MAX;

	if (0 != (err = pthread_mutex_init(&manager->results_lock, NULL)))
	{
		*error = zbx_dsprintf(NULL, "cannot initialize results mutex: %s", zbx_strerror(err));
		return FAIL;
	}

	if (SUCCEED != discoverer_queue_init(&manager->queue, SNMPV3_WORKERS_MAX, checks_per_worker_max, error))
	{
		pthread_mutex_destroy(&manager->results_lock);
		return FAIL;
	}

	discoverer_libs_init();

	zbx_hashset_create(&manager->results, 1, discoverer_result_hash, discoverer_result_compare);
	zbx_hashset_create(&manager->incomplete_checks_count, 1, discoverer_check_count_hash,
			discoverer_check_count_compare);

	zbx_vector_discoverer_jobs_ptr_create(&manager->job_refs);

	manager->timekeeper = zbx_timekeeper_create(args_in->workers_num, NULL);
	manager->workers_num = args_in->workers_num;
	manager->workers = (zbx_discoverer_worker_t*)zbx_calloc(NULL, (size_t)args_in->workers_num,
			sizeof(zbx_discoverer_worker_t));

	for (i = 0; i < args_in->workers_num; i++)
	{
		manager->workers[i].worker_id = i + 1;

		if (SUCCEED != discoverer_worker_init(&manager->workers[i], &manager->queue, manager->timekeeper,
				discoverer_worker_entry, error))
		{
			goto out;
		}
	}

	/* wait for threads to start */
	time_start = time(NULL);

	while (started_num != args_in->workers_num)
	{
		if (time_start + ZBX_DISCOVERER_STARTUP_TIMEOUT < time(NULL))
		{
			*error = zbx_strdup(NULL, "timeout occurred while waiting for workers to start");
			goto out;
		}

		discoverer_queue_lock(&manager->queue);
		started_num = manager->queue.workers_num;
		discoverer_queue_unlock(&manager->queue);

		nanosleep(&poll_delay, NULL);
	}

	ret = SUCCEED;
out:
	if (FAIL == ret)
	{
		for (i = 0; i < manager->workers_num; i++)
			discoverer_worker_stop(&manager->workers[i]);

		discoverer_queue_destroy(&manager->queue);

		zbx_hashset_destroy(&manager->results);
		zbx_hashset_destroy(&manager->incomplete_checks_count);
		zbx_vector_discoverer_jobs_ptr_destroy(&manager->job_refs);

		zbx_timekeeper_free(manager->timekeeper);
		discoverer_libs_destroy();
	}

	return ret;

#	undef SNMPV3_WORKERS_MAX
}

static void	discoverer_manager_free(zbx_discoverer_manager_t *manager)
{
	zbx_hashset_iter_t		iter;
	zbx_discoverer_results_t	*result;

	discoverer_queue_lock(&manager->queue);

	for (int i = 0; i < manager->workers_num; i++)
		discoverer_worker_stop(&manager->workers[i]);

	discoverer_queue_notify_all(&manager->queue);
	discoverer_queue_unlock(&manager->queue);

	for (int i = 0; i < manager->workers_num; i++)
		discoverer_worker_destroy(&manager->workers[i]);

	zbx_free(manager->workers);

	discoverer_queue_destroy(&manager->queue);

	zbx_timekeeper_free(manager->timekeeper);

	zbx_hashset_destroy(&manager->incomplete_checks_count);

	zbx_vector_discoverer_jobs_ptr_clear(&manager->job_refs);
	zbx_vector_discoverer_jobs_ptr_destroy(&manager->job_refs);

	zbx_hashset_iter_reset(&manager->results, &iter);

	while (NULL != (result = (zbx_discoverer_results_t *)zbx_hashset_iter_next(&iter)))
		results_clear(result);

	zbx_hashset_destroy(&manager->results);

	pthread_mutex_destroy(&manager->results_lock);

	discoverer_libs_destroy();
}

/******************************************************************************
 *                                                                            *
 * Purpose: responds to worker usage statistics request                       *
 *                                                                            *
 * Parameters: manager - [IN] discovery manager                               *
 *             client  - [IN] request source                                  *
 *                                                                            *
 ******************************************************************************/
static void	discoverer_reply_usage_stats(zbx_discoverer_manager_t *manager, zbx_ipc_client_t *client)
{
	zbx_vector_dbl_t	usage;
	unsigned char		*data;
	zbx_uint32_t		data_len;

	zbx_vector_dbl_create(&usage);
	(void)zbx_timekeeper_get_usage(manager->timekeeper, &usage);

	data_len = zbx_discovery_pack_usage_stats(&data, &usage,  manager->workers_num);

	zbx_ipc_client_send(client, ZBX_IPC_DISCOVERER_USAGE_STATS_RESULT, data, data_len);

	zbx_free(data);
	zbx_vector_dbl_destroy(&usage);
}

/******************************************************************************
 *                                                                            *
 * Purpose: periodically tries to find new hosts and services                 *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(zbx_discoverer_thread, args)
{
	zbx_thread_discoverer_args		*discoverer_args_in = (zbx_thread_discoverer_args *)
								(((zbx_thread_args_t *)args)->args);
	double					sec;
	int					nextcheck = 0;
	zbx_ipc_service_t			ipc_service;
	zbx_ipc_client_t			*client;
	zbx_ipc_message_t			*message;
	zbx_timespec_t				sleeptime = { .sec = DISCOVERER_DELAY, .ns = 0 };
	const zbx_thread_info_t			*info = &((zbx_thread_args_t *)args)->info;
	char					*error = NULL;
	zbx_vector_uint64_pair_t		revisions;
	zbx_vector_uint64_t			del_druleids, del_jobs;
	zbx_vector_discoverer_drule_error_t	drule_errors;
	zbx_hashset_t				incomplete_druleids;
	zbx_uint32_t				rtc_msgs[] = {ZBX_RTC_SNMP_CACHE_RELOAD};
	zbx_uint64_t				rev_last = 0;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			info->server_num, get_process_type_string(info->process_type), info->process_num);
	zbx_get_progname_cb = discoverer_args_in->zbx_get_progname_cb_arg;
	zbx_get_program_type_cb = discoverer_args_in->zbx_get_program_type_cb_arg;
	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child(discoverer_args_in->zbx_config_tls, discoverer_args_in->zbx_get_program_type_cb_arg,
			zbx_dc_get_psk_by_identity);
#endif
	zbx_get_progname_cb = discoverer_args_in->zbx_get_progname_cb_arg;
	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(info->process_type),
			info->process_num);

	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	if (FAIL == zbx_ipc_service_start(&ipc_service, ZBX_IPC_SERVICE_DISCOVERER, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start discoverer service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (FAIL == discoverer_manager_init(&dmanager, discoverer_args_in, info, &error))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot initialize discovery manager: %s", error);
		zbx_free(error);
		zbx_ipc_service_close(&ipc_service);
		exit(EXIT_FAILURE);
	}

	zbx_rtc_subscribe_service(ZBX_PROCESS_TYPE_DISCOVERYMANAGER, 0, rtc_msgs, ARRSIZE(rtc_msgs),
			discoverer_args_in->config_timeout, ZBX_IPC_SERVICE_DISCOVERER);

	zbx_vector_uint64_pair_create(&revisions);
	zbx_vector_uint64_create(&del_druleids);
	zbx_vector_uint64_create(&del_jobs);

	zbx_hashset_create(&incomplete_druleids, 1, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_discoverer_drule_error_create(&drule_errors);

	zbx_setproctitle("%s #%d [started]", get_process_type_string(info->process_type), info->process_num);

	while (ZBX_IS_RUNNING())
	{
		int		processing_rules_num, more_results, is_drules_rev_updated;
		zbx_uint64_t	unsaved_checks;

		sec = zbx_time();
		zbx_update_env(get_process_type_string(info->process_type), sec);

		/* update local drules revisions */

		zbx_vector_uint64_clear(&del_druleids);
		zbx_vector_uint64_pair_clear(&revisions);
		is_drules_rev_updated = zbx_dc_drule_revisions_get(&rev_last, &revisions);

		discoverer_queue_lock(&dmanager.queue);

		if (SUCCEED == is_drules_rev_updated)
		{
			for (int i = 0; i < dmanager.job_refs.values_num; i++)
			{
				int			k;
				zbx_uint64_pair_t	revision;
				zbx_discoverer_job_t	*job = dmanager.job_refs.values[i];

				revision.first = job->druleid;

				if (FAIL == (k = zbx_vector_uint64_pair_bsearch(&revisions, revision,
						ZBX_DEFAULT_UINT64_COMPARE_FUNC)) ||
						revisions.values[k].second != job->drule_revision)
				{
					zbx_vector_uint64_append(&del_druleids, job->druleid);
					dmanager.queue.pending_checks_count -= discoverer_job_tasks_free(job);
					zabbix_log(LOG_LEVEL_DEBUG, "%s() changed revision of druleid:" ZBX_FS_UI64,
							__func__, job->druleid);
				}
			}

			nextcheck = 0;
		}

		processing_rules_num = dmanager.job_refs.values_num;

		zbx_vector_discoverer_drule_error_append_array(&drule_errors, dmanager.queue.errors.values,
				dmanager.queue.errors.values_num);
		zbx_vector_discoverer_drule_error_clear(&dmanager.queue.errors);

		zbx_vector_uint64_append_array(&del_jobs, dmanager.queue.del_jobs.values,
				dmanager.queue.del_jobs.values_num);
		zbx_vector_uint64_clear(&dmanager.queue.del_jobs);

		discoverer_queue_unlock(&dmanager.queue);

		zbx_vector_uint64_sort(&del_druleids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		more_results = process_results(&dmanager, &del_druleids, &incomplete_druleids, &unsaved_checks,
				&drule_errors, discoverer_args_in->events_cbs, discoverer_args_in->discovery_open_cb,
				discoverer_args_in->discovery_close_cb, discoverer_args_in->discovery_update_host_cb,
				discoverer_args_in->discovery_update_service_cb,
				discoverer_args_in->discovery_update_service_down_cb,
				discoverer_args_in->discovery_find_host_cb);

		process_job_finalize(&del_jobs, &drule_errors, &incomplete_druleids,
				discoverer_args_in->discovery_open_cb, discoverer_args_in->discovery_close_cb,
				discoverer_args_in->discovery_update_drule_cb);

		zbx_setproctitle("%s #%d [processing %d rules, " ZBX_FS_UI64 " unsaved checks]",
				get_process_type_string(info->process_type), info->process_num, processing_rules_num,
				unsaved_checks);

		/* process discovery rules and create net check jobs */

		sec = zbx_time();

		if ((int)sec >= nextcheck)
		{
			int					rule_count;
			zbx_vector_discoverer_jobs_ptr_t	jobs;
			zbx_hashset_t				check_counts;

			zbx_vector_discoverer_jobs_ptr_create(&jobs);
			zbx_hashset_create(&check_counts, 1, discoverer_check_count_hash,
					discoverer_check_count_compare);

			rule_count = process_discovery(&nextcheck, &incomplete_druleids, &jobs, &check_counts,
					&drule_errors, &del_jobs);

			if (0 < rule_count)
			{
				zbx_hashset_iter_t		iter;
				zbx_discoverer_check_count_t	*count;
				zbx_uint64_t			queued = 0;

				zbx_hashset_iter_reset(&check_counts, &iter);
				pthread_mutex_lock(&dmanager.results_lock);

				while (NULL != (count = (zbx_discoverer_check_count_t *)zbx_hashset_iter_next(&iter)))
				{
					queued += count->count;
					zbx_hashset_insert(&dmanager.incomplete_checks_count, count,
							sizeof(zbx_discoverer_check_count_t));
				}

				pthread_mutex_unlock(&dmanager.results_lock);
				discoverer_queue_lock(&dmanager.queue);
				dmanager.queue.pending_checks_count += queued;

				for (int i = 0; i < jobs.values_num; i++)
				{
					zbx_discoverer_job_t	*job = jobs.values[i];

					discoverer_queue_push(&dmanager.queue, job);
					zbx_vector_discoverer_jobs_ptr_append(&dmanager.job_refs, job);
				}

				zbx_vector_discoverer_jobs_ptr_sort(&dmanager.job_refs,
						ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

				discoverer_queue_notify_all(&dmanager.queue);
				discoverer_queue_unlock(&dmanager.queue);
			}

			zbx_vector_discoverer_jobs_ptr_destroy(&jobs);
			zbx_hashset_destroy(&check_counts);
		}

		/* update sleeptime */

		sleeptime.sec = 0 != more_results ? 0 : zbx_calculate_sleeptime(nextcheck, DISCOVERER_DELAY);

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);
		(void)zbx_ipc_service_recv(&ipc_service, &sleeptime, &client, &message);
		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

		if (NULL != message)
		{
			zbx_uint64_t	count;

			switch (message->code)
			{
				case ZBX_IPC_DISCOVERER_QUEUE:
					discoverer_queue_lock(&dmanager.queue);
					count = dmanager.queue.pending_checks_count;
					discoverer_queue_unlock(&dmanager.queue);

					zbx_ipc_client_send(client, ZBX_IPC_DISCOVERER_QUEUE, (unsigned char *)&count,
							sizeof(count));
					break;
				case ZBX_IPC_DISCOVERER_USAGE_STATS:
					discoverer_reply_usage_stats(&dmanager, client);
					break;
#ifdef HAVE_NETSNMP
				case ZBX_RTC_SNMP_CACHE_RELOAD:
					zbx_clear_cache_snmp(info->process_type, info->process_num);
					break;
#endif
				case ZBX_RTC_SHUTDOWN:
					zabbix_log(LOG_LEVEL_DEBUG, "shutdown message received, terminating...");
					goto out;
			}

			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);

		zbx_timekeeper_collect(dmanager.timekeeper);
	}
out:
	zbx_setproctitle("%s #%d [terminating]", get_process_type_string(info->process_type), info->process_num);

	zbx_vector_uint64_pair_destroy(&revisions);
	zbx_vector_uint64_destroy(&del_druleids);
	zbx_vector_uint64_destroy(&del_jobs);
	zbx_vector_discoverer_drule_error_clear_ext(&drule_errors, zbx_discoverer_drule_error_free);
	zbx_vector_discoverer_drule_error_destroy(&drule_errors);
	zbx_hashset_destroy(&incomplete_druleids);
	discoverer_manager_free(&dmanager);
	zbx_ipc_service_close(&ipc_service);

	exit(EXIT_SUCCESS);
}
