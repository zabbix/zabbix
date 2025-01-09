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

#include "zbxagentget.h"

#include "zbxversion.h"
#include "zbxstr.h"
#include "zbxjson.h"

#include <stddef.h>

int	zbx_get_agent_protocol_version_int(const char *version_str)
{
	int	version_int;

	if (0 != strcmp(ZBX_VERSION_UNDEFINED_STR, version_str) &&
			FAIL != (version_int = zbx_get_component_version(version_str)))
	{
		return version_int;
	}

	return 0;
}

void	zbx_agent_prepare_request(struct zbx_json *j, const char *key, int timeout)
{
	zbx_json_addstring(j, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_GET_PASSIVE_CHECKS, ZBX_JSON_TYPE_STRING);
	zbx_json_addarray(j, ZBX_PROTO_TAG_DATA);

	zbx_json_addobject(j, NULL);
	zbx_json_addstring(j, ZBX_PROTO_TAG_KEY, key, ZBX_JSON_TYPE_STRING);
	zbx_json_addint64(j, ZBX_PROTO_TAG_TIMEOUT, (zbx_int64_t)timeout);
	zbx_json_close(j);
}

int	zbx_agent_handle_response(char *buffer, size_t read_bytes, ssize_t received_len, const char *addr,
		AGENT_RESULT *result, int *version)
{
	zabbix_log(LOG_LEVEL_DEBUG, "get value from agent result: '%s'", buffer);

	if (0 == received_len)
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Received empty response from Zabbix Agent at [%s]."
				" Assuming that agent dropped connection because of access permissions.",
				addr));
		return NETWORK_ERROR;
	}

	if (ZBX_COMPONENT_VERSION(7, 0, 0) <= *version)
	{
		struct zbx_json_parse	jp, jp_data, jp_row;
		const char		*p = NULL;
		size_t			value_alloc = 0;
		char			*value = NULL, tmp[MAX_STRING_LEN];
		zbx_json_type_t		value_type;

		if (FAIL == zbx_json_open(buffer, &jp))
		{
			*version = 0;
			return FAIL;
		}

		if (FAIL == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_VERSION, tmp, sizeof(tmp), NULL))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "cannot find the \"%s\" object in the received JSON"
					" object.", ZBX_PROTO_TAG_VERSION));
			return NETWORK_ERROR;
		}

		*version = zbx_get_agent_protocol_version_int(tmp);

		if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_ERROR, tmp, sizeof(tmp), NULL))
		{
			zbx_replace_invalid_utf8(tmp);
			SET_MSG_RESULT(result, zbx_strdup(NULL, tmp));
			return NETWORK_ERROR;
		}

		if (FAIL == zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_DATA, &jp_data))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "cannot find the \"%s\" object in the received JSON"
					" object.", ZBX_PROTO_TAG_DATA));
			return NETWORK_ERROR;
		}

		if (NULL == (p = zbx_json_next(&jp_data, p)))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "received empty data response"));
			return NETWORK_ERROR;
		}

		if (FAIL == zbx_json_brackets_open(p, &jp_row))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "cannot parse response: %s", zbx_json_strerror()));
			return NETWORK_ERROR;
		}

		if (SUCCEED == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_ERROR, tmp, sizeof(tmp), NULL))
		{
			zbx_replace_invalid_utf8(tmp);
			SET_MSG_RESULT(result, zbx_strdup(NULL, tmp));
			return NOTSUPPORTED;
		}

		if (FAIL == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_VALUE, &value, &value_alloc,
				&value_type))
		{
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "cannot parse response: %s",
					zbx_json_strerror()));
			return NETWORK_ERROR;
		}

		if (ZBX_JSON_TYPE_NULL != value_type)
		{
			zbx_replace_invalid_utf8(value);
			SET_TEXT_RESULT(result, zbx_strdup(NULL, value));
		}
		else
			zbx_free_agent_result(result);

		zbx_free(value);

		return SUCCEED;
	}

	if (0 == strcmp(buffer, ZBX_NOTSUPPORTED))
	{
		/* 'ZBX_NOTSUPPORTED\0<error message>' */
		if (sizeof(ZBX_NOTSUPPORTED) < read_bytes)
			SET_MSG_RESULT(result, zbx_dsprintf(NULL, "%s", buffer + sizeof(ZBX_NOTSUPPORTED)));
		else
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Not supported by Zabbix Agent"));

		return NOTSUPPORTED;
	}
	else if (0 == strcmp(buffer, ZBX_ERROR))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Zabbix Agent non-critical error"));
		return AGENT_ERROR;
	}
	else
	{
		zbx_replace_invalid_utf8(buffer);
		SET_TEXT_RESULT(result, zbx_strdup(NULL, buffer));
		return SUCCEED;
	}
}
