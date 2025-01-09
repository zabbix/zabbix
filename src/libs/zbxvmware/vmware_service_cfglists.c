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

#include "vmware_service_cfglists.h"

#include "zbxcommon.h"

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

#include "zbxvmware.h"

#include "vmware_internal.h"

#include "zbxxml.h"
#ifdef HAVE_LIBXML2
#	include <libxml/xpath.h>
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves list of vmware service datacenters                      *
 *                                                                            *
 * Parameters:                                                                *
 *             service      - [IN]                                            *
 *             easyhandle   - [IN]                                            *
 *             doc          - [IN] xml document                               *
 *             alarms_data  - [IN]                                            *
 *             datacenters  - [OUT] list of vmware datacenters                *
 *                                                                            *
 * Return value: SUCCEED - operation has completed successfully               *
 *               FAIL    - operation has failed                               *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_datacenters_list(const zbx_vmware_service_t *service, CURL *easyhandle, xmlDoc *doc,
		zbx_vmware_alarms_data_t *alarms_data, zbx_vector_vmware_datacenter_ptr_t *datacenters)
{
	xmlXPathContext		*xpathCtx;
	xmlXPathObject		*xpathObj;
	xmlNodeSetPtr		nodeset;
	char			*id, *name;
	zbx_vmware_datacenter_t	*datacenter;
	int			ret = FAIL;

	if (NULL == doc)
		return ret;

	xpathCtx = xmlXPathNewContext(doc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)ZBX_XPATH_OBJECTS_BY_TYPE(ZBX_VMWARE_SOAP_DC),
			xpathCtx)))
	{
		goto out;
	}

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
	{
		xmlXPathFreeObject(xpathObj);
		goto out;
	}

	nodeset = xpathObj->nodesetval;
	zbx_vector_vmware_datacenter_ptr_reserve(datacenters, (size_t)nodeset->nodeNr);

	for (int i = 0; i < nodeset->nodeNr; i++)
	{
		char	*error = NULL;

		if (NULL == (id = zbx_xml_node_read_value(doc, nodeset->nodeTab[i], ZBX_XPATH_NN("obj"))))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): Cannot get datacenter id.", __func__);
			continue;
		}

		if (NULL == (name = zbx_xml_node_read_value(doc, nodeset->nodeTab[i],
				ZBX_XPATH_PROP_NAME_NODE("name"))))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): Cannot get datacenter name for id: %s.", __func__, id);
			zbx_free(id);
			continue;
		}

		datacenter = (zbx_vmware_datacenter_t *)zbx_malloc(NULL, sizeof(zbx_vmware_datacenter_t));
		datacenter->id = id;
		datacenter->name = name;
		zbx_vector_str_create(&datacenter->alarm_ids);

		if (FAIL == vmware_service_get_alarms_data(__func__, service, easyhandle, doc, zbx_xml_node_get(doc,
				nodeset->nodeTab[i], ZBX_XPATH_PROP_NAME_NODE("triggeredAlarmState")),
				&datacenter->alarm_ids, alarms_data, &error))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): Cannot get datacenter %s alarms: %s.", __func__,
					datacenter->id, error);
			zbx_str_free(error);
		}

		zbx_vector_vmware_datacenter_ptr_append(datacenters, datacenter);
	}

	zbx_vector_vmware_datacenter_ptr_sort(datacenters, zbx_vmware_dc_id_compare);

	ret = SUCCEED;
	xmlXPathFreeObject(xpathObj);
out:
	xmlXPathFreeContext(xpathCtx);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves list of vmware service DVSwitch                         *
 *                                                                            *
 * Parameters: doc         - [IN] xml document                                *
 *             dvswitches  - [OUT] list of vmware DVSwitch                    *
 *                                                                            *
 * Return value: SUCCEED - operation has completed successfully               *
 *               FAIL    - operation has failed                               *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_dvswitch_list(xmlDoc *doc, zbx_vector_vmware_dvswitch_ptr_t *dvswitches)
{
	char			*id, *name, *uuid;
	int			ret = FAIL;
	xmlXPathContext		*xpathCtx;
	xmlXPathObject		*xpathObj;
	xmlNodeSetPtr		nodeset;
	zbx_vmware_dvswitch_t	*dvswitch;

	if (NULL == doc)
		return ret;

	xpathCtx = xmlXPathNewContext(doc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)ZBX_XPATH_OBJECTS_BY_TYPE(ZBX_VMWARE_SOAP_DVS),
			xpathCtx)))
	{
		goto out;
	}

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
	{
		xmlXPathFreeObject(xpathObj);
		goto out;
	}

	nodeset = xpathObj->nodesetval;
	zbx_vector_vmware_dvswitch_ptr_reserve(dvswitches, (size_t)nodeset->nodeNr);

	for (int i = 0; i < nodeset->nodeNr; i++)
	{
		if (NULL == (id = zbx_xml_node_read_value(doc, nodeset->nodeTab[i], ZBX_XPATH_NN("obj"))))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): Cannot get DVSwitch id.", __func__);
			continue;
		}

		if (NULL == (name = zbx_xml_node_read_value(doc, nodeset->nodeTab[i],
				ZBX_XPATH_PROP_NAME_NODE("name"))))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): Cannot get DVSwitch name for id: %s.", __func__, id);
			zbx_free(id);
			continue;
		}

		if (NULL == (uuid = zbx_xml_node_read_value(doc, nodeset->nodeTab[i],
				ZBX_XPATH_PROP_NAME_NODE("uuid"))))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): Cannot get DVSwitch uuid for id: %s.", __func__, id);
			zbx_free(name);
			zbx_free(id);
			continue;
		}

		dvswitch = (zbx_vmware_dvswitch_t *)zbx_malloc(NULL, sizeof(zbx_vmware_dvswitch_t));
		dvswitch->id = id;
		dvswitch->name = name;
		dvswitch->uuid = uuid;
		zbx_vector_vmware_dvswitch_ptr_append(dvswitches, dvswitch);
	}

	zbx_vector_vmware_dvswitch_ptr_sort(dvswitches, ZBX_DEFAULT_STR_COMPARE_FUNC);

	ret = SUCCEED;
	xmlXPathFreeObject(xpathObj);
out:
	xmlXPathFreeContext(xpathCtx);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves list of all vmware service hypervisor ids               *
 *                                                                            *
 * Parameters: service      - [IN] vmware service                             *
 *             easyhandle   - [IN] CURL handle                                *
 *             alarms_data  - [IN/OUT] list of vmware alarms                  *
 *             hvs          - [OUT] list of vmware hypervisor ids             *
 *             dss          - [OUT] list of vmware datastore ids              *
 *             datacenters  - [OUT] list of vmware datacenters                *
 *             dvswitches   - [OUT] list of vmware DVSwitch                   *
 *             vc_alarm_ids - [OUT] list of vc alarms id                      *
 *             error        - [OUT] error message in case of failure          *
 *                                                                            *
 * Return value: SUCCEED - operation has completed successfully               *
 *               FAIL    - operation has failed                               *
 *                                                                            *
 ******************************************************************************/
int	vmware_service_get_hv_ds_dc_dvs_list(const zbx_vmware_service_t *service, CURL *easyhandle,
		zbx_vmware_alarms_data_t *alarms_data, zbx_vector_str_t *hvs, zbx_vector_str_t *dss,
		zbx_vector_vmware_datacenter_ptr_t *datacenters, zbx_vector_vmware_dvswitch_ptr_t *dvswitches,
		zbx_vector_str_t *vc_alarm_ids, char **error)
{
#	define ZBX_POST_VCENTER_HV_DS_LIST							\
		ZBX_POST_VSPHERE_HEADER								\
		"<ns0:RetrievePropertiesEx xsi:type=\"ns0:RetrievePropertiesExRequestType\">"	\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"			\
			"<ns0:specSet>"								\
				"<ns0:propSet>"							\
					"<ns0:type>Folder</ns0:type>"				\
					"<ns0:pathSet>triggeredAlarmState</ns0:pathSet>"	\
				"</ns0:propSet>"						\
				"<ns0:propSet>"							\
					"<ns0:type>HostSystem</ns0:type>"			\
				"</ns0:propSet>"						\
				"<ns0:propSet>"							\
					"<ns0:type>Datastore</ns0:type>"			\
				"</ns0:propSet>"						\
				"<ns0:propSet>"							\
					"<ns0:type>Datacenter</ns0:type>"			\
					"<ns0:pathSet>name</ns0:pathSet>"			\
					"<ns0:pathSet>triggeredAlarmState</ns0:pathSet>"	\
				"</ns0:propSet>"						\
				"<ns0:propSet>"							\
					"<ns0:type>VmwareDistributedVirtualSwitch</ns0:type>"	\
					"<ns0:pathSet>name</ns0:pathSet>"			\
					"<ns0:pathSet>uuid</ns0:pathSet>"			\
				"</ns0:propSet>"						\
				"<ns0:objectSet>"						\
					"<ns0:obj type=\"Folder\">%s</ns0:obj>"			\
					"<ns0:skip>false</ns0:skip>"				\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>visitFolders</ns0:name>"		\
						"<ns0:type>Folder</ns0:type>"			\
						"<ns0:path>childEntity</ns0:path>"		\
						"<ns0:skip>false</ns0:skip>"			\
						"<ns0:selectSet>"				\
							"<ns0:name>visitFolders</ns0:name>"	\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>dcToHf</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>dcToNf</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>dcToVmf</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>crToH</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>crToRp</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>dcToDs</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>hToVm</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>rpToVm</ns0:name>"		\
						"</ns0:selectSet>"				\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>dcToVmf</ns0:name>"			\
						"<ns0:type>Datacenter</ns0:type>"		\
						"<ns0:path>vmFolder</ns0:path>"			\
						"<ns0:skip>false</ns0:skip>"			\
						"<ns0:selectSet>"				\
							"<ns0:name>visitFolders</ns0:name>"	\
						"</ns0:selectSet>"				\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>dcToDs</ns0:name>"			\
						"<ns0:type>Datacenter</ns0:type>"		\
						"<ns0:path>datastore</ns0:path>"		\
						"<ns0:skip>false</ns0:skip>"			\
						"<ns0:selectSet>"				\
							"<ns0:name>visitFolders</ns0:name>"	\
						"</ns0:selectSet>"				\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>dcToHf</ns0:name>"			\
						"<ns0:type>Datacenter</ns0:type>"		\
						"<ns0:path>hostFolder</ns0:path>"		\
						"<ns0:skip>false</ns0:skip>"			\
						"<ns0:selectSet>"				\
							"<ns0:name>visitFolders</ns0:name>"	\
						"</ns0:selectSet>"				\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>dcToNf</ns0:name>"			\
						"<ns0:type>Datacenter</ns0:type>"		\
						"<ns0:path>networkFolder</ns0:path>"		\
						"<ns0:skip>false</ns0:skip>"			\
						"<ns0:selectSet>"				\
							"<ns0:name>visitFolders</ns0:name>"	\
						"</ns0:selectSet>"				\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>crToH</ns0:name>"			\
						"<ns0:type>ComputeResource</ns0:type>"		\
						"<ns0:path>host</ns0:path>"			\
						"<ns0:skip>false</ns0:skip>"			\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>crToRp</ns0:name>"			\
						"<ns0:type>ComputeResource</ns0:type>"		\
						"<ns0:path>resourcePool</ns0:path>"		\
						"<ns0:skip>false</ns0:skip>"			\
						"<ns0:selectSet>"				\
							"<ns0:name>rpToRp</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>rpToVm</ns0:name>"		\
						"</ns0:selectSet>"				\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>rpToRp</ns0:name>"			\
						"<ns0:type>ResourcePool</ns0:type>"		\
						"<ns0:path>resourcePool</ns0:path>"		\
						"<ns0:skip>false</ns0:skip>"			\
						"<ns0:selectSet>"				\
							"<ns0:name>rpToRp</ns0:name>"		\
						"</ns0:selectSet>"				\
						"<ns0:selectSet>"				\
							"<ns0:name>rpToVm</ns0:name>"		\
						"</ns0:selectSet>"				\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>hToVm</ns0:name>"			\
						"<ns0:type>HostSystem</ns0:type>"		\
						"<ns0:path>vm</ns0:path>"			\
						"<ns0:skip>false</ns0:skip>"			\
						"<ns0:selectSet>"				\
							"<ns0:name>visitFolders</ns0:name>"	\
						"</ns0:selectSet>"				\
					"</ns0:selectSet>"					\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"	\
						"<ns0:name>rpToVm</ns0:name>"			\
						"<ns0:type>ResourcePool</ns0:type>"		\
						"<ns0:path>vm</ns0:path>"			\
						"<ns0:skip>false</ns0:skip>"			\
					"</ns0:selectSet>"					\
				"</ns0:objectSet>"						\
			"</ns0:specSet>"							\
			"<ns0:options/>"							\
		"</ns0:RetrievePropertiesEx>"							\
		ZBX_POST_VSPHERE_FOOTER

#define ZBX_XPATH_GET_ROOT_ALARMS(object, id)							\
		ZBX_XPATH_PROP_OBJECT_ID(object, "[text()='" id "']") "/"			\
		ZBX_XPATH_PROP_NAME_NODE("triggeredAlarmState")

	char				tmp[MAX_STRING_LEN * 2];
	const char			*pcollector = get_vmware_service_objects()[service->type].property_collector;
	int				ret = FAIL;
	xmlDoc				*doc = NULL;
	xmlNode				*vc_alarms;
	zbx_property_collection_iter	*iter = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VCENTER_HV_DS_LIST, pcollector,
			get_vmware_service_objects()[service->type].root_folder);

	if (SUCCEED != zbx_property_collection_init(easyhandle, tmp, pcollector, __func__, &iter, &doc, error))
		goto out;

	if (ZBX_VMWARE_TYPE_VCENTER == service->type)
		zbx_xml_read_values(doc, ZBX_XPATH_OBJS_BY_TYPE(ZBX_VMWARE_SOAP_HV) , hvs);
	else
		zbx_vector_str_append(hvs, zbx_strdup(NULL, "ha-host"));

	zbx_xml_read_values(doc, ZBX_XPATH_OBJS_BY_TYPE(ZBX_VMWARE_SOAP_DS), dss);
	vmware_service_get_datacenters_list(service, easyhandle, doc, alarms_data, datacenters);
	vmware_service_get_dvswitch_list(doc, dvswitches);
	zbx_snprintf(tmp, sizeof(tmp), ZBX_XPATH_GET_ROOT_ALARMS(ZBX_VMWARE_SOAP_FOLDER, "%s"),
			get_vmware_service_objects()[service->type].root_folder);

	if (NULL != (vc_alarms = zbx_xml_doc_get(doc, tmp)) && FAIL == vmware_service_get_alarms_data(__func__,
			service, easyhandle, doc, vc_alarms, vc_alarm_ids, alarms_data, error))
	{
		goto out;
	}

	while (NULL != iter->token)
	{
		zbx_xml_doc_free(doc);

		if (SUCCEED != zbx_property_collection_next(__func__, iter, &doc, error))
			goto out;

		if (ZBX_VMWARE_TYPE_VCENTER == service->type)
			zbx_xml_read_values(doc, ZBX_XPATH_OBJS_BY_TYPE(ZBX_VMWARE_SOAP_HV), hvs);

		zbx_xml_read_values(doc, ZBX_XPATH_OBJS_BY_TYPE(ZBX_VMWARE_SOAP_DS), dss);
		vmware_service_get_datacenters_list(service, easyhandle, doc, alarms_data, datacenters);
		vmware_service_get_dvswitch_list(doc, dvswitches);

		if (NULL != (vc_alarms = zbx_xml_doc_get(doc, tmp)) && FAIL == vmware_service_get_alarms_data(__func__,
				service, easyhandle, doc, vc_alarms, vc_alarm_ids, alarms_data, error))
		{
			goto out;
		}
	}

	ret = SUCCEED;
out:
	zbx_property_collection_free(iter);
	zbx_xml_doc_free(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s found hv:%d ds:%d dc:%d", __func__, zbx_result_string(ret),
			hvs->values_num, dss->values_num, datacenters->values_num);

	return ret;

#	undef ZBX_XPATH_GET_ROOT_ALARMS
#	undef ZBX_POST_VCENTER_HV_DS_LIST
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves list of vmware service datastore diskextents            *
 *                                                                            *
 * Parameters: doc         - [IN] xml document                                *
 *             diskextents - [OUT] list of vmware diskextents                 *
 *                                                                            *
 * Return value: SUCCEED - operation has completed successfully               *
 *               FAIL    - operation has failed                               *
 *                                                                            *
 ******************************************************************************/
int	vmware_service_get_diskextents_list(xmlDoc *doc, zbx_vector_vmware_diskextent_ptr_t *diskextents)
{
#	define ZBX_XPATH_DS_INFO_EXTENT()								\
		ZBX_XPATH_PROP_NAME("info") "/*/*[local-name()='extent']"

	xmlXPathContext		*xpathCtx;
	xmlXPathObject		*xpathObj;
	xmlNodeSetPtr		nodeset;
	int			ret = FAIL;

	if (NULL == doc)
		return ret;

	xpathCtx = xmlXPathNewContext(doc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *) ZBX_XPATH_DS_INFO_EXTENT(), xpathCtx)))
		goto out;

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto out;

	nodeset = xpathObj->nodesetval;

	for (int i = 0; i < nodeset->nodeNr; i++)
	{
		char			*value;
		zbx_vmware_diskextent_t	*diskextent;
		xmlNode			*xn = nodeset->nodeTab[i];

		if (NULL == (value = zbx_xml_node_read_value(doc, xn, ZBX_XPATH_NN("diskName"))))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): Cannot get diskName.", __func__);
			continue;
		}

		diskextent = (zbx_vmware_diskextent_t *)zbx_malloc(NULL, sizeof(zbx_vmware_diskextent_t));
		diskextent->diskname = value;

		if (NULL != (value = zbx_xml_node_read_value(doc, xn, ZBX_XPATH_NN("partition"))))
		{
			diskextent->partitionid = (unsigned int) atoi(value);
			zbx_free(value);
		}
		else
			diskextent->partitionid = 0;

		zbx_vector_vmware_diskextent_ptr_append(diskextents, diskextent);
	}

	zbx_vector_vmware_diskextent_ptr_sort(diskextents, ZBX_DEFAULT_STR_PTR_COMPARE_FUNC);

	ret = SUCCEED;
out:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);

	return ret;

#	undef ZBX_XPATH_DS_INFO_EXTENT
}
#endif /* defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL) */
