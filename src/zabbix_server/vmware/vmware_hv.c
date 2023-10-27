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

#include "config.h"

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

#include "vmware_hv.h"
#include "vmware_shmem.h"
#include "vmware_internal.h"
#include "vmware.h"

#include "zbxstr.h"
#include "zbxip.h"
#include "zbxxml.h"
#ifdef HAVE_LIBXML2
#	include <libxml/xpath.h>
#endif

#define ZBX_XPATH_HV_SENSOR_STATUS(node, sensor)							\
	ZBX_XPATH_PROP_NAME(node) "/*[local-name()='HostNumericSensorInfo']"				\
		"[*[local-name()='name'][text()='" sensor "']]"						\
		"/*[local-name()='healthState']/*[local-name()='key']"

#define ZBX_XPATH_PROP_OBJECT(type)	ZBX_XPATH_PROP_OBJECT_ID(type, "") "/"

#define ZBX_HVPROPMAP_EXT(property, func, ver)								\
	{property, ZBX_XPATH_PROP_OBJECT(ZBX_VMWARE_SOAP_HV) ZBX_XPATH_PROP_NAME_NODE(property), func, ver}
#define ZBX_HVPROPMAP(property)			\
	ZBX_HVPROPMAP_EXT(property, NULL, 0)
#define ZBX_VMPROPMAP(property)										\
	{property, ZBX_XPATH_PROP_OBJECT(ZBX_VMWARE_SOAP_VM) ZBX_XPATH_PROP_NAME_NODE(property), NULL, 0}

static zbx_vmware_propmap_t	hv_propmap[] = {
	ZBX_HVPROPMAP("summary.quickStats.overallCpuUsage"),	/* ZBX_VMWARE_HVPROP_OVERALL_CPU_USAGE */
	ZBX_HVPROPMAP("summary.config.product.fullName"),	/* ZBX_VMWARE_HVPROP_FULL_NAME */
	ZBX_HVPROPMAP("summary.hardware.numCpuCores"),		/* ZBX_VMWARE_HVPROP_HW_NUM_CPU_CORES */
	ZBX_HVPROPMAP("summary.hardware.cpuMhz"),		/* ZBX_VMWARE_HVPROP_HW_CPU_MHZ */
	ZBX_HVPROPMAP("summary.hardware.cpuModel"),		/* ZBX_VMWARE_HVPROP_HW_CPU_MODEL */
	ZBX_HVPROPMAP("summary.hardware.numCpuThreads"), 	/* ZBX_VMWARE_HVPROP_HW_NUM_CPU_THREADS */
	ZBX_HVPROPMAP("summary.hardware.memorySize"), 		/* ZBX_VMWARE_HVPROP_HW_MEMORY_SIZE */
	ZBX_HVPROPMAP("summary.hardware.model"), 		/* ZBX_VMWARE_HVPROP_HW_MODEL */
	ZBX_HVPROPMAP("summary.hardware.uuid"), 		/* ZBX_VMWARE_HVPROP_HW_UUID */
	ZBX_HVPROPMAP("summary.hardware.vendor"), 		/* ZBX_VMWARE_HVPROP_HW_VENDOR */
	ZBX_HVPROPMAP("summary.quickStats.overallMemoryUsage"),	/* ZBX_VMWARE_HVPROP_MEMORY_USED */
	{NULL, ZBX_XPATH_HV_SENSOR_STATUS("runtime.healthSystemRuntime.systemHealthInfo.numericSensorInfo",
			"VMware Rollup Health State"), NULL, 0},/* ZBX_VMWARE_HVPROP_HEALTH_STATE */
	ZBX_HVPROPMAP("summary.quickStats.uptime"),		/* ZBX_VMWARE_HVPROP_UPTIME */
	ZBX_HVPROPMAP("summary.config.product.version"),	/* ZBX_VMWARE_HVPROP_VERSION */
	ZBX_HVPROPMAP("summary.config.name"),			/* ZBX_VMWARE_HVPROP_NAME */
	ZBX_HVPROPMAP("overallStatus"),				/* ZBX_VMWARE_HVPROP_STATUS */
	ZBX_HVPROPMAP("runtime.inMaintenanceMode"),		/* ZBX_VMWARE_HVPROP_MAINTENANCE */
	ZBX_HVPROPMAP_EXT("summary.runtime.healthSystemRuntime.systemHealthInfo.numericSensorInfo",
			zbx_xmlnode_to_json, 0),		/* ZBX_VMWARE_HVPROP_SENSOR */
	{"config.network.dnsConfig", "concat("			/* ZBX_VMWARE_HVPROP_NET_NAME */
			ZBX_XPATH_PROP_NAME("config.network.dnsConfig") "/*[local-name()='hostName']" ",'.',"
			ZBX_XPATH_PROP_NAME("config.network.dnsConfig") "/*[local-name()='domainName'])", NULL, 0},
	ZBX_HVPROPMAP("parent"),				/* ZBX_VMWARE_HVPROP_PARENT */
	ZBX_HVPROPMAP("runtime.connectionState"),		/* ZBX_VMWARE_HVPROP_CONNECTIONSTATE */
	ZBX_HVPROPMAP_EXT("hardware.systemInfo.serialNumber", NULL, 67),/* ZBX_VMWARE_HVPROP_HW_SERIALNUMBER */
	ZBX_HVPROPMAP_EXT("runtime.healthSystemRuntime.hardwareStatusInfo",
			zbx_xmlnode_to_json, 0)			/* ZBX_VMWARE_HVPROP_HW_SENSOR */
};

static zbx_vmware_propmap_t	vm_propmap[] = {
	ZBX_VMPROPMAP("summary.config.numCpu"),			/* ZBX_VMWARE_VMPROP_CPU_NUM */
	ZBX_VMPROPMAP("summary.quickStats.overallCpuUsage"),	/* ZBX_VMWARE_VMPROP_CPU_USAGE */
	ZBX_VMPROPMAP("summary.config.name"),			/* ZBX_VMWARE_VMPROP_NAME */
	ZBX_VMPROPMAP("summary.config.memorySizeMB"),		/* ZBX_VMWARE_VMPROP_MEMORY_SIZE */
	ZBX_VMPROPMAP("summary.quickStats.balloonedMemory"),	/* ZBX_VMWARE_VMPROP_MEMORY_SIZE_BALLOONED */
	ZBX_VMPROPMAP("summary.quickStats.compressedMemory"),	/* ZBX_VMWARE_VMPROP_MEMORY_SIZE_COMPRESSED */
	ZBX_VMPROPMAP("summary.quickStats.swappedMemory"),	/* ZBX_VMWARE_VMPROP_MEMORY_SIZE_SWAPPED */
	ZBX_VMPROPMAP("summary.quickStats.guestMemoryUsage"),	/* ZBX_VMWARE_VMPROP_MEMORY_SIZE_USAGE_GUEST */
	ZBX_VMPROPMAP("summary.quickStats.hostMemoryUsage"),	/* ZBX_VMWARE_VMPROP_MEMORY_SIZE_USAGE_HOST */
	ZBX_VMPROPMAP("summary.quickStats.privateMemory"),	/* ZBX_VMWARE_VMPROP_MEMORY_SIZE_PRIVATE */
	ZBX_VMPROPMAP("summary.quickStats.sharedMemory"),	/* ZBX_VMWARE_VMPROP_MEMORY_SIZE_SHARED */
	ZBX_VMPROPMAP("summary.runtime.powerState"),		/* ZBX_VMWARE_VMPROP_POWER_STATE */
	ZBX_VMPROPMAP("summary.storage.committed"),		/* ZBX_VMWARE_VMPROP_STORAGE_COMMITED */
	ZBX_VMPROPMAP("summary.storage.unshared"),		/* ZBX_VMWARE_VMPROP_STORAGE_UNSHARED */
	ZBX_VMPROPMAP("summary.storage.uncommitted"),		/* ZBX_VMWARE_VMPROP_STORAGE_UNCOMMITTED */
	ZBX_VMPROPMAP("summary.quickStats.uptimeSeconds"),	/* ZBX_VMWARE_VMPROP_UPTIME */
	ZBX_VMPROPMAP("guest.ipAddress"),			/* ZBX_VMWARE_VMPROP_IPADDRESS */
	ZBX_VMPROPMAP("guest.hostName"),			/* ZBX_VMWARE_VMPROP_GUESTHOSTNAME */
	ZBX_VMPROPMAP("guest.guestFamily"),			/* ZBX_VMWARE_VMPROP_GUESTFAMILY */
	ZBX_VMPROPMAP("guest.guestFullName"),			/* ZBX_VMWARE_VMPROP_GUESTFULLNAME */
	ZBX_VMPROPMAP("parent"),				/* ZBX_VMWARE_VMPROP_FOLDER */
	{"layoutEx</ns0:pathSet><ns0:pathSet>snapshot",		/* ZBX_VMWARE_VMPROP_SNAPSHOT */
			ZBX_XPATH_PROP_OBJECT(ZBX_VMWARE_SOAP_VM) ZBX_XPATH_PROP_NAME_NODE("snapshot"),
			vmware_service_get_vm_snapshot, 0},
	{"datastore", ZBX_XPATH_PROP_OBJECT(ZBX_VMWARE_SOAP_VM)/* ZBX_VMWARE_VMPROP_DATASTOREID */
			ZBX_XPATH_PROP_NAME_NODE("datastore") ZBX_XPATH_LN("ManagedObjectReference"), NULL, 0},
	ZBX_VMPROPMAP("summary.runtime.consolidationNeeded"),	/* ZBX_VMWARE_VMPROP_CONSOLIDATION_NEEDED */
	ZBX_VMPROPMAP("resourcePool"),				/* ZBX_VMWARE_VMPROP_RESOURCEPOOL */
	ZBX_VMPROPMAP("guest.toolsVersion"),			/* ZBX_VMWARE_VMPROP_TOOLS_VERSION */
	ZBX_VMPROPMAP("guest.toolsRunningStatus"),		/* ZBX_VMWARE_VMPROP_TOOLS_RUNNING_STATUS */
	ZBX_VMPROPMAP("guest.guestState")			/* ZBX_VMWARE_VMPROP_STATE */
};

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store virtual machine         *
 *                                                                            *
 * Parameters: vm   - [IN] the virtual machine                                *
 *                                                                            *
 ******************************************************************************/
static void	vmware_vm_shared_free(zbx_vmware_vm_t *vm)
{
	zbx_vector_ptr_clear_ext(&vm->devs, (zbx_clean_func_t)vmware_shmem_dev_free);
	zbx_vector_ptr_destroy(&vm->devs);

	zbx_vector_ptr_clear_ext(&vm->file_systems, (zbx_mem_free_func_t)vmware_shmem_fs_free);
	zbx_vector_ptr_destroy(&vm->file_systems);

	zbx_vector_vmware_custom_attr_clear_ext(&vm->custom_attrs, vmware_shmem_custom_attr_free);
	zbx_vector_vmware_custom_attr_destroy(&vm->custom_attrs);

	zbx_vector_str_clear_ext(&vm->alarm_ids, vmware_shared_strfree);
	zbx_vector_str_destroy(&vm->alarm_ids);

	if (NULL != vm->uuid)
		vmware_shared_strfree(vm->uuid);

	if (NULL != vm->id)
		vmware_shared_strfree(vm->id);

	vmware_shmem_props_free(vm->props, ZBX_VMWARE_VMPROPS_NUM);

	vmware_shmem_vm_free(vm);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees shared resources allocated to store vmware hypervisor       *
 *                                                                            *
 * Parameters: hv   - [IN] the vmware hypervisor                              *
 *                                                                            *
 ******************************************************************************/
void	vmware_hv_shared_clean(zbx_vmware_hv_t *hv)
{
	zbx_vector_vmware_dsname_clear_ext(&hv->dsnames, vmware_shmem_dsname_free);
	zbx_vector_vmware_dsname_destroy(&hv->dsnames);

	zbx_vector_ptr_clear_ext(&hv->vms, (zbx_clean_func_t)vmware_vm_shared_free);
	zbx_vector_ptr_destroy(&hv->vms);

	zbx_vector_vmware_pnic_clear_ext(&hv->pnics, vmware_shmem_pnic_free);
	zbx_vector_vmware_pnic_destroy(&hv->pnics);

	zbx_vector_str_clear_ext(&hv->alarm_ids, vmware_shared_strfree);
	zbx_vector_str_destroy(&hv->alarm_ids);

	zbx_vector_vmware_diskinfo_clear_ext(&hv->diskinfo, vmware_shmem_diskinfo_free);
	zbx_vector_vmware_diskinfo_destroy(&hv->diskinfo);

	if (NULL != hv->uuid)
		vmware_shared_strfree(hv->uuid);

	if (NULL != hv->id)
		vmware_shared_strfree(hv->id);

	if (NULL != hv->clusterid)
		vmware_shared_strfree(hv->clusterid);

	if (NULL != hv->datacenter_name)
		vmware_shared_strfree(hv->datacenter_name);

	if (NULL != hv->parent_name)
		vmware_shared_strfree(hv->parent_name);

	if (NULL != hv->parent_type)
		vmware_shared_strfree(hv->parent_type);

	if (NULL != hv->ip)
		vmware_shared_strfree(hv->ip);

	vmware_shmem_props_free(hv->props, ZBX_VMWARE_HVPROPS_NUM);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store Datastore name data            *
 *                                                                            *
 * Parameters: dsname   - [IN] the Datastore name                             *
 *                                                                            *
 ******************************************************************************/
static void	vmware_dsname_free(zbx_vmware_dsname_t *dsname)
{
	zbx_vector_vmware_hvdisk_destroy(&dsname->hvdisks);
	zbx_free(dsname->name);
	zbx_free(dsname->uuid);
	zbx_free(dsname);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store disk info data                 *
 *                                                                            *
 * Parameters: di - [IN] the disk info                                        *
 *                                                                            *
 ******************************************************************************/
static void	vmware_diskinfo_free(zbx_vmware_diskinfo_t *di)
{
	zbx_free(di->diskname);
	zbx_free(di->ds_uuid);
	zbx_free(di->operational_state);
	zbx_free(di->lun_type);
	zbx_free(di->model);
	zbx_free(di->vendor);
	zbx_free(di->revision);
	zbx_free(di->serial_number);

	if (NULL != di->vsan)
	{
		zbx_free(di->vsan->ssd);
		zbx_free(di->vsan->local_disk);
		zbx_free(di->vsan);
	}

	zbx_free(di);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to physical NIC data                    *
 *                                                                            *
 * Parameters: nic - [IN] the pnic of hv                                      *
 *                                                                            *
 ******************************************************************************/
static void	vmware_pnic_free(zbx_vmware_pnic_t *nic)
{
	zbx_free(nic->name);
	zbx_free(nic->driver);
	zbx_free(nic->mac);
	zbx_free(nic);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store vmware hypervisor              *
 *                                                                            *
 * Parameters: hv   - [IN] the vmware hypervisor                              *
 *                                                                            *
 ******************************************************************************/
void	vmware_hv_clean(zbx_vmware_hv_t *hv)
{
	zbx_vector_vmware_dsname_clear_ext(&hv->dsnames, vmware_dsname_free);
	zbx_vector_vmware_dsname_destroy(&hv->dsnames);

	zbx_vector_ptr_clear_ext(&hv->vms, (zbx_clean_func_t)vmware_vm_free);
	zbx_vector_ptr_destroy(&hv->vms);

	zbx_vector_vmware_pnic_clear_ext(&hv->pnics, vmware_pnic_free);
	zbx_vector_vmware_pnic_destroy(&hv->pnics);

	zbx_vector_str_clear_ext(&hv->alarm_ids, zbx_str_free);
	zbx_vector_str_destroy(&hv->alarm_ids);

	zbx_vector_vmware_diskinfo_clear_ext(&hv->diskinfo, vmware_diskinfo_free);
	zbx_vector_vmware_diskinfo_destroy(&hv->diskinfo);

	zbx_free(hv->uuid);
	zbx_free(hv->id);
	zbx_free(hv->clusterid);
	zbx_free(hv->datacenter_name);
	zbx_free(hv->parent_name);
	zbx_free(hv->parent_type);
	zbx_free(hv->ip);
	vmware_props_free(hv->props, ZBX_VMWARE_HVPROPS_NUM);
}


/******************************************************************************
 *                                                                            *
 * Purpose: gets list of ip for virtual machine network interface             *
 *                                                                            *
 * Parameters: details       - [IN] xml document containing vm data           *
 *             guestnet_node - [IN] xml node containing list of guest ips     *
 *             mac_addr      - [IN] mac address of network interface          *
 *                                                                            *
 * Return value: json with array of ip                                        *
 *                                                                            *
 ******************************************************************************/
static char	*vmware_vm_get_nic_device_ips(xmlDoc *details, xmlNode *guestnet_node, const char *mac_addr)
{
	char			xpath[VMWARE_SHORT_STR_LEN], *val = NULL;
	zbx_vector_str_t	ips;

	zbx_vector_str_create(&ips);

	zbx_snprintf(xpath, sizeof(xpath), "*[*[local-name()='macAddress']/text()='%s']" ZBX_XPATH_LN("ipAddress") ,
			mac_addr);

	if (SUCCEED == zbx_xml_node_read_values(details, guestnet_node, xpath, &ips))
	{
		struct zbx_json	json_data;
		int		i;

		zbx_json_initarray(&json_data, VMWARE_SHORT_STR_LEN);

		for (i = 0; i < ips.values_num; i++)
			zbx_json_addstring(&json_data, NULL, ips.values[i], ZBX_JSON_TYPE_STRING);

		zbx_json_close(&json_data);
		val = zbx_strdup(val, json_data.buffer);
		zbx_json_free(&json_data);
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "%s() empty list of guest ips for mac:%s", __func__, mac_addr);

	zbx_vector_str_clear_ext(&ips, zbx_str_free);
	zbx_vector_str_destroy(&ips);

	return val;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets virtual machine network interface devices' additional        *
 * properties (props member of zbx_vmware_dev_t)                              *
 *                                                                            *
 * Parameters: details - [IN] an xml document containing virtual machine data *
 *             xmlNode - [IN] an xml document node that corresponds to given  *
 *                            network interface device                        *
 *             xmlNode - [IN] an xml node containing list of guest ips        *
 *                                                                            *
 ******************************************************************************/
static char	**vmware_vm_get_nic_device_props(xmlDoc *details, xmlNode *node, xmlNode *guestnet_node)
{
	char	**props;

	props = (char **)zbx_malloc(NULL, sizeof(char *) * ZBX_VMWARE_DEV_PROPS_NUM);

	props[ZBX_VMWARE_DEV_PROPS_IFMAC] = zbx_xml_node_read_value(details, node, ZBX_XNN("macAddress"));
	props[ZBX_VMWARE_DEV_PROPS_IFCONNECTED] = zbx_xml_node_read_value(details, node,
			ZBX_XNN("connectable") ZBX_XPATH_LN("connected"));
	props[ZBX_VMWARE_DEV_PROPS_IFTYPE] = zbx_xml_node_read_prop(node, "type");
	props[ZBX_VMWARE_DEV_PROPS_IFBACKINGDEVICE] = zbx_xml_node_read_value(details, node,
			ZBX_XNN("backing") ZBX_XPATH_LN("deviceName"));
	props[ZBX_VMWARE_DEV_PROPS_IFDVSWITCH_UUID] = zbx_xml_node_read_value(details, node,
			ZBX_XNN("backing") ZBX_XPATH_LN2("port", "switchUuid"));
	props[ZBX_VMWARE_DEV_PROPS_IFDVSWITCH_PORTGROUP] = zbx_xml_node_read_value(details, node,
			ZBX_XNN("backing") ZBX_XPATH_LN2("port", "portgroupKey"));
	props[ZBX_VMWARE_DEV_PROPS_IFDVSWITCH_PORT] = zbx_xml_node_read_value(details, node,
			ZBX_XNN("backing") ZBX_XPATH_LN2("port", "portKey"));
	props[ZBX_VMWARE_DEV_PROPS_IFIPS] = vmware_vm_get_nic_device_ips(details, guestnet_node,
			props[ZBX_VMWARE_DEV_PROPS_IFMAC]);

	return props;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets virtual machine network interface devices                    *
 *                                                                            *
 * Parameters: vm      - [OUT] the virtual machine                            *
 *             details - [IN] an xml document containing virtual machine data *
 *                                                                            *
 * Comments: The network interface devices are taken from vm device list      *
 *           filtered by macAddress key.                                      *
 *                                                                            *
 ******************************************************************************/
static void	vmware_vm_get_nic_devices(zbx_vmware_vm_t *vm, xmlDoc *details)
{
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	xmlNode		*guestnet_node;
	int		i, nics = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	xpathCtx = xmlXPathNewContext(details);

	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)ZBX_XPATH_VM_HARDWARE("device")
			"[*[local-name()='macAddress']]", xpathCtx)))
	{
		goto clean;
	}

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto clean;

	nodeset = xpathObj->nodesetval;
	guestnet_node = zbx_xml_doc_get(details, ZBX_XPATH_PROP_NAME("guest.net"));
	zbx_vector_ptr_reserve(&vm->devs, (size_t)(nodeset->nodeNr + vm->devs.values_alloc));

	for (i = 0; i < nodeset->nodeNr; i++)
	{
		char			*key;
		zbx_vmware_dev_t	*dev;

		if (NULL == (key = zbx_xml_node_read_value(details, nodeset->nodeTab[i], "*[local-name()='key']")))
			continue;

		dev = (zbx_vmware_dev_t *)zbx_malloc(NULL, sizeof(zbx_vmware_dev_t));
		dev->type = ZBX_VMWARE_DEV_TYPE_NIC;
		dev->instance = key;
		dev->label = zbx_xml_node_read_value(details, nodeset->nodeTab[i],
				"*[local-name()='deviceInfo']/*[local-name()='label']");
		dev->props = vmware_vm_get_nic_device_props(details, nodeset->nodeTab[i], guestnet_node);

		zbx_vector_ptr_append(&vm->devs, dev);
		nics++;
	}
clean:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() found:%d", __func__, nics);
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets virtual machine virtual disk devices                         *
 *                                                                            *
 * Parameters: vm      - [OUT] the virtual machine                            *
 *             details - [IN] an xml document containing virtual machine data *
 *                                                                            *
 ******************************************************************************/
static void	vmware_vm_get_disk_devices(zbx_vmware_vm_t *vm, xmlDoc *details)
{
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	int		i, disks = 0;
	char		*xpath = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	xpathCtx = xmlXPathNewContext(details);

	/* select all hardware devices of VirtualDisk type */
	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)ZBX_XPATH_VM_HARDWARE("device")
			"[string(@*[local-name()='type'])='VirtualDisk']", xpathCtx)))
	{
		goto clean;
	}

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto clean;

	nodeset = xpathObj->nodesetval;
	zbx_vector_ptr_reserve(&vm->devs, (size_t)(nodeset->nodeNr + vm->devs.values_alloc));

	for (i = 0; i < nodeset->nodeNr; i++)
	{
		zbx_vmware_dev_t	*dev;
		char			*unitNumber = NULL, *controllerKey = NULL, *busNumber = NULL,
					*controllerLabel = NULL, *controllerType = NULL,
					*scsiCtlrUnitNumber = NULL;
		xmlXPathObject		*xpathObjController = NULL;

		do
		{
			if (NULL == (unitNumber = zbx_xml_node_read_value(details, nodeset->nodeTab[i],
					"*[local-name()='unitNumber']")))
			{
				break;
			}

			if (NULL == (controllerKey = zbx_xml_node_read_value(details, nodeset->nodeTab[i],
					"*[local-name()='controllerKey']")))
			{
				break;
			}

			/* find the controller (parent) device */
			xpath = zbx_dsprintf(xpath, ZBX_XPATH_VM_HARDWARE("device")
					"[*[local-name()='key']/text()='%s']", controllerKey);

			if (NULL == (xpathObjController = xmlXPathEvalExpression((const xmlChar *)xpath, xpathCtx)))
				break;

			if (0 != xmlXPathNodeSetIsEmpty(xpathObjController->nodesetval))
				break;

			if (NULL == (busNumber = zbx_xml_node_read_value(details,
					xpathObjController->nodesetval->nodeTab[0], "*[local-name()='busNumber']")))
			{
				break;
			}

			/* scsiCtlrUnitNumber property is simply used to determine controller type. */
			/* For IDE controllers it is not set.                                       */
			scsiCtlrUnitNumber = zbx_xml_node_read_value(details, xpathObjController->nodesetval->nodeTab[0],
				"*[local-name()='scsiCtlrUnitNumber']");

			dev = (zbx_vmware_dev_t *)zbx_malloc(NULL, sizeof(zbx_vmware_dev_t));
			dev->type =  ZBX_VMWARE_DEV_TYPE_DISK;
			dev->props = NULL;

			/* the virtual disk instance has format <controller type><busNumber>:<unitNumber>     */
			/* where controller type is either ide, sata or scsi depending on the controller type */

			dev->label = zbx_xml_node_read_value(details, nodeset->nodeTab[i],
					"*[local-name()='deviceInfo']/*[local-name()='label']");

			controllerLabel = zbx_xml_node_read_value(details, xpathObjController->nodesetval->nodeTab[0],
				"*[local-name()='deviceInfo']/*[local-name()='label']");

			if (NULL != scsiCtlrUnitNumber ||
				(NULL != controllerLabel && NULL != strstr(controllerLabel, "SCSI")))
			{
				controllerType = "scsi";
			}
			else if (NULL != controllerLabel && NULL != strstr(controllerLabel, "SATA"))
			{
				controllerType = "sata";
			}
			else
			{
				controllerType = "ide";
			}

			dev->instance = zbx_dsprintf(NULL, "%s%s:%s", controllerType, busNumber, unitNumber);
			zbx_vector_ptr_append(&vm->devs, dev);

			disks++;

		}
		while (0);

		xmlXPathFreeObject(xpathObjController);

		zbx_free(controllerLabel);
		zbx_free(scsiCtlrUnitNumber);
		zbx_free(busNumber);
		zbx_free(unitNumber);
		zbx_free(controllerKey);

	}
clean:
	zbx_free(xpath);

	if (NULL != xpathObj)
		xmlXPathFreeObject(xpathObj);

	xmlXPathFreeContext(xpathCtx);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() found:%d", __func__, disks);
}

#define ZBX_XPATH_VM_GUESTDISKS()									\
	"/*/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='guest.disk']]"		\
	"/*/*[local-name()='GuestDiskInfo']"

/******************************************************************************
 *                                                                            *
 * Purpose: gets the parameters of virtual machine disks                      *
 *                                                                            *
 * Parameters: vm      - [OUT] the virtual machine                            *
 *             details - [IN] an xml document containing virtual machine data *
 *                                                                            *
 ******************************************************************************/
static void	vmware_vm_get_file_systems(zbx_vmware_vm_t *vm, xmlDoc *details)
{
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	int		i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	xpathCtx = xmlXPathNewContext(details);

	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)ZBX_XPATH_VM_GUESTDISKS(), xpathCtx)))
		goto clean;

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto clean;

	nodeset = xpathObj->nodesetval;
	zbx_vector_ptr_reserve(&vm->file_systems, (size_t)(nodeset->nodeNr + vm->file_systems.values_alloc));

	for (i = 0; i < nodeset->nodeNr; i++)
	{
		zbx_vmware_fs_t	*fs;
		char		*value;

		if (NULL == (value = zbx_xml_node_read_value(details, nodeset->nodeTab[i], "*[local-name()='diskPath']")))
			continue;

		fs = (zbx_vmware_fs_t *)zbx_malloc(NULL, sizeof(zbx_vmware_fs_t));
		memset(fs, 0, sizeof(zbx_vmware_fs_t));

		fs->path = value;

		if (NULL != (value = zbx_xml_node_read_value(details, nodeset->nodeTab[i], "*[local-name()='capacity']")))
		{
			ZBX_STR2UINT64(fs->capacity, value);
			zbx_free(value);
		}

		if (NULL != (value = zbx_xml_node_read_value(details, nodeset->nodeTab[i], "*[local-name()='freeSpace']")))
		{
			ZBX_STR2UINT64(fs->free_space, value);
			zbx_free(value);
		}

		zbx_vector_ptr_append(&vm->file_systems, fs);
	}
clean:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() found:%d", __func__, vm->file_systems.values_num);
}

#define ZBX_XPATH_VM_CUSTOM_FIELD_VALUES()				\
	ZBX_XPATH_PROP_NAME("customValue") ZBX_XPATH_LN("CustomFieldValue")

/******************************************************************************
 *                                                                            *
 * Purpose: gets custom attributes data of the virtual machine                *
 *                                                                            *
 * Parameters: vm      - [OUT] the virtual machine                            *
 *             details - [IN] an xml document containing virtual machine data *
 *                                                                            *
 ******************************************************************************/
static void	vmware_vm_get_custom_attrs(zbx_vmware_vm_t *vm, xmlDoc *details)
{
	xmlXPathContext			*xpathCtx;
	xmlXPathObject			*xpathObj;
	xmlNodeSetPtr			nodeset;
	xmlNode				*node;
	int				i;
	char				*value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	xpathCtx = xmlXPathNewContext(details);

	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)ZBX_XPATH_VM_CUSTOM_FIELD_VALUES(), xpathCtx)))
		goto clean;

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto clean;

	if (NULL == (node = zbx_xml_doc_get(details, ZBX_XPATH_PROP_NAME("availableField"))))
		goto clean;

	nodeset = xpathObj->nodesetval;
	zbx_vector_vmware_custom_attr_reserve(&vm->custom_attrs, (size_t)nodeset->nodeNr);

	for (i = 0; i < nodeset->nodeNr; i++)
	{
		char				xpath[MAX_STRING_LEN];
		zbx_vmware_custom_attr_t	*attr;

		if (NULL == (value = zbx_xml_node_read_value(details, nodeset->nodeTab[i], ZBX_XNN("key"))))
			continue;

		zbx_snprintf(xpath, sizeof(xpath),
				ZBX_XNN("CustomFieldDef") "[" ZBX_XNN("key") "=%s][1]/" ZBX_XNN("name"), value);
		zbx_free(value);

		if (NULL == (value = zbx_xml_node_read_value(details, node, xpath)))
			continue;

		attr = (zbx_vmware_custom_attr_t *)zbx_malloc(NULL, sizeof(zbx_vmware_custom_attr_t));
		attr->name = value;
		value = NULL;

		if (NULL == (attr->value = zbx_xml_node_read_value(details, nodeset->nodeTab[i], ZBX_XNN("value"))))
			attr->value = zbx_strdup(NULL, "");

		zbx_vector_vmware_custom_attr_append(&vm->custom_attrs, attr);
	}

	zbx_vector_vmware_custom_attr_sort(&vm->custom_attrs, vmware_custom_attr_compare_name);
clean:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() attributes:%d", __func__, vm->custom_attrs.values_num);
}

#define ZBX_XPATH_HV_DATASTORES()									\
	"/*/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='datastore']]"		\
	"/*[local-name()='val']/*[@type='Datastore']"


#define ZBX_XPATH_HV_VMS()										\
	"/*/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='vm']]"			\
	"/*[local-name()='val']/*[@type='VirtualMachine']"

#define ZBX_XPATH_HV_MULTIPATH(state)									\
		"count(/*/*/*/*/*/*[local-name()='propSet'][1]/*[local-name()='val']"			\
		"/*[local-name()='lun'][*[local-name()='lun'][text()='%s']][1]"				\
		"/*[local-name()='path']" state ")"

#define ZBX_XPATH_HV_MULTIPATH_PATHS()	ZBX_XPATH_HV_MULTIPATH("")
#define ZBX_XPATH_HV_MULTIPATH_ACTIVE_PATHS()								\
		ZBX_XPATH_HV_MULTIPATH("[*[local-name()='state'][text()='active']]")

#define ZBX_XPATH_VM_UUID()										\
	"/*/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='config.uuid']]"		\
		"/*[local-name()='val']"


#define ZBX_XPATH_VM_INSTANCE_UUID()									\
	"/*/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='config.instanceUuid']]"	\
		"/*[local-name()='val']"

/******************************************************************************
 *                                                                            *
 * Purpose: gets the virtual machine data                                     *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             vmid         - [IN] the virtual machine id                     *
 *             propmap      - [IN] the xpaths of the properties to read       *
 *             props_num    - [IN] the number of properties to read           *
 *             cq_prop      - [IN] the soap part of query with cq property    *
 *             xdoc         - [OUT] a reference to output xml document        *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_vm_data(zbx_vmware_service_t *service, CURL *easyhandle, const char *vmid,
		const zbx_vmware_propmap_t *propmap, int props_num, const char *cq_prop, xmlDoc **xdoc, char **error)
{
#	define ZBX_POST_VMWARE_VM_STATUS_EX 						\
		ZBX_POST_VSPHERE_HEADER							\
		"<ns0:RetrievePropertiesEx>"						\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"		\
			"<ns0:specSet>"							\
				"<ns0:propSet>"						\
					"<ns0:type>VirtualMachine</ns0:type>"		\
					"<ns0:pathSet>config.hardware</ns0:pathSet>"	\
					"<ns0:pathSet>config.uuid</ns0:pathSet>"	\
					"<ns0:pathSet>config.instanceUuid</ns0:pathSet>"\
					"<ns0:pathSet>guest.disk</ns0:pathSet>"		\
					"<ns0:pathSet>customValue</ns0:pathSet>"	\
					"<ns0:pathSet>availableField</ns0:pathSet>"	\
					"<ns0:pathSet>triggeredAlarmState</ns0:pathSet>"\
					"<ns0:pathSet>guest.net</ns0:pathSet>"		\
					"%s"						\
					"%s"						\
				"</ns0:propSet>"					\
				"<ns0:propSet>"						\
					"<ns0:type>Folder</ns0:type>"			\
					"<ns0:pathSet>name</ns0:pathSet>"		\
					"<ns0:pathSet>parent</ns0:pathSet>"		\
				"</ns0:propSet>"					\
				"<ns0:objectSet>"					\
					"<ns0:obj type=\"VirtualMachine\">%s</ns0:obj>"	\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"\
						"<ns0:name>vm</ns0:name>"		\
						"<ns0:type>VirtualMachine</ns0:type>"	\
						"<ns0:path>parent</ns0:path>"		\
						"<ns0:skip>false</ns0:skip>"		\
						"<ns0:selectSet>"			\
							"<ns0:name>fl</ns0:name>"	\
						"</ns0:selectSet>"			\
					"</ns0:selectSet>"				\
					"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"\
						"<ns0:name>fl</ns0:name>"		\
						"<ns0:type>Folder</ns0:type>"		\
						"<ns0:path>parent</ns0:path>"		\
						"<ns0:skip>false</ns0:skip>"		\
						"<ns0:selectSet>"			\
							"<ns0:name>fl</ns0:name>"	\
						"</ns0:selectSet>"			\
					"</ns0:selectSet>"				\
				"</ns0:objectSet>"					\
			"</ns0:specSet>"						\
			"<ns0:options/>"						\
		"</ns0:RetrievePropertiesEx>"						\
		ZBX_POST_VSPHERE_FOOTER

	char	*tmp, props[ZBX_VMWARE_VMPROPS_NUM * 150], *vmid_esc;
	int	i, ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() vmid:'%s'", __func__, vmid);
	props[0] = '\0';

	for (i = 0; i < props_num; i++)
	{
		zbx_strscat(props, "<ns0:pathSet>");
		zbx_strscat(props, propmap[i].name);
		zbx_strscat(props, "</ns0:pathSet>");
	}

	vmid_esc = zbx_xml_escape_dyn(vmid);
	tmp = zbx_dsprintf(NULL, ZBX_POST_VMWARE_VM_STATUS_EX,
			get_vmware_service_objects()[service->type].property_collector, props, cq_prop, vmid_esc);

	zbx_free(vmid_esc);
	ret = zbx_soap_post(__func__, easyhandle, tmp, xdoc, NULL, error);
	zbx_str_free(tmp);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

#define ZBX_XPATH_GET_OBJECT_NAME(object, id)				\
		ZBX_XPATH_PROP_OBJECT_ID(object, "[text()='" id "']") "/"				\
		ZBX_XPATH_PROP_NAME_NODE("name")

#define ZBX_XPATH_GET_FOLDER_NAME(id)									\
		ZBX_XPATH_GET_OBJECT_NAME(ZBX_VMWARE_SOAP_FOLDER, id)

#define ZBX_XPATH_GET_FOLDER_PARENTID(id)								\
		ZBX_XPATH_PROP_OBJECT_ID(ZBX_VMWARE_SOAP_FOLDER, "[text()='" id "']") "/"		\
		ZBX_XPATH_PROP_NAME_NODE("parent") "[@type='Folder']"

/******************************************************************************
 *                                                                            *
 * Purpose: convert vm folder id to chain of folder names divided by '/'      *
 *                                                                            *
 * Parameters: xdoc      - [IN] the xml with all vm details                   *
 *             vm_folder - [IN/OUT] the vm property with folder id            *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_vm_folder(xmlDoc *xdoc, char **vm_folder)
{
	char	tmp[MAX_STRING_LEN], *id, *fl, *folder = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() folder id:'%s'", __func__, *vm_folder);
	id = zbx_strdup(NULL, *vm_folder);

	do
	{
		char	*id_esc;

		id_esc = zbx_xml_escape_dyn(id);
		zbx_free(id);
		zbx_snprintf(tmp, sizeof(tmp), ZBX_XPATH_GET_FOLDER_NAME("%s"), id_esc);

		if (NULL == (fl = zbx_xml_doc_read_value(xdoc , tmp)))
		{
			zbx_free(folder);
			zbx_free(id_esc);
			return FAIL;
		}

		zbx_snprintf(tmp, sizeof(tmp), ZBX_XPATH_GET_FOLDER_PARENTID("%s"), id_esc);
		zbx_free(id_esc);
		id = zbx_xml_doc_read_value(xdoc , tmp);

		if (NULL == folder)	/* we always resolve the first 'Folder' name */
		{
			folder = fl;
			fl = NULL;
		}
		else if (NULL != id)	/* we do not include the last default 'Folder' */
			folder = zbx_dsprintf(folder, "%s/%s", fl, folder);

		zbx_free(fl);
	}
	while (NULL != id);

	zbx_free(*vm_folder);
	*vm_folder = folder;
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): vm folder:%s", __func__, *vm_folder);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create virtual machine object                                     *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             id           - [IN] the virtual machine id                     *
 *             rpools       - [IN/OUT] the vector with all Resource Pools     *
 *             cq_values    - [IN/OUT] the vector with custom query entries   *
 *             alarms_data  - [IN/OUT] the all alarms with cache              *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: The created virtual machine object or NULL if an error was   *
 *               detected.                                                    *
 *                                                                            *
 ******************************************************************************/
static zbx_vmware_vm_t	*vmware_service_create_vm(zbx_vmware_service_t *service, CURL *easyhandle,
		const char *id, zbx_vector_vmware_resourcepool_t *rpools, zbx_vector_cq_value_t *cq_values,
		zbx_vmware_alarms_data_t *alarms_data, char **error)
{
	zbx_vmware_vm_t		*vm;
	char			*value, *cq_prop;
	xmlDoc			*details = NULL;
	zbx_vector_cq_value_t	cqvs;
	const char		*uuid_xpath[3] = {NULL, ZBX_XPATH_VM_UUID(), ZBX_XPATH_VM_INSTANCE_UUID()};
	int			ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() vmid:'%s'", __func__, id);

	vm = (zbx_vmware_vm_t *)zbx_malloc(NULL, sizeof(zbx_vmware_vm_t));
	memset(vm, 0, sizeof(zbx_vmware_vm_t));

	zbx_vector_ptr_create(&vm->devs);
	zbx_vector_ptr_create(&vm->file_systems);
	zbx_vector_vmware_custom_attr_create(&vm->custom_attrs);
	zbx_vector_cq_value_create(&cqvs);
	cq_prop = vmware_cq_prop_soap_request(cq_values, ZBX_VMWARE_SOAP_VM, id, &cqvs);
	ret = vmware_service_get_vm_data(service, easyhandle, id, vm_propmap, ZBX_VMWARE_VMPROPS_NUM, cq_prop,
			&details, error);
	zbx_str_free(cq_prop);

	if (FAIL == ret)
		goto out;

	if (NULL == (value = zbx_xml_doc_read_value(details, uuid_xpath[service->type])))
	{
		ret = FAIL;
		goto out;
	}

	vm->uuid = value;
	vm->id = zbx_strdup(NULL, id);

	if (NULL == (vm->props = xml_read_props(details, vm_propmap, ZBX_VMWARE_VMPROPS_NUM)))
		goto out;

	if (NULL != vm->props[ZBX_VMWARE_VMPROP_FOLDER] &&
			SUCCEED != vmware_service_get_vm_folder(details, &vm->props[ZBX_VMWARE_VMPROP_FOLDER]))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): cannot find vm folder name for id:%s", __func__,
				vm->props[ZBX_VMWARE_VMPROP_FOLDER]);
	}

	if (NULL != vm->props[ZBX_VMWARE_VMPROP_SNAPSHOT])
	{
		struct zbx_json_parse	jp;
		char			count[ZBX_MAX_UINT64_LEN];

		if (SUCCEED == zbx_json_open(vm->props[ZBX_VMWARE_VMPROP_SNAPSHOT], &jp) &&
				SUCCEED == zbx_json_value_by_name(&jp, "count", count, sizeof(count), NULL))
		{
			vm->snapshot_count = (unsigned int)atoi(count);
		}
	}
	else
	{
		vm->props[ZBX_VMWARE_VMPROP_SNAPSHOT] = zbx_strdup(NULL, "{\"snapshot\":[],\"count\":0,"
				"\"latestdate\":null,\"size\":0,\"uniquesize\":0}");
	}

	if (NULL != vm->props[ZBX_VMWARE_VMPROP_RESOURCEPOOL])
	{
		int				i;
		zbx_vmware_resourcepool_t	rpool_cmp;

		rpool_cmp.id = vm->props[ZBX_VMWARE_VMPROP_RESOURCEPOOL];

		if (FAIL != (i = zbx_vector_vmware_resourcepool_bsearch(rpools, &rpool_cmp,
				ZBX_DEFAULT_STR_PTR_COMPARE_FUNC)))
		{
			rpools->values[i]->vm_num += 1;
		}
	}

	vmware_vm_get_nic_devices(vm, details);
	vmware_vm_get_disk_devices(vm, details);
	vmware_vm_get_file_systems(vm, details);
	vmware_vm_get_custom_attrs(vm, details);

	if (0 != cqvs.values_num)
		vmware_service_cq_prop_value(__func__, details, &cqvs);

	zbx_vector_str_create(&vm->alarm_ids);
	ret = vmware_service_get_alarms_data(__func__, service, easyhandle, details, NULL, &vm->alarm_ids, alarms_data,
			error);
out:
	zbx_vector_cq_value_destroy(&cqvs);
	zbx_xml_free_doc(details);

	if (SUCCEED != ret)
	{
		vmware_vm_free(vm);
		vm = NULL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return vm;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets the vmware hypervisor data                                   *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             hvid         - [IN] the vmware hypervisor id                   *
 *             propmap      - [IN] the xpaths of the properties to read       *
 *             props_num    - [IN] the number of properties to read           *
 *             cq_prop      - [IN] the soap part of query with cq property    *
 *             xdoc         - [OUT] a reference to output xml document        *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_hv_data(const zbx_vmware_service_t *service, CURL *easyhandle, const char *hvid,
		const zbx_vmware_propmap_t *propmap, int props_num, const char *cq_prop, xmlDoc **xdoc, char **error)
{
#	define ZBX_POST_HV_DETAILS 										\
		ZBX_POST_VSPHERE_HEADER										\
		"<ns0:RetrievePropertiesEx>"									\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"					\
			"<ns0:specSet>"										\
				"<ns0:propSet>"									\
					"<ns0:type>HostSystem</ns0:type>"					\
					"<ns0:pathSet>vm</ns0:pathSet>"						\
					"<ns0:pathSet>parent</ns0:pathSet>"					\
					"<ns0:pathSet>datastore</ns0:pathSet>"					\
					"<ns0:pathSet>config.virtualNicManagerInfo.netConfig</ns0:pathSet>"	\
					"<ns0:pathSet>config.network.pnic</ns0:pathSet>"			\
					"<ns0:pathSet>config.network.ipRouteConfig.defaultGateway</ns0:pathSet>"\
					"<ns0:pathSet>summary.managementServerIp</ns0:pathSet>"			\
					"<ns0:pathSet>config.storageDevice.scsiTopology</ns0:pathSet>"		\
					"<ns0:pathSet>triggeredAlarmState</ns0:pathSet>"			\
					"%s"									\
					"%s"									\
				"</ns0:propSet>"								\
				"<ns0:objectSet>"								\
					"<ns0:obj type=\"HostSystem\">%s</ns0:obj>"				\
					"<ns0:skip>false</ns0:skip>"						\
				"</ns0:objectSet>"								\
			"</ns0:specSet>"									\
			"<ns0:options/>"									\
		"</ns0:RetrievePropertiesEx>"									\
		ZBX_POST_VSPHERE_FOOTER

	char	*tmp, props[ZBX_VMWARE_HVPROPS_NUM * 150], *hvid_esc;
	int	i, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() guesthvid:'%s'", __func__, hvid);
	props[0] = '\0';

	for (i = 0; i < props_num; i++)
	{
		if (NULL == propmap[i].name)
			continue;

		if (0 != propmap[i].vc_min && propmap[i].vc_min > service->major_version * 10 + service->minor_version)
			continue;

		zbx_strscat(props, "<ns0:pathSet>");
		zbx_strscat(props, propmap[i].name);
		zbx_strscat(props, "</ns0:pathSet>");
	}

	hvid_esc = zbx_xml_escape_dyn(hvid);
	tmp = zbx_dsprintf(NULL, ZBX_POST_HV_DETAILS, get_vmware_service_objects()[service->type].property_collector,
			props, cq_prop, hvid_esc);
	zbx_free(hvid_esc);
	zabbix_log(LOG_LEVEL_TRACE, "%s() SOAP request: %s", __func__, tmp);

	ret = zbx_soap_post(__func__, easyhandle, tmp, xdoc, NULL, error);
	zbx_str_free(tmp);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef	ZBX_POST_HV_DETAILS
}

#define ZBX_XPATH_HV_PARENTID										\
	ZBX_XPATH_PROP_OBJECT(ZBX_VMWARE_SOAP_HV) ZBX_XPATH_PROP_NAME_NODE("parent")

#define ZBX_XPATH_HV_PARENTFOLDERNAME(parent_id)							\
	"/*/*/*/*/*[local-name()='objects']["								\
		"*[local-name()='obj'][@type='Folder'] and "						\
		"*[local-name()='propSet'][*[local-name()='name'][text()='childEntity']]"		\
		"/*[local-name()='val']/*[local-name()='ManagedObjectReference']=" parent_id " and "	\
		"*[local-name()='propSet'][*[local-name()='name'][text()='parent']]"			\
		"/*[local-name()='val'][@type!='Datacenter']"						\
	"]/*[local-name()='propSet'][*[local-name()='name'][text()='name']]/*[local-name()='val']"

#define ZBX_XPATH_NAME_BY_TYPE(type)									\
	ZBX_XPATH_PROP_OBJECT(type) "*[local-name()='propSet'][*[local-name()='name']]"			\
	"/*[local-name()='val']"

/******************************************************************************
 *                                                                            *
 * Purpose: gets the vmware hypervisor datacenter, parent folder or cluster   *
 *          name                                                              *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             hv           - [IN/OUT] the vmware hypervisor                  *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_hv_get_parent_data(const zbx_vmware_service_t *service, CURL *easyhandle,
		zbx_vmware_hv_t *hv, char **error)
{
#	define ZBX_POST_HV_DATACENTER_NAME									\
		ZBX_POST_VSPHERE_HEADER										\
			"<ns0:RetrievePropertiesEx>"								\
				"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"				\
				"<ns0:specSet>"									\
					"<ns0:propSet>"								\
						"<ns0:type>Datacenter</ns0:type>"				\
						"<ns0:pathSet>name</ns0:pathSet>"				\
						"<ns0:pathSet>triggeredAlarmState</ns0:pathSet>"		\
					"</ns0:propSet>"							\
					"%s"									\
					"<ns0:objectSet>"							\
						"<ns0:obj type=\"HostSystem\">%s</ns0:obj>"			\
						"<ns0:skip>false</ns0:skip>"					\
						"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"		\
							"<ns0:name>parentObject</ns0:name>"			\
							"<ns0:type>HostSystem</ns0:type>"			\
							"<ns0:path>parent</ns0:path>"				\
							"<ns0:skip>false</ns0:skip>"				\
							"<ns0:selectSet>"					\
								"<ns0:name>parentComputeResource</ns0:name>"	\
							"</ns0:selectSet>"					\
							"<ns0:selectSet>"					\
								"<ns0:name>parentFolder</ns0:name>"		\
							"</ns0:selectSet>"					\
						"</ns0:selectSet>"						\
						"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"		\
							"<ns0:name>parentComputeResource</ns0:name>"		\
							"<ns0:type>ComputeResource</ns0:type>"			\
							"<ns0:path>parent</ns0:path>"				\
							"<ns0:skip>false</ns0:skip>"				\
							"<ns0:selectSet>"					\
								"<ns0:name>parentFolder</ns0:name>"		\
							"</ns0:selectSet>"					\
						"</ns0:selectSet>"						\
						"<ns0:selectSet xsi:type=\"ns0:TraversalSpec\">"		\
							"<ns0:name>parentFolder</ns0:name>"			\
							"<ns0:type>Folder</ns0:type>"				\
							"<ns0:path>parent</ns0:path>"				\
							"<ns0:skip>false</ns0:skip>"				\
							"<ns0:selectSet>"					\
								"<ns0:name>parentFolder</ns0:name>"		\
							"</ns0:selectSet>"					\
							"<ns0:selectSet>"					\
								"<ns0:name>parentComputeResource</ns0:name>"	\
							"</ns0:selectSet>"					\
						"</ns0:selectSet>"						\
					"</ns0:objectSet>"							\
				"</ns0:specSet>"								\
				"<ns0:options/>"								\
			"</ns0:RetrievePropertiesEx>"								\
		ZBX_POST_VSPHERE_FOOTER

#	define ZBX_POST_SOAP_FOLDER										\
		"<ns0:propSet>"											\
			"<ns0:type>Folder</ns0:type>"								\
			"<ns0:pathSet>name</ns0:pathSet>"							\
			"<ns0:pathSet>parent</ns0:pathSet>"							\
			"<ns0:pathSet>childEntity</ns0:pathSet>"						\
		"</ns0:propSet>"										\
		"<ns0:propSet>"											\
			"<ns0:type>HostSystem</ns0:type>"							\
			"<ns0:pathSet>parent</ns0:pathSet>"							\
		"</ns0:propSet>"

#	define ZBX_POST_SOAP_CUSTER										\
		"<ns0:propSet>"											\
			"<ns0:type>ClusterComputeResource</ns0:type>"						\
			"<ns0:pathSet>name</ns0:pathSet>"							\
		"</ns0:propSet>"

	char	tmp[MAX_STRING_LEN];
	int	ret = FAIL;
	xmlDoc	*doc = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() id:'%s'", __func__, hv->id);

	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_HV_DATACENTER_NAME,
			get_vmware_service_objects()[service->type].property_collector,
			NULL != hv->clusterid ? ZBX_POST_SOAP_CUSTER : ZBX_POST_SOAP_FOLDER, hv->id);

	if (SUCCEED != zbx_soap_post(__func__, easyhandle, tmp, &doc, NULL, error))
		goto out;

	if (NULL == (hv->datacenter_name = zbx_xml_doc_read_value(doc,
			ZBX_XPATH_NAME_BY_TYPE(ZBX_VMWARE_SOAP_DATACENTER))))
	{
		hv->datacenter_name = zbx_strdup(NULL, "");
	}

	if (NULL != hv->clusterid && (NULL != (hv->parent_name = zbx_xml_doc_read_value(doc,
			ZBX_XPATH_NAME_BY_TYPE(ZBX_VMWARE_SOAP_CLUSTER)))))
	{
		hv->parent_type = zbx_strdup(NULL, ZBX_VMWARE_SOAP_CLUSTER);
	}
	else if (NULL != (hv->parent_name = zbx_xml_doc_read_value(doc,
			ZBX_XPATH_HV_PARENTFOLDERNAME(ZBX_XPATH_HV_PARENTID))))
	{
		hv->parent_type = zbx_strdup(NULL, ZBX_VMWARE_SOAP_FOLDER);
	}
	else if ('\0' != *hv->datacenter_name)
	{
		hv->parent_name = zbx_strdup(NULL, hv->datacenter_name);
		hv->parent_type = zbx_strdup(NULL, ZBX_VMWARE_SOAP_DATACENTER);
	}
	else
	{
		hv->parent_name = zbx_strdup(NULL, ZBX_VMWARE_TYPE_VCENTER == service->type ? "Vcenter" : "ESXi");
		hv->parent_type = zbx_strdup(NULL, ZBX_VMWARE_SOAP_DEFAULT);
	}

	ret = SUCCEED;
out:
	zbx_xml_free_doc(doc);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef	ZBX_POST_HV_DATACENTER_NAME
#	undef	ZBX_POST_SOAP_FOLDER
#	undef	ZBX_POST_SOAP_CUSTER
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets the vmware hypervisor data about ds multipath                *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             hvid         - [IN] the vmware hypervisor id                   *
 *             xdoc         - [OUT] a reference to output xml document        *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_hv_get_multipath_data(const zbx_vmware_service_t *service, CURL *easyhandle,
		const char *hvid, xmlDoc **xdoc, char **error)
{
#	define ZBX_POST_HV_MP_DETAILS									\
		ZBX_POST_VSPHERE_HEADER									\
		"<ns0:RetrievePropertiesEx>"								\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"				\
			"<ns0:specSet>"									\
				"<ns0:propSet>"								\
					"<ns0:type>HostSystem</ns0:type>"				\
					"<ns0:pathSet>config.storageDevice.multipathInfo</ns0:pathSet>"	\
				"</ns0:propSet>"							\
				"<ns0:objectSet>"							\
					"<ns0:obj type=\"HostSystem\">%s</ns0:obj>"			\
					"<ns0:skip>false</ns0:skip>"					\
				"</ns0:objectSet>"							\
			"</ns0:specSet>"								\
			"<ns0:options/>"								\
		"</ns0:RetrievePropertiesEx>"								\
		ZBX_POST_VSPHERE_FOOTER

	char	tmp[MAX_STRING_LEN], *hvid_esc;
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hvid:'%s'", __func__, hvid);

	hvid_esc = zbx_xml_escape_dyn(hvid);
	zbx_snprintf(tmp, sizeof(tmp), ZBX_POST_HV_MP_DETAILS,
			get_vmware_service_objects()[service->type].property_collector, hvid_esc);
	zbx_free(hvid_esc);

	ret = zbx_soap_post(__func__, easyhandle, tmp, xdoc, NULL, error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;

#	undef	ZBX_POST_HV_MP_DETAILS
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
static char	*vmware_datastores_diskname_search(const zbx_vector_vmware_datastore_t *dss, char *diskname)
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
 * Purpose: parse the vmware hypervisor internal disks details info           *
 *                                                                            *
 * Parameters: xdoc       - [IN] a reference to xml document with disks info  *
 *             dss        - [IN] all known Datastores                         *
 *             disks_info - [OUT]                                             *
 *                                                                            *
 * Return value: count of updated disk objects                                *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_hv_disks_parse_info(xmlDoc *xdoc, const zbx_vector_vmware_datastore_t *dss,
		zbx_vector_ptr_pair_t *disks_info)
{
#	define SCSILUN_PROP_NUM		8
#	define ZBX_XPATH_PSET		"/*/*/*/*/*/*[local-name()='propSet']"
#	define ZBX_XPATH_LUN		"substring-before(substring-after(*[local-name()='name'],'\"'),'\"')"
#	define ZBX_XPATH_LUN_PR_NAME	"substring-after(*[local-name()='name'],'\"].')"


	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	char		*lun_key = NULL, *name = NULL;
	int 		i, created = 0, j = FAIL;


	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	xpathCtx = xmlXPathNewContext(xdoc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)ZBX_XPATH_PSET, xpathCtx)))
		goto clean;

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto clean;

	nodeset = xpathObj->nodesetval;
	zbx_vector_ptr_pair_reserve(disks_info, (size_t)(nodeset->nodeNr / SCSILUN_PROP_NUM + disks_info->values_num));

	for (i = 0; i < nodeset->nodeNr; i++)
	{
		zbx_vmware_diskinfo_t	*di;
		xmlNode			*node = nodeset->nodeTab[i];
		zbx_ptr_pair_t		pr;

		zbx_str_free(lun_key);
		zbx_str_free(name);

		if (NULL == (lun_key = zbx_xml_node_read_value(xdoc, node, ZBX_XPATH_LUN)))
			continue;

		if (NULL == (name = zbx_xml_node_read_value(xdoc, node, ZBX_XPATH_LUN_PR_NAME)))
			continue;

		pr.first = lun_key;

		if ((FAIL == j || 0 != strcmp(disks_info->values[j].first, lun_key)) &&
				FAIL == (j = zbx_vector_ptr_pair_bsearch(disks_info, pr, ZBX_DEFAULT_STR_COMPARE_FUNC)))
		{
			lun_key = NULL;
			pr.second = zbx_malloc(NULL, sizeof(zbx_vmware_diskinfo_t));
			memset(pr.second, 0, sizeof(zbx_vmware_diskinfo_t));
			zbx_vector_ptr_pair_append(disks_info, pr);
			zbx_vector_ptr_pair_sort(disks_info, ZBX_DEFAULT_STR_COMPARE_FUNC);
			di = (zbx_vmware_diskinfo_t *)pr.second;
			created++;
		}
		else
			di = (zbx_vmware_diskinfo_t *)disks_info->values[j].second;

		if (0 == strcmp(name, "canonicalName"))
		{
			di->diskname = zbx_xml_node_read_value(xdoc, node, ZBX_XNN("val"));
			di->ds_uuid = vmware_datastores_diskname_search(dss, di->diskname);
		}
		else if (0 == strcmp(name, "operationalState"))
		{
			zbx_vector_str_t	values;
			int			k;

			zbx_vector_str_create(&values);
			zbx_xml_node_read_values(xdoc, node, ZBX_XNN("val") "/*", &values);
			di->operational_state = zbx_strdcat(di->operational_state, "[");

			for (k = 0; k < values.values_num; k++)
				di->operational_state = zbx_strdcatf(di->operational_state, "\"%s\",", values.values[k]);

			if (0 != values.values_num)
				di->operational_state[strlen(di->operational_state) - 1] = '\0';

			di->operational_state = zbx_strdcat(di->operational_state, "]");
			zbx_vector_str_clear_ext(&values, zbx_str_free);
			zbx_vector_str_destroy(&values);
		}
		else if (0 == strcmp(name, "lunType"))
		{
			di->lun_type = zbx_xml_node_read_value(xdoc, node, ZBX_XNN("val"));
		}
		else if (0 == strcmp(name, "queueDepth"))
		{
			zbx_xml_node_read_num(xdoc, node, "number(" ZBX_XNN("val") ")", &di->queue_depth);
		}
		else if (0 == strcmp(name, "model"))
		{
			di->model = zbx_xml_node_read_value(xdoc, node, ZBX_XNN("val"));
			zbx_lrtrim(di->model, " ");
		}
		else if (0 == strcmp(name, "vendor"))
		{
			di->vendor = zbx_xml_node_read_value(xdoc, node, ZBX_XNN("val"));
			zbx_lrtrim(di->vendor, " ");
		}
		else if (0 == strcmp(name, "revision"))
		{
			di->revision = zbx_xml_node_read_value(xdoc, node, ZBX_XNN("val"));
			zbx_lrtrim(di->revision, " ");
		}
		else if (0 == strcmp(name, "serialNumber"))
		{
			di->serial_number = zbx_xml_node_read_value(xdoc, node, ZBX_XNN("val"));
			zbx_lrtrim(di->serial_number, " ");
		}
	}

	zbx_str_free(lun_key);
	zbx_str_free(name);
clean:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() created:%d", __func__, created);

	return created;

#	undef SCSILUN_PROP_NUM
#	undef ZBX_XPATH_LUN_PR_NAME
#	undef ZBX_XPATH_PSET
}

static int	vmware_diskinfo_diskname_compare(const void *d1, const void *d2);

/******************************************************************************
 *                                                                            *
 * Purpose: parse the vmware hypervisor vsan disks details info               *
 *                                                                            *
 * Parameters: xdoc       - [IN] - a reference to xml document with disks info*
 *             vsan_uuid  - [IN] - uuid of vsan DS                            *
 *             disks_info - [IN/OUT] - collected the hv internal disks        *
 *                                                                            *
 * Return value: count of updated vsan disk objects                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_hv_vsan_parse_info(xmlDoc *xdoc, const char *vsan_uuid,
		zbx_vector_ptr_pair_t *disks_info)
{
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	int		i, updated_vsan = 0, j = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	xpathCtx = xmlXPathNewContext(xdoc);

	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)"//*[" ZBX_XNN("canonicalName") "]", xpathCtx)))
		goto clean;

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto clean;

	nodeset = xpathObj->nodesetval;

	for (i = 0; i < nodeset->nodeNr; i++)
	{
		zbx_vmware_diskinfo_t	*di, di_cmp;
		xmlNode			*mapinfo_node = nodeset->nodeTab[i];
		zbx_ptr_pair_t		pr = {.first = NULL, .second = &di_cmp};

		if (NULL == (di_cmp.diskname = zbx_xml_node_read_value(xdoc, mapinfo_node, ZBX_XNN("canonicalName"))) ||
				FAIL == (j = zbx_vector_ptr_pair_bsearch(disks_info, pr,
				vmware_diskinfo_diskname_compare)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() skipped internal disk: %s", __func__,
					ZBX_NULL2EMPTY_STR(di_cmp.diskname));
			zbx_str_free(di_cmp.diskname);
			continue;
		}

		zbx_str_free(di_cmp.diskname);
		di = (zbx_vmware_diskinfo_t *)disks_info->values[j].second;
		di->vsan = zbx_malloc(NULL, sizeof(zbx_vmware_vsandiskinfo_t));
		memset(di->vsan, 0, sizeof(zbx_vmware_vsandiskinfo_t));
		di->vsan->ssd = zbx_xml_node_read_value(xdoc, mapinfo_node, ZBX_XNN("ssd"));
		di->vsan->local_disk = zbx_xml_node_read_value(xdoc, mapinfo_node, ZBX_XNN("localDisk"));
		zbx_xml_node_read_num(xdoc, mapinfo_node,
				"number(." ZBX_XPATH_LN2("capacity", "block") ")", (int *)&di->vsan->block);
		zbx_xml_node_read_num(xdoc, mapinfo_node,
				"number(." ZBX_XPATH_LN2("capacity", "blockSize") ")", (int *)&di->vsan->block_size);

		if (NULL == di->ds_uuid)
			di->ds_uuid = zbx_strdup(di->ds_uuid, vsan_uuid);

		updated_vsan++;
	}
clean:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() vsan disks updated:%d", __func__, updated_vsan);

	return updated_vsan;
}

#define ZBX_XPATH_HV_SCSI_TOPOLOGY									\
		ZBX_XPATH_PROP_NAME("config.storageDevice.scsiTopology")				\
		"/*[local-name()='adapter']/*[local-name()='target']"					\
		"/*[local-name()='lun']/*[local-name()='scsiLun']"

/******************************************************************************
 *                                                                            *
 * Purpose: gets the vmware hypervisor internal disks details info            *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             hv_data      - [IN] the hv data with scsi topology info        *
 *             hvid         - [IN] the vmware hypervisor id                   *
 *             dss          - [IN] all known Datastores                       *
 *             vsan_uuid    - [IN] uuid of vsan Datastore                    *
 *             disks_info   - [OUT]
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the operation has completed successfully           *
 *               FAIL    - the operation has failed                           *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_hv_disks_get_info(const zbx_vmware_service_t *service, CURL *easyhandle,
		xmlDoc *hv_data, const char *hvid, const zbx_vector_vmware_datastore_t *dss,
		const char *vsan_uuid, zbx_vector_ptr_pair_t *disks_info, char **error)
{
#	define ZBX_POST_HV_DISK_INFO									\
		ZBX_POST_VSPHERE_HEADER									\
		"<ns0:RetrievePropertiesEx>"								\
			"<ns0:_this type=\"PropertyCollector\">%s</ns0:_this>"				\
			"<ns0:specSet>"									\
				"<ns0:propSet>"								\
					"<ns0:type>HostSystem</ns0:type>"				\
					"%s"								\
				"</ns0:propSet>"							\
				"<ns0:objectSet>"							\
					"<ns0:obj type=\"HostSystem\">%s</ns0:obj>"			\
					"<ns0:skip>false</ns0:skip>"					\
				"</ns0:objectSet>"							\
			"</ns0:specSet>"								\
			"<ns0:options/>"								\
		"</ns0:RetrievePropertiesEx>"								\
		ZBX_POST_VSPHERE_FOOTER

#	define ZBX_POST_SCSI_INFO									\
		"<ns0:pathSet>config.storageDevice.scsiLun[\"%s\"].canonicalName</ns0:pathSet>"		\
		"<ns0:pathSet>config.storageDevice.scsiLun[\"%s\"].operationalState</ns0:pathSet>"	\
		"<ns0:pathSet>config.storageDevice.scsiLun[\"%s\"].lunType</ns0:pathSet>"		\
		"<ns0:pathSet>config.storageDevice.scsiLun[\"%s\"].queueDepth</ns0:pathSet>"		\
		"<ns0:pathSet>config.storageDevice.scsiLun[\"%s\"].model</ns0:pathSet>"			\
		"<ns0:pathSet>config.storageDevice.scsiLun[\"%s\"].vendor</ns0:pathSet>"		\
		"<ns0:pathSet>config.storageDevice.scsiLun[\"%s\"].revision</ns0:pathSet>"		\
		"<ns0:pathSet>config.storageDevice.scsiLun[\"%s\"].serialNumber</ns0:pathSet>"

	zbx_vector_str_t		scsi_luns;
	xmlDoc				*doc = NULL, *doc_dinfo = NULL;
	zbx_property_collection_iter	*iter = NULL;
	char				*tmp = NULL, *hvid_esc, *scsi_req = NULL, *err = NULL;
	int				i, total, updated = 0, updated_vsan = 0, ret = SUCCEED;
	const char			*pcollecter = get_vmware_service_objects()[service->type].property_collector;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hvid:'%s'", __func__, hvid);

	zbx_vector_str_create(&scsi_luns);
	zbx_xml_read_values(hv_data, ZBX_XPATH_HV_SCSI_TOPOLOGY, &scsi_luns);
	total = scsi_luns.values_num;
	zabbix_log(LOG_LEVEL_DEBUG, "%s() count of scsiLun:%d", __func__, total);

	if (0 == total)
		goto out;

	for (i = 0; i < scsi_luns.values_num; i++)
	{
		scsi_req = zbx_strdcatf(scsi_req , ZBX_POST_SCSI_INFO, scsi_luns.values[i], scsi_luns.values[i],
				scsi_luns.values[i], scsi_luns.values[i], scsi_luns.values[i], scsi_luns.values[i],
				scsi_luns.values[i], scsi_luns.values[i]);
	}

	zbx_vector_str_clear_ext(&scsi_luns, zbx_str_free);
	hvid_esc = zbx_xml_escape_dyn(hvid);
	tmp = zbx_dsprintf(tmp, ZBX_POST_HV_DISK_INFO, pcollecter, ZBX_NULL2EMPTY_STR(scsi_req), hvid_esc);
	zbx_free(hvid_esc);
	zbx_free(scsi_req);

	if (SUCCEED != (ret = zbx_property_collection_init(easyhandle, tmp, pcollecter, __func__, &iter, &doc, error)))
		goto out;

	updated += vmware_service_hv_disks_parse_info(doc, dss, disks_info);

	while (NULL != iter->token)
	{
		zbx_xml_free_doc(doc);
		doc = NULL;

		if (SUCCEED != (ret = zbx_property_collection_next(__func__, iter, &doc, error)))
			goto out;

		updated += vmware_service_hv_disks_parse_info(doc, dss, disks_info);
	}

	zbx_vector_ptr_pair_sort(disks_info, vmware_diskinfo_diskname_compare);

	if (NULL == vsan_uuid)
		goto out;

	zbx_property_collection_free(iter);
	iter = NULL;
	hvid_esc = zbx_xml_escape_dyn(hvid);
	tmp = zbx_dsprintf(tmp, ZBX_POST_HV_DISK_INFO, pcollecter,
			"<ns0:pathSet>config.vsanHostConfig.storageInfo.diskMapping</ns0:pathSet>", hvid_esc);
	zbx_free(hvid_esc);

	if (SUCCEED != (ret = zbx_property_collection_init(easyhandle, tmp, pcollecter, __func__, &iter, &doc_dinfo,
			&err)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot get vsan disk_info:%s", __func__, err);
		zbx_str_free(err);
		goto out;
	}

	updated_vsan += vmware_service_hv_vsan_parse_info(doc_dinfo, vsan_uuid, disks_info);

	while (NULL != iter->token)
	{
		zbx_xml_free_doc(doc_dinfo);
		doc_dinfo = NULL;

		if (SUCCEED != (ret = zbx_property_collection_next(__func__, iter, &doc_dinfo, error)))
			goto out;

		updated_vsan += vmware_service_hv_vsan_parse_info(doc_dinfo, vsan_uuid, disks_info);
	}
out:
	zbx_free(tmp);
	zbx_xml_free_doc(doc);
	zbx_xml_free_doc(doc_dinfo);
	zbx_vector_str_destroy(&scsi_luns);
	zbx_property_collection_free(iter);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s for %d(vsan:%d) / %d", __func__, zbx_result_string(ret), updated,
			updated_vsan, total);

	return ret;

#	undef	ZBX_POST_SCSI_INFO
#	undef	ZBX_POST_HV_DISK_INFO
}

/******************************************************************************
 *                                                                            *
 * Purpose: sorting function to sort diskinfo vector by diskname              *
 *                                                                            *
 ******************************************************************************/
static int	vmware_diskinfo_diskname_compare(const void *d1, const void *d2)
{
	const zbx_ptr_pair_t		*p1 = (const zbx_ptr_pair_t *)d1;
	const zbx_ptr_pair_t		*p2 = (const zbx_ptr_pair_t *)d2;
	const zbx_vmware_diskinfo_t	*di1 = (const zbx_vmware_diskinfo_t *)p1->second;
	const zbx_vmware_diskinfo_t	*di2 = (const zbx_vmware_diskinfo_t *)p2->second;

	return strcmp(di1->diskname, di2->diskname);
}

#define ZBX_XPATH_PROP_SUFFIX(property)									\
	"*[local-name()='propSet'][*[local-name()='name']"						\
	"[substring(text(),string-length(text())-string-length('" property "')+1)='" property "']]"	\
	"/*[local-name()='val']"

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
 *                                                                            *
 * Purpose: Convert ipv4 netmask to cidr prefix                               *
 *                                                                            *
 * Parameters: mask      - [IN] net mask string                               *
 *                                                                            *
 * Return value: size of v4 netmask prefix                                    *
 *                                                                            *
 ******************************************************************************/
static int	vmware_v4mask2pefix(const char *mask)
{
#	define	V4MASK_MAX	32

	struct in_addr	inaddr;
	int		p = 0;

	if (-1 == inet_pton(AF_INET, mask, &inaddr))
		return V4MASK_MAX;

	while (inaddr.s_addr > 0)
	{
		inaddr.s_addr = inaddr.s_addr >> 1;
		p++;
	}

	return p;

#	undef	V4MASK_MAX
}

/******************************************************************************
 *                                                                            *
 * Purpose: Search HV management interface ip value from an xml data          *
 *                                                                            *
 * Parameters: xdoc   - [IN] XML document                                     *
 *                                                                            *
 * Return: Upon successful completion the function return string with ip.     *
 *         Otherwise, NULL is returned.                                       *
 *                                                                            *
 ******************************************************************************/
static char	*vmware_hv_ip_search(xmlDoc *xdoc)
{
#define ZBX_XPATH_HV_IP(nicType, addr)									\
		ZBX_XNN("VirtualNicManagerNetConfig") "[" ZBX_XNN("nicType") "[text()='"nicType "']]/"	\
		ZBX_XNN("candidateVnic") "[" ZBX_XNN("key") "=../" ZBX_XNN("selectedVnic") "]//"	\
		ZBX_XNN("ip") ZBX_XPATH_LN(addr)

#define ZBX_XPATH_HV_IPV4(nicType)	ZBX_XPATH_HV_IP(nicType, "ipAddress")
#define ZBX_XPATH_HV_IPV6(nicType)	ZBX_XPATH_HV_IP(nicType, "ipV6Config")				\
		ZBX_XPATH_LN("ipV6Address") ZBX_XPATH_LN("ipAddress")

#define ZBX_XPATH_HV_NIC(nicType, param)								\
		ZBX_XNN("VirtualNicManagerNetConfig") "[" ZBX_XNN("nicType") "[text()='"nicType "']]/"	\
		ZBX_XNN("candidateVnic") "[" ZBX_XNN("key") "='%s']//" ZBX_XNN("ip") ZBX_XPATH_LN(param)

#define ZBX_XPATH_HV_NIC_IPV4(nicType)	ZBX_XPATH_HV_NIC(nicType, "ipAddress")
#define ZBX_XPATH_HV_NIC_IPV6(nicType)	ZBX_XPATH_HV_NIC(nicType, "ipV6Config")				\
		ZBX_XPATH_LN("ipV6Address") ZBX_XPATH_LN("ipAddress")
#define ZBX_XPATH_HV_NIC_V4MASK(nicType)	ZBX_XPATH_HV_NIC(nicType, "subnetMask")
#define ZBX_XPATH_HV_NIC_V6MASK(nicType)	ZBX_XPATH_HV_NIC(nicType, "ipV6Config")			\
		ZBX_XPATH_LN("ipV6Address") ZBX_XPATH_LN("prefixLength")

	xmlXPathContext		*xpathCtx;
	xmlXPathObject		*xpathObj;
	xmlNode			*node;
	zbx_vector_str_t	selected_ifs, selected_ips;
	char			*value = NULL, *ip_vc = NULL, *ip_gw = NULL, *end;
	int			i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_str_create(&selected_ifs);
	zbx_vector_str_create(&selected_ips);
	xpathCtx = xmlXPathNewContext(xdoc);

	if (NULL == (xpathObj = xmlXPathEvalExpression(
			(const xmlChar *)ZBX_XPATH_PROP_NAME("config.virtualNicManagerInfo.netConfig"), xpathCtx)))
	{
		goto out;
	}

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto out;

	node = xpathObj->nodesetval->nodeTab[0];

	if (SUCCEED != zbx_xml_node_read_values(xdoc, node, ZBX_XNN("VirtualNicManagerNetConfig")
			"[" ZBX_XNN("nicType") "[text()='management']]/" ZBX_XNN("selectedVnic"),
			&selected_ifs) || 0 == selected_ifs.values_num)
	{
		goto out;
	}

	if (1 == selected_ifs.values_num)
	{
		if (NULL == (value = zbx_xml_node_read_value(xdoc, node, ZBX_XPATH_HV_IPV4("management"))))
			value = zbx_xml_node_read_value(xdoc, node, ZBX_XPATH_HV_IPV6("management"));

		goto out;
	}

	zbx_vector_str_sort(&selected_ifs, zbx_natural_str_compare_func);

	/* prefer IP which shares the IP-subnet with the vCenter IP */

	ip_vc = zbx_xml_doc_read_value(xdoc, ZBX_XPATH_PROP_NAME("summary.managementServerIp"));
	zabbix_log(LOG_LEVEL_DEBUG, "%s() managementServerIp rule; selected_ifs:%d ip_vc:%s", __func__,
			selected_ifs.values_num, ZBX_NULL2EMPTY_STR(ip_vc));

	for (i = 0; i < selected_ifs.values_num; i++)
	{
		char	*ip_hv = NULL, *mask = NULL, buff[MAX_STRING_LEN];
		int	ipv6 = 0;

		zbx_snprintf(buff, sizeof(buff), ZBX_XPATH_HV_NIC_IPV4("management"), selected_ifs.values[i]);

		if (NULL == (ip_hv = zbx_xml_node_read_value(xdoc, node, buff)))
		{
			zbx_snprintf(buff, sizeof(buff), ZBX_XPATH_HV_NIC_IPV6("management"), selected_ifs.values[i]);
			ip_hv = zbx_xml_node_read_value(xdoc, node, buff);
			ipv6 = 1;
		}

		if (NULL == ip_hv)
			continue;

		if (0 == ipv6)
			zbx_snprintf(buff, sizeof(buff), ZBX_XPATH_HV_NIC_V4MASK("management"), selected_ifs.values[i]);
		else
			zbx_snprintf(buff, sizeof(buff), ZBX_XPATH_HV_NIC_V6MASK("management"), selected_ifs.values[i]);

		if (NULL == (mask = zbx_xml_node_read_value(xdoc, node, buff)))
		{
			zbx_free(ip_hv);
			continue;
		}

		if (0 == ipv6)
			zbx_snprintf(buff, sizeof(buff), "%s/%d", ip_hv, vmware_v4mask2pefix(mask));
		else
			zbx_snprintf(buff, sizeof(buff), "%s/%s", ip_hv, mask);

		zbx_free(mask);
		zbx_vector_str_append(&selected_ips, zbx_strdup(NULL, buff));

		if (NULL != ip_vc && SUCCEED == zbx_ip_in_list(buff, ip_vc))
		{
			value = ip_hv;
			goto out;
		}

		zbx_free(ip_hv);
		zabbix_log(LOG_LEVEL_TRACE, "%s() managementServerIp fail; ip_vc:%s ip_hv:%s", __func__,
				ZBX_NULL2EMPTY_STR(ip_vc), buff);
	}

	if (0 == selected_ips.values_num)
		goto out;

	/* prefer IP from IP-subnet with default gateway */

	ip_gw = zbx_xml_doc_read_value(xdoc, ZBX_XPATH_PROP_NAME("config.network.ipRouteConfig.defaultGateway"));
	zabbix_log(LOG_LEVEL_DEBUG, "%s() default gateway rule; selected_ips:%d ip_gw:%s", __func__,
			selected_ips.values_num, ZBX_NULL2EMPTY_STR(ip_gw));

	for (i = 0; NULL != ip_gw && i < selected_ips.values_num; i++)
	{
		if (SUCCEED != zbx_ip_in_list(selected_ips.values[i], ip_gw))
		{
			zabbix_log(LOG_LEVEL_TRACE, "%s() default gateway fail; ip_gw:%s ip_hv:%s", __func__,
					ip_gw, selected_ips.values[i]);
			continue;
		}

		if (NULL != (end = strchr(selected_ips.values[i], '/')))
			*end = '\0';

		value = zbx_strdup(NULL, selected_ips.values[i]);
		goto out;
	}

	/* prefer IP from interface with lowest id */

	zabbix_log(LOG_LEVEL_DEBUG, "%s() lowest interface id rule", __func__);

	if (NULL != (end = strchr(selected_ips.values[0], '/')))
		*end = '\0';

	value = zbx_strdup(NULL, selected_ips.values[0]);
out:
	zbx_vector_str_clear_ext(&selected_ifs, zbx_str_free);
	zbx_vector_str_clear_ext(&selected_ips, zbx_str_free);
	zbx_vector_str_destroy(&selected_ifs);
	zbx_vector_str_destroy(&selected_ips);
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);
	zbx_free(ip_vc);
	zbx_free(ip_gw);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ip:%s", __func__, ZBX_NULL2EMPTY_STR(value));

	return value;
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
static int	vmware_hv_ds_access_update(zbx_vmware_service_t *service, CURL *easyhandle, const char *hv_uuid,
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

#define ZBX_XPATH_HV_PNICS()										\
	"/*/*/*/*/*/*[local-name()='propSet']/*[local-name()='val']/*[local-name()='PhysicalNic']"	\

static void	vmware_service_get_hv_pnics_data(xmlDoc *details, zbx_vector_vmware_pnic_t *nics)
{
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	int 		i = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	xpathCtx = xmlXPathNewContext(details);

	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)ZBX_XPATH_HV_PNICS(), xpathCtx)))
		goto clean;

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto clean;

	nodeset = xpathObj->nodesetval;
	zbx_vector_vmware_pnic_reserve(nics, (size_t)nodeset->nodeNr);

	for (; i < nodeset->nodeNr; i++)
	{
		zbx_vmware_pnic_t	*nic;
		char			*value;

		if (NULL == (value = zbx_xml_node_read_value(details, nodeset->nodeTab[i], ZBX_XNN("device"))))
			continue;

		nic = (zbx_vmware_pnic_t *)zbx_malloc(NULL, sizeof(zbx_vmware_pnic_t));
		memset(nic, 0, sizeof(zbx_vmware_pnic_t));
		nic->name = value;

		if (NULL != (value = zbx_xml_node_read_value(details, nodeset->nodeTab[i],
				ZBX_XNN("linkSpeed") ZBX_XPATH_LN("speedMb"))))
		{
			ZBX_STR2UINT64(nic->speed, value);
			zbx_free(value);
		}

		if (NULL != (value = zbx_xml_node_read_value(details, nodeset->nodeTab[i],
				ZBX_XNN("linkSpeed") ZBX_XPATH_LN("duplex"))))
		{
			nic->duplex = 0 == strcmp(value, "true") ? ZBX_DUPLEX_FULL : ZBX_DUPLEX_HALF;
			zbx_free(value);
		}

		nic->driver = zbx_xml_node_read_value(details, nodeset->nodeTab[i], ZBX_XNN("driver"));
		nic->mac = zbx_xml_node_read_value(details, nodeset->nodeTab[i], ZBX_XNN("mac"));
		zbx_vector_vmware_pnic_append(nics, nic);
	}

	zbx_vector_vmware_pnic_sort(nics, vmware_pnic_compare);
clean:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() found:%d", __func__, i);
}

/******************************************************************************
 *                                                                            *
 * Function: vmware_hv_vsan_uuid                                              *
 *                                                                            *
 * Purpose: search for Datastore uuid with type equal to 'vsan'               *
 *                                                                            *
 * Parameters: dss    - [IN] the vector with all Datastores                   *
 *             hv_dss - [IN] the vector with all Datastores attechad to HV    *
 *                                                                            *
 * Return value: pointer to vsan DS uuid or NULL                              *
 *                                                                            *
 ******************************************************************************/
static const char	*vmware_hv_vsan_uuid(zbx_vector_vmware_datastore_t *dss, zbx_vector_str_t *hv_dss)
{
	int	i;

	for (i = 0; i < hv_dss->values_num; i++)
	{
		int			j;
		zbx_vmware_datastore_t	*ds, ds_cmp;

		ds_cmp.id = hv_dss->values[i];

		if (FAIL == (j = zbx_vector_vmware_datastore_bsearch(dss, &ds_cmp, vmware_ds_id_compare)))
			continue;

		ds = dss->values[j];

		if ('v' == *ds->type && 0 == strcmp("vsan", ds->type))	/* only one vsan can be attached to HV */
			return ds->uuid;
	}

	return NULL;
}


/******************************************************************************
 *                                                                            *
 * Function: vmware_service_init_hv                                           *
 *                                                                            *
 * Purpose: initialize vmware hypervisor object                               *
 *                                                                            *
 * Parameters: service      - [IN] the vmware service                         *
 *             easyhandle   - [IN] the CURL handle                            *
 *             id           - [IN] the vmware hypervisor id                   *
 *             dss          - [IN/OUT] the vector with all Datastores         *
 *             rpools       - [IN/OUT] the vector with all Resource Pools     *
 *             cq_values    - [IN/OUT] the vector with custom query entries   *
 *             alarms_data  - [IN/OUT] the vector with all alarms             *
 *             hv           - [OUT] the hypervisor object (must be allocated) *
 *             error        - [OUT] the error message in the case of failure  *
 *                                                                            *
 * Return value: SUCCEED - the hypervisor object was initialized successfully *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	vmware_service_init_hv(zbx_vmware_service_t *service, CURL *easyhandle, const char *id,
		zbx_vector_vmware_datastore_t *dss, zbx_vector_vmware_resourcepool_t *rpools,
		zbx_vector_cq_value_t *cq_values, zbx_vmware_alarms_data_t *alarms_data, zbx_vmware_hv_t *hv,
		char **error)
{
	char				*value, *cq_prop;
	int				i, j, ret;
	xmlDoc				*details = NULL, *multipath_data = NULL;
	zbx_vector_str_t		datastores, vms;
	zbx_vector_cq_value_t		cqvs;
	zbx_vector_ptr_pair_t		disks_info;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hvid:'%s'", __func__, id);

	memset(hv, 0, sizeof(zbx_vmware_hv_t));

	zbx_vector_vmware_dsname_create(&hv->dsnames);
	zbx_vector_vmware_diskinfo_create(&hv->diskinfo);
	zbx_vector_ptr_create(&hv->vms);

	zbx_vector_str_create(&datastores);
	zbx_vector_str_create(&vms);
	zbx_vector_ptr_pair_create(&disks_info);
	zbx_vector_cq_value_create(&cqvs);

	zbx_vector_vmware_pnic_create(&hv->pnics);
	cq_prop = vmware_cq_prop_soap_request(cq_values, ZBX_VMWARE_SOAP_HV, id, &cqvs);
	ret = vmware_service_get_hv_data(service, easyhandle, id, hv_propmap, ZBX_VMWARE_HVPROPS_NUM, cq_prop, &details,
			error);
	zbx_str_free(cq_prop);

	if (FAIL == ret)
		goto out;

	ret = FAIL;

	if (NULL == (hv->props = xml_read_props(details, hv_propmap, ZBX_VMWARE_HVPROPS_NUM)))
		goto out;

	if (NULL == hv->props[ZBX_VMWARE_HVPROP_HW_UUID])
		goto out;

	hv->uuid = zbx_strdup(NULL, hv->props[ZBX_VMWARE_HVPROP_HW_UUID]);
	hv->id = zbx_strdup(NULL, id);

	vmware_service_get_hv_pnics_data(details, &hv->pnics);
	zbx_vector_str_create(&hv->alarm_ids);

	if (FAIL == vmware_service_get_alarms_data(__func__, service, easyhandle, details, NULL, &hv->alarm_ids,
			alarms_data, error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot get hv %s alarms: %s.", hv->id, *error);
		zbx_str_free(*error);
	}

	if (NULL != (value = zbx_xml_doc_read_value(details, "//*[@type='" ZBX_VMWARE_SOAP_CLUSTER "']")))
		hv->clusterid = value;

	hv->ip = vmware_hv_ip_search(details);

	if (0 != cqvs.values_num)
		vmware_service_cq_prop_value(__func__, details, &cqvs);

	if (SUCCEED != vmware_hv_get_parent_data(service, easyhandle, hv, error))
		goto out;

	zbx_xml_read_values(details, ZBX_XPATH_HV_DATASTORES(), &datastores);
	zbx_vector_str_sort(&datastores, ZBX_DEFAULT_STR_COMPARE_FUNC);
	zbx_vector_vmware_dsname_reserve(&hv->dsnames, (size_t)datastores.values_num);
	zabbix_log(LOG_LEVEL_DEBUG, "%s(): %d datastores are connected to hypervisor \"%s\"", __func__,
			datastores.values_num, hv->id);

	if (SUCCEED != vmware_service_hv_disks_get_info(service, easyhandle, details, id, dss,
			vmware_hv_vsan_uuid(dss, &datastores), &disks_info, error))
	{
		goto out;
	}

	if (0 != disks_info.values_num && SUCCEED != vmware_service_hv_get_multipath_data(service, easyhandle, id,
			&multipath_data, error))
	{
		goto out;
	}

	if (SUCCEED != vmware_hv_ds_access_update(service, easyhandle, hv->uuid, hv->id, &datastores, dss, error))
		goto out;

	for (i = 0; i < datastores.values_num; i++)
	{
		zbx_vmware_datastore_t	*ds, ds_cmp;
		zbx_vmware_dsname_t	*dsname;

		ds_cmp.id = datastores.values[i];

		if (FAIL == (j = zbx_vector_vmware_datastore_bsearch(dss, &ds_cmp, vmware_ds_id_compare)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): Datastore \"%s\" not found on hypervisor \"%s\".", __func__,
					datastores.values[i], hv->id);
			continue;
		}

		ds = dss->values[j];
		dsname = (zbx_vmware_dsname_t *)zbx_malloc(NULL, sizeof(zbx_vmware_dsname_t));
		dsname->name = zbx_strdup(NULL, ds->name);
		dsname->uuid = zbx_strdup(NULL, ds->uuid);
		zbx_vector_vmware_hvdisk_create(&dsname->hvdisks);
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): for %d diskextents check multipath at ds:\"%s\"", __func__,
				ds->diskextents.values_num, ds->name);

		for (j = 0; NULL != multipath_data && j < ds->diskextents.values_num; j++)
		{
			zbx_vmware_diskextent_t	*diskextent = ds->diskextents.values[j];
			zbx_vmware_hvdisk_t	hvdisk;
			zbx_vmware_diskinfo_t	di;
			zbx_ptr_pair_t		pair_cmp = {.second = &di};
			const char		*lun;
			char			tmp[MAX_STRING_LEN];
			int			k;

			di.diskname = diskextent->diskname;

			if (FAIL == (k = zbx_vector_ptr_pair_bsearch(&disks_info, pair_cmp,
					vmware_diskinfo_diskname_compare)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s(): not found diskextent: %s",
						__func__, diskextent->diskname);
				continue;
			}

			lun = (const char*)disks_info.values[k].first;
			zbx_snprintf(tmp, sizeof(tmp), ZBX_XPATH_HV_MULTIPATH_PATHS(), lun);

			if (SUCCEED != zbx_xml_doc_read_num(multipath_data, tmp, &hvdisk.multipath_total) ||
					0 == hvdisk.multipath_total)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s(): for diskextent: %s and lun: %s"
						" multipath data is not found", __func__, diskextent->diskname, lun);
				continue;
			}

			zbx_snprintf(tmp, sizeof(tmp), ZBX_XPATH_HV_MULTIPATH_ACTIVE_PATHS(), lun);

			if (SUCCEED != zbx_xml_doc_read_num(multipath_data, tmp, &hvdisk.multipath_active))
				hvdisk.multipath_active = 0;

			hvdisk.partitionid = diskextent->partitionid;
			zbx_vector_vmware_hvdisk_append(&dsname->hvdisks, hvdisk);
		}

		zbx_vector_vmware_hvdisk_sort(&dsname->hvdisks, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_vmware_dsname_append(&hv->dsnames, dsname);
	}

	zbx_vector_vmware_dsname_sort(&hv->dsnames, vmware_dsname_compare);
	zbx_xml_read_values(details, ZBX_XPATH_HV_VMS(), &vms);
	zbx_vector_ptr_reserve(&hv->vms, (size_t)(vms.values_num + hv->vms.values_alloc));

	for (i = 0; i < vms.values_num; i++)
	{
		zbx_vmware_vm_t	*vm;

		if (NULL != (vm = vmware_service_create_vm(service, easyhandle, vms.values[i], rpools, cq_values,
				alarms_data, error)))
		{
			zbx_vector_ptr_append(&hv->vms, vm);
		}
		else if (NULL != *error)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Unable initialize vm %s: %s.", vms.values[i], *error);
			zbx_free(*error);
		}
	}

	zbx_vector_vmware_diskinfo_reserve(&hv->diskinfo, (size_t)disks_info.values_num);

	for (i = 0; i < disks_info.values_num; i++)
	{
		zbx_vector_vmware_diskinfo_append(&hv->diskinfo, disks_info.values[i].second);
		disks_info.values[i].second = NULL;
	}

	ret = SUCCEED;
out:
	zbx_xml_free_doc(multipath_data);
	zbx_xml_free_doc(details);

	zbx_vector_str_clear_ext(&vms, zbx_str_free);
	zbx_vector_str_destroy(&vms);

	zbx_vector_str_clear_ext(&datastores, zbx_str_free);
	zbx_vector_str_destroy(&datastores);
	zbx_vector_cq_value_destroy(&cqvs);

	for (i = 0; i < disks_info.values_num; i++)
	{
		zbx_str_free(disks_info.values[i].first);

		if (NULL != disks_info.values[i].second)
			vmware_diskinfo_free((zbx_vmware_diskinfo_t *)disks_info.values[i].second);
	}

	zbx_vector_ptr_pair_destroy(&disks_info);

	if (SUCCEED != ret)
		vmware_hv_clean(hv);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

#endif /* defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL) */
