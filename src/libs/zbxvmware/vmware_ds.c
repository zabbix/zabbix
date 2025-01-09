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

#include "vmware_ds.h"

#include "zbxcommon.h"

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

#include "vmware_internal.h"
#include "vmware_service_cfglists.h"

#include "zbxnum.h"

#include "zbxxml.h"
#ifdef HAVE_LIBXML2
#	include <libxml/xpath.h>
#endif

ZBX_PTR_VECTOR_IMPL(vmware_datastore_ptr, zbx_vmware_datastore_t *)

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store datastore name data            *
 *                                                                            *
 * Parameters: dsname - [IN] datastore name                                   *
 *                                                                            *
 ******************************************************************************/
void	vmware_dsname_free(zbx_vmware_dsname_t *dsname)
{
	zbx_vector_vmware_hvdisk_destroy(&dsname->hvdisks);
	zbx_free(dsname->name);
	zbx_free(dsname->uuid);
	zbx_free(dsname);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store diskextent data                *
 *                                                                            *
 * Parameters: diskextent - [IN]                                              *
 *                                                                            *
 ******************************************************************************/
static void	vmware_diskextent_free(zbx_vmware_diskextent_t *diskextent)
{
	zbx_free(diskextent->diskname);
	zbx_free(diskextent);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees memory of vector element                                    *
 *                                                                            *
 ******************************************************************************/
static void	zbx_str_uint64_pair_free(zbx_str_uint64_pair_t data)
{
	zbx_free(data.name);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store datastore data                 *
 *                                                                            *
 * Parameters: datastore - [IN]                                               *
 *                                                                            *
 ******************************************************************************/
void	vmware_datastore_free(zbx_vmware_datastore_t *datastore)
{
	zbx_vector_str_uint64_pair_clear_ext(&datastore->hv_uuids_access, zbx_str_uint64_pair_free);
	zbx_vector_str_uint64_pair_destroy(&datastore->hv_uuids_access);

	zbx_vector_vmware_diskextent_ptr_clear_ext(&datastore->diskextents, vmware_diskextent_free);
	zbx_vector_vmware_diskextent_ptr_destroy(&datastore->diskextents);

	zbx_vector_str_clear_ext(&datastore->alarm_ids, zbx_str_free);
	zbx_vector_str_destroy(&datastore->alarm_ids);

	zbx_free(datastore->name);
	zbx_free(datastore->uuid);
	zbx_free(datastore->id);
	zbx_free(datastore->type);
	zbx_free(datastore);
}

/******************************************************************************
 *                                                                            *
 * Purpose: finds DS by canonical disk name (perf counter instance)           *
 *                                                                            *
 * Parameters: dss      - [IN] all known datastores                           *
 *             diskname - [IN] canonical disk name                            *
 *                                                                            *
 * Return value: uuid of datastore or NULL                                    *
 *                                                                            *
 ******************************************************************************/
char	*vmware_datastores_diskname_search(const zbx_vector_vmware_datastore_ptr_t *dss, char *diskname)
{
	zbx_vmware_diskextent_t	dx_cmp = {.diskname = diskname};

	for (int i = 0; i< dss->values_num; i++)
	{
		zbx_vmware_datastore_t	*ds = dss->values[i];

		if (FAIL == zbx_vector_vmware_diskextent_ptr_bsearch(&ds->diskextents, &dx_cmp,
				ZBX_DEFAULT_STR_PTR_COMPARE_FUNC))
		{
			continue;
		}

		return zbx_strdup(NULL, ds->uuid);
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sorting function to sort datastore vector by uuid                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_vmware_ds_uuid_compare(const void *d1, const void *d2)
{
	const zbx_vmware_datastore_t	*ds1 = *(const zbx_vmware_datastore_t * const *)d1;
	const zbx_vmware_datastore_t	*ds2 = *(const zbx_vmware_datastore_t * const *)d2;

	return strcmp(ds1->uuid, ds2->uuid);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sorting function to sort datastore vector by name                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_vmware_ds_name_compare(const void *d1, const void *d2)
{
	const zbx_vmware_datastore_t	*ds1 = *(const zbx_vmware_datastore_t * const *)d1;
	const zbx_vmware_datastore_t	*ds2 = *(const zbx_vmware_datastore_t * const *)d2;

	return strcmp(ds1->name, ds2->name);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sorting function to sort datastore vector by id                   *
 *                                                                            *
 ******************************************************************************/
int	vmware_ds_id_compare(const void *d1, const void *d2)
{
	const zbx_vmware_datastore_t	*ds1 = *(const zbx_vmware_datastore_t * const *)d1;
	const zbx_vmware_datastore_t	*ds2 = *(const zbx_vmware_datastore_t * const *)d2;

	return strcmp(ds1->id, ds2->id);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Refreshes all storage related information including free-space,   *
 *          capacity, and detailed usage of virtual machines.                 *
 *                                                                            *
 * Parameters: easyhandle   - [IN] CURL handle                                *
 *             id           - [IN] datastore id                               *
 *             error        - [OUT] error message in case of failure          *
 *                                                                            *
 * Comments: This is required for ESX/ESXi hosts version < 6.0 only.          *
 *                                                                            *
 ******************************************************************************/
int	vmware_service_refresh_datastore_info(CURL *easyhandle, const char *id, char **error)
{
#	define ZBX_POST_REFRESH_DATASTORE							\
		ZBX_POST_VSPHERE_HEADER								\
		"<ns0:RefreshDatastoreStorageInfo>"						\
			"<ns0:_this type=\"Datastore\">%s</ns0:_this>"				\
		"</ns0:RefreshDatastoreStorageInfo>"						\
		ZBX_POST_VSPHERE_FOOTER

	char		tmp[MAX_STRING_LEN];
	int		ret = FAIL;

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_REFRESH_DATASTORE, id);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, NULL, NULL, error))
		goto out;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
#	undef ZBX_POST_REFRESH_DATASTORE
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates vmware hypervisor datastore object                        *
 *                                                                            *
 * Parameters: service     - [IN] vmware service                              *
 *             easyhandle  - [IN] CURL handle                                 *
 *             id          - [IN] datastore id                                *
 *             cq_values   - [IN/OUT] custom query values                     *
 *             alarms_data - [IN/OUT] all alarms with cache                   *
 *                                                                            *
 * Return value: The created datastore object or NULL if an error was         *
 *               detected.                                                    *
 *                                                                            *
 ******************************************************************************/
zbx_vmware_datastore_t	*vmware_service_create_datastore(const zbx_vmware_service_t *service, CURL *easyhandle,
		const char *id, zbx_vector_cq_value_ptr_t *cq_values, zbx_vmware_alarms_data_t *alarms_data)
{
#define ZBX_VMWARE_DS_REFRESH_VERSION	6

#	define ZBX_XPATH_DATASTORE_SUMMARY(property)			\
	"/*/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='summary']]"	\
		"/*[local-name()='val']/*[local-name()='" property "']"

#	define ZBX_POST_DATASTORE_GET								\
		ZBX_POST_VSPHERE_HEADER								\
		"<ns0:RetrievePropertiesEx>"							\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"			\
			"<ns0:specSet>"								\
				"<ns0:propSet>"							\
					"<ns0:type>Datastore</ns0:type>"			\
					"<ns0:pathSet>summary</ns0:pathSet>"			\
					"<ns0:pathSet>info</ns0:pathSet>"			\
					"%s"							\
					"<ns0:pathSet>triggeredAlarmState</ns0:pathSet>"	\
				"</ns0:propSet>"						\
				"<ns0:objectSet>"						\
					"<ns0:obj type=\"Datastore\">%s</ns0:obj>"		\
				"</ns0:objectSet>"						\
			"</ns0:specSet>"							\
			"<ns0:options/>"							\
		"</ns0:RetrievePropertiesEx>"							\
		ZBX_POST_VSPHERE_FOOTER

	char				*tmp, *cq_prop, *uuid = NULL, *name = NULL, *path, *id_esc, *value,
					*error = NULL;
	int				ret;
	zbx_vmware_datastore_t		*datastore = NULL;
	zbx_uint64_t			capacity = ZBX_MAX_UINT64, free_space = ZBX_MAX_UINT64,
					uncommitted = ZBX_MAX_UINT64;
	xmlDoc				*doc = NULL;
	zbx_vector_cq_value_ptr_t	cqvs;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() datastore:'%s'", __func__, id);

	zbx_vector_cq_value_ptr_create(&cqvs);
	id_esc = zbx_xml_escape_dyn(id);

	if (ZBX_VMWARE_TYPE_VSPHERE == service->type && NULL != service->version &&
			ZBX_VMWARE_DS_REFRESH_VERSION > service->major_version && SUCCEED !=
			vmware_service_refresh_datastore_info(easyhandle, id_esc, &error))
	{
		zbx_free(id_esc);
		goto out;
	}

	cq_prop = vmware_cq_prop_soap_request(cq_values, ZBX_VMWARE_SOAP_DS, id, &cqvs);
	tmp = zbx_dsprintf(NULL, ZBX_POST_DATASTORE_GET, get_vmware_service_objects()[service->type].property_collector,
			cq_prop, id_esc);
	zbx_str_free(id_esc);
	zbx_str_free(cq_prop);
	ret = zbx_soap_post(__func__, easyhandle, tmp, &doc, NULL, &error);
	zbx_str_free(tmp);

	if (FAIL == ret)
		goto out;

	name = zbx_xml_doc_read_value(doc, ZBX_XPATH_DATASTORE_SUMMARY("name"));

	if (NULL != (path = zbx_xml_doc_read_value(doc, ZBX_XPATH_DATASTORE_SUMMARY("url"))))
	{
		if ('\0' != *path)
		{
			size_t	len;
			char	*ptr;

			len = strlen(path);

			if ('/' == path[len - 1])
				path[len - 1] = '\0';

			for (ptr = path + len - 2; ptr > path && *ptr != '/' && *ptr != ':'; ptr--)
				;

			uuid = zbx_strdup(NULL, ptr + 1);
		}
		zbx_free(path);
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() datastore uuid not present for id:'%s'", __func__, id);
		zbx_free(name);
		goto out;
	}

	if (ZBX_VMWARE_TYPE_VSPHERE == service->type)
	{
		if (NULL != (value = zbx_xml_doc_read_value(doc, ZBX_XPATH_DATASTORE_SUMMARY("capacity"))))
		{
			zbx_is_uint64(value, &capacity);
			zbx_free(value);
		}

		if (NULL != (value = zbx_xml_doc_read_value(doc, ZBX_XPATH_DATASTORE_SUMMARY("freeSpace"))))
		{
			zbx_is_uint64(value, &free_space);
			zbx_free(value);
		}

		if (NULL != (value = zbx_xml_doc_read_value(doc, ZBX_XPATH_DATASTORE_SUMMARY("uncommitted"))))
		{
			zbx_is_uint64(value, &uncommitted);
			zbx_free(value);
		}
	}

	datastore = (zbx_vmware_datastore_t *)zbx_malloc(NULL, sizeof(zbx_vmware_datastore_t));
	datastore->name = (NULL != name) ? name : zbx_strdup(NULL, id);
	datastore->uuid = uuid;
	datastore->id = zbx_strdup(NULL, id);
	datastore->type = zbx_xml_doc_read_value(doc, ZBX_XPATH_DATASTORE_SUMMARY("type"));
	datastore->capacity = capacity;
	datastore->free_space = free_space;
	datastore->uncommitted = uncommitted;
	zbx_vector_str_create(&datastore->alarm_ids);
	zbx_vector_str_uint64_pair_create(&datastore->hv_uuids_access);
	zbx_vector_vmware_diskextent_ptr_create(&datastore->diskextents);
	vmware_service_get_diskextents_list(doc, &datastore->diskextents);

	if (0 != cqvs.values_num)
		vmware_service_cq_prop_value(__func__, doc, &cqvs);

	if (FAIL == vmware_service_get_alarms_data(__func__, service, easyhandle, doc, NULL, &datastore->alarm_ids,
			alarms_data, &error))
	{
		vmware_datastore_free(datastore);
		datastore = NULL;
	}
out:
	zbx_xml_doc_free(doc);
	zbx_vector_cq_value_ptr_destroy(&cqvs);

	if (NULL != error)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot get Datastore info: %s.", error);
		zbx_free(error);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return datastore;
#undef ZBX_VMWARE_DS_REFRESH_VERSION

#	undef ZBX_XPATH_DATASTORE_SUMMARY
#	undef ZBX_POST_DATASTORE_GET
}

/******************************************************************************
 *                                                                            *
 * Purpose: populates array of values from xml data                           *
 *                                                                            *
 * Parameters: xdoc    - [IN] xml document                                    *
 *             ds_node - [IN] xml node with datastore info                    *
 *             ds_id   - [IN] datastore id (for logging)                      *
 *                                                                            *
 * Return: bitmap value of HV access mode to DS                               *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	vmware_hv_get_ds_access(xmlDoc *xdoc, xmlNode *ds_node, const char *ds_id)
{
#	define ZBX_XPATH_PROP_SUFFIX(property)									\
		"*[local-name()='propSet'][*[local-name()='name']"						\
		"[substring(text(),string-length(text())-string-length('" property "')+1)='" property "']]"	\
		"/*[local-name()='val']"

	zbx_uint64_t	mi_access = ZBX_VMWARE_DS_NONE;
	char		*value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() for DS:%s", __func__, ds_id);

	if (NULL != (value = zbx_xml_node_read_value(xdoc, ds_node, ZBX_XPATH_PROP_SUFFIX("mounted"))))
	{
		if (0 == strcmp(value, "true"))
			mi_access |= ZBX_VMWARE_DS_MOUNTED;

		zbx_free(value);
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot find item 'mounted' in mountinfo for DS:%s", ds_id);

	if (NULL != (value = zbx_xml_node_read_value(xdoc, ds_node, ZBX_XPATH_PROP_SUFFIX("accessible"))))
	{
		if (0 == strcmp(value, "true"))
			mi_access |= ZBX_VMWARE_DS_ACCESSIBLE;

		zbx_free(value);
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot find item 'accessible' in accessible for DS:%s", ds_id);

	if (NULL != (value = zbx_xml_node_read_value(xdoc, ds_node, ZBX_XPATH_PROP_SUFFIX("accessMode"))))
	{
		if (0 == strcmp(value, "readWrite"))
			mi_access |= ZBX_VMWARE_DS_READWRITE;
		else
			mi_access |= ZBX_VMWARE_DS_READ;

		zbx_free(value);
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot find item 'accessMode' in mountinfo for DS:%s", ds_id);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() mountinfo:" ZBX_FS_UI64, __func__, mi_access);

	return mi_access;
#	undef ZBX_XPATH_PROP_SUFFIX
}

/******************************************************************************
 *                                                                            *
 * Purpose: reads access state of hv to ds                                    *
 *                                                                            *
 * Parameters: xdoc    - [IN] xml data with DS access info                    *
 *             hv_dss  - [IN] vector with all DS connected to HV              *
 *             hv_uuid - [IN] uuid of HV                                      *
 *             hv_id   - [IN] id of HV (for logging)                          *
 *             dss     - [IN/OUT] vector with all datastores                  *
 *                                                                            *
 * Return value: count of updated DS                                          *
 *                                                                            *
 ******************************************************************************/
static int	vmware_hv_ds_access_parse(xmlDoc *xdoc, const zbx_vector_str_t *hv_dss, const char *hv_uuid,
		const char *hv_id, zbx_vector_vmware_datastore_ptr_t *dss)
{
	int		parsed_num = 0;
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	xpathCtx = xmlXPathNewContext(xdoc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((xmlChar *)ZBX_XPATH_OBJECTS_BY_TYPE(ZBX_VMWARE_SOAP_DS),
			xpathCtx)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot make xpath for Datastore list query.");
		goto clean;
	}

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot find Datastores in the list.");
		goto clean;
	}

	nodeset = xpathObj->nodesetval;

	for (int i = 0; i < nodeset->nodeNr; i++)
	{
		int				j;
		char				*value;
		zbx_vmware_datastore_t		*ds, ds_cmp;
		zbx_str_uint64_pair_t		hv_ds_access;

		if (NULL == (value = zbx_xml_node_read_value(xdoc, nodeset->nodeTab[i], ZBX_XPATH_NN("obj"))))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "skipping DS record without ID, xml number '%d'", i);
			continue;
		}

		if (FAIL == (j = zbx_vector_str_bsearch(hv_dss, value, ZBX_DEFAULT_STR_COMPARE_FUNC)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "DS:%s not connected to HV:%s", value, hv_id);
			zbx_str_free(value);
			continue;
		}

		zbx_str_free(value);

		ds_cmp.id = hv_dss->values[j];

		if (FAIL == (j = zbx_vector_vmware_datastore_ptr_bsearch(dss, &ds_cmp, vmware_ds_id_compare)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): Datastore \"%s\" not found on hypervisor \"%s\".", __func__,
					ds_cmp.id, hv_id);
			continue;
		}

		ds = dss->values[j];
		hv_ds_access.name = zbx_strdup(NULL, hv_uuid);
		hv_ds_access.value = vmware_hv_get_ds_access(xdoc, nodeset->nodeTab[i], ds->id);
		zbx_vector_str_uint64_pair_append_ptr(&ds->hv_uuids_access, &hv_ds_access);
		parsed_num++;
	}
clean:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() parsed:%d", __func__, parsed_num);

	return parsed_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates access state of hv to ds                                  *
 *                                                                            *
 * Parameters: service      - [IN] vmware service                             *
 *             easyhandle   - [IN] CURL handle                                *
 *             hv_uuid      - [IN] vmware hypervisor uuid                     *
 *             hv_id        - [IN] vmware hypervisor id                       *
 *             hv_dss       - [IN] vector with all DS connected to HV         *
 *             dss          - [IN/OUT] vector with all datastores             *
 *             error        - [OUT] error message in case of failure          *
 *                                                                            *
 * Return value: SUCCEED - access state was updated successfully              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	vmware_hv_ds_access_update(zbx_vmware_service_t *service, CURL *easyhandle, const char *hv_uuid,
		const char *hv_id, const zbx_vector_str_t *hv_dss, zbx_vector_vmware_datastore_ptr_t *dss, char **error)
{
#	define ZBX_POST_HV_DS_ACCESS 									\
		ZBX_POST_VSPHERE_HEADER									\
		"<ns0:RetrievePropertiesEx>"								\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"				\
			"<ns0:specSet>"									\
				"<ns0:propSet>"								\
					"<ns0:type>Datastore</ns0:type>"				\
					"<ns0:pathSet>host[\"%s\"].mountInfo.mounted</ns0:pathSet>"	\
					"<ns0:pathSet>host[\"%s\"].mountInfo.accessible</ns0:pathSet>"	\
					"<ns0:pathSet>host[\"%s\"].mountInfo.accessMode</ns0:pathSet>"	\
				"</ns0:propSet>"							\
				"<ns0:objectSet>"							\
					"<ns0:obj type=\"HostSystem\">%s</ns0:obj>"			\
					"<ns0:skip>false</ns0:skip>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"		\
						"<ns0:name>DSObject</ns0:name>"				\
						"<ns0:type>HostSystem</ns0:type>"			\
						"<ns0:path>datastore</ns0:path>"			\
						"<ns0:skip>false</ns0:skip>"				\
					"</ns0:selectSet>"						\
				"</ns0:objectSet>"							\
			"</ns0:specSet>"								\
			"<ns0:options/>"								\
		"</ns0:RetrievePropertiesEx>"								\
		ZBX_POST_VSPHERE_FOOTER

	char				*hvid_esc, tmp[MAX_STRING_LEN];
	const char			*pcollector = get_vmware_service_objects()[service->type].property_collector;
	int				ret = FAIL, updated = 0;
	xmlDoc				*doc = NULL;
	zbx_property_collection_iter	*iter = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hv id:%s hv dss:%d dss:%d", __func__, hv_id, hv_dss->values_num,
			dss->values_num);

	hvid_esc = zbx_xml_escape_dyn(hv_id);
	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_HV_DS_ACCESS, pcollector, hvid_esc, hvid_esc, hvid_esc, hvid_esc);
	zbx_free(hvid_esc);

	if (SUCCEED != zbx_property_collection_init(easyhandle, tmp, pcollector, __func__, &iter, &doc, error))
		goto out;

	updated += vmware_hv_ds_access_parse(doc, hv_dss, hv_uuid, hv_id, dss);

	while (NULL != iter->token)
	{
		zbx_xml_doc_free(doc);

		if (SUCCEED != zbx_property_collection_next(__func__, iter, &doc, error))
			goto out;

		updated += vmware_hv_ds_access_parse(doc, hv_dss, hv_uuid, hv_id, dss);
	}

	ret = SUCCEED;
out:
	zbx_property_collection_free(iter);
	zbx_xml_doc_free(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s for %d / %d", __func__, zbx_result_string(ret), updated,
			hv_dss->values_num);

	return ret;

#	undef ZBX_POST_HV_DS_ACCESS
}

/******************************************************************************
 *                                                                            *
 * Purpose: Gets statically defined default value for maxquerymetrics for     *
 *          vcenter when it could not be retrieved from soap, depending on    *
 *          vcenter version (https://kb.vmware.com/s/article/2107096).        *
 *                                                                            *
 * Parameters: service - [IN] vmware service                                  *
 *                                                                            *
 * Return value: maxquerymetrics                                              *
 *                                                                            *
 ******************************************************************************/
static int	get_default_maxquerymetrics_for_vcenter(const zbx_vmware_service_t *service)
{
	if ((6 == service->major_version && 5 <= service->minor_version) ||
			6 < service->major_version)
	{
		return ZBX_VCENTER_6_5_0_AND_MORE_STATS_MAXQUERYMETRICS;
	}
	else
		return ZBX_VCENTER_LESS_THAN_6_5_0_STATS_MAXQUERYMETRICS;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets vpxd.stats.maxquerymetrics parameter from vcenter only       *
 *                                                                            *
 * Parameters: easyhandle - [IN] CURL handle                                  *
 *             service    - [IN] vmware service                               *
 *             max_qm     - [OUT] max count of datastore metrics in one       *
 *                                request                                     *
 *             error      - [OUT] error message in case of failure            *
 *                                                                            *
 * Return value: SUCCEED - operation has completed successfully               *
 *               FAIL    - operation has failed                               *
 *                                                                            *
 ******************************************************************************/
int	vmware_service_get_maxquerymetrics(CURL *easyhandle, zbx_vmware_service_t *service, int *max_qm,
		char **error)
{
#	define ZBX_XPATH_MAXQUERYMETRICS()									\
		"/*/*/*/*[*[local-name()='key']='config.vpxd.stats.maxQueryMetrics']/*[local-name()='value']"

#	define ZBX_POST_MAXQUERYMETRICS										\
		ZBX_POST_VSPHERE_HEADER										\
		"<ns0:QueryOptions>"										\
			"<ns0:_this type=\"OptionManager\">VpxSettings</ns0:_this>"				\
			"<ns0:name>config.vpxd.stats.maxQueryMetrics</ns0:name>"				\
		"</ns0:QueryOptions>"										\
		ZBX_POST_VSPHERE_FOOTER

	int	ret = FAIL;
	char	*val;
	xmlDoc	*doc = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, ZBX_POST_MAXQUERYMETRICS, &doc, NULL, error))
	{
		if (NULL == doc)	/* if not SOAP error */
			goto out;

		zabbix_log(LOG_LEVEL_DEBUG, "Error of query maxQueryMetrics: %s.", *error);
		zbx_free(*error);
	}

	ret = SUCCEED;

	if (NULL == (val = zbx_xml_doc_read_value(doc, ZBX_XPATH_MAXQUERYMETRICS())))
	{
		*max_qm = get_default_maxquerymetrics_for_vcenter(service);
		zabbix_log(LOG_LEVEL_DEBUG, "maxQueryMetrics defaults to %d", *max_qm);
		goto out;
	}

	/* vmware article 2107096                                                                     */
	/* Edit the config.vpxd.stats.maxQueryMetrics key in the advanced settings of vCenter Server. */
	/* To disable the limit, set a value to -1.                                                   */
	/* Edit the web.xml file. To disable the limit, set a value 0.                                */
	if (-1 == atoi(val))
	{
		*max_qm = ZBX_MAXQUERYMETRICS_UNLIMITED;
	}
	else if (SUCCEED != zbx_is_uint31(val, max_qm))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot convert maxQueryMetrics from %s.", val);
		*max_qm = get_default_maxquerymetrics_for_vcenter(service);
		zabbix_log(LOG_LEVEL_DEBUG, "maxQueryMetrics defaults to %d", *max_qm);
	}
	else if (0 == *max_qm)
	{
		*max_qm = ZBX_MAXQUERYMETRICS_UNLIMITED;
	}

	zbx_free(val);
out:
	zbx_xml_doc_free(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef ZBX_POST_MAXQUERYMETRICS
#	undef ZBX_XPATH_MAXQUERYMETRICS
}

#endif /* defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL) */
