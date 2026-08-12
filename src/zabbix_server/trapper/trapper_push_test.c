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

#include "trapper_push_test.h"

#include "zbxalerter.h"
#include "zbxipcservice.h"
#include "zbxcrypto.h"
#include "zbxstr.h"
#include "zbxjson.h"
#include "zbxalgo.h"
#include "zbx_bridge_adapter_constants.h"

static char	*trapper_build_push_notify_json(const char *device_id, const char *push_token,
		const char *mobile_encryption_key, const char *subject, const char *message)
{
	struct zbx_json	json;
	char		*message_uuid7, *request_uuid7, *params, clock_str[ZBX_BRIDGE_TIME_LEN];

	zbx_json_init(&json, 1024);
	zbx_json_addstring(&json, "jsonrpc", "2.0", ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, "method", ZBX_PROTO_VALUE_DEVICE_NOTIFY, ZBX_JSON_TYPE_STRING);

	zbx_json_addobject(&json, "params");

	zbx_json_addobject(&json, "to");
	zbx_json_addstring(&json, "push_token", push_token, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, "device_id", device_id, ZBX_JSON_TYPE_STRING);
	zbx_json_close(&json);

	zbx_json_addobject(&json, "payload");
	zbx_json_addstring(&json, "specversion", "1", ZBX_JSON_TYPE_STRING);

	message_uuid7 = zbx_gen_uuid7_hyphenated();
	zbx_json_addstring(&json, "id", message_uuid7, ZBX_JSON_TYPE_STRING);
	zbx_snprintf(clock_str, sizeof(clock_str), "%d", (int)time(NULL));
	zbx_json_addstring(&json, "time", clock_str, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, "type", ZBX_PROTO_VALUE_MEDIA_TEST, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, "source", "zabbix/server", ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, "subject", "mediatype/test", ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, "dataschema", "urn:zabbix:server:test:1", ZBX_JSON_TYPE_STRING);

	zbx_json_addobject(&json, "data");
	zbx_json_addstring(&json, "title", subject, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&json, "body", message, ZBX_JSON_TYPE_STRING);
	zbx_json_close(&json);

	zbx_json_close(&json);

	zbx_json_addint64(&json, "priority", 0);
	if (NULL != mobile_encryption_key)
		zbx_json_addraw(&json, "mobile_encryption_key", mobile_encryption_key);
	else
		/* Bridge-adapter treats empty {} as an invalid key.             */
		/* null indicates that MEK is absent for non-encrypted delivery. */
		zbx_json_addstring(&json, "mobile_encryption_key", NULL, ZBX_JSON_TYPE_NULL);

	zbx_json_close(&json);

	request_uuid7 = zbx_gen_uuid7_hyphenated();
	zbx_json_addstring(&json, "id", request_uuid7, ZBX_JSON_TYPE_STRING);
	zbx_json_close(&json);

	params = zbx_strdup(NULL, json.buffer);

	zbx_json_free(&json);
	zbx_free(request_uuid7);
	zbx_free(message_uuid7);

	return params;
}

typedef struct
{
	char	*uuid;
	char	*name;
	char	*push_token;
	char	*mobile_encryption_key;
}
zbx_push_device_t;

ZBX_PTR_VECTOR_DECL(push_device_ptr, zbx_push_device_t *)
ZBX_PTR_VECTOR_IMPL(push_device_ptr, zbx_push_device_t *)

static void	trapper_push_device_free(zbx_push_device_t *device)
{
	zbx_free(device->uuid);
	zbx_free(device->name);
	zbx_free(device->push_token);
	zbx_free(device->mobile_encryption_key);
	zbx_free(device);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sends test push notification(s) for push media type test          *
 *                                                                            *
 * Parameters: sendto              - [IN] comma/newline separated list of     *
 *                                        target device UUIDs                 *
 *             subject             - [IN]                                     *
 *             message             - [IN]                                     *
 *             mediatypeid         - [IN]                                     *
 *             type                - [IN] media type (MEDIA_TYPE_PUSH)        *
 *             row                 - [IN] media type configuration db row     *
 *             smtp_port           - [IN]                                     *
 *             smtp_security       - [IN]                                     *
 *             smtp_verify_peer    - [IN]                                     *
 *             smtp_verify_host    - [IN]                                     *
 *             smtp_authentication - [IN]                                     *
 *             message_format      - [IN] unused for push media type,         *
 *                                        passed through to alerter as-is     *
 *             error               - [OUT] error message(s), one per failed   *
 *                                         device when testing multiple       *
 *                                         devices                            *
 *             debug               - [OUT] debug info of the last processed   *
 *                                         device, optional                   *
 *                                                                            *
 * Return value: SUCCEED - all requested devices were notified successfully   *
 *               FAIL    - no device UUIDs were specified, or delivery        *
 *                         failed for at least one device                     *
 *                                                                            *
 ******************************************************************************/
int	trapper_process_push_test(const char *sendto, const char *subject, const char *message,
		zbx_uint64_t mediatypeid, unsigned char type, const zbx_db_row_t row, unsigned short smtp_port,
		unsigned char smtp_security, unsigned char smtp_verify_peer, unsigned char smtp_verify_host,
		unsigned char smtp_authentication, unsigned char message_format, char **error, char **debug)
{
	zbx_vector_str_t		uuids;
	zbx_vector_push_device_ptr_t	found_devices;
	zbx_db_result_t			result;
	zbx_db_row_t			db_row;
	char				*sendto_copy, *token, *saveptr, *sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0, error_alloc = 0, error_offset = 0;
	int				i, failed = 0, ret = FAIL;

	zbx_vector_str_create(&uuids);

	sendto_copy = zbx_strdup(NULL, sendto);

	for (token = strtok_r(sendto_copy, ",\n", &saveptr); NULL != token; token = strtok_r(NULL, ",\n", &saveptr))
	{
		zbx_lrtrim(token, ZBX_WHITESPACE);

		if ('\0' != *token)
			zbx_vector_str_append(&uuids, zbx_strdup(NULL, token));
	}

	zbx_free(sendto_copy);

	if (0 == uuids.values_num)
	{
		*error = zbx_strdup(NULL, "No device UUIDs specified for push media type test.");
		zbx_vector_str_destroy(&uuids);
		return FAIL;
	}

	/* one batched lookup for every requested device, instead of one query per device */
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select d.uuid,d.name,d.push_token,dk.key_"
			" from device d"
			" left join device_key dk on dk.deviceid=d.deviceid and dk.active=%d and dk.scope=%d"
			" where d.status=%d and",
			ZBX_DEVICE_KEY_ACTIVE, ZBX_BRIDGE_ADAPTER_DEVICE_KEY_SCOPE_MOBILE_ENCRYPTION,
			ZBX_DEVICE_STATUS_ACTIVATED);
	zbx_db_add_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "d.uuid",
			(const char * const *)uuids.values, uuids.values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by d.uuid,dk.device_keyid desc");

	result = zbx_db_select("%s", sql);
	zbx_free(sql);

	zbx_vector_push_device_ptr_create(&found_devices);
	zbx_vector_push_device_ptr_reserve(&found_devices, (size_t)uuids.values_num);

	/* rows are ordered by uuid, then by device_keyid desc -> first row per uuid is the active key */
	while (NULL != (db_row = zbx_db_fetch(result)))
	{
		zbx_push_device_t	*device;

		if (0 < found_devices.values_num &&
				0 == strcmp(found_devices.values[found_devices.values_num - 1]->uuid, db_row[0]))
		{
			continue;
		}

		device = (zbx_push_device_t *)zbx_malloc(NULL, sizeof(zbx_push_device_t));
		device->uuid = zbx_strdup(NULL, db_row[0]);
		device->name = zbx_strdup(NULL, db_row[1]);
		device->push_token = zbx_strdup(NULL, db_row[2]);
		device->mobile_encryption_key = (NULL != db_row[3] ? zbx_strdup(NULL, db_row[3]) : NULL);
		zbx_vector_push_device_ptr_append(&found_devices, device);
	}
	zbx_db_free_result(result);

	for (i = 0; i < uuids.values_num; i++)
	{
		const char	*uuid = uuids.values[i], *name = NULL;
		char		*params = NULL, *item_error = NULL, *item_value = NULL, *item_debug = NULL,
				*recipient = NULL;
		unsigned char	*data = NULL, *response = NULL;
		zbx_uint32_t	size;
		int		item_errcode = FAIL, j;

		for (j = 0; j < found_devices.values_num; j++)
		{
			zbx_push_device_t	*device = found_devices.values[j];

			if (0 == strcmp(device->uuid, uuid))
			{
				name = device->name;
				params = trapper_build_push_notify_json(device->uuid, device->push_token,
						device->mobile_encryption_key, subject, message);
				break;
			}
		}

		if (NULL != params)
		{
			size = zbx_alerter_serialize_alert_send(&data, mediatypeid, type, row[19], row[1], row[2],
					row[3], row[4], row[5], row[6], row[7], smtp_port, smtp_security,
					smtp_verify_peer, smtp_verify_host, smtp_authentication, atoi(row[13]),
					atoi(row[14]), row[15], message_format, row[17], row[18],
					ZBX_ALERT_MESSAGE_TEST, uuid, subject, message, params);

			if (SUCCEED == zbx_ipc_async_exchange(ZBX_IPC_SERVICE_ALERTER, zbx_alerter_send_alert_code(),
					SEC_PER_MIN, data, size, &response, &item_error))
			{
				zbx_alerter_deserialize_result_ext(response, &recipient, &item_value, &item_errcode,
						&item_error, &item_debug);
				zbx_free(response);
			}

			zbx_free(data);
		}
		else
			item_error = zbx_strdup(NULL, ZBX_PUSH_TEST_ERR_DEVICE_NOT_FOUND);

		if (SUCCEED != item_errcode)
		{
			failed++;

			if (0 != error_offset)
				zbx_strcpy_alloc(error, &error_alloc, &error_offset, "\n");

			if (1 < uuids.values_num)
			{
				if (NULL != name)
				{
					zbx_snprintf_alloc(error, &error_alloc, &error_offset, "%s (%s): %s", name,
							uuid, ZBX_NULL2EMPTY_STR(item_error));
				}
				else
				{
					zbx_snprintf_alloc(error, &error_alloc, &error_offset, "%s: %s", uuid,
							ZBX_NULL2EMPTY_STR(item_error));
				}
			}
			else
				zbx_strcpy_alloc(error, &error_alloc, &error_offset, ZBX_NULL2EMPTY_STR(item_error));
		}

		if (NULL != item_debug)
		{
			zbx_free(*debug);
			*debug = item_debug;
		}

		zbx_free(recipient);
		zbx_free(params);
		zbx_free(item_error);
		zbx_free(item_value);
	}

	if (0 == failed)
		ret = SUCCEED;

	zbx_vector_push_device_ptr_clear_ext(&found_devices, trapper_push_device_free);
	zbx_vector_push_device_ptr_destroy(&found_devices);

	zbx_vector_str_clear_ext(&uuids, zbx_str_free);
	zbx_vector_str_destroy(&uuids);

	return ret;
}
