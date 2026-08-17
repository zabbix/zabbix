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

#include "zbxcrypto.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxcacheconfig.h"
#include "zbxjson.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxalgo.h"
#include "zbx_bridge_adapter_constants.h"

typedef struct
{
	char	*device_id;
	char	*token;
	char	*enc_key;
} zbx_push_target_t;

ZBX_VECTOR_DECL(push_target, zbx_push_target_t *)
ZBX_VECTOR_IMPL(push_target, zbx_push_target_t *)
ZBX_PTR_VECTOR_IMPL(push_alert, zbx_push_alert_t *)

static int	push_uuid_is_valid(const char *uuid)
{
	size_t	i;

	if (NULL == uuid || 36 != strlen(uuid))
		return FAIL;

	for (i = 0; i < 36; i++)
	{
		if (8 == i || 13 == i || 18 == i || 23 == i)
		{
			if ('-' != uuid[i])
				return FAIL;
		}
		else if (0 == isxdigit((unsigned char)uuid[i]))
			return FAIL;
	}

	return SUCCEED;
}

static void	push_uuid_normalize(char *uuid)
{
	for (size_t i = 0; '\0' != uuid[i]; i++)
	{
		if ('-' != uuid[i])
			uuid[i] = (char)tolower((unsigned char)uuid[i]);
	}
}

static int	push_target_exists(const zbx_vector_push_target_t *targets, const char *device_id)
{
	if (NULL == device_id || '\0' == *device_id)
		return FAIL;

	for (int i = 0; i < targets->values_num; i++)
	{
		if (0 == strcmp(targets->values[i]->device_id, device_id))
			return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees push alert and its resources                                *
 *                                                                            *
 * Parameters: alert - [IN]                                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_push_alert_free(zbx_push_alert_t *alert)
{
	if (NULL == alert)
		return;

	zbx_free(alert->sendto);
	zbx_free(alert->params);
	zbx_free(alert->error);
	zbx_free(alert);
}

static void	push_alert_append_failed(zbx_vector_push_alert_t *alerts, const char *sendto, const char *error)
{
	zbx_push_alert_t	*alert = zbx_malloc(NULL, sizeof(zbx_push_alert_t));

	alert->sendto = zbx_strdup(NULL, ZBX_NULL2EMPTY_STR(sendto));
	alert->params = NULL;
	alert->error = zbx_strdup(NULL, error);
	alert->status = ALERT_STATUS_FAILED;

	zbx_vector_push_alert_append(alerts, alert);
}

static void	push_target_append(zbx_vector_push_target_t *targets, const char *device_id, const char *token,
		const char *enc_key)
{
	zbx_push_target_t	*target;

	if (SUCCEED == push_target_exists(targets, device_id))
		return;

	target = zbx_malloc(NULL, sizeof(zbx_push_target_t));
	target->device_id = zbx_strdup(NULL, device_id);
	target->token = zbx_strdup(NULL, token);
	target->enc_key = (NULL != enc_key ? zbx_strdup(NULL, enc_key) : NULL);

	zbx_vector_push_target_append(targets, target);
}

static int	push_get_target_info_by_uuid(const char *uuid, zbx_uint64_t *target_userid, int *status)
{
	int			ret = FAIL;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*uuid_esc;

	uuid_esc = zbx_db_dyn_escape_string(uuid);

	result = zbx_db_select(
			"select d.userid,d.status"
			" from device d"
			" where d.uuid='%s'",
			uuid_esc);

	if (NULL != (row = zbx_db_fetch(result)))
	{
		ret = SUCCEED;

		if (NULL != target_userid)
			ZBX_DBROW2UINT64(*target_userid, row[0]);

		if (NULL != status)
			*status = atoi(row[1]);
	}

	zbx_db_free_result(result);
	zbx_free(uuid_esc);

	return ret;
}

static int	push_get_target_by_uuid(const char *uuid, zbx_push_target_t *target)
{
	int			ret = FAIL;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*uuid_esc;

	uuid_esc = zbx_db_dyn_escape_string(uuid);

	result = zbx_db_select(
			"select d.push_token,dk.key_"
			" from device d"
			" left join device_key dk on dk.device_keyid=("
				"select max(dk2.device_keyid)"
				" from device_key dk2"
				" where dk2.deviceid=d.deviceid"
					" and dk2.active=%d"
					" and dk2.scope=%d"
			")"
			" where d.uuid='%s'"
				" and d.status=%d"
				" and d.push_token is not null",
			ZBX_DEVICE_KEY_ACTIVE, ZBX_BRIDGE_ADAPTER_DEVICE_KEY_SCOPE_MOBILE_ENCRYPTION, uuid_esc,
			ZBX_DEVICE_STATUS_ACTIVATED);

	if (NULL != (row = zbx_db_fetch(result)))
	{
		ret = SUCCEED;
		target->device_id = zbx_strdup(NULL, uuid);
		target->token = zbx_strdup(NULL, row[0]);
		target->enc_key = (NULL != row[1] ? zbx_strdup(NULL, row[1]) : NULL);
	}

	zbx_db_free_result(result);
	zbx_free(uuid_esc);

	return ret;
}

static void	push_add_all_user_targets(zbx_uint64_t userid, zbx_vector_push_target_t *targets)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	result = zbx_db_select(
			"select d.uuid,d.push_token,dk.key_"
			" from device d"
			" left join device_key dk on dk.device_keyid=("
				"select max(dk2.device_keyid)"
				" from device_key dk2"
				" where dk2.deviceid=d.deviceid"
					" and dk2.active=%d"
					" and dk2.scope=%d"
			")"
			" where d.userid=" ZBX_FS_UI64
				" and d.status=%d",
			ZBX_DEVICE_KEY_ACTIVE, ZBX_BRIDGE_ADAPTER_DEVICE_KEY_SCOPE_MOBILE_ENCRYPTION, userid,
			ZBX_DEVICE_STATUS_ACTIVATED);

	while (NULL != (row = zbx_db_fetch(result)))
		push_target_append(targets, row[0], row[1], row[2]);

	zbx_db_free_result(result);
}

static void	push_collect_targets(zbx_uint64_t userid, const char *sendto, zbx_vector_push_target_t *targets,
		zbx_vector_push_alert_t *alerts)
{
	char	*copy, *token, *saveptr;

	if (NULL == sendto || '\0' == *sendto)
		return;

	copy = zbx_strdup(NULL, sendto);

	for (token = strtok_r(copy, ",\n", &saveptr); NULL != token; token = strtok_r(NULL, ",\n", &saveptr))
	{
		zbx_uint64_t		target_userid;
		int			target_status;

		zbx_lrtrim(token, ZBX_WHITESPACE);

		if ('\0' == *token)
			continue;

		if (0 == strcmp(token, "*"))
		{
			push_add_all_user_targets(userid, targets);
			continue;
		}

		if (SUCCEED != push_uuid_is_valid(token))
		{
			push_alert_append_failed(alerts, token, ZBX_PUSH_ALERT_ERR_INVALID_UUID);
			continue;
		}

		push_uuid_normalize(token);

		if (SUCCEED != push_get_target_info_by_uuid(token, &target_userid, &target_status))
		{
			push_alert_append_failed(alerts, token, ZBX_PUSH_ALERT_ERR_DEVICE_UNKNOWN);
			continue;
		}

		if (userid != target_userid)
		{
			push_alert_append_failed(alerts, token, ZBX_PUSH_ALERT_ERR_DEVICE_UNKNOWN);
			continue;
		}

		if (ZBX_DEVICE_STATUS_ACTIVATED != target_status)
		{
			push_alert_append_failed(alerts, token, ZBX_PUSH_ALERT_ERR_DEVICE_NOT_ACTIVE);
			continue;
		}

		if (SUCCEED != push_target_exists(targets, token))
		{
			zbx_push_target_t	target;

			if (SUCCEED != push_get_target_by_uuid(token, &target))
				continue;

			push_target_append(targets, target.device_id, target.token, target.enc_key);
			zbx_free(target.device_id);
			zbx_free(target.token);
			zbx_free(target.enc_key);
		}
	}

	zbx_free(copy);
}

static void	add_hostids_array(struct zbx_json *json, const char *input)
{
	char		*copy, *token, *saveptr;
	zbx_uint64_t	hostid;

	zbx_json_addarray(json, "hostids");

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
	zbx_json_close(json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: builds push notification alerts for all devices resolved from     *
 *          the "sendto" recipient list                                       *
 *                                                                            *
 * Parameters: event         - [IN]                                           *
 *             r_event       - [IN] recovery event (optional, can be NULL)    *
 *             actionid      - [IN]                                           *
 *             userid        - [IN]                                           *
 *             sendto        - [IN] comma/newline separated list of device    *
 *                                  UUIDs or "*" for all user's devices       *
 *             subject       - [IN]                                           *
 *             message       - [IN]                                           *
 *             ack           - [IN] (optional, can be NULL)                   *
 *             service_alarm - [IN] (optional, can be NULL)                   *
 *             service       - [IN] (optional, can be NULL)                   *
 *             alerts        - [OUT] resulting push alerts, one per           *
 *                                   resolved device, plus failed alerts      *
 *                                   for invalid/unknown recipients           *
 *             tz            - [IN] user timezone                             *
 *                                                                            *
 ******************************************************************************/
void	get_build_push_params(const zbx_db_event *event, const zbx_db_event *r_event,
		zbx_uint64_t actionid, zbx_uint64_t userid, const char *sendto, const char *subject,
		const char *message, const zbx_db_acknowledge *ack, const zbx_service_alarm_t *service_alarm,
		const zbx_db_service *service, zbx_vector_push_alert_t *alerts, const char *tz)
{
	int				message_type;
	zbx_dc_um_handle_t		*um_handle_unmasked;
	zbx_vector_push_target_t	targets;
	char				*subject_dyn = NULL;

	zbx_db_alert	alert = {
			.sendto = (char *)sendto,
			.subject = (char *)(uintptr_t)subject,
			.message = (char *)(uintptr_t)message
	};

	char	*trigger_severity, *event_id, *event_value, *event_update_action, *host_ids, *trigger_id;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_push_target_create(&targets);
	push_collect_targets(userid, sendto, &targets, alerts);

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

	{
		char	**macro_fields[] = {&trigger_severity, &event_id, &event_value, &event_update_action,
				&host_ids, &trigger_id};
		size_t	i;

		for (i = 0; i < ARRSIZE(macro_fields); i++)
		{
			substitute_message_macros(macro_fields[i], NULL, 0, message_type, um_handle_unmasked,
					&actionid, event, r_event, &userid, NULL, &alert, service_alarm, service, tz,
					ack);
		}
	}

	for (int i = 0; i < targets.values_num; i++)
	{
		struct zbx_json		json;
		zbx_push_target_t	*t = targets.values[i];
		zbx_push_alert_t	*push_alert;
		char			*message_uuid7, *uuid7id;
		zbx_uint64_t		eventid, triggerid;

		zbx_json_init(&json, 1024);
		zbx_json_addstring(&json, "jsonrpc", "2.0", ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json, "method", ZBX_PROTO_VALUE_DEVICE_NOTIFY, ZBX_JSON_TYPE_STRING);

		zbx_json_addobject(&json, "params");

		zbx_json_addobject(&json, "to");

		zbx_json_addstring(&json, "push_token", t->token, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json, "device_id", t->device_id, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&json); /* to */

		zbx_json_addobject(&json, "payload");
		zbx_json_addstring(&json, "specversion", "1", ZBX_JSON_TYPE_STRING);

		message_uuid7 = zbx_gen_uuid7_hyphenated();

		zbx_json_addstring(&json, "id", message_uuid7, ZBX_JSON_TYPE_STRING);

		char	clock_str[ZBX_BRIDGE_TIME_LEN];
		zbx_snprintf(clock_str, sizeof(clock_str), "%d", event->clock);
		zbx_json_addstring(&json, "time", clock_str, ZBX_JSON_TYPE_STRING);

		if (0 == strcmp(event_value, "0"))
		{
			zbx_json_addstring(&json, "type", "problem.resolved", ZBX_JSON_TYPE_STRING);
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
		zbx_json_addstring(&json, "dataschema", "urn:zabbix:server:event:1", ZBX_JSON_TYPE_STRING);

		zbx_json_addobject(&json, "data");
		zbx_json_addstring(&json, "title", subject, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(&json, "body", message, ZBX_JSON_TYPE_STRING);

		if (SUCCEED != zbx_is_uint64(event_id, &eventid))
		{
			THIS_SHOULD_NEVER_HAPPEN_MSG("invalid event ID: \"%s\"",
					NULL == event_id ? "(NULL)" : event_id);
			eventid = 0;
		}

		zbx_json_adduint64(&json, "eventid", eventid);
		add_hostids_array(&json, host_ids);

		if (SUCCEED != zbx_is_uint64(trigger_id, &triggerid))
		{
			THIS_SHOULD_NEVER_HAPPEN_MSG("invalid trigger ID: \"%s\"",
					NULL == trigger_id ? "(NULL)" : trigger_id);
			triggerid = 0;
		}

		zbx_json_adduint64(&json, "triggerid", triggerid);
		zbx_json_addint64(&json, "severity", event->severity);

		zbx_json_close(&json); /* payload.data */
		zbx_json_close(&json); /* payload */

		zbx_json_addint64(&json, "priority", event->severity);

		if (NULL != t->enc_key)
			zbx_json_addraw(&json, "mobile_encryption_key", t->enc_key);
		else
			/* Bridge-adapter treats empty {} as an invalid key.             */
			/* null indicates that MEK is absent for non-encrypted delivery. */
			zbx_json_addstring(&json, "mobile_encryption_key", NULL, ZBX_JSON_TYPE_NULL);

		zbx_json_close(&json); /* params */

		uuid7id = zbx_gen_uuid7_hyphenated();

		zbx_json_addstring(&json, "id", uuid7id, ZBX_JSON_TYPE_STRING);

		zbx_json_close(&json);

		push_alert = zbx_malloc(NULL, sizeof(zbx_push_alert_t));
		push_alert->sendto = zbx_strdup(NULL, t->device_id);
		push_alert->params = zbx_strdup(NULL, json.buffer);
		push_alert->error = NULL;
		push_alert->status = ALERT_STATUS_NEW;

		zbx_vector_push_alert_append(alerts, push_alert);

		zbx_free(message_uuid7);
		zbx_free(uuid7id);
		zbx_json_free(&json);
	}

	zbx_dc_close_user_macros(um_handle_unmasked);

	for (int i = 0; i < targets.values_num; i++)
	{
		zbx_free(targets.values[i]->device_id);
		zbx_free(targets.values[i]->token);
		zbx_free(targets.values[i]->enc_key);
		zbx_free(targets.values[i]);
	}

	zbx_vector_push_target_destroy(&targets);

	zbx_free(trigger_id);
	zbx_free(trigger_severity);
	zbx_free(event_id);
	zbx_free(event_value);
	zbx_free(event_update_action);
	zbx_free(host_ids);
	zbx_free(subject_dyn);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
