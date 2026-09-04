/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "zbxcacheconfig.h"
#include "../zbxexpression/datafunc.h"
#include "zbx_expression_constants.h"

/******************************************************************************
 *                                                                            *
 * Purpose: resolves macros in item tags                                      *
 *                                                                            *
 * Parameters: p            - [IN] macro resolver data structure              *
 *             um_handle    - [IN] user macro cache handle                    *
 *             hostid       - [IN]                                            *
 *             itemid       - [IN]                                            *
 *             replace_with - [OUT] pointer to value to replace macro with    *
 *             data         - [IN/OUT] pointer to original input raw string   *
 *                                  (for macro in macro resolving)            *
 *             error        - [OUT] pointer to pre-allocated error message    *
 *                                  buffer (can be NULL)                      *
 *             maxerrlen    - [IN] size of error message buffer (can be 0 if  *
 *                                 'error' is NULL)                           *
 *                                                                            *
 ******************************************************************************/
static int	macro_item_tag_resolv_impl(zbx_macro_resolv_data_t *p, const zbx_dc_um_handle_t *um_handle,
		zbx_uint64_t hostid, zbx_uint64_t itemid, char **replace_with, char **data, char *error,
		size_t maxerrlen)
{
	ZBX_UNUSED(data);
	ZBX_UNUSED(error);
	ZBX_UNUSED(maxerrlen);

	if (ZBX_TOKEN_USER_MACRO == p->token.type || (ZBX_TOKEN_USER_FUNC_MACRO == p->token.type &&
			0 == strncmp(p->macro, MVAR_USER_MACRO, ZBX_CONST_STRLEN(MVAR_USER_MACRO))))
	{
		zbx_dc_get_user_macro(um_handle, p->macro, &hostid, 1, replace_with);
	}
	else if (0 == strncmp(p->macro, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)))
	{
		expr_dc_get_host_inventory_by_hostid(p->macro, hostid, replace_with);
	}
	else if (0 == strcmp(p->macro, MVAR_HOST_ID))
	{
		expr_dc_get_host_value(itemid, replace_with, ZBX_REQUEST_HOST_ID);
	}
	else if (0 == strcmp(p->macro, MVAR_HOST_HOST))
	{
		expr_dc_get_host_value(itemid, replace_with, ZBX_REQUEST_HOST_HOST);
	}
	else if (0 == strcmp(p->macro, MVAR_HOST_NAME))
	{
		expr_dc_get_host_value(itemid, replace_with, ZBX_REQUEST_HOST_NAME);
	}
	else if (0 == strcmp(p->macro, MVAR_HOST_IP))
	{
		expr_dc_get_interface_value(hostid, itemid, replace_with, ZBX_REQUEST_HOST_IP);
	}
	else if (0 == strcmp(p->macro, MVAR_HOST_DNS))
	{
		expr_dc_get_interface_value(hostid, itemid, replace_with, ZBX_REQUEST_HOST_DNS);
	}
	else if (0 == strcmp(p->macro, MVAR_HOST_CONN))
	{
		expr_dc_get_interface_value(hostid, itemid, replace_with, ZBX_REQUEST_HOST_CONN);
	}
	else if (0 == strcmp(p->macro, MVAR_HOST_PORT))
	{
		expr_dc_get_interface_value(hostid, itemid, replace_with, ZBX_REQUEST_HOST_PORT);
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: callback helper for resolves macros in item tags                  *
 *                                                                            *
 * Parameters: p            - [IN] macro resolver data structure              *
 *             args         - [IN] list of variadic parameters                *
 *                                 Expected content:                          *
 *                                  - const char *tz: name of timezone        *
 *                                      (can be NULL)                         *
 *             replace_with - [OUT] pointer to value to replace macro with    *
 *             data         - [IN/OUT] pointer to original input raw string   *
 *                                  (for macro in macro resolving)            *
 *             error        - [OUT] pointer to pre-allocated error message    *
 *                                  buffer (can be NULL)                      *
 *             maxerrlen    - [IN] size of error message buffer (can be 0 if  *
 *                                 'error' is NULL)                           *
 *                                                                            *
 * Comments: intended for use with zbx_substitute_macros()                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_macro_item_tag_resolv(zbx_macro_resolv_data_t *p, va_list args, char **replace_with, char **data,
		char *error, size_t maxerrlen)
{
	const zbx_dc_um_handle_t	*um_handle = va_arg(args, zbx_dc_um_handle_t *);
	const zbx_uint64_t		hostid = va_arg(args, zbx_uint64_t);
	const zbx_uint64_t		itemid = va_arg(args, zbx_uint64_t);

	return macro_item_tag_resolv_impl(p, um_handle, hostid, itemid, replace_with, data, error, maxerrlen);
}

/******************************************************************************
 *                                                                            *
 * Purpose: resolves macros in item tags for event                            *
 *                                                                            *
 * Parameters: p            - [IN] macro resolver data structure              *
 *             args         - [IN] list of variadic parameters                *
 *                                 Expected content:                          *
 *                                  - const char *tz: name of timezone        *
 *                                      (can be NULL)                         *
 *             replace_with - [OUT] pointer to value to replace macro with    *
 *             data         - [IN/OUT] pointer to original input raw string   *
 *                                  (for macro in macro resolving)            *
 *             error        - [OUT] pointer to pre-allocated error message    *
 *                                  buffer (can be NULL)                      *
 *             maxerrlen    - [IN] size of error message buffer (can be 0 if  *
 *                                 'error' is NULL)                           *
 *                                                                            *
 * Comments: intended for use with zbx_substitute_macros()                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_macro_event_item_tag_resolv(zbx_macro_resolv_data_t *p, va_list args, char **replace_with, char **data,
		char *error, size_t maxerrlen)
{
	/* Passed arguments */
	const zbx_dc_um_handle_t	*um_handle = va_arg(args, zbx_dc_um_handle_t *);
	const zbx_db_event		*event = va_arg(args, const zbx_db_event *);
	const zbx_uint64_t		hostid = va_arg(args, zbx_uint64_t);
	const zbx_uint64_t		itemid = va_arg(args, zbx_uint64_t);

	if (0 == p->indexed)
	{
		if (EVENT_SOURCE_TRIGGERS == event->source && 0 == strcmp(p->macro, MVAR_TRIGGER_ID))
		{
			*replace_with = zbx_dsprintf(*replace_with, ZBX_FS_UI64, event->objectid);
		}
		else if (EVENT_SOURCE_TRIGGERS == event->source || EVENT_SOURCE_INTERNAL == event->source)
		{
			macro_item_tag_resolv_impl(p, um_handle, hostid, itemid, replace_with, data, error, maxerrlen);
		}
	}

	return SUCCEED;
}
