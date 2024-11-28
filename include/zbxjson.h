/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#ifndef ZABBIX_ZJSON_H
#define ZABBIX_ZJSON_H

#include "zbxtypes.h"
#include "zbxalgo.h"

#define ZBX_PROTO_TAG_CLOCK			"clock"
#define ZBX_PROTO_TAG_NS			"ns"
#define ZBX_PROTO_TAG_DATA			"data"
#define ZBX_PROTO_TAG_COMMANDS			"commands"
#define ZBX_PROTO_TAG_REGEXP			"regexp"
#define ZBX_PROTO_TAG_DELAY			"delay"
#define ZBX_PROTO_TAG_REFRESH_UNSUPPORTED	"refresh_unsupported"
#define ZBX_PROTO_TAG_DRULE			"drule"
#define ZBX_PROTO_TAG_DCHECK			"dcheck"
#define ZBX_PROTO_TAG_HOST			"host"
#define ZBX_PROTO_TAG_HOST_METADATA		"host_metadata"
#define ZBX_PROTO_TAG_INFO			"info"
#define ZBX_PROTO_TAG_IP			"ip"
#define ZBX_PROTO_TAG_DNS			"dns"
#define ZBX_PROTO_TAG_CONN			"conn"
#define ZBX_PROTO_TAG_KEY			"key"
#define ZBX_PROTO_TAG_KEY_ORIG			"key_orig"
#define ZBX_PROTO_TAG_KEYS			"keys"
#define ZBX_PROTO_TAG_LASTLOGSIZE		"lastlogsize"
#define ZBX_PROTO_TAG_MANUALINPUT		"manualinput"
#define ZBX_PROTO_TAG_MTIME			"mtime"
#define ZBX_PROTO_TAG_LOGTIMESTAMP		"timestamp"
#define ZBX_PROTO_TAG_LOGSOURCE			"source"
#define ZBX_PROTO_TAG_LOGSEVERITY		"severity"
#define ZBX_PROTO_TAG_LOGEVENTID		"eventid"
#define ZBX_PROTO_TAG_PORT			"port"
#define ZBX_PROTO_TAG_TLS_ACCEPTED		"tls_accepted"
#define ZBX_PROTO_TAG_PROXY			"proxy"
#define ZBX_PROTO_TAG_REQUEST			"request"
#define ZBX_PROTO_TAG_RESPONSE			"response"
#define ZBX_PROTO_TAG_STATUS			"status"
#define ZBX_PROTO_TAG_STATE			"state"
#define ZBX_PROTO_TAG_TYPE			"type"
#define ZBX_PROTO_TAG_LIMIT			"limit"
#define ZBX_PROTO_TAG_VALUE			"value"
#define ZBX_PROTO_TAG_SCRIPTID			"scriptid"
#define ZBX_PROTO_TAG_HOSTID			"hostid"
#define ZBX_PROTO_TAG_AVAILABLE			"available"
#define ZBX_PROTO_TAG_ERROR			"error"
#define ZBX_PROTO_TAG_USERNAME			"username"
#define ZBX_PROTO_TAG_PASSWORD			"password"
#define ZBX_PROTO_TAG_SID			"sid"
#define ZBX_PROTO_TAG_VERSION			"version"
#define ZBX_PROTO_TAG_INTERFACE_AVAILABILITY	"interface availability"
#define ZBX_PROTO_TAG_HISTORY_DATA		"history data"
#define ZBX_PROTO_TAG_DISCOVERY_DATA		"discovery data"
#define ZBX_PROTO_TAG_AUTOREGISTRATION		"auto registration"
#define ZBX_PROTO_TAG_MORE			"more"
#define ZBX_PROTO_TAG_ITEMID			"itemid"
#define ZBX_PROTO_TAG_TTL			"ttl"
#define ZBX_PROTO_TAG_COMMANDTYPE		"commandtype"
#define ZBX_PROTO_TAG_COMMAND			"command"
#define ZBX_PROTO_TAG_EXECUTE_ON		"execute_on"
#define ZBX_PROTO_TAG_AUTHTYPE			"authtype"
#define ZBX_PROTO_TAG_PUBLICKEY			"publickey"
#define ZBX_PROTO_TAG_PRIVATEKEY		"privatekey"
#define ZBX_PROTO_TAG_PARENT_TASKID		"parent_taskid"
#define ZBX_PROTO_TAG_TASKS			"tasks"
#define ZBX_PROTO_TAG_ALERTID			"alertid"
#define ZBX_PROTO_TAG_JMX_ENDPOINT		"jmx_endpoint"
#define ZBX_PROTO_TAG_EVENTID			"eventid"
#define ZBX_PROTO_TAG_CAUSE_EVENTID		"cause_eventid"
#define ZBX_PROTO_TAG_NAME			"name"
#define ZBX_PROTO_TAG_SEVERITY			"severity"
#define ZBX_PROTO_TAG_HOSTS			"hosts"
#define ZBX_PROTO_TAG_GROUPS			"groups"
#define ZBX_PROTO_TAG_TAGS			"tags"
#define ZBX_PROTO_TAG_TAG			"tag"
#define ZBX_PROTO_TAG_PROBLEM_EVENTID		"p_eventid"
#define ZBX_PROTO_TAG_COUNT			"count"
#define ZBX_PROTO_TAG_MIN			"min"
#define ZBX_PROTO_TAG_AVG			"avg"
#define ZBX_PROTO_TAG_MAX			"max"
#define ZBX_PROTO_TAG_SESSION			"session"
#define ZBX_PROTO_TAG_ID			"id"
#define ZBX_PROTO_TAG_PARAMS			"params"
#define ZBX_PROTO_TAG_FROM			"from"
#define ZBX_PROTO_TAG_TO			"to"
#define ZBX_PROTO_TAG_HISTORY			"history"
#define ZBX_PROTO_TAG_TIMESTAMP			"timestamp"
#define ZBX_PROTO_TAG_ERROR_HANDLER		"error_handler"
#define ZBX_PROTO_TAG_ERROR_HANDLER_PARAMS	"error_handler_params"
#define ZBX_PROTO_TAG_VALUE_TYPE		"value_type"
#define ZBX_PROTO_TAG_STEPS			"steps"
#define ZBX_PROTO_TAG_ACTION			"action"
#define ZBX_PROTO_TAG_FAILED			"failed"
#define ZBX_PROTO_TAG_RESULT			"result"
#define ZBX_PROTO_TAG_LINE_RAW			"line_raw"
#define ZBX_PROTO_TAG_LABELS			"labels"
#define ZBX_PROTO_TAG_HELP			"help"
#define ZBX_PROTO_TAG_MEDIATYPEID		"mediatypeid"
#define ZBX_PROTO_TAG_SENDTO			"sendto"
#define ZBX_PROTO_TAG_SUBJECT			"subject"
#define ZBX_PROTO_TAG_MESSAGE			"message"
#define ZBX_PROTO_TAG_PREVIOUS			"previous"
#define ZBX_PROTO_TAG_SINGLE			"single"
#define ZBX_PROTO_TAG_INTERFACE			"interface"
#define ZBX_PROTO_TAG_FLAGS			"flags"
#define ZBX_PROTO_TAG_PARAMETERS		"parameters"
#define ZBX_PROTO_TAG_PROXYID			"proxyid"
#define ZBX_PROTO_TAG_INTERFACE_ID		"interfaceid"
#define ZBX_PROTO_TAG_USEIP			"useip"
#define ZBX_PROTO_TAG_ADDRESS			"address"
#define ZBX_PROTO_TAG_TLS_CONNECT		"tls_connect"
#define ZBX_PROTO_TAG_TLS_ISSUER		"tls_issuer"
#define ZBX_PROTO_TAG_TLS_SUBJECT		"tls_subject"
#define ZBX_PROTO_TAG_TLS_PSK_IDENTITY		"tls_psk_identity"
#define ZBX_PROTO_TAG_TLS_PSK			"tls_psk"
#define ZBX_PROTO_TAG_FOLLOW_REDIRECTS		"follow_redirects"
#define ZBX_PROTO_TAG_POST_TYPE			"post_type"
#define ZBX_PROTO_TAG_RETRIEVE_MODE		"retrieve_mode"
#define ZBX_PROTO_TAG_REQUEST_METHOD		"request_method"
#define ZBX_PROTO_TAG_OUTPUT_FORMAT		"output_format"
#define ZBX_PROTO_TAG_VERIFY_PEER		"verify_peer"
#define ZBX_PROTO_TAG_VERIFY_HOST		"verify_host"
#define ZBX_PROTO_TAG_SNMP_OID			"snmp_oid"
#define ZBX_PROTO_TAG_DETAILS			"details"
#define ZBX_PROTO_TAG_COMMUNITY			"community"
#define	ZBX_PROTO_TAG_SECURITYNAME		"securityname"
#define ZBX_PROTO_TAG_SECURITYLEVEL		"securitylevel"
#define ZBX_PROTO_TAG_AUTHPASSPHRASE		"authpassphrase"
#define ZBX_PROTO_TAG_PRIVPASSPHRASE		"privpassphrase"
#define ZBX_PROTO_TAG_AUTHPROTOCOL		"authprotocol"
#define ZBX_PROTO_TAG_PRIVPROTOCOL		"privprotocol"
#define ZBX_PROTO_TAG_CONTEXTNAME		"contextname"
#define ZBX_PROTO_TAG_MAX_REPS			"max_repetitions"
#define ZBX_PROTO_TAG_IPMI_SENSOR		"ipmi_sensor"
#define ZBX_PROTO_TAG_TIMEOUT			"timeout"
#define ZBX_PROTO_TAG_URL			"url"
#define ZBX_PROTO_TAG_QUERY_FIELDS		"query_fields"
#define ZBX_PROTO_TAG_POSTS			"posts"
#define ZBX_PROTO_TAG_STATUS_CODES		"status_codes"
#define ZBX_PROTO_TAG_HTTP_PROXY		"http_proxy"
#define ZBX_PROTO_TAG_HTTP_HEADERS		"headers"
#define ZBX_PROTO_TAG_SSL_CERT_FILE		"ssl_cert_file"
#define ZBX_PROTO_TAG_SSL_KEY_FILE		"ssl_key_file"
#define ZBX_PROTO_TAG_SSL_KEY_PASSWORD		"ssl_key_password"
#define ZBX_PROTO_TAG_MAINTENANCE_STATUS	"maintenance_status"
#define ZBX_PROTO_TAG_MAINTENANCE_TYPE		"maintenance_type"
#define ZBX_PROTO_TAG_IPMI_AUTHTYPE		"ipmi_authtype"
#define ZBX_PROTO_TAG_IPMI_PRIVILEGE		"ipmi_privilege"
#define ZBX_PROTO_TAG_IPMI_USERNAME		"ipmi_username"
#define ZBX_PROTO_TAG_IPMI_PASSWORD		"ipmi_password"
#define ZBX_PROTO_TAG_DATA_TYPE			"datatype"
#define ZBX_PROTO_TAG_PROXY_DELAY		"proxy_delay"
#define ZBX_PROTO_TAG_EXPRESSIONS		"expressions"
#define ZBX_PROTO_TAG_EXPRESSION		"expression"
#define ZBX_PROTO_TAG_CLIENTIP			"clientip"
#define ZBX_PROTO_TAG_ITEM_TAGS			"item_tags"
#define ZBX_PROTO_TAG_HISTORY_UPLOAD		"upload"
#define ZBX_PROTO_TAG_DASHBOARDID		"dashboardid"
#define ZBX_PROTO_TAG_USERID			"userid"
#define ZBX_PROTO_TAG_PERIOD			"period"
#define ZBX_PROTO_TAG_NOW			"now"
#define ZBX_PROTO_TAG_SESSIONID			"sessionid"
#define ZBX_PROTO_TAG_SIGN			"sign"
#define ZBX_PROTO_TAG_DETAIL			"detail"
#define ZBX_PROTO_TAG_RECIPIENT			"recipient"
#define ZBX_PROTO_TAG_RECIPIENTS		"recipients"
#define ZBX_PROTO_TAG_LASTACCESS		"lastaccess"
#define ZBX_PROTO_TAG_LASTACCESS_AGE		"lastaccess_age"
#define ZBX_PROTO_TAG_DB_TIMESTAMP		"db_timestamp"
#define ZBX_PROTO_TAG_NODE			"node"
#define ZBX_PROTO_TAG_FAILOVER_DELAY		"failover_delay"
#define ZBX_PROTO_TAG_SECTION			"section"
#define ZBX_PROTO_TAG_PID			"pid"
#define ZBX_PROTO_TAG_PROCESS_NAME		"process_name"
#define ZBX_PROTO_TAG_PROCESS_NUM		"process_num"
#define ZBX_PROTO_TAG_SCOPE			"scope"
#define ZBX_PROTO_TAG_HEARTBEAT_FREQ		"heartbeat_freq"
#define ZBX_PROTO_TAG_ACTIVE_STATUS		"active_status"
#define ZBX_PROTO_TAG_PROXY_ACTIVE_AVAIL_DATA	"host data"
#define ZBX_PROTO_TAG_PROXY_NAME		"proxy_name"
#define ZBX_PROTO_TAG_PROXY_NAMES		"proxy_names"
#define ZBX_PROTO_TAG_PROXYIDS			"proxyids"
#define ZBX_PROTO_TAG_SUPPRESS_UNTIL		"suppress_until"
#define ZBX_PROTO_TAG_CONFIG_REVISION		"config_revision"
#define ZBX_PROTO_TAG_FULL_SYNC			"full_sync"
#define ZBX_PROTO_TAG_MACRO_SECRETS		"macro.secrets"
#define ZBX_PROTO_TAG_REMOVED_HOSTIDS		"del_hostids"
#define ZBX_PROTO_TAG_REMOVED_MACRO_HOSTIDS	"del_macro_hostids"
#define ZBX_PROTO_TAG_ACKNOWLEDGEID		"acknowledgeid"
#define ZBX_PROTO_TAG_WAIT			"wait"
#define ZBX_PROTO_TAG_RUNTIME_ERROR		"runtime_error"
#define ZBX_PROTO_TAG_TRUNCATED			"truncated"
#define ZBX_PROTO_TAG_ORIGINAL_SIZE		"original_size"
#define ZBX_PROTO_TAG_ITEM			"item"
#define ZBX_PROTO_TAG_PREPROCESSING		"preprocessing"
#define ZBX_PROTO_TAG_OPTIONS			"options"
#define ZBX_PROTO_TAG_EOL			"eol"
#define ZBX_PROTO_TAG_REDIRECT			"redirect"
#define ZBX_PROTO_TAG_REVISION			"revision"
#define ZBX_PROTO_TAG_HOSTMAP_REVISION		"hostmap_revision"
#define ZBX_PROTO_TAG_PROXY_GROUP		"proxy_group"
#define ZBX_PROTO_TAG_DEL_HOSTPROXYIDS		"del_hostproxyids"
#define ZBX_PROTO_TAG_RESET			"reset"
#define ZBX_PROTO_TAG_VARIANT			"variant"
#define ZBX_PROTO_TAG_ACKNOWLEDGE		"acknowledge"
#define ZBX_PROTO_TAG_UNACKNOWLEDGE		"unacknowledge"
#define ZBX_PROTO_TAG_UNSUPPRESS		"unsuppress"
#define ZBX_PROTO_TAG_OLD			"old"
#define ZBX_PROTO_TAG_NEW			"new"
#define ZBX_PROTO_TAG_TIME			"time"
#define ZBX_PROTO_TAG_CLOSE			"close"
#define ZBX_PROTO_TAG_CAUSE			"cause"
#define ZBX_PROTO_TAG_SYMPTOM			"symptom"
#define ZBX_PROTO_TAG_AUTH			"auth"
#define ZBX_PROTO_TAG_LEASE_DURATION		"lease_duration"
#define ZBX_PROTO_TAG_PREPROC			"preproc"

#define ZBX_PROTO_VALUE_FAILED		"failed"
#define ZBX_PROTO_VALUE_SUCCESS		"success"

#define ZBX_PROTO_VALUE_GET_PASSIVE_CHECKS	"passive checks"
#define ZBX_PROTO_VALUE_GET_ACTIVE_CHECKS	"active checks"
#define ZBX_PROTO_VALUE_PROXY_CONFIG		"proxy config"
#define ZBX_PROTO_VALUE_PROXY_HEARTBEAT		"proxy heartbeat"
#define ZBX_PROTO_VALUE_SENDER_DATA		"sender data"
#define ZBX_PROTO_VALUE_AGENT_DATA		"agent data"
#define ZBX_PROTO_VALUE_COMMAND			"command"
#define ZBX_PROTO_VALUE_JAVA_GATEWAY_INTERNAL	"java gateway internal"
#define ZBX_PROTO_VALUE_JAVA_GATEWAY_JMX	"java gateway jmx"
#define ZBX_PROTO_VALUE_GET_QUEUE		"queue.get"
#define ZBX_PROTO_VALUE_GET_STATUS		"status.get"
#define ZBX_PROTO_VALUE_PROXY_DATA		"proxy data"
#define ZBX_PROTO_VALUE_PROXY_TASKS		"proxy tasks"
#define ZBX_PROTO_VALUE_ACTIVE_CHECK_HEARTBEAT	"active check heartbeat"

#define ZBX_PROTO_VALUE_GET_QUEUE_OVERVIEW	"overview"
#define ZBX_PROTO_VALUE_GET_QUEUE_PROXY		"overview by proxy"
#define ZBX_PROTO_VALUE_GET_QUEUE_DETAILS	"details"

#define ZBX_PROTO_VALUE_GET_STATUS_PING		"ping"
#define ZBX_PROTO_VALUE_GET_STATUS_FULL		"full"

#define ZBX_PROTO_VALUE_ZABBIX_STATS		"zabbix.stats"
#define ZBX_PROTO_VALUE_ZABBIX_STATS_QUEUE	"queue"

#define ZBX_PROTO_VALUE_ZABBIX_ALERT_SEND	"alert.send"
#define ZBX_PROTO_VALUE_ZABBIX_ITEM_TEST	"item.test"
#define ZBX_PROTO_VALUE_EXPRESSIONS_EVALUATE	"expressions.evaluate"

#define ZBX_PROTO_VALUE_HISTORY_UPLOAD_ENABLED	"enabled"
#define ZBX_PROTO_VALUE_HISTORY_UPLOAD_DISABLED	"disabled"

#define ZBX_PROTO_VALUE_REPORT_TEST		"report.test"

#define ZBX_PROTO_VALUE_HISTORY_PUSH		"history.push"

#define ZBX_PROTO_VALUE_SUPPRESSION_SUPPRESS	"suppress"
#define ZBX_PROTO_VALUE_SUPPRESSION_UNSUPPRESS	"unsuppress"

#define ZBX_PROTO_VALUE_TRUE			"true"

typedef enum
{
	ZBX_JSON_TYPE_UNKNOWN = 0,
	ZBX_JSON_TYPE_STRING,
	ZBX_JSON_TYPE_INT,
	ZBX_JSON_TYPE_ARRAY,
	ZBX_JSON_TYPE_OBJECT,
	ZBX_JSON_TYPE_NULL,
	ZBX_JSON_TYPE_TRUE,
	ZBX_JSON_TYPE_FALSE,
	ZBX_JSON_TYPE_NUMBER
}
zbx_json_type_t;

typedef enum
{
	ZBX_JSON_EMPTY = 0,
	ZBX_JSON_COMMA
}
zbx_json_status_t;

#define ZBX_JSON_STAT_BUF_LEN 4096
#define ZBX_JSON_TEST_DATA_MAX_SIZE (512 * ZBX_KIBIBYTE)

struct zbx_json
{
	char			*buffer;
	char			buf_stat[ZBX_JSON_STAT_BUF_LEN];
	size_t			buffer_allocated;
	size_t			buffer_offset;
	size_t			buffer_size;
	zbx_json_status_t	status;
	int			level;
};

typedef struct zbx_json zbx_json_t;

struct zbx_json_parse
{
	const char		*start;
	const char		*end;
};

typedef struct zbx_json_parse zbx_json_parse_t;

const char	*zbx_json_strerror(void);

void	zbx_json_init(struct zbx_json *j, size_t allocate);
void	zbx_json_initarray(struct zbx_json *j, size_t allocate);
void	zbx_json_init_with(struct zbx_json *j, const char *src, size_t len);
void	zbx_json_clean(struct zbx_json *j);
void	zbx_json_free(struct zbx_json *j);
void	zbx_json_addobject(struct zbx_json *j, const char *name);
void	zbx_json_addarray(struct zbx_json *j, const char *name);
void	zbx_json_addstring(struct zbx_json *j, const char *name, const char *string, zbx_json_type_t type);
size_t	zbx_json_addstring_limit(struct zbx_json *j, const char *name, const char *string, zbx_json_type_t type,
		size_t max_size);
void	zbx_json_adduint64(struct zbx_json *j, const char *name, zbx_uint64_t value);
void	zbx_json_addint64(struct zbx_json *j, const char *name, zbx_int64_t value);
void	zbx_json_addraw(struct zbx_json *j, const char *name, const char *data);
void	zbx_json_addfloat(struct zbx_json *j, const char *name, double value);
void	zbx_json_adddouble(struct zbx_json *j, const char *name, double value);
int	zbx_json_close(struct zbx_json *j);

int		zbx_json_open(const char *buffer, struct zbx_json_parse *jp);
const char	*zbx_json_next(const struct zbx_json_parse *jp, const char *p);
const char	*zbx_json_next_value(const struct zbx_json_parse *jp, const char *p, char *string, size_t len,
		zbx_json_type_t *type);
const char	*zbx_json_next_value_dyn(const struct zbx_json_parse *jp, const char *p, char **string,
		size_t *string_alloc, zbx_json_type_t *type);
const char	*zbx_json_pair_next(const struct zbx_json_parse *jp, const char *p, char *name, size_t len);
const char	*zbx_json_pair_by_name(const struct zbx_json_parse *jp, const char *name);
int		zbx_json_value_by_name(const struct zbx_json_parse *jp, const char *name, char *string, size_t len,
		zbx_json_type_t *type);
int		zbx_json_value_by_name_dyn(const struct zbx_json_parse *jp, const char *name, char **string,
		size_t *string_alloc, zbx_json_type_t *type);
int		zbx_json_brackets_open(const char *p, struct zbx_json_parse *jp);
int		zbx_json_brackets_by_name(const struct zbx_json_parse *jp, const char *name, struct zbx_json_parse *out);
int		zbx_json_object_is_empty(const struct zbx_json_parse *jp);
int		zbx_json_count(const struct zbx_json_parse *jp);
const char	*zbx_json_decodevalue(const char *p, char *string, size_t size, zbx_json_type_t *type);
const char	*zbx_json_decodevalue_dyn(const char *p, char **string, size_t *string_alloc, zbx_json_type_t *type);
void		zbx_json_escape(char **string);
int		zbx_json_open_path(const struct zbx_json_parse *jp, const char *path, struct zbx_json_parse *out);
zbx_json_type_t	zbx_json_valuetype(const char *p);
struct zbx_json	*zbx_json_clone(const struct zbx_json *src);

/* jsonpath support */

typedef struct zbx_jsonpath_segment zbx_jsonpath_segment_t;

typedef struct
{
	zbx_jsonpath_segment_t	*segments;
	int			segments_num;
	int			segments_alloc;

	/* set to 1 when jsonpath points at single location */
	unsigned char		definite;
	unsigned char		first_match;	/* set to 1 if first match must be returned */
}
zbx_jsonpath_t;

typedef struct zbx_jsonobj zbx_jsonobj_t;

ZBX_PTR_VECTOR_DECL(jsonobj_ptr, zbx_jsonobj_t *)

typedef union
{
	char				*string;
	double				number;
	zbx_hashset_t			object;
	zbx_vector_jsonobj_ptr_t	array;
}
zbx_jsonobj_data_t;

struct zbx_jsonobj
{
	zbx_json_type_t		type;
	zbx_jsonobj_data_t	data;
};

typedef struct
{
	char		*name;
	zbx_jsonobj_t	value;
}
zbx_jsonobj_el_t;

typedef struct zbx_jsonpath_index zbx_jsonpath_index_t;

int	zbx_jsonpath_compile(const char *path, zbx_jsonpath_t *jsonpath);
int	zbx_jsonpath_query(const struct zbx_json_parse *jp, const char *path, char **output);
int	zbx_jsonobj_query_ext(zbx_jsonobj_t *obj, zbx_jsonpath_index_t *index, const char *path, char **output);
void	zbx_jsonpath_clear(zbx_jsonpath_t *jsonpath);

zbx_jsonpath_index_t	*zbx_jsonpath_index_create(char **error);
void	zbx_jsonpath_index_free(zbx_jsonpath_index_t *index);

int	zbx_jsonobj_open(const char *data, zbx_jsonobj_t *obj);
void	zbx_jsonobj_clear(zbx_jsonobj_t *obj);
int	zbx_jsonobj_query(zbx_jsonobj_t *obj, const char *path, char **output);
int	zbx_jsonobj_to_string(char **str, size_t *str_alloc, size_t *str_offset, zbx_jsonobj_t *obj);

#endif /* ZABBIX_ZJSON_H */
