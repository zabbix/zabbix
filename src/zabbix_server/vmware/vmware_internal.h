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
#ifndef ZABBIX_VMWARE_INTERNAL_H
#define ZABBIX_VMWARE_INTERNAL_H

#include "config.h"

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

#include "zbxxml.h"

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
#endif	/* defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL) */

#endif	/* ZABBIX_VMWARE_INTERNAL_H */
