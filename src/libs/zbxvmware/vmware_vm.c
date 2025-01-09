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

#include "vmware_vm.h"

#include "zbxcommon.h"

#if defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL)

#include "vmware_shmem.h"

#include "zbxtime.h"
#include "zbxstr.h"
#include "zbxxml.h"
#ifdef HAVE_LIBXML2
#	include <libxml/xpath.h>
#endif
#include "zbxalgo.h"
#include "zbxjson.h"
#include "zbxnum.h"

#define ZBX_VMPROPMAP(property)										\
	{property, ZBX_XPATH_PROP_OBJECT(ZBX_VMWARE_SOAP_VM) ZBX_XPATH_PROP_NAME_NODE(property), NULL, 0}

static int	vmware_service_get_vm_snapshot(void *xml_node, char **jstr);

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

#undef ZBX_VMPROPMAP

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
 * Purpose: frees shared resources allocated to store virtual machine         *
 *                                                                            *
 * Parameters: vm - [IN]                                                      *
 *                                                                            *
 ******************************************************************************/
void	vmware_vm_shared_free(zbx_vmware_vm_t *vm)
{
	zbx_vector_vmware_dev_ptr_clear_ext(&vm->devs, vmware_shmem_dev_free);
	zbx_vector_vmware_dev_ptr_destroy(&vm->devs);

	zbx_vector_vmware_fs_ptr_clear_ext(&vm->file_systems, vmware_shmem_fs_free);
	zbx_vector_vmware_fs_ptr_destroy(&vm->file_systems);

	zbx_vector_vmware_custom_attr_ptr_clear_ext(&vm->custom_attrs, vmware_shmem_custom_attr_free);
	zbx_vector_vmware_custom_attr_ptr_destroy(&vm->custom_attrs);

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
 * Purpose: frees resources allocated to store vm device object               *
 *                                                                            *
 * Parameters: dev - [IN] vm device                                           *
 *                                                                            *
 ******************************************************************************/
static void	vmware_dev_free(zbx_vmware_dev_t *dev)
{
	zbx_free(dev->instance);
	zbx_free(dev->label);
	vmware_props_free(dev->props, ZBX_VMWARE_DEV_PROPS_NUM);
	zbx_free(dev);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store vm file system object          *
 *                                                                            *
 * Parameters: fs - [IN] file system                                          *
 *                                                                            *
 ******************************************************************************/
static void	vmware_fs_free(zbx_vmware_fs_t *fs)
{
	zbx_free(fs->path);
	zbx_free(fs);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store vm custom attributes           *
 *                                                                            *
 * Parameters: ca - [IN] custom attribute                                     *
 *                                                                            *
 ******************************************************************************/
static void	vmware_custom_attr_free(zbx_vmware_custom_attr_t *ca)
{
	zbx_free(ca->name);
	zbx_free(ca->value);
	zbx_free(ca);
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store virtual machine                *
 *                                                                            *
 * Parameters: vm - [IN]                                                      *
 *                                                                            *
 ******************************************************************************/
void	vmware_vm_free(zbx_vmware_vm_t *vm)
{
	zbx_vector_vmware_dev_ptr_clear_ext(&vm->devs, vmware_dev_free);
	zbx_vector_vmware_dev_ptr_destroy(&vm->devs);

	zbx_vector_vmware_fs_ptr_clear_ext(&vm->file_systems, vmware_fs_free);
	zbx_vector_vmware_fs_ptr_destroy(&vm->file_systems);

	zbx_vector_vmware_custom_attr_ptr_clear_ext(&vm->custom_attrs, vmware_custom_attr_free);
	zbx_vector_vmware_custom_attr_ptr_destroy(&vm->custom_attrs);

	zbx_vector_str_clear_ext(&vm->alarm_ids, zbx_str_free);
	zbx_vector_str_destroy(&vm->alarm_ids);

	zbx_free(vm->uuid);
	zbx_free(vm->id);
	vmware_props_free(vm->props, ZBX_VMWARE_VMPROPS_NUM);
	zbx_free(vm);
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

		zbx_json_initarray(&json_data, VMWARE_SHORT_STR_LEN);

		for (int i = 0; i < ips.values_num; i++)
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

/*********************************************************************************
 *                                                                               *
 * Purpose: gets virtual machine network interface devices' additional           *
 *          properties (props member of zbx_vmware_dev_t)                        *
 *                                                                               *
 * Parameters: details       - [IN] xml document containing virtual machine data *
 *             node          - [IN] xml document node that corresponds to given  *
 *                                  network interface device                     *
 *             guestnet_node - [IN] xml node containing list of guest ips        *
 *                                                                               *
 *********************************************************************************/
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
 * Parameters: vm      - [OUT]                                                *
 *             details - [IN] xml document containing virtual machine data    *
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
	int		nics = 0;

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
	zbx_vector_vmware_dev_ptr_reserve(&vm->devs, (size_t)(nodeset->nodeNr + vm->devs.values_alloc));

	for (int i = 0; i < nodeset->nodeNr; i++)
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

		zbx_vector_vmware_dev_ptr_append(&vm->devs, dev);
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
 * Parameters: vm      - [OUT]                                                *
 *             details - [IN] xml document containing virtual machine data    *
 *                                                                            *
 ******************************************************************************/
static void	vmware_vm_get_disk_devices(zbx_vmware_vm_t *vm, xmlDoc *details)
{
	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;
	int		disks = 0;
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
	zbx_vector_vmware_dev_ptr_reserve(&vm->devs, (size_t)(nodeset->nodeNr + vm->devs.values_alloc));

	for (int i = 0; i < nodeset->nodeNr; i++)
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
			zbx_vector_vmware_dev_ptr_append(&vm->devs, dev);

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

/******************************************************************************
 *                                                                            *
 * Purpose: gets parameters of virtual machine disks                          *
 *                                                                            *
 * Parameters: vm      - [OUT]                                                *
 *             details - [IN] xml document containing virtual machine data    *
 *                                                                            *
 ******************************************************************************/
static void	vmware_vm_get_file_systems(zbx_vmware_vm_t *vm, xmlDoc *details)
{
#	define ZBX_XPATH_VM_GUESTDISKS()									\
		"/*/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='guest.disk']]"		\
		"/*/*[local-name()='GuestDiskInfo']"

	xmlXPathContext	*xpathCtx;
	xmlXPathObject	*xpathObj;
	xmlNodeSetPtr	nodeset;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	xpathCtx = xmlXPathNewContext(details);

	if (NULL == (xpathObj = xmlXPathEvalExpression((const xmlChar *)ZBX_XPATH_VM_GUESTDISKS(), xpathCtx)))
		goto clean;

	if (0 != xmlXPathNodeSetIsEmpty(xpathObj->nodesetval))
		goto clean;

	nodeset = xpathObj->nodesetval;
	zbx_vector_vmware_fs_ptr_reserve(&vm->file_systems, (size_t)(nodeset->nodeNr + vm->file_systems.values_alloc));

	for (int i = 0; i < nodeset->nodeNr; i++)
	{
		zbx_vmware_fs_t	*fs;
		char		*value;

		if (NULL == (value = zbx_xml_node_read_value(details, nodeset->nodeTab[i],
				"*[local-name()='diskPath']")))
		{
			continue;
		}

		fs = (zbx_vmware_fs_t *)zbx_malloc(NULL, sizeof(zbx_vmware_fs_t));
		memset(fs, 0, sizeof(zbx_vmware_fs_t));

		fs->path = value;

		if (NULL != (value = zbx_xml_node_read_value(details, nodeset->nodeTab[i],
				"*[local-name()='capacity']")))
		{
			ZBX_STR2UINT64(fs->capacity, value);
			zbx_free(value);
		}

		if (NULL != (value = zbx_xml_node_read_value(details, nodeset->nodeTab[i],
				"*[local-name()='freeSpace']")))
		{
			ZBX_STR2UINT64(fs->free_space, value);
			zbx_free(value);
		}

		zbx_vector_vmware_fs_ptr_append(&vm->file_systems, fs);
	}
clean:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() found:%d", __func__, vm->file_systems.values_num);

#	undef ZBX_XPATH_VM_GUESTDISKS
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets custom attributes data of virtual machine                    *
 *                                                                            *
 * Parameters: vm      - [OUT]                                                *
 *             details - [IN] xml document containing virtual machine data    *
 *                                                                            *
 ******************************************************************************/
static void	vmware_vm_get_custom_attrs(zbx_vmware_vm_t *vm, xmlDoc *details)
{
#	define ZBX_XPATH_VM_CUSTOM_FIELD_VALUES()				\
		ZBX_XPATH_PROP_NAME("customValue") ZBX_XPATH_LN("CustomFieldValue")

	xmlXPathContext			*xpathCtx;
	xmlXPathObject			*xpathObj;
	xmlNodeSetPtr			nodeset;
	xmlNode				*node;
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
	zbx_vector_vmware_custom_attr_ptr_reserve(&vm->custom_attrs, (size_t)nodeset->nodeNr);

	for (int i = 0; i < nodeset->nodeNr; i++)
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

		zbx_vector_vmware_custom_attr_ptr_append(&vm->custom_attrs, attr);
	}

	zbx_vector_vmware_custom_attr_ptr_sort(&vm->custom_attrs, vmware_custom_attr_compare_name);
clean:
	xmlXPathFreeObject(xpathObj);
	xmlXPathFreeContext(xpathCtx);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() attributes:%d", __func__, vm->custom_attrs.values_num);

#	undef ZBX_XPATH_VM_CUSTOM_FIELD_VALUES
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets virtual machine data                                         *
 *                                                                            *
 * Parameters: service      - [IN] vmware service                             *
 *             easyhandle   - [IN] CURL handle                                *
 *             vmid         - [IN] virtual machine id                         *
 *             propmap      - [IN] xpaths of properties to read               *
 *             props_num    - [IN] number of properties to read               *
 *             cq_prop      - [IN] soap part of query with cq property        *
 *             xdoc         - [OUT] reference to output xml document          *
 *             error        - [OUT] error message in case of failure          *
 *                                                                            *
 * Return value: SUCCEED - operation has completed successfully               *
 *               FAIL    - operation has failed                               *
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
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() vmid:'%s'", __func__, vmid);
	props[0] = '\0';

	for (int i = 0; i < props_num; i++)
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

#	undef ZBX_POST_VMWARE_VM_STATUS_EX
}

/******************************************************************************
 *                                                                            *
 * Purpose: converts vm folder id to chain of folder names divided by '/'     *
 *                                                                            *
 * Parameters: xdoc      - [IN] xml with all vm details                       *
 *             vm_folder - [IN/OUT] vm property with folder id                *
 *                                                                            *
 * Return value: SUCCEED - operation has completed successfully               *
 *               FAIL    - operation has failed                               *
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
 * Purpose: collects info about snapshot disk size                            *
 *                                                                            *
 * Parameters: xdoc        - [IN] xml document with all details               *
 *             key         - [IN] id of snapshot disk                         *
 *             layout_node - [IN] xml node with snapshot disk info            *
 *             sz          - [OUT] size of snapshot disk                      *
 *             usz         - [OUT] uniquesize of snapshot disk                *
 *                                                                            *
 ******************************************************************************/
static void	vmware_vm_snapshot_disksize(xmlDoc *xdoc, const char *key, xmlNode *layout_node, zbx_uint64_t *sz,
		zbx_uint64_t *usz)
{
	char	*value, xpath[MAX_STRING_LEN];

	zbx_snprintf(xpath, sizeof(xpath), ZBX_XNN("file") "[" ZBX_XNN("key") "='%s' and " ZBX_XNN("accessible")
			"='true'][1]/" ZBX_XNN("size"), key);

	if (NULL != (value = zbx_xml_node_read_value(xdoc, layout_node, xpath)))
	{
		if (SUCCEED != zbx_is_uint64(value, sz))
			*sz = 0;

		zbx_free(value);
	}
	else
	{
		zbx_snprintf(xpath, sizeof(xpath), ZBX_XNN("file") "[" ZBX_XNN("key") "='%s'][1]/" ZBX_XNN("size"),
				key);	/* snapshot version < 6 */

		if (NULL != (value = zbx_xml_node_read_value(xdoc, layout_node, xpath)))
		{
			if (SUCCEED != zbx_is_uint64(value, sz))
				*sz = 0;

			zbx_free(value);
			*usz = 0;
			return;
		}

		*sz = 0;
	}

	zbx_snprintf(xpath, sizeof(xpath), ZBX_XNN("file") "[" ZBX_XNN("key") "='%s' and " ZBX_XNN("accessible")
			"='true'][1]/" ZBX_XNN("uniqueSize"), key);

	if (NULL != (value = zbx_xml_node_read_value(xdoc, layout_node, xpath)))
	{
		if (SUCCEED != zbx_is_uint64(value, usz))
			*usz = 0;

		zbx_free(value);
	}
	else
	{
		*usz = 0;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: collects info about snapshots and creates json                    *
 *                                                                            *
 * Parameters: xdoc        - [IN] xml document with all details               *
 *             snap_node   - [IN] xml node with snapshot info                 *
 *             layout_node - [IN] xml node with snapshot disk info            *
 *             disks_used  - [IN/OUT] processed disk ids                      *
 *             size        - [IN/OUT] total size of all snapshots             *
 *             uniquesize  - [IN/OUT] total uniquesize of all snapshots       *
 *             count       - [IN/OUT] total number of all snapshots           *
 *             latestdate  - [OUT] date of last snapshot                      *
 *             oldestdate  - [OUT] date of oldest snapshot                    *
 *             json_data   - [OUT] json with info about snapshot              *
 *                                                                            *
 * Return value: SUCCEED  - operation has completed successfully              *
 *               FAIL     - operation has failed                              *
 *                                                                            *
 ******************************************************************************/
static int	vmware_vm_snapshot_collect(xmlDoc *xdoc, xmlNode *snap_node, xmlNode *layout_node,
		zbx_vector_uint64_t *disks_used, zbx_uint64_t *size, zbx_uint64_t *uniquesize, zbx_uint64_t *count,
		char **latestdate, char **oldestdate, struct zbx_json *json_data)
{
	int			ret = FAIL;
	char			*value, xpath[MAX_STRING_LEN], *name, *desc, *crtime;
	zbx_vector_str_t	ids;
	zbx_uint64_t		snap_size, snap_usize;
	xmlNode			*next_node;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() count:" ZBX_FS_UI64, __func__, *count);

	zbx_vector_str_create(&ids);

	if (NULL == (value = zbx_xml_node_read_value(xdoc, snap_node, ZBX_XNN("snapshot"))))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s() snapshot empty", __func__);
		goto out;
	}

	zbx_snprintf(xpath, sizeof(xpath), ZBX_XNN("snapshot") "[" ZBX_XNN("key") "='%s'][1]" ZBX_XPATH_LN("disk")
			ZBX_XPATH_LN("chain") ZBX_XPATH_LN("fileKey"), value);

	if (FAIL == zbx_xml_node_read_values(xdoc, layout_node, xpath, &ids))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s() empty list of fileKey", __func__);
		zbx_free(value);
		goto out;
	}

	zbx_snprintf(xpath, sizeof(xpath), ZBX_XNN("snapshot") "[" ZBX_XNN("key") "='%s'][1]"
			ZBX_XPATH_LN("dataKey"), value);
	zbx_free(value);

	if (NULL == (value = zbx_xml_node_read_value(xdoc, layout_node, xpath)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s() dataKey empty", __func__);
		goto out;
	}

	if (0 <= atoi(value))
	{
		vmware_vm_snapshot_disksize(xdoc, value, layout_node, &snap_size, &snap_usize);
	}
	else
	{
		snap_size = 0;
		snap_usize = 0;
	}

	zbx_free(value);

	for (int i = 0; i < ids.values_num; i++)
	{
		zbx_uint64_t	dsize, dusize, disk_id =  (unsigned int)atoi(ids.values[i]);

		if (FAIL != zbx_vector_uint64_search(disks_used, disk_id, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			continue;

		zbx_vector_uint64_append(disks_used, disk_id);
		vmware_vm_snapshot_disksize(xdoc, ids.values[i], layout_node, &dsize, &dusize);
		snap_size += dsize;
		snap_usize += dusize;
	}

	name = zbx_xml_node_read_value(xdoc, snap_node, ZBX_XNN("name"));
	desc = zbx_xml_node_read_value(xdoc, snap_node, ZBX_XNN("description"));
	crtime = zbx_xml_node_read_value(xdoc, snap_node, ZBX_XNN("createTime"));

	zbx_json_addobject(json_data, NULL);
	zbx_json_addstring(json_data, "name", ZBX_NULL2EMPTY_STR(name), ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(json_data, "description", ZBX_NULL2EMPTY_STR(desc), ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(json_data, "createtime", ZBX_NULL2EMPTY_STR(crtime), ZBX_JSON_TYPE_STRING);
	zbx_json_adduint64(json_data, "size", snap_size);
	zbx_json_adduint64(json_data, "uniquesize", snap_usize);
	zbx_json_close(json_data);

	if (NULL != oldestdate)
		*oldestdate = zbx_strdup(NULL, crtime);

	if (NULL != (next_node = zbx_xml_node_get(xdoc, snap_node, ZBX_XNN("childSnapshotList"))))
	{
		ret = vmware_vm_snapshot_collect(xdoc, next_node, layout_node, disks_used, size, uniquesize, count,
				latestdate, NULL, json_data);
	}
	else
	{
		*latestdate = crtime;
		crtime = NULL;
		ret = SUCCEED;
	}

	*count += 1;
	*size += snap_size;
	*uniquesize += snap_usize;

	zbx_free(name);
	zbx_free(desc);
	zbx_free(crtime);
out:
	zbx_vector_str_clear_ext(&ids, zbx_str_free);
	zbx_vector_str_destroy(&ids);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates json with info about vm snapshots                         *
 *                                                                            *
 * Parameters: xml_node - [IN] xml node with last vm snapshot                 *
 *             jstr     - [OUT] json with vm snapshot info                    *
 *                                                                            *
 ******************************************************************************/
static int	vmware_service_get_vm_snapshot(void *xml_node, char **jstr)
{
	xmlNode			*root_node, *layout_node, *node = (xmlNode *)xml_node;
	xmlDoc			*xdoc = node->doc;
	struct zbx_json		json_data;
	int			ret = FAIL;
	char			*latestdate = NULL, *oldestdate = NULL;
	zbx_uint64_t		count, size, uniquesize;
	zbx_vector_uint64_t	disks_used;
	time_t			xml_time, now = time(NULL), latest_age = 0, oldest_age = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_json_init(&json_data, ZBX_JSON_STAT_BUF_LEN);
	zbx_vector_uint64_create(&disks_used);

	if (NULL == (root_node = zbx_xml_node_get(xdoc, node, ZBX_XNN("rootSnapshotList"))))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() rootSnapshotList empty", __func__);
		goto out;
	}

	if (NULL == (layout_node = zbx_xml_doc_get(xdoc, ZBX_XPATH_PROP_NAME("layoutEx"))))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s() layoutEx empty", __func__);
		goto out;
	}

	zbx_json_addarray(&json_data, "snapshot");

	count = 0;
	size = 0;
	uniquesize = 0;

	if (FAIL == (ret = vmware_vm_snapshot_collect(xdoc, root_node, layout_node, &disks_used, &size, &uniquesize,
			&count, &latestdate, &oldestdate, &json_data)))
	{
		goto out;
	}

	if (SUCCEED == zbx_iso8601_utc(ZBX_NULL2EMPTY_STR(latestdate), &xml_time))
		latest_age = now - xml_time;

	if (SUCCEED == zbx_iso8601_utc(ZBX_NULL2EMPTY_STR(oldestdate), &xml_time))
		oldest_age = now - xml_time;

	zbx_json_close(&json_data);
	zbx_json_adduint64(&json_data, "count", count);
	zbx_json_addstring(&json_data, "latestdate", ZBX_NULL2EMPTY_STR(latestdate), ZBX_JSON_TYPE_STRING);
	zbx_json_addint64(&json_data, "latestage", latest_age);
	zbx_json_addstring(&json_data, "oldestdate", ZBX_NULL2EMPTY_STR(oldestdate), ZBX_JSON_TYPE_STRING);
	zbx_json_addint64(&json_data, "oldestage", oldest_age);
	zbx_json_adduint64(&json_data, "size", size);
	zbx_json_adduint64(&json_data, "uniquesize", uniquesize);
	zbx_json_close(&json_data);

	*jstr = zbx_strdup(NULL, json_data.buffer);
out:
	zbx_free(latestdate);
	zbx_free(oldestdate);
	zbx_vector_uint64_destroy(&disks_used);
	zbx_json_free(&json_data);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, FAIL == ret ? zbx_result_string(ret) :
			ZBX_NULL2EMPTY_STR(*jstr));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates virtual machine object                                    *
 *                                                                            *
 * Parameters: service      - [IN] vmware service                             *
 *             easyhandle   - [IN] CURL handle                                *
 *             id           - [IN] virtual machine id                         *
 *             rpools       - [IN/OUT] vector with all Resource Pools         *
 *             cq_values    - [IN/OUT] vector with custom query entries       *
 *             alarms_data  - [IN/OUT] all alarms with cache                  *
 *             error        - [OUT] error message in case of failure          *
 *                                                                            *
 * Return value: The created virtual machine object or NULL if an error was   *
 *               detected.                                                    *
 *                                                                            *
 ******************************************************************************/
zbx_vmware_vm_t	*vmware_service_create_vm(zbx_vmware_service_t *service, CURL *easyhandle,
		const char *id, zbx_vector_vmware_resourcepool_ptr_t *rpools, zbx_vector_cq_value_ptr_t *cq_values,
		zbx_vmware_alarms_data_t *alarms_data, char **error)
{
#	define ZBX_XPATH_VM_UUID()										\
		"/*/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='config.uuid']]"		\
			"/*[local-name()='val']"

#	define ZBX_XPATH_VM_INSTANCE_UUID()					\
		"/*/*/*/*/*/*[local-name()='propSet'][*[local-name()='name'][text()='config.instanceUuid']]"	\
			"/*[local-name()='val']"

	zbx_vmware_vm_t			*vm;
	char				*value, *cq_prop;
	xmlDoc				*details = NULL;
	zbx_vector_cq_value_ptr_t	cqvs;
	const char			*uuid_xpath[3] = {NULL, ZBX_XPATH_VM_UUID(), ZBX_XPATH_VM_INSTANCE_UUID()};
	int				ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() vmid:'%s'", __func__, id);

	vm = (zbx_vmware_vm_t *)zbx_malloc(NULL, sizeof(zbx_vmware_vm_t));
	memset(vm, 0, sizeof(zbx_vmware_vm_t));

	zbx_vector_vmware_dev_ptr_create(&vm->devs);
	zbx_vector_vmware_fs_ptr_create(&vm->file_systems);
	zbx_vector_vmware_custom_attr_ptr_create(&vm->custom_attrs);
	zbx_vector_cq_value_ptr_create(&cqvs);
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
				"\"latestdate\":null,\"latestage\":0,\"oldestdate\":null,\"oldestage\":0,"
				"\"size\":0,\"uniquesize\":0}");
	}

	if (NULL != vm->props[ZBX_VMWARE_VMPROP_RESOURCEPOOL])
	{
		int				i;
		zbx_vmware_resourcepool_t	rpool_cmp;

		rpool_cmp.id = vm->props[ZBX_VMWARE_VMPROP_RESOURCEPOOL];

		if (FAIL != (i = zbx_vector_vmware_resourcepool_ptr_bsearch(rpools, &rpool_cmp,
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
	zbx_vector_cq_value_ptr_destroy(&cqvs);
	zbx_xml_doc_free(details);

	if (SUCCEED != ret)
	{
		vmware_vm_free(vm);
		vm = NULL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return vm;

#	undef ZBX_XPATH_VM_UUID
#	undef ZBX_XPATH_VM_INSTANCE_UUID
}

#endif /* defined(HAVE_LIBXML2) && defined(HAVE_LIBCURL) */
