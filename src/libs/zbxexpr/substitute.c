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

#include "zbxexpr.h"

#include "zbx_expression_constants.h"
#include "zbxstr.h"

/* macros that can be indexed */
static const char	*ex_macros[] =
{
	MVAR_INVENTORY_TYPE, MVAR_INVENTORY_TYPE_FULL,
	MVAR_INVENTORY_NAME, MVAR_INVENTORY_ALIAS, MVAR_INVENTORY_OS, MVAR_INVENTORY_OS_FULL, MVAR_INVENTORY_OS_SHORT,
	MVAR_INVENTORY_SERIALNO_A, MVAR_INVENTORY_SERIALNO_B, MVAR_INVENTORY_TAG,
	MVAR_INVENTORY_ASSET_TAG, MVAR_INVENTORY_MACADDRESS_A, MVAR_INVENTORY_MACADDRESS_B,
	MVAR_INVENTORY_HARDWARE, MVAR_INVENTORY_HARDWARE_FULL, MVAR_INVENTORY_SOFTWARE, MVAR_INVENTORY_SOFTWARE_FULL,
	MVAR_INVENTORY_SOFTWARE_APP_A, MVAR_INVENTORY_SOFTWARE_APP_B, MVAR_INVENTORY_SOFTWARE_APP_C,
	MVAR_INVENTORY_SOFTWARE_APP_D, MVAR_INVENTORY_SOFTWARE_APP_E, MVAR_INVENTORY_CONTACT, MVAR_INVENTORY_LOCATION,
	MVAR_INVENTORY_LOCATION_LAT, MVAR_INVENTORY_LOCATION_LON, MVAR_INVENTORY_NOTES, MVAR_INVENTORY_CHASSIS,
	MVAR_INVENTORY_MODEL, MVAR_INVENTORY_HW_ARCH, MVAR_INVENTORY_VENDOR, MVAR_INVENTORY_CONTRACT_NUMBER,
	MVAR_INVENTORY_INSTALLER_NAME, MVAR_INVENTORY_DEPLOYMENT_STATUS, MVAR_INVENTORY_URL_A, MVAR_INVENTORY_URL_B,
	MVAR_INVENTORY_URL_C, MVAR_INVENTORY_HOST_NETWORKS, MVAR_INVENTORY_HOST_NETMASK, MVAR_INVENTORY_HOST_ROUTER,
	MVAR_INVENTORY_OOB_IP, MVAR_INVENTORY_OOB_NETMASK, MVAR_INVENTORY_OOB_ROUTER, MVAR_INVENTORY_HW_DATE_PURCHASE,
	MVAR_INVENTORY_HW_DATE_INSTALL, MVAR_INVENTORY_HW_DATE_EXPIRY, MVAR_INVENTORY_HW_DATE_DECOMM,
	MVAR_INVENTORY_SITE_ADDRESS_A, MVAR_INVENTORY_SITE_ADDRESS_B, MVAR_INVENTORY_SITE_ADDRESS_C,
	MVAR_INVENTORY_SITE_CITY, MVAR_INVENTORY_SITE_STATE, MVAR_INVENTORY_SITE_COUNTRY, MVAR_INVENTORY_SITE_ZIP,
	MVAR_INVENTORY_SITE_RACK, MVAR_INVENTORY_SITE_NOTES, MVAR_INVENTORY_POC_PRIMARY_NAME,
	MVAR_INVENTORY_POC_PRIMARY_EMAIL, MVAR_INVENTORY_POC_PRIMARY_PHONE_A, MVAR_INVENTORY_POC_PRIMARY_PHONE_B,
	MVAR_INVENTORY_POC_PRIMARY_CELL, MVAR_INVENTORY_POC_PRIMARY_SCREEN, MVAR_INVENTORY_POC_PRIMARY_NOTES,
	MVAR_INVENTORY_POC_SECONDARY_NAME, MVAR_INVENTORY_POC_SECONDARY_EMAIL, MVAR_INVENTORY_POC_SECONDARY_PHONE_A,
	MVAR_INVENTORY_POC_SECONDARY_PHONE_B, MVAR_INVENTORY_POC_SECONDARY_CELL, MVAR_INVENTORY_POC_SECONDARY_SCREEN,
	MVAR_INVENTORY_POC_SECONDARY_NOTES,
	/* PROFILE.* is deprecated, use INVENTORY.* instead */
	MVAR_PROFILE_DEVICETYPE, MVAR_PROFILE_NAME, MVAR_PROFILE_OS, MVAR_PROFILE_SERIALNO,
	MVAR_PROFILE_TAG, MVAR_PROFILE_MACADDRESS, MVAR_PROFILE_HARDWARE, MVAR_PROFILE_SOFTWARE,
	MVAR_PROFILE_CONTACT, MVAR_PROFILE_LOCATION, MVAR_PROFILE_NOTES,
	MVAR_HOST_HOST, MVAR_HOSTNAME, MVAR_HOST_NAME, MVAR_HOST_DESCRIPTION, MVAR_PROXY_NAME, MVAR_PROXY_DESCRIPTION,
	MVAR_HOST_CONN, MVAR_HOST_DNS, MVAR_HOST_IP, MVAR_HOST_PORT, MVAR_IPADDRESS, MVAR_HOST_ID,
	MVAR_ITEM_ID, MVAR_ITEM_NAME, MVAR_ITEM_NAME_ORIG, MVAR_ITEM_DESCRIPTION, MVAR_ITEM_DESCRIPTION_ORIG,
	MVAR_ITEM_KEY, MVAR_ITEM_KEY_ORIG, MVAR_TRIGGER_KEY,
	MVAR_ITEM_LASTVALUE,
	MVAR_ITEM_STATE,
	MVAR_ITEM_VALUE, MVAR_ITEM_VALUETYPE,
	MVAR_ITEM_LOG_DATE, MVAR_ITEM_LOG_TIME, MVAR_ITEM_LOG_TIMESTAMP, MVAR_ITEM_LOG_AGE, MVAR_ITEM_LOG_SOURCE,
	MVAR_ITEM_LOG_SEVERITY, MVAR_ITEM_LOG_NSEVERITY, MVAR_ITEM_LOG_EVENTID,
	MVAR_FUNCTION_VALUE, MVAR_FUNCTION_RECOVERY_VALUE,
	NULL
};

const char	**zbx_get_indexable_macros(void)
{
	return ex_macros;
}

static int	substitute_macros_args(zbx_token_search_t search, char **data, char *error, size_t maxerrlen,
		zbx_macro_resolv_func_t resolver, va_list args)
{
	zbx_macro_resolv_data_t	p = {0};
	int			found, res = SUCCEED;
	char			c, *m_ptr, *replace_to = NULL;
	size_t			data_alloc, data_len;

	if (NULL == data || NULL == *data || '\0' == **data)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:EMPTY", __func__);
		return res;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:'%s'", __func__, *data);

	p.token_search = search;

	if (SUCCEED != zbx_token_find(*data, p.pos, &p.token, p.token_search))
		goto out;

	data_alloc = data_len = strlen(*data) + 1;

	for (found = SUCCEED; SUCCEED == res && SUCCEED == found;
			found = zbx_token_find(*data, p.pos, &p.token, p.token_search))
	{
		int	ret = SUCCEED;

		p.inner_token = p.token;
		p.indexed = p.raw_value = p.resolved = 0;
		p.index = 1;

		p.pos = p.token.loc.l;

		switch (p.token.type)
		{
			case ZBX_TOKEN_OBJECTID:
			case ZBX_TOKEN_LLD_MACRO:
			case ZBX_TOKEN_LLD_FUNC_MACRO:
				/* neither lld nor {123123} macros are processed by this function, skip them */
				p.pos = p.token.loc.r + 1;
				continue;
			case ZBX_TOKEN_MACRO:
				if (0 != zbx_is_indexed_macro(*data, &p.token) &&
						NULL != (p.macro = zbx_macro_in_list(*data, p.token.loc,
						zbx_get_indexable_macros(), &p.index)))
				{
					p.indexed = 1;
				}
				else
				{
					p.macro = *data + p.token.loc.l;
					c = (*data)[p.token.loc.r + 1];
					(*data)[p.token.loc.r + 1] = '\0';
				}
				break;
			case ZBX_TOKEN_USER_FUNC_MACRO:
			case ZBX_TOKEN_FUNC_MACRO:
				p.raw_value = 1;
				p.indexed = zbx_is_indexed_macro(*data, &p.token);
				if (NULL == (m_ptr = zbx_get_macro_from_func(*data, &p.token.data.func_macro, &p.index))
						|| SUCCEED != zbx_token_find(*data, p.token.data.func_macro.macro.l,
						&p.inner_token, p.token_search))
				{
					/* Ignore functions with macros not supporting them, but do not skip the */
					/* whole token, nested macro should be resolved in this case. */
					p.pos++;
					ret = FAIL;
				}
				p.macro = m_ptr;
				break;
			case ZBX_TOKEN_USER_MACRO:
				/* To avoid *data modification user macro resolver should be replaced with a function */
				/* that takes initial *data string and token.data.user_macro instead of p.macro as    */
				/* params.                                                                            */
				p.macro = *data + p.token.loc.l;
				c = (*data)[p.token.loc.r + 1];
				(*data)[p.token.loc.r + 1] = '\0';
				break;
			case ZBX_TOKEN_REFERENCE:
			case ZBX_TOKEN_EXPRESSION_MACRO:
				/* These macros (and probably all other in the future) must be resolved using only    */
				/* information stored in token.data union. For now, force crash if they rely on       */
				/* p.macro.                                                                           */
				p.macro = NULL;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				res = FAIL;
				continue;
		}

		if (SUCCEED == ret)
		{
			va_list	pargs;

			va_copy(pargs, args); /* copy current argument position */

			ret = resolver(&p, pargs, &replace_to, data, error, maxerrlen);

			va_end(pargs);

			if (SUCCEED < ret) continue; /* resolver did everything */

			if ((ZBX_TOKEN_FUNC_MACRO == p.token.type || ZBX_TOKEN_USER_FUNC_MACRO == p.token.type) &&
					NULL != replace_to)
			{
				if (SUCCEED != (ret = zbx_calculate_macro_function(*data, &p.token.data.func_macro,
						&replace_to)))
				{
					zbx_free(replace_to);
				}
			}
		}

		if (FAIL == ret)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot resolve macro '%.*s'",
					(int)(p.token.loc.r - p.token.loc.l + 1), *data + p.token.loc.l);

			if (ZBX_TOKEN_MACRO == p.token.type && SUCCEED == zbx_is_strict_macro(p.macro))
			{
				if (NULL != error)
				{
					/* return error if strict macro resolving failed */
					zbx_snprintf(error, maxerrlen, "Invalid macro '%.*s' value",
							(int)(p.token.loc.r - p.token.loc.l + 1),
							*data + p.token.loc.l);

					res = FAIL;
				}
			}

			replace_to = zbx_strdup(replace_to, STR_UNKNOWN_VARIABLE);
		}

		if (ZBX_TOKEN_USER_MACRO == p.token.type || (ZBX_TOKEN_MACRO == p.token.type && 0 == p.indexed))
			(*data)[p.token.loc.r + 1] = c;

		if (NULL != replace_to)
		{
			p.pos = p.token.loc.r;

			p.pos += zbx_replace_mem_dyn(data, &data_alloc, &data_len, p.token.loc.l,
					p.token.loc.r - p.token.loc.l + 1, replace_to, strlen(replace_to));
			zbx_free(replace_to);
		}

		if (ZBX_TOKEN_FUNC_MACRO == p.token.type || ZBX_TOKEN_USER_FUNC_MACRO == p.token.type)
			zbx_free(m_ptr);

		p.pos++;
	}

out:
	zabbix_log(LOG_LEVEL_DEBUG, "End %s()", __func__);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitutes macros                                                *
 *                                                                            *
 * Parameters: data      - [IN/OUT] pointer to data where macros should be    *
 *                                  resolved                                  *
 *             error     - [OUT] pre-allocated buffer for error message       *
 *             maxerrlen - [IN] size of pre-allocated error message buffer    *
 *             resolver  - [IN] callback to macro resolver function           *
 *             ...       - [IN/OUT] variadic arguments passed to macro        *
 *                                  resolver function to identify data        *
 *                                                                            *
 * Return value: SUCCEED  - all recognised macros were resolved               *
 *               FAIL     - macro resolving failed                            *
 *                                                                            *
 * Note: When macro is recognised but has no value then it will be resolved   *
 *       as *UNKNOWN*.                                                        *
 *                                                                            *
 * Macro resolver parameters:                                                 *
 *             p          - [IN] macro resolver data structure                *
 *             args       - [IN] list of variadic parameters passed from      *
 *                               zbx_substitute_macros_* function             *
 *             replace_to - [OUT] pointer to value to replace macro with      *
 *                                Note: value will be freed.                  *
 *             data       - [IN/OUT] pointer to input data string             *
 *             error      - [OUT] pointer to pre-allocated error message      *
 *                                buffer                                      *
 *             maxerrlen  - [IN] size of error message buffer                 *
 *                                                                            *
 * Macro resolver return value:                                               *
 *               SUCCEED         - when recognised macro were found and it's  *
 *                                 value were assigned to replace_to pointer  *
 *               SUCCEED_PARTIAL - when resolver itself did everything, no    *
 *                                 further checks are required *special case* *
 *               FAIL            - when data gathering failed: value will be  *
 *                                 get to *UNKNOWN*                           *
 *                                                                            *
 * Note: When macro is not recognised then do not return FAIL, just keep      *
 *       replace_to unassigned so other macro resolvers may resolve it's      *
 *       macros.                                                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_substitute_macros(char **data, char *error, size_t maxerrlen, zbx_macro_resolv_func_t resolver, ...)
{
	int	ret;
	va_list	args;

	va_start(args, resolver);

	ret = substitute_macros_args(ZBX_TOKEN_SEARCH_BASIC, data, error, maxerrlen, resolver, args);

	va_end(args);

	return ret;
}
