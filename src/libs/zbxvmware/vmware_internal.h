/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
#ifndef ZABBIX_VMWARE_INTERNAL_H
#define ZABBIX_VMWARE_INTERNAL_H

#include "config.h"

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

#include "zbxxml.h"

#define ZBX_XPATH_PROP_OBJECT(type)	ZBX_XPATH_PROP_OBJECT_ID(type, "") "/"


#define		VMWARE_SHORT_STR_LEN	MAX_STRING_LEN / 8

#define ZBX_POST_VSPHERE_HEADER									\
		"<?xml version=\"1.0\" encoding=\"UTF-8\"?>"					\
		"<SOAP-ENV:Envelope"								\
			" xmlns:ns0=\"urn:vim25\""						\
			" xmlns:ns1=\"http://schemas.xmlsoap.org/soap/envelope/\""		\
			" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\""		\
			" xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\">"	\
			"<SOAP-ENV:Header/>"							\
			"<ns1:Body>"
#define ZBX_POST_VSPHERE_FOOTER									\
			"</ns1:Body>"								\
		"</SOAP-ENV:Envelope>"

/* VMware service object name mapping for vcenter and vsphere installations */
typedef struct
{
	const char	*performance_manager;
	const char	*session_manager;
	const char	*event_manager;
	const char	*property_collector;
	const char	*root_folder;
}
zbx_vmware_service_objects_t;

#define VMWARE_SERVICE_OBJECTS_ARR_SIZE	3
zbx_vmware_service_objects_t	*get_vmware_service_objects(void);

int	zbx_vmware_service_update(zbx_vmware_service_t *service, const char *config_source_ip,
		int config_vmware_timeout, int cache_update_period);
int	zbx_vmware_service_update_tags(zbx_vmware_service_t *service, const char *config_source_ip,
		int config_vmware_timeout);
int	zbx_vmware_job_remove(zbx_vmware_job_t *job);
void	zbx_vmware_shared_tags_error_set(const char *error, zbx_vmware_data_tags_t *data_tags);
void	zbx_vmware_shared_tags_replace(const zbx_vector_vmware_entity_tags_t *src, zbx_vmware_data_tags_t *dst);
int	zbx_soap_post(const char *fn_parent, CURL *easyhandle, const char *request, xmlDoc **xdoc,
		char **token , char **error);

#define zbx_xml_free_doc(xdoc)		if (NULL != xdoc)		\
						xmlFreeDoc(xdoc)

#define ZBX_VPXD_STATS_MAXQUERYMETRICS				64
#define ZBX_MAXQUERYMETRICS_UNLIMITED				1000
#define ZBX_VCENTER_LESS_THAN_6_5_0_STATS_MAXQUERYMETRICS	64
#define ZBX_VCENTER_6_5_0_AND_MORE_STATS_MAXQUERYMETRICS	256

/* value of custom query for a specific instance */
typedef struct
{
	char			*response;
#define	ZBX_VMWARE_CQV_EMPTY	0
#define	ZBX_VMWARE_CQV_VALUE	1
#define	ZBX_VMWARE_CQV_ERROR	2
	unsigned char		status;
	zbx_vmware_cust_query_t	*instance;
}
zbx_vmware_cq_value_t;

ZBX_PTR_VECTOR_DECL(cq_value_ptr, zbx_vmware_cq_value_t *)

/* VMware alarms cache information */
typedef struct
{
	char	*alarm;
	char	*name;
	char	*system_name;
	char	*description;
	int	enabled;
}
zbx_vmware_alarm_details_t;

ZBX_PTR_VECTOR_DECL(vmware_alarm_details_ptr, zbx_vmware_alarm_details_t *)

typedef struct
{
	zbx_vector_vmware_alarm_ptr_t		*alarms;
	zbx_vector_vmware_alarm_details_ptr_t	details;
}
zbx_vmware_alarms_data_t;
/* VMware alarms cache information END */

#define ZBX_XPATH_PROP_OBJECT_ID(type, id)								\
	"/*/*/*/*/*[local-name()='objects'][*[local-name()='obj'][@type='" type "']" id "][1]"

#define ZBX_XPATH_PROP_NAME_NODE(property)								\
	"*[local-name()='propSet'][*[local-name()='name'][text()='" property "']][1]/*[local-name()='val']"

#define ZBX_XPATH_PROP_NAME(property)									\
	"/*/*/*/*/*/" ZBX_XPATH_PROP_NAME_NODE(property)

void	vmware_props_free(char **props, int props_num);

typedef struct
{
	const char	*property_collector;
	CURL		*easyhandle;
	char		*token;
}
zbx_property_collection_iter;

int	zbx_property_collection_init(CURL *easyhandle, const char *property_collection_query,
		const char *property_collector, const char *fn_parent, zbx_property_collection_iter **iter,
		xmlDoc **xdoc, char **error);

int	zbx_property_collection_next(const char *fn_parent, zbx_property_collection_iter *iter, xmlDoc **xdoc,
		char **error);

void	zbx_property_collection_free(zbx_property_collection_iter *iter);

#define ZBX_XPATH_OBJECTS_BY_TYPE(type)									\
	"/*/*/*/*/*[local-name()='objects'][*[local-name()='obj'][@type='" type "']]"

int	vmware_ds_id_compare(const void *d1, const void *d2);

char	*vmware_cq_prop_soap_request(const zbx_vector_cq_value_ptr_t *cq_values, const char *soap_type,
		const char *obj_id, zbx_vector_cq_value_ptr_t *cqvs);

typedef int	(*nodeprocfunc_t)(void *, char **);

typedef struct
{
	const char	*name;
	const char	*xpath;
	nodeprocfunc_t	func;
	unsigned short	vc_min;
}
zbx_vmware_propmap_t;

char	**xml_read_props(xmlDoc *xdoc, const zbx_vmware_propmap_t *propmap, int props_num);

int	vmware_service_get_alarms_data(const char *func_parent, const zbx_vmware_service_t *service,
		CURL *easyhandle, xmlDoc *xdoc, xmlNode *node, zbx_vector_str_t *ids,
		zbx_vmware_alarms_data_t *alarms_data, char **error);

void	vmware_service_cq_prop_value(const char *fn_parent, xmlDoc *xdoc, zbx_vector_cq_value_ptr_t *cqvs);

#define ZBX_XPATH_VM_HARDWARE(property)									\
	"/*/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='config.hardware']]"	\
		"/*[local-name()='val']/*[local-name()='" property "']"

#define ZBX_XPATH_OBJS_BY_TYPE(type)									\
	"/*/*/*/*/*[local-name()='objects']/*[local-name()='obj'][@type='" type "']"

char	*evt_msg_strpool_strdup(const char *str, zbx_uint64_t *len);
void	evt_msg_strpool_strfree(char *str);

#endif	/* defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL) */

#endif	/* ZABBIX_VMWARE_INTERNAL_H */
