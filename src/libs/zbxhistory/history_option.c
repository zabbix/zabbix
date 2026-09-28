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

#include "history_option.h"
#include "history.h"
#include "zbxcommon.h"
#include "zbxstr.h"
#include "zbxhistory.h"

static const char	*history_options_value_types[ITEM_VALUE_TYPE_COUNT] = {
		"dbl", "str", "log", "uint", "text", "bin", "json"};

/******************************************************************************
 *                                                                            *
 * Purpose: get description of history value type                             *
 *                                                                            *
 * Parameters: value_type - [IN] history value type                           *
 *                                                                            *
 * Return value: description of the history value type                        *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_history_option_value_type_str(unsigned char value_type)
{
	if (value_type >= ARRSIZE(history_options_value_types))
		return "unknown";

	return history_options_value_types[value_type];
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert history value type string to its numeric representation   *
 *                                                                            *
 * Parameters: value_type_str - [IN] history value type string                *
 *                                                                            *
 * Return value: value type or FAIL if unknown                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_history_option_value_type_from_str(const char *value_type_str)
{
	for (int i = 0; i < (int)ARRSIZE(history_options_value_types); i++)
	{
		if (0 == strcmp(history_options_value_types[i], value_type_str))
			return i;
	}

	THIS_SHOULD_NEVER_HAPPEN_MSG("unknown history value type \"%s\"", value_type_str);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse history provider configuration string                       *
 *                                                                            *
 * Parameters: conf    - [IN] configuration string                            *
 *             name    - [OUT] provider name                                  *
 *             options - [OUT] parsed provider options                        *
 *             error   - [OUT] error message                                  *
 *                                                                            *
 * Return value: SUCCEED - successfully parsed                                *
 *               FAIL    - an error occurred                                  *
 *                                                                            *
 ******************************************************************************/
int	history_provider_parse_options(const char *conf, char **name, zbx_vector_config_option_t *options,
		char **error)
{
	const char	*ptr = conf;
	ssize_t		len;

	len = zbx_config_option_parse_param(ptr);
	ptr += len;
	while (' ' == *ptr)
		ptr++;

	/* there must be at least one mandatory option */
	if (0 == len || ';' != *ptr)
	{
		*error = zbx_dsprintf(NULL, "invalid HistoryProvider value \"%s\"", conf);
		return FAIL;
	}

	while (' ' == *(++ptr))
		;

	if (SUCCEED != zbx_config_option_parse_options(ptr, options, error))
		return FAIL;

	if (0 != strncmp(conf, HISTORY_PROVIDER_SQL, ZBX_CONST_STRLEN(HISTORY_PROVIDER_SQL)))
	{
		/* value_types option is mandatory for non default providers */
		if (NULL == zbx_config_option_value(options->values, options->values_num,
				HISTORY_PROVIDER_OPTION_VALUE_TYPES))
		{
			for (int i = 0; i < options->values_num; i++)
			{
				zbx_free(options->values[i].name);
				zbx_free(options->values[i].value);
			}
			zbx_vector_config_option_clear(options);

			*error = zbx_dsprintf(NULL, "cannot find mandatory option \"%s\" in history provider"
					" configuration \"%s\"", HISTORY_PROVIDER_OPTION_VALUE_TYPES, conf);

			return FAIL;
		}
	}

	/* unquoted string extraction never fails */
	(void)zbx_str_extract(conf, len, name);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert value types option string to bitmask                      *
 *                                                                            *
 * Parameters: options     - [IN] array of history options                    *
 *             options_num - [IN] number of options in the array              *
 *                                                                            *
 * Return value: bitmask representing supported value types                   *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	history_options_type_mask(const zbx_config_option_t *options, int options_num)
{
	zbx_uint64_t	mask = 0;
	const char	*types;

	if (NULL == (types = zbx_config_option_value(options, options_num, HISTORY_PROVIDER_OPTION_VALUE_TYPES)))
		return 0;

	for (int i = 0; i < ITEM_VALUE_TYPE_COUNT; i++)
	{
		if (NULL == history_options_value_types[i])
			continue;

		if (SUCCEED == zbx_str_in_list(types, history_options_value_types[i], ','))
			mask |= (__UINT64_C(1) << i);
	}

	return mask;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validate value types specified in history provider options        *
 *                                                                            *
 * Parameters: options     - [IN] array of history options                    *
 *             options_num - [IN] number of options in the array              *
 *             error       - [OUT] error message                              *
 *                                                                            *
 * Return value: SUCCEED - all value types are valid                          *
 *               FAIL    - invalid value type found                           *
 *                                                                            *
 ******************************************************************************/
static int	history_options_validate_value_type(const zbx_config_option_t *options, int options_num, char **error)
{
	const char	*value_types;

	if (NULL == (value_types = zbx_config_option_value(options, options_num, HISTORY_PROVIDER_OPTION_VALUE_TYPES)))
		return FAIL;

	const char	*start = value_types, *end;
	zbx_uint64_t	mask = 0;

	for (;;)
	{
		size_t	len, i;

		end = strchr(start, ',');

		len = (NULL == end ? strlen(start) : (size_t)(end - start));

		for (i = 0; i < ARRSIZE(history_options_value_types); i++)
		{
			const char	*value_type = history_options_value_types[i];

			if (strlen(value_type) == len && 0 == memcmp(value_type, start, len))
				break;
		}

		if (i == ARRSIZE(history_options_value_types))
		{
			*error = zbx_dsprintf(NULL, "unknown value type \"%.*s\"",  (int)len, start);
			return FAIL;
		}

		if (0 != (mask & (__UINT64_C(1) << i)))
		{
			*error = zbx_dsprintf(NULL, "value type \"%.*s\" has already been set",  (int)len, start);
			return FAIL;
		}

		mask |= (__UINT64_C(1) << i);

		if (NULL == end)
			break;

		start = end + 1;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validate precache setting specified in history provider options   *
 *                                                                            *
 * Parameters: options     - [IN] array of history options                    *
 *             options_num - [IN] number of options in the array              *
 *             error       - [OUT] error message                              *
 *                                                                            *
 * Return value: SUCCEED - precache is not specified or correct               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	history_options_validate_precache(const zbx_config_option_t *options, int options_num, char **error)
{
	const char	*precache, *valid_values = "0,1,on,off";

	if (NULL == (precache = zbx_config_option_value(options, options_num, HISTORY_PROVIDER_OPTION_PRECACHE)))
		return SUCCEED;

	if (SUCCEED == zbx_str_in_list(valid_values, precache, ','))
		return SUCCEED;

	*error = zbx_dsprintf(NULL, "invalid precache option value \"%s\"", precache);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validate value types,precache in history provider options         *
 *                                                                            *
 * Parameters: options     - [IN] array of history options                    *
 *             options_num - [IN] number of options in the array              *
 *             error       - [OUT] error message                              *
 *                                                                            *
 * Return value: SUCCEED - all value types are valid                          *
 *               FAIL    - invalid value type found                           *
 *                                                                            *
 ******************************************************************************/
int	history_options_validate_common_settings(const zbx_config_option_t *options, int options_num, char **error)
{
	if (SUCCEED != history_options_validate_value_type(options, options_num, error))
		return FAIL;

	if (SUCCEED != history_options_validate_precache(options, options_num, error))
		return FAIL;

	return SUCCEED;
}
/******************************************************************************
 *                                                                            *
 * Purpose: determine if precaching is required based on provider options     *
 *                                                                            *
 * Parameters: options     - [IN] array of history options                    *
 *             options_num - [IN] number of options in the array              *
 *                                                                            *
 * Return value: ZBX_HISTORY_TRAIT_REQUIRES_PRECACHING if precaching is       *
 *               enabled, 0 otherwise                                         *
 *                                                                            *
 * Comments: Precaching is enabled by default or when explicitly set to "on"  *
 *           or "1".                                                          *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	history_options_precache(const zbx_config_option_t *options, int options_num)
{
	const char	*precache;

	if (NULL == (precache = zbx_config_option_value(options, options_num, HISTORY_PROVIDER_OPTION_PRECACHE)) ||
			0 == strcmp(precache, "on") || 0 == strcmp(precache, "1"))
	{
		return ZBX_HISTORY_TRAIT_REQUIRES_PRECACHING;
	}

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create a history types option from a bitmask                      *
 *                                                                            *
 * Parameters: mask - [IN] bitmask representing value types                   *
 *                                                                            *
 * Return value: The created option with comma-separated value types.         *
 *                                                                            *
 ******************************************************************************/
zbx_config_option_t	history_option_types(zbx_uint64_t mask)
{
	zbx_config_option_t	option;

	char	*types = NULL;
	size_t	types_alloc = 0, types_offset = 0;

	for (int i = 0; i < ITEM_VALUE_TYPE_COUNT; i++)
	{
		if (NULL == history_options_value_types[i] || SUCCEED != ZBX_HISTORY_CHECK_TYPE_FLAGS(mask, i))
			continue;

		if (0 != types_offset)
			zbx_chrcpy_alloc(&types, &types_alloc, &types_offset, ',');

		zbx_strcpy_alloc(&types, &types_alloc, &types_offset, history_options_value_types[i]);
	}

	option.name = zbx_strdup(NULL, HISTORY_PROVIDER_OPTION_VALUE_TYPES);
	option.value = types;

	return option;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add SSL configuration parameters to history provider options if   *
 *          they are not already present                                      *
 *                                                                            *
 * Parameters:                                                                *
 *     options                   - [IN/OUT] vector of history options         *
 *     config_source_ip          - [IN] source IP address from configuration  *
 *                                      (optional)                            *
 *     config_log_slow_queries   - [IN] slow query logging limit              *
 *     config_ssl_ca_location    - [IN] SSL CA certificate location from      *
 *                                      configuration (optional)              *
 *     config_ssl_cert_location  - [IN] SSL certificate location from         *
 *                                      configuration (optional)              *
 *     config_ssl_key_location   - [IN] SSL key location from configuration   *
 *                                      (optional)                            *
 *     error                     - [OUT] error message                        *
 *                                                                            *
 * Return value: SUCCEED - common parameters were added successfully          *
 *               FAIL    - parameter conflict                                 *
 *                                                                            *
 ******************************************************************************/
int	history_options_add_common_params(zbx_vector_config_option_t *options, const char *config_source_ip,
		int config_log_slow_queries, const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, char **error)
{
	if (NULL != zbx_config_option_value(options->values, options->values_num,
			HISTORY_PROVIDER_OPTION_LOG_SLOW_QUERIES))
	{
		*error = zbx_strdup(NULL, "invalid configuration: cannot override LogSlowQueries parameter");
		return FAIL;
	}

	if (0 != config_log_slow_queries)
	{
		zbx_vector_config_option_append(options, zbx_config_option_int(HISTORY_PROVIDER_OPTION_LOG_SLOW_QUERIES,
				config_log_slow_queries));
	}

	if (NULL != zbx_config_option_value(options->values, options->values_num, HISTORY_PROVIDER_OPTION_SOURCE_IP))
	{
		*error = zbx_strdup(NULL, "invalid configuration: cannot override SourceIP parameter");
		return FAIL;
	}

	if (NULL != config_source_ip)
	{
		zbx_vector_config_option_append(options, zbx_config_option_str(HISTORY_PROVIDER_OPTION_SOURCE_IP,
				config_source_ip));
	}

	if (NULL != zbx_config_option_value(options->values, options->values_num,
			HISTORY_PROVIDER_OPTION_SSL_CA_LOCATION))
	{
		*error = zbx_strdup(NULL, "invalid configuration: cannot override SSLCALocation parameter");
		return FAIL;
	}

	if (NULL != config_ssl_ca_location)
	{
		zbx_vector_config_option_append(options, zbx_config_option_str(HISTORY_PROVIDER_OPTION_SSL_CA_LOCATION,
				config_ssl_ca_location));
	}

	if (NULL != zbx_config_option_value(options->values, options->values_num,
			HISTORY_PROVIDER_OPTION_SSL_CERT_LOCATION))
	{
		*error = zbx_strdup(NULL, "invalid configuration: cannot override SSLCertLocation parameter");
		return FAIL;
	}

	if (NULL != config_ssl_cert_location)
	{
		zbx_vector_config_option_append(options,
				zbx_config_option_str(HISTORY_PROVIDER_OPTION_SSL_CERT_LOCATION,
				config_ssl_cert_location));
	}

	if (NULL != zbx_config_option_value(options->values, options->values_num,
			HISTORY_PROVIDER_OPTION_SSL_KEY_LOCATION))
	{
		*error = zbx_strdup(NULL, "invalid configuration: cannot override SSLKeyLocation parameter");
		return FAIL;
	}

	if (NULL != config_ssl_key_location)
	{
		zbx_vector_config_option_append(options, zbx_config_option_str(HISTORY_PROVIDER_OPTION_SSL_KEY_LOCATION,
				config_ssl_key_location));
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: log history provider options for debugging purposes               *
 *                                                                            *
 * Parameters: options     - [IN] array of history options                    *
 *             options_num - [IN] number of options in the array              *
 *                                                                            *
 * Comments: Sensitive option values are masked in the log output.            *
 *                                                                            *
 ******************************************************************************/
void	history_log_options(zbx_config_option_t *options, int options_num)
{
	const char	*unmasked =
			HISTORY_PROVIDER_OPTION_LOG_SLOW_QUERIES ","
			HISTORY_PROVIDER_OPTION_VALUE_TYPES ","
			HISTORY_PROVIDER_OPTION_SOURCE_IP ","
			HISTORY_PROVIDER_OPTION_PRECACHE ","
			HISTORY_PROVIDER_OPTION_NAME ","
			HISTORY_PROVIDER_OPTION_DATE_INDEX ","
	;


	if (SUCCEED != ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
		return;

	zabbix_log(LOG_LEVEL_DEBUG, "options:");
	for (int i = 0; i < options_num; i++)
	{
		const char	*value;

		value = options[i].value;

		if (SUCCEED != zbx_str_in_list(unmasked, options[i].name, ','))
			value = ZBX_STRMASK(options[i].value);

		zabbix_log(LOG_LEVEL_DEBUG, "  %s: %s", options[i].name, value);
	}
}
