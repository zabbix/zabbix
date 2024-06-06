/*
** Copyright (C) 2001-2024 Zabbix SIA
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
#include "vmware_internal.h"

#include "zbxstr.h"
#include "zbxtime.h"
#include "zbxshmem.h"
#include "zbxnix.h"
#include "zbxxml.h"
#include "zbxalgo.h"
#ifdef HAVE_LIBXML2
#	include <libxml/xpath.h>
#endif

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

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

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store vmware event                   *
 *                                                                            *
 * Parameters: event - [IN] vmware event                                      *
 *                                                                            *
 ******************************************************************************/
void	vmware_event_free(zbx_vmware_event_t *event)
{
	evt_msg_strpool_strfree(event->message);
	zbx_free(event);
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
 *             event_session - [OUT] pointer to output variable               *
 *             error         - [OUT] error message in case of failure         *
 *                                                                            *
 * Return value: SUCCEED - operation has completed successfully               *
 *               FAIL    - operation has failed                               *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_event_session(const zbx_vmware_service_t *service, CURL *easyhandle,
		char **event_session, char **error)
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
		if (0 != ((1 << i) & service->eventlog.severity))
		{
			zbx_snprintf_alloc(&filter, &alloc_len, &offset, ZBX_POST_VMWARE_EVENT_FILTER_SPEC_CATEGORY,
					levels[i]);
		}
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
	zbx_xml_free_doc(doc);
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
 *             alloc_sz       - [OUT] allocated memory size for events        *
 *                                                                            *
 * Return value: SUCCEED - operation has completed successfully               *
 *               FAIL    - operation has failed                               *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_put_event_data(zbx_vector_vmware_event_ptr_t *events, zbx_id_xmlnode_t xml_event,
		xmlDoc *xdoc, const zbx_hashset_t *evt_severities, zbx_uint64_t *alloc_sz)
{
#define ZBX_HOSTINFO_NODES_DATACENTER		0x01
#define ZBX_HOSTINFO_NODES_COMPRES		0x02
#define ZBX_HOSTINFO_NODES_HOST			0x04
#define ZBX_HOSTINFO_NODES_VM			0x08
#define ZBX_HOSTINFO_NODES_DS			0x10
#define ZBX_HOSTINFO_NODES_NET			0x20
#define ZBX_HOSTINFO_NODES_DVS			0x40
#define ZBX_HOSTINFO_NODES_MASK_ALL									\
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
	zbx_uint64_t			sz;
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
	event->message = evt_msg_strpool_strdup(message, &sz);
	zbx_free(message);
	zbx_vector_vmware_event_ptr_append(events, event);

	if (0 < sz)
		*alloc_sz += zbx_shmem_required_chunk_size(sz);

	return SUCCEED;
#undef ZBX_HOSTINFO_NODES_DATACENTER
#undef ZBX_HOSTINFO_NODES_COMPRES
#undef ZBX_HOSTINFO_NODES_HOST
#undef ZBX_HOSTINFO_NODES_VM
#undef ZBX_HOSTINFO_NODES_DS
#undef ZBX_HOSTINFO_NODES_NET
#undef ZBX_HOSTINFO_NODES_DVS
#undef ZBX_HOSTINFO_NODES_MASK_ALL

#	undef	ZBX_XPATH_EVT_INFO
#	undef	ZBX_XPATH_EVT_ARGUMENT
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
 * Parameters: events     - [IN/OUT] array of parsed events                   *
 *             last_key   - [IN] key of last parsed event                     *
 *             last_ts    - [IN] the timestamp of last parsed event           *
 *             is_prop    - [IN] read events from RetrieveProperties xml      *
 *             xdoc       - [IN] xml document with eventlog records           *
 *             eventlog   - [IN] VMware event log state                       *
 *             alloc_sz   - [OUT] allocated memory size for events            *
 *             node_count - [OUT] count of xml event nodes                    *
 *             skip_old   - [OUT] detected event key reset                    *
 *                                                                            *
 * Return value: count of events successfully parsed                          *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_parse_event_data(zbx_vector_vmware_event_ptr_t *events, zbx_uint64_t last_key,
		time_t last_ts, const int is_prop, xmlDoc *xdoc, const zbx_vmware_eventlog_state_t *eventlog,
		zbx_uint64_t *alloc_sz, int *node_count, unsigned char *skip_old)
{
#	define LAST_KEY(evs)	(((const zbx_vmware_event_t *)evs->values[evs->values_num - 1])->key)

	zbx_vector_id_xmlnode_t	ids;
	int			parsed_num = 0;
	char			*value;
	xmlXPathContext		*xpathCtx;
	xmlXPathObject		*xpathObj;
	xmlNodeSetPtr		nodeset;
	static int		is_clear = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() last_key:" ZBX_FS_UI64, __func__, last_key);

	xpathCtx = xmlXPathNewContext(xdoc);
	zbx_vector_id_xmlnode_create(&ids);

	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)(0 == is_prop ? "/*/*/*"
			ZBX_XPATH_LN("returnval") : "/*/*/*" ZBX_XPATH_LN("returnval") "/*/*/*"ZBX_XPATH_LN("Event")),
			xpathCtx)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot make evenlog list parsing query.");
		goto clean;
	}

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
	{
		if (NULL != node_count)
			*node_count = 0;

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

		if (0 == key && 0 == isdigit(value[('-' == *value || '+' == *value) ? 1 : 0 ]))
		{
			zabbix_log(LOG_LEVEL_TRACE, "skipping eventlog key '%s', not a number", value);
			zbx_free(value);
			continue;
		}

		zbx_free(value);

		xml_event.created_time = vmware_service_parse_event_ts(xdoc, nodeset->nodeTab[i], key);

		if (key <= last_key)
		{
			if (xml_event.created_time <= last_ts)
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
		zbx_vector_vmware_event_ptr_reserve(events, (size_t)(ids.values_num + events->values_alloc));

		/* validate that last event from "latestPage" is connected with first event from ReadPreviousEvents */
		if (0 != events->values_num && LAST_KEY(events) != ids.values[ids.values_num -1].id + 1)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() events:%d is_clear:%d id gap:%d severity:%d", __func__,
					events->values_num, is_clear,
					(int)(LAST_KEY(events) - (ids.values[ids.values_num -1].id + 1)),
					(int)eventlog->severity);

			/* if sequence of events is not continuous, ignore events from "latestPage" property */
			/* except when events are filtered by severity */
			if (0 != is_clear && 0 == eventlog->severity)
				zbx_vector_vmware_event_ptr_clear_ext(events, vmware_event_free);
		}

		/* we are reading "scrollable views" in reverse chronological order, */
		/* so inside a "scrollable view" latest events should come first too */
		for (int i = ids.values_num - 1; i >= 0; i--)
		{
			if (SUCCEED == vmware_service_put_event_data(events, ids.values[i], xdoc,
					&eventlog->evt_severities, alloc_sz))
			{
				parsed_num++;
			}
		}
	}
	else if (0 != last_key && 0 != events->values_num && LAST_KEY(events) != last_key + 1)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() events:%d is_clear:%d last_key id gap:%d severity:%d", __func__,
				events->values_num, is_clear, (int)(LAST_KEY(events) - (last_key + 1)),
				(int)eventlog->severity);

		/* if sequence of events is not continuous, ignore events from "latestPage" property */
		/* except when events are filtered by severity */
		if (0 != is_clear && 0 == eventlog->severity)
			zbx_vector_vmware_event_ptr_clear_ext(events, vmware_event_free);
	}
clean:
	zbx_vector_id_xmlnode_destroy(&ids);
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);
	is_clear = is_prop;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() parsed:%d", __func__, parsed_num);

	return parsed_num;

#	undef LAST_KEY
}

/******************************************************************************
 *                                                                            *
 * Parameters: service    - [IN] vmware service                               *
 *             easyhandle - [IN] CURL handle                                  *
 *             last_key   - [IN] ID of last processed event                   *
 *             last_ts    - [IN] the create time of last processed event      *
 *             skip_old   - [IN/OUT] reset last_key of event                  *
 *             events     - [OUT] pointer to output variable                  *
 *             alloc_sz   - [OUT] allocated memory size for events            *
 *             error      - [OUT] error message in case of failure            *
 *                                                                            *
 * Return value: SUCCEED - operation has completed successfully               *
 *               FAIL    - operation has failed                               *
 *                                                                            *
 ******************************************************************************/
int	vmware_service_get_event_data(const zbx_vmware_service_t *service, CURL *easyhandle, zbx_uint64_t last_key,
		time_t last_ts, unsigned char *skip_old, zbx_vector_vmware_event_ptr_t *events, zbx_uint64_t *alloc_sz,
		char **error)
{
#	define ATTEMPTS_NUM	4
#	define EVENT_TAG	1
#	define RETURNVAL_TAG	0
#	define LAST_KEY(evs)	(((const zbx_vmware_event_t *)evs->values[evs->values_num - 1])->key)

	char		*event_session = NULL, *err = NULL;
	int		ret = FAIL, node_count = 1, soap_retry = ATTEMPTS_NUM,
			soap_count = 5; /* 10 - initial value of eventlog records number in one response */
	xmlDoc		*doc = NULL;
	zbx_uint64_t	eventlog_last_key;
	time_t		eventlog_last_ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != vmware_service_get_event_session(service, easyhandle, &event_session, error))
		goto out;

	if (SUCCEED != vmware_service_reset_event_history_collector(easyhandle, event_session, error))
		goto end_session;

	if (NULL != service->data && 0 != service->data->events.values_num &&
			((const zbx_vmware_event_t *)service->data->events.values[0])->key > last_key)
	{
		eventlog_last_key = ((const zbx_vmware_event_t *)service->data->events.values[0])->key;
		eventlog_last_ts = ((const zbx_vmware_event_t *)service->data->events.values[0])->timestamp;
	}
	else
	{
		eventlog_last_key = last_key;
		eventlog_last_ts = last_ts;
	}

	if (SUCCEED != vmware_service_get_event_latestpage(service, easyhandle, event_session, &doc, error))
		goto end_session;

	if (0 < vmware_service_parse_event_data(events, eventlog_last_key, eventlog_last_ts, EVENT_TAG, doc,
			&service->eventlog, alloc_sz, NULL, skip_old) &&
			(0 != *skip_old || LAST_KEY(events) == eventlog_last_key + 1))
	{
		zabbix_log(LOG_LEVEL_TRACE, "%s() latestPage events:%d", __func__, events->values_num);

		ret = SUCCEED;
		goto end_session;
	}

	do
	{
		zbx_xml_free_doc(doc);
		doc = NULL;

		if ((ZBX_MAXQUERYMETRICS_UNLIMITED / 2) >= soap_count)
			soap_count = soap_count * 2;
		else if (ZBX_MAXQUERYMETRICS_UNLIMITED != soap_count)
			soap_count = ZBX_MAXQUERYMETRICS_UNLIMITED;

		if (0 != events->values_num && (LAST_KEY(events) - eventlog_last_key -1) < (unsigned int)soap_count)
		{
			soap_count = (int)(LAST_KEY(events) - eventlog_last_key - 1);
		}

		if (!ZBX_IS_RUNNING() || (0 < soap_count && SUCCEED != vmware_service_read_previous_events(easyhandle,
				event_session, soap_count, &doc, error)))
		{
			goto end_session;
		}

		if (0 != node_count)
			soap_retry = ATTEMPTS_NUM;
	}
	while ((0 < vmware_service_parse_event_data(events, eventlog_last_key, eventlog_last_ts, RETURNVAL_TAG, doc,
			&service->eventlog, alloc_sz, &node_count, skip_old) ||
			(0 == node_count && 0 < soap_retry--)) && 0 == *skip_old);

	if (0 != eventlog_last_key && 0 != events->values_num && LAST_KEY(events) != eventlog_last_key + 1)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() events:%d id gap:%d", __func__, events->values_num,
				(int)(LAST_KEY(events) - (eventlog_last_key + 1)));
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
	zbx_xml_free_doc(doc);

	if (SUCCEED == ret && 10 == soap_count && 0 == events->values_num && 0 == *skip_old)
		zabbix_log(LOG_LEVEL_WARNING, "vmware events collector returned empty result");

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s events:%d", __func__, zbx_result_string(ret), events->values_num);

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
 *             alloc_sz   - [OUT] allocated memory size for events            *
 *             error      - [OUT] error message in case of failure            *
 *                                                                            *
 * Return value: SUCCEED - operation has completed successfully               *
 *               FAIL    - operation has failed                               *
 *                                                                            *
 ******************************************************************************/
int	vmware_service_get_last_event_data(const zbx_vmware_service_t *service, CURL *easyhandle,
		zbx_vector_vmware_event_ptr_t *events, zbx_uint64_t *alloc_sz, char **error)
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
			alloc_sz))
	{
		*error = zbx_dsprintf(*error, "Cannot retrieve last eventlog data for key "ZBX_FS_UI64, xml_event.id);
		goto clean;
	}

	ret = SUCCEED;
clean:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);
out:
	zbx_xml_free_doc(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s last_key:" ZBX_FS_UI64, __func__, zbx_result_string(ret),
			(SUCCEED == ret ? xml_event.id : 0));
	return ret;

#	undef ZBX_POST_VMWARE_LASTEVENT
}

#endif /* defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL) */
