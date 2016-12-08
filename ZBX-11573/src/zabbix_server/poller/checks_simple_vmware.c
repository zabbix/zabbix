/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

#include "log.h"
#include "zbxjson.h"
#include "zbxalgo.h"
#include "checks_simple_vmware.h"
#include"../vmware/vmware.h"

#define ZBX_VMWARE_DATASTORE_SIZE_TOTAL		0
#define ZBX_VMWARE_DATASTORE_SIZE_FREE		1
#define ZBX_VMWARE_DATASTORE_SIZE_PFREE		2
#define ZBX_VMWARE_DATASTORE_SIZE_UNCOMMITTED	3

static const char	*sysinfo_ret_string(int ret)
{
	switch (ret)
	{
		case SYSINFO_RET_OK:
			return "SYSINFO_SUCCEED";
		case SYSINFO_RET_FAIL:
			return "SYSINFO_FAIL";
		default:
			return "SYSINFO_UNKNOWN";
	}
}

static int	vmware_set_powerstate_result(AGENT_RESULT *result)
{
	int	ret = SYSINFO_RET_OK;

	if (NULL != GET_STR_RESULT(result))
	{
		if (0 == strcmp(result->str, "poweredOff"))
			SET_UI64_RESULT(result, 0);
		else if (0 == strcmp(result->str, "poweredOn"))
			SET_UI64_RESULT(result, 1);
		else if (0 == strcmp(result->str, "suspended"))
			SET_UI64_RESULT(result, 2);
		else
			ret = SYSINFO_RET_FAIL;

		UNSET_STR_RESULT(result);
	}

	return ret;
}

static zbx_vmware_hv_t	*hv_get(zbx_hashset_t *hvs, const char *uuid)
{
	const char	*__function_name = "hv_get";

	zbx_vmware_hv_t	*hv, hv_local = {(char *)uuid};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() uuid:'%s'", __function_name, uuid);

	hv = zbx_hashset_search(hvs, &hv_local);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __function_name, hv);

	return hv;
}

static zbx_vmware_hv_t	*service_hv_get_by_vm_uuid(zbx_vmware_service_t *service, const char *uuid)
{
	const char	*__function_name = "service_hv_get_by_vm_uuid";

	zbx_vmware_vm_t		vm_local = {(char *)uuid};
	zbx_vmware_vm_index_t	vmi_local = {&vm_local}, *vmi;
	zbx_vmware_hv_t		*hv = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() uuid:'%s'", __function_name, uuid);

	if (NULL != (vmi = zbx_hashset_search(&service->data->vms_index, &vmi_local)))
		hv = vmi->hv;
	else
		hv = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __function_name, hv);

	return hv;

}

static zbx_vmware_vm_t	*service_vm_get(zbx_vmware_service_t *service, const char *uuid)
{
	const char	*__function_name = "service_vm_get";

	zbx_vmware_vm_t		vm_local = {(char *)uuid}, *vm;
	zbx_vmware_vm_index_t	vmi_local = {&vm_local}, *vmi;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() uuid:'%s'", __function_name, uuid);

	if (NULL != (vmi = zbx_hashset_search(&service->data->vms_index, &vmi_local)))
		vm = vmi->vm;
	else
		vm = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __function_name, vm);

	return vm;
}

static zbx_vmware_cluster_t	*cluster_get(zbx_vector_ptr_t *clusters, const char *clusterid)
{
	const char		*__function_name = "cluster_get";

	int			i;
	zbx_vmware_cluster_t	*cluster;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() uuid:'%s'", __function_name, clusterid);

	for (i = 0; i < clusters->values_num; i++)
	{
		cluster = (zbx_vmware_cluster_t *)clusters->values[i];

		if (0 == strcmp(cluster->id, clusterid))
			goto out;
	}

	cluster = NULL;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __function_name, cluster);

	return cluster;
}

static zbx_vmware_cluster_t	*cluster_get_by_name(zbx_vector_ptr_t *clusters, const char *name)
{
	const char		*__function_name = "cluster_get_by_name";

	int			i;
	zbx_vmware_cluster_t	*cluster;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() name:'%s'", __function_name, name);

	for (i = 0; i < clusters->values_num; i++)
	{
		cluster = (zbx_vmware_cluster_t *)clusters->values[i];

		if (0 == strcmp(cluster->name, name))
			goto out;
	}

	cluster = NULL;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __function_name, cluster);

	return cluster;
}

static int	vmware_service_get_counter_value_by_id(zbx_vmware_service_t *service, const char *type, const char *id,
		zbx_uint64_t counterid, const char *instance, int coeff, AGENT_RESULT *result)
{
	const char			*__function_name = "vmware_service_get_counter_value_by_id";

	zbx_vmware_perf_entity_t	*entity;
	zbx_vmware_perf_counter_t	*perfcounter;
	zbx_ptr_pair_t			*perfvalue;
	int				i, ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() type:%s id:%s counterid:" ZBX_FS_UI64 " instance:%s", __function_name,
			type, id, counterid, instance);

	if (NULL == (entity = zbx_vmware_service_get_perf_entity(service, type, id)))
	{
		/* the requested counter has not been queried yet */
		zabbix_log(LOG_LEVEL_DEBUG, "performance data is not yet ready, ignoring request");
		ret = SYSINFO_RET_OK;
		goto out;
	}

	if (FAIL == (i = zbx_vector_ptr_bsearch(&entity->counters, &counterid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Performance counter data was not found."));
		goto out;
	}

	perfcounter = (zbx_vmware_perf_counter_t *)entity->counters.values[i];

	if (0 == (perfcounter->state & ZBX_VMWARE_COUNTER_READY))
	{
		ret = SYSINFO_RET_OK;
		goto out;
	}

	if (0 == perfcounter->values.values_num)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Performance counter data is not available."));
		goto out;
	}

	for (i = 0; i < perfcounter->values.values_num; i++)
	{
		perfvalue = (zbx_ptr_pair_t *)&perfcounter->values.values[i];

		if (0 == strcmp(perfvalue->first, instance))
			break;
	}

	if (i == perfcounter->values.values_num)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Performance counter instance was not found."));
		goto out;
	}

	/* VMware returns -1 value if the performance data for the specified period is not ready - ignore it */
	if (0 == strcmp(perfvalue->second, "-1"))
	{
		ret = SYSINFO_RET_OK;
		goto out;
	}

	if (SUCCEED == set_result_type(result, ITEM_VALUE_TYPE_UINT64, ITEM_DATA_TYPE_DECIMAL, perfvalue->second))
	{
		result->ui64 *= coeff;
		ret = SYSINFO_RET_OK;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

static int	vmware_service_get_counter_value_by_path(zbx_vmware_service_t *service, const char *type,
		const char *id, const char *path, const char *instance, int coeff, AGENT_RESULT *result)
{
	zbx_uint64_t	counterid;

	if (FAIL == zbx_vmware_service_get_counterid(service, path, &counterid))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Performance counter is not available."));
		return SYSINFO_RET_FAIL;
	}

	return vmware_service_get_counter_value_by_id(service, type, id, counterid, instance, coeff, result);
}

static int	vmware_service_get_vm_counter(zbx_vmware_service_t *service, const char *uuid, const char *instance,
		const char *path, int coeff, AGENT_RESULT *result)
{
	const char	*__function_name = "vmware_service_get_vm_counter";

	zbx_vmware_vm_t	*vm;
	int		ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() uuid:%s instance:%s path:%s", __function_name, uuid, instance, path);

	if (NULL == (vm = service_vm_get(service, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown virtual machine uuid."));
		goto out;
	}

	ret = vmware_service_get_counter_value_by_path(service, "VirtualMachine", vm->id, path, instance, coeff,
			result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: get_vmware_service                                               *
 *                                                                            *
 * Purpose: gets vmware service object                                        *
 *                                                                            *
 * Parameters: url       - [IN] the vmware service URL                        *
 *             username  - [IN] the vmware service username                   *
 *             password  - [IN] the vmware service password                   *
 *             ret       - [OUT] the operation result code                    *
 *                                                                            *
 * Return value: The vmware service object or NULL if the service was not     *
 *               found, did not have data or any error occurred. In the last  *
 *               case the error message will be stored in agent result.       *
 *                                                                            *
 * Comments: There are three possible cases:                                  *
 *             1) the vmware service is not ready. This can happen when       *
 *                service was added, but not yet processed by collector.      *
 *                In this case NULL is returned and result code is set to     *
 *                SYSINFO_RET_OK.                                             *
 *             2) the vmware service update failed. This can happen if there  *
 *                was a network problem, authentication failure or any error  *
 *                that prevented from obtaining and parsing vmware data.      *
 *                In this case NULL is returned and result code is set to     *
 *                SYSINFO_RET_FAIL.                                           *
 *             3) the vmware service has been updated successfully.           *
 *                In this case the service object is returned and result code *
 *                is not set.                                                 *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_service_t	*get_vmware_service(const char *url, const char *username, const char *password,
		AGENT_RESULT *result, int *ret)
{
	const char		*__function_name = "get_vmware_service";

	zbx_vmware_service_t	*service;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() '%s'@'%s'", __function_name, username, url);

	if (NULL == (service = zbx_vmware_get_service(url, username, password)))
	{
		*ret = SYSINFO_RET_OK;
		goto out;
	}

	if (0 != (service->state & ZBX_VMWARE_STATE_FAILED))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, NULL != service->data->error ? service->data->error :
				"Unknown VMware service error."));

		zabbix_log(LOG_LEVEL_DEBUG, "failed to query VMware service: %s",
				NULL != service->data->error ? service->data->error : "unknown error");

		*ret = SYSINFO_RET_FAIL;
		service = NULL;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __function_name, service);

	return service;
}

/******************************************************************************
 *                                                                            *
 * Function: get_vcenter_vmprop                                               *
 *                                                                            *
 * Purpose: retrieves data from virtual machine details                       *
 *                                                                            *
 * Parameters: request   - [IN] the original request. The first parameter is  *
 *                              vmware service URL and the second parameter   *
 *                              is virtual machine uuid.                      *
 *             username  - [IN] the vmware service user name                  *
 *             password  - [IN] the vmware service password                   *
 *             xpath     - [IN] the xpath describing data to retrieve         *
 *             result    - [OUT] the request result                           *
 *                                                                            *
 ******************************************************************************/
static int	get_vcenter_vmprop(AGENT_REQUEST *request, const char *username, const char *password,
		int propid, AGENT_RESULT *result)
{
	const char		*__function_name = "get_vcenter_vmprop";

	zbx_vmware_service_t	*service;
	zbx_vmware_vm_t		*vm = NULL;
	char			*url, *uuid, *value;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() propid:%d", __function_name, propid);

	if (2 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	if ('\0' == *uuid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (vm = service_vm_get(service, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown virtual machine uuid."));
		goto unlock;
	}

	if (NULL == (value = vm->props[propid]))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Value is not available."));
		goto unlock;
	}

	SET_STR_RESULT(result, zbx_strdup(NULL, value));

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: get_vcenter_hvprop                                               *
 *                                                                            *
 * Purpose: retrieves hypervisor property                                     *
 *                                                                            *
 * Parameters: request   - [IN] the original request. The first parameter is  *
 *                              vmware service URL and the second parameter   *
 *                              is hypervisor uuid.                           *
 *             username  - [IN] the vmware service user name                  *
 *             password  - [IN] the vmware service password                   *
 *             propid    - [IN] the property id                               *
 *             result    - [OUT] the request result                           *
 *                                                                            *
 ******************************************************************************/
static int	get_vcenter_hvprop(AGENT_REQUEST *request, const char *username, const char *password, int propid,
		AGENT_RESULT *result)
{
	const char		*__function_name = "get_vcenter_hvprop";

	zbx_vmware_service_t	*service;
	const char		*uuid, *url, *value;
	zbx_vmware_hv_t		*hv;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() propid:%d", __function_name, propid);

	if (2 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	if ('\0' == *uuid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (hv = hv_get(&service->data->hvs, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown hypervisor uuid."));
		goto unlock;
	}

	if (NULL == (value = hv->props[propid]))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Value is not available."));
		goto unlock;
	}

	SET_STR_RESULT(result, zbx_strdup(NULL, value));
	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}


int	check_vcenter_cluster_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_cluster_discovery";

	struct zbx_json		json_data;
	char			*url;
	zbx_vmware_service_t	*service;
	int			i, ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (1 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	zbx_json_init(&json_data, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&json_data, ZBX_PROTO_TAG_DATA);

	for (i = 0; i < service->data->clusters.values_num; i++)
	{
		zbx_vmware_cluster_t	*cluster = (zbx_vmware_cluster_t *)service->data->clusters.values[i];

		zbx_json_addobject(&json_data, NULL);
		zbx_json_addstring(&json_data, "{#CLUSTER.ID}", cluster->id, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "{#CLUSTER.NAME}", cluster->name, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&json_data);
	}

	zbx_json_close(&json_data);

	SET_STR_RESULT(result, zbx_strdup(NULL, json_data.buffer));

	zbx_json_free(&json_data);

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_cluster_status(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_cluster_status";

	char			*url, *name, *status;
	zbx_vmware_service_t	*service;
	zbx_vmware_cluster_t	*cluster;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (2 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	name = get_rparam(request, 1);

	if ('\0' == *name)
		goto out;

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (cluster = cluster_get_by_name(&service->data->clusters, name)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown cluster name."));
		goto unlock;
	}

	if (NULL == (status = zbx_xml_read_value(cluster->status, ZBX_XPATH_LN2("val", "overallStatus"))))
		goto unlock;

	ret = SYSINFO_RET_OK;

	if (0 == strcmp(status, "gray"))
		SET_UI64_RESULT(result, 0);
	else if (0 == strcmp(status, "green"))
		SET_UI64_RESULT(result, 1);
	else if (0 == strcmp(status, "yellow"))
		SET_UI64_RESULT(result, 2);
	else if (0 == strcmp(status, "red"))
		SET_UI64_RESULT(result, 3);
	else
		ret = SYSINFO_RET_FAIL;

	zbx_free(status);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

static int	vmware_get_events(const char *events, zbx_uint64_t lastlogsize, const DC_ITEM *item,
		AGENT_RESULT *result, zbx_vector_ptr_t *add_results)
{
	const char		*__function_name = "vmware_get_events";

	zbx_vector_str_t	keys;
	zbx_vector_uint64_t	ids;
	zbx_uint64_t		key;
	char			*value, xpath[MAX_STRING_LEN];
	int			i, ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() lastlogsize:" ZBX_FS_UI64, __function_name, lastlogsize);

	zbx_vector_str_create(&keys);

	if (SUCCEED != zbx_xml_read_values(events, ZBX_XPATH_LN2("Event", "key"), &keys))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "No event key found."));
		zbx_vector_str_destroy(&keys);
		goto out;
	}

	zbx_vector_uint64_create(&ids);

	for (i = 0; i < keys.values_num; i++)
	{
		if (SUCCEED != is_uint64(keys.values[i], &key))
			continue;

		if (key <= lastlogsize)
			continue;

		zbx_vector_uint64_append(&ids, key);
	}

	if (0 != ids.values_num)
	{
		zbx_vector_uint64_sort(&ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		for (i = 0; i < ids.values_num; i++)
		{
			AGENT_RESULT	*add_result;

			zbx_snprintf(xpath, sizeof(xpath), ZBX_XPATH_LN2("Event", "key") "[.='" ZBX_FS_UI64 "']/.."
					ZBX_XPATH_LN("fullFormattedMessage"), ids.values[i]);

			if (NULL == (value = zbx_xml_read_value(events, xpath)))
				continue;

			zbx_replace_invalid_utf8(value);

			add_result = zbx_malloc(NULL, sizeof(AGENT_RESULT));

			init_result(add_result);

			if (SUCCEED == (ret = set_result_type(add_result, item->value_type, item->flags, value)))
			{
				set_result_meta(add_result, ids.values[i], 0);

				if (ITEM_VALUE_TYPE_LOG == item->value_type)
				{
					char	*timestamp;

					add_result->log->logeventid = ids.values[i];

					zbx_snprintf(xpath, sizeof(xpath), ZBX_XPATH_LN2("Event", "key")
							"[.='" ZBX_FS_UI64 "']/.." ZBX_XPATH_LN("createdTime"),
							ids.values[i]);

					if (NULL != (timestamp = zbx_xml_read_value(events, xpath)))
					{
						int	year, mon, mday, hour, min, sec, t;

						/* 2013-06-04T14:19:23.406298Z */
						if (6 == sscanf(timestamp, "%d-%d-%dT%d:%d:%d.%*s",
								&year, &mon, &mday, &hour, &min, &sec))
						{
							if (FAIL != zbx_utc_time(year, mon, mday, hour, min, sec, &t))
								add_result->log->timestamp = t;
						}

						zbx_free(timestamp);
					}
				}
			}

			zbx_free(value);

			zbx_vector_ptr_append(add_results, add_result);
		}
	}

	zbx_vector_uint64_destroy(&ids);

	zbx_vector_str_clear_ext(&keys, zbx_ptr_free);
	zbx_vector_str_destroy(&keys);

	ret = SYSINFO_RET_OK;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s events:%d", __function_name, sysinfo_ret_string(ret),
			add_results->values_num);

	return ret;
}

int	check_vcenter_eventlog(AGENT_REQUEST *request, const DC_ITEM *item, AGENT_RESULT *result,
		zbx_vector_ptr_t *add_results)
{
	const char		*__function_name = "check_vcenter_eventlog";

	char			*url;
	zbx_vmware_service_t	*service;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (1 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, item->username, item->password, result, &ret)))
		goto unlock;

	ret = vmware_get_events(service->data->events, request->lastlogsize, item, result, add_results);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_version(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_version";

	char			*url, *version;
	zbx_vmware_service_t	*service;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (1 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (version = zbx_xml_read_value(service->contents, ZBX_XPATH_VMWARE_ABOUT("version"))))
		goto unlock;

	SET_STR_RESULT(result, version);

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_fullname(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_fullname";

	char			*url, *fullname = NULL;
	zbx_vmware_service_t	*service;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (1 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (fullname = zbx_xml_read_value(service->contents, ZBX_XPATH_VMWARE_ABOUT("fullName"))))
		goto unlock;

	SET_STR_RESULT(result, fullname);

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s fullname:[%s]", __function_name, sysinfo_ret_string(ret),
			ZBX_NULL2STR(fullname));

	return ret;
}

int	check_vcenter_hv_cluster_name(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_hv_cluster_name";

	char			*url, *uuid;
	zbx_vmware_hv_t		*hv;
	zbx_vmware_service_t	*service;
	zbx_vmware_cluster_t	*cluster = NULL;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (2 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	if ('\0' == *uuid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (hv = hv_get(&service->data->hvs, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown hypervisor uuid."));
		goto unlock;
	}

	if (NULL != hv->clusterid)
		cluster = cluster_get(&service->data->clusters, hv->clusterid);

	SET_STR_RESULT(result, zbx_strdup(NULL, NULL != cluster ? cluster->name : ""));

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_cpu_usage(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_hv_cpu_usage";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_OVERALL_CPU_USAGE, result);
	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * 1000000;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_hv_discovery";

	struct zbx_json		json_data;
	char			*url, *name;
	zbx_vmware_service_t	*service;
	int			ret = SYSINFO_RET_FAIL;
	zbx_vmware_hv_t		*hv;
	zbx_hashset_iter_t	iter;


	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (1 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	zbx_json_init(&json_data, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&json_data, ZBX_PROTO_TAG_DATA);

	zbx_hashset_iter_reset(&service->data->hvs, &iter);
	while (NULL != (hv = zbx_hashset_iter_next(&iter)))
	{
		zbx_vmware_cluster_t	*cluster = NULL;

		if (NULL == (name = hv->props[ZBX_VMWARE_HVPROP_NAME]))
			continue;

		if (NULL != hv->clusterid)
			cluster = cluster_get(&service->data->clusters, hv->clusterid);

		zbx_json_addobject(&json_data, NULL);
		zbx_json_addstring(&json_data, "{#HV.UUID}", hv->uuid, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "{#HV.ID}", hv->id, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "{#HV.NAME}", name, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "{#CLUSTER.NAME}",
				NULL != cluster ? cluster->name : "", ZBX_JSON_TYPE_STRING);
		zbx_json_close(&json_data);
	}

	zbx_json_close(&json_data);

	SET_STR_RESULT(result, zbx_strdup(NULL, json_data.buffer));

	zbx_json_free(&json_data);

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_fullname(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_hv_fullname";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_FULL_NAME, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_hw_cpu_num(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_hv_hw_cpu_num";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_HW_NUM_CPU_CORES, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_hw_cpu_freq(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_hv_hw_cpu_freq";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_HW_CPU_MHZ, result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * 1000000;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_hw_cpu_model(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_hv_hw_cpu_model";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_HW_CPU_MODEL, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_hw_cpu_threads(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_hv_hw_cpu_threads";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_HW_NUM_CPU_THREADS, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_hw_memory(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_hv_hw_memory";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_HW_MEMORY_SIZE, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_hw_model(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_hv_hw_model";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_HW_MODEL, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_hw_uuid(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_hv_hw_uuid";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_HW_UUID, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_hw_vendor(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_hv_hw_vendor";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_HW_VENDOR, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_memory_size_ballooned(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_hv_memory_size_ballooned";

	int			i, ret = SYSINFO_RET_FAIL;
	zbx_vmware_service_t	*service;
	const char		*uuid, *url;
	zbx_vmware_hv_t		*hv;
	zbx_uint64_t		value = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (2 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	if ('\0' == *uuid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (hv = hv_get(&service->data->hvs, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown hypervisor uuid."));
		goto unlock;
	}

	for (i = 0; i < hv->vms.values_num; i++)
	{
		zbx_uint64_t	mem;
		const char	*value;
		zbx_vmware_vm_t	*vm = (zbx_vmware_vm_t *)hv->vms.values[i];

		if (NULL == (value = vm->props[ZBX_VMWARE_VMPROP_MEMORY_SIZE_BALLOONED]))
			continue;

		if (SUCCEED != is_uint64(value, &mem))
			continue;

		value += mem;
	}

	value *= ZBX_MEBIBYTE;
	SET_UI64_RESULT(result, value);

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_memory_used(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_hv_memory_used";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_MEMORY_USED, result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_sensor_health_state(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_hv_sensor_health_state";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_HEALTH_STATE, result);

	if (SYSINFO_RET_OK == ret && NULL != GET_STR_RESULT(result))
	{
		if (0 == strcmp(result->str, "gray") || 0 == strcmp(result->str, "unknown"))
			SET_UI64_RESULT(result, 0);
		else if (0 == strcmp(result->str, "green"))
			SET_UI64_RESULT(result, 1);
		else if (0 == strcmp(result->str, "yellow"))
			SET_UI64_RESULT(result, 2);
		else if (0 == strcmp(result->str, "red"))
			SET_UI64_RESULT(result, 3);
		else
			ret = SYSINFO_RET_FAIL;

		UNSET_STR_RESULT(result);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_status(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_hv_status";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_STATUS, result);

	if (SYSINFO_RET_OK == ret && NULL != GET_STR_RESULT(result))
	{
		if (0 == strcmp(result->str, "gray") || 0 == strcmp(result->str, "unknown"))
			SET_UI64_RESULT(result, 0);
		else if (0 == strcmp(result->str, "green"))
			SET_UI64_RESULT(result, 1);
		else if (0 == strcmp(result->str, "yellow"))
			SET_UI64_RESULT(result, 2);
		else if (0 == strcmp(result->str, "red"))
			SET_UI64_RESULT(result, 3);
		else
			ret = SYSINFO_RET_FAIL;

		UNSET_STR_RESULT(result);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}
int	check_vcenter_hv_uptime(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_hv_uptime";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_UPTIME, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_version(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_hv_version";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_VERSION, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_vm_num(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_hv_vm_num";

	int			ret = SYSINFO_RET_FAIL;
	zbx_vmware_service_t	*service;
	const char		*uuid, *url;
	zbx_vmware_hv_t		*hv;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (2 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	if ('\0' == *uuid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (hv = hv_get(&service->data->hvs, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown hypervisor uuid."));
		goto unlock;
	}

	SET_UI64_RESULT(result, hv->vms.values_num);
	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_network_in(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_hv_network_in";

	char			*url, *mode, *uuid;
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (2 > request->nparam || request->nparam > 3)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	mode = get_rparam(request, 2);

	if (NULL != mode && '\0' != *mode && 0 != strcmp(mode, "bps"))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (hv = hv_get(&service->data->hvs, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown hypervisor uuid."));
		goto unlock;
	}

	ret = vmware_service_get_counter_value_by_path(service, "HostSystem", hv->id, "net/received[average]", "",
			ZBX_KIBIBYTE, result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_network_out(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_hv_network_out";

	char			*url, *mode, *uuid;
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (2 > request->nparam || request->nparam > 3)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	mode = get_rparam(request, 2);

	if (NULL != mode && '\0' != *mode && 0 != strcmp(mode, "bps"))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (hv = hv_get(&service->data->hvs, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown hypervisor uuid."));
		goto unlock;
	}

	ret = vmware_service_get_counter_value_by_path(service, "HostSystem", hv->id, "net/transmitted[average]", "",
			ZBX_KIBIBYTE, result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_datastore_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_hv_datastore_discovery";

	char			*url, *uuid;
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	struct zbx_json		json_data;
	int			i, ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (2 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (hv = hv_get(&service->data->hvs, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown hypervisor uuid."));
		goto unlock;
	}

	zbx_json_init(&json_data, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&json_data, ZBX_PROTO_TAG_DATA);

	for (i = 0; i < hv->datastores.values_num; i++)
	{
		zbx_vmware_datastore_t	*datastore = hv->datastores.values[i];

		zbx_json_addobject(&json_data, NULL);
		zbx_json_addstring(&json_data, "{#DATASTORE}", datastore->name, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&json_data);
	}

	zbx_json_close(&json_data);

	SET_STR_RESULT(result, zbx_strdup(NULL, json_data.buffer));

	zbx_json_free(&json_data);

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_datastore_read(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_hv_datastore_read";

	char			*url, *mode, *uuid, *name;
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	int			i, ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (3 > request->nparam || request->nparam > 4)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	name = get_rparam(request, 2);
	mode = get_rparam(request, 3);

	if (NULL != mode && '\0' != *mode && 0 != strcmp(mode, "latency"))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (hv = hv_get(&service->data->hvs, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown hypervisor uuid."));
		goto unlock;
	}

	for (i = 0; i < hv->datastores.values_num; i++)
	{
		zbx_vmware_datastore_t	*datastore = hv->datastores.values[i];

		if (0 == strcmp(name, datastore->name))
		{
			if (NULL == datastore->uuid)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown datastore uuid."));
				goto unlock;
			}

			ret = vmware_service_get_counter_value_by_path(service, "HostSystem", hv->id,
					"datastore/totalReadLatency[average]", datastore->uuid, 1, result);
			goto unlock;
		}
	}

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_datastore_write(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_hv_datastore_write";

	char			*url, *mode, *uuid, *name;
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	int			i, ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (3 > request->nparam || request->nparam > 4)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	name = get_rparam(request, 2);
	mode = get_rparam(request, 3);

	if (NULL != mode && '\0' != *mode && 0 != strcmp(mode, "latency"))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (hv = hv_get(&service->data->hvs, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown hypervisor uuid."));
		goto unlock;
	}

	for (i = 0; i < hv->datastores.values_num; i++)
	{
		zbx_vmware_datastore_t	*datastore = hv->datastores.values[i];

		if (0 == strcmp(name, datastore->name))
		{
			if (NULL == datastore->uuid)
				break;

			ret = vmware_service_get_counter_value_by_path(service, "HostSystem", hv->id,
					"datastore/totalWriteLatency[average]", datastore->uuid, 1, result);
			goto unlock;
		}
	}

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_datastore_size(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_hv_datastore_size";

	char			*url, *param, *uuid, *name;
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	int			i, ret = SYSINFO_RET_FAIL, mode;
	zbx_vmware_datastore_t	*datastore;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (3 > request->nparam || request->nparam > 4)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	name = get_rparam(request, 2);
	param = get_rparam(request, 3);

	if (NULL == param || '\0' == *param || 0 == strcmp(param, "total"))
	{
		mode = ZBX_VMWARE_DATASTORE_SIZE_TOTAL;
	}
	else if (0 == strcmp(param, "free"))
	{
		mode = ZBX_VMWARE_DATASTORE_SIZE_FREE;
	}
	else if (0 == strcmp(param, "pfree"))
	{
		mode = ZBX_VMWARE_DATASTORE_SIZE_PFREE;
	}
	else if (0 == strcmp(param, "uncommitted"))
	{
		mode = ZBX_VMWARE_DATASTORE_SIZE_UNCOMMITTED;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (hv = hv_get(&service->data->hvs, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown hypervisor uuid."));
		goto unlock;
	}

	for (i = 0; i < hv->datastores.values_num; i++)
	{
		datastore = hv->datastores.values[i];
		if (0 == strcmp(name, datastore->name))
			break;
	}

	if (NULL == datastore)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown datastore name."));
		goto unlock;
	}

	switch (mode)
	{
		case ZBX_VMWARE_DATASTORE_SIZE_TOTAL:
			if (ZBX_MAX_UINT64 == datastore->capacity)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Datastore \"capacity\" is not available."));
				goto unlock;
			}
			SET_UI64_RESULT(result, datastore->capacity);
			break;
		case ZBX_VMWARE_DATASTORE_SIZE_FREE:
			if (ZBX_MAX_UINT64 == datastore->free_space)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Datastore \"free space\" is not available."));
				goto unlock;
			}
			SET_UI64_RESULT(result, datastore->free_space);
			break;
		case ZBX_VMWARE_DATASTORE_SIZE_UNCOMMITTED:
			if (ZBX_MAX_UINT64 == datastore->uncommitted)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Datastore \"uncommitted\" is not available."));
				goto unlock;
			}
			SET_UI64_RESULT(result, datastore->uncommitted);
			break;
		case ZBX_VMWARE_DATASTORE_SIZE_PFREE:
			if (ZBX_MAX_UINT64 == datastore->capacity)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Datastore \"capacity\" is not available."));
				goto unlock;
			}
			if (ZBX_MAX_UINT64 == datastore->free_space)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Datastore \"free space\" is not available."));
				goto unlock;
			}
			if (0 == datastore->capacity)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Datastore \"capacity\" is zero."));
				goto unlock;
			}
			SET_DBL_RESULT(result, (double)datastore->free_space / datastore->capacity * 100);
			break;
	}

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_perfcounter(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_hv_perfcounter";

	char			*url, *uuid, *path, *instance;
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	zbx_uint64_t		counterid;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (3 > request->nparam || request->nparam > 4)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	path = get_rparam(request, 2);
	instance = get_rparam(request, 3);

	if (NULL == instance)
		instance = "";

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (hv = hv_get(&service->data->hvs, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown hypervisor uuid."));
		goto unlock;
	}

	if (FAIL == zbx_vmware_service_get_counterid(service, path, &counterid))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Performance counter is not available."));
		goto unlock;
	}

	/* FAIL is returned if counter already exists */
	if (SUCCEED == zbx_vmware_service_add_perf_counter(service, "HostSystem", hv->id, counterid))
	{
		ret = SYSINFO_RET_OK;
		goto unlock;
	}

	/* the performance counter is already being monitored, try to get the results from statistics */
	ret = vmware_service_get_counter_value_by_id(service, "HostSystem", hv->id, counterid, instance, 1, result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}


int	check_vcenter_vm_cpu_num(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_vm_cpu_num";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_CPU_NUM, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_cluster_name(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_vm_cluster_name";

	char			*url, *uuid;
	zbx_vmware_service_t	*service;
	zbx_vmware_cluster_t	*cluster = NULL;
	int			ret = SYSINFO_RET_FAIL;
	zbx_vmware_hv_t		*hv;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (2 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	if ('\0' == *uuid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (hv = service_hv_get_by_vm_uuid(service, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown virtual machine uuid."));
		goto unlock;
	}
	if (NULL != hv->clusterid)
		cluster = cluster_get(&service->data->clusters, hv->clusterid);

	SET_STR_RESULT(result, zbx_strdup(NULL, NULL != cluster ? cluster->name : ""));

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_cpu_ready(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_vm_cpu_ready";

	zbx_vmware_service_t	*service;
	int			ret = SYSINFO_RET_FAIL;
	const char		*url, *uuid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (2 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	if ('\0' == *uuid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	ret = vmware_service_get_vm_counter(service, uuid, "", "cpu/ready[summation]", 1, result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_cpu_usage(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_vm_cpu_usage";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_CPU_USAGE, result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * 1000000;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_vm_discovery";

	struct zbx_json		json_data;
	char			*url, *vm_name, *hv_name;
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	zbx_vmware_vm_t		*vm;
	zbx_hashset_iter_t	iter;
	int			i, ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (1 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	zbx_json_init(&json_data, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&json_data, ZBX_PROTO_TAG_DATA);

	zbx_hashset_iter_reset(&service->data->hvs, &iter);
	while (NULL != (hv = zbx_hashset_iter_next(&iter)))
	{
		zbx_vmware_cluster_t	*cluster = NULL;

		if (NULL != hv->clusterid)
			cluster = cluster_get(&service->data->clusters, hv->clusterid);

		for (i = 0; i < hv->vms.values_num; i++)
		{
			vm = (zbx_vmware_vm_t *)hv->vms.values[i];

			if (NULL == (vm_name = vm->props[ZBX_VMWARE_VMPROP_NAME]))
				continue;

			if (NULL == (hv_name = hv->props[ZBX_VMWARE_HVPROP_NAME]))
				continue;

			zbx_json_addobject(&json_data, NULL);
			zbx_json_addstring(&json_data, "{#VM.UUID}", vm->uuid, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json_data, "{#VM.ID}", vm->id, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json_data, "{#VM.NAME}", vm_name, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json_data, "{#HV.NAME}", hv_name, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json_data, "{#CLUSTER.NAME}",
					NULL != cluster ? cluster->name : "", ZBX_JSON_TYPE_STRING);
			zbx_json_close(&json_data);
		}
	}

	zbx_json_close(&json_data);

	SET_STR_RESULT(result, zbx_strdup(NULL, json_data.buffer));

	zbx_json_free(&json_data);

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_hv_name(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_vm_hv_name";

	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	char			*url, *uuid, *name;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (2 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	if ('\0' == *uuid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;


	if (NULL == (hv = service_hv_get_by_vm_uuid(service, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown virtual machine uuid."));
		goto unlock;
	}

	if (NULL == (name = hv->props[ZBX_VMWARE_HVPROP_NAME]))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "No hypervisor name found."));
		goto unlock;
	}

	SET_STR_RESULT(result, zbx_strdup(NULL, name));
	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_memory_size(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_vm_memory_size";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_MEMORY_SIZE, result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_memory_size_ballooned(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_vm_memory_size_ballooned";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_MEMORY_SIZE_BALLOONED, result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_memory_size_compressed(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_vm_memory_size_compressed";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_MEMORY_SIZE_COMPRESSED, result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_memory_size_swapped(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_vm_memory_size_swapped";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_MEMORY_SIZE_SWAPPED, result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_memory_size_usage_guest(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_vm_memory_size_usage_guest";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_MEMORY_SIZE_USAGE_GUEST, result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_memory_size_usage_host(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_vm_memory_size_usage_host";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_MEMORY_SIZE_USAGE_HOST, result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_memory_size_private(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_vm_memory_size_private";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_MEMORY_SIZE_PRIVATE, result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_memory_size_shared(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_vm_memory_size_shared";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_MEMORY_SIZE_SHARED, result);

	if (SYSINFO_RET_OK == ret && NULL != GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_powerstate(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_vm_powerstate";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_POWER_STATE, result);

	if (SYSINFO_RET_OK == ret)
		ret = vmware_set_powerstate_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_net_if_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_vm_net_if_discovery";

	struct zbx_json		json_data;
	zbx_vmware_service_t	*service;
	zbx_vmware_vm_t		*vm = NULL;
	zbx_vmware_dev_t	*dev;
	char			*url, *uuid;
	int			i, ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (2 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	if ('\0' == *uuid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (vm = service_vm_get(service, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown virtual machine uuid."));
		goto unlock;
	}

	zbx_json_init(&json_data, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&json_data, ZBX_PROTO_TAG_DATA);

	for (i = 0; i < vm->devs.values_num; i++)
	{
		dev = (zbx_vmware_dev_t *)vm->devs.values[i];

		if (ZBX_VMWARE_DEV_TYPE_NIC != dev->type)
			continue;

		zbx_json_addobject(&json_data, NULL);
		zbx_json_addstring(&json_data, "{#IFNAME}", dev->instance, ZBX_JSON_TYPE_STRING);
		if (NULL != dev->label)
			zbx_json_addstring(&json_data, "{#IFDESC}", dev->label, ZBX_JSON_TYPE_STRING);

		zbx_json_close(&json_data);
	}

	zbx_json_close(&json_data);

	SET_STR_RESULT(result, zbx_strdup(NULL, json_data.buffer));

	zbx_json_free(&json_data);

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_net_if_in(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_vm_net_if_in";

	char			*url, *uuid, *instance, *mode;
	zbx_vmware_service_t	*service;
	const char		*path;
	int 			coeff, ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (3 > request->nparam || request->nparam > 4)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	instance = get_rparam(request, 2);
	mode = get_rparam(request, 3);

	if ('\0' == *uuid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	if ('\0' == *instance)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bps"))
	{
		path = "net/received[average]";
		coeff = ZBX_KIBIBYTE;
	}
	else if (0 == strcmp(mode, "pps"))
	{
		path = "net/packetsRx[summation]";
		coeff = 1;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
		goto unlock;
	}

	ret = vmware_service_get_vm_counter(service, uuid, instance, path, coeff, result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_net_if_out(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_vm_net_if_out";

	char			*url, *uuid, *instance, *mode;
	zbx_vmware_service_t	*service;
	const char		*path;
	int 			coeff, ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (3 > request->nparam || request->nparam > 4)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	instance = get_rparam(request, 2);
	mode = get_rparam(request, 3);

	if ('\0' == *uuid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	if ('\0' == *instance)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bps"))
	{
		path = "net/transmitted[average]";
		coeff = ZBX_KIBIBYTE;
	}
	else if (0 == strcmp(mode, "pps"))
	{
		path = "net/packetsTx[summation]";
		coeff = 1;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
		goto unlock;
	}

	ret = vmware_service_get_vm_counter(service, uuid, instance, path, coeff, result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_storage_committed(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_vm_storage_committed";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_STORAGE_COMMITED, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_storage_unshared(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_vm_storage_unshared";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_STORAGE_UNSHARED, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_storage_uncommitted(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_vm_storage_committed";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_STORAGE_UNCOMMITTED, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_uptime(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*__function_name = "check_vcenter_vm_uptime";

	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_UPTIME, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_vfs_dev_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_vm_vfs_dev_discovery";

	struct zbx_json		json_data;
	zbx_vmware_service_t	*service;
	zbx_vmware_vm_t		*vm = NULL;
	zbx_vmware_dev_t	*dev;
	char			*url, *uuid;
	int			i, ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (2 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	if ('\0' == *uuid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (vm = service_vm_get(service, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown virtual machine uuid."));
		goto unlock;
	}

	zbx_json_init(&json_data, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&json_data, ZBX_PROTO_TAG_DATA);

	for (i = 0; i < vm->devs.values_num; i++)
	{
		dev = (zbx_vmware_dev_t *)vm->devs.values[i];

		if (ZBX_VMWARE_DEV_TYPE_DISK != dev->type)
			continue;

		zbx_json_addobject(&json_data, NULL);
		zbx_json_addstring(&json_data, "{#DISKNAME}", dev->instance, ZBX_JSON_TYPE_STRING);
		if (NULL != dev->label)
			zbx_json_addstring(&json_data, "{#DISKDESC}", dev->label, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&json_data);
	}

	zbx_json_close(&json_data);

	SET_STR_RESULT(result, zbx_strdup(NULL, json_data.buffer));

	zbx_json_free(&json_data);

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_vfs_dev_read(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_vm_vfs_dev_read";

	char			*url, *uuid, *instance, *mode;
	zbx_vmware_service_t	*service;
	const char		*path;
	int			coeff, ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (3 > request->nparam || request->nparam > 4)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	instance = get_rparam(request, 2);
	mode = get_rparam(request, 3);

	if ('\0' == *uuid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	if ('\0' == *instance)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bps"))
	{
		path = "virtualDisk/read[average]";
		coeff = ZBX_KIBIBYTE;
	}
	else if (0 == strcmp(mode, "ops"))
	{
		path = "virtualDisk/numberReadAveraged[average]";
		coeff = 1;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
		goto unlock;
	}

	ret =  vmware_service_get_vm_counter(service, uuid, instance, path, coeff, result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_vfs_dev_write(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_vm_vfs_dev_write";

	char			*url, *uuid, *instance, *mode;
	zbx_vmware_service_t	*service;
	const char		*path;
	int			coeff, ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (3 > request->nparam || request->nparam > 4)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	instance = get_rparam(request, 2);
	mode = get_rparam(request, 3);

	if ('\0' == *uuid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	if ('\0' == *instance)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bps"))
	{
		path = "virtualDisk/write[average]";
		coeff = ZBX_KIBIBYTE;
	}
	else if (0 == strcmp(mode, "ops"))
	{
		path = "virtualDisk/numberWriteAveraged[average]";
		coeff = 1;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
		goto unlock;
	}

	ret =  vmware_service_get_vm_counter(service, uuid, instance, path, coeff, result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_vfs_fs_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_vm_vfs_fs_discovery";

	struct zbx_json		json_data;
	zbx_vmware_service_t	*service;
	zbx_vmware_vm_t		*vm = NULL;
	char			*url, *uuid;
	int			i, ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (2 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);

	if ('\0' == *uuid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (vm = service_vm_get(service, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown virtual machine uuid."));
		goto unlock;
	}

	zbx_json_init(&json_data, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&json_data, ZBX_PROTO_TAG_DATA);

	for (i = 0; i < vm->file_systems.values_num; i++)
	{
		zbx_vmware_fs_t	*fs = (zbx_vmware_fs_t *)vm->file_systems.values[i];

		zbx_json_addobject(&json_data, NULL);
		zbx_json_addstring(&json_data, "{#FSNAME}", fs->path, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&json_data);
	}

	zbx_json_close(&json_data);

	SET_STR_RESULT(result, zbx_strdup(NULL, json_data.buffer));

	zbx_json_free(&json_data);

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_vfs_fs_size(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_vm_vfs_fs_size";

	zbx_vmware_service_t	*service;
	zbx_vmware_vm_t		*vm;
	char			*url, *uuid, *fsname, *mode;
	int			i, ret = SYSINFO_RET_FAIL;
	zbx_vmware_fs_t		*fs = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (3 > request->nparam || request->nparam > 4)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	fsname = get_rparam(request, 2);
	mode = get_rparam(request, 3);

	if ('\0' == *uuid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (vm = service_vm_get(service, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown virtual machine uuid."));
		goto unlock;
	}

	for (i = 0; i < vm->file_systems.values_num; i++)
	{
		fs = (zbx_vmware_fs_t *)vm->file_systems.values[i];

		if (0 == strcmp(fs->path, fsname))
			break;
	}

	if (NULL == fs)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown file system path."));
		goto unlock;
	}

	ret = SYSINFO_RET_OK;

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "total"))
		SET_UI64_RESULT(result, fs->capacity);
	else if (0 == strcmp(mode, "free"))
		SET_UI64_RESULT(result, fs->free_space);
	else if (0 == strcmp(mode, "used"))
		SET_UI64_RESULT(result, fs->capacity - fs->free_space);
	else if (0 == strcmp(mode, "pfree"))
		SET_DBL_RESULT(result, 0 != fs->capacity ? (double)(100.0 * fs->free_space) / fs->capacity : 0);
	else if (0 == strcmp(mode, "pused"))
		SET_DBL_RESULT(result, 100.0 - (0 != fs->capacity ? 100.0 * fs->free_space / fs->capacity : 0));
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
		ret = SYSINFO_RET_FAIL;
	}
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_perfcounter(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*__function_name = "check_vcenter_vm_perfcounter";

	char			*url, *uuid, *path, *instance;
	zbx_vmware_service_t	*service;
	zbx_vmware_vm_t		*vm;
	zbx_uint64_t		counterid;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (3 > request->nparam || request->nparam > 4)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	path = get_rparam(request, 2);
	instance = get_rparam(request, 3);

	if (NULL == instance)
		instance = "";

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (vm = service_vm_get(service, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown virtual machine uuid."));
		goto unlock;
	}

	if (FAIL == zbx_vmware_service_get_counterid(service, path, &counterid))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Performance counter is not available."));
		goto unlock;
	}

	/* FAIL is returned if counter already exists */
	if (SUCCEED == zbx_vmware_service_add_perf_counter(service, "VirtualMachine", vm->id, counterid))
	{
		ret = SYSINFO_RET_OK;
		goto unlock;
	}

	/* the performance counter is already being monitored, try to get the results from statistics */
	ret = vmware_service_get_counter_value_by_id(service, "VirtualMachine", vm->id, counterid, instance, 1, result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, sysinfo_ret_string(ret));

	return ret;
}

#endif	/* defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL) */
