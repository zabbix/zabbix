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

#include "get_build_push_param.h"
#include "escalator.h"

#include "zbxstr.h"
#include "zbxalgo.h"
#include "zbxtime.h"
#include "zbxcrypto.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxcacheconfig.h"

typedef struct
{
	const char	*device_id;
	const char	*token;
	const char	*enc_key;
} zbx_push_target_t;

ZBX_VECTOR_DECL(push_target, zbx_push_target_t *)
ZBX_VECTOR_IMPL(push_target, zbx_push_target_t *)

static char	*filter_unknown_hosts(const char *input)
{
	char	*token, *saveptr, *copy, *result;
	size_t	result_size = strlen(input) + 1;

	copy = zbx_strdup(NULL, input);
	result = malloc(result_size);
	result[0] = '\0';

	int	first = 1;

	for (token = strtok_r(copy, ",", &saveptr); token != NULL; token = strtok_r(NULL, ",", &saveptr))
	{
		while (*token == ' ') token++;

		if (strcmp(token, "*UNKNOWN*") != 0)
		{
			if (0 == first)
				strcat(result, ", ");

			strcat(result, token);
			first = 0;
		}
	}

	free(copy);

	return result;
}

void	get_build_push_params(const zbx_db_event *event, const zbx_db_event *r_event,
		zbx_uint64_t actionid, zbx_uint64_t userid, zbx_uint64_t mediatypeid, const char *sendto,
		const char *subject, const char *message, const zbx_db_acknowledge *ack,
		const zbx_service_alarm_t *service_alarm, const zbx_db_service *service, zbx_vector_str_t *params,
		const char *tz)
{
	zbx_db_result_t			result;
	zbx_db_row_t			row;
	int				message_type;
	zbx_dc_um_handle_t		*um_handle_unmasked;
	zbx_vector_push_target_t	targets;

	zbx_db_alert	alert = {
			.sendto = (char *)sendto,
			.subject = (char *)(uintptr_t)subject,
			.message = (char *)(uintptr_t)message
	};

	char	*trigger_severity, *event_id, *event_value, *event_update_action, *host_ids, *trigger_id,
		*filtered_hostids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_push_target_create(&targets);

	result = zbx_db_select(
		"select d.deviceid,d.push_token,dk.kid,dk.key_,dk.scope"
		" from device d "
		" left join device_key dk"
			" on dk.deviceid=d.deviceid and dk.active=1"
				" where d.userid=" ZBX_FS_UI64 " and d.status=1", userid);


	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_push_target_t	*t = zbx_malloc(NULL, sizeof(zbx_push_target_t));

		t->device_id = row[0];
		t->token = row[1];
		t->enc_key = row[3];

		zbx_vector_push_target_append(&targets, t);
	}

	if (0 == targets.values_num)
	{
		zbx_vector_push_target_destroy(&targets);
		goto out;
	}

	if (NULL != ack)
		message_type = ZBX_MESSAGE_TYPE_UPDATE;
	else
		message_type = (NULL != r_event ? ZBX_MESSAGE_TYPE_RECOVERY : ZBX_MESSAGE_TYPE_NORMAL);

	um_handle_unmasked = zbx_dc_open_user_macros_secure();


	trigger_severity = zbx_strdup(NULL, "{TRIGGER.SEVERITY}");
	event_id = zbx_strdup(NULL, "{EVENT.ID}");
	event_value = zbx_strdup(NULL, "{EVENT.VALUE}");
	event_update_action = zbx_strdup(NULL, "{EVENT.UPDATE.ACTION}");

	host_ids = zbx_strdup(NULL, "{HOST.ID1}, {HOST.ID2}, {HOST.ID3}, {HOST.ID4}, {HOST.ID5}, "
			"{HOST.ID6},{HOST.ID7},{HOST.ID8},{HOST.ID9},");
	trigger_id = zbx_strdup(NULL, "{TRIGGER.ID}");

	substitute_message_macros(&trigger_severity, NULL, 0, message_type, um_handle_unmasked, &actionid,
			event, r_event, &userid, NULL, &alert, service_alarm, service, tz, ack);

	substitute_message_macros(&event_id, NULL, 0, message_type, um_handle_unmasked, &actionid, event,
			r_event, &userid, NULL, &alert, service_alarm, service, tz, ack);

	substitute_message_macros(&event_value, NULL, 0, message_type, um_handle_unmasked, &actionid, event,
			r_event, &userid, NULL, &alert, service_alarm, service, tz, ack);

	substitute_message_macros(&event_update_action, NULL, 0, message_type, um_handle_unmasked, &actionid, event,
			r_event, &userid, NULL, &alert, service_alarm, service, tz, ack);

	substitute_message_macros(&host_ids, NULL, 0, message_type, um_handle_unmasked, &actionid, event,
			r_event, &userid, NULL, &alert, service_alarm, service, tz, ack);

	filtered_hostids = filter_unknown_hosts(host_ids);
	zbx_free(host_ids);
	substitute_message_macros(&trigger_id, NULL, 0, message_type, um_handle_unmasked, &actionid, event,
			r_event, &userid, NULL, &alert, service_alarm, service, tz, ack);


	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER_OMEGA: %s, %s, %s, %s, %s", trigger_severity, event_id, event_value,
			filtered_hostids, trigger_id);

	for (int i = 0; i < targets.values_num; i++)
	{
		struct zbx_json		json;
		zbx_push_target_t	*t = targets.values[i];

		zbx_json_init(&json, 1024);
		zbx_json_addstring(&json, "jsonrpc", "2.0", ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json, "method", "notification", ZBX_JSON_TYPE_STRING);

		zbx_json_addobject(&json, "params");

			zbx_json_addobject(&json, "to");
			zbx_json_addstring(&json, "token", t->token, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json, "device_id", t->device_id, ZBX_JSON_TYPE_STRING);
			zbx_json_close(&json); /* to */

			zbx_json_addstring(&json, "priority", trigger_severity, ZBX_JSON_TYPE_STRING);

			zbx_json_addobject(&json, "data");

				zbx_json_addobject(&json, "payload");
					zbx_json_addstring(&json, "specversion", "1", ZBX_JSON_TYPE_STRING);
					zbx_json_addstring(&json, "id", "(UUIDv7)", ZBX_JSON_TYPE_STRING);
					zbx_json_addint64(&json, "time", (int)zbx_time());

					if (0 == strcmp(event_value, "0"))
					{
						zbx_json_addstring(&json, "type", "problem.recovered",
								ZBX_JSON_TYPE_STRING);
					}
					else if (0 == strcmp(event_value, "1") && 0 != strcmp(event_update_action,
							"{EVENT.UPDATE.ACTION}"))
					{
						zbx_json_addstring(&json, "type", "problem.updated",
								ZBX_JSON_TYPE_STRING);
					}
					else
					{
						zbx_json_addstring(&json, "type", "problem.created",
								ZBX_JSON_TYPE_STRING);
					}

					zbx_json_addstring(&json, "source", "<unused>", ZBX_JSON_TYPE_STRING);
					zbx_json_addstring(&json, "subject", "<unused>", ZBX_JSON_TYPE_STRING);
					zbx_json_addstring(&json, "schema", "<unused>", ZBX_JSON_TYPE_STRING);

					zbx_json_addobject(&json, "data");
						zbx_json_addstring(&json, "title", subject, ZBX_JSON_TYPE_STRING);
						zbx_json_addstring(&json, "body", message, ZBX_JSON_TYPE_STRING);

						zbx_json_addstring(&json, "eventid", event_id, ZBX_JSON_TYPE_STRING);

						zbx_json_addarray(&json, "hostids");
						zbx_json_addstring(&json, NULL, filtered_hostids, ZBX_JSON_TYPE_STRING);
						zbx_json_close(&json); /* hostids */

						zbx_json_addstring(&json, "triggerid", trigger_id,
								ZBX_JSON_TYPE_STRING);
					zbx_json_addint64(&json, "userid", userid);
						zbx_json_addstring(&json, "severity", trigger_severity,
								ZBX_JSON_TYPE_STRING);

						zbx_json_close(&json); /* payload.data */
						zbx_json_close(&json); /* payload */

				zbx_json_addstring(&json, "enc_key", t->enc_key, ZBX_JSON_TYPE_STRING);

				zbx_json_close(&json); /* data */
				zbx_json_close(&json); /* params */

		char	*uuid7 = zbx_gen_uuid7();

		zbx_json_addstring(&json, "id", uuid7, ZBX_JSON_TYPE_STRING);

		zbx_json_close(&json);

		zabbix_log(LOG_LEVEL_INFORMATION, "BADGER_OMEGA FINAL PARAMS %s", json.buffer);

		zbx_vector_str_append(params, zbx_strdup(NULL, json.buffer));
	}

	zbx_db_free_result(result);

	zbx_dc_close_user_macros(um_handle_unmasked);

	for (int i = 0; i < targets.values_num; i++)
	{
		zbx_free(targets.values[i]);
	}

	zbx_vector_push_target_destroy(&targets);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

