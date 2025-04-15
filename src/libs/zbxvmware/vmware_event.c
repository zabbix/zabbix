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

#include "vmware_event.h"

#include "zbxcommon.h"

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

#include "vmware_internal.h"
#include "vmware_shmem.h"

#include "zbxalgo.h"
#include "zbxnix.h"
#include "zbxstr.h"
#include "zbxtime.h"
#include "zbxshmem.h"
#include "zbxxml.h"

#ifdef HAVE_LIBXML2
#	include <libxml/xpath.h>
#endif

typedef struct
{
	zbx_uint64_t	id;
	xmlNode		*xml_node;
	time_t		created_time;
}
zbx_id_xmlnode_t;

ZBX_VECTOR_DECL(id_xmlnode, zbx_id_xmlnode_t)
ZBX_VECTOR_IMPL(id_xmlnode, zbx_id_xmlnode_t)

/* VMware events host information */
typedef struct
{
	const char	*node_name;
	int		flag;
	char		*name;
}
event_hostinfo_node_t;

static zbx_hashset_t	evt_msg_strpool;

/******************************************************************************
 *                                                                            *
 * Purpose: initialization of strpool resources                               *
 *                                                                            *
 ******************************************************************************/
static void	evt_msg_strpool_init(void)
{
	zbx_hashset_create(&evt_msg_strpool, 100, vmware_strpool_hash_func, vmware_strpool_compare_func);
}

/******************************************************************************
 *                                                                            *
 * Purpose: release of strpool resources                                      *
 *                                                                            *
 ******************************************************************************/
static void	evt_msg_strpool_destroy(void)
{
	zbx_hashset_destroy(&evt_msg_strpool);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store event message in strpool       *
 *                                                                            *
 ******************************************************************************/
static void	evt_msg_strpool_strfree(char *str, zbx_uint64_t *strpool_sz)
{
	zbx_uint64_t	len;

	vmware_strpool_strfree(str, &evt_msg_strpool, &len);

	if (NULL != strpool_sz && 0 < len)
		*strpool_sz -= zbx_shmem_required_chunk_size(len);
}

/******************************************************************************
 *                                                                            *
 * Purpose: store event message in strpool                                    *
 *                                                                            *
 ******************************************************************************/
static char	*evt_msg_strpool_strdup(const char *str, zbx_uint64_t *strpool_sz)
{
	char		*strdup;
	zbx_uint64_t	len;

	strdup = vmware_strpool_strdup(str, &evt_msg_strpool, &len);

	if (NULL != strpool_sz && 0 < len)
		*strpool_sz += zbx_shmem_required_chunk_size(len);

	return strdup;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compute common size of memory for evt strpool and shmem strpool   *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	vmware_evt_strpool_overlap_mem(void)
{
	void			*ptr;
	zbx_hashset_iter_t	iter;
	zbx_uint64_t		common_sz = 0;

	zbx_hashset_iter_reset(&evt_msg_strpool, &iter);

	while (NULL != (ptr = zbx_hashset_iter_next(&iter)))
	{
		const char	*str = (char *)ptr + REFCOUNT_FIELD_SIZE;

		if (FAIL == vmware_shared_strsearch(str))
			continue;

		common_sz += zbx_shmem_required_chunk_size(strlen(str) +
				REFCOUNT_FIELD_SIZE + 1 + ZBX_HASHSET_ENTRY_OFFSET);
	}

	return common_sz;
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store vmware event                   *
 *                                                                            *
 * Parameters: event - [IN] vmware event                                      *
 *                                                                            *
 ******************************************************************************/
void	vmware_event_free(zbx_vmware_event_t *event)
{
	evt_msg_strpool_strfree(event->message, NULL);
	zbx_free(event);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compute full shared memory size of vmware event vector            *
 *                                                                            *
 * Parameters: events - [IN] vmware event vector                              *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	vmware_service_evt_vector_memsize(zbx_vector_vmware_event_ptr_t *events)
{
	return zbx_vmware_get_evt_req_chunk_sz() * events->values_num +
			zbx_shmem_required_chunk_size(events->values_alloc * sizeof(zbx_vmware_event_t*));
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets map of severity and event type                               *
 *                                                                            *
 * Parameters: service        - [IN] vmware service                           *
 *             easyhandle     - [IN] CURL handle                              *
 *             evt_severities - [IN/OUT] key-value vector with EventID and    *
 *                                       severity as value                    *
 *             error          - [OUT] error message in case of failure        *
 *                                                                            *
 * Return value: SUCCEED - operation has completed successfully               *
 *               FAIL    - operation has failed                               *
 *                                                                            *
 ******************************************************************************/
int	vmware_service_get_evt_severity(zbx_vmware_service_t *service, CURL *easyhandle,
		zbx_vector_vmware_key_value_t *evt_severities, char **error)
{
#	define ZBX_POST_VMWARE_GET_EVT_SEVERITY							\
		ZBX_POST_VSPHERE_HEADER								\
		"<ns0:RetrievePropertiesEx>"							\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"			\
			"<ns0:specSet>"								\
				"<ns0:propSet>"							\
					"<ns0:type>EventManager</ns0:type>"			\
					"<ns0:pathSet>description</ns0:pathSet>"		\
				"</ns0:propSet>"						\
				"<ns0:objectSet>"						\
					"<ns0:obj type=\"EventManager\">%s</ns0:obj>"		\
				"</ns0:objectSet>"						\
			"</ns0:specSet>"							\
			"<ns0:options/>"							\
		"</ns0:RetrievePropertiesEx>"							\
		ZBX_POST_VSPHERE_FOOTER

	char		tmp[MAX_STRING_LEN];
	xmlDoc		*doc = NULL;
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	int		i, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_GET_EVT_SEVERITY,
			get_vmware_service_objects()[service->type].property_collector,
			get_vmware_service_objects()[service->type].event_manager);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, NULL, error))
		goto out;

	xpathCtx = xmlXPathNewContext(doc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)ZBX_XPATH_PROP_NAME("description")
			ZBX_XPATH_LN("eventInfo"), xpathCtx)))
	{
		*error = zbx_strdup(*error, "Cannot make events description list parsing query.");
		goto clean;
	}

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
	{
		*error = zbx_strdup(*error, "Cannot find items in events description list.");
		goto clean;
	}

	nodeset = xpathObj->nodesetval;
	zbx_vector_vmware_key_value_reserve(evt_severities, (size_t)nodeset->nodeNr);

	for (i = 0; i < nodeset->nodeNr; i++)
	{
		zbx_vmware_key_value_t	evt_sev;
		char			*delimetr, *full_format;

		if (NULL == (full_format = zbx_xml_node_read_value(doc, nodeset->nodeTab[i], ZBX_XNN("fullFormat"))))
			continue;

		if (NULL != (delimetr = strchr(full_format, '|')))
		{
			*delimetr = '\0';
			evt_sev.key = zbx_strdup(NULL, full_format);
		}
		else
			evt_sev.key = zbx_xml_node_read_value(doc, nodeset->nodeTab[i], ZBX_XNN("key"));

		zbx_str_free(full_format);
		evt_sev.value = zbx_xml_node_read_value(doc, nodeset->nodeTab[i], ZBX_XNN("category"));

		if (NULL == evt_sev.key || NULL == evt_sev.value)
		{
			zbx_vmware_key_value_free(evt_sev);
			continue;
		}

		zbx_vector_vmware_key_value_append(evt_severities, evt_sev);
	}

	ret = SUCCEED;
clean:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);
	zbx_xml_doc_free(doc);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() evt_severities:%d", __func__, evt_severities->values_num);

	return ret;
#	undef ZBX_POST_VMWARE_GET_EVT_SEVERITY
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves event session name                                      *
 *                                                                            *
 * Parameters: service       - [IN] vmware service                            *
 *             easyhandle    - [IN] CURL handle                               *
 *             evt_severity  - [IN] event severities                          *
 *             end_time      - [IN] end of the time range                     *
 *             event_session - [OUT] pointer to output variable               *
 *             error         - [OUT] error message in case of failure         *
 *                                                                            *
 * Return value: SUCCEED - operation has completed successfully               *
 *               FAIL    - operation has failed                               *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_event_session(const zbx_vmware_service_t *service, CURL *easyhandle,
		const unsigned char evt_severity, const time_t end_time, char **event_session, char **error)
{
#	define ZBX_POST_VMWARE_CREATE_EVENT_COLLECTOR				\
		ZBX_POST_VSPHERE_HEADER						\
		"<ns0:CreateCollectorForEvents>"				\
			"<ns0:_this type=\"EventManager\">%s</ns0:_this>"	\
			"<ns0:filter>%s</ns0:filter>"				\
		"</ns0:CreateCollectorForEvents>"				\
		ZBX_POST_VSPHERE_FOOTER

#	define ZBX_POST_VMWARE_EVENT_FILTER_SPEC_CATEGORY			\
		"<ns0:category>%s</ns0:category>"

	static const char	*levels[] = {ZBX_VMWARE_EVTLOG_SEVERITIES};
	char			tmp[MAX_STRING_LEN], *filter = NULL;
	size_t			alloc_len = 0, offset = 0;
	int			ret = FAIL;
	xmlDoc			*doc = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (size_t i = 0; i < ARRSIZE(levels); i++)
	{
		if (0 != ((1 << i) & evt_severity))
		{
			zbx_snprintf_alloc(&filter, &alloc_len, &offset, ZBX_POST_VMWARE_EVENT_FILTER_SPEC_CATEGORY,
					levels[i]);
		}
	}

	if (0 != end_time)
	{
		struct	tm	st;
		char		end_dt[ZBX_XML_DATETIME];

		gmtime_r(&end_time, &st);
		strftime(end_dt, sizeof(end_dt), "%Y-%m-%dT%TZ", &st);
		zbx_snprintf_alloc(&filter, &alloc_len, &offset, "<ns0:time><ns0:endTime>%s</ns0:endTime></ns0:time>",
				end_dt);
	}

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_CREATE_EVENT_COLLECTOR,
			get_vmware_service_objects()[service->type].event_manager, ZBX_NULL2EMPTY_STR(filter));

	zbx_free(filter);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, NULL, error))
		goto out;

	if (NULL == (*event_session = zbx_xml_doc_read_value(doc, "/*/*/*/*[@type='EventHistoryCollector']")))
	{
		*error = zbx_strdup(*error, "Cannot get EventHistoryCollector session.");
		goto out;
	}

	ret = SUCCEED;
out:
	zbx_xml_doc_free(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s event_session:'%s'", __func__, zbx_result_string(ret),
			ZBX_NULL2EMPTY_STR(*event_session));

	return ret;

#	undef ZBX_POST_VMWARE_CREATE_EVENT_COLLECTOR
#	undef ZBX_POST_VMWARE_EVENT_FILTER_SPEC_CATEGORY
}

/******************************************************************************
 *                                                                            *
 * Purpose: resets "scrollable view" to latest events                         *
 *                                                                            *
 * Parameters: easyhandle    - [IN] CURL handle                               *
 *             event_session - [IN] event session (EventHistoryCollector)     *
 *                                  identifier                                *
 *             error         - [OUT] error message in case of failure         *
 *                                                                            *
 * Return value: SUCCEED - operation has completed successfully               *
 *               FAIL    - operation has failed                               *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_reset_event_history_collector(CURL *easyhandle, const char *event_session, char **error)
{
#	define ZBX_POST_VMWARE_RESET_EVENT_COLLECTOR					\
		ZBX_POST_VSPHERE_HEADER							\
		"<ns0:ResetCollector>"							\
			"<ns0:_this type=\"EventHistoryCollector\">%s</ns0:_this>"	\
		"</ns0:ResetCollector>"							\
		ZBX_POST_VSPHERE_FOOTER

	int		ret = FAIL;
	char		tmp[MAX_STRING_LEN], *event_session_esc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	event_session_esc = zbx_xml_escape_dyn(event_session);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_RESET_EVENT_COLLECTOR, event_session_esc);

	zbx_free(event_session_esc);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, NULL, NULL, error))
		goto out;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef ZBX_POST_VMWARE_DESTROY_EVENT_COLLECTOR
}

/******************************************************************************
 *                                                                            *
 * Purpose: reads events from "scrollable view" and moves it back in time     *
 *                                                                            *
 * Parameters: easyhandle    - [IN] CURL handle                               *
 *             event_session - [IN] event session (EventHistoryCollector)     *
 *                                  identifier                                *
 *             soap_count    - [IN] max count of events in response           *
 *             xdoc          - [OUT] result as xml document                   *
 *             error         - [OUT] error message in case of failure         *
 *                                                                            *
 * Return value: SUCCEED - operation has completed successfully               *
 *               FAIL    - operation has failed                               *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_read_previous_events(CURL *easyhandle, const char *event_session, int soap_count,
		xmlDoc **xdoc, char **error)
{
#	define ZBX_POST_VMWARE_READ_PREVIOUS_EVENTS					\
		ZBX_POST_VSPHERE_HEADER							\
		"<ns0:ReadPreviousEvents>"						\
			"<ns0:_this type=\"EventHistoryCollector\">%s</ns0:_this>"	\
			"<ns0:maxCount>%d</ns0:maxCount>"				\
		"</ns0:ReadPreviousEvents>"						\
		ZBX_POST_VSPHERE_FOOTER

	int	ret = FAIL;
	char	tmp[MAX_STRING_LEN], *event_session_esc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() soap_count: %d", __func__, soap_count);

	event_session_esc = zbx_xml_escape_dyn(event_session);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_READ_PREVIOUS_EVENTS, event_session_esc, soap_count);

	zbx_free(event_session_esc);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, xdoc, NULL, error))
		goto out;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
#	undef	ZBX_POST_VMWARE_READ_PREVIOUS_EVENTS
}

/******************************************************************************
 *                                                                            *
 * Purpose: reads events from "latest page" and moves it back in time         *
 *                                                                            *
 * Parameters: service       - [IN] vmware service                            *
 *             easyhandle    - [IN] CURL handle                               *
 *             event_session - [IN] event session (EventHistoryCollector)     *
 *                                  identifier                                *
 *             xdoc          - [OUT] result as xml document                   *
 *             error         - [OUT] error message in case of failure         *
 *                                                                            *
 * Return value: SUCCEED - operation has completed successfully               *
 *               FAIL    - operation has failed                               *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_event_latestpage(const zbx_vmware_service_t *service, CURL *easyhandle,
		const char *event_session, xmlDoc **xdoc, char **error)
{
#	define ZBX_POST_VMWARE_READ_EVENT_LATEST_PAGE						\
		ZBX_POST_VSPHERE_HEADER								\
		"<ns0:RetrievePropertiesEx>"							\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"			\
			"<ns0:specSet>"								\
				"<ns0:propSet>"							\
					"<ns0:type>EventHistoryCollector</ns0:type>"		\
					"<ns0:pathSet>latestPage</ns0:pathSet>"			\
				"</ns0:propSet>"						\
				"<ns0:objectSet>"						\
					"<ns0:obj type=\"EventHistoryCollector\">%s</ns0:obj>"	\
				"</ns0:objectSet>"						\
			"</ns0:specSet>"							\
			"<ns0:options/>"							\
		"</ns0:RetrievePropertiesEx>"							\
		ZBX_POST_VSPHERE_FOOTER

	int	ret = FAIL;
	char	tmp[MAX_STRING_LEN], *event_session_esc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	event_session_esc = zbx_xml_escape_dyn(event_session);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_READ_EVENT_LATEST_PAGE,
			get_vmware_service_objects()[service->type].property_collector, event_session_esc);

	zbx_free(event_session_esc);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, xdoc, NULL, error))
		goto out;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef ZBX_POST_VMWARE_READ_EVENT_LATEST_PAGE
}

/******************************************************************************
 *                                                                            *
 * Parameters: easyhandle    - [IN] CURL handle                               *
 *             event_session - [IN] event session (EventHistoryCollector)     *
 *                                  identifier                                *
 *             error         - [OUT] error message in case of failure         *
 *                                                                            *
 * Return value: SUCCEED - operation has completed successfully               *
 *               FAIL    - operation has failed                               *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_destroy_event_session(CURL *easyhandle, const char *event_session, char **error)
{
#	define ZBX_POST_VMWARE_DESTROY_EVENT_COLLECTOR					\
		ZBX_POST_VSPHERE_HEADER							\
		"<ns0:DestroyCollector>"						\
			"<ns0:_this type=\"EventHistoryCollector\">%s</ns0:_this>"	\
		"</ns0:DestroyCollector>"						\
		ZBX_POST_VSPHERE_FOOTER

	int	ret = FAIL;
	char	tmp[MAX_STRING_LEN], *event_session_esc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	event_session_esc = zbx_xml_escape_dyn(event_session);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_DESTROY_EVENT_COLLECTOR, event_session_esc);

	zbx_free(event_session_esc);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, NULL, NULL, error))
		goto out;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
#	undef ZBX_POST_VMWARE_DESTROY_EVENT_COLLECTOR
}

/******************************************************************************
 *                                                                            *
 * Purpose: reads event data by id from xml and puts it to array of events    *
 *                                                                            *
 * Parameters: events         - [IN/OUT] array of parsed events               *
 *             xml_event      - [IN] xml node and id of parsed event          *
 *             xdoc           - [IN] xml document with eventlog records       *
 *             evt_severities - [IN] dictionary of severity for event types   *
 *             strpool_sz     - [OUT] estimated shared memory size for events *
 *                                                                            *
 * Return value: SUCCEED - operation has completed successfully               *
 *               FAIL    - operation has failed                               *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_put_event_data(zbx_vector_vmware_event_ptr_t *events, zbx_id_xmlnode_t xml_event,
		xmlDoc *xdoc, const zbx_hashset_t *evt_severities, zbx_uint64_t *strpool_sz)
{
#	define ZBX_HOSTINFO_NODES_DATACENTER		0x01
#	define ZBX_HOSTINFO_NODES_COMPRES		0x02
#	define ZBX_HOSTINFO_NODES_HOST			0x04
#	define ZBX_HOSTINFO_NODES_VM			0x08
#	define ZBX_HOSTINFO_NODES_DS			0x10
#	define ZBX_HOSTINFO_NODES_NET			0x20
#	define ZBX_HOSTINFO_NODES_DVS			0x40
#	define ZBX_HOSTINFO_NODES_MASK_ALL									\
		(ZBX_HOSTINFO_NODES_DATACENTER | ZBX_HOSTINFO_NODES_COMPRES | ZBX_HOSTINFO_NODES_HOST | \
		ZBX_HOSTINFO_NODES_VM | ZBX_HOSTINFO_NODES_DS | ZBX_HOSTINFO_NODES_NET | ZBX_HOSTINFO_NODES_DVS)

#	define	ZBX_XPATH_EVT_INFO(param)				\
		"*[local-name()='" param "']/*[local-name()='name']"
#	define	ZBX_XPATH_EVT_ARGUMENT(key)									\
		"*[local-name()='arguments'][*[local-name()='key'][text()='" key "']]/*[local-name()='value']"

	zbx_vmware_event_t		*event = NULL;
	char				*message, *ip, *type, *username, *info;
	int				nodes_det = 0;
	unsigned int			i;
	static event_hostinfo_node_t	host_nodes[] =
	{
		{ ZBX_XPATH_EVT_INFO("datacenter"),		ZBX_HOSTINFO_NODES_DATACENTER,	NULL },
		{ ZBX_XPATH_EVT_INFO("computeResource"),	ZBX_HOSTINFO_NODES_COMPRES,	NULL },
		{ ZBX_XPATH_EVT_INFO("host"),			ZBX_HOSTINFO_NODES_HOST,	NULL },
		{ ZBX_XPATH_EVT_ARGUMENT("_sourcehost_"),	ZBX_HOSTINFO_NODES_HOST,	NULL },
		{ ZBX_XPATH_EVT_ARGUMENT("entityName"),		ZBX_HOSTINFO_NODES_HOST,	NULL },
		{ ZBX_XPATH_EVT_INFO("vm"),			ZBX_HOSTINFO_NODES_VM,		NULL },
		{ ZBX_XPATH_EVT_INFO("ds"),			ZBX_HOSTINFO_NODES_DS,		NULL },
		{ ZBX_XPATH_EVT_INFO("net"),			ZBX_HOSTINFO_NODES_NET,		NULL },
		{ ZBX_XPATH_EVT_INFO("dvs"),			ZBX_HOSTINFO_NODES_DVS,		NULL }
	};

	if (NULL == (message = zbx_xml_node_read_value(xdoc, xml_event.xml_node, ZBX_XPATH_NN("fullFormattedMessage"))))
	{
		zabbix_log(LOG_LEVEL_TRACE, "skipping event key '" ZBX_FS_UI64 "', fullFormattedMessage"
				" is missing", xml_event.id);
		return FAIL;
	}

	if (NULL == (type = zbx_xml_node_read_value(xdoc, xml_event.xml_node, ZBX_XPATH_NN("eventTypeId"))))
		type = zbx_xml_node_read_prop(xml_event.xml_node, "type");

	info = zbx_strdup(NULL, "");

	if (NULL != type)
	{
		zbx_vmware_key_value_t	*severity, evt_cmp = {.key=type};
		char			*value;

		if (NULL != (value = zbx_xml_node_read_value(xdoc, xml_event.xml_node, ZBX_XPATH_NN("severity"))))
		{
			info = zbx_dsprintf(info, "\ntype: %s/%s", value, type);
			zbx_str_free(value);
		}
		else if (NULL != (severity = (zbx_vmware_key_value_t *)zbx_hashset_search(evt_severities, &evt_cmp)))
		{
			info = zbx_dsprintf(info, "\ntype: %s/%s", severity->value, type);
		}
		else
			info = zbx_dsprintf(info, "\ntype: %s", type);

		zbx_free(type);
	}

	for (i = 0; i < ARRSIZE(host_nodes); i++)
	{
		if (0 == (nodes_det & host_nodes[i].flag) && NULL != (host_nodes[i].name =
				zbx_xml_node_read_value(xdoc, xml_event.xml_node, host_nodes[i].node_name)))
		{
			switch(host_nodes[i].flag)
			{
				case ZBX_HOSTINFO_NODES_DS:
					host_nodes[i].name = zbx_dsprintf(host_nodes[i].name, " ds:%s",
							host_nodes[i].name);
					break;
				case ZBX_HOSTINFO_NODES_NET:
					host_nodes[i].name = zbx_dsprintf(host_nodes[i].name," net:%s",
							host_nodes[i].name);
					break;
				case ZBX_HOSTINFO_NODES_DVS:
					host_nodes[i].name = zbx_dsprintf(host_nodes[i].name, " dvs:%s",
							host_nodes[i].name);
					break;
				default:
					host_nodes[i].name = zbx_dsprintf(host_nodes[i].name, "%s%s",
							0 != nodes_det ? "/" : ": ", host_nodes[i].name);
			}

			nodes_det |= host_nodes[i].flag;
		}
	}

	if (0 != (nodes_det & ZBX_HOSTINFO_NODES_MASK_ALL))
	{
		info = zbx_dsprintf(info, "%s\nsource", info);

		for (i = 0; i < ARRSIZE(host_nodes); i++)
		{
			if (NULL == host_nodes[i].name)
				continue;

			info = zbx_dsprintf(info, "%s%s", info, host_nodes[i].name);
			zbx_free(host_nodes[i].name);
		}
	}
	else
	{
		for (i = 0; i < ARRSIZE(host_nodes); i++)
			zbx_free(host_nodes[i].name);

		if (NULL != (ip = zbx_xml_node_read_value(xdoc, xml_event.xml_node, ZBX_XPATH_NN("ipAddress"))))
		{
			info = zbx_dsprintf(info, "%s\nsource: %s", info, ip);
			zbx_free(ip);
		}
	}

	if (NULL != (username = zbx_xml_node_read_value(xdoc, xml_event.xml_node, ZBX_XPATH_NN("userName"))))
	{
		info = zbx_dsprintf(info, "%s\nuser: %s", info, username);
		zbx_free(username);
	}

	if ('\0' != *info)
		message = zbx_dsprintf(message, "%s\n%s", message, info);

	zbx_free(info);
	zbx_replace_invalid_utf8(message);

	event = (zbx_vmware_event_t *)zbx_malloc(event, sizeof(zbx_vmware_event_t));
	event->key = xml_event.id;
	event->timestamp = xml_event.created_time;
	event->message = evt_msg_strpool_strdup(message, strpool_sz);
	zbx_free(message);
	zbx_vector_vmware_event_ptr_append(events, event);

	return SUCCEED;

#	undef ZBX_HOSTINFO_NODES_DATACENTER
#	undef ZBX_HOSTINFO_NODES_COMPRES
#	undef ZBX_HOSTINFO_NODES_HOST
#	undef ZBX_HOSTINFO_NODES_VM
#	undef ZBX_HOSTINFO_NODES_DS
#	undef ZBX_HOSTINFO_NODES_NET
#	undef ZBX_HOSTINFO_NODES_DVS
#	undef ZBX_HOSTINFO_NODES_MASK_ALL
#	undef ZBX_XPATH_EVT_INFO
#	undef ZBX_XPATH_EVT_ARGUMENT
}

/******************************************************************************
 *                                                                            *
 * Purpose: reads event's createdTime from xml                                *
 *                                                                            *
 * Parameters: doc       - [IN] xml document with eventlog records            *
 *             node      - [IN] the xml node with given event                 *
 *             eventid   - [IN]                                               *
 *                                                                            *
 * Return value: createdTime converted to timestamp - if operation has        *
 *                       completed successfully,                              *
 *               0 - otherwise                                                *
 *                                                                            *
 ******************************************************************************/
static time_t	vmware_service_parse_event_ts(xmlDoc *doc, xmlNode *node, zbx_uint64_t eventid)
{
	char	*ts;
	time_t	created_time = 0;

	if (NULL == (ts = zbx_xml_node_read_value(doc, node, ZBX_XPATH_NN("createdTime"))))
	{
		zabbix_log(LOG_LEVEL_TRACE, "eventlog record without createdTime, event key '" ZBX_FS_UI64 "'",
				eventid);
		return 0;
	}

	if (FAIL == zbx_iso8601_utc(ts, &created_time))	/* 2013-06-04T14:19:23.406298Z */
	{
		zabbix_log(LOG_LEVEL_TRACE, "unexpected format of createdTime '%s' for event key '" ZBX_FS_UI64 "'",
				ts, eventid);
	}

	zbx_free(ts);

	return created_time;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses multiple events data                                       *
 *                                                                            *
 * Parameters: events       - [IN/OUT] array of parsed events                 *
 *             last_key     - [IN] key of last parsed event                   *
 *             last_ts      - [IN] the timestamp of last parsed event         *
 *             is_prop      - [IN] read events from RetrieveProperties xml    *
 *             xdoc         - [IN] xml document with eventlog records         *
 *             eventlog     - [IN] VMware event log state                     *
 *             evt_severity - [IN] event severities                           *
 *             strpool_sz   - [OUT] estimated shared memory size for events   *
 *             node_count   - [OUT] count of xml event nodes                  *
 *             skip_old     - [OUT] detected event key reset                  *
 *                                                                            *
 * Return value: count of events successfully parsed                          *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_parse_event_data(zbx_vector_vmware_event_ptr_t *events, zbx_uint64_t last_key,
		time_t last_ts, const int is_prop, xmlDoc *xdoc, const zbx_vmware_eventlog_state_t *eventlog,
		const unsigned char evt_severity, zbx_uint64_t *strpool_sz, int *node_count, unsigned char *skip_old)
{
#	define LAST_KEY(evs)	(evs->values[evs->values_num - 1]->key)

	zbx_vector_id_xmlnode_t	ids;
	int			parsed_num = 0;
	char			*value;
	xmlXPathContext		*xpathCtx;
	xmlXPathObject		*xpathObj;
	xmlNodeSetPtr		nodeset;
	static int		is_clear = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() last_key:" ZBX_FS_UI64 " events:%d memory:" ZBX_FS_UI64, __func__,
			last_key, events->values_num, *strpool_sz + vmware_service_evt_vector_memsize(events));

	xpathCtx = xmlXPathNewContext(xdoc);
	zbx_vector_id_xmlnode_create(&ids);

	if (NULL != node_count)
		*node_count = 0;

	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)(0 == is_prop ? "/*/*/*"
			ZBX_XPATH_LN("returnval") : "/*/*/*" ZBX_XPATH_LN("returnval") "/*/*/*" ZBX_XPATH_LN("Event")),
			xpathCtx)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot make evenlog list parsing query.");
		goto clean;
	}

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot find items in evenlog list.");
		goto clean;
	}

	nodeset = xpathObj->nodesetval;
	zbx_vector_id_xmlnode_reserve(&ids, (size_t)nodeset->nodeNr);

	if (NULL != node_count)
		*node_count = nodeset->nodeNr;

	for (int i = 0; i < nodeset->nodeNr; i++)
	{
		zbx_id_xmlnode_t	xml_event;
		zbx_uint64_t		key;

		if (NULL == (value = zbx_xml_node_read_value(xdoc, nodeset->nodeTab[i], ZBX_XPATH_NN("key"))))
		{
			zabbix_log(LOG_LEVEL_TRACE, "skipping eventlog record without key, xml number '%d'", i);
			continue;
		}

		key = (unsigned int) atoi(value);

		if (0 == key)
		{
			if (0 == isdigit(value[('-' == *value || '+' == *value) ? 1 : 0 ]))
				zabbix_log(LOG_LEVEL_TRACE, "skipping eventlog key '%s', not a number", value);
			else
				zabbix_log(LOG_LEVEL_TRACE, "skipping empty eventlog key");

			zbx_free(value);
			continue;
		}

		zbx_free(value);

		xml_event.created_time = vmware_service_parse_event_ts(xdoc, nodeset->nodeTab[i], key);

		if (key <= last_key)
		{
			if (xml_event.created_time <= last_ts || 0 != events->values_num + ids.values_num)
			{
				zabbix_log(LOG_LEVEL_TRACE, "skipping event key '" ZBX_FS_UI64 "', has been processed",
						key);
				continue;
			}

			zabbix_log(LOG_LEVEL_TRACE, "event key reset, key: '" ZBX_FS_UI64 "', last_key: '"
					ZBX_FS_UI64 "', createdTime: '" ZBX_FS_TIME_T "', last_ts: '" ZBX_FS_TIME_T "'",
					key, last_key, xml_event.created_time, last_ts);
			*skip_old = 1;
			goto clean;
		}

		xml_event.id = key;
		xml_event.xml_node = nodeset->nodeTab[i];
		zbx_vector_id_xmlnode_append(&ids, xml_event);
	}

	if (0 != ids.values_num)
	{
		zbx_vector_id_xmlnode_sort(&ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_vmware_event_ptr_reserve(events, (size_t)(ids.values_num + events->values_num));

		/* validate that last event from "latestPage" is connected with first event from ReadPreviousEvents */
		if (0 != events->values_num && LAST_KEY(events) != ids.values[ids.values_num -1].id + 1)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() events:%d is_clear:%d id gap:%d severity:%d", __func__,
					events->values_num, is_clear,
					(int)(LAST_KEY(events) - (ids.values[ids.values_num -1].id + 1)),
					(int)evt_severity);

			/* if sequence of events is not continuous, ignore events from "latestPage" property */
			/* except when events are filtered by severity */
			if (0 != is_clear && 0 == evt_severity)
			{
				zbx_vector_vmware_event_ptr_clear_ext(events, vmware_event_free);
				*strpool_sz = 0;
			}
		}

		/* we are reading "scrollable views" in reverse chronological order, */
		/* so inside a "scrollable view" latest events should come first too */
		for (int i = ids.values_num - 1; i >= 0; i--)
		{
			if (SUCCEED == vmware_service_put_event_data(events, ids.values[i], xdoc,
					&eventlog->evt_severities, strpool_sz))
			{
				parsed_num++;
			}
		}
	}
	else if (0 != last_key && 0 != events->values_num && LAST_KEY(events) != last_key + 1)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() events:%d is_clear:%d last_key id gap:%d severity:%d", __func__,
				events->values_num, is_clear, (int)(LAST_KEY(events) - (last_key + 1)),
				(int)evt_severity);

		/* if sequence of events is not continuous, ignore events from "latestPage" property */
		/* except when events are filtered by severity */
		if (0 != is_clear && 0 == evt_severity)
		{
			zbx_vector_vmware_event_ptr_clear_ext(events, vmware_event_free);
			*strpool_sz = 0;
		}
	}
clean:
	zbx_vector_id_xmlnode_destroy(&ids);
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);
	is_clear = is_prop;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() parsed:%d memory:" ZBX_FS_UI64, __func__, parsed_num,
			*strpool_sz + vmware_service_evt_vector_memsize(events));

	return parsed_num;

#	undef LAST_KEY
}

/******************************************************************************
 *                                                                            *
 * Purpose: removing NEW events from "head" of vector                         *
 *                                                                            *
 * Parameters: max_mem    - [IN] available memory size                        *
 *             strpool_sz - [IN/OUT] allocated memory size for events         *
 *             events     - [IN/OUT] pointer to output variable               *
 *                                                                            *
 ******************************************************************************/
static	void vmware_service_clear_event_data_mem(const zbx_uint64_t max_mem, zbx_uint64_t *strpool_sz,
		zbx_vector_vmware_event_ptr_t *events)
{
	int	events_num = events->values_num;

	while(0 != events->values_num && max_mem < *strpool_sz + vmware_service_evt_vector_memsize(events))
	{
		evt_msg_strpool_strfree(events->values[events_num - events->values_num]->message, strpool_sz);
		zbx_free(events->values[events_num - events->values_num]);
		events->values_num--;
	}

	if (0 != events->values_num)
	{
		memmove(events->values, &events->values[events_num - events->values_num],
				sizeof(zbx_vmware_event_t *) * (size_t)events->values_num);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() removed:%d current:%d max_mem:" ZBX_FS_UI64, __func__,
			events_num - events->values_num, events->values_num, max_mem);
}

/******************************************************************************
 *                                                                            *
 * Parameters: service       - [IN] vmware service                            *
 *             easyhandle    - [IN] CURL handle                               *
 *             last_key      - [IN] ID of last processed event                *
 *             last_ts       - [IN] the create time of last processed event   *
 *             shmem_free_sz - [IN] free size of shared memory                *
 *             evt_severity  - [IN] event severities                          *
 *             end_time      - [IN] end of the time range                     *
 *             skip_old      - [IN/OUT] reset last_key of event               *
 *             events        - [OUT] pointer to output variable               *
 *             strpool_sz    - [OUT] allocated memory size for events         *
 *             evt_top_key   - [OUT] newest event id                          *
 *             evt_top_time  - [OUT] timestamp of evt_top_key event           *
 *             error         - [OUT] error message in case of failure         *
 *                                                                            *
 * Return value: SUCCEED - operation has completed successfully               *
 *               FAIL    - operation has failed                               *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_event_data(const zbx_vmware_service_t *service, CURL *easyhandle,
		const zbx_uint64_t last_key, const time_t last_ts, const zbx_uint64_t shmem_free_sz,
		const unsigned char evt_severity, const time_t end_time, unsigned char *skip_old,
		zbx_vector_vmware_event_ptr_t *events, zbx_uint64_t *strpool_sz, zbx_uint64_t *evt_top_key,
		time_t *evt_top_time, char **error)
{
#	define ATTEMPTS_NUM	4
#	define EVENT_TAG	1
#	define RETURNVAL_TAG	0
#	define LAST_KEY(evs)	(evs->values[evs->values_num - 1]->key)

	char		*event_session = NULL, *err = NULL;
	int		ret = FAIL, parsed_count = -1, node_count = -1, soap_retry = ATTEMPTS_NUM,
			soap_count = 5; /* 10 - initial value of eventlog records number in one response */
	xmlDoc		*doc = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() shmem_free_sz:" ZBX_FS_UI64, __func__, shmem_free_sz);

	if (SUCCEED != vmware_service_get_event_session(service, easyhandle, evt_severity, end_time, &event_session,
			error))
	{
		goto out;
	}

	if (SUCCEED != vmware_service_reset_event_history_collector(easyhandle, event_session, error))
		goto end_session;

	if (SUCCEED != vmware_service_get_event_latestpage(service, easyhandle, event_session, &doc, error))
		goto end_session;

	if (0 < vmware_service_parse_event_data(events, last_key, last_ts, EVENT_TAG, doc,
			&service->eventlog, evt_severity, strpool_sz, NULL, skip_old) &&
			(0 != *skip_old || LAST_KEY(events) == last_key + 1))
	{
		zabbix_log(LOG_LEVEL_TRACE, "%s() latestPage events:%d", __func__, events->values_num);

		*evt_top_key = events->values[0]->key;
		*evt_top_time = events->values[0]->timestamp;
		ret = SUCCEED;
		goto end_session;
	}

	do
	{
		zbx_xml_doc_free(doc);

		if ((ZBX_MAXQUERYMETRICS_UNLIMITED / 2) >= soap_count)
			soap_count = soap_count * 2;
		else if (ZBX_MAXQUERYMETRICS_UNLIMITED != soap_count)
			soap_count = ZBX_MAXQUERYMETRICS_UNLIMITED;

		if (0 != events->values_num && (LAST_KEY(events) - last_key - 1) < (unsigned int)soap_count)
		{
			soap_count = (int)(LAST_KEY(events) - last_key - 1);
		}

		/* we store the identifier here because later we can trim the vector according to the memory limit */
		if (0 == *evt_top_key && 0 != events->values_num)
		{
			*evt_top_key = events->values[0]->key;
			*evt_top_time = events->values[0]->timestamp;
		}

		if (!ZBX_IS_RUNNING() || 0 == soap_count || SUCCEED != vmware_service_read_previous_events(easyhandle,
				event_session, soap_count, &doc, error))
		{
			goto end_session;
		}

		if (0 != node_count && soap_retry != ATTEMPTS_NUM)
			soap_retry = ATTEMPTS_NUM;

		if (shmem_free_sz < *strpool_sz + vmware_service_evt_vector_memsize(events))
		{
			vmware_service_clear_event_data_mem(shmem_free_sz, strpool_sz, events);

			if (shmem_free_sz < *strpool_sz + vmware_service_evt_vector_memsize(events))
				break;
		}
	}
	while ((0 < (parsed_count = vmware_service_parse_event_data(events, last_key, last_ts, RETURNVAL_TAG, doc,
			&service->eventlog, evt_severity, strpool_sz, &node_count, skip_old)) ||
			(0 == node_count && 0 < soap_retry--)) && 0 == *skip_old &&
			(0 == events->values_num || LAST_KEY(events) != last_key + 1));

	if (0 == *evt_top_key)
	{
		*evt_top_key = 0 != events->values_num ? events->values[0]->key : last_key;
		*evt_top_time = 0 != events->values_num ? events->values[0]->timestamp : last_ts;
	}

	if (shmem_free_sz < *strpool_sz + vmware_service_evt_vector_memsize(events))
		vmware_service_clear_event_data_mem(shmem_free_sz, strpool_sz, events);

	if (0 != last_key && 0 != events->values_num && LAST_KEY(events) != last_key + 1)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() events:%d id gap:%d", __func__, events->values_num,
				(int)(LAST_KEY(events) - (last_key + 1)));
	}

	ret = SUCCEED;
end_session:
	if (SUCCEED != vmware_service_destroy_event_session(easyhandle, event_session, &err))
	{
		*error = zbx_strdcatf(*error, "%s%s", NULL != *error ? "; " : "", err);
		zbx_free(err);
		ret = FAIL;
	}
out:
	zbx_free(event_session);
	zbx_xml_doc_free(doc);

	if (SUCCEED == ret && 10 == soap_count && 0 == events->values_num && 0 == *skip_old && 0 == evt_severity)
		zabbix_log(LOG_LEVEL_WARNING, "vmware events collector returned empty result");

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s events:%d skip_old:%hhu memory:" ZBX_FS_UI64, __func__,
			zbx_result_string(ret), events->values_num, *skip_old,
			*strpool_sz + vmware_service_evt_vector_memsize(events));

	return ret;

#	undef ATTEMPTS_NUM
#	undef EVENT_TAG
#	undef RETURNVAL_TAG
#	undef LAST_KEY
}

/******************************************************************************
 *                                                                            *
 * Parameters: service    - [IN] vmware service                               *
 *             easyhandle - [IN] CURL handle                                  *
 *             events     - [OUT] pointer to output variable                  *
 *             strpool_sz - [OUT] allocated memory size for events            *
 *             error      - [OUT] error message in case of failure            *
 *                                                                            *
 * Return value: SUCCEED - operation has completed successfully               *
 *               FAIL    - operation has failed                               *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_last_event_data(const zbx_vmware_service_t *service, CURL *easyhandle,
		zbx_vector_vmware_event_ptr_t *events, zbx_uint64_t *strpool_sz, char **error)
{
#	define ZBX_POST_VMWARE_LASTEVENT 								\
		ZBX_POST_VSPHERE_HEADER									\
		"<ns0:RetrievePropertiesEx>"								\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"				\
			"<ns0:specSet>"									\
				"<ns0:propSet>"								\
					"<ns0:type>EventManager</ns0:type>"				\
					"<ns0:all>false</ns0:all>"					\
					"<ns0:pathSet>latestEvent</ns0:pathSet>"			\
				"</ns0:propSet>"							\
				"<ns0:objectSet>"							\
					"<ns0:obj type=\"EventManager\">%s</ns0:obj>"			\
				"</ns0:objectSet>"							\
			"</ns0:specSet>"								\
			"<ns0:options/>"								\
		"</ns0:RetrievePropertiesEx>"								\
		ZBX_POST_VSPHERE_FOOTER

	char			tmp[MAX_STRING_LEN], *value;
	int			ret = FAIL;
	xmlDoc			*doc = NULL;
	zbx_id_xmlnode_t	xml_event;
	xmlXPathContext		*xpathCtx;
	xmlXPathObject		*xpathObj;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_VMWARE_LASTEVENT,
			get_vmware_service_objects()[service->type].property_collector,
			get_vmware_service_objects()[service->type].event_manager);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, NULL, error))
		goto out;

	xpathCtx = xmlXPathNewContext(doc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)ZBX_XPATH_PROP_NAME("latestEvent"), xpathCtx)))
	{
		*error = zbx_strdup(*error, "Cannot make lastevenlog list parsing query.");
		goto clean;
	}

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
	{
		*error = zbx_strdup(*error, "Cannot find items in lastevenlog list.");
		goto clean;
	}

	xml_event.xml_node = xpathObj->nodesetval->nodeTab[0];

	if (NULL == (value = zbx_xml_node_read_value(doc, xml_event.xml_node, ZBX_XPATH_NN("key"))))
	{
		*error = zbx_strdup(*error, "Cannot find last event key");
		goto clean;
	}

	xml_event.id = (unsigned int) atoi(value);

	if (0 == xml_event.id && 0 == isdigit(value[('-' == *value || '+' == *value) ? 1 : 0 ]))
	{
		*error = zbx_dsprintf(*error, "Cannot convert eventlog key from %s", value);
		zbx_free(value);
		goto clean;
	}

	zbx_free(value);

	xml_event.created_time = vmware_service_parse_event_ts(doc, xpathObj->nodesetval->nodeTab[0], xml_event.id);

	if (SUCCEED != vmware_service_put_event_data(events, xml_event, doc, &service->eventlog.evt_severities,
			strpool_sz))
	{
		*error = zbx_dsprintf(*error, "Cannot retrieve last eventlog data for key "ZBX_FS_UI64, xml_event.id);
		goto clean;
	}

	ret = SUCCEED;
clean:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);
out:
	zbx_xml_doc_free(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s last_key:" ZBX_FS_UI64, __func__, zbx_result_string(ret),
			(SUCCEED == ret ? xml_event.id : 0));
	return ret;

#	undef ZBX_POST_VMWARE_LASTEVENT
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store vmware service event log data  *
 *                                                                            *
 * Parameters: evt_data - [IN] vmware service event log data                  *
 *                                                                            *
 ******************************************************************************/
static void	vmware_eventlog_data_free(zbx_vmware_eventlog_data_t *evt_data)
{
	zbx_vector_vmware_event_ptr_clear_ext(&evt_data->events, vmware_event_free);
	zbx_vector_vmware_event_ptr_destroy(&evt_data->events);

	zbx_free(evt_data->error);
	zbx_free(evt_data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: calculate the event log timestamp of the last events whose read   *
 *          start best matches the allocated memory fragment                  *
 *                                                                            *
 * Parameters: eventlog - [IN] vmware service event log info                  *
 *             mem_sz   - [IN] max shared memory for events                   *
 *                                                                            *
 ******************************************************************************/
static time_t	vmware_evt_endtime(const zbx_vmware_eventlog_state_t *eventlog, const zbx_uint64_t mem_sz)
{
#	define	EVTNUM_PER_ONE_MB	8000

	zbx_uint64_t	x_evt;
	time_t		end_time, now = time(NULL);

	if (0 == eventlog->last_ts || 0 == eventlog->top_time || 0 == eventlog->expect_num ||
			SEC_PER_HOUR > now - eventlog->last_ts ||
			SEC_PER_YEAR < now - eventlog->last_ts)	/* it could be in case of incorrect esxi time */
	{
		return 0;
	}

	x_evt = (mem_sz * EVTNUM_PER_ONE_MB) / ZBX_MEBIBYTE;
	end_time = x_evt * (eventlog->top_time - eventlog->last_ts) / eventlog->expect_num + eventlog->last_ts;

	if (end_time > now)
		end_time = 0;	/* it could be in case of incorrect esxi time */

	return end_time;

#	undef	EVTNUM_PER_ONE_MB
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates vmware event log                                          *
 *                                                                            *
 * Parameters: service               - [IN] vmware service                    *
 *             config_source_ip      - [IN]                                   *
 *             config_vmware_timeout - [IN]                                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_vmware_service_eventlog_update(zbx_vmware_service_t *service, const char *config_source_ip,
		int config_vmware_timeout)
{
#define ZBX_INIT_UPD_XML_SIZE		(100 * ZBX_KIBIBYTE)
	CURL				*easyhandle = NULL;
	struct curl_slist		*headers = NULL;
	zbx_vmware_eventlog_data_t	*evt_data;
	int				ret = FAIL;
	ZBX_HTTPPAGE			page;	/* 347K/87K */
	unsigned char			evt_pause = 0, evt_skip_old, evt_severity;
	zbx_uint64_t			evt_last_key, evt_top_key = 0, events_sz = 0, shmem_free_sz = 0;
	time_t				evt_last_ts, evt_end_time = 0, evt_top_time = 0;
	char				msg[VMWARE_SHORT_STR_LEN];
	float				shmem_factor = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() '%s'@'%s'", __func__, service->username, service->url);

	evt_data = (zbx_vmware_eventlog_data_t *)zbx_malloc(NULL, sizeof(zbx_vmware_eventlog_data_t));
	evt_data->error = NULL;
	zbx_vector_vmware_event_ptr_create(&evt_data->events);
	page.alloc = 0;

	evt_msg_strpool_init();

	zbx_vmware_lock();
	evt_last_key = service->eventlog.last_key;
	evt_last_ts = service->eventlog.last_ts;
	evt_skip_old = service->eventlog.skip_old;
	evt_severity = service->eventlog.severity;

	if (NULL != service->eventlog.data && 0 != service->eventlog.data->events.values_num && 0 == evt_skip_old &&
			service->eventlog.data->events.values[0]->key > evt_last_key)
	{
		evt_pause = 1;
	}
	else
	{
		if (NULL != service->eventlog.data)
			vmware_eventlog_msg_shared_free(&service->eventlog.data->events);

		if (vmware_shmem_get_vmware_mem()->free_size > vmware_shmem_get_vmware_mem()->total_size * 5 / 100)
		{
			shmem_free_sz = vmware_shmem_get_vmware_mem()->free_size -
					vmware_shmem_get_vmware_mem()->total_size * 5 / 100;

			/* we try to escape the scenario when new events happen faster than we can read them */
			/* in case of small memory chunk and long item polling interval */
			shmem_factor = vmware_shared_evtpart_size(service->eventlog.expect_num);
			shmem_free_sz = (zbx_uint64_t)(shmem_free_sz * shmem_factor);
			service->eventlog.req_sz = 0;
			evt_end_time = vmware_evt_endtime(&service->eventlog, shmem_free_sz);

			if (FAIL == vmware_shared_is_ready())
				evt_pause = 1;
		}
		else
		{
			evt_pause = 1;
			service->eventlog.oom = 1;
		}
	}

	zbx_vmware_unlock();

	if (0 != evt_pause)
	{
		if (0 != service->eventlog.req_sz)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Postponed VMware events requires up to " ZBX_FS_UI64
					" bytes of free VMwareCache memory. Available " ZBX_FS_UI64 " bytes."
					" Reading events skipped", service->eventlog.req_sz, shmem_free_sz);
		}
		else if (0 != service->eventlog.oom)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "There is no 5%% free memory. Reading new events skipped");
		}
		else
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Previous events have not been read. Reading new events skipped");
		}

		ret = SUCCEED;
		goto out;
	}

	if (NULL == (easyhandle = curl_easy_init()))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot initialize cURL library");
		goto out;
	}

	page.alloc = ZBX_INIT_UPD_XML_SIZE;
	page.data = (char *)zbx_malloc(NULL, page.alloc);

	if (SUCCEED != vmware_curl_set_header(easyhandle, service->major_version, &headers, &evt_data->error))
		goto clean;

	if (SUCCEED != vmware_service_authenticate(service, easyhandle, &page, config_source_ip, config_vmware_timeout,
			&evt_data->error))
	{
		goto clean;
	}

	/* skip collection of event data if we don't know where	*/
	/* we stopped last time or item can't accept values 	*/
	if (ZBX_VMWARE_EVENT_KEY_UNINITIALIZED != evt_last_key && 0 == evt_skip_old && 0 != shmem_free_sz &&
			0 != service->eventlog.top_time && SUCCEED != vmware_service_get_event_data(service,
			easyhandle, evt_last_key, evt_last_ts, shmem_free_sz, evt_severity, evt_end_time,
			&evt_skip_old, &evt_data->events, &events_sz, &evt_top_key, &evt_top_time, &evt_data->error))
	{
		goto clean;
	}

	if (0 != evt_skip_old || 0 == service->eventlog.top_key)	/* or first run after reboot */
	{
		char	*error = NULL;

		/* May not be present */
		if (SUCCEED != vmware_service_get_last_event_data(service, easyhandle, &evt_data->events,
				&events_sz, &error))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Unable retrieve lastevent value: %s.", error);
			zbx_free(error);
		}
		else
		{
			evt_top_key = evt_data->events.values[0]->key;
			evt_top_time = evt_data->events.values[0]->timestamp;

			if (0 == evt_skip_old)
				zbx_vector_vmware_event_ptr_clear_ext(&evt_data->events, vmware_event_free);
			else
				evt_skip_old = 0;
		}
	}

	if (SUCCEED != vmware_service_logout(service, easyhandle, &evt_data->error))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot close vmware connection: %s.", evt_data->error);
		zbx_free(evt_data->error);
	}

	ret = SUCCEED;
clean:
	curl_slist_free_all(headers);
	curl_easy_cleanup(easyhandle);
	zbx_free(page.data);
out:
	zbx_vmware_lock();

	/* statistically we can expect the same number of events next time */
	if (service->eventlog.top_key < evt_top_key)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() update top_time:" ZBX_FS_UI64 "/" ZBX_FS_TIME_T " top_key:"
				ZBX_FS_UI64 "/" ZBX_FS_UI64 " last_key:" ZBX_FS_UI64 " last_ts:" ZBX_FS_TIME_T,
				__func__, service->eventlog.top_time, evt_top_time, service->eventlog.top_key,
				evt_top_key, service->eventlog.last_key, service->eventlog.last_ts);

		if (0 == service->eventlog.top_key)	/* first run after reboot */
			service->eventlog.expect_num = evt_top_key - service->eventlog.last_key;

		service->eventlog.top_key = evt_top_key;
		service->eventlog.top_time = evt_top_time;
	}

	if (0 < evt_data->events.values_num)
	{
		if (0 != service->eventlog.oom)
			service->eventlog.oom = 0;

		service->eventlog.expect_num = service->eventlog.top_key - evt_data->events.values[0]->key;
		events_sz += vmware_service_evt_vector_memsize(&evt_data->events);

		if (0 == service->eventlog.last_key || vmware_shmem_get_vmware_mem()->free_size < events_sz ||
				SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
		{
			events_sz -= vmware_evt_strpool_overlap_mem();

			if (vmware_shmem_get_vmware_mem()->free_size < events_sz)
			{
				service->eventlog.req_sz = events_sz;
				service->eventlog.oom = 1;
				zbx_vector_vmware_event_ptr_clear_ext(&evt_data->events, vmware_event_free);

				zabbix_log(LOG_LEVEL_WARNING, "Postponed VMware events requires up to " ZBX_FS_UI64
						" bytes of free VMwareCache memory, while currently only " ZBX_FS_UI64
						" bytes are free. VMwareCache memory usage (free/strpool/total): "
						ZBX_FS_UI64 " / " ZBX_FS_UI64 " / " ZBX_FS_UI64, events_sz,
						vmware_shmem_get_vmware_mem()->free_size,
						vmware_shmem_get_vmware_mem()->free_size,
						zbx_vmware_get_vmware()->strpool_sz,
						vmware_shmem_get_vmware_mem()->total_size);
			}
			else
			{
				int	level;

				level = 0 == service->eventlog.last_key ? LOG_LEVEL_WARNING : LOG_LEVEL_DEBUG;

				zabbix_log(level, "Processed VMware events requires up to " ZBX_FS_UI64
						" bytes of free VMwareCache memory. VMwareCache memory usage"
						" (free/strpool/total): " ZBX_FS_UI64 " / " ZBX_FS_UI64 " / "
						ZBX_FS_UI64, events_sz, vmware_shmem_get_vmware_mem()->free_size,
						zbx_vmware_get_vmware()->strpool_sz,
						vmware_shmem_get_vmware_mem()->total_size);
			}
		}
	}

	if (0 == evt_pause)
	{
		vmware_eventlog_data_shared_free(service->eventlog.data);
		service->eventlog.data = vmware_shmem_eventlog_data_dup(evt_data);
		service->eventlog.skip_old = evt_skip_old;
	}

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
		zbx_shmem_dump_stats(LOG_LEVEL_DEBUG, vmware_shmem_get_vmware_mem());

	zbx_snprintf(msg, sizeof(msg), "Events (number/expect/factor/chunk/endtime):%d / %d / %.2f / " ZBX_FS_UI64
			" / " ZBX_FS_TIME_T " VMwareCache memory usage (free/strpool/total): " ZBX_FS_UI64 " / "
			ZBX_FS_UI64 " / " ZBX_FS_UI64,
			NULL != service->eventlog.data ? service->eventlog.data->events.values_num : 0,
			service->eventlog.expect_num, shmem_factor, shmem_free_sz, evt_end_time,
			vmware_shmem_get_vmware_mem()->free_size, zbx_vmware_get_vmware()->strpool_sz,
			vmware_shmem_get_vmware_mem()->total_size);

	zbx_vmware_unlock();

	vmware_eventlog_data_free(evt_data);
	evt_msg_strpool_destroy();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s \tprocessed:" ZBX_FS_SIZE_T " bytes of data. %s", __func__,
			zbx_result_string(ret), (zbx_fs_size_t)page.alloc, msg);

	return ret;
#undef ZBX_INIT_UPD_XML_SIZE
}

#endif /* defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL) */
