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

#include "vmware_ds.h"
#include "vmware_internal.h"

#include "zbxxml.h"
#ifdef HAVE_LIBXML2
#	include <libxml/xpath.h>
#endif

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

ZBX_PTR_VECTOR_IMPL(vmware_datastore, zbx_vmware_datastore_t *)

#define ZBX_XPATH_PROP_SUFFIX(property)									\
	"*[local-name()='propSet'][*[local-name()='name']"						\
	"[substring(text(),string-length(text())-string-length('" property "')+1)='" property "']]"	\
	"/*[local-name()='val']"

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store Datastore name data            *
 *                                                                            *
 * Parameters: dsname   - [IN] the Datastore name                             *
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
 * Purpose: find DS by canonical disk name (perf counter instance)            *
 *                                                                            *
 * Parameters: dss      - [IN] all known Datastores                           *
 *             diskname - [IN] canonical disk name                            *
 *                                                                            *
 * Return value: uuid of Datastore or NULL                                    *
 *                                                                            *
 ******************************************************************************/
char	*vmware_datastores_diskname_search(const zbx_vector_vmware_datastore_t *dss, char *diskname)
{
	zbx_vmware_diskextent_t	dx_cmp = {.diskname = diskname};
	int			i;

	for (i = 0; i< dss->values_num; i++)
	{
		zbx_vmware_datastore_t	*ds = dss->values[i];

		if (FAIL == zbx_vector_vmware_diskextent_bsearch(&ds->diskextents, &dx_cmp,
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
 * Function: vmware_ds_uuid_compare                                           *
 *                                                                            *
 * Purpose: sorting function to sort Datastore vector by uuid                 *
 *                                                                            *
 ******************************************************************************/
int	vmware_ds_uuid_compare(const void *d1, const void *d2)
{
	const zbx_vmware_datastore_t	*ds1 = *(const zbx_vmware_datastore_t * const *)d1;
	const zbx_vmware_datastore_t	*ds2 = *(const zbx_vmware_datastore_t * const *)d2;

	return strcmp(ds1->uuid, ds2->uuid);
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_ds_name_compare                                           *
 *                                                                            *
 * Purpose: sorting function to sort Datastore vector by name                 *
 *                                                                            *
 ******************************************************************************/
int	vmware_ds_name_compare(const void *d1, const void *d2)
{
	const zbx_vmware_datastore_t	*ds1 = *(const zbx_vmware_datastore_t * const *)d1;
	const zbx_vmware_datastore_t	*ds2 = *(const zbx_vmware_datastore_t * const *)d2;

	return strcmp(ds1->name, ds2->name);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sorting function to sort Datastore vector by id                   *
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
 * Purpose: populate array of values from an xml data                         *
 *                                                                            *
 * Parameters: xdoc    - [IN] XML document                                    *
 *             ds_node - [IN] xml node with datastore info                    *
 *             ds_id   - [IN] datastore id (for logging)                      *
 *                                                                            *
 * Return: bitmap value of HV access mode to DS                               *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	vmware_hv_get_ds_access(xmlDoc *xdoc, xmlNode *ds_node, const char *ds_id)
{

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
}

/******************************************************************************
 * Function: vmware_hv_ds_access_parse                                        *
 *                                                                            *
 * Purpose: read access state of hv to ds                                     *
 *                                                                            *
 * Parameters: xdoc    - [IN] the xml data with DS access info                *
 *             hv_dss  - [IN] the vector with all DS connected to HV          *
 *             hv_uuid - [IN] the uuid of HV                                  *
 *             hv_id   - [IN] the id of HV (for logging)                      *
 *             dss     - [IN/OUT] the vector with all Datastores              *
 *                                                                            *
 * Return value: count of updated DS                                          *
 *                                                                            *
 ******************************************************************************/
static int	vmware_hv_ds_access_parse(xmlDoc *xdoc, const zbx_vector_str_t *hv_dss, const char *hv_uuid,
		const char *hv_id, zbx_vector_vmware_datastore_t *dss)
{
	int		i, parsed_num = 0;
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

	for (i = 0; i < nodeset->nodeNr; i++)
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

		if (FAIL == (j = zbx_vector_vmware_datastore_bsearch(dss, &ds_cmp, vmware_ds_id_compare)))
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
 * Function: vmware_hv_ds_access_update                                       *
 *                                                                            *
 * Purpose: update access state of hv to ds                                   *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             hv_uuid      - [IN] the vmware hypervisor uuid                 *
 *             hv_id        - [IN] the vmware hypervisor id                   *
 *             hv_dss       - [IN] the vector with all DS connected to HV     *
 *             dss          - [IN/OUT] the vector with all Datastores         *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the access state was updated successfully          *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	vmware_hv_ds_access_update(zbx_vmware_service_t *service, CURL *easyhandle, const char *hv_uuid,
		const char *hv_id, const zbx_vector_str_t *hv_dss, zbx_vector_vmware_datastore_t *dss, char **error)
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
		zbx_xml_free_doc(doc);
		doc = NULL;

		if (SUCCEED != zbx_property_collection_next(__func__, iter, &doc, error))
			goto out;

		updated += vmware_hv_ds_access_parse(doc, hv_dss, hv_uuid, hv_id, dss);
	}

	ret = SUCCEED;
out:
	zbx_property_collection_free(iter);
	zbx_xml_free_doc(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s for %d / %d", __func__, zbx_result_string(ret), updated,
			hv_dss->values_num);

	return ret;

#	undef ZBX_POST_HV_DS_ACCESS
}

#endif /* defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL) */
