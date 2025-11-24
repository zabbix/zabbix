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

#include "zbxcacheconfig.h"

#include "zbx_expression_constants.h"
#include "zbxinterface.h"
#include "zbxip.h"
#include "zbxexpr.h"
#include "zbxstr.h"
#include "zbxparam.h"

static int	common_resolv(zbx_macro_resolv_data_t *p, zbx_uint64_t hostid, const char *host, const char *name,
		const zbx_dc_interface_t *interface, zbx_uint64_t itemid, char **replace_to)
{
	int	ret = SUCCEED;

	if (0 == strcmp(p->macro, MVAR_HOST_HOST) || 0 == strcmp(p->macro, MVAR_HOSTNAME))
	{
		*replace_to = zbx_strdup(*replace_to, host);
	}
	else if (0 == strcmp(p->macro, MVAR_HOST_NAME))
	{
		*replace_to = zbx_strdup(*replace_to, name);
	}
	else if (0 == strcmp(p->macro, MVAR_HOST_IP) || 0 == strcmp(p->macro, MVAR_IPADDRESS))
	{
		if (INTERFACE_TYPE_UNKNOWN != interface->type)
		{
			if ('\0' != *interface->ip_orig && FAIL == zbx_is_ip(interface->ip_orig))
			{
				ret = FAIL;
			}
			else
				*replace_to = zbx_strdup(*replace_to, interface->ip_orig);
		}
		else
		{
			ret = zbx_dc_get_interface_value(hostid, itemid, replace_to, ZBX_DC_REQUEST_HOST_IP);
		}
	}
	else if	(0 == strcmp(p->macro, MVAR_HOST_DNS))
	{
		if (INTERFACE_TYPE_UNKNOWN != interface->type)
		{
			if ('\0' != *interface->dns_orig && FAIL == zbx_is_ip(interface->dns_orig) &&
					FAIL == zbx_validate_hostname(interface->dns_orig))
			{
				ret = FAIL;
			}
			else
				*replace_to = zbx_strdup(*replace_to, interface->dns_orig);
		}
		else
		{
			ret = zbx_dc_get_interface_value(hostid, itemid, replace_to, ZBX_DC_REQUEST_HOST_DNS);
		}
	}
	else if (0 == strcmp(p->macro, MVAR_HOST_CONN))
	{
		if (INTERFACE_TYPE_UNKNOWN != interface->type)
		{
			if (FAIL == zbx_is_ip(interface->addr) && FAIL == zbx_validate_hostname(interface->addr))
				ret = FAIL;
			else
				*replace_to = zbx_strdup(*replace_to, interface->addr);
		}
		else
		{
			ret = zbx_dc_get_interface_value(hostid, itemid, replace_to, ZBX_DC_REQUEST_HOST_CONN);
		}
	}

	return ret;
}

int	zbx_macro_query_filter_resolv(zbx_macro_resolv_data_t *p, va_list args, char **replace_to, char **data,
		char *error, size_t maxerrlen)
{
	/* Passed arguments */
	const zbx_dc_item_t	*item = va_arg(args, const zbx_dc_item_t *);

	int			ret = SUCCEED;

	ZBX_UNUSED(data);
	ZBX_UNUSED(error);
	ZBX_UNUSED(maxerrlen);

	if (0 == p->indexed)
	{
		if (0 == strcmp(p->macro, MVAR_HOST_PORT))
		{
			if (INTERFACE_TYPE_UNKNOWN != item->interface.type)
			{
				*replace_to = zbx_dsprintf(*replace_to, "%hu", item->interface.port);
			}
			else
			{
				ret = zbx_dc_get_interface_value(item->host.hostid, item->itemid, replace_to,
						ZBX_DC_REQUEST_HOST_PORT);
			}
		}
		else
		{
			ret = common_resolv(p, item->host.hostid, item->host.host, item->host.name, &item->interface,
					item->itemid, replace_to);
		}
	}

	if (NULL != replace_to)
	{
		char	*esc = zbx_dyn_escape_string(*replace_to, "\\");

		zbx_free(*replace_to);
		*replace_to = esc;
	}

	return ret;
}

int	zbx_macro_allowed_hosts_resolv(zbx_macro_resolv_data_t *p, va_list args, char **replace_to, char **data,
		char *error, size_t maxerrlen)
{
	/* Passed arguments */
	const zbx_dc_um_handle_t	*um_handle = va_arg(args, const zbx_dc_um_handle_t *);
	const zbx_history_recv_item_t	*item = va_arg(args, const zbx_history_recv_item_t *);

	int	ret = SUCCEED;

	ZBX_UNUSED(data);
	ZBX_UNUSED(error);
	ZBX_UNUSED(maxerrlen);

	if (0 == p->indexed)
	{
		if ((ZBX_TOKEN_USER_MACRO == p->token.type || (ZBX_TOKEN_USER_FUNC_MACRO == p->token.type)))
		{
			zbx_dc_get_user_macro(um_handle, p->macro, &item->host.hostid, 1, replace_to);

			p->pos = (int)p->token.loc.r;
		}
		else
		{
			ret = common_resolv(p, item->host.hostid, item->host.host, item->host.name, &item->interface,
					item->itemid, replace_to);
		}
	}

	return ret;
}

int	zbx_macro_field_params_resolv(zbx_macro_resolv_data_t *p, va_list args, char **replace_to, char **data,
		char *error, size_t maxerrlen)
{
	/* Passed arguments */
	const zbx_dc_um_handle_t	*um_handle = va_arg(args, const zbx_dc_um_handle_t *);
	const zbx_dc_item_t		*item = va_arg(args, const zbx_dc_item_t *);

	int	ret = SUCCEED;

	ZBX_UNUSED(data);
	ZBX_UNUSED(error);
	ZBX_UNUSED(maxerrlen);

	if (0 == p->indexed)
	{
		if ((ZBX_TOKEN_USER_MACRO == p->token.type || (ZBX_TOKEN_USER_FUNC_MACRO == p->token.type)))
		{
			zbx_dc_get_user_macro(um_handle, p->macro, &item->host.hostid, 1, replace_to);

			p->pos = (int)p->token.loc.r;
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_PORT))
		{
			if (INTERFACE_TYPE_UNKNOWN != item->interface.type)
			{
				*replace_to = zbx_dsprintf(*replace_to, "%hu", item->interface.port);
			}
			else
			{
				ret = zbx_dc_get_interface_value(item->host.hostid, item->itemid, replace_to,
						ZBX_DC_REQUEST_HOST_PORT);
			}
		}
		else
		{
			ret = common_resolv(p, item->host.hostid, item->host.host, item->host.name, &item->interface,
					item->itemid, replace_to);
		}
	}

	return ret;
}

int	zbx_macro_script_params_field_resolv(zbx_macro_resolv_data_t *p, va_list args, char **replace_to, char **data,
		char *error, size_t maxerrlen)
{
	/* Passed arguments */
	const zbx_dc_um_handle_t	*um_handle = va_arg(args, const zbx_dc_um_handle_t *);
	const zbx_dc_item_t		*item = va_arg(args, const zbx_dc_item_t *);

	int				ret = SUCCEED;

	ZBX_UNUSED(data);
	ZBX_UNUSED(error);
	ZBX_UNUSED(maxerrlen);

	if (0 == p->indexed)
	{
		if ((ZBX_TOKEN_USER_MACRO == p->token.type || (ZBX_TOKEN_USER_FUNC_MACRO == p->token.type)))
		{
			zbx_dc_get_user_macro(um_handle, p->macro, &item->host.hostid, 1, replace_to);

			p->pos = (int)p->token.loc.r;
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_ID))
		{
			*replace_to = zbx_dsprintf(*replace_to, ZBX_FS_UI64, item->itemid);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_KEY))
		{
			*replace_to = zbx_strdup(*replace_to, item->key);
		}
		else if (0 == strcmp(p->macro, MVAR_ITEM_KEY_ORIG))
		{
			*replace_to = zbx_strdup(*replace_to, item->key_orig);
		}
		else if (0 == strncmp(p->macro, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)) ||
				0 == strncmp(p->macro, MVAR_PROFILE, ZBX_CONST_STRLEN(MVAR_PROFILE)))
		{
			ret = zbx_dc_get_host_inventory_by_itemid(p->macro, item->itemid, replace_to);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_PORT))
		{
			if (INTERFACE_TYPE_UNKNOWN != item->interface.type)
			{
				*replace_to = zbx_dsprintf(*replace_to, "%hu", item->interface.port);
			}
			else
			{
				ret = zbx_dc_get_interface_value(item->host.hostid, item->itemid, replace_to,
						ZBX_DC_REQUEST_HOST_PORT);
			}
		}
		else
		{
			ret = common_resolv(p, item->host.hostid, item->host.host, item->host.name, &item->interface,
					item->itemid, replace_to);
		}
	}

	return ret;
}

int	zbx_item_key_subst_cb(const char *data, int level, int num, int quoted, char **param, va_list args)
{
	int	ret = SUCCEED;

	/* Passed parameters */
	const zbx_dc_um_handle_t	*um_handle = va_arg(args, const zbx_dc_um_handle_t *);
	const zbx_dc_item_t		*item = va_arg(args, const zbx_dc_item_t *);

	ZBX_UNUSED(num);

	if (NULL == strchr(data, '{'))
		return ret;

	*param = zbx_strdup(NULL, data);

	if (0 != level)
		zbx_unquote_key_param(*param);

	zbx_substitute_macros(param, NULL, 0, zbx_macro_field_params_resolv, um_handle, item);

	if (0 != level)
	{
		if (FAIL == (ret = zbx_quote_key_param(param, quoted)))
			zbx_free(*param);
	}

	return ret;
}

int	zbx_snmp_oid_subst_cb(const char *data, int level, int num, int quoted, char **param, va_list args)
{
	int	ret = SUCCEED;

	/* Passed parameters */
	const zbx_dc_um_handle_t	*um_handle = va_arg(args, const zbx_dc_um_handle_t *);
	const zbx_uint64_t		*hostid = va_arg(args, const zbx_uint64_t *);

	ZBX_UNUSED(num);

	if (NULL == strchr(data, '{'))
		return ret;

	*param = zbx_strdup(NULL, data);

	if (0 != level)
		zbx_unquote_key_param(*param);

	zbx_dc_expand_user_and_func_macros(um_handle, param, hostid, 1, NULL);

	if (0 != level)
	{
		if (FAIL == (ret = zbx_quote_key_param(param, quoted)))
			zbx_free(*param);
	}

	return ret;
}
