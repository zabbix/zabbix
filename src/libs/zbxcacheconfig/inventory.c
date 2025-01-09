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

#include "zbx_expression_constants.h"
#include "zbxdbhigh.h"
#include "zbxcacheconfig.h"
#include "zbxdbwrap.h"

typedef struct
{
	const char	*macro;
	int		idx;
} inventory_field_t;

static inventory_field_t	inventory_fields[] =
{
	{MVAR_INVENTORY_TYPE, 0},
	{MVAR_PROFILE_DEVICETYPE, 0},	/* deprecated */
	{MVAR_INVENTORY_TYPE_FULL, 1},
	{MVAR_INVENTORY_NAME, 2},
	{MVAR_PROFILE_NAME, 2},		/* deprecated */
	{MVAR_INVENTORY_ALIAS, 3},
	{MVAR_INVENTORY_OS, 4},
	{MVAR_PROFILE_OS, 4},		/* deprecated */
	{MVAR_INVENTORY_OS_FULL, 5},
	{MVAR_INVENTORY_OS_SHORT, 6},
	{MVAR_INVENTORY_SERIALNO_A, 7},
	{MVAR_PROFILE_SERIALNO, 7},	/* deprecated */
	{MVAR_INVENTORY_SERIALNO_B, 8},
	{MVAR_INVENTORY_TAG, 9},
	{MVAR_PROFILE_TAG, 9},		/* deprecated */
	{MVAR_INVENTORY_ASSET_TAG, 10},
	{MVAR_INVENTORY_MACADDRESS_A, 11},
	{MVAR_PROFILE_MACADDRESS, 11},	/* deprecated */
	{MVAR_INVENTORY_MACADDRESS_B, 12},
	{MVAR_INVENTORY_HARDWARE, 13},
	{MVAR_PROFILE_HARDWARE, 13},	/* deprecated */
	{MVAR_INVENTORY_HARDWARE_FULL, 14},
	{MVAR_INVENTORY_SOFTWARE, 15},
	{MVAR_PROFILE_SOFTWARE, 15},	/* deprecated */
	{MVAR_INVENTORY_SOFTWARE_FULL, 16},
	{MVAR_INVENTORY_SOFTWARE_APP_A, 17},
	{MVAR_INVENTORY_SOFTWARE_APP_B, 18},
	{MVAR_INVENTORY_SOFTWARE_APP_C, 19},
	{MVAR_INVENTORY_SOFTWARE_APP_D, 20},
	{MVAR_INVENTORY_SOFTWARE_APP_E, 21},
	{MVAR_INVENTORY_CONTACT, 22},
	{MVAR_PROFILE_CONTACT, 22},	/* deprecated */
	{MVAR_INVENTORY_LOCATION, 23},
	{MVAR_PROFILE_LOCATION, 23},	/* deprecated */
	{MVAR_INVENTORY_LOCATION_LAT, 24},
	{MVAR_INVENTORY_LOCATION_LON, 25},
	{MVAR_INVENTORY_NOTES, 26},
	{MVAR_PROFILE_NOTES, 26},	/* deprecated */
	{MVAR_INVENTORY_CHASSIS, 27},
	{MVAR_INVENTORY_MODEL, 28},
	{MVAR_INVENTORY_HW_ARCH, 29},
	{MVAR_INVENTORY_VENDOR, 30},
	{MVAR_INVENTORY_CONTRACT_NUMBER, 31},
	{MVAR_INVENTORY_INSTALLER_NAME, 32},
	{MVAR_INVENTORY_DEPLOYMENT_STATUS, 33},
	{MVAR_INVENTORY_URL_A, 34},
	{MVAR_INVENTORY_URL_B, 35},
	{MVAR_INVENTORY_URL_C, 36},
	{MVAR_INVENTORY_HOST_NETWORKS, 37},
	{MVAR_INVENTORY_HOST_NETMASK, 38},
	{MVAR_INVENTORY_HOST_ROUTER, 39},
	{MVAR_INVENTORY_OOB_IP, 40},
	{MVAR_INVENTORY_OOB_NETMASK, 41},
	{MVAR_INVENTORY_OOB_ROUTER, 42},
	{MVAR_INVENTORY_HW_DATE_PURCHASE, 43},
	{MVAR_INVENTORY_HW_DATE_INSTALL, 44},
	{MVAR_INVENTORY_HW_DATE_EXPIRY, 45},
	{MVAR_INVENTORY_HW_DATE_DECOMM, 46},
	{MVAR_INVENTORY_SITE_ADDRESS_A, 47},
	{MVAR_INVENTORY_SITE_ADDRESS_B, 48},
	{MVAR_INVENTORY_SITE_ADDRESS_C, 49},
	{MVAR_INVENTORY_SITE_CITY, 50},
	{MVAR_INVENTORY_SITE_STATE, 51},
	{MVAR_INVENTORY_SITE_COUNTRY, 52},
	{MVAR_INVENTORY_SITE_ZIP, 53},
	{MVAR_INVENTORY_SITE_RACK, 54},
	{MVAR_INVENTORY_SITE_NOTES, 55},
	{MVAR_INVENTORY_POC_PRIMARY_NAME, 56},
	{MVAR_INVENTORY_POC_PRIMARY_EMAIL, 57},
	{MVAR_INVENTORY_POC_PRIMARY_PHONE_A, 58},
	{MVAR_INVENTORY_POC_PRIMARY_PHONE_B, 59},
	{MVAR_INVENTORY_POC_PRIMARY_CELL, 60},
	{MVAR_INVENTORY_POC_PRIMARY_SCREEN, 61},
	{MVAR_INVENTORY_POC_PRIMARY_NOTES, 62},
	{MVAR_INVENTORY_POC_SECONDARY_NAME, 63},
	{MVAR_INVENTORY_POC_SECONDARY_EMAIL, 64},
	{MVAR_INVENTORY_POC_SECONDARY_PHONE_A, 65},
	{MVAR_INVENTORY_POC_SECONDARY_PHONE_B, 66},
	{MVAR_INVENTORY_POC_SECONDARY_CELL, 67},
	{MVAR_INVENTORY_POC_SECONDARY_SCREEN, 68},

	{MVAR_INVENTORY_POC_SECONDARY_NOTES, 69},
	{0}
};

/******************************************************************************
 *                                                                            *
 * Purpose: request host inventory value by macro and trigger.                *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_get_host_inventory(const char *macro, const zbx_db_trigger *trigger, char **replace_to,
		int N_functionid)
{
	int	i;

	for (i = 0; NULL != inventory_fields[i].macro; i++)
	{
		if (0 == strcmp(macro, inventory_fields[i].macro))
		{
			zbx_uint64_t	itemid;

			if (SUCCEED != zbx_db_trigger_get_itemid(trigger, N_functionid, &itemid))
				return FAIL;

			return zbx_dc_get_host_inventory_value_by_itemid(itemid, replace_to, inventory_fields[i].idx);
		}
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: request host inventory value by macro and itemid.                 *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_get_host_inventory_by_itemid(const char *macro, zbx_uint64_t itemid, char **replace_to)
{
	int	i;

	for (i = 0; NULL != inventory_fields[i].macro; i++)
	{
		if (0 == strcmp(macro, inventory_fields[i].macro))
			return zbx_dc_get_host_inventory_value_by_itemid(itemid, replace_to, inventory_fields[i].idx);
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: request host inventory value by macro and hostid.                 *
 *                                                                            *
 * Return value: upon successful completion return SUCCEED                    *
 *               otherwise FAIL                                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_dc_get_host_inventory_by_hostid(const char *macro, zbx_uint64_t hostid, char **replace_to)
{
	int	i;

	for (i = 0; NULL != inventory_fields[i].macro; i++)
	{
		if (0 == strcmp(macro, inventory_fields[i].macro))
			return zbx_dc_get_host_inventory_value_by_hostid(hostid, replace_to, inventory_fields[i].idx);
	}

	return FAIL;
}
