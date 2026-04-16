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

static void	add_hostids_array(struct zbx_json *json, const char *input)
{
	char		*copy, *token, *saveptr;
	zbx_uint64_t	hostid;

	zbx_json_addarray(json, "hostids");

	if (NULL == input || '\0' == *input)
		goto out;

	copy = zbx_strdup(NULL, input);

	for (token = strtok_r(copy, ",", &saveptr); NULL != token; token = strtok_r(NULL, ",", &saveptr))
	{
		while (' ' == *token)
			token++;

		if ('\0' == *token || 0 == strcmp(token, "*UNKNOWN*"))
			continue;

		if (SUCCEED != zbx_is_uint64(token, &hostid))
			continue;

		zbx_json_adduint64(json, NULL, hostid);
	}

	zbx_free(copy);
out:
	zbx_json_close(json);
}

void	get_build_push_params(const zbx_db_event *event, const zbx_db_event *r_event,
		zbx_uint64_t actionid, zbx_uint64_t userid, const char *sendto, const char *subject,
		const char *message, const zbx_db_acknowledge *ack, const zbx_service_alarm_t *service_alarm,
		const zbx_db_service *service, zbx_vector_str_t *params, const char *tz)
{
	zbx_db_result_t			result;
	zbx_db_row_t			row;
	int				message_type;
	zbx_dc_um_handle_t		*um_handle_unmasked;
	zbx_vector_push_target_t	targets;
	char				*subject_dyn = NULL;
	zbx_config_t			cfg;
	const char			*server_id;

	zbx_db_alert	alert = {
			.sendto = (char *)sendto,
			.subject = (char *)(uintptr_t)subject,
			.message = (char *)(uintptr_t)message
	};

	char	*trigger_severity, *event_id, *event_value, *event_update_action, *host_ids, *trigger_id;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_push_target_create(&targets);

	result = zbx_db_select(
		"select d.uuid,d.push_token,dk.kid,dk.key_,dk.scope"
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

	substitute_message_macros(&trigger_id, NULL, 0, message_type, um_handle_unmasked, &actionid, event,
			r_event, &userid, NULL, &alert, service_alarm, service, tz, ack);


	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER_OMEGA: %s, %s, %s, %s", trigger_severity, event_id, event_value,
			trigger_id);

	zbx_config_get(&cfg, ZBX_CONFIG_FLAGS_SERVER_ID);
	server_id = cfg.serverid;

	for (int i = 0; i < targets.values_num; i++)
	{
		struct zbx_json		json;
		zbx_push_target_t	*t = targets.values[i];
		char			*message_uuid7, *uuid7id;

		zbx_json_init(&json, 1024);
		zbx_json_addstring(&json, "jsonrpc", "2.0", ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json, "method", "device.notify", ZBX_JSON_TYPE_STRING);

		zbx_json_addobject(&json, "params");

		zbx_json_addobject(&json, "to");
		zbx_json_addstring(&json, "push_token", t->token, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json, "server_id", server_id, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json, "device_id", t->device_id, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&json); /* to */

		zbx_json_addobject(&json, "payload");
		zbx_json_addstring(&json, "specversion", "1", ZBX_JSON_TYPE_STRING);

		message_uuid7 = zbx_gen_uuid7();

		zbx_json_addstring(&json, "id", message_uuid7, ZBX_JSON_TYPE_STRING);
		zbx_json_addint64(&json, "time", event->clock);

		if (0 == strcmp(event_value, "0"))
		{
			zbx_json_addstring(&json, "type", "problem.recovered", ZBX_JSON_TYPE_STRING);
		}
		else if (0 == strcmp(event_value, "1") && 0 != strcmp(event_update_action, "{EVENT.UPDATE.ACTION}"))
		{
			zbx_json_addstring(&json, "type", "problem.updated", ZBX_JSON_TYPE_STRING);
		}
		else
		{
			zbx_json_addstring(&json, "type", "problem.created", ZBX_JSON_TYPE_STRING);
		}

		zbx_json_addstring(&json, "source", "zabbix/server", ZBX_JSON_TYPE_STRING);
		subject_dyn = zbx_dsprintf(subject_dyn, "event/%s", event_id);
		zbx_json_addstring(&json, "subject", subject_dyn, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json, "schema", "urn:zabbix:server:event:1", ZBX_JSON_TYPE_STRING);

		zbx_json_addobject(&json, "data");
		zbx_json_addstring(&json, "title", subject, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json, "body", message, ZBX_JSON_TYPE_STRING);

		zbx_json_addint64(&json, "eventid", atoi(event_id));

		add_hostids_array(&json, host_ids);

		zbx_json_addint64(&json, "triggerid", atoi(trigger_id));
		zbx_json_addint64(&json, "userid", userid);
		zbx_json_addint64(&json, "severity", event->severity);

		zbx_json_close(&json); /* payload.data */
		zbx_json_close(&json); /* payload */

		zbx_json_addint64(&json, "priority", event->severity);

		zbx_json_addraw(&json, "mobile_encryption_key", t->enc_key);

		zbx_json_close(&json); /* params */

		uuid7id = zbx_gen_uuid7();

		zbx_json_addstring(&json, "id", uuid7id, ZBX_JSON_TYPE_STRING);

		zbx_json_close(&json);

		zbx_vector_str_append(params, zbx_strdup(NULL, json.buffer));
		zbx_free(message_uuid7);
		zbx_free(uuid7id);
		zbx_json_free(&json);
	}

	zbx_db_free_result(result);

	zbx_dc_close_user_macros(um_handle_unmasked);

	for (int i = 0; i < targets.values_num; i++)
	{
		zbx_free(targets.values[i]);
	}

	zbx_vector_push_target_destroy(&targets);

	zbx_free(subject_dyn);
	zbx_free(host_ids);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
