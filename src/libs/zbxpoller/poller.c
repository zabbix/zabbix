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

#include "zbxpoller.h"

#include "zbx_availability_constants.h"
#include "zbxavailability.h"
#include "zbxcacheconfig.h"
#include "zbxdbhigh.h"
#include "zbxtime.h"
#include "zbxinterface.h"

/******************************************************************************
 *                                                                            *
 * Purpose: writes interface availability changes into database               *
 *                                                                            *
 * Parameters: data        - [IN/OUT] serialized availability data            *
 *             data_alloc  - [IN/OUT] serialized availability data size       *
 *             data_offset - [IN/OUT] serialized availability data offset     *
 *             ia          - [IN] interface availability data                 *
 *                                                                            *
 * Return value: SUCCEED - availability changes were written into db          *
 *               FAIL    - no changes in availability data were detected      *
 *                                                                            *
 ******************************************************************************/
static int	update_interface_availability(unsigned char **data, size_t *data_alloc, size_t *data_offset,
		const zbx_interface_availability_t *ia)
{
	if (FAIL == zbx_interface_availability_is_set(ia))
		return FAIL;

	zbx_availability_serialize_interface(data, data_alloc, data_offset, ia);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Parameters: dc_interface - [IN] interface                                  *
 *             ia           - [OUT] interface availability data               *
 *                                                                            *
 ******************************************************************************/
static void	interface_get_availability(const zbx_dc_interface_t *dc_interface, zbx_interface_availability_t *ia)
{
	zbx_agent_availability_t	*availability = &ia->agent;

	availability->flags = ZBX_FLAGS_AGENT_STATUS;

	availability->available = dc_interface->available;
	availability->error = zbx_strdup(NULL, dc_interface->error);
	availability->errors_from = dc_interface->errors_from;
	availability->disable_until = dc_interface->disable_until;

	ia->interfaceid = dc_interface->interfaceid;
}

/********************************************************************************
 *                                                                              *
 * Parameters: dc_interface - [IN/OUT] interface                                *
 *             ia           - [IN] interface availability data                  *
 *                                                                              *
 *******************************************************************************/
static void	interface_set_availability(zbx_dc_interface_t *dc_interface, const zbx_interface_availability_t *ia)
{
	const zbx_agent_availability_t	*availability = &ia->agent;
	unsigned char			*pavailable;
	int				*perrors_from, *pdisable_until;
	char				*perror;

	pavailable = &dc_interface->available;
	perror = dc_interface->error;
	perrors_from = &dc_interface->errors_from;
	pdisable_until = &dc_interface->disable_until;

	if (0 != (availability->flags & ZBX_FLAGS_AGENT_STATUS_AVAILABLE))
		*pavailable = availability->available;

	if (0 != (availability->flags & ZBX_FLAGS_AGENT_STATUS_ERROR))
		zbx_strlcpy(perror, availability->error, ZBX_INTERFACE_ERROR_LEN_MAX);

	if (0 != (availability->flags & ZBX_FLAGS_AGENT_STATUS_ERRORS_FROM))
		*perrors_from = availability->errors_from;

	if (0 != (availability->flags & ZBX_FLAGS_AGENT_STATUS_DISABLE_UNTIL))
		*pdisable_until = availability->disable_until;
}

static int	interface_availability_by_item_type(unsigned char item_type, unsigned char interface_type)
{
	if ((ITEM_TYPE_ZABBIX == item_type && INTERFACE_TYPE_AGENT == interface_type) ||
			(ITEM_TYPE_SNMP == item_type && INTERFACE_TYPE_SNMP == interface_type) ||
			(ITEM_TYPE_JMX == item_type && INTERFACE_TYPE_JMX == interface_type) ||
			(ITEM_TYPE_IPMI == item_type && INTERFACE_TYPE_IPMI == interface_type))
		return SUCCEED;

	return FAIL;
}

static const char	*item_type_agent_string(zbx_item_type_t item_type)
{
	switch (item_type)
	{
		case ITEM_TYPE_ZABBIX:
			return "Zabbix agent";
		case ITEM_TYPE_SNMP:
			return "SNMP agent";
		case ITEM_TYPE_IPMI:
			return "IPMI agent";
		case ITEM_TYPE_JMX:
			return "JMX agent";
		default:
			return "generic";
	}
}

/********************************************************************************
 *                                                                              *
 * Parameters: ts          - [IN] timestamp                                     *
 *             interface   - [IN]                                               *
 *             itemid      - [IN]                                               *
 *             type        - [IN]                                               *
 *             host        - [IN]                                               *
 *             version     - [IN/OUT] interface version                         *
 *             data        - [IN/OUT] serialized availability data              *
 *             data_alloc  - [IN/OUT] serialized availability data size         *
 *             data_offset - [IN/OUT] serialized availability data offset       *
 *                                                                              *
 *******************************************************************************/
void	zbx_activate_item_interface(zbx_timespec_t *ts, zbx_dc_interface_t *interface, zbx_uint64_t itemid, int type,
		char *host, int version, unsigned char **data, size_t *data_alloc, size_t *data_offset)
{
	zbx_interface_availability_t	in, out;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() interfaceid:" ZBX_FS_UI64 " itemid:" ZBX_FS_UI64 " type:%d version:%x",
			__func__, interface->interfaceid, itemid, (int)type, (unsigned int)version);

	zbx_interface_availability_init(&in, interface->interfaceid);
	zbx_interface_availability_init(&out, interface->interfaceid);

	if (FAIL == interface_availability_by_item_type((unsigned char)type, interface->type))
		goto out;

	interface_get_availability(interface, &in);

	if (INTERFACE_TYPE_AGENT == interface->type && version != interface->version)
		zbx_dc_set_interface_version(interface->interfaceid, version);

	if (FAIL == zbx_dc_interface_activate(interface->interfaceid, ts, &in.agent, &out.agent))
		goto out;

	if (FAIL == update_interface_availability(data, data_alloc, data_offset, &out))
		goto out;

	interface_set_availability(interface, &out);

	if (ZBX_INTERFACE_AVAILABLE_TRUE == in.agent.available)
	{
		zabbix_log(LOG_LEVEL_WARNING, "resuming %s checks on host \"%s\": connection restored",
				item_type_agent_string(type), host);
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "enabling %s checks on host \"%s\": interface became available",
				item_type_agent_string(type), host);
	}
out:
	zbx_interface_availability_clean(&out);
	zbx_interface_availability_clean(&in);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/***********************************************************************************
 *                                                                                 *
 * Parameters: ts                 - [IN] timestamp                                 *
 *             interface          - [IN]                                           *
 *             itemid             - [IN]                                           *
 *             type               - [IN]                                           *
 *             host               - [IN]                                           *
 *             key_orig           - [IN]                                           *
 *             data               - [IN/OUT] serialized availability data          *
 *             data_alloc         - [IN/OUT] serialized availability data size     *
 *             data_offset        - [IN/OUT] serialized availability data offset   *
 *             unavailable_delay  - [IN]                                           *
 *             unreachable_period - [IN]                                           *
 *             unreachable_delay  - [IN]                                           *
 *             error              - [IN/OUT]                                       *
 *                                                                                 *
 ***********************************************************************************/
void	zbx_deactivate_item_interface(zbx_timespec_t *ts, zbx_dc_interface_t *interface, zbx_uint64_t itemid, int type,
		char *host, char *key_orig, unsigned char **data, size_t *data_alloc, size_t *data_offset,
		int unavailable_delay, int unreachable_period, int unreachable_delay, const char *error)
{
	zbx_interface_availability_t	in, out;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() interfaceid:" ZBX_FS_UI64 " itemid:" ZBX_FS_UI64 " type:%d",
			__func__, interface->interfaceid, itemid, type);

	zbx_interface_availability_init(&in, interface->interfaceid);
	zbx_interface_availability_init(&out, interface->interfaceid);

	if (FAIL == interface_availability_by_item_type((unsigned char)type, interface->type))
		goto out;

	interface_get_availability(interface, &in);

	if (FAIL == zbx_dc_interface_deactivate(interface->interfaceid, ts, unavailable_delay, unreachable_period,
			unreachable_delay, &in.agent, &out.agent, error))
	{
		goto out;
	}

	if (FAIL == update_interface_availability(data, data_alloc, data_offset, &out))
		goto out;

	interface_set_availability(interface, &out);

	if (0 == in.agent.errors_from)
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s item \"%s\" on host \"%s\" failed:"
				" first network error, wait for %d seconds",
				item_type_agent_string(type), key_orig, host,
				out.agent.disable_until - ts->sec);
	}
	else if (ZBX_INTERFACE_AVAILABLE_FALSE != in.agent.available)
	{
		if (ZBX_INTERFACE_AVAILABLE_FALSE != out.agent.available)
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s item \"%s\" on host \"%s\" failed:"
					" another network error, wait for %d seconds",
					item_type_agent_string(type), key_orig, host,
					out.agent.disable_until - ts->sec);
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "temporarily disabling %s checks on host \"%s\":"
					" interface unavailable",
					item_type_agent_string(type), host);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() errors_from:%d available:%d", __func__,
			out.agent.errors_from, out.agent.available);
out:
	zbx_interface_availability_clean(&out);
	zbx_interface_availability_clean(&in);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
