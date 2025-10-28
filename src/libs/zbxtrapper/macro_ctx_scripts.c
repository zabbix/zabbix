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

#include "nodecommand.h"

#include "zbxexpr.h"
#include "zbx_expression_constants.h"
#include "zbxcacheconfig.h"
#include "zbxdbwrap.h"
#include "zbxdbhigh.h"
#include "zbxstr.h"
#include "zbx_scripts_constants.h"

/******************************************************************************
 *                                                                            *
 * Purpose: formats full user name from name, surname and alias.              *
 *                                                                            *
 * Parameters: name    - [IN] user name, can be empty string                  *
 *             surname - [IN] user surname, can be empty string               *
 *             alias   - [IN] user alias                                      *
 *                                                                            *
 * Return value: the formatted user fullname.                                 *
 *                                                                            *
 ******************************************************************************/
static char	*format_user_fullname(const char *name, const char *surname, const char *alias)
{
	char	*buf = NULL;
	size_t	buf_alloc = 0, buf_offset = 0;

	zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, name);

	if ('\0' != *surname)
	{
		if (0 != buf_offset)
			zbx_chrcpy_alloc(&buf, &buf_alloc, &buf_offset, ' ');

		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, surname);
	}

	if ('\0' != *alias)
	{
		size_t	offset = buf_offset;

		if (0 != buf_offset)
			zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, " (");

		zbx_strcpy_alloc(&buf, &buf_alloc, &buf_offset, alias);

		if (0 != offset)
			zbx_chrcpy_alloc(&buf, &buf_alloc, &buf_offset, ')');
	}

	return buf;
}

/******************************************************************************
 *                                                                            *
 * Purpose: resolve {USER.*} macros.                                          *
 *                                                                            *
 ******************************************************************************/
static void	resolve_user_macros(zbx_uint64_t userid, const char *m, zbx_user_names_t **user_names,
		char **replace_to)
{
	/* use only one DB request for all occurrences of 5 macros */
	if (NULL == *user_names && SUCCEED != zbx_db_get_user_names(userid, user_names))
		return;

	if (0 == strcmp(m, MVAR_USER_USERNAME) || 0 == strcmp(m, MVAR_USER_ALIAS))
	{
		*replace_to = zbx_strdup(*replace_to, (*user_names)->username);
	}
	else if (0 == strcmp(m, MVAR_USER_NAME))
	{
		*replace_to = zbx_strdup(*replace_to, (*user_names)->name);
	}
	else if (0 == strcmp(m, MVAR_USER_SURNAME))
	{
		*replace_to = zbx_strdup(*replace_to, (*user_names)->surname);
	}
	else if (0 == strcmp(m, MVAR_USER_FULLNAME))
	{
		zbx_free(*replace_to);
		*replace_to = format_user_fullname((*user_names)->name, (*user_names)->surname,
				(*user_names)->username);
	}
}

static void	user_names_clean_rem_wrap(void *ptr)
{
	zbx_user_names_clean((zbx_user_names_t **)ptr);
}

static int	macro_host_script_resolv(zbx_macro_resolv_data_t *p, va_list args, char **replace_to, char **data,
		char *error, size_t maxerrlen)
{
	int	ret = SUCCEED;

	/* Passed arguments */
	zbx_dc_um_handle_t	*um_handle = va_arg(args, zbx_dc_um_handle_t *);

	const zbx_uint64_t	*userid = va_arg(args, const zbx_uint64_t *);
	const zbx_dc_host_t	*dc_host = va_arg(args, const zbx_dc_host_t *);

	ZBX_UNUSED(data);
	ZBX_UNUSED(error);
	ZBX_UNUSED(maxerrlen);

	if (0 == p->indexed)
	{
		if (SUCCEED == zbx_token_is_user_macro(p->macro, &p->token))
		{
			zbx_dc_get_user_macro(um_handle, p->macro, &dc_host->hostid, 1, replace_to);
			p->pos = p->token.loc.r;
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_HOST) || 0 == strcmp(p->macro, MVAR_HOSTNAME))
			*replace_to = zbx_strdup(*replace_to, dc_host->host);
		else if (0 == strcmp(p->macro, MVAR_HOST_NAME))
			*replace_to = zbx_strdup(*replace_to, dc_host->name);
		else if (0 == strcmp(p->macro, MVAR_HOST_IP) || 0 == strcmp(p->macro, MVAR_IPADDRESS))
		{
			ret = zbx_dc_get_interface_value(dc_host->hostid, 0, replace_to, ZBX_DC_REQUEST_HOST_IP);
		}
		else if	(0 == strcmp(p->macro, MVAR_HOST_DNS))
		{
			ret = zbx_dc_get_interface_value(dc_host->hostid, 0, replace_to, ZBX_DC_REQUEST_HOST_DNS);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_CONN))
		{
			ret = zbx_dc_get_interface_value(dc_host->hostid, 0, replace_to, ZBX_DC_REQUEST_HOST_CONN);
		}
		else if (0 == strcmp(p->macro, MVAR_HOST_PORT))
		{
			ret = zbx_dc_get_interface_value(dc_host->hostid, 0, replace_to, ZBX_DC_REQUEST_HOST_PORT);
		}
		else if (0 == strncmp(p->macro, MVAR_INVENTORY, ZBX_CONST_STRLEN(MVAR_INVENTORY)))
		{
			ret = zbx_dc_get_host_inventory_by_hostid(p->macro, dc_host->hostid, replace_to);
		}
		else if (NULL != userid)
		{
			if (0 == strcmp(p->macro, MVAR_USER_USERNAME) || 0 == strcmp(p->macro, MVAR_USER_NAME) ||
					0 == strcmp(p->macro, MVAR_USER_SURNAME) ||
					0 == strcmp(p->macro, MVAR_USER_FULLNAME) ||
					0 == strcmp(p->macro, MVAR_USER_ALIAS))
			{
				zbx_user_names_t	**user_names = (zbx_user_names_t **)zbx_expr_rem(userid,
						sizeof(zbx_user_names_t *), NULL, user_names_clean_rem_wrap);

				resolve_user_macros(*userid, p->macro, user_names, replace_to);
			}
		}
	}

	return ret;
}

static int	macro_normal_script_resolv(zbx_macro_resolv_data_t *p, va_list args, char **replace_to, char **data,
		char *error, size_t maxerrlen)
{
	int	ret = SUCCEED;

	/* Passed arguments */
	zbx_dc_um_handle_t		*um_handle = va_arg(args, zbx_dc_um_handle_t *);

	/* Passed arguments for common resolver */
	const zbx_db_event		*event = va_arg(args, const zbx_db_event *);
	const zbx_db_event		*r_event = va_arg(args, const zbx_db_event *);
	const zbx_uint64_t		*userid = va_arg(args, const zbx_uint64_t *);
	const zbx_dc_host_t		*dc_host = va_arg(args, const zbx_dc_host_t *);
	const char			*tz = va_arg(args, const char *);

	ret = zbx_macro_message_common_resolv(p, um_handle, NULL, event, r_event, userid, dc_host, NULL, NULL, NULL,
			tz,  replace_to, data, error, maxerrlen);

	if (SUCCEED == ret && NULL != p->macro)
	{
		const zbx_db_event	*c_event;

		c_event = ((NULL != r_event) ? r_event : event);

		if (EVENT_SOURCE_TRIGGERS == c_event->source)
		{
			if (NULL != userid && (0 == strcmp(p->macro, MVAR_USER_USERNAME) ||
					0 == strcmp(p->macro, MVAR_USER_NAME) ||
					0 == strcmp(p->macro, MVAR_USER_SURNAME) ||
					0 == strcmp(p->macro, MVAR_USER_FULLNAME) ||
					0 == strcmp(p->macro, MVAR_USER_ALIAS)))
			{
				zbx_user_names_t	**user_names = (zbx_user_names_t **)zbx_expr_rem(userid,
						sizeof(zbx_user_names_t *), NULL, user_names_clean_rem_wrap);

				resolve_user_macros(*userid, p->macro, user_names, replace_to);
			}
			else if (0 == strcmp(p->macro, MVAR_EVENT_UPDATE_STATUS))
			{
				*replace_to = zbx_strdup(*replace_to, "0");
			}
		}
		else if (0 == p->indexed && EVENT_SOURCE_SERVICE == c_event->source)
		{
			if (0 == strcmp(p->macro, MVAR_EVENT_UPDATE_STATUS))
			{
				*replace_to = zbx_strdup(*replace_to, "0");
			}
		}
	}

	return ret;
}

int	substitute_script_macros(char **data, char *error, int maxerrlen, int script_type,
		zbx_dc_um_handle_t * um_handle, const zbx_db_event *event, const zbx_db_event *r_event,
		zbx_uint64_t *userid, const zbx_dc_host_t *dc_host, const char *tz)
{
	int	ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() script_type:%d", __func__, script_type);

	switch (script_type)
	{
		case ZBX_SCRIPT_SCOPE_HOST:
			ret = zbx_substitute_macros(data, error, maxerrlen, &macro_host_script_resolv, um_handle,
					userid, dc_host);
			break;
		case ZBX_SCRIPT_SCOPE_EVENT:
			ret = zbx_substitute_macros(data, error, maxerrlen, &macro_normal_script_resolv,
					um_handle, event, r_event, userid, dc_host, tz);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
