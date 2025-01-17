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

#include "vmware_perfcntr.h"

#include "zbxcommon.h"

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

#include "zbxvmware.h"
#include "vmware_shmem.h"
#include "vmware_internal.h"
#include "zbxshmem.h"

#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxxml.h"
#ifdef HAVE_LIBXML2
#	include <libxml/xpath.h>
#endif

ZBX_VECTOR_IMPL(uint16, uint16_t)
ZBX_PTR_VECTOR_IMPL(perf_available_ptr, zbx_vmware_perf_available_t *)
ZBX_PTR_VECTOR_IMPL(vmware_perf_value_ptr, zbx_vmware_perf_value_t *)
ZBX_PTR_VECTOR_IMPL(vmware_counter_ptr, zbx_vmware_counter_t *)

/******************************************************************************
 *                                                                            *
 * performance counter hashset support functions                              *
 *                                                                            *
 ******************************************************************************/
zbx_hash_t	vmware_counter_hash_func(const void *data)
{
	const zbx_vmware_counter_t	*counter = (const zbx_vmware_counter_t *)data;

	return ZBX_DEFAULT_STRING_HASH_ALGO(counter->path, strlen(counter->path), ZBX_DEFAULT_HASH_SEED);
}

int	vmware_counter_compare_func(const void *d1, const void *d2)
{
	const zbx_vmware_counter_t	*c1 = (const zbx_vmware_counter_t *)d1;
	const zbx_vmware_counter_t	*c2 = (const zbx_vmware_counter_t *)d2;

	return strcmp(c1->path, c2->path);
}

/******************************************************************************
 *                                                                            *
 * performance entities hashset support functions                             *
 *                                                                            *
 ******************************************************************************/
zbx_hash_t	vmware_perf_entity_hash_func(const void *data)
{
	zbx_hash_t	seed;

	const zbx_vmware_perf_entity_t	*entity = (const zbx_vmware_perf_entity_t *)data;

	seed = ZBX_DEFAULT_STRING_HASH_ALGO(entity->type, strlen(entity->type), ZBX_DEFAULT_HASH_SEED);

	return ZBX_DEFAULT_STRING_HASH_ALGO(entity->id, strlen(entity->id), seed);
}

int	vmware_perf_entity_compare_func(const void *d1, const void *d2)
{
	int	ret;

	const zbx_vmware_perf_entity_t	*e1 = (const zbx_vmware_perf_entity_t *)d1;
	const zbx_vmware_perf_entity_t	*e2 = (const zbx_vmware_perf_entity_t *)d2;

	if (0 == (ret = strcmp(e1->type, e2->type)))
		ret = strcmp(e1->id, e2->id);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * performance counter availability list support functions                    *
 *                                                                            *
 ******************************************************************************/
static int	vmware_perf_available_compare(const void *d1, const void *d2)
{
	int	ret;

	const zbx_vmware_perf_available_t	*e1 = *(const zbx_vmware_perf_available_t * const *)d1;
	const zbx_vmware_perf_available_t	*e2 = *(const zbx_vmware_perf_available_t * const *)d2;

	if (0 == (ret = strcmp(e1->type, e2->type)))
		ret = strcmp(e1->id, e2->id);

	return ret;
}

static void	vmware_perf_available_free(zbx_vmware_perf_available_t *value)
{
	zbx_free(value->type);
	zbx_free(value->id);
	zbx_vector_uint16_clear(&value->list);
	zbx_vector_uint16_destroy(&value->list);
	zbx_free(value);
}

static int	vmware_uint16_compare(const void *d1, const void *d2)
{
	const uint16_t	*i1 = (const uint16_t *)d1;
	const uint16_t	*i2 = (const uint16_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(*i1, *i2);

	return 0;
}

static void	vmware_free_perfvalue(zbx_vmware_perf_value_t *value)
{
	zbx_free(value->instance);
	zbx_free(value);
}

static void	vmware_free_perfdata(zbx_vmware_perf_data_t *data)
{
	zbx_free(data->id);
	zbx_free(data->type);
	zbx_free(data->error);
	zbx_vector_vmware_perf_value_ptr_clear_ext(&data->values, vmware_free_perfvalue);
	zbx_vector_vmware_perf_value_ptr_destroy(&data->values);

	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies performance counter vector into shared memory hashset      *
 *                                                                            *
 * Parameters: dst - [IN] destination hashset                                 *
 *             src - [IN] source vector                                       *
 *                                                                            *
 ******************************************************************************/
void	vmware_counters_shared_copy(zbx_hashset_t *dst, const zbx_vector_vmware_counter_ptr_t *src)
{
	zbx_vmware_counter_t	*csrc, *cdst;

	if (SUCCEED != zbx_hashset_reserve(dst, src->values_num))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	for (int i = 0; i < src->values_num; i++)
	{
		csrc = src->values[i];

		cdst = (zbx_vmware_counter_t *)zbx_hashset_insert(dst, csrc, sizeof(zbx_vmware_counter_t));

		/* check if the counter was inserted - copy path only for inserted counters */
		if (cdst->path == csrc->path)
			cdst->path = vmware_shared_strdup(csrc->path);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store instance performance    *
 *          counter values                                                    *
 *                                                                            *
 * Parameters: pairs - [IN] vector of performance counter pairs               *
 *                                                                            *
 ******************************************************************************/
void	vmware_vector_str_uint64_pair_shared_clean(zbx_vector_str_uint64_pair_t *pairs)
{
	for (int i = 0; i < pairs->values_num; i++)
	{
		zbx_str_uint64_pair_t	*pair = &pairs->values[i];

		if (NULL != pair->name)
			vmware_shared_strfree(pair->name);
	}

	pairs->values_num = 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: removes statistics data from vmware entities                      *
 *                                                                            *
 ******************************************************************************/
static void	vmware_entities_shared_clean_stats(zbx_hashset_t *entities)
{
	zbx_vmware_perf_entity_t	*entity;
	zbx_vmware_perf_counter_t	*counter;
	zbx_hashset_iter_t		iter;

	zbx_hashset_iter_reset(entities, &iter);
	while (NULL != (entity = (zbx_vmware_perf_entity_t *)zbx_hashset_iter_next(&iter)))
	{
		for (int i = 0; i < entity->counters.values_num; i++)
		{
			counter = (zbx_vmware_perf_counter_t *)entity->counters.values[i];
			vmware_vector_str_uint64_pair_shared_clean(&counter->values);

			if (0 != (counter->state & ZBX_VMWARE_COUNTER_UPDATING))
			{
				counter->state = ZBX_VMWARE_COUNTER_READY |
						(counter->state & ZBX_VMWARE_COUNTER_STATE_MASK);
			}
		}
		vmware_shared_strfree(entity->error);
		entity->error = NULL;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: cleans resources allocated by vmware performance entity in vmware *
 *          cache                                                             *
 *                                                                            *
 * Parameters: entity - [IN] entity to free                                   *
 *                                                                            *
 ******************************************************************************/
void	vmware_shared_perf_entity_clean(zbx_vmware_perf_entity_t *entity)
{
	zbx_vector_vmware_perf_counter_ptr_clear_ext(&entity->counters, vmware_shmem_perf_counter_free);
	zbx_vector_vmware_perf_counter_ptr_destroy(&entity->counters);

	vmware_shared_strfree(entity->query_instance);
	vmware_shared_strfree(entity->type);
	vmware_shared_strfree(entity->id);
	vmware_shared_strfree(entity->error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated by vmware performance counter           *
 *                                                                            *
 * Parameters: counter - [IN] performance counter to free                     *
 *                                                                            *
 ******************************************************************************/
void	vmware_counter_shared_clean(zbx_vmware_counter_t *counter)
{
	vmware_shared_strfree(counter->path);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees vmware performance counter and resources allocated by it    *
 *                                                                            *
 * Parameters: counter - [IN] performance counter to free                     *
 *                                                                            *
 ******************************************************************************/
void	vmware_counter_free(zbx_vmware_counter_t *counter)
{
	zbx_free(counter->path);
	zbx_free(counter);
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets performance counter refreshrate for specified entity         *
 *                                                                            *
 * Parameters: service      - [IN] vmware service                             *
 *             easyhandle   - [IN] CURL handle                                *
 *             type         - [IN] entity type (HostSystem, datastore or      *
 *                                 VirtualMachine)                            *
 *             id           - [IN] entity id                                  *
 *             refresh_rate - [OUT] pointer to variable to store refresh rate *
 *             error        - [OUT] error message in case of failure          *
 *                                                                            *
 * Return value: SUCCEED - authentication was completed successfully          *
 *               FAIL    - authentication process has failed                  *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_perf_counter_refreshrate(zbx_vmware_service_t *service, CURL *easyhandle,
		const char *type, const char *id, int *refresh_rate, char **error)
{
#	define ZBX_XPATH_REFRESHRATE()						\
		"/*/*/*/*/*[local-name()='refreshRate' and ../*[local-name()='currentSupported']='true']"

#	define ZBX_POST_VCENTER_PERF_COUNTERS_REFRESH_RATE			\
		ZBX_POST_VSPHERE_HEADER						\
		"<ns0:QueryPerfProviderSummary>"				\
			"<ns0:_this type=\"PerformanceManager\">%s</ns0:_this>"	\
			"<ns0:entity type=\"%s\">%s</ns0:entity>"		\
		"</ns0:QueryPerfProviderSummary>"				\
		ZBX_POST_VSPHERE_FOOTER

#	define ZBX_XPATH_ISAGGREGATE()										\
		"/*/*/*/*/*[local-name()='entity'][../*[local-name()='summarySupported']='true' and "		\
		"../*[local-name()='currentSupported']='false']"

	char	tmp[MAX_STRING_LEN], *value = NULL, *id_esc;
	int	ret = FAIL;
	xmlDoc	*doc = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() type: %s id: %s", __func__, type, id);

	id_esc = zbx_xml_escape_dyn(id);
	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VCENTER_PERF_COUNTERS_REFRESH_RATE,
			get_vmware_service_objects()[service->type].performance_manager, type, id_esc);
	zbx_free(id_esc);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, NULL, error))
		goto out;

	if (NULL != (value = zbx_xml_doc_read_value(doc, ZBX_XPATH_ISAGGREGATE())))
	{
		zbx_free(value);
		*refresh_rate = ZBX_VMWARE_PERF_INTERVAL_NONE;
		ret = SUCCEED;

		zabbix_log(LOG_LEVEL_DEBUG, "%s() refresh_rate: unused", __func__);
		goto out;
	}
	else if (NULL == (value = zbx_xml_doc_read_value(doc, ZBX_XPATH_REFRESHRATE())))
	{
		*error = zbx_strdup(*error, "Cannot find refreshRate.");
		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() refresh_rate:%s", __func__, value);

	if (SUCCEED != (ret = zbx_is_uint31(value, refresh_rate)))
		*error = zbx_dsprintf(*error, "Cannot convert refreshRate from %s.",  value);

	zbx_free(value);
out:
	zbx_xml_doc_free(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef ZBX_XPATH_REFRESHRATE
#	undef ZBX_XPATH_ISAGGREGATE
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets performance counter ids                                      *
 *                                                                            *
 * Parameters: service      - [IN] vmware service                             *
 *             easyhandle   - [IN] CURL handle                                *
 *             counters     - [IN/OUT] vector, created performance counter    *
 *                                     object should be added to              *
 *             error        - [OUT] error message in case of failure          *
 *                                                                            *
 * Return value: SUCCEED - operation has completed successfully               *
 *               FAIL    - operation has failed                               *
 *                                                                            *
 ******************************************************************************/
int	vmware_service_get_perf_counters(zbx_vmware_service_t *service, CURL *easyhandle,
		zbx_vector_vmware_counter_ptr_t *counters, char **error)
{
#	define ZBX_XPATH_COUNTERINFO()								\
		"/*/*/*/*/*/*[local-name()='propSet']/*[local-name()='val']/*[local-name()='PerfCounterInfo']"

#	define ZBX_POST_VMWARE_GET_PERFCOUNTER							\
		ZBX_POST_VSPHERE_HEADER								\
		"<ns0:RetrievePropertiesEx>"							\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"			\
			"<ns0:specSet>"								\
				"<ns0:propSet>"							\
					"<ns0:type>PerformanceManager</ns0:type>"		\
					"<ns0:pathSet>perfCounter</ns0:pathSet>"		\
				"</ns0:propSet>"						\
				"<ns0:objectSet>"						\
					"<ns0:obj type=\"PerformanceManager\">%s</ns0:obj>"	\
				"</ns0:objectSet>"						\
			"</ns0:specSet>"							\
			"<ns0:options/>"							\
		"</ns0:RetrievePropertiesEx>"							\
		ZBX_POST_VSPHERE_FOOTER

#	define STR2UNIT(unit, val)								\
		if (0 == strcmp("joule",val))							\
			unit = ZBX_VMWARE_UNIT_JOULE;						\
		else if (0 == strcmp("kiloBytes",val))						\
			unit = ZBX_VMWARE_UNIT_KILOBYTES;					\
		else if (0 == strcmp("megaBytes",val))						\
			unit = ZBX_VMWARE_UNIT_MEGABYTES;					\
		else if (0 == strcmp("gigaBytes",val))						\
			unit = ZBX_VMWARE_UNIT_GIGABYTES;					\
		else if (0 == strcmp("teraBytes",val))						\
			unit = ZBX_VMWARE_UNIT_TERABYTES;					\
		else if (0 == strcmp("kiloBytesPerSecond",val))					\
			unit = ZBX_VMWARE_UNIT_KILOBYTESPERSECOND;				\
		else if (0 == strcmp("megaBytesPerSecond",val))					\
			unit = ZBX_VMWARE_UNIT_MEGABYTESPERSECOND;				\
		else if (0 == strcmp("megaHertz",val))						\
			unit = ZBX_VMWARE_UNIT_MEGAHERTZ;					\
		else if (0 == strcmp("nanosecond",val))						\
			unit = ZBX_VMWARE_UNIT_NANOSECOND;					\
		else if (0 == strcmp("microsecond",val))					\
			unit = ZBX_VMWARE_UNIT_MICROSECOND;					\
		else if (0 == strcmp("millisecond",val))					\
			unit = ZBX_VMWARE_UNIT_MILLISECOND;					\
		else if (0 == strcmp("second",val))						\
			unit = ZBX_VMWARE_UNIT_SECOND;						\
		else if (0 == strcmp("number",val))						\
			unit = ZBX_VMWARE_UNIT_NUMBER;						\
		else if (0 == strcmp("percent",val))						\
			unit = ZBX_VMWARE_UNIT_PERCENT;						\
		else if (0 == strcmp("watt",val))						\
			unit = ZBX_VMWARE_UNIT_WATT;						\
		else if (0 == strcmp("celsius",val))						\
			unit = ZBX_VMWARE_UNIT_CELSIUS;						\
		else										\
			unit = ZBX_VMWARE_UNIT_UNDEFINED

	char		tmp[MAX_STRING_LEN], *group = NULL, *key = NULL, *rollup = NULL, *stats = NULL,
			*counterid = NULL, *unit = NULL;
	xmlDoc		*doc = NULL;
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_GET_PERFCOUNTER,
			get_vmware_service_objects()[service->type].property_collector,
			get_vmware_service_objects()[service->type].performance_manager);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, NULL, error))
		goto out;

	xpathCtx = xmlXPathNewContext(doc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)ZBX_XPATH_COUNTERINFO(), xpathCtx)))
	{
		*error = zbx_strdup(*error, "Cannot make performance counter list parsing query.");
		goto clean;
	}

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
	{
		*error = zbx_strdup(*error, "Cannot find items in performance counter list.");
		goto clean;
	}

	nodeset = xpathObj->nodesetval;
	zbx_vector_vmware_counter_ptr_reserve(counters, (size_t)(2 * nodeset->nodeNr + counters->values_alloc));

	for (int i = 0; i < nodeset->nodeNr; i++)
	{
		zbx_vmware_counter_t	*counter;

		group = zbx_xml_node_read_value(doc, nodeset->nodeTab[i],
				"*[local-name()='groupInfo']/*[local-name()='key']");

		key = zbx_xml_node_read_value(doc, nodeset->nodeTab[i],
						"*[local-name()='nameInfo']/*[local-name()='key']");

		rollup = zbx_xml_node_read_value(doc, nodeset->nodeTab[i], "*[local-name()='rollupType']");
		stats = zbx_xml_node_read_value(doc, nodeset->nodeTab[i], "*[local-name()='statsType']");
		counterid = zbx_xml_node_read_value(doc, nodeset->nodeTab[i], "*[local-name()='key']");
		unit = zbx_xml_node_read_value(doc, nodeset->nodeTab[i],
				"*[local-name()='unitInfo']/*[local-name()='key']");

		if (NULL != group && NULL != key && NULL != rollup && NULL != counterid && NULL != unit)
		{
			counter = (zbx_vmware_counter_t *)zbx_malloc(NULL, sizeof(zbx_vmware_counter_t));
			counter->path = zbx_dsprintf(NULL, "%s/%s[%s]", group, key, rollup);
			ZBX_STR2UINT64(counter->id, counterid);
			STR2UNIT(counter->unit, unit);

			if (ZBX_VMWARE_UNIT_UNDEFINED == counter->unit)
			{
				zabbix_log(LOG_LEVEL_WARNING, "Unknown performance counter " ZBX_FS_UI64
						" type of unitInfo:%s", counter->id, unit);
			}

			zbx_vector_vmware_counter_ptr_append(counters, counter);

			zabbix_log(LOG_LEVEL_DEBUG, "adding performance counter %s:" ZBX_FS_UI64, counter->path,
					counter->id);
		}

		if (NULL != group && NULL != key && NULL != rollup && NULL != counterid && NULL != stats &&
				NULL != unit)
		{
			counter = (zbx_vmware_counter_t *)zbx_malloc(NULL, sizeof(zbx_vmware_counter_t));
			counter->path = zbx_dsprintf(NULL, "%s/%s[%s,%s]", group, key, rollup, stats);
			ZBX_STR2UINT64(counter->id, counterid);
			STR2UNIT(counter->unit, unit);

			if (ZBX_VMWARE_UNIT_UNDEFINED == counter->unit)
			{
				zabbix_log(LOG_LEVEL_WARNING, "Unknown performance counter " ZBX_FS_UI64
						" type of unitInfo:%s", counter->id, unit);
			}

			zbx_vector_vmware_counter_ptr_append(counters, counter);

			zabbix_log(LOG_LEVEL_DEBUG, "adding performance counter %s:" ZBX_FS_UI64, counter->path,
					counter->id);
		}

		zbx_free(counterid);
		zbx_free(stats);
		zbx_free(rollup);
		zbx_free(key);
		zbx_free(group);
		zbx_free(unit);
	}

	ret = SUCCEED;
clean:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);
out:
	zbx_xml_doc_free(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef ZBX_XPATH_COUNTERINFO
#	undef STR2UNIT
#	undef ZBX_POST_VMWARE_GET_PERFCOUNTER
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds entity to vmware service performance entity list             *
 *                                                                            *
 * Parameters: service  - [IN] vmware service                                 *
 *             type     - [IN] performance entity type (HostSystem,           *
 *                             (Datastore, VirtualMachine...)                 *
 *             id       - [IN] performance entity id                          *
 *             counters - [IN] NULL terminated list of performance counters   *
 *                             to be monitored for this entity                *
 *             instance - [IN] performance counter instance name              *
 *             now      - [IN] current timestamp                              *
 *                                                                            *
 * Comments: performance counters are specified by their path:                *
 *             <group>/<key>[<rollup type>]                                   *
 *                                                                            *
 ******************************************************************************/
static void	vmware_service_add_perf_entity(zbx_vmware_service_t *service, const char *type, const char *id,
		const char **counters, const char *instance, time_t now)
{
	zbx_vmware_perf_entity_t	entity, *pentity;
	zbx_uint64_t			counterid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() type:%s id:%s", __func__, type, id);

	if (NULL == (pentity = zbx_vmware_service_get_perf_entity(service, type, id)))
	{
		entity.type = vmware_shared_strdup(type);
		entity.id = vmware_shared_strdup(id);

		pentity = (zbx_vmware_perf_entity_t *)zbx_hashset_insert(&service->entities, &entity,
				sizeof(zbx_vmware_perf_entity_t));

		vmware_perf_counters_vector_ptr_create_ext(pentity);

		for (int i = 0; NULL != counters[i]; i++)
		{
			if (SUCCEED == zbx_vmware_service_get_counterid(service, counters[i], &counterid, NULL))
				vmware_perf_counters_add_new(&pentity->counters, counterid, ZBX_VMWARE_COUNTER_NEW);
			else
				zabbix_log(LOG_LEVEL_DEBUG, "cannot find performance counter %s", counters[i]);
		}

		zbx_vector_vmware_perf_counter_ptr_sort(&pentity->counters, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		pentity->refresh = ZBX_VMWARE_PERF_INTERVAL_UNKNOWN;
		pentity->query_instance = vmware_shared_strdup(instance);
		pentity->error = NULL;
	}

	pentity->last_seen = now;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() perfcounters:%d", __func__, pentity->counters.values_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds new or removes old entities (hypervisors, virtual machines)  *
 *          from service performance entity list                              *
 *                                                                            *
 * Parameters: service - [IN] vmware service                                  *
 *                                                                            *
 ******************************************************************************/
void	vmware_service_update_perf_entities(zbx_vmware_service_t *service)
{
	zbx_vmware_hv_t		*hv;
	zbx_vmware_vm_t		*vm;
	zbx_hashset_iter_t	iter;

	const char		*hv_perfcounters[] = {
					"net/packetsRx[summation]", "net/packetsTx[summation]",
					"net/received[average]", "net/transmitted[average]",
					"datastore/totalReadLatency[average]",
					"datastore/totalWriteLatency[average]",
					"datastore/numberReadAveraged[average]",
					"datastore/numberWriteAveraged[average]",
					"cpu/usage[average]", "cpu/utilization[average]",
					"power/power[average]", "power/powerCap[average]",
					"net/droppedRx[summation]", "net/droppedTx[summation]",
					"net/errorsRx[summation]", "net/errorsTx[summation]",
					"net/broadcastRx[summation]", "net/broadcastTx[summation]",
					NULL
				};

	const char		*vm_perfcounters[] = {
					"virtualDisk/read[average]", "virtualDisk/write[average]",
					"virtualDisk/numberReadAveraged[average]",
					"virtualDisk/numberWriteAveraged[average]",
					"net/packetsRx[summation]", "net/packetsTx[summation]",
					"net/received[average]", "net/transmitted[average]",
					"cpu/ready[summation]", "net/usage[average]", "cpu/usage[average]",
					"cpu/latency[average]", "cpu/readiness[average]",
					"cpu/swapwait[summation]", "sys/osUptime[latest]",
					"mem/consumed[average]", "mem/usage[average]", "mem/swapped[average]",
					"net/usage[average]", "virtualDisk/readOIO[latest]",
					"virtualDisk/writeOIO[latest]",
					"virtualDisk/totalWriteLatency[average]",
					"virtualDisk/totalReadLatency[average]",
					NULL
				};

	const char		*ds_perfcounters[] = {
					"disk/used[latest]", "disk/provisioned[latest]",
					"disk/capacity[latest]", NULL
				};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* update current performance entities */
	zbx_hashset_iter_reset(&service->data->hvs, &iter);
	while (NULL != (hv = (zbx_vmware_hv_t *)zbx_hashset_iter_next(&iter)))
	{
		vmware_service_add_perf_entity(service, ZBX_VMWARE_SOAP_HV, hv->id, hv_perfcounters,
				ZBX_VMWARE_PERF_QUERY_ALL, service->lastcheck);

		for (int i = 0; i < hv->vms.values_num; i++)
		{
			vm = (zbx_vmware_vm_t *)hv->vms.values[i];
			vmware_service_add_perf_entity(service, ZBX_VMWARE_SOAP_VM, vm->id, vm_perfcounters,
					ZBX_VMWARE_PERF_QUERY_ALL, service->lastcheck);
			zabbix_log(LOG_LEVEL_TRACE, "%s() for type: VirtualMachine hv id: %s hv uuid: %s linked vm id:"
					" %s vm uuid: %s", __func__, hv->id, hv->uuid, vm->id, vm->uuid);
		}
	}

	if (ZBX_VMWARE_TYPE_VCENTER == service->type)
	{
		for (int i = 0; i < service->data->datastores.values_num; i++)
		{
			zbx_vmware_datastore_t	*ds = service->data->datastores.values[i];
			vmware_service_add_perf_entity(service, ZBX_VMWARE_SOAP_DS, ds->id, ds_perfcounters,
					ZBX_VMWARE_PERF_QUERY_TOTAL, service->lastcheck);
			zabbix_log(LOG_LEVEL_TRACE, "%s() for type: Datastore id: %s name: %s uuid: %s", __func__,
					ds->id, ds->name, ds->uuid);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() entities:%d", __func__, service->entities.num_data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates vmware performance statistics data                        *
 *                                                                            *
 * Parameters: perfdata  - [OUT] performance counter values                   *
 *             xdoc      - [IN] xml document containing performance           *
 *                              counter values for all entities               *
 *             node      - [IN] xml node containing performance counter       *
 *                              values for specified entity                   *
 *                                                                            *
 * Return value: SUCCEED - performance entity data was parsed                 *
 *               FAIL    - performance entity data did not contain valid      *
 *                         values                                             *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_process_perf_entity_data(zbx_vmware_perf_data_t *perfdata, xmlDoc *xdoc, xmlNode *node)
{
	xmlXPathContext				*xpathCtx;
	xmlXPathObject				*xpathObj;
	xmlNodeSetPtr				nodeset;
	char					*instance, *counter, *value;
	int					values = 0, ret = FAIL;
	zbx_vector_vmware_perf_value_ptr_t	*pervalues = &perfdata->values;
	zbx_vmware_perf_value_t			*perfvalue;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	xpathCtx = xmlXPathNewContext(xdoc);
	xpathCtx->node = node;

	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)"*[local-name()='value']", xpathCtx)))
		goto out;

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto out;

	nodeset = xpathObj->nodesetval;
	zbx_vector_vmware_perf_value_ptr_reserve(pervalues, (size_t)(nodeset->nodeNr + pervalues->values_alloc));

	for (int i = 0; i < nodeset->nodeNr; i++)
	{
		if (NULL == (value = zbx_xml_node_read_value(xdoc, nodeset->nodeTab[i],
				"*[local-name()='value'][text() != '-1'][last()]")))
		{
			value = zbx_xml_node_read_value(xdoc, nodeset->nodeTab[i], "*[local-name()='value'][last()]");
		}

		instance = zbx_xml_node_read_value(xdoc, nodeset->nodeTab[i], "*[local-name()='id']"
				"/*[local-name()='instance']");
		counter = zbx_xml_node_read_value(xdoc, nodeset->nodeTab[i], "*[local-name()='id']"
				"/*[local-name()='counterId']");

		if (NULL != value && NULL != counter)
		{
			perfvalue = (zbx_vmware_perf_value_t *)zbx_malloc(NULL, sizeof(zbx_vmware_perf_value_t));

			ZBX_STR2UINT64(perfvalue->counterid, counter);
			perfvalue->instance = (NULL != instance ? instance : zbx_strdup(NULL, ""));

			if (0 == strcmp(value, "-1") || SUCCEED != zbx_is_uint64(value, &perfvalue->value))
			{
				perfvalue->value = ZBX_MAX_UINT64;
				zabbix_log(LOG_LEVEL_DEBUG, "PerfCounter inaccessible. type:%s object id:%s "
						"counter id:" ZBX_FS_UI64 " instance:%s value:%s", perfdata->type,
						perfdata->id, perfvalue->counterid, perfvalue->instance, value);
			}
			else if (FAIL == ret)
				ret = SUCCEED;

			zbx_vector_vmware_perf_value_ptr_append(pervalues, perfvalue);

			instance = NULL;
			values++;
		}

		zbx_free(counter);
		zbx_free(instance);
		zbx_free(value);
	}

out:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() values:%d", __func__, values);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates vmware performance statistics data                        *
 *                                                                            *
 * Parameters: perfdata - [OUT] performance entity data                       *
 *             xdoc     - [IN] performance data xml document                  *
 *                                                                            *
 ******************************************************************************/
static void	vmware_service_parse_perf_data(zbx_vector_vmware_perf_data_ptr_t *perfdata, xmlDoc *xdoc)
{
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	xpathCtx = xmlXPathNewContext(xdoc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)"/*/*/*/*", xpathCtx)))
		goto clean;

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto clean;

	nodeset = xpathObj->nodesetval;
	zbx_vector_vmware_perf_data_ptr_reserve(perfdata, (size_t)(nodeset->nodeNr + perfdata->values_alloc));

	for (int i = 0; i < nodeset->nodeNr; i++)
	{
		zbx_vmware_perf_data_t	*data;
		int			ret = FAIL;

		data = (zbx_vmware_perf_data_t *)zbx_malloc(NULL, sizeof(zbx_vmware_perf_data_t));

		data->id = zbx_xml_node_read_value(xdoc, nodeset->nodeTab[i], "*[local-name()='entity']");
		data->type = zbx_xml_node_read_value(xdoc, nodeset->nodeTab[i], "*[local-name()='entity']/@type");
		data->error = NULL;
		zbx_vector_vmware_perf_value_ptr_create(&data->values);

		if (NULL != data->type && NULL != data->id)
			ret = vmware_service_process_perf_entity_data(data, xdoc, nodeset->nodeTab[i]);

		if (SUCCEED == ret)
			zbx_vector_vmware_perf_data_ptr_append(perfdata, data);
		else
			vmware_free_perfdata(data);
	}
clean:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds error for specified perf entity                              *
 *                                                                            *
 * Parameters: perfdata - [OUT] collected performance counter data            *
 *             type     - [IN] performance entity type (HostSystem,           *
 *                             (Datastore, VirtualMachine...)                 *
 *             id       - [IN] performance entity id                          *
 *             error    - [IN] error to add                                   *
 *                                                                            *
 * Comments: performance counters are specified by their path:                *
 *             <group>/<key>[<rollup type>]                                   *
 *                                                                            *
 ******************************************************************************/
static void	vmware_perf_data_add_error(zbx_vector_vmware_perf_data_ptr_t *perfdata, const char *type,
		const char *id, const char *error)
{
	zbx_vmware_perf_data_t	*data = zbx_malloc(NULL, sizeof(zbx_vmware_perf_data_t));

	data->type = zbx_strdup(NULL, type);
	data->id = zbx_strdup(NULL, id);
	data->error = zbx_strdup(NULL, error);
	zbx_vector_vmware_perf_value_ptr_create(&data->values);

	zbx_vector_vmware_perf_data_ptr_append(perfdata, data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: copies vmware performance statistics of specified service         *
 *                                                                            *
 * Parameters: service  - [IN] vmware service                                 *
 *             perfdata - [IN/OUT]                                            *
 *                                                                            *
 ******************************************************************************/
static void	vmware_service_copy_perf_data(const zbx_vmware_service_t *service,
		zbx_vector_vmware_perf_data_ptr_t *perfdata)
{
	zbx_vmware_perf_data_t		*data;
	zbx_vmware_perf_value_t		*value;
	zbx_vmware_perf_entity_t	*entity;
	zbx_vmware_perf_counter_t	*perfcounter;
	zbx_str_uint64_pair_t		perfvalue;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (int i = 0; i < perfdata->values_num; i++)
	{
		data = (zbx_vmware_perf_data_t *)perfdata->values[i];

		if (NULL == (entity = zbx_vmware_service_get_perf_entity(service, data->type, data->id)))
			continue;

		if (NULL != data->error)
		{
			entity->error = vmware_shared_strdup(data->error);
			continue;
		}

		for (int j = 0; j < data->values.values_num; j++)
		{
			int	index;

			value = (zbx_vmware_perf_value_t *)data->values.values[j];

			zbx_vmware_perf_counter_t	loc = {.counterid = value->counterid};

			if (FAIL == (index = zbx_vector_vmware_perf_counter_ptr_bsearch(&entity->counters,
					&loc, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				continue;
			}

			perfcounter = (zbx_vmware_perf_counter_t *)entity->counters.values[index];

			perfvalue.name = vmware_shared_strdup(value->instance);
			perfvalue.value = value->value;

			zbx_vector_str_uint64_pair_append_ptr(&perfcounter->values, &perfvalue);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves performance counter values from vmware service          *
 *                                                                            *
 * Parameters: service      - [IN] vmware service                             *
 *             easyhandle   - [IN] prepared cURL connection handle            *
 *             entities     - [IN] performance collector entities to retrieve *
 *                                 counters for                               *
 *             counters_max - [IN] maximum number of counters per query       *
 *             perfdata     - [OUT] performance counter values                *
 *                                                                            *
 ******************************************************************************/
static void	vmware_service_retrieve_perf_counters(zbx_vmware_service_t *service, CURL *easyhandle,
		zbx_vector_vmware_perf_entity_ptr_t *entities, int counters_max,
		zbx_vector_vmware_perf_data_ptr_t *perfdata)
{
	char				*tmp = NULL, *error = NULL;
	size_t				tmp_alloc = 0, tmp_offset;
	int				i, j, start_counter = 0;
	zbx_vmware_perf_entity_t	*entity;
	xmlDoc				*doc = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() counters_max:%d", __func__, counters_max);

	while (0 != entities->values_num)
	{
		int	counters_num = 0;

		tmp_offset = 0;
		zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, ZBX_POST_VSPHERE_HEADER);
		zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset, "<ns0:QueryPerf>"
				"<ns0:_this type=\"PerformanceManager\">%s</ns0:_this>",
				get_vmware_service_objects()[service->type].performance_manager);

		zbx_vmware_lock();

		for (i = entities->values_num - 1; 0 <= i && counters_num < counters_max;)
		{
			char	*id_esc;

			entity = (zbx_vmware_perf_entity_t *)entities->values[i];

			id_esc = zbx_xml_escape_dyn(entity->id);

			/* add entity performance counter request */
			zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset, "<ns0:querySpec>"
					"<ns0:entity type=\"%s\">%s</ns0:entity>", entity->type, id_esc);

			zbx_free(id_esc);

			if (ZBX_VMWARE_PERF_INTERVAL_NONE == entity->refresh)
			{
				time_t	st_raw;
				struct	tm st;
				char	st_str[ZBX_XML_DATETIME];

				/* add startTime for entity performance counter request for decrease xml data load */
				st_raw = time(NULL) - SEC_PER_HOUR;
				gmtime_r(&st_raw, &st);
				strftime(st_str, sizeof(st_str), "%Y-%m-%dT%TZ", &st);
				zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset, "<ns0:startTime>%s</ns0:startTime>",
						st_str);
			}

			zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset, "<ns0:maxSample>2</ns0:maxSample>");

			for (j = start_counter; j < entity->counters.values_num && counters_num < counters_max; j++)
			{
				zbx_vmware_perf_counter_t	*counter;

				counter = (zbx_vmware_perf_counter_t *)entity->counters.values[j];

				if (0 != (counter->state & ZBX_VMWARE_COUNTER_CUSTOM) &&
						0 == (counter->state & ZBX_VMWARE_COUNTER_ACCEPTABLE))
				{
					continue;
				}

				zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset,
						"<ns0:metricId><ns0:counterId>" ZBX_FS_UI64
						"</ns0:counterId><ns0:instance>%s</ns0:instance></ns0:metricId>",
						counter->counterid, NULL == counter->query_instance ?
						entity->query_instance : counter->query_instance);

				counter->state |= ZBX_VMWARE_COUNTER_UPDATING;

				counters_num++;
			}

			if (j == entity->counters.values_num)
			{
				start_counter = 0;
				i--;
			}
			else
				start_counter = j;

			if (ZBX_VMWARE_PERF_INTERVAL_NONE != entity->refresh)
			{
				zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset, "<ns0:intervalId>%d</ns0:intervalId>",
					entity->refresh);
			}

			zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset, "</ns0:querySpec>");
		}

		zbx_vmware_unlock();
		zbx_xml_doc_free(doc);

		zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, "</ns0:QueryPerf>");
		zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, ZBX_POST_VSPHERE_FOOTER);

		zabbix_log(LOG_LEVEL_TRACE, "%s() SOAP request: %s", __func__, tmp);

		if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, NULL, &error))
		{
			for (j = i + 1; j < entities->values_num; j++)
			{
				entity = (zbx_vmware_perf_entity_t *)entities->values[j];
				vmware_perf_data_add_error(perfdata, entity->type, entity->id, error);
			}

			zbx_free(error);
			break;
		}

		/* parse performance data into local memory */
		vmware_service_parse_perf_data(perfdata, doc);

		while (entities->values_num > i + 1)
			zbx_vector_vmware_perf_entity_ptr_remove_noorder(entities, entities->values_num - 1);
	}

	zbx_free(tmp);
	zbx_xml_doc_free(doc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: removes unused performance counters                               *
 *                                                                            *
 * Parameters: counters - [IN] list of perf counters                          *
 *                                                                            *
 * Return value: SUCCEED - performance entity is empty (can be deleted)       *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	vmware_perf_counters_expired_remove(zbx_vector_vmware_perf_counter_ptr_t *counters)
{
	time_t	now = time(NULL);

	for (int i = counters->values_num - 1; i >= 0 ; i--)
	{
		zbx_vmware_perf_counter_t	*counter = counters->values[i];

		if (0 == (counter->state & ZBX_VMWARE_COUNTER_CUSTOM))
			continue;

		if (0 == counter->last_used ||
				(0 != (counter->state & ZBX_VMWARE_COUNTER_NOTSUPPORTED) &&
				now - SEC_PER_HOUR * 2 < counter->last_used) ||
				(0 == (counter->state & ZBX_VMWARE_COUNTER_NOTSUPPORTED) &&
				now - SEC_PER_DAY < counter->last_used))
		{
			continue;
		}

		vmware_shmem_perf_counter_free(counter);
		zbx_vector_vmware_perf_counter_ptr_remove(counters, i);
	}

	return 0 == counters->values_num ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates cache with lists of available perf counter for entity     *
 *                                                                            *
 * Parameters: service        - [IN] vmware service                           *
 *             easyhandle     - [IN] prepared cURL connection handle          *
 *             type           - [IN] vmware object type (vm, hv etc)          *
 *             id             - [IN] vmware object id (vm, hv etc)            *
 *             refresh        - [IN] vmware refresh interval for perf counter *
 *             begin_time     - [IN] vmware begin time for perf counters list *
 *             perf_available - [IN/OUT] list of available counter per object *
 *             perf           - [IN/OUT] list of perf entities                *
 *             error          - [OUT] error message in case of failure        *
 *                                                                            *
 * Return value: SUCCEED - operation has completed successfully               *
 *               FAIL    - operation has failed                               *
 *                                                                            *
 ******************************************************************************/
static int	vmware_perf_available_update(zbx_vmware_service_t *service, CURL *easyhandle, const char *type,
		const char *id, const int refresh, const char *begin_time,
		zbx_vector_perf_available_ptr_t *perf_available, zbx_vmware_perf_available_t **perf, char **error)
{
#	define ZBX_POST_VMWARE_GET_AVAIL_PERF							\
		ZBX_POST_VSPHERE_HEADER								\
		"<ns0:QueryAvailablePerfMetric>"						\
			"<ns0:_this type=\"PerformanceManager\">%s</ns0:_this>"			\
			"<ns0:entity type=\"%s\">%s</ns0:entity>"				\
			"<ns0:beginTime>%s</ns0:beginTime>"					\
			"%s"									\
		"</ns0:QueryAvailablePerfMetric>"						\
		ZBX_POST_VSPHERE_FOOTER

	int			ret;
	char			tmp[MAX_STRING_LEN], interval[MAX_STRING_LEN / 32];
	xmlDoc			*doc = NULL;
	zbx_vector_str_t	counters;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() type:%s id:%s begin_time:%s interval:%d", __func__, type, id,
			begin_time, refresh);

	zbx_vector_str_create(&counters);

	if (ZBX_VMWARE_PERF_INTERVAL_NONE == refresh)
		*interval = '\0';
	else
		zbx_snprintf(interval, sizeof(interval), "<ns0:intervalId>%d</ns0:intervalId>", refresh);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_GET_AVAIL_PERF,
			get_vmware_service_objects()[service->type].performance_manager, type, id, begin_time,
			interval);

	if (SUCCEED != (ret = zbx_soap_post(__func__, easyhandle, tmp, &doc, NULL, error)))
		goto out;

	if (FAIL == zbx_xml_read_values(doc, "/" ZBX_XPATH_LN2("returnval", "counterId"), &counters))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() empty list for type:%s id:%s interval:%d begin time:%s", __func__,
				type, id, refresh, begin_time);
	}

	*perf = (zbx_vmware_perf_available_t *)zbx_malloc(NULL, sizeof(zbx_vmware_perf_available_t));
	(*perf)->type = zbx_strdup(NULL, type);
	(*perf)->id = zbx_strdup(NULL, id);
	zbx_vector_uint16_create(&(*perf)->list);

	for (int i = 0; i < counters.values_num; i++)
		zbx_vector_uint16_append(&(*perf)->list, (uint16_t)atoi(counters.values[i]));

	zbx_vector_uint16_sort(&(*perf)->list, vmware_uint16_compare);
	zbx_vector_uint16_uniq(&(*perf)->list, vmware_uint16_compare);
	zbx_vector_perf_available_ptr_append(perf_available, *perf);
	zbx_vector_perf_available_ptr_sort(perf_available, vmware_perf_available_compare);
out:
	zbx_vector_str_clear_ext(&counters, zbx_str_free);
	zbx_vector_str_destroy(&counters);
	zbx_xml_doc_free(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef ZBX_POST_VMWARE_GET_AVAIL_PERF
}

/******************************************************************************
 *                                                                            *
 * Purpose: sets flag ZBX_VMWARE_COUNTER_ACCEPTABLE for new perf counters     *
 *                                                                            *
 * Parameters: service        - [IN] vmware service                           *
 *             easyhandle     - [IN] prepared cURL connection handle          *
 *             perf_available - [IN/OUT] list of available counter per object *
 *             entities       - [IN/OUT] list of perf entities                *
 *                                                                            *
 ******************************************************************************/
static void	vmware_perf_counters_availability_check(zbx_vmware_service_t *service, CURL *easyhandle,
		zbx_vector_perf_available_ptr_t *perf_available, zbx_vector_vmware_perf_entity_ptr_t *entities)
{
	char	begin_time[ZBX_XML_DATETIME];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() entities:%d perf_available:%d", __func__,
			entities->values_num, perf_available->values_num);

	*begin_time = '\0';

	for (int i = 0; i < entities->values_num ; i++)
	{
		zbx_vmware_perf_entity_t	*entity;

		entity = (zbx_vmware_perf_entity_t *)entities->values[i];

		for (int j = 0; j < entity->counters.values_num; j++)
		{
			int				k;
			char				*err = NULL;
			zbx_vmware_perf_counter_t	*counter;
			zbx_vmware_perf_available_t	*perf, perf_cmp = {.type = entity->type, .id = entity->id};

			counter = (zbx_vmware_perf_counter_t *)entity->counters.values[j];

			if (0 == (counter->state & ZBX_VMWARE_COUNTER_CUSTOM) ||
					0 != (counter->state & ZBX_VMWARE_COUNTER_ACCEPTABLE))
			{
				continue;
			}

			if ('\0' == *begin_time)
			{
				time_t		st_raw;
				struct	tm	st;

				st_raw = time(NULL) - SEC_PER_HOUR;
				gmtime_r(&st_raw, &st);
				strftime(begin_time, sizeof(begin_time), "%Y-%m-%dT%TZ", &st);
			}

			if (FAIL != (k = zbx_vector_perf_available_ptr_bsearch(
					perf_available, &perf_cmp, vmware_perf_available_compare)))
			{
				perf = perf_available->values[k];
			}
			else if (FAIL == vmware_perf_available_update(service, easyhandle, entity->type,
					entity->id, entity->refresh, begin_time, perf_available, &perf, &err))
			{
				zabbix_log(LOG_LEVEL_WARNING, "%s() cache update error: %s", __func__, err);
				zbx_str_free(err);
				return;
			}

			if (FAIL == zbx_vector_uint16_bsearch(&perf->list, (uint16_t)counter->counterid,
					vmware_uint16_compare))
			{
				counter->state |= ZBX_VMWARE_COUNTER_NOTSUPPORTED;
			}
			else
			{
				counter->state |= ZBX_VMWARE_COUNTER_ACCEPTABLE;
			}

			zabbix_log(LOG_LEVEL_DEBUG, "%s() type:%s id:%s counterid:" ZBX_FS_UI64 " state:%X %s",
					__func__, entity->type, entity->id, counter->counterid, counter->state,
					0 == (counter->state & ZBX_VMWARE_COUNTER_ACCEPTABLE) ?
					"NOTSUPPORTED" : "ACCEPTABLE");
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate required shared memory size of the perfCounter entity   *
 *                                                                            *
 * Parameters: type      - [IN] entity type                                   *
 *             id        - [IN] entity id                                     *
 *             instance  - [IN] performance counter instance name             *
 *                                                                            *
 * Return value: size of shared memory in bytes                               *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	vmware_perf_entity_shmem_size(const char *type, const char *id, const char *instance)
{
	zbx_uint64_t	req_sz = 0;

	req_sz += zbx_shmem_required_chunk_size(sizeof(zbx_vmware_perf_entity_t) + ZBX_HASHSET_ENTRY_OFFSET);
	req_sz += vmware_shared_str_sz(id);
	req_sz += vmware_shared_str_sz(type);
	req_sz += vmware_shared_str_sz(instance);
	req_sz += zbx_shmem_required_chunk_size(sizeof(zbx_vmware_perf_counter_t));
	req_sz += zbx_shmem_required_chunk_size(sizeof(zbx_vmware_perf_counter_t*));

	return req_sz;
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate required shared memory size of the perfCounter values   *
 *                                                                            *
 * Parameters: perfdata - [IN] performance counter values                     *
 *                                                                            *
 * Return value: size of shared memory in bytes                               *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	vmware_perf_data_shmem_size(zbx_vector_vmware_perf_data_ptr_t *perfdata)
{
	zbx_vmware_perf_data_t	*data;
	zbx_uint64_t		req_sz = 0;

	for (int i = 0; i < perfdata->values_num; i++)
	{
		data = (zbx_vmware_perf_data_t *)perfdata->values[i];

		if (NULL != data->error)
		{
			req_sz += vmware_shared_str_sz(data->error);
			continue;
		}

		for (int j = 0; j < data->values.values_num; j++)
		{
			zbx_vmware_perf_value_t	*value = data->values.values[j];

			req_sz += zbx_shmem_required_chunk_size(sizeof(zbx_str_uint64_pair_t));
			req_sz += vmware_shared_str_sz(value->instance);
		}
	}

	return req_sz;
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates vmware statistics data                                    *
 *                                                                            *
 * Parameters: service               - [IN] vmware service                    *
 *             config_source_ip      - [IN]                                   *
 *             config_vmware_timeout - [IN]                                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_vmware_service_update_perf(zbx_vmware_service_t *service, const char *config_source_ip,
		int config_vmware_timeout)
{
#define INIT_PERF_XML_SIZE	200 * ZBX_KIBIBYTE
	CURL					*easyhandle = NULL;
	CURLoption				opt;
	CURLcode				err;
	struct curl_slist			*headers = NULL;
	int					ret = FAIL;
	char					*error = NULL;
	zbx_vector_vmware_perf_entity_ptr_t	entities, hist_entities;
	zbx_vmware_perf_entity_t		*entity;

	zbx_hashset_iter_t			iter;
	zbx_vector_vmware_perf_data_ptr_t	perfdata;
	zbx_vector_perf_available_ptr_t		perf_available;
	static ZBX_HTTPPAGE			page;	/* 173K */
	zbx_uint64_t				perf_data_sz = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() '%s'@'%s'", __func__, service->username, service->url);

	zbx_vector_vmware_perf_entity_ptr_create(&entities);
	zbx_vector_vmware_perf_entity_ptr_create(&hist_entities);
	zbx_vector_vmware_perf_data_ptr_create(&perfdata);
	zbx_vector_perf_available_ptr_create(&perf_available);
	page.alloc = 0;

	if (NULL == (easyhandle = curl_easy_init()))
	{
		error = zbx_strdup(error, "cannot initialize cURL library");
		goto out;
	}

	page.alloc = INIT_PERF_XML_SIZE;
	page.data = (char *)zbx_malloc(NULL, page.alloc);
	headers = curl_slist_append(headers, ZBX_XML_HEADER1_V4);
	headers = curl_slist_append(headers, ZBX_XML_HEADER2);
	headers = curl_slist_append(headers, ZBX_XML_HEADER3);

	if (CURLE_OK != (err = curl_easy_setopt(easyhandle, opt = CURLOPT_HTTPHEADER, headers)))
	{
		error = zbx_dsprintf(error, "Cannot set cURL option %d: %s.", (int)opt, curl_easy_strerror(err));
		goto clean;
	}

	if (SUCCEED != vmware_service_authenticate(service, easyhandle, &page, config_source_ip, config_vmware_timeout,
			&error))
	{
		goto clean;
	}

	/* update performance counter refresh rate for entities */

	zbx_vmware_lock();

	zbx_hashset_iter_reset(&service->entities, &iter);
	while (NULL != (entity = (zbx_vmware_perf_entity_t *)zbx_hashset_iter_next(&iter)))
	{
		/* remove old entities */
		if ((0 != entity->last_seen && entity->last_seen < service->lastcheck) ||
				SUCCEED == vmware_perf_counters_expired_remove(&entity->counters))
		{
			vmware_shared_perf_entity_clean(entity);
			zbx_hashset_iter_remove(&iter);
			continue;
		}

		if (ZBX_VMWARE_PERF_INTERVAL_UNKNOWN != entity->refresh)
			continue;

		/* Entities are removed only during performance counter update and no two */
		/* performance counter updates for one service can happen simultaneously. */
		/* This means for refresh update we can safely use reference to entity    */
		/* outside vmware lock.                                                   */
		zbx_vector_vmware_perf_entity_ptr_append(&entities, entity);
	}

	zbx_vmware_unlock();

	/* get refresh rates */
	for (int i = 0; i < entities.values_num; i++)
	{
		entity = entities.values[i];

		if (SUCCEED != vmware_service_get_perf_counter_refreshrate(service, easyhandle, entity->type,
				entity->id, &entity->refresh, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot get refresh rate for %s \"%s\": %s", entity->type,
					entity->id, error);
			zbx_free(error);
		}
	}

	zbx_vector_vmware_perf_entity_ptr_clear(&entities);

	zbx_vmware_lock();

	/* checking the availability of custom performance counters */
	zbx_hashset_iter_reset(&service->entities, &iter);
	while (NULL != (entity = (zbx_vmware_perf_entity_t *)zbx_hashset_iter_next(&iter)))
	{
		for (int i = 0; i < entity->counters.values_num; i++)
		{
			zbx_vmware_perf_counter_t	*counter;

			counter = (zbx_vmware_perf_counter_t *)entity->counters.values[i];

			if (0 == (counter->state & ZBX_VMWARE_COUNTER_CUSTOM) || 0 != (counter->state &
					(ZBX_VMWARE_COUNTER_ACCEPTABLE | ZBX_VMWARE_COUNTER_NOTSUPPORTED)))
			{
				continue;
			}

			zbx_vector_vmware_perf_entity_ptr_append(&entities, entity);
			break;
		}
	}

	zbx_vmware_unlock();

	vmware_perf_counters_availability_check(service, easyhandle, &perf_available, &entities);
	zbx_vector_vmware_perf_entity_ptr_clear(&entities);

	zbx_vmware_lock();

	zbx_hashset_iter_reset(&service->entities, &iter);
	while (NULL != (entity = (zbx_vmware_perf_entity_t *)zbx_hashset_iter_next(&iter)))
	{
		if (ZBX_VMWARE_PERF_INTERVAL_UNKNOWN == entity->refresh)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "skipping performance entity with zero refresh rate "
					"type:%s id:%s", entity->type, entity->id);
			continue;
		}

		int	i;

		/* pre-check acceptable counters */
		for (i = 0; i < entity->counters.values_num; i++)
		{
			zbx_vmware_perf_counter_t	*counter;

			counter = (zbx_vmware_perf_counter_t *)entity->counters.values[i];

			if (0 != (counter->state & ZBX_VMWARE_COUNTER_CUSTOM) &&
					0 == (counter->state & ZBX_VMWARE_COUNTER_ACCEPTABLE))
			{
				continue;
			}

			break;
		}

		if (i == entity->counters.values_num)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "skipping performance entity with type:%s id:%s: "
					"unsupported counters", entity->type, entity->id);
			continue;
		}


		if (ZBX_VMWARE_PERF_INTERVAL_NONE == entity->refresh)
			zbx_vector_vmware_perf_entity_ptr_append(&hist_entities, entity);
		else
			zbx_vector_vmware_perf_entity_ptr_append(&entities, entity);
	}

	zbx_vmware_unlock();

	vmware_service_retrieve_perf_counters(service, easyhandle, &entities, ZBX_MAXQUERYMETRICS_UNLIMITED, &perfdata);
	vmware_service_retrieve_perf_counters(service, easyhandle, &hist_entities, service->data->max_query_metrics,
			&perfdata);

	if (SUCCEED != vmware_service_logout(service, easyhandle, &error))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot close vmware connection: %s.", error);
		zbx_free(error);
	}

	ret = SUCCEED;
clean:
	curl_slist_free_all(headers);
	curl_easy_cleanup(easyhandle);
	zbx_free(page.data);
out:
	zbx_vmware_lock();

	if (FAIL == ret)
	{
		zbx_hashset_iter_reset(&service->entities, &iter);
		while (NULL != (entity = zbx_hashset_iter_next(&iter)))
			entity->error = vmware_shared_strdup(error);

		zbx_free(error);
	}
	else if (vmware_shmem_get_vmware_mem()->free_size > (perf_data_sz = vmware_perf_data_shmem_size(&perfdata)))
	{
		/* clean old performance data and copy the new data into shared memory */
		vmware_entities_shared_clean_stats(&service->entities);
		vmware_service_copy_perf_data(service, &perfdata);

		if (0 == (service->state & ZBX_VMWARE_STATE_SHMEM_READY))
			service->state |= ZBX_VMWARE_STATE_SHMEM_READY;
	}
	else if (0 == (service->state & ZBX_VMWARE_STATE_SHMEM_READY))
	{
		zabbix_log(LOG_LEVEL_WARNING, "There is not enough VMware shared memory. Performance counters require"
				" up to " ZBX_FS_UI64 " bytes of free VMwareCache memory. Available " ZBX_FS_UI64
				" bytes. Increase value of VMwareCacheSize", perf_data_sz,
				vmware_shmem_get_vmware_mem()->free_size);
		exit(EXIT_SUCCESS);
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "Postponed VMware performance counters require up to " ZBX_FS_UI64
				" bytes of free VMwareCache memory. Available " ZBX_FS_UI64 " bytes."
				" Reading performance counters skipped", perf_data_sz,
				vmware_shmem_get_vmware_mem()->free_size);
	}

	zbx_vmware_unlock();

	zbx_vector_perf_available_ptr_clear_ext(&perf_available, vmware_perf_available_free);
	zbx_vector_perf_available_ptr_destroy(&perf_available);

	zbx_vector_vmware_perf_data_ptr_clear_ext(&perfdata, vmware_free_perfdata);
	zbx_vector_vmware_perf_data_ptr_destroy(&perfdata);

	zbx_vector_vmware_perf_entity_ptr_destroy(&hist_entities);
	zbx_vector_vmware_perf_entity_ptr_destroy(&entities);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s \tprocessed " ZBX_FS_SIZE_T " bytes of data."
			" Performance counters require up to " ZBX_FS_UI64  " bytes of data.", __func__,
			zbx_result_string(ret), (zbx_fs_size_t)page.alloc, perf_data_sz);

	return ret;
#undef INIT_PERF_XML_SIZE
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets vmware performance counter id and unit info by path          *
 *                                                                            *
 * Parameters: service   - [IN] vmware service                                *
 *             path      - [IN] path of counter to retrieve in format         *
 *                              <group>/<key>[<rollup type>]                  *
 *             counterid - [OUT]                                              *
 *             unit      - [OUT] counter unit info (kilo, mega, % etc)        *
 *                                                                            *
 * Return value: SUCCEED if counter was found, FAIL otherwise                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_vmware_service_get_counterid(const zbx_vmware_service_t *service, const char *path,
		zbx_uint64_t *counterid, int *unit)
{
#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)
	zbx_vmware_counter_t	*counter;
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() path:%s", __func__, path);

	if (NULL == (counter = (zbx_vmware_counter_t *)zbx_hashset_search(&service->counters, &path)))
		goto out;

	*counterid = counter->id;

	if (NULL != unit)
		*unit = counter->unit;

	zabbix_log(LOG_LEVEL_DEBUG, "%s() counterid:" ZBX_FS_UI64, __func__, *counterid);

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
#else
	return FAIL;
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: starts monitoring performance counter of specified entity         *
 *                                                                            *
 * Parameters: service   - [IN] vmware service                                *
 *             type      - [IN] entity type                                   *
 *             id        - [IN] entity id                                     *
 *             counterid - [IN] performance counter id                        *
 *             instance  - [IN] performance counter instance name             *
 *                                                                            *
 * Return value: SUCCEED - entity counter was added to monitoring list        *
 *               FAIL    - performance counter of specified entity is already *
 *                         being monitored.                                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_vmware_service_add_perf_counter(zbx_vmware_service_t *service, const char *type, const char *id,
		zbx_uint64_t counterid, const char *instance)
{
	zbx_vmware_perf_entity_t	*pentity, entity;
	zbx_vmware_perf_counter_t	*counter;
	int				i, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() type:%s id:%s counterid:" ZBX_FS_UI64, __func__, type, id,
			counterid);

	if (NULL == (pentity = zbx_vmware_service_get_perf_entity(service, type, id)))
	{
		if (vmware_shmem_get_vmware_mem()->free_size < vmware_perf_entity_shmem_size(type, id, instance))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() Adding of performance counter has been postponed."
					" type:%s id:%s counterid:" ZBX_FS_UI64 " instance:%s",
					__func__, type, id, counterid, instance);
			return SUCCEED;
		}

		entity.refresh = ZBX_VMWARE_PERF_INTERVAL_UNKNOWN;
		entity.last_seen = 0;
		entity.query_instance = vmware_shared_strdup(instance);
		entity.type = vmware_shared_strdup(type);
		entity.id = vmware_shared_strdup(id);
		entity.error = NULL;
		vmware_shmem_vector_vmware_perf_counter_ptr_create_ext(&entity.counters);

		pentity = (zbx_vmware_perf_entity_t *)zbx_hashset_insert(&service->entities, &entity,
				sizeof(zbx_vmware_perf_entity_t));
	}

	zbx_vmware_perf_counter_t	loc = {.counterid = counterid};

	if (FAIL == (i = zbx_vector_vmware_perf_counter_ptr_search(&pentity->counters, &loc,
			ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
	{
		vmware_perf_counters_add_new(&pentity->counters, counterid,
				ZBX_VMWARE_COUNTER_NEW | ZBX_VMWARE_COUNTER_CUSTOM);
		counter = (zbx_vmware_perf_counter_t *)pentity->counters.values[pentity->counters.values_num - 1];
		zbx_vector_vmware_perf_counter_ptr_sort(&pentity->counters, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

		ret = SUCCEED;
	}
	else
		counter = (zbx_vmware_perf_counter_t *)pentity->counters.values[i];

	if (*ZBX_VMWARE_PERF_QUERY_ALL != *pentity->query_instance)
		counter->query_instance = vmware_shared_strdup(instance);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s counter state:%X", __func__, zbx_result_string(ret),
			counter->state);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets performance entity by type and id                            *
 *                                                                            *
 * Parameters: service - [IN] vmware service                                  *
 *             type    - [IN] performance entity type                         *
 *             id      - [IN] performance entity id                           *
 *                                                                            *
 * Return value: performance entity or NULL if not found                      *
 *                                                                            *
 ******************************************************************************/
zbx_vmware_perf_entity_t	*zbx_vmware_service_get_perf_entity(const zbx_vmware_service_t *service,
		const char *type, const char *id)
{
	zbx_vmware_perf_entity_t	*pentity, entity = {.type = (char *)type, .id = (char *)id};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() type:%s id:%s", __func__, type, id);

	pentity = (zbx_vmware_perf_entity_t *)zbx_hashset_search(&service->entities, &entity);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() entity:%p", __func__, (void *)pentity);

	return pentity;
}

#endif	/* defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL) */
