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

#include "zbxcommon.h"

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

#include "checks_simple_vmware.h"

#include "zbxvmware.h"
#include "zbxxml.h"
#include "zbxsysinfo.h"
#include "zbxparam.h"
#include "zbxjson.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxalgo.h"

#define ZBX_VMWARE_DATASTORE_SIZE_TOTAL		0
#define ZBX_VMWARE_DATASTORE_SIZE_FREE		1
#define ZBX_VMWARE_DATASTORE_SIZE_PFREE		2
#define ZBX_VMWARE_DATASTORE_SIZE_UNCOMMITTED	3

#define ZBX_DATASTORE_TOTAL			""
#define ZBX_DATASTORE_COUNTER_CAPACITY		0x01
#define ZBX_DATASTORE_COUNTER_USED		0x02
#define ZBX_DATASTORE_COUNTER_PROVISIONED	0x04

#define ZBX_DATASTORE_DIRECTION_READ		0
#define ZBX_DATASTORE_DIRECTION_WRITE		1

#define ZBX_IF_DIRECTION_IN			0
#define ZBX_IF_DIRECTION_OUT			1

static int	vmware_set_powerstate_result(AGENT_RESULT *result)
{
	int	ret = SYSINFO_RET_OK;

	if (NULL != ZBX_GET_STR_RESULT(result))
	{
		if (0 == strcmp(result->str, "poweredOff"))
			SET_UI64_RESULT(result, 0);
		else if (0 == strcmp(result->str, "poweredOn"))
			SET_UI64_RESULT(result, 1);
		else if (0 == strcmp(result->str, "suspended"))
			SET_UI64_RESULT(result, 2);
		else
			ret = SYSINFO_RET_FAIL;

		ZBX_UNSET_STR_RESULT(result);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns pointer to Hypervisor data from hashset with uuid         *
 *                                                                            *
 * Parameters: hvs  - [IN] hashset with all Hypervisors                       *
 *             uuid - [IN] uuid of Hypervisor                                 *
 *                                                                            *
 * Return value: zbx_vmware_hv_t* - operation has completed successfully      *
 *               NULL             - operation has failed                      *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_hv_t	*hv_get(const zbx_hashset_t *hvs, const char *uuid)
{
	zbx_vmware_hv_t	*hv, hv_local = {.uuid = (char *)uuid};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() uuid:'%s'", __func__, uuid);

	hv = (zbx_vmware_hv_t *)zbx_hashset_search(hvs, &hv_local);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __func__, (void *)hv);

	return hv;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets pointer to Datastore data in vector by UUID                  *
 *                                                                            *
 * Parameters: dss     - [IN] vector with all Datastores                      *
 *             ds_uuid - [IN] UUID of Datastore                               *
 *                                                                            *
 * Return value:                                                              *
 *        zbx_vmware_datastore_t* - operation has completed successfully      *
 *        NULL                    - operation has failed                      *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_datastore_t	*ds_get(const zbx_vector_vmware_datastore_ptr_t *dss, const char *ds_uuid)
{
	int			i;
	zbx_vmware_datastore_t	ds_cmp;

	ds_cmp.uuid = (char *)ds_uuid;

	if (FAIL == (i = zbx_vector_vmware_datastore_ptr_bsearch(dss, &ds_cmp, zbx_vmware_ds_uuid_compare)))
		return NULL;

	return dss->values[i];
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns pointer to DVSwitch data from vector with uuid            *
 *                                                                            *
 * Parameters: dvss - [IN] vector with all DVSwitches                         *
 *             uuid - [IN] id of dvswitch                                     *
 *                                                                            *
 * Return value:                                                              *
 *        zbx_vmware_dvswitch_t* - operation completed successfully           *
 *        NULL                   - operation failed                           *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_dvswitch_t	*dvs_get(const zbx_vector_vmware_dvswitch_ptr_t *dvss, const char *uuid)
{
	zbx_vmware_dvswitch_t	dvs_cmp;
	int			i;

	dvs_cmp.uuid = (char *)uuid;

	if (FAIL == (i = zbx_vector_vmware_dvswitch_ptr_bsearch(dvss, &dvs_cmp, zbx_vmware_dvs_uuid_compare)))
		return NULL;

	return dvss->values[i];
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns pointer to Datacenter data from vector with id            *
 *                                                                            *
 * Parameters: dcs - [IN] vector with all Datacenters                         *
 *             id  - [IN] id of Datacenter                                    *
 *                                                                            *
 * Return value:                                                              *
 *        zbx_vmware_datacenter_t* - operation completed successfully         *
 *        NULL                     - operation failed                         *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_datacenter_t	*dc_get(const zbx_vector_vmware_datacenter_ptr_t *dcs, const char *id)
{
	int			i;
	zbx_vmware_datacenter_t	cmp;

	cmp.id = (char *)id;

	if (FAIL == (i = zbx_vector_vmware_datacenter_ptr_bsearch(dcs, &cmp, zbx_vmware_dc_id_compare)))
		return NULL;

	return dcs->values[i];
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets index of dsname data in vector by datastore UUID or name     *
 *                                                                            *
 * Parameters: hv_dsnames   - [IN] vector with all hv Datastores              *
 *             uuid_or_name - [IN] name or UUID of Datastore                  *
 *                                                                            *
 * Return value:                                                              *
 *        index - operation has completed successfully                        *
 *        FAIL  - operation has failed                                        *
 *                                                                            *
 ******************************************************************************/
static int	dsname_idx_get(const zbx_vector_vmware_dsname_ptr_t *hv_dsnames, const char *uuid_or_name)
{
	int			i;
	zbx_vmware_dsname_t	dsname_cmp;

	dsname_cmp.name = (char *)uuid_or_name;
	dsname_cmp.uuid = (char *)uuid_or_name;

	if (FAIL == (i = zbx_vector_vmware_dsname_ptr_bsearch(hv_dsnames, &dsname_cmp, zbx_vmware_dsname_compare)) &&
			FAIL == (i = zbx_vector_vmware_dsname_ptr_search(hv_dsnames, &dsname_cmp,
					zbx_vmware_dsname_compare_uuid)))
	{
		return FAIL;
	}

	return i;
}

static zbx_vmware_hv_t	*service_hv_get_by_vm_uuid(zbx_vmware_service_t *service, const char *uuid)
{
	zbx_vmware_vm_t		vm_local = {.uuid = (char *)uuid};
	zbx_vmware_vm_index_t	vmi_local = {&vm_local, NULL}, *vmi;
	zbx_vmware_hv_t		*hv = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() uuid:'%s'", __func__, uuid);

	if (NULL != (vmi = (zbx_vmware_vm_index_t *)zbx_hashset_search(&service->data->vms_index, &vmi_local)))
		hv = vmi->hv;
	else
		hv = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __func__, (void *)hv);

	return hv;
}

static zbx_vmware_vm_t	*service_vm_get(zbx_vmware_service_t *service, const char *uuid)
{
	zbx_vmware_vm_t		vm_local = {.uuid = (char *)uuid}, *vm;
	zbx_vmware_vm_index_t	vmi_local = {&vm_local, NULL}, *vmi;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() uuid:'%s'", __func__, uuid);

	if (NULL != (vmi = (zbx_vmware_vm_index_t *)zbx_hashset_search(&service->data->vms_index, &vmi_local)))
		vm = vmi->vm;
	else
		vm = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __func__, (void *)vm);

	return vm;
}

static zbx_vmware_cluster_t	*cluster_get(zbx_vector_vmware_cluster_ptr_t *clusters, const char *clusterid)
{
	zbx_vmware_cluster_t	*cluster;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() uuid:'%s'", __func__, clusterid);

	for (int i = 0; i < clusters->values_num; i++)
	{
		cluster = clusters->values[i];

		if (0 == strcmp(cluster->id, clusterid))
			goto out;
	}

	cluster = NULL;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __func__, (void *)cluster);

	return cluster;
}

static zbx_vmware_cluster_t	*cluster_get_by_name(zbx_vector_vmware_cluster_ptr_t *clusters, const char *name)
{
	zbx_vmware_cluster_t	*cluster;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() name:'%s'", __func__, name);

	for (int i = 0; i < clusters->values_num; i++)
	{
		cluster = clusters->values[i];

		if (0 == strcmp(cluster->name, name))
			goto out;
	}

	cluster = NULL;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __func__, (void *)cluster);

	return cluster;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets vmware performance counter value by its identifier           *
 *                                                                            *
 * Parameters: service   - [IN] vmware service                                *
 *             type      - [IN] performance entity type (HostSystem,          *
 *                              VirtualMachine, Datastore)                    *
 *             id        - [IN] performance entity identifier                 *
 *             counterid - [IN] performance counter identifier                *
 *             instance  - [IN] performance counter instance or "" for        *
 *                              aggregate data                                *
 *             coeff     - [IN] coefficient to apply to value                 *
 *             unit      - [IN] counter unit info (kilo, mega, % etc)         *
 *             result    - [OUT] output result                                *
 *                                                                            *
 * Return value: SYSINFO_RET_OK, result has value - performance counter value *
 *                               was successfully retrieved                   *
 *               SYSINFO_RET_OK, result has no value - performance counter    *
 *                               was found without value                      *
 *               SYSINFO_RET_FAIL - otherwise, error message is set in result *
 *                                                                            *
 * Comments: There can be situation when performance counter is configured    *
 *           to be read but the collector has not yet processed it. In this   *
 *           case return SYSINFO_RET_OK with empty result so that it is       *
 *           ignored by server rather than generating error.                  *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_counter_value_by_id(const zbx_vmware_service_t *service, const char *type,
		const char *id, zbx_uint64_t counterid, const char *instance, unsigned int coeff, int unit,
		AGENT_RESULT *result)
{
	zbx_vmware_perf_entity_t	*entity;
	zbx_vmware_perf_counter_t	*perfcounter;
	zbx_str_uint64_pair_t		*perfvalue;
	int				i, ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() type:%s id:%s counterid:" ZBX_FS_UI64 " instance:%s", __func__,
			type, id, counterid, instance);

	zbx_vmware_perf_counter_t	loc = {.counterid = counterid};

	if (NULL == (entity = zbx_vmware_service_get_perf_entity(service, type, id)))
	{
		/* requested counter has not been queried yet */
		zabbix_log(LOG_LEVEL_DEBUG, "performance data is not yet ready, ignoring request");
		ret = SYSINFO_RET_OK;
		goto out;
	}

	if (NULL != entity->error)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, entity->error));
		goto out;
	}

	if (FAIL == (i = zbx_vector_vmware_perf_counter_ptr_bsearch(&entity->counters, &loc,
			ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Performance counter data was not found."));
		goto out;
	}

	perfcounter = (zbx_vmware_perf_counter_t *)entity->counters.values[i];

	if (0 != (perfcounter->state & ZBX_VMWARE_COUNTER_NOTSUPPORTED))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Performance counter not supported or data not ready."));
		goto out;
	}

	if (0 != (ZBX_VMWARE_COUNTER_CUSTOM & perfcounter->state) &&
			0 != (ZBX_VMWARE_COUNTER_READY & perfcounter->state))
	{
		perfcounter->last_used = time(NULL);
	}

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
		perfvalue = &perfcounter->values.values[i];

		if (0 == strcmp(perfvalue->name, instance))
			break;
	}

	if (i == perfcounter->values.values_num)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Performance counter instance was not found."));
		goto out;
	}

	/* VMware returns -1 value if the performance data for the specified period is not ready - ignore it. */
	if (ZBX_MAX_UINT64 == perfvalue->value)
	{
		ret = SYSINFO_RET_OK;
		goto out;
	}

	if (0 != coeff)
	{
		SET_UI64_RESULT(result, perfvalue->value * coeff);
	}
	else
	{
		switch (unit)
		{
		case ZBX_VMWARE_UNIT_KILOBYTES:
		case ZBX_VMWARE_UNIT_KILOBYTESPERSECOND:
			SET_UI64_RESULT(result, perfvalue->value * ZBX_KIBIBYTE);
			break;
		case ZBX_VMWARE_UNIT_MEGABYTES:
		case ZBX_VMWARE_UNIT_MEGABYTESPERSECOND:
			SET_UI64_RESULT(result, perfvalue->value * ZBX_MEBIBYTE);
			break;
		case ZBX_VMWARE_UNIT_GIGABYTES:
			SET_UI64_RESULT(result, perfvalue->value * ZBX_GIBIBYTE);
			break;
		case ZBX_VMWARE_UNIT_TERABYTES:
			SET_UI64_RESULT(result, perfvalue->value * ZBX_TEBIBYTE);
			break;
		case ZBX_VMWARE_UNIT_PERCENT:
			SET_DBL_RESULT(result, (double)perfvalue->value / 100.0);
			break;
		case ZBX_VMWARE_UNIT_MEGAHERTZ:
			SET_UI64_RESULT(result, perfvalue->value * 1000000);
			break;
		case ZBX_VMWARE_UNIT_JOULE:
		case ZBX_VMWARE_UNIT_NANOSECOND:
		case ZBX_VMWARE_UNIT_MICROSECOND:
		case ZBX_VMWARE_UNIT_MILLISECOND:
		case ZBX_VMWARE_UNIT_NUMBER:
		case ZBX_VMWARE_UNIT_SECOND:
		case ZBX_VMWARE_UNIT_WATT:
		case ZBX_VMWARE_UNIT_CELSIUS:
			SET_UI64_RESULT(result, perfvalue->value);
			break;
		default:
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Performance counter type of unitInfo is unknown. "
					"Counter id:" ZBX_FS_UI64, counterid));
			goto out;
		}
	}

	ret = SYSINFO_RET_OK;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets vmware performance counter value by path                     *
 *                                                                            *
 * Parameters: service  - [IN] vmware service                                 *
 *             type     - [IN] performance entity type (HostSystem,           *
 *                             VirtualMachine, Datastore)                     *
 *             id       - [IN] performance entity identifier                  *
 *             path     - [IN] performance counter path                       *
 *                             (<group>/<key>[<rollup type>])                 *
 *             instance - [IN] performance counter instance or "" for         *
 *                             aggregate data                                 *
 *             coeff    - [IN] coefficient to apply to value                  *
 *             result   - [OUT] output result                                 *
 *                                                                            *
 * Return value: SYSINFO_RET_OK, result has value - performance counter value *
 *                               was successfully retrieved                   *
 *               SYSINFO_RET_OK, result has no value - performance counter    *
 *                               was found without value                      *
 *               SYSINFO_RET_FAIL - otherwise, error message is set in result *
 *                                                                            *
 * Comments: There can be situation when performance counter is configured    *
 *           to be read but the collector has not yet processed it. In this   *
 *           case return SYSINFO_RET_OK with empty result so that it is       *
 *           ignored by server rather than generating error.                  *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_counter_value_by_path(const zbx_vmware_service_t *service, const char *type,
		const char *id, const char *path, const char *instance, unsigned int coeff, AGENT_RESULT *result)
{
	zbx_uint64_t	counterid;
	int		unit;

	if (FAIL == zbx_vmware_service_get_counterid(service, path, &counterid, &unit))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Performance counter is not available."));
		return SYSINFO_RET_FAIL;
	}

	return vmware_service_get_counter_value_by_id(service, type, id, counterid, instance, coeff, unit, result);
}

static int	vmware_service_get_vm_counter(zbx_vmware_service_t *service, const char *uuid, const char *instance,
		const char *path, unsigned int coeff, AGENT_RESULT *result)
{
	zbx_vmware_vm_t	*vm;
	int		ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() uuid:%s instance:%s path:%s", __func__, uuid, instance, path);

	if (NULL == (vm = service_vm_get(service, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown virtual machine uuid."));
		goto out;
	}

	ret = vmware_service_get_counter_value_by_path(service, "VirtualMachine", vm->id, path, instance, coeff,
			result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets vmware service object                                        *
 *                                                                            *
 * Parameters: url       - [IN] vmware service URL                            *
 *             username  - [IN] vmware service username                       *
 *             password  - [IN] vmware service password                       *
 *             result    - [OUT]                                              *
 *             ret       - [OUT] operation result code                        *
 *                                                                            *
 * Return value: The vmware service object or NULL if the service was not     *
 *               found, did not have data or any error occurred. In the last  *
 *               case the error message will be stored in agent result.       *
 *                                                                            *
 * Comments: There are three possible cases:                                  *
 *             1) The vmware service is not ready. This can happen when       *
 *                service was added, but not yet processed by collector.      *
 *                In this case NULL is returned and result code is set to     *
 *                SYSINFO_RET_OK.                                             *
 *             2) The vmware service update failed. This can happen if there  *
 *                was a network problem, authentication failure or any error  *
 *                that prevented from obtaining and parsing vmware data.      *
 *                In this case NULL is returned and result code is set to     *
 *                SYSINFO_RET_FAIL.                                           *
 *             3) The vmware service has been updated successfully.           *
 *                In this case the service object is returned and result code *
 *                is not set.                                                 *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_service_t	*get_vmware_service(const char *url, const char *username, const char *password,
		AGENT_RESULT *result, int *ret)
{
	zbx_vmware_service_t	*service;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() '%s'@'%s'", __func__, username, url);

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
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%p", __func__, (void *)service);

	return service;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves data from virtual machine details                       *
 *                                                                            *
 * Parameters: request   - [IN] The original request. The first parameter is  *
 *                              vmware service URL and the second parameter   *
 *                              is virtual machine uuid.                      *
 *             username  - [IN] vmware service user name                      *
 *             password  - [IN] vmware service password                       *
 *             propid    - [IN]                                               *
 *             result    - [OUT] request result                               *
 *                                                                            *
 ******************************************************************************/
static int	get_vcenter_vmprop(const AGENT_REQUEST *request, const char *username, const char *password,
		int propid, AGENT_RESULT *result)
{
	zbx_vmware_service_t	*service;
	zbx_vmware_vm_t		*vm = NULL;
	const char		*url, *uuid, *value;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() propid:%d", __func__, propid);

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
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves hypervisor property                                     *
 *                                                                            *
 * Parameters: request  - [IN] The original request. The first parameter is   *
 *                             vmware service URL and the second parameter.   *
 *                             is hypervisor uuid.                            *
 *             username - [IN] vmware service user name                       *
 *             password - [IN] vmware service password                        *
 *             propid   - [IN] property id                                    *
 *             result   - [OUT] request result                                *
 *                                                                            *
 ******************************************************************************/
static int	get_vcenter_hvprop(const AGENT_REQUEST *request, const char *username, const char *password, int propid,
		AGENT_RESULT *result)
{
	zbx_vmware_service_t	*service;
	const char		*uuid, *url, *value;
	zbx_vmware_hv_t		*hv;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() propid:%d", __func__, propid);

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
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

static int	custquery_read_result(zbx_vmware_cust_query_t *custom_query, AGENT_RESULT *result)
{
	if (0 != (custom_query->state & ZBX_VMWARE_CQ_ERROR))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Custom query error: %s", custom_query->error));
		return SYSINFO_RET_FAIL;
	}

	if (0 != (custom_query->state & ZBX_VMWARE_CQ_READY))
	{
		SET_STR_RESULT(result, zbx_strdup(NULL, ZBX_NULL2EMPTY_STR(custom_query->value)));

		if (0 != (custom_query->state & ZBX_VMWARE_CQ_PAUSED))
			custom_query->state &= (unsigned char)~ZBX_VMWARE_CQ_PAUSED;

		if (NULL != custom_query->value && '\0' != *custom_query->value &&
				0 != (custom_query->state & ZBX_VMWARE_CQ_SEPARATE))
		{
			custom_query->state &= (unsigned char)~ZBX_VMWARE_CQ_SEPARATE;
		}
	}

	custom_query->last_pooled = time(NULL);

	return SYSINFO_RET_OK;
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates json document with tags info                              *
 *                                                                            *
 * Parameters:                                                                *
 *             data_tags   - [IN] all tags and linked objects                 *
 *             uuid        - [IN] vmware object uuid                          *
 *             tag_name    - [IN] name of tags array                          *
 *             json_data   - [OUT] json document                              *
 *             error       - [OUT] error of tags receiving (optional)         *
 *                                                                            *
 ******************************************************************************/
static void	vmware_tags_uuid_json(const zbx_vmware_data_tags_t *data_tags, const char *uuid, const char *tag_name,
		struct zbx_json *json_data, char **error)
{
	int				i;
	zbx_vmware_entity_tags_t	entity_cmp;
	zbx_vector_vmware_tag_ptr_t	*tags;

	if (NULL != data_tags->error)
	{
		if (NULL != error)
			*error = zbx_strdup(NULL, data_tags->error);

		return;
	}

	entity_cmp.uuid = (char *)uuid;

	if (FAIL == (i = zbx_vector_vmware_entity_tags_ptr_bsearch(&data_tags->entity_tags, &entity_cmp,
			ZBX_DEFAULT_STR_PTR_COMPARE_FUNC)))
	{
		return;
	}

	if (NULL != error && NULL != data_tags->entity_tags.values[i]->error)
	{
		*error = zbx_strdup(NULL, data_tags->entity_tags.values[i]->error);
		return;
	}

	tags = &data_tags->entity_tags.values[i]->tags;

	if (NULL != tag_name)
		zbx_json_addarray(json_data, tag_name);

	for (i = 0; i < tags->values_num; i++)
	{
		zbx_vmware_tag_t	*tag = tags->values[i];

		zbx_json_addobject(json_data, NULL);
		zbx_json_addstring(json_data, "name", tag->name, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(json_data, "description", tag->description, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(json_data, "category", tag->category, ZBX_JSON_TYPE_STRING);
		zbx_json_close(json_data);
	}

	if (NULL != tag_name)
		zbx_json_close(json_data);

}

/******************************************************************************
 *                                                                            *
 * Purpose: updates json document with tags info by object id                 *
 *                                                                            *
 * Parameters:                                                                *
 *             data_tags   - [IN]                                             *
 *             type        - [IN] HostSystem, VirtualMachine etc              *
 *             id          - [IN] id of hv, vm etc                            *
 *             tag_name    - [IN] name of tags array                          *
 *             json_data   - [OUT] json document                              *
 *             error       - [OUT] error of tags receiving (optional)         *
 *                                                                            *
 ******************************************************************************/
static void	vmware_tags_id_json(const zbx_vmware_data_tags_t *data_tags, const char *type,
		const char *id, const char *tag_name, struct zbx_json *json_data, char **error)
{
	char	uuid[MAX_STRING_LEN / 8];

	zbx_snprintf(uuid, sizeof(uuid),"%s:%s", type, id);
	vmware_tags_uuid_json(data_tags, uuid, tag_name, json_data, error);
}

int	check_vcenter_cluster_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	struct zbx_json		json_data;
	const char		*url;
	zbx_vmware_service_t	*service;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (1 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	zbx_json_initarray(&json_data, ZBX_JSON_STAT_BUF_LEN);

	for (int i = 0; i < service->data->clusters.values_num; i++)
	{
		zbx_vmware_cluster_t	*cluster = (zbx_vmware_cluster_t *)service->data->clusters.values[i];

		zbx_json_addobject(&json_data, NULL);
		zbx_json_addstring(&json_data, "{#CLUSTER.ID}", cluster->id, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "{#CLUSTER.NAME}", cluster->name, ZBX_JSON_TYPE_STRING);
		zbx_json_addarray(&json_data, "resource_pool");

		for (int j = 0; j < service->data->resourcepools.values_num; j++)
		{
			zbx_vmware_resourcepool_t	*rp = service->data->resourcepools.values[j];

			if (0 != strcmp(rp->parentid, cluster->id))
				continue;

			zbx_json_addobject(&json_data, NULL);
			zbx_json_addstring(&json_data, "rpid", rp->id, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json_data, "rpath", rp->path, ZBX_JSON_TYPE_STRING);
			zbx_json_adduint64(&json_data, "vm_count", rp->vm_num);
			vmware_tags_id_json(&service->data_tags, ZBX_VMWARE_SOAP_RESOURCEPOOL, rp->id, "tags",
					&json_data, NULL);
			zbx_json_close(&json_data);
		}

		zbx_json_close(&json_data);
		vmware_tags_id_json(&service->data_tags, ZBX_VMWARE_SOAP_CLUSTER, cluster->id, "tags", &json_data,
				NULL);
		zbx_json_addarray(&json_data, "datastore_uuid");

		for (int j = 0; j < cluster->dss_uuid.values_num; j++)
			zbx_json_addstring(&json_data, NULL, cluster->dss_uuid.values[j], ZBX_JSON_TYPE_STRING);

		zbx_json_close(&json_data);
		zbx_json_close(&json_data);
	}

	zbx_json_close(&json_data);

	SET_STR_RESULT(result, zbx_strdup(NULL, json_data.buffer));

	zbx_json_free(&json_data);

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_cluster_property(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char			*url, *id, *key, *type = ZBX_VMWARE_SOAP_CLUSTER, *mode = "";
	int				ret = SYSINFO_RET_FAIL;
	char				*key_esc = NULL;
	zbx_vmware_service_t		*service;
	zbx_vmware_cluster_t		*cl;
	zbx_vmware_cust_query_t		*custom_query;
	zbx_vmware_custom_query_type_t	query_type = VMWARE_OBJECT_PROPERTY;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (3 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	id = get_rparam(request, 1);
	key = get_rparam(request, 2);

	if (NULL == key || '\0' == *key)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		goto out;
	}

	key_esc = zbx_xml_escape_dyn(key);

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (cl = cluster_get(&service->data->clusters, id)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown cluster id."));
		goto unlock;
	}

	/* FAIL is returned if custom query exists */
	if (NULL == (custom_query = zbx_vmware_service_get_cust_query(service, type, cl->id, key_esc, query_type, mode))
			&& NULL != (custom_query = zbx_vmware_service_add_cust_query(service, type, cl->id, key_esc,
			query_type, mode, NULL)))
	{
		ret = SYSINFO_RET_OK;
		goto unlock;
	}
	else if (NULL == custom_query)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown vmware property query."));
		goto unlock;
	}

	ret = custquery_read_result(custom_query, result);
unlock:
	zbx_vmware_unlock();
out:
	zbx_str_free(key_esc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_cluster_status(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*url, *name;
	zbx_vmware_service_t	*service;
	zbx_vmware_cluster_t	*cluster;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	if (NULL == cluster->status)
		goto unlock;

	ret = SYSINFO_RET_OK;

	if (0 == strcmp(cluster->status, "gray"))
		SET_UI64_RESULT(result, 0);
	else if (0 == strcmp(cluster->status, "green"))
		SET_UI64_RESULT(result, 1);
	else if (0 == strcmp(cluster->status, "yellow"))
		SET_UI64_RESULT(result, 2);
	else if (0 == strcmp(cluster->status, "red"))
		SET_UI64_RESULT(result, 3);
	else
		ret = SYSINFO_RET_FAIL;

unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_cluster_tags_get(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_service_t		*service;
	zbx_vmware_cluster_t		*cl = NULL;
	int				ret = SYSINFO_RET_FAIL;
	const char			*url, *id;
	struct zbx_json			json_data;
	char				*error = NULL;

	if (2 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	id = get_rparam(request, 1);

	if ('\0' == *id)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (cl = cluster_get(&service->data->clusters, id)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown cluster id."));
		goto unlock;
	}

	if (NULL != service->data_tags.error)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, service->data_tags.error));
		goto unlock;
	}

	zbx_json_initarray(&json_data, ZBX_JSON_STAT_BUF_LEN);
	vmware_tags_id_json(&service->data_tags, ZBX_VMWARE_SOAP_CLUSTER, cl->id, NULL, &json_data, &error);
	zbx_json_close(&json_data);

	if (NULL == error)
	{
		SET_TEXT_RESULT(result, zbx_strdup(NULL, json_data.buffer));
		ret = SYSINFO_RET_OK;
	}
	else
		SET_STR_RESULT(result, error);

	zbx_json_free(&json_data);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

static void	vmware_get_events(const zbx_vector_vmware_event_ptr_t *events,
		const zbx_vmware_eventlog_state_t *evt_state, const zbx_dc_item_t *item,
		zbx_vector_agent_result_ptr_t *add_results)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() last_key:" ZBX_FS_UI64 " last_ts:" ZBX_FS_TIME_T " events:%d top event id:"
			ZBX_FS_UI64 " top event ts:" ZBX_FS_TIME_T, __func__, evt_state->last_key, evt_state->last_ts,
			events->values_num, events->values[0]->key, events->values[0]->timestamp);

	/* events were retrieved in reverse chronological order */
	for (int i = events->values_num - 1; i >= 0; i--)
	{
		const zbx_vmware_event_t	*event = events->values[i];
		AGENT_RESULT			*add_result = NULL;

		/* Event id of ESXi will reset when ESXi is rebooted */
		if (event->timestamp <= evt_state->last_ts && event->key <= evt_state->last_key)
			continue;

		add_result = (AGENT_RESULT *)zbx_malloc(add_result, sizeof(AGENT_RESULT));
		zbx_init_agent_result(add_result);

		if (SUCCEED == zbx_set_agent_result_type(add_result, item->value_type, event->message))
		{
			zbx_set_agent_result_meta(add_result, event->key, 0);

			if (ITEM_VALUE_TYPE_LOG == item->value_type)
			{
				add_result->log->logeventid = event->key;
				add_result->log->timestamp = (int)event->timestamp;
			}

			zbx_vector_agent_result_ptr_append(add_results, add_result);
		}
		else
			zbx_free(add_result);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): events:%d", __func__, add_results->values_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Converts a single value of VMware event log level from string to  *
 *          bitmask.                                                          *
 *                                                                            *
 * Parameters: level         - [IN]                                           *
 *             severity_mask - [OUT]                                          *
 *                                                                            *
 * Return value: SUCCEED - if no errors were detected                         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	severity_to_mask(const char *level, unsigned char *severity_mask)
{
	size_t			i;
	static const char	*levels[] = {ZBX_VMWARE_EVTLOG_SEVERITIES};

	for (i = 0; i < ARRSIZE(levels); i++)
	{
		if (0 == strcmp(level, levels[i]))
			break;
	}

	*severity_mask = (unsigned char)(1 << i);

	return (i < ARRSIZE(levels)) ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: converts VMware event log level severity to bitmask               *
 *                                                                            *
 * Parameters: severity - [IN] event severity value from item parameter,      *
 *                             which might contain multiple severity levels   *
 *             mask     - [OUT] result of conversion                          *
 *             error    - [OUT] error message in case of error                *
 *                                                                            *
 * Return value: SUCCEED - if no errors were detected                         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	evt_severities_to_mask(const char *severity, unsigned char *mask, char **error)
{
	*mask = 0;

	for (int i = 1; i <= zbx_num_param(severity); i++)
	{
		unsigned char	level_mask;
		char		*level;

		if (NULL == (level = zbx_get_param_dyn(severity, i, NULL)))
			continue;

		if (SUCCEED != severity_to_mask(level, &level_mask))
		{
			*error = zbx_dsprintf(*error, "Invalid event severity level \"%s\".", level);
			zbx_free(level);
			return FAIL;
		}

		*mask |= level_mask;
		zbx_free(level);
	}

	return SUCCEED;
}

int	check_vcenter_eventlog(AGENT_REQUEST *request, const zbx_dc_item_t *item, AGENT_RESULT *result,
		zbx_vector_agent_result_ptr_t *add_results)
{
	const char		*url, *skip, *severity_str;
	unsigned char		skip_old, severity = 0;
	zbx_vmware_service_t	*service;
	int			ret = SYSINFO_RET_FAIL;
	time_t			lastaccess;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (3 < request->nparam || 0 == request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);

	if (NULL == (skip = get_rparam(request, 1)) || '\0' == *skip || 0 == strcmp(skip, "all"))
	{
		skip_old = 0;
	}
	else if (0 == strcmp(skip, "skip"))
	{
		skip_old = (0 == request->lastlogsize ? 1 : 0);
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	if (3 == request->nparam && NULL != (severity_str = get_rparam(request, 2)) && '\0' != *severity_str)
	{
		char	*error = NULL;

		if (FAIL == evt_severities_to_mask(severity_str, &severity, &error))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Invalid third parameter. %s", error));
			zbx_free(error);
			goto out;
		}
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, item->username, item->password, result, &ret)))
		goto unlock;

	lastaccess = time(NULL);

	if (0 != service->eventlog.lastaccess &&
			service->eventlog.interval != lastaccess - service->eventlog.lastaccess)
	{
		service->jobs_flag |= ZBX_VMWARE_REQ_UPDATE_EVENTLOG;
		service->eventlog.interval = lastaccess - service->eventlog.lastaccess;
	}

	service->eventlog.lastaccess = lastaccess;

	if (0 == (service->jobs_flag & ZBX_VMWARE_UPDATE_EVENTLOG))
		service->jobs_flag |= ZBX_VMWARE_REQ_UPDATE_EVENTLOG;

	if (severity != service->eventlog.severity)
		service->eventlog.severity = severity;

	if (ZBX_VMWARE_EVENT_KEY_UNINITIALIZED == service->eventlog.last_key ||
			(0 != skip_old && service->eventlog.owner_itemid != item->itemid))
	{
		/* this may happen if recreate item vmware.eventlog for same service URL */
		service->eventlog.last_key = request->lastlogsize;
		service->eventlog.last_ts = 0;
		service->eventlog.skip_old = skip_old;
		service->eventlog.owner_itemid = item->itemid;
	}
	else if (item->itemid != service->eventlog.owner_itemid)
	{
		/* To protect against data fragmentation among multiple vmware event items. */
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Duplicate VMware eventlog item is not supported"));
		goto unlock;
	}
	else if (0 != service->eventlog.oom)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Not enough shared memory to store VMware events."));
		goto unlock;
	}
	else if (NULL == service->eventlog.data)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s():eventlog data is still not collected", __func__);
	}
	else if (NULL != service->eventlog.data->error)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, service->eventlog.data->error));
		goto unlock;
	}
	else if (0 < service->eventlog.data->events.values_num)
	{
		/* Some times request->lastlogsize value gets stuck due to concurrent update of history cache */
		/* thereby we can't rely to value of the request->lastlogsize and have to return events based on */
		/* internal state of the service->eventlog */
		vmware_get_events(&service->eventlog.data->events, &service->eventlog, item, add_results);
		service->eventlog.last_key = service->eventlog.data->events.values[0]->key;
		service->eventlog.last_ts = service->eventlog.data->events.values[0]->timestamp;
	}

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_version(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*url;
	zbx_vmware_service_t	*service;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (1 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == service->version)
		goto unlock;

	SET_STR_RESULT(result, zbx_strdup(NULL, service->version));

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_fullname(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	char			*url;
	zbx_vmware_service_t	*service;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (1 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == service->fullname)
		goto unlock;

	SET_STR_RESULT(result, zbx_strdup(NULL, service->fullname));

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_cluster_name(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*url, *uuid;
	zbx_vmware_hv_t		*hv;
	zbx_vmware_service_t	*service;
	zbx_vmware_cluster_t	*cluster = NULL;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_connectionstate(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_CONNECTIONSTATE, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_cpu_usage(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_OVERALL_CPU_USAGE, result);

	if (SYSINFO_RET_OK == ret && NULL != ZBX_GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * 1000000;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_cpu_usage_perf(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*url, *uuid;
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	ret = vmware_service_get_counter_value_by_path(service, "HostSystem", hv->id, "cpu/usage[average]", "", 0,
			result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_cpu_utilization(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*url, *uuid;
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	ret = vmware_service_get_counter_value_by_path(service, "HostSystem", hv->id, "cpu/utilization[average]", "",
			0, result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_power(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*path, *url, *uuid, *max;
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (2 > request->nparam || request->nparam > 3)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	max = get_rparam(request, 2);

	if ('\0' == *uuid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	if (NULL != max && '\0' != *max)
	{
		if (0 != strcmp(max, "max"))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
			goto out;
		}

		path = "power/powerCap[average]";
	}
	else
		path = "power/power[average]";

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (hv = hv_get(&service->data->hvs, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown hypervisor uuid."));
		goto unlock;
	}

	ret = vmware_service_get_counter_value_by_path(service, "HostSystem", hv->id, path, "", 1, result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	struct zbx_json		json_data;
	const char		*url;
	zbx_vmware_service_t	*service;
	int			ret = SYSINFO_RET_FAIL;
	zbx_vmware_hv_t		*hv;
	zbx_hashset_iter_t	iter;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (1 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	zbx_json_initarray(&json_data, ZBX_JSON_STAT_BUF_LEN);

	zbx_hashset_iter_reset(&service->data->hvs, &iter);
	while (NULL != (hv = (zbx_vmware_hv_t *)zbx_hashset_iter_next(&iter)))
	{
		const char		*name;
		zbx_vmware_cluster_t	*cluster = NULL;

		if (NULL == (name = hv->props[ZBX_VMWARE_HVPROP_NAME]))
			continue;

		if (NULL != hv->clusterid)
			cluster = cluster_get(&service->data->clusters, hv->clusterid);

		zbx_json_addobject(&json_data, NULL);
		zbx_json_addstring(&json_data, "{#HV.UUID}", hv->uuid, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "{#HV.ID}", hv->id, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "{#HV.NAME}", name, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "{#HV.IP}", ZBX_NULL2EMPTY_STR(hv->ip), ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "{#DATACENTER.NAME}", hv->datacenter_name, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "{#CLUSTER.NAME}",
				NULL != cluster ? cluster->name : "", ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "{#PARENT.NAME}", hv->parent_name, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "{#PARENT.TYPE}", hv->parent_type, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "{#HV.NETNAME}",
				ZBX_NULL2EMPTY_STR(hv->props[ZBX_VMWARE_HVPROP_NET_NAME]), ZBX_JSON_TYPE_STRING);
		zbx_json_addarray(&json_data, "resource_pool");

		for (int i = 0; NULL == cluster && i < service->data->resourcepools.values_num; i++)
		{
			zbx_vmware_resourcepool_t	*rp = service->data->resourcepools.values[i];

			if (0 != strcmp(rp->parentid, hv->props[ZBX_VMWARE_HVPROP_PARENT]))
				continue;

			zbx_json_addobject(&json_data, NULL);
			zbx_json_addstring(&json_data, "rpid", rp->id, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json_data, "rpath", rp->path, ZBX_JSON_TYPE_STRING);
			zbx_json_adduint64(&json_data, "vm_count", rp->vm_num);
			vmware_tags_id_json(&service->data_tags, ZBX_VMWARE_SOAP_RESOURCEPOOL, rp->id, "tags",
					&json_data, NULL);
			zbx_json_close(&json_data);
		}

		zbx_json_close(&json_data);
		vmware_tags_uuid_json(&service->data_tags, hv->uuid, "tags", &json_data, NULL);
		zbx_json_close(&json_data);
	}

	zbx_json_close(&json_data);

	SET_STR_RESULT(result, zbx_strdup(NULL, json_data.buffer));

	zbx_json_free(&json_data);

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_diskinfo_get(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	struct zbx_json		json_data;
	const char		*url, *uuid;
	zbx_vmware_service_t	*service;
	int			ret = SYSINFO_RET_FAIL;
	zbx_vmware_hv_t		*hv;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	zbx_json_initarray(&json_data, ZBX_JSON_STAT_BUF_LEN);

	for (int i = 0; i < hv->diskinfo.values_num; i++)
	{
		zbx_vmware_diskinfo_t	*di = hv->diskinfo.values[i];

		zbx_json_addobject(&json_data, NULL);
		zbx_json_addstring(&json_data, "instance", di->diskname,
				ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "hv_uuid", hv->uuid, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "datastore_uuid", ZBX_NULL2EMPTY_STR(di->ds_uuid),
				ZBX_JSON_TYPE_STRING);
		zbx_json_addraw(&json_data, "operational_state", ZBX_NULL2EMPTY_STR(di->operational_state));
		zbx_json_addstring(&json_data, "lun_type", ZBX_NULL2EMPTY_STR(di->lun_type),
				ZBX_JSON_TYPE_STRING);
		zbx_json_addint64(&json_data, "queue_depth", di->queue_depth);
		zbx_json_addstring(&json_data, "model", ZBX_NULL2EMPTY_STR(di->model),
				ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "vendor", ZBX_NULL2EMPTY_STR(di->vendor),
				ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "revision", ZBX_NULL2EMPTY_STR(di->revision),
				ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "serial_number", ZBX_NULL2EMPTY_STR(di->serial_number),
				ZBX_JSON_TYPE_STRING);
		zbx_json_addobject(&json_data, "vsan");

		if (NULL != di->vsan)
		{
			zbx_json_addstring(&json_data, "ssd", ZBX_NULL2EMPTY_STR(di->vsan->ssd),
					ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json_data, "local_disk", ZBX_NULL2EMPTY_STR(di->vsan->local_disk),
					ZBX_JSON_TYPE_STRING);
			zbx_json_adduint64(&json_data, "block", di->vsan->block);
			zbx_json_adduint64(&json_data, "block_size", di->vsan->block_size);
		}

		zbx_json_close(&json_data);
		zbx_json_close(&json_data);
	}

	zbx_json_close(&json_data);

	SET_STR_RESULT(result, zbx_strdup(NULL, json_data.buffer));

	zbx_json_free(&json_data);

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_fullname(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_FULL_NAME, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_hw_cpu_num(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_HW_NUM_CPU_CORES, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_hw_cpu_freq(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_HW_CPU_MHZ, result);

	if (SYSINFO_RET_OK == ret && NULL != ZBX_GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * 1000000;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_hw_cpu_model(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_HW_CPU_MODEL, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_hw_cpu_threads(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_HW_NUM_CPU_THREADS, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_hw_memory(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_HW_MEMORY_SIZE, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_hw_model(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_HW_MODEL, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_hw_serialnumber(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_HW_SERIALNUMBER, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_hw_uuid(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_HW_UUID, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_hw_vendor(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_HW_VENDOR, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_memory_size_ballooned(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int			ret = SYSINFO_RET_FAIL;
	zbx_vmware_service_t	*service;
	const char		*uuid, *url;
	zbx_vmware_hv_t		*hv;
	zbx_uint64_t		value = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	for (int i = 0; i < hv->vms.values_num; i++)
	{
		zbx_uint64_t	mem;
		const char	*value_str;
		zbx_vmware_vm_t	*vm = (zbx_vmware_vm_t *)hv->vms.values[i];

		if (NULL == (value_str = vm->props[ZBX_VMWARE_VMPROP_MEMORY_SIZE_BALLOONED]))
			continue;

		if (SUCCEED != zbx_is_uint64(value_str, &mem))
			continue;

		value += mem;
	}

	value *= ZBX_MEBIBYTE;
	SET_UI64_RESULT(result, value);

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_memory_used(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_MEMORY_USED, result);

	if (SYSINFO_RET_OK == ret && NULL != ZBX_GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_property(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char			*url, *uuid, *key, *type = ZBX_VMWARE_SOAP_HV, *mode = "";
	int				ret = SYSINFO_RET_FAIL;
	char				*key_esc = NULL;
	zbx_vmware_service_t		*service;
	zbx_vmware_hv_t			*hv;
	zbx_vmware_cust_query_t		*custom_query;
	zbx_vmware_custom_query_type_t	query_type = VMWARE_OBJECT_PROPERTY;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (3 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	key = get_rparam(request, 2);

	if (NULL == key || '\0' == *key)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		goto out;
	}

	key_esc = zbx_xml_escape_dyn(key);

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (hv = hv_get(&service->data->hvs, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown hypervisor uuid."));
		goto unlock;
	}

	/* FAIL is returned if custom query exists */
	if (NULL == (custom_query = zbx_vmware_service_get_cust_query(service, type, hv->id, key_esc, query_type, mode))
			&& NULL != (custom_query = zbx_vmware_service_add_cust_query(service, type, hv->id, key_esc,
			query_type, mode, NULL)))
	{
		ret = SYSINFO_RET_OK;
		goto unlock;
	}
	else if (NULL == custom_query)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown vmware property query."));
		goto unlock;
	}

	ret = custquery_read_result(custom_query, result);
unlock:
	zbx_vmware_unlock();
out:
	zbx_str_free(key_esc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_sensor_health_state(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_HEALTH_STATE, result);

	if (SYSINFO_RET_OK == ret && NULL != ZBX_GET_STR_RESULT(result))
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

		ZBX_UNSET_STR_RESULT(result);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_status(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_STATUS, result);

	if (SYSINFO_RET_OK == ret && NULL != ZBX_GET_STR_RESULT(result))
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

		ZBX_UNSET_STR_RESULT(result);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_maintenance(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_MAINTENANCE, result);

	if (SYSINFO_RET_OK == ret && NULL != ZBX_GET_STR_RESULT(result))
	{
		if (0 == strcmp(result->str, "false"))
			SET_UI64_RESULT(result, 0);
		else
			SET_UI64_RESULT(result, 1);

		ZBX_UNSET_STR_RESULT(result);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_uptime(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_UPTIME, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_version(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_VERSION, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_sensors_get(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_SENSOR, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_hw_sensors_get(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_hvprop(request, username, password, ZBX_VMWARE_HVPROP_HW_SENSOR, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_vm_num(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int			ret = SYSINFO_RET_FAIL;
	zbx_vmware_service_t	*service;
	const char		*uuid, *url;
	zbx_vmware_hv_t		*hv;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

static int	check_vcenter_hv_network_common(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result, int direction, const char *func_parent)
{
	const char		*url, *mode, *uuid, *counter_name;
	unsigned int		coeff = 0;
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(), func_parent:'%s'", __func__, func_parent);

	if (2 > request->nparam || request->nparam > 3)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	mode = get_rparam(request, 2);

	if (NULL == mode || '\0' == *mode || 0 == strcmp(mode, "bps"))
	{
		counter_name = ZBX_IF_DIRECTION_IN == direction ? "net/received[average]" : "net/transmitted[average]";
		coeff = ZBX_KIBIBYTE;
	}
	else if (0 == strcmp(mode, "packets"))
	{
		counter_name = ZBX_IF_DIRECTION_IN ==
				direction ? "net/packetsRx[summation]" : "net/packetsTx[summation]";
	}
	else if (0 == strcmp(mode, "dropped"))
	{
		counter_name = ZBX_IF_DIRECTION_IN ==
				direction ? "net/droppedRx[summation]" : "net/droppedTx[summation]";
	}
	else if (0 == strcmp(mode, "errors"))
	{
		counter_name = ZBX_IF_DIRECTION_IN == direction ? "net/errorsRx[summation]" : "net/errorsTx[summation]";
	}
	else if (0 == strcmp(mode, "broadcast"))
	{
		counter_name = ZBX_IF_DIRECTION_IN ==
				direction ? "net/broadcastRx[summation]" : "net/broadcastTx[summation]";
	}
	else
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

	ret = vmware_service_get_counter_value_by_path(service, "HostSystem", hv->id, counter_name, "",
			coeff, result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(), func_parent:'%s', ret: %s", __func__, func_parent,
			zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_network_in(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return	check_vcenter_hv_network_common(request, username, password, result, ZBX_IF_DIRECTION_IN, __func__);
}

int	check_vcenter_hv_network_out(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return	check_vcenter_hv_network_common(request, username, password, result, ZBX_IF_DIRECTION_OUT, __func__);
}

int	check_vcenter_hv_net_if_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int			ret = SYSINFO_RET_FAIL;
	const char		*url, *uuid;
	struct zbx_json		json_data;
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	zbx_vmware_pnic_t	*nic;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	zbx_json_initarray(&json_data, ZBX_JSON_STAT_BUF_LEN);

	for (int i = 0; i < hv->pnics.values_num; i++)
	{
		nic = hv->pnics.values[i];

		zbx_json_addobject(&json_data, NULL);
		zbx_json_addstring(&json_data, "{#IFNAME}", nic->name, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "{#IFDRIVER}", ZBX_NULL2EMPTY_STR(nic->driver), ZBX_JSON_TYPE_STRING);
		zbx_json_adduint64(&json_data, "{#IFSPEED}", nic->speed);
		zbx_json_addstring(&json_data, "{#IFDUPLEX}", ZBX_DUPLEX_FULL == nic->duplex ? "full" : "half",
				ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "{#IFMAC}", ZBX_NULL2EMPTY_STR(nic->mac), ZBX_JSON_TYPE_STRING);

		zbx_json_close(&json_data);
	}

	zbx_json_close(&json_data);

	SET_STR_RESULT(result, zbx_strdup(NULL, json_data.buffer));
	zbx_json_free(&json_data);

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(), ret: %s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_network_linkspeed(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int			i, ret = SYSINFO_RET_FAIL;
	const char		*url, *uuid, *if_name;
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	zbx_vmware_pnic_t	nic_cmp;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (3 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	if_name = get_rparam(request, 2);

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (hv = hv_get(&service->data->hvs, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown hypervisor uuid."));
		goto unlock;
	}

	nic_cmp.name = (char *)if_name;

	if (FAIL == (i = zbx_vector_vmware_pnic_ptr_bsearch(&hv->pnics, &nic_cmp, zbx_vmware_pnic_compare)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown physical network interface name"));
		goto out;
	}

	SET_UI64_RESULT(result, hv->pnics.values[i]->speed);
	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(), ret: %s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_tags_get(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_service_t		*service;
	zbx_vmware_hv_t			*hv = NULL;
	int				ret = SYSINFO_RET_FAIL;
	const char			*url, *uuid;
	struct zbx_json			json_data;
	char				*error = NULL;

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

	if (NULL != service->data_tags.error)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, service->data_tags.error));
		goto unlock;
	}

	zbx_json_initarray(&json_data, ZBX_JSON_STAT_BUF_LEN);
	vmware_tags_uuid_json(&service->data_tags, hv->uuid, NULL, &json_data, &error);
	zbx_json_close(&json_data);

	if (NULL == error)
	{
		SET_TEXT_RESULT(result, zbx_strdup(NULL, json_data.buffer));
		ret = SYSINFO_RET_OK;
	}
	else
		SET_STR_RESULT(result, error);

	zbx_json_free(&json_data);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_datacenter_name(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*url, *uuid;
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	SET_STR_RESULT(result, zbx_strdup(NULL, hv->datacenter_name));

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_datastore_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*url, *hv_uuid;
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	struct zbx_json		json_data;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (2 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	hv_uuid = get_rparam(request, 1);

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (hv = hv_get(&service->data->hvs, hv_uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown hypervisor uuid."));
		goto unlock;
	}

	zbx_json_initarray(&json_data, ZBX_JSON_STAT_BUF_LEN);

	for (int i = 0; i < hv->dsnames.values_num; i++)
	{
		zbx_vmware_dsname_t	*dsname = hv->dsnames.values[i];
		zbx_vmware_datastore_t	*datastore;
		int			total = 0;

		if (NULL == (datastore = ds_get(&service->data->datastores, dsname->uuid)))
		{
			zbx_json_free(&json_data);
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown datastore uuid."));
			goto unlock;
		}

		for (int j = 0; j < dsname->hvdisks.values_num; j++)
			total += dsname->hvdisks.values[j].multipath_total;

		zbx_json_addobject(&json_data, NULL);
		zbx_json_addstring(&json_data, "{#DATASTORE}", dsname->name, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "{#DATASTORE.UUID}", dsname->uuid, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "{#DATASTORE.TYPE}", ZBX_NULL2EMPTY_STR(datastore->type),
				ZBX_JSON_TYPE_STRING);
		zbx_json_adduint64(&json_data, "{#MULTIPATH.COUNT}", (unsigned int)total);
		zbx_json_adduint64(&json_data, "{#MULTIPATH.PARTITION.COUNT}",
				(unsigned int)dsname->hvdisks.values_num);
		zbx_json_addarray(&json_data, "datastore_extent");

		for (int j = 0; j < datastore->diskextents.values_num; j++)
		{
			zbx_vmware_diskextent_t	*ext = datastore->diskextents.values[j];

			zbx_json_addobject(&json_data, NULL);
			zbx_json_adduint64(&json_data, "partitionid", ext->partitionid);
			zbx_json_addstring(&json_data, "instance", ext->diskname,
					ZBX_JSON_TYPE_STRING);
			zbx_json_close(&json_data);
		}

		zbx_json_close(&json_data);
		vmware_tags_uuid_json(&service->data_tags, dsname->uuid, "tags", &json_data, NULL);
		zbx_json_close(&json_data);
	}

	zbx_json_close(&json_data);

	SET_STR_RESULT(result, zbx_strdup(NULL, json_data.buffer));

	zbx_json_free(&json_data);

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

#define DATASTORE_METRIC_MODE_LATENCY		0
#define	DATASTORE_METRIC_MODE_MAX_LATENCY	1
#define DATASTORE_METRIC_MODE_RPS		2

static int	check_vcenter_hv_datastore_metrics(AGENT_REQUEST *request, const char *username, const char *password,
		int direction, AGENT_RESULT *result)
{
	const char		*url, *mode, *hv_uuid, *ds_uuid, *perfcounter;
	zbx_uint64_t		access_filter;
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	zbx_vmware_datastore_t	*datastore;
	int			i, metric_mode, ret = SYSINFO_RET_FAIL;
	zbx_str_uint64_pair_t	uuid_cmp = {.value = 0};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (3 > request->nparam || request->nparam > 4)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	hv_uuid = get_rparam(request, 1);
	ds_uuid = get_rparam(request, 2);
	mode = get_rparam(request, 3);

	if (NULL == mode || '\0' == *mode || (0 == strcmp(mode, "latency")))
	{
		metric_mode = DATASTORE_METRIC_MODE_LATENCY;
	}
	else if (0 == strcmp(mode, "rps"))
	{
		metric_mode = DATASTORE_METRIC_MODE_RPS;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (hv = hv_get(&service->data->hvs, hv_uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown hypervisor uuid."));
		goto unlock;
	}

	if (FAIL == (i = dsname_idx_get(&hv->dsnames, ds_uuid)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Datastore \"%s\" not found on this hypervisor.", ds_uuid));
		goto unlock;
	}

	if (NULL == (datastore = ds_get(&service->data->datastores, hv->dsnames.values[i]->uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown datastore uuid."));
		goto unlock;
	}

	uuid_cmp.name = hv->uuid;

	if (FAIL == (i = zbx_vector_str_uint64_pair_bsearch(&datastore->hv_uuids_access, uuid_cmp,
			zbx_str_uint64_pair_name_compare)))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Unknown hypervisor \"%s\" for datastore \"%s\".",
				hv->props[ZBX_VMWARE_HVPROP_NAME], datastore->name));
		goto unlock;
	}

	switch (direction)
	{
		case ZBX_DATASTORE_DIRECTION_READ:
			access_filter = ZBX_VMWARE_DS_READ_FILTER;

			switch (metric_mode)
			{
				case DATASTORE_METRIC_MODE_RPS:
					perfcounter = "datastore/numberReadAveraged[average]";
					break;
				default:
					perfcounter = "datastore/totalReadLatency[average]";
			}
			break;
		case ZBX_DATASTORE_DIRECTION_WRITE:
			access_filter = ZBX_VMWARE_DS_WRITE_FILTER;

			switch (metric_mode)
			{
				case DATASTORE_METRIC_MODE_RPS:
					perfcounter = "datastore/numberWriteAveraged[average]";
					break;
				default:
					perfcounter = "datastore/totalWriteLatency[average]";
			}
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			goto unlock;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s(): perfcounter:%s", __func__, perfcounter);

	if (access_filter != (datastore->hv_uuids_access.values[i].value & access_filter))
	{
		zbx_uint64_t	mi = datastore->hv_uuids_access.values[i].value;

		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Datastore is not available for hypervisor: %s",
				0 == (ZBX_VMWARE_DS_MOUNTED & mi) ? "unmounted" : (
				0 == (ZBX_VMWARE_DS_ACCESSIBLE & mi) ? "inaccessible" : (
				ZBX_VMWARE_DS_READ == (ZBX_VMWARE_DS_READWRITE & mi)? "readOnly" :
				"unknown"))));
		goto unlock;
	}

	ret = vmware_service_get_counter_value_by_path(service, ZBX_VMWARE_SOAP_HV, hv->id, perfcounter,
			datastore->uuid, 1, result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

static int	check_vcenter_datastore_metrics(AGENT_REQUEST *request, const char *username, const char *password,
		int direction, AGENT_RESULT *result)
{
	const char		*url, *mode, *ds_name, *perfcounter;
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	zbx_vmware_datastore_t	*datastore;
	int			i, metric_mode, ret = SYSINFO_RET_FAIL, unit, count = 0, ds_count = 0;
	zbx_uint64_t		access_filter, counterid, value = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (2 > request->nparam || request->nparam > 3)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	ds_name = get_rparam(request, 1);
	mode = get_rparam(request, 2);

	if (NULL == mode || '\0' == *mode || (0 == strcmp(mode, "latency")))
	{
		metric_mode = DATASTORE_METRIC_MODE_LATENCY;
	}
	else if (0 == strcmp(mode, "maxlatency"))
	{
		metric_mode = DATASTORE_METRIC_MODE_MAX_LATENCY;
	}
	else if (0 == strcmp(mode, "rps"))
	{
		metric_mode = DATASTORE_METRIC_MODE_RPS;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	/* allow passing ds uuid or name for backwards compatibility */
	if (NULL == (datastore = ds_get(&service->data->datastores, ds_name)))
	{
		zbx_vmware_datastore_t	ds_cmp = {.name = (char *)ds_name};

		if (FAIL == (i = zbx_vector_vmware_datastore_ptr_search(&service->data->datastores, &ds_cmp,
				zbx_vmware_ds_name_compare)))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown datastore name."));
			goto unlock;
		}

		datastore = service->data->datastores.values[i];
	}

	switch (direction)
	{
		case ZBX_DATASTORE_DIRECTION_READ:
			access_filter = ZBX_VMWARE_DS_READ_FILTER;

			switch (metric_mode)
			{
				case DATASTORE_METRIC_MODE_RPS:
					perfcounter = "datastore/numberReadAveraged[average]";
					break;
				default:
					perfcounter = "datastore/totalReadLatency[average]";
			}
			break;
		case ZBX_DATASTORE_DIRECTION_WRITE:
			access_filter = ZBX_VMWARE_DS_WRITE_FILTER;

			switch (metric_mode)
			{
				case DATASTORE_METRIC_MODE_RPS:
					perfcounter = "datastore/numberWriteAveraged[average]";
					break;
				default:
					perfcounter = "datastore/totalWriteLatency[average]";
			}
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			goto unlock;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s(): perfcounter:%s", __func__, perfcounter);

	if (FAIL == zbx_vmware_service_get_counterid(service, perfcounter, &counterid, &unit))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Performance counter is not available."));
		goto unlock;
	}

	for (i = 0; i < datastore->hv_uuids_access.values_num; i++)
	{
		if (access_filter != (datastore->hv_uuids_access.values[i].value & access_filter))
		{
			zbx_uint64_t	mi = datastore->hv_uuids_access.values[i].value;

			zabbix_log(LOG_LEVEL_DEBUG, "Datastore %s is not available for hypervisor %s: %s",
					datastore->name, datastore->hv_uuids_access.values[i].name,
					0 == (ZBX_VMWARE_DS_MOUNTED & mi) ? "unmounted" : (
					0 == (ZBX_VMWARE_DS_ACCESSIBLE & mi) ? "inaccessible" : (
					ZBX_VMWARE_DS_READ == (ZBX_VMWARE_DS_READWRITE & mi)? "readOnly" :
					"unknown")));
			continue;
		}

		if (NULL == (hv = hv_get(&service->data->hvs, datastore->hv_uuids_access.values[i].name)))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown hypervisor uuid."));
			goto unlock;
		}

		ds_count++;

		if (0 == strcmp(hv->props[ZBX_VMWARE_HVPROP_MAINTENANCE], "true"))
			continue;

		if (SYSINFO_RET_OK != vmware_service_get_counter_value_by_id(service, "HostSystem", hv->id,
				counterid, datastore->uuid, 1, unit, result))
		{
			char	*err, *msg = *ZBX_GET_MSG_RESULT(result);

			*msg = (char)tolower(*msg);
			err = zbx_dsprintf(NULL, "Counter %s for datastore %s is not available for hypervisor %s: %s",
					perfcounter, datastore->name,
					ZBX_NULL2EMPTY_STR(hv->props[ZBX_VMWARE_HVPROP_NAME]), msg);
			ZBX_UNSET_MSG_RESULT(result);
			SET_MSG_RESULT(result, err);
			goto unlock;
		}

		if (0 == ZBX_ISSET_VALUE(result))
			continue;

		if (DATASTORE_METRIC_MODE_MAX_LATENCY != metric_mode)
		{
			value += *ZBX_GET_UI64_RESULT(result);
			count++;
		}
		else if (value < *ZBX_GET_UI64_RESULT(result))
			value = *ZBX_GET_UI64_RESULT(result);

		ZBX_UNSET_UI64_RESULT(result);
	}

	if (0 == ds_count)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "No datastores available."));
		goto unlock;
	}

	if (DATASTORE_METRIC_MODE_MAX_LATENCY != metric_mode && 0 != count)
		value = value / count;

	SET_UI64_RESULT(result, value);
	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

#undef DATASTORE_METRIC_MODE_LATENCY
#undef DATASTORE_METRIC_MODE_MAX_LATENCY
#undef DATASTORE_METRIC_MODE_RPS

int	check_vcenter_hv_datastore_read(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return check_vcenter_hv_datastore_metrics(request, username, password, ZBX_DATASTORE_DIRECTION_READ, result);
}

int	check_vcenter_hv_datastore_write(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return check_vcenter_hv_datastore_metrics(request, username, password, ZBX_DATASTORE_DIRECTION_WRITE, result);
}

int	check_vcenter_datastore_read(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return check_vcenter_datastore_metrics(request, username, password, ZBX_DATASTORE_DIRECTION_READ, result);
}

int	check_vcenter_datastore_write(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return check_vcenter_datastore_metrics(request, username, password, ZBX_DATASTORE_DIRECTION_WRITE, result);
}

static int	check_vcenter_hv_datastore_size_vsphere(int mode, const zbx_vmware_datastore_t *datastore,
		AGENT_RESULT *result)
{
	switch (mode)
	{
		case ZBX_VMWARE_DATASTORE_SIZE_TOTAL:
			if (ZBX_MAX_UINT64 == datastore->capacity)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Datastore \"capacity\" is not available."));
				return SYSINFO_RET_FAIL;
			}
			SET_UI64_RESULT(result, datastore->capacity);
			break;
		case ZBX_VMWARE_DATASTORE_SIZE_FREE:
			if (ZBX_MAX_UINT64 == datastore->free_space)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Datastore \"free space\" is not available."));
				return SYSINFO_RET_FAIL;
			}
			SET_UI64_RESULT(result, datastore->free_space);
			break;
		case ZBX_VMWARE_DATASTORE_SIZE_UNCOMMITTED:
			if (ZBX_MAX_UINT64 == datastore->uncommitted)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Datastore \"uncommitted\" is not available."));
				return SYSINFO_RET_FAIL;
			}
			SET_UI64_RESULT(result, datastore->uncommitted);
			break;
		case ZBX_VMWARE_DATASTORE_SIZE_PFREE:
			if (ZBX_MAX_UINT64 == datastore->capacity)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Datastore \"capacity\" is not available."));
				return SYSINFO_RET_FAIL;
			}
			if (ZBX_MAX_UINT64 == datastore->free_space)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Datastore \"free space\" is not available."));
				return SYSINFO_RET_FAIL;
			}
			if (0 == datastore->capacity)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Datastore \"capacity\" is zero."));
				return SYSINFO_RET_FAIL;
			}
			SET_DBL_RESULT(result, (double)datastore->free_space / datastore->capacity * 100);
			break;
	}

	return SYSINFO_RET_OK;
}

static int	check_vcenter_ds_param(const char *param, int *mode)
{

	if (NULL == param || '\0' == *param || 0 == strcmp(param, "total"))
	{
		*mode = ZBX_VMWARE_DATASTORE_SIZE_TOTAL;
	}
	else if (0 == strcmp(param, "free"))
	{
		*mode = ZBX_VMWARE_DATASTORE_SIZE_FREE;
	}
	else if (0 == strcmp(param, "pfree"))
	{
		*mode = ZBX_VMWARE_DATASTORE_SIZE_PFREE;
	}
	else if (0 == strcmp(param, "uncommitted"))
	{
		*mode = ZBX_VMWARE_DATASTORE_SIZE_UNCOMMITTED;
	}
	else
		return FAIL;

	return SUCCEED;
}

static int	check_vcenter_ds_size(const char *url, const char *hv_uuid, const char *ds_uuid, const int mode,
		const char *username, const char *password, AGENT_RESULT *result)
{
	zbx_vmware_service_t	*service;
	int			i, ret = SYSINFO_RET_FAIL;
	zbx_vmware_datastore_t	*datastore;
	zbx_vmware_hv_t		*hv;
	zbx_uint64_t		disk_used, disk_provisioned, disk_capacity;
	unsigned int		flags;
	zbx_str_uint64_pair_t	uuid_cmp = {.name = (char *)hv_uuid, .value = 0};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL != hv_uuid)
	{
		if (NULL == (hv = hv_get(&service->data->hvs, hv_uuid)))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown hypervisor uuid."));
			goto unlock;
		}

		if (FAIL == (i = dsname_idx_get(&hv->dsnames, ds_uuid)))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Datastore \"%s\" not found on this hypervisor.",
					ds_uuid));
			goto unlock;
		}

		if (NULL == (datastore = ds_get(&service->data->datastores, hv->dsnames.values[i]->uuid)))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown datastore uuid."));
			goto unlock;
		}
	}
	/* allow passing ds uuid or name for backwards compatibility */
	else if (NULL == (datastore = ds_get(&service->data->datastores, ds_uuid)))
	{
		zbx_vmware_datastore_t	ds_cmp = {.name = (char *)ds_uuid};

		if (FAIL == (i = zbx_vector_vmware_datastore_ptr_search(&service->data->datastores, &ds_cmp,
				zbx_vmware_ds_name_compare)))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown datastore uuid."));
			goto unlock;
		}

		datastore = service->data->datastores.values[i];
	}

	if (NULL != hv_uuid &&
			FAIL == zbx_vector_str_uint64_pair_bsearch(&datastore->hv_uuids_access, uuid_cmp,
			zbx_str_uint64_pair_name_compare))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Hypervisor '%s' not found on this datastore.", hv_uuid));
		goto unlock;
	}

	if (ZBX_VMWARE_TYPE_VSPHERE == service->type)
	{
		ret = check_vcenter_hv_datastore_size_vsphere(mode, datastore, result);
		goto unlock;
	}

	switch (mode)
	{
		case ZBX_VMWARE_DATASTORE_SIZE_TOTAL:
			flags = ZBX_DATASTORE_COUNTER_CAPACITY;
			break;
		case ZBX_VMWARE_DATASTORE_SIZE_FREE:
			flags = ZBX_DATASTORE_COUNTER_CAPACITY | ZBX_DATASTORE_COUNTER_USED;
			break;
		case ZBX_VMWARE_DATASTORE_SIZE_PFREE:
			flags = ZBX_DATASTORE_COUNTER_CAPACITY | ZBX_DATASTORE_COUNTER_USED;
			break;
		case ZBX_VMWARE_DATASTORE_SIZE_UNCOMMITTED:
			flags = ZBX_DATASTORE_COUNTER_PROVISIONED | ZBX_DATASTORE_COUNTER_USED;
			break;
	}

	if (0 != (flags & ZBX_DATASTORE_COUNTER_PROVISIONED))
	{
		ret = vmware_service_get_counter_value_by_path(service, "Datastore", datastore->id,
				"disk/provisioned[latest]", ZBX_DATASTORE_TOTAL, ZBX_KIBIBYTE, result);

		if (SYSINFO_RET_OK != ret || NULL == ZBX_GET_UI64_RESULT(result))
			goto unlock;

		disk_provisioned = *ZBX_GET_UI64_RESULT(result);
		ZBX_UNSET_UI64_RESULT(result);
	}

	if (0 != (flags & ZBX_DATASTORE_COUNTER_USED))
	{
		ret = vmware_service_get_counter_value_by_path(service, "Datastore", datastore->id,
				"disk/used[latest]", ZBX_DATASTORE_TOTAL, ZBX_KIBIBYTE, result);

		if (SYSINFO_RET_OK != ret || NULL == ZBX_GET_UI64_RESULT(result))
			goto unlock;

		disk_used = *ZBX_GET_UI64_RESULT(result);
		ZBX_UNSET_UI64_RESULT(result);
	}

	if (0 != (flags & ZBX_DATASTORE_COUNTER_CAPACITY))
	{
		ret = vmware_service_get_counter_value_by_path(service, "Datastore", datastore->id,
				"disk/capacity[latest]", ZBX_DATASTORE_TOTAL, ZBX_KIBIBYTE, result);

		if (SYSINFO_RET_OK != ret || NULL == ZBX_GET_UI64_RESULT(result))
			goto unlock;

		disk_capacity = *ZBX_GET_UI64_RESULT(result);
		ZBX_UNSET_UI64_RESULT(result);
	}

	switch (mode)
	{
		case ZBX_VMWARE_DATASTORE_SIZE_TOTAL:
			SET_UI64_RESULT(result, disk_capacity);
			break;
		case ZBX_VMWARE_DATASTORE_SIZE_FREE:
			SET_UI64_RESULT(result, disk_capacity - disk_used);
			break;
		case ZBX_VMWARE_DATASTORE_SIZE_UNCOMMITTED:
			SET_UI64_RESULT(result, disk_provisioned - disk_used);
			break;
		case ZBX_VMWARE_DATASTORE_SIZE_PFREE:
			SET_DBL_RESULT(result, 0 != disk_capacity ?
					(double) (disk_capacity - disk_used) / disk_capacity * 100 : 0);
			break;
	}

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_datastore_size(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*url, *hv_uuid, *ds_uuid, *param;
	int		ret = SYSINFO_RET_FAIL, mode;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (3 > request->nparam || request->nparam > 4)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	hv_uuid = get_rparam(request, 1);
	ds_uuid = get_rparam(request, 2);
	param = get_rparam(request, 3);

	if (SUCCEED == check_vcenter_ds_param(param, &mode))
		ret = check_vcenter_ds_size(url, hv_uuid, ds_uuid, mode, username, password, result);
	else
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_cl_perfcounter(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	char			*url, *path, *clusterid;
	const char 		*instance;
	zbx_vmware_service_t	*service;
	zbx_vmware_cluster_t	*cluster;
	zbx_uint64_t		counterid;
	int			unit, ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (3 > request->nparam || request->nparam > 4)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	clusterid = get_rparam(request, 1);
	path = get_rparam(request, 2);
	instance = get_rparam(request, 3);

	if (NULL == instance)
		instance = "";

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (FAIL == zbx_vmware_service_get_counterid(service, path, &counterid, &unit))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Performance counter is not available."));
		goto unlock;
	}

	if (NULL == (cluster = cluster_get(&service->data->clusters, clusterid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid cluster id."));
		goto unlock;
	}

	/* FAIL is returned if counter already exists */
	if (SUCCEED == zbx_vmware_service_add_perf_counter(service, ZBX_VMWARE_SOAP_CLUSTER, cluster->id,
			counterid, ZBX_VMWARE_PERF_QUERY_ALL))
	{
		ret = SYSINFO_RET_OK;
		goto unlock;
	}

	/* The performance counter is already being monitored, try to get the results from statistics. */
	ret = vmware_service_get_counter_value_by_id(service, ZBX_VMWARE_SOAP_CLUSTER, cluster->id, counterid,
			instance, 1, unit, result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_perfcounter(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*instance, *url, *uuid, *path;
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	zbx_uint64_t		counterid;
	int			unit, ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	if (FAIL == zbx_vmware_service_get_counterid(service, path, &counterid, &unit))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Performance counter is not available."));
		goto unlock;
	}

	/* FAIL is returned if counter already exists */
	if (SUCCEED == zbx_vmware_service_add_perf_counter(service, ZBX_VMWARE_SOAP_HV, hv->id, counterid,
			ZBX_VMWARE_PERF_QUERY_ALL))
	{
		ret = SYSINFO_RET_OK;
		goto unlock;
	}

	/* The performance counter is already being monitored, try to get the results from statistics. */
	ret = vmware_service_get_counter_value_by_id(service, ZBX_VMWARE_SOAP_HV, hv->id, counterid, instance, 1, unit,
			result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_datastore_list(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*url, *hv_uuid;
	char			*ds_list = NULL;
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (2 != request->nparam )
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	hv_uuid = get_rparam(request, 1);
	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (hv = hv_get(&service->data->hvs, hv_uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown hypervisor uuid."));
		goto unlock;
	}

	for (int i = 0; i < hv->dsnames.values_num; i++)
	{
		zbx_vmware_dsname_t	*dsname = hv->dsnames.values[i];

		ds_list = zbx_strdcatf(ds_list, "%s\n", dsname->name);
	}

	if (NULL != ds_list)
		ds_list[strlen(ds_list)-1] = '\0';
	else
		ds_list = zbx_strdup(NULL, "");

	SET_TEXT_RESULT(result, ds_list);

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_hv_datastore_multipath(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*url, *hv_uuid, *ds_uuid, *partition;
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	zbx_vmware_dsname_t	*dsname;
	int			ret = SYSINFO_RET_FAIL, i, multipath_count = 0;
	zbx_uint64_t		partitionid = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (2 > request->nparam || request->nparam > 4)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	hv_uuid = get_rparam(request, 1);
	ds_uuid = get_rparam(request, 2);
	partition = get_rparam(request, 3);

	if ('\0' == *hv_uuid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	if (NULL != partition && '\0' != *partition)
	{
		if (NULL == ds_uuid || '\0' == *ds_uuid)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
			goto out;
		}

		partitionid = (unsigned int) atoi(partition);
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (hv = hv_get(&service->data->hvs, hv_uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown hypervisor uuid."));
		goto unlock;
	}

	if (NULL != ds_uuid && '\0' != *ds_uuid)
	{
		zbx_vmware_hvdisk_t	hvdisk_cmp;

		if (FAIL == (i = dsname_idx_get(&hv->dsnames, ds_uuid)))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Datastore \"%s\" not found on this hypervisor.",
					ds_uuid));
			goto unlock;
		}

		dsname = hv->dsnames.values[i];

		if (NULL != partition)
		{
			hvdisk_cmp.partitionid = partitionid;

			if (FAIL == (i = zbx_vector_vmware_hvdisk_bsearch(&dsname->hvdisks, hvdisk_cmp,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Unknown partition id:" ZBX_FS_UI64,
						partitionid));
				goto unlock;
			}

			multipath_count = dsname->hvdisks.values[i].multipath_active;
		}
		else
		{
			for (int j = 0; j < dsname->hvdisks.values_num; j++)
				multipath_count += dsname->hvdisks.values[j].multipath_active;
		}
	}
	else
	{
		for (i = 0; i < hv->dsnames.values_num; i++)
		{
			dsname = hv->dsnames.values[i];

			for (int j = 0; j < dsname->hvdisks.values_num; j++)
				multipath_count += dsname->hvdisks.values[j].multipath_active;
		}
	}

	SET_UI64_RESULT(result, (unsigned int)multipath_count);
	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_datastore_hv_list(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*url, *ds_name, *hv_name;
	char			*hv_list = NULL;
	zbx_vmware_service_t	*service;
	int			i, ret = SYSINFO_RET_FAIL;
	zbx_vmware_datastore_t	*datastore = NULL;
	zbx_vmware_hv_t		*hv;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (2 != request->nparam )
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	ds_name = get_rparam(request, 1);
	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (datastore = ds_get(&service->data->datastores, ds_name)))
	{
		zbx_vmware_datastore_t	ds_cmp =  {.name = (char *)ds_name};

		if (FAIL == (i = zbx_vector_vmware_datastore_ptr_search(&service->data->datastores, &ds_cmp,
				zbx_vmware_ds_name_compare)))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown datastore name."));
			goto unlock;
		}

		datastore = service->data->datastores.values[i];
	}

	for (i = 0; i < datastore->hv_uuids_access.values_num; i++)
	{
		if (NULL == (hv = hv_get(&service->data->hvs, datastore->hv_uuids_access.values[i].name)))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown hypervisor uuid."));
			zbx_free(hv_list);
			goto unlock;
		}

		if (NULL == (hv_name = hv->props[ZBX_VMWARE_HVPROP_NAME]))
			hv_name = datastore->hv_uuids_access.values[i].name;

		hv_list = zbx_strdcatf(hv_list, "%s\n", hv_name);
	}

	if (NULL != hv_list)
		hv_list[strlen(hv_list)-1] = '\0';
	else
		hv_list = zbx_strdup(NULL, "");

	SET_TEXT_RESULT(result, hv_list);

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_datastore_perfcounter(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*instance, *url, *uuid, *path;
	zbx_vmware_service_t	*service;
	zbx_vmware_datastore_t	*ds;
	zbx_uint64_t		counterid;
	int			unit, ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	if (NULL == (ds = ds_get(&service->data->datastores, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown datastore uuid."));
		goto unlock;
	}

	if (FAIL == zbx_vmware_service_get_counterid(service, path, &counterid, &unit))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Performance counter is not available."));
		goto unlock;
	}

	/* FAIL is returned if counter already exists */
	if (SUCCEED == zbx_vmware_service_add_perf_counter(service, ZBX_VMWARE_SOAP_DS, ds->id, counterid,
			ZBX_VMWARE_PERF_QUERY_ALL))
	{
		ret = SYSINFO_RET_OK;
		goto unlock;
	}

	/* The performance counter is already being monitored, try to get the results from statistics. */
	ret = vmware_service_get_counter_value_by_id(service, ZBX_VMWARE_SOAP_DS, ds->id, counterid, instance, 1, unit,
			result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_datastore_property(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char			*url, *uuid, *key, *type = ZBX_VMWARE_SOAP_DS, *mode = "";
	int				ret = SYSINFO_RET_FAIL;
	char				*key_esc = NULL;
	zbx_vmware_service_t		*service;
	zbx_vmware_datastore_t		*ds;
	zbx_vmware_cust_query_t		*custom_query;
	zbx_vmware_custom_query_type_t	query_type = VMWARE_OBJECT_PROPERTY;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (3 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	key = get_rparam(request, 2);

	if (NULL == key || '\0' == *key)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		goto out;
	}

	key_esc = zbx_xml_escape_dyn(key);

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (ds = ds_get(&service->data->datastores, uuid)))
	{
		int			i;
		zbx_vmware_datastore_t	ds_cmp = {.name = (char *)uuid};

		if (FAIL == (i = zbx_vector_vmware_datastore_ptr_search(&service->data->datastores, &ds_cmp,
				zbx_vmware_ds_name_compare)))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown datastore name."));
			goto unlock;
		}

		ds = service->data->datastores.values[i];
	}

	/* FAIL is returned if custom query exists */
	if (NULL == (custom_query = zbx_vmware_service_get_cust_query(service, type, ds->id, key_esc, query_type, mode))
			&& NULL != (custom_query = zbx_vmware_service_add_cust_query(service, type, ds->id, key_esc,
			query_type, mode, NULL)))
	{
		ret = SYSINFO_RET_OK;
		goto unlock;
	}
	else if (NULL == custom_query)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown vmware property query."));
		goto unlock;
	}

	ret = custquery_read_result(custom_query, result);
unlock:
	zbx_vmware_unlock();
out:
	zbx_str_free(key_esc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_datastore_size(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*url, *ds_uuid, *param;
	int		ret = SYSINFO_RET_FAIL, mode;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (2 > request->nparam || request->nparam > 3)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	ds_uuid = get_rparam(request, 1);
	param = get_rparam(request, 2);

	if (SUCCEED == check_vcenter_ds_param(param, &mode))
		ret = check_vcenter_ds_size(url, NULL, ds_uuid, mode, username, password, result);
	else
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_datastore_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*url;
	zbx_vmware_service_t	*service;
	struct zbx_json		json_data;
	int			i, j, ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (1 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	zbx_json_initarray(&json_data, ZBX_JSON_STAT_BUF_LEN);

	for (i = 0; i < service->data->datastores.values_num; i++)
	{
		zbx_vmware_datastore_t	*datastore = service->data->datastores.values[i];

		zbx_json_addobject(&json_data, NULL);
		zbx_json_addstring(&json_data, "{#DATASTORE}", datastore->name, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "{#DATASTORE.UUID}", datastore->uuid, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "{#DATASTORE.TYPE}", ZBX_NULL2EMPTY_STR(datastore->type),
				ZBX_JSON_TYPE_STRING);
		zbx_json_addarray(&json_data, "datastore_extent");

		for (j = 0; j < datastore->diskextents.values_num; j++)
		{
			zbx_vmware_diskextent_t	*ext = datastore->diskextents.values[j];

			zbx_json_addobject(&json_data, NULL);
			zbx_json_adduint64(&json_data, "partitionid", ext->partitionid);
			zbx_json_addstring(&json_data, "instance", ext->diskname,
					ZBX_JSON_TYPE_STRING);
			zbx_json_close(&json_data);
		}

		zbx_json_close(&json_data);
		vmware_tags_uuid_json(&service->data_tags, datastore->uuid, "tags", &json_data, NULL);
		zbx_json_close(&json_data);
	}

	zbx_json_close(&json_data);

	SET_STR_RESULT(result, zbx_strdup(NULL, json_data.buffer));

	zbx_json_free(&json_data);

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_datastore_tags_get(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_service_t		*service;
	zbx_vmware_datastore_t		*ds = NULL;
	int				ret = SYSINFO_RET_FAIL;
	const char			*url, *uuid;
	struct zbx_json			json_data;
	char				*error = NULL;

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

	if (NULL == (ds = ds_get(&service->data->datastores, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown datastore uuid."));
		goto unlock;
	}

	if (NULL != service->data_tags.error)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, service->data_tags.error));
		goto unlock;
	}

	zbx_json_initarray(&json_data, ZBX_JSON_STAT_BUF_LEN);
	vmware_tags_uuid_json(&service->data_tags, ds->uuid, NULL, &json_data, &error);
	zbx_json_close(&json_data);

	if (NULL == error)
	{
		SET_TEXT_RESULT(result, zbx_strdup(NULL, json_data.buffer));
		ret = SYSINFO_RET_OK;
	}
	else
		SET_STR_RESULT(result, error);

	zbx_json_free(&json_data);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_dvswitch_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*url;
	int			ret = SYSINFO_RET_FAIL;
	zbx_vmware_service_t	*service;
	struct zbx_json		json_data;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (1 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	zbx_json_initarray(&json_data, ZBX_JSON_STAT_BUF_LEN);

	for (int i = 0; i < service->data->dvswitches.values_num; i++)
	{
		zbx_vmware_dvswitch_t	*dvswitch = (zbx_vmware_dvswitch_t *)service->data->dvswitches.values[i];

		zbx_json_addobject(&json_data, NULL);
		zbx_json_addstring(&json_data, "{#DVSWITCH.UUID}", dvswitch->uuid, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "{#DVSWITCH.NAME}", dvswitch->name, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&json_data);
	}

	zbx_json_close(&json_data);

	SET_STR_RESULT(result, zbx_strdup(NULL, json_data.buffer));

	zbx_json_free(&json_data);

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

static int	dvs_param_validate(zbx_vector_custquery_param_t *query_params, unsigned int vc_version)
{
	for (int i = 0; i < query_params->values_num; i++)
	{
		zbx_vmware_custquery_param_t	*p = &query_params->values[i];

		if (0 == strcmp("active", p->name) || 0 == strcmp("connected", p->name) ||
				0 == strcmp("inside", p->name) || 0 == strcmp("nsxPort", p->name) ||
				0 == strcmp("uplinkPort", p->name))
		{
			if (0 != strcmp("true", p->value) && 0 != strcmp("false", p->value))
				return FAIL;
		}
		else if (0 != strcmp("host", p->name) && 0 != strcmp("portgroupKey", p->name) &&
				0 != strcmp("portKey", p->name))
		{
			return FAIL;
		}

		if (0 == strcmp("host", p->name) && vc_version < 65)
			return FAIL;

		if (0 == strcmp("nsxPort", p->name) && vc_version < 70)
			return FAIL;
	}

	return SUCCEED;
}

static int	custquery_param_create(const char *key, zbx_vector_custquery_param_t *query_params)
{
	char				*left, *right, *src;
	zbx_vmware_custquery_param_t	param = {NULL, NULL};
	int				ret = SUCCEED;

	if ('\0' == *key)
		return ret;

	src = zbx_strdup(NULL, key);

	while (1)
	{
		zbx_strsplit_first(src, ',', &left, &right);

		if (NULL == left || '\0' == *left)
		{
			ret = FAIL;
			break;
		}

		zbx_strsplit_first(left, ':', &param.name, &param.value);

		if (NULL == param.name || '\0' == *param.name || NULL == param.value)
		{
			ret = FAIL;
			break;
		}

		zbx_vector_custquery_param_append(query_params, param);
		param.name = NULL;
		param.value = NULL;

		if (NULL == right || '\0' == *right)
			break;

		zbx_free(src);
		src = right;
		right = NULL;
		zbx_free(left);
	}

	zbx_free(param.name);
	zbx_free(param.value);
	zbx_free(left);
	zbx_free(right);
	zbx_free(src);

	return ret;
}

int	check_vcenter_dvswitch_fetchports_get(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char			*mode, *url, *uuid, *key, *type = ZBX_VMWARE_SOAP_DVS;
	int				ret = SYSINFO_RET_FAIL;
	char				*key_esc = NULL;
	zbx_vmware_service_t		*service;
	zbx_vmware_dvswitch_t		*dvs;
	zbx_vmware_cust_query_t		*custom_query;
	zbx_vector_custquery_param_t	query_params;
	zbx_vmware_custom_query_type_t	query_type = VMWARE_DVSWITCH_FETCH_DV_PORTS;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_custquery_param_create(&query_params);

	if (2 > request->nparam || request->nparam > 4)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	key = get_rparam(request, 2);
	mode = get_rparam(request, 3);

	if (NULL == mode)
	{
		mode = "state";
	}
	else if (0 != strcmp(mode, "state") && 0 != strcmp(mode, "full"))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
		goto out;
	}

	if (NULL == key)
		key = "";

	key_esc = zbx_xml_escape_dyn(key);

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (dvs = dvs_get(&service->data->dvswitches, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown DVSwitch uuid."));
		goto unlock;
	}

	if (NULL == (custom_query =
			zbx_vmware_service_get_cust_query(service, type, dvs->id, key_esc, query_type, mode))
			&& (SUCCEED != custquery_param_create(key_esc, &query_params)
			|| SUCCEED != dvs_param_validate(&query_params,
			service->major_version * 10 + service->minor_version)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL,
				"Unknown format of vmware DistributedVirtualSwitchPortCriteria."));
		goto unlock;
	}

	/* FAIL is returned if custom query exists */
	if (NULL == custom_query && NULL != (custom_query = zbx_vmware_service_add_cust_query(service, type, dvs->id,
			key_esc, query_type, mode, &query_params)))
	{
		ret = SYSINFO_RET_OK;
		goto unlock;
	}
	else if (NULL == custom_query)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown DVSwitch query."));
		goto unlock;
	}

	if (0 != (custom_query->state & ZBX_VMWARE_CQ_ERROR))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Custom query error: %s", custom_query->error));
		goto unlock;
	}

	if (0 != (custom_query->state & ZBX_VMWARE_CQ_READY))
		SET_STR_RESULT(result, zbx_strdup(NULL, custom_query->value));

	if (0 != (custom_query->state & ZBX_VMWARE_CQ_PAUSED))
		custom_query->state &= ~(unsigned char)ZBX_VMWARE_CQ_PAUSED;

	custom_query->last_pooled = time(NULL);
	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zbx_str_free(key_esc);
	zbx_vector_custquery_param_clear_ext(&query_params, zbx_vmware_cq_param_free);
	zbx_vector_custquery_param_destroy(&query_params);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_attribute(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_service_t		*service;
	zbx_vmware_vm_t			*vm;
	zbx_vmware_custom_attr_t	custom_attr;
	const char			*url, *vm_uuid, *attr_name;
	int				index, ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (3 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	vm_uuid = get_rparam(request, 1);
	attr_name = get_rparam(request, 2);

	if ('\0' == *vm_uuid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (vm = service_vm_get(service, vm_uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown virtual machine uuid."));
		goto unlock;
	}

	custom_attr.name = (char *)attr_name;

	if (FAIL == (index = zbx_vector_vmware_custom_attr_ptr_bsearch(&vm->custom_attrs, &custom_attr,
			vmware_custom_attr_compare_name)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Custom attribute is not available."));
		goto unlock;
	}

	SET_STR_RESULT(result, zbx_strdup(NULL, vm->custom_attrs.values[index]->value));
	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_cpu_num(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_CPU_NUM, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_consolidationneeded(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_CONSOLIDATION_NEEDED, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_cluster_name(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*url, *uuid;
	zbx_vmware_service_t	*service;
	zbx_vmware_cluster_t	*cluster = NULL;
	int			ret = SYSINFO_RET_FAIL;
	zbx_vmware_hv_t		*hv;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_cpu_ready(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_service_t	*service;
	int			ret = SYSINFO_RET_FAIL;
	const char		*url, *uuid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_cpu_usage(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_CPU_USAGE, result);

	if (SYSINFO_RET_OK == ret && NULL != ZBX_GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * 1000000;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_datacenter_name(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	const char		*url, *uuid;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	SET_STR_RESULT(result, zbx_strdup(NULL, hv->datacenter_name));
	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	struct zbx_json		json_data;
	const char		*url, *vm_name, *hv_name, *hv_uuid;
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	zbx_vmware_vm_t		*vm;
	zbx_hashset_iter_t	iter;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (1 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	zbx_json_initarray(&json_data, ZBX_JSON_STAT_BUF_LEN);

	zbx_hashset_iter_reset(&service->data->hvs, &iter);
	while (NULL != (hv = (zbx_vmware_hv_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_vmware_cluster_t	*cluster = NULL;

		if (NULL != hv->clusterid)
			cluster = cluster_get(&service->data->clusters, hv->clusterid);

		for (int i = 0; i < hv->vms.values_num; i++)
		{
			zbx_vmware_datastore_t	*datastore = NULL;

			vm = (zbx_vmware_vm_t *)hv->vms.values[i];

			if (NULL == (vm_name = vm->props[ZBX_VMWARE_VMPROP_NAME]))
				continue;

			if (NULL == (hv_name = hv->props[ZBX_VMWARE_HVPROP_NAME]))
				continue;

			if (NULL == (hv_uuid = hv->props[ZBX_VMWARE_HVPROP_HW_UUID]))
				continue;

			for (int j = 0; NULL != vm->props[ZBX_VMWARE_VMPROP_DATASTOREID] &&
					j < service->data->datastores.values_num; j++)
			{
				if (0 != strcmp(vm->props[ZBX_VMWARE_VMPROP_DATASTOREID],
						service->data->datastores.values[j]->id))
				{
					continue;
				}

				datastore = service->data->datastores.values[j];
				break;
			}

			if (NULL == datastore)
			{
				zabbix_log(LOG_LEVEL_WARNING, "%s() Unknown datastore id:%s", __func__,
						ZBX_NULL2EMPTY_STR(vm->props[ZBX_VMWARE_VMPROP_DATASTOREID]));
				continue;
			}

			zbx_json_addobject(&json_data, NULL);
			zbx_json_addstring(&json_data, "{#VM.UUID}", vm->uuid, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json_data, "{#VM.ID}", vm->id, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json_data, "{#VM.NAME}", vm_name, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json_data, "{#HV.NAME}", hv_name, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json_data, "{#HV.UUID}", hv_uuid, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json_data, "{#HV.ID}", hv->id, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json_data, "{#DATACENTER.NAME}", hv->datacenter_name, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json_data, "{#CLUSTER.NAME}",
					NULL != cluster ? cluster->name : "", ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json_data, "{#VM.IP}",
					ZBX_NULL2EMPTY_STR(vm->props[ZBX_VMWARE_VMPROP_IPADDRESS]),
					ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json_data, "{#VM.DNS}",
					ZBX_NULL2EMPTY_STR(vm->props[ZBX_VMWARE_VMPROP_GUESTHOSTNAME]),
					ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json_data, "{#VM.GUESTFAMILY}",
					ZBX_NULL2EMPTY_STR(vm->props[ZBX_VMWARE_VMPROP_GUESTFAMILY]),
					ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json_data, "{#VM.GUESTFULLNAME}",
					ZBX_NULL2EMPTY_STR(vm->props[ZBX_VMWARE_VMPROP_GUESTFULLNAME]),
					ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json_data, "{#VM.FOLDER}",
					ZBX_NULL2EMPTY_STR(vm->props[ZBX_VMWARE_VMPROP_FOLDER]), ZBX_JSON_TYPE_STRING);
			zbx_json_adduint64(&json_data, "{#VM.SNAPSHOT.COUNT}", vm->snapshot_count);
			zbx_json_addstring(&json_data, "{#VM.TOOLS.STATUS}",
					ZBX_NULL2EMPTY_STR(vm->props[ZBX_VMWARE_VMPROP_TOOLS_RUNNING_STATUS]),
					ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json_data, "{#VM.POWERSTATE}",
					ZBX_NULL2EMPTY_STR(vm->props[ZBX_VMWARE_VMPROP_POWER_STATE]),
					ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json_data, "{#DATASTORE.NAME}", datastore->name, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json_data, "{#DATASTORE.UUID}", datastore->uuid, ZBX_JSON_TYPE_STRING);

			zbx_json_addstring(&json_data, "{#VM.RPOOL.ID}",
					ZBX_NULL2EMPTY_STR(vm->props[ZBX_VMWARE_VMPROP_RESOURCEPOOL]),
					ZBX_JSON_TYPE_STRING);

			if (NULL != vm->props[ZBX_VMWARE_VMPROP_RESOURCEPOOL])
			{
				zbx_vmware_resourcepool_t	rpool_cmp;
				int				idx;

				rpool_cmp.id = vm->props[ZBX_VMWARE_VMPROP_RESOURCEPOOL];

				if (FAIL != (idx = zbx_vector_vmware_resourcepool_ptr_bsearch(
						&service->data->resourcepools, &rpool_cmp,
						ZBX_DEFAULT_STR_PTR_COMPARE_FUNC)))
				{
					zbx_json_addstring(&json_data, "{#VM.RPOOL.PATH}", ZBX_NULL2EMPTY_STR(
							service->data->resourcepools.values[idx]->path),
							ZBX_JSON_TYPE_STRING);
				}
				else
					zbx_json_addstring(&json_data, "{#VM.RPOOL.PATH}", "", ZBX_JSON_TYPE_STRING);
			}
			else
				zbx_json_addstring(&json_data, "{#VM.RPOOL.PATH}", "", ZBX_JSON_TYPE_STRING);

			zbx_json_addarray(&json_data, "vm_customattribute");

			for (int j = 0; j < vm->custom_attrs.values_num; j++)
			{
				zbx_json_addobject(&json_data, NULL);
				zbx_json_addstring(&json_data, "name",
						vm->custom_attrs.values[j]->name, ZBX_JSON_TYPE_STRING);
				zbx_json_addstring(&json_data, "value",
						vm->custom_attrs.values[j]->value, ZBX_JSON_TYPE_STRING);
				zbx_json_close(&json_data);
			}

			zbx_json_close(&json_data);

			vmware_tags_uuid_json(&service->data_tags, vm->uuid, "tags", &json_data, NULL);

			zbx_json_addarray(&json_data, "net_if");

			for (int j = 0; j < vm->devs.values_num; j++)
			{
				zbx_vmware_dev_t	*dev;

				dev = (zbx_vmware_dev_t *)vm->devs.values[j];

				if (ZBX_VMWARE_DEV_TYPE_NIC != dev->type)
					continue;

				zbx_json_addobject(&json_data, NULL);
				zbx_json_addstring(&json_data, "ifname", ZBX_NULL2EMPTY_STR(dev->instance),
						ZBX_JSON_TYPE_STRING);
				zbx_json_addstring(&json_data, "ifdesc", ZBX_NULL2EMPTY_STR(dev->label),
						ZBX_JSON_TYPE_STRING);
				zbx_json_addstring(&json_data, "ifmac", ZBX_NULL2EMPTY_STR(
						dev->props[ZBX_VMWARE_DEV_PROPS_IFMAC]), ZBX_JSON_TYPE_STRING);
				zbx_json_addstring(&json_data, "ifconnected", ZBX_NULL2EMPTY_STR(
						dev->props[ZBX_VMWARE_DEV_PROPS_IFCONNECTED]), ZBX_JSON_TYPE_INT);
				zbx_json_addstring(&json_data, "iftype", ZBX_NULL2EMPTY_STR(
						dev->props[ZBX_VMWARE_DEV_PROPS_IFTYPE]), ZBX_JSON_TYPE_STRING);
				zbx_json_addstring(&json_data, "ifbackingdevice", ZBX_NULL2EMPTY_STR(
						dev->props[ZBX_VMWARE_DEV_PROPS_IFBACKINGDEVICE]),
						ZBX_JSON_TYPE_STRING);
				zbx_json_addstring(&json_data, "ifdvswitch_uuid", ZBX_NULL2EMPTY_STR(
						dev->props[ZBX_VMWARE_DEV_PROPS_IFDVSWITCH_UUID]),
						ZBX_JSON_TYPE_STRING);
				zbx_json_addstring(&json_data, "ifdvswitch_portgroup", ZBX_NULL2EMPTY_STR(
						dev->props[ZBX_VMWARE_DEV_PROPS_IFDVSWITCH_PORTGROUP]),
						ZBX_JSON_TYPE_STRING);
				zbx_json_addstring(&json_data, "ifdvswitch_port", ZBX_NULL2EMPTY_STR(
						dev->props[ZBX_VMWARE_DEV_PROPS_IFDVSWITCH_PORT]),
						ZBX_JSON_TYPE_STRING);
				zbx_json_addraw(&json_data, "ifip", NULL == dev->props[ZBX_VMWARE_DEV_PROPS_IFIPS] ?
						"[]" : dev->props[ZBX_VMWARE_DEV_PROPS_IFIPS]);
				zbx_json_close(&json_data);
			}

			zbx_json_close(&json_data);
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
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_hv_maintenance(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	const char		*url, *uuid, *value;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	if (NULL == (value = hv->props[ZBX_VMWARE_HVPROP_MAINTENANCE]))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "No hypervisor value found."));
		goto unlock;
	}

	if (0 == strcmp(value, "false"))
		SET_UI64_RESULT(result, 0);
	else
		SET_UI64_RESULT(result, 1);

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_hv_name(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_service_t	*service;
	zbx_vmware_hv_t		*hv;
	const char		*url, *uuid, *name;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_memory_size(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_MEMORY_SIZE, result);

	if (SYSINFO_RET_OK == ret && NULL != ZBX_GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_memory_size_ballooned(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_MEMORY_SIZE_BALLOONED, result);

	if (SYSINFO_RET_OK == ret && NULL != ZBX_GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_memory_size_compressed(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_MEMORY_SIZE_COMPRESSED, result);

	if (SYSINFO_RET_OK == ret && NULL != ZBX_GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_KIBIBYTE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_memory_size_swapped(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_MEMORY_SIZE_SWAPPED, result);

	if (SYSINFO_RET_OK == ret && NULL != ZBX_GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_memory_size_usage_guest(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_MEMORY_SIZE_USAGE_GUEST, result);

	if (SYSINFO_RET_OK == ret && NULL != ZBX_GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_memory_size_usage_host(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_MEMORY_SIZE_USAGE_HOST, result);

	if (SYSINFO_RET_OK == ret && NULL != ZBX_GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_memory_size_private(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_MEMORY_SIZE_PRIVATE, result);

	if (SYSINFO_RET_OK == ret && NULL != ZBX_GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_memory_size_shared(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_MEMORY_SIZE_SHARED, result);

	if (SYSINFO_RET_OK == ret && NULL != ZBX_GET_UI64_RESULT(result))
		result->ui64 = result->ui64 * ZBX_MEBIBYTE;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_property(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char			*url, *uuid, *key, *type = ZBX_VMWARE_SOAP_VM, *mode = "";
	int				ret = SYSINFO_RET_FAIL;
	char				*key_esc = NULL;
	zbx_vmware_service_t		*service;
	zbx_vmware_vm_t			*vm;
	zbx_vmware_cust_query_t		*custom_query;
	zbx_vmware_custom_query_type_t	query_type = VMWARE_OBJECT_PROPERTY;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (3 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	key = get_rparam(request, 2);

	if (NULL == key || '\0' == *key)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		goto out;
	}

	key_esc = zbx_xml_escape_dyn(key);

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (NULL == (vm = service_vm_get(service, uuid)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown virtual machine uuid."));
		goto unlock;
	}

	/* FAIL is returned if custom query exists */
	if (NULL == (custom_query = zbx_vmware_service_get_cust_query(service, type, vm->id, key_esc, query_type, mode))
			&& NULL != (custom_query = zbx_vmware_service_add_cust_query(service, type, vm->id, key_esc,
			query_type, mode, NULL)))
	{
		ret = SYSINFO_RET_OK;
		goto unlock;
	}
	else if (NULL == custom_query)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown vmware property query."));
		goto unlock;
	}

	ret = custquery_read_result(custom_query, result);
unlock:
	zbx_vmware_unlock();
out:
	zbx_str_free(key_esc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_powerstate(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_POWER_STATE, result);

	if (SYSINFO_RET_OK == ret)
		ret = vmware_set_powerstate_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_snapshot_get(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_SNAPSHOT, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

typedef void	(*vmpropfunc_t)(struct zbx_json *j, zbx_vmware_dev_t *dev);

static void	check_vcenter_vm_discovery_nic_props_cb(struct zbx_json *j, zbx_vmware_dev_t *dev)
{
	zbx_json_addstring(j, "{#IFNAME}", ZBX_NULL2EMPTY_STR(dev->instance), ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(j, "{#IFDESC}", ZBX_NULL2EMPTY_STR(dev->label), ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(j, "{#IFMAC}", ZBX_NULL2EMPTY_STR(dev->props[ZBX_VMWARE_DEV_PROPS_IFMAC]),
			ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(j, "{#IFCONNECTED}", ZBX_NULL2EMPTY_STR(dev->props[ZBX_VMWARE_DEV_PROPS_IFCONNECTED]),
			ZBX_JSON_TYPE_INT);
	zbx_json_addstring(j, "{#IFTYPE}", ZBX_NULL2EMPTY_STR(dev->props[ZBX_VMWARE_DEV_PROPS_IFTYPE]),
			ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(j, "{#IFBACKINGDEVICE}",
			ZBX_NULL2EMPTY_STR(dev->props[ZBX_VMWARE_DEV_PROPS_IFBACKINGDEVICE]),
			ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(j, "{#IFDVSWITCH.UUID}", ZBX_NULL2EMPTY_STR(
			dev->props[ZBX_VMWARE_DEV_PROPS_IFDVSWITCH_UUID]), ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(j, "{#IFDVSWITCH.PORTGROUP}", ZBX_NULL2EMPTY_STR(
			dev->props[ZBX_VMWARE_DEV_PROPS_IFDVSWITCH_PORTGROUP]), ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(j, "{#IFDVSWITCH.PORT}", ZBX_NULL2EMPTY_STR(
			dev->props[ZBX_VMWARE_DEV_PROPS_IFDVSWITCH_PORT]), ZBX_JSON_TYPE_STRING);
	zbx_json_addraw(j, "ifip", NULL == dev->props[ZBX_VMWARE_DEV_PROPS_IFIPS] ? "[]" :
			dev->props[ZBX_VMWARE_DEV_PROPS_IFIPS]);
}

static void	check_vcenter_vm_discovery_disk_props_cb(struct zbx_json *j, zbx_vmware_dev_t *dev)
{
	zbx_json_addstring(j, "{#DISKNAME}", ZBX_NULL2EMPTY_STR(dev->instance), ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(j, "{#DISKDESC}", ZBX_NULL2EMPTY_STR(dev->label), ZBX_JSON_TYPE_STRING);
}

static int	check_vcenter_vm_discovery_common(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result, int dev_type, const char *func_parent, vmpropfunc_t props_cb)
{
	struct zbx_json		json_data;
	zbx_vmware_service_t	*service;
	zbx_vmware_vm_t		*vm = NULL;
	zbx_vmware_dev_t	*dev;
	const char		*url, *uuid;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(), func_parent:'%s'", __func__, func_parent);

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

	zbx_json_initarray(&json_data, ZBX_JSON_STAT_BUF_LEN);

	for (int i = 0; i < vm->devs.values_num; i++)
	{
		dev = (zbx_vmware_dev_t *)vm->devs.values[i];

		if (dev_type != dev->type)
			continue;

		zbx_json_addobject(&json_data, NULL);
		props_cb(&json_data, dev);
		zbx_json_close(&json_data);
	}

	zbx_json_close(&json_data);

	SET_STR_RESULT(result, zbx_strdup(NULL, json_data.buffer));

	zbx_json_free(&json_data);

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(), func_parent:'%s', ret: %s", __func__, func_parent,
			zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_net_if_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return check_vcenter_vm_discovery_common(request, username, password, result, ZBX_VMWARE_DEV_TYPE_NIC, __func__,
			check_vcenter_vm_discovery_nic_props_cb);
}

static int	check_vcenter_vm_common(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result, const char *mode_in, const char *countername_mode, const char *countername_def,
		const char *func_parent)
{
	zbx_vmware_service_t	*service;
	const char		*path, *url, *uuid, *instance, *mode;
	unsigned int		coeff;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(), func_parent:'%s'", __func__, func_parent);

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
		path = countername_def;
		coeff = ZBX_KIBIBYTE;
	}
	else if (0 == strcmp(mode, mode_in))
	{
		path = countername_mode;
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
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(), func_parent:'%s', ret: %s", __func__, func_parent,
			zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_net_if_in(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return check_vcenter_vm_common(request, username, password, result, "pps", "net/packetsRx[summation]",
			"net/received[average]", __func__);
}

int	check_vcenter_vm_net_if_out(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return check_vcenter_vm_common(request, username, password, result, "pps", "net/packetsTx[summation]",
			"net/transmitted[average]",  __func__);
}

int	check_vcenter_vm_state(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_STATE, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_storage_committed(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_STORAGE_COMMITED, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_storage_unshared(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_STORAGE_UNSHARED, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_storage_uncommitted(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_STORAGE_UNCOMMITTED, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_tags_get(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_service_t		*service;
	zbx_vmware_vm_t			*vm = NULL;
	int				ret = SYSINFO_RET_FAIL;
	const char			*url, *uuid;
	struct zbx_json			json_data;
	char				*error = NULL;

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

	if (NULL != service->data_tags.error)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, service->data_tags.error));
		goto unlock;
	}

	zbx_json_initarray(&json_data, ZBX_JSON_STAT_BUF_LEN);
	vmware_tags_uuid_json(&service->data_tags, vm->uuid, NULL, &json_data, &error);
	zbx_json_close(&json_data);

	if (NULL == error)
	{
		SET_TEXT_RESULT(result, zbx_strdup(NULL, json_data.buffer));
		ret = SYSINFO_RET_OK;
	}
	else
		SET_STR_RESULT(result, error);

	zbx_json_free(&json_data);

unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_tools(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_service_t	*service;
	zbx_vmware_vm_t		*vm = NULL;
	int			propid, ret = SYSINFO_RET_FAIL;
	const char		*url, *uuid, *mode, *value;

	if (3 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	mode = get_rparam(request, 2);

	if ('\0' == *uuid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	if (NULL == mode || '\0' == *mode)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		goto out;
	}
	else if (0 == strcmp(mode, "version"))
	{
		propid = ZBX_VMWARE_VMPROP_TOOLS_VERSION;
	}
	else if (0 == strcmp(mode, "status"))
	{
		propid = ZBX_VMWARE_VMPROP_TOOLS_RUNNING_STATUS;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter value."));
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

	if (ZBX_VMWARE_VMPROP_TOOLS_VERSION == propid)
		SET_UI64_RESULT(result, atoi(value));
	else
		SET_STR_RESULT(result, zbx_strdup(NULL, value));

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_uptime(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ret = get_vcenter_vmprop(request, username, password, ZBX_VMWARE_VMPROP_UPTIME, result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_vfs_dev_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return check_vcenter_vm_discovery_common(request, username, password, result, ZBX_VMWARE_DEV_TYPE_DISK,
			__func__, check_vcenter_vm_discovery_disk_props_cb);
}

int	check_vcenter_vm_vfs_dev_read(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return check_vcenter_vm_common(request, username, password, result, "ops",
			"virtualDisk/numberReadAveraged[average]", "virtualDisk/read[average]", __func__);
}

int	check_vcenter_vm_vfs_dev_write(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return check_vcenter_vm_common(request, username, password, result, "ops",
			"virtualDisk/numberWriteAveraged[average]", "virtualDisk/write[average]", __func__);
}

int	check_vcenter_vm_vfs_fs_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	struct zbx_json		json_data;
	zbx_vmware_service_t	*service;
	zbx_vmware_vm_t		*vm = NULL;
	const char		*url, *uuid;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	zbx_json_initarray(&json_data, ZBX_JSON_STAT_BUF_LEN);

	for (int i = 0; i < vm->file_systems.values_num; i++)
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
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_vfs_fs_size(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_service_t	*service;
	zbx_vmware_vm_t		*vm;
	const char		*url, *uuid, *fsname, *mode;
	int			ret = SYSINFO_RET_FAIL;
	zbx_vmware_fs_t		*fs = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	for (int i = 0; i < vm->file_systems.values_num; i++)
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
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_perfcounter(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*instance, *url, *uuid, *path;
	zbx_vmware_service_t	*service;
	zbx_vmware_vm_t		*vm;
	zbx_uint64_t		counterid;
	int			unit, ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	if (FAIL == zbx_vmware_service_get_counterid(service, path, &counterid, &unit))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Performance counter is not available."));
		goto unlock;
	}

	/* FAIL is returned if counter already exists */
	if (SUCCEED == zbx_vmware_service_add_perf_counter(service, ZBX_VMWARE_SOAP_VM, vm->id, counterid,
			ZBX_VMWARE_PERF_QUERY_ALL))
	{
		ret = SYSINFO_RET_OK;
		goto unlock;
	}

	/* The performance counter is already being monitored, try to get the results from statistics. */
	ret = vmware_service_get_counter_value_by_id(service, ZBX_VMWARE_SOAP_VM, vm->id, counterid, instance, 1, unit,
			result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_dc_discovery(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char		*url;
	zbx_vmware_service_t	*service;
	struct zbx_json		json_data;
	int			ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (1 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	zbx_json_initarray(&json_data, ZBX_JSON_STAT_BUF_LEN);

	for (int i = 0; i < service->data->datacenters.values_num; i++)
	{
		zbx_vmware_datacenter_t	*datacenter = service->data->datacenters.values[i];

		zbx_json_addobject(&json_data, NULL);
		zbx_json_addstring(&json_data, "{#DATACENTER}", datacenter->name, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "{#DATACENTERID}", datacenter->id, ZBX_JSON_TYPE_STRING);
		vmware_tags_id_json(&service->data_tags, ZBX_VMWARE_SOAP_DC, datacenter->id, "tags", &json_data, NULL);
		zbx_json_close(&json_data);
	}

	zbx_json_close(&json_data);

	SET_STR_RESULT(result, zbx_strdup(NULL, json_data.buffer));

	zbx_json_free(&json_data);

	ret = SYSINFO_RET_OK;
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_dc_tags_get(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_service_t		*service;
	zbx_vmware_datacenter_t		*dc = NULL;
	int				ret = SYSINFO_RET_FAIL;
	const char			*url, *id;
	struct zbx_json			json_data;
	char				*error = NULL;

	if (2 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	id = get_rparam(request, 1);

	if ('\0' == *id)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	for (int i = 0; i < service->data->datacenters.values_num; i++)
	{
		if (0 == strcmp(service->data->datacenters.values[i]->id, id))
		{
			dc = service->data->datacenters.values[i];
			break;
		}
	}

	if (NULL == dc)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown datacenter id."));
		goto unlock;
	}

	if (NULL != service->data_tags.error)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, service->data_tags.error));
		goto unlock;
	}

	zbx_json_initarray(&json_data, ZBX_JSON_STAT_BUF_LEN);
	vmware_tags_id_json(&service->data_tags, ZBX_VMWARE_SOAP_DC, dc->id, NULL, &json_data, &error);
	zbx_json_close(&json_data);

	if (NULL == error)
	{
		SET_TEXT_RESULT(result, zbx_strdup(NULL, json_data.buffer));
		ret = SYSINFO_RET_OK;
	}
	else
		SET_STR_RESULT(result, error);

	zbx_json_free(&json_data);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_net_if_usage(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_service_t	*service;
	int			ret = SYSINFO_RET_FAIL;
	const char		*url, *uuid, *instance;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (2 > request->nparam || request->nparam > 3)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	instance = get_rparam(request, 2);

	if (NULL == instance)
		instance = "";

	if ('\0' == *uuid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	ret = vmware_service_get_vm_counter(service, uuid, instance, "net/usage[average]", ZBX_KIBIBYTE, result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_guest_memory_size_swapped(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_service_t	*service;
	int			ret = SYSINFO_RET_FAIL;
	const char		*url, *uuid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	ret = vmware_service_get_vm_counter(service, uuid, "", "mem/swapped[average]", ZBX_KIBIBYTE, result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_memory_size_consumed(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_service_t	*service;
	int			ret = SYSINFO_RET_FAIL;
	const char		*url, *uuid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	ret = vmware_service_get_vm_counter(service, uuid, "", "mem/consumed[average]", ZBX_KIBIBYTE, result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_memory_usage(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_service_t	*service;
	int			ret = SYSINFO_RET_FAIL;
	const char		*url, *uuid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	ret = vmware_service_get_vm_counter(service, uuid, "", "mem/usage[average]", 0, result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_cpu_latency(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_service_t	*service;
	int			ret = SYSINFO_RET_FAIL;
	const char		*url, *uuid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	ret = vmware_service_get_vm_counter(service, uuid, "", "cpu/latency[average]", 0, result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_cpu_readiness(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_service_t	*service;
	int			ret = SYSINFO_RET_FAIL;
	const char		*url, *uuid, *instance;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (2 > request->nparam || request->nparam > 3)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	instance = get_rparam(request, 2);

	if (NULL == instance)
		instance = "";

	if ('\0' == *uuid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	ret = vmware_service_get_vm_counter(service, uuid, instance, "cpu/readiness[average]", 0, result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_cpu_swapwait(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_service_t	*service;
	int			ret = SYSINFO_RET_FAIL;
	const char		*url, *uuid, *instance;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (2 > request->nparam || request->nparam > 3)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	instance = get_rparam(request, 2);

	if (NULL == instance)
		instance = "";

	if ('\0' == *uuid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	ret = vmware_service_get_vm_counter(service, uuid, instance, "cpu/swapwait[summation]", 0, result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_cpu_usage_perf(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_service_t	*service;
	int			ret = SYSINFO_RET_FAIL;
	const char		*url, *uuid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	ret = vmware_service_get_vm_counter(service, uuid, "", "cpu/usage[average]", 0, result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

static int	check_vcenter_vm_storage_common(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result, const char *counter_name,  const char *func_parent)
{
	zbx_vmware_service_t	*service;
	int			ret = SYSINFO_RET_FAIL;
	const char		*url, *uuid, *instance;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(), func_parent:'%s'", __func__, func_parent);

	if (3 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	uuid = get_rparam(request, 1);
	instance = get_rparam(request, 2);

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

	ret = vmware_service_get_vm_counter(service, uuid, instance, counter_name, 1, result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(), func_parent:'%s', ret: %s", __func__, func_parent,
			zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_vm_storage_readoio(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return check_vcenter_vm_storage_common(request, username, password, result, "virtualDisk/readOIO[latest]",
			__func__);
}

int	check_vcenter_vm_storage_writeoio(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return check_vcenter_vm_storage_common(request, username, password, result, "virtualDisk/writeOIO[latest]",
			__func__);
}

int	check_vcenter_vm_storage_totalwritelatency(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return check_vcenter_vm_storage_common(request, username, password, result,
			"virtualDisk/totalWriteLatency[average]", __func__);
}

int	check_vcenter_vm_storage_totalreadlatency(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	return check_vcenter_vm_storage_common(request, username, password, result,
			"virtualDisk/totalReadLatency[average]", __func__);
}

int	check_vcenter_vm_guest_uptime(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_service_t	*service;
	int			ret = SYSINFO_RET_FAIL;
	const char		*url, *uuid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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

	ret = vmware_service_get_vm_counter(service, uuid, "", "sys/osUptime[latest]", 1, result);
unlock:
	zbx_vmware_unlock();
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

static int	check_vcenter_rp_common(const char *url, const char *username, const char *password,
		const char *counter, const char *rpid, AGENT_RESULT *result)
{
	zbx_vmware_service_t		*service;
	zbx_vmware_resourcepool_t	rp_cmp;
	zbx_uint64_t			counterid;
	int				unit, ret = SYSINFO_RET_FAIL;

	zbx_vmware_lock();

	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))
		goto unlock;

	if (FAIL == zbx_vmware_service_get_counterid(service, counter, &counterid, &unit))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Performance counter is not available."));
		goto unlock;
	}

	rp_cmp.id = (char *)rpid;

	if (FAIL == zbx_vector_vmware_resourcepool_ptr_bsearch(&service->data->resourcepools, &rp_cmp,
			ZBX_DEFAULT_STR_PTR_COMPARE_FUNC))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown resource pool id."));
		goto unlock;
	}

	/* FAIL is returned if counter already exists */
	if (SUCCEED == zbx_vmware_service_add_perf_counter(service, ZBX_VMWARE_SOAP_RESOURCEPOOL, rpid, counterid,
			ZBX_VMWARE_PERF_QUERY_TOTAL))
	{
		ret = SYSINFO_RET_OK;
		goto unlock;
	}

	/* The performance counter is already being monitored, try to get the results from statistics. */
	ret = vmware_service_get_counter_value_by_id(service, ZBX_VMWARE_SOAP_RESOURCEPOOL, rpid, counterid, "", 0,
			unit, result);
unlock:
	zbx_vmware_unlock();
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_rp_cpu_usage(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*rpid, *url;
	int		ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (2 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);

	if (NULL == (rpid = get_rparam(request, 1)) || '\0' == *rpid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	ret = check_vcenter_rp_common(url, username, password, "cpu/usagemhz[average]", rpid, result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}

int	check_vcenter_rp_memory(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	const char	*rpid, *url, *mode, *counter;
	int		ret = SYSINFO_RET_FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (2 > request->nparam || 3 < request->nparam )
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	url = get_rparam(request, 0);
	rpid = get_rparam(request, 1);
	mode = get_rparam(request, 2);

	if (NULL == rpid || '\0' == *rpid)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto out;
	}

	if (NULL == mode || '\0' == *mode)
	{
		mode = "consumed";
	}

	if (0 == strcmp(mode, "consumed"))
	{
		counter = "mem/consumed[average]";
	}
	else if (0 == strcmp(mode, "ballooned"))
	{
		counter = "mem/vmmemctl[average]";
	}
	else if (0 == strcmp(mode, "overhead"))
	{
		counter = "mem/overhead[average]";
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		goto out;
	}

	ret = check_vcenter_rp_common(url, username, password, counter, rpid, result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));

	return ret;
}


static int	check_vcenter_alarm_get_common(zbx_vector_vmware_alarm_ptr_t *alarms, zbx_vector_str_t *ids,
		AGENT_RESULT *result)
{
	int		ret = SYSINFO_RET_OK;
	struct zbx_json	json_data;

	zbx_json_initarray(&json_data, ZBX_JSON_STAT_BUF_LEN);

	for (int i = 0; i < ids->values_num; i++)
	{
		zbx_vmware_alarm_t	*alarm, cmp = {.key = ids->values[i]};
		int			j;

		if (FAIL == (j = zbx_vector_vmware_alarm_ptr_bsearch(alarms, &cmp, ZBX_DEFAULT_STR_PTR_COMPARE_FUNC)))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Alarm not found:%s", cmp.key));
			ret = SYSINFO_RET_FAIL;
			break;
		}

		alarm = alarms->values[j];

		zbx_json_addobject(&json_data, NULL);
		zbx_json_addstring(&json_data, "name", alarm->name, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "system_name", alarm->system_name, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "description", alarm->description, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "enabled", (1 == alarm->enabled ? "true" : "false"), ZBX_JSON_TYPE_INT);
		zbx_json_addstring(&json_data, "key", alarm->key, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "time", alarm->time, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "overall_status", alarm->overall_status, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json_data, "acknowledged", (0 == alarm->acknowledged ? "false" : "true"),
				ZBX_JSON_TYPE_INT);
		zbx_json_close(&json_data);
	}

	zbx_json_close(&json_data);

	if (SYSINFO_RET_OK == ret)
		SET_STR_RESULT(result, zbx_strdup(NULL, json_data.buffer));

	zbx_json_free(&json_data);

	return ret;
}

#define	ALARMS_GET_START(num)									\
	const char		*uuid_or_id, *url;						\
	int			ret = SYSINFO_RET_FAIL;						\
	zbx_vmware_service_t	*service;							\
												\
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);					\
												\
	if (num != request->nparam)								\
	{											\
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));	\
		goto out;									\
	}											\
												\
	url = get_rparam(request, 0);								\
												\
	if (num > 1 && (NULL == (uuid_or_id = get_rparam(request, 1)) || '\0' == *uuid_or_id))	\
	{											\
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));		\
		goto out;									\
	}											\
												\
	zbx_vmware_lock();									\
												\
	if (NULL == (service = get_vmware_service(url, username, password, result, &ret)))	\
		goto unlock

#define	ALARMS_GET_END(ids)									\
	ret = check_vcenter_alarm_get_common(&service->data->alarms, &ids, result);		\
unlock:												\
	zbx_vmware_unlock();									\
out:												\
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_sysinfo_ret_string(ret));	\
												\
	return ret

int	check_vcenter_hv_alarms_get(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_hv_t	*hv;

	ALARMS_GET_START(2);

	if (NULL == (hv = hv_get(&service->data->hvs, uuid_or_id)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown hypervisor uuid."));
		goto unlock;
	}

	ALARMS_GET_END(hv->alarm_ids);
}

int	check_vcenter_vm_alarms_get(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_vm_t	*vm;

	ALARMS_GET_START(2);

	if (NULL == (vm = service_vm_get(service, uuid_or_id)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown virtual machine uuid."));
		goto unlock;
	}

	ALARMS_GET_END(vm->alarm_ids);
}

int	check_vcenter_datastore_alarms_get(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_datastore_t	*ds;

	ALARMS_GET_START(2);

	if (NULL == (ds = ds_get(&service->data->datastores, uuid_or_id)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown datastore uuid."));
		goto unlock;
	}

	ALARMS_GET_END(ds->alarm_ids);
}

int	check_vcenter_dc_alarms_get(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_datacenter_t	*dc;

	ALARMS_GET_START(2);

	if (NULL == (dc = dc_get(&service->data->datacenters, uuid_or_id)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown datacenter id."));
		goto unlock;
	}

	ALARMS_GET_END(dc->alarm_ids);
}

int	check_vcenter_cluster_alarms_get(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	zbx_vmware_cluster_t	*cl;

	ALARMS_GET_START(2);

	if (NULL == (cl = cluster_get(&service->data->clusters, uuid_or_id)))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Unknown cluster id."));
		goto unlock;
	}

	ALARMS_GET_END(cl->alarm_ids);
}

int	check_vcenter_alarms_get(AGENT_REQUEST *request, const char *username, const char *password,
		AGENT_RESULT *result)
{
	ALARMS_GET_START(1);
	ALARMS_GET_END(service->data->alarm_ids);
}

#undef	ALARMS_GET_START
#undef	ALARMS_GET_END

#undef	ZBX_VMWARE_DATASTORE_SIZE_TOTAL
#undef	ZBX_VMWARE_DATASTORE_SIZE_FREE
#undef	ZBX_VMWARE_DATASTORE_SIZE_PFREE
#undef	ZBX_VMWARE_DATASTORE_SIZE_UNCOMMITTED

#undef	ZBX_DATASTORE_TOTAL
#undef	ZBX_DATASTORE_COUNTER_CAPACITY
#undef	ZBX_DATASTORE_COUNTER_USED
#undef	ZBX_DATASTORE_COUNTER_PROVISIONED

#undef	ZBX_DATASTORE_DIRECTION_READ
#undef	ZBX_DATASTORE_DIRECTION_WRITE

#undef	ZBX_IF_DIRECTION_IN
#undef	ZBX_IF_DIRECTION_OUT

#endif	/* defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL) */
