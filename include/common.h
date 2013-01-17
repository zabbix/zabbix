/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#ifndef ZABBIX_COMMON_H
#define ZABBIX_COMMON_H

#include "sysinc.h"
#include "zbxtypes.h"
#include "version.h"

#ifdef DEBUG
#	include "threads.h"

#	define SDI(msg)		fprintf(stderr, "%6li:DEBUG INFO: %s\n", zbx_get_thread_id(), msg); fflush(stderr)
#	define SDI2(msg,p1)	fprintf(stderr, "%6li:DEBUG INFO: " msg "\n", zbx_get_thread_id(), p1); fflush(stderr)
#	define zbx_dbg_assert(exp)	assert(exp)
#else
#	define SDI(msg)			((void)(0))
#	define SDI2(msg,p1)		((void)(0))
#	define zbx_dbg_assert(exp)	((void)(0))
#endif

#if defined(ENABLE_CHECK_MEMOTY)
#	include "crtdbg.h"

#	define REINIT_CHECK_MEMORY() \
		_CrtMemCheckpoint(&oldMemState)

#	define INIT_CHECK_MEMORY() \
		char DumpMessage[0x1FF]; \
		_CrtMemState  oldMemState, newMemState, diffMemState; \
		REINIT_CHECK_MEMORY()

#	define CHECK_MEMORY(fncname, msg) \
		DumpMessage[0] = '\0'; \
		_CrtMemCheckpoint(&newMemState); \
		if(_CrtMemDifference(&diffMemState, &oldMemState, &newMemState)) \
		{ \
			zbx_snprintf(DumpMessage, sizeof(DumpMessage), \
				"%s\n" \
				"free:  %10li bytes in %10li blocks\n" \
				"normal:%10li bytes in %10li blocks\n" \
				"CRT:   %10li bytes in %10li blocks\n", \
				 \
				fncname ": Memory changed! (" msg ")\n", \
				 \
				(long) diffMemState.lSizes[_FREE_BLOCK], \
				(long) diffMemState.lCounts[_FREE_BLOCK], \
				 \
				(long) diffMemState.lSizes[_NORMAL_BLOCK], \
				(long) diffMemState.lCounts[_NORMAL_BLOCK], \
				 \
				(long) diffMemState.lSizes[_CRT_BLOCK], \
				(long) diffMemState.lCounts[_CRT_BLOCK]); \
		} \
		else \
		{ \
			zbx_snprintf(DumpMessage, sizeof(DumpMessage), \
					"%s: Memory OK! (%s)", fncname, msg); \
		} \
		SDI2("MEMORY_LEAK: %s", DumpMessage)
#else
#	define INIT_CHECK_MEMORY() ((void)0)
#	define CHECK_MEMORY(fncname, msg) ((void)0)
#endif

#ifndef va_copy
#	if defined(__va_copy)
#		define va_copy(d, s) __va_copy(d, s)
#	else
#		define va_copy(d, s) memcpy(&d, &s, sizeof(va_list))
#	endif
#endif

#ifdef snprintf
#	undef snprintf
#endif
#define snprintf	ERROR_DO_NOT_USE_SNPRINTF_FUNCTION_TRY_TO_USE_ZBX_SNPRINTF

#ifdef sprintf
#	undef sprintf
#endif
#define sprintf		ERROR_DO_NOT_USE_SPRINTF_FUNCTION_TRY_TO_USE_ZBX_SNPRINTF

#ifdef strncpy
#	undef strncpy
#endif
#define strncpy		ERROR_DO_NOT_USE_STRNCPY_FUNCTION_TRY_TO_USE_ZBX_STRLCPY

#ifdef strcpy
#	undef strcpy
#endif
#define strcpy		ERROR_DO_NOT_USE_STRCPY_FUNCTION_TRY_TO_USE_ZBX_STRLCPY

#ifdef vsprintf
#	undef vsprintf
#endif
#define vsprintf	ERROR_DO_NOT_USE_VSPRINTF_FUNCTION_TRY_TO_USE_ZBX_VSNPRINTF

#ifdef strncat
#	undef strncat
#endif
#define strncat		ERROR_DO_NOT_USE_STRNCAT_FUNCTION_TRY_TO_USE_ZBX_STRLCAT

#define ON	1
#define OFF	0

#if defined(_WINDOWS)
#	define	ZBX_SERVICE_NAME_LEN	64
extern char ZABBIX_SERVICE_NAME[ZBX_SERVICE_NAME_LEN];
extern char ZABBIX_EVENT_SOURCE[ZBX_SERVICE_NAME_LEN];

#	pragma warning (disable: 4996)	/* warning C4996: <function> was declared deprecated */
#endif

#define	SUCCEED		0
#define	FAIL		-1
#define	NOTSUPPORTED	-2
#define	NETWORK_ERROR	-3
#define	TIMEOUT_ERROR	-4
#define	AGENT_ERROR	-5
#define	GATEWAY_ERROR	-6

#define SUCCEED_OR_FAIL(result) (FAIL != (result) ? SUCCEED : FAIL)
const char	*zbx_result_string(int result);

#define MAX_ID_LEN		21
#define MAX_STRING_LEN		2048
#define MAX_BUFFER_LEN		65536
#define MAX_ZBX_HOSTNAME_LEN	64

#define ZBX_MAX_UINT64_LEN	21
#define ZBX_DM_DELIMITER	'\255'

typedef struct
{
	int	sec;	/* seconds */
	int	ns;	/* nanoseconds */
}
zbx_timespec_t;

/* item types */
typedef enum
{
	ITEM_TYPE_ZABBIX = 0,
	ITEM_TYPE_SNMPv1,
	ITEM_TYPE_TRAPPER,
	ITEM_TYPE_SIMPLE,
	ITEM_TYPE_SNMPv2c,
	ITEM_TYPE_INTERNAL,
	ITEM_TYPE_SNMPv3,
	ITEM_TYPE_ZABBIX_ACTIVE,
	ITEM_TYPE_AGGREGATE,
	ITEM_TYPE_HTTPTEST,
	ITEM_TYPE_EXTERNAL,
	ITEM_TYPE_DB_MONITOR,
	ITEM_TYPE_IPMI,
	ITEM_TYPE_SSH,
	ITEM_TYPE_TELNET,
	ITEM_TYPE_CALCULATED,
	ITEM_TYPE_JMX,
	ITEM_TYPE_SNMPTRAP	/* 17 */
}
zbx_item_type_t;
const char	*zbx_host_type_string(zbx_item_type_t item_type);

typedef enum
{
	INTERFACE_TYPE_UNKNOWN = 0,
	INTERFACE_TYPE_AGENT,
	INTERFACE_TYPE_SNMP,
	INTERFACE_TYPE_IPMI,
	INTERFACE_TYPE_JMX,
	INTERFACE_TYPE_ANY = 255
}
zbx_interface_type_t;
const char	*zbx_interface_type_string(zbx_interface_type_t type);

#define INTERFACE_TYPE_COUNT	4	/* number of interface types */
extern const int	INTERFACE_TYPE_PRIORITY[INTERFACE_TYPE_COUNT];

#define ZBX_FLAG_DISCOVERY		0x01	/* low-level discovery rule */
#define ZBX_FLAG_DISCOVERY_CHILD	0x02	/* low-level discovery proto-item, proto-trigger or proto-graph */
#define ZBX_FLAG_DISCOVERY_CREATED	0x04	/* auto-created item, trigger or graph */

typedef enum
{
	ITEM_AUTHTYPE_PASSWORD = 0,
	ITEM_AUTHTYPE_PUBLICKEY
} zbx_item_authtype_t;

/* event sources */
typedef enum
{
	EVENT_SOURCE_TRIGGERS = 0,
	EVENT_SOURCE_DISCOVERY,
	EVENT_SOURCE_AUTO_REGISTRATION
} zbx_event_source_t;

/* event objects */
typedef enum
{
/* EVENT_SOURCE_TRIGGERS */
	EVENT_OBJECT_TRIGGER = 0,
/* EVENT_SOURCE_DISCOVERY */
	EVENT_OBJECT_DHOST,
	EVENT_OBJECT_DSERVICE,
/* EVENT_SOURCE_AUTO_REGISTRATION */
	EVENT_OBJECT_ZABBIX_ACTIVE
} zbx_event_object_t;

typedef enum
{
	DOBJECT_STATUS_UP = 0,
	DOBJECT_STATUS_DOWN,
	DOBJECT_STATUS_DISCOVER,
	DOBJECT_STATUS_LOST
} zbx_dstatus_t;

/* item value types */
typedef enum
{
	ITEM_VALUE_TYPE_FLOAT = 0,
	ITEM_VALUE_TYPE_STR,
	ITEM_VALUE_TYPE_LOG,
	ITEM_VALUE_TYPE_UINT64,
	ITEM_VALUE_TYPE_TEXT
} zbx_item_value_type_t;
const char	*zbx_item_value_type_string(zbx_item_value_type_t value_type);

typedef union
{
	double		dbl;
	zbx_uint64_t	ui64;
	char		*str;
	char		*err;
}
history_value_t;

/* item data types */
typedef enum
{
	ITEM_DATA_TYPE_DECIMAL = 0,
	ITEM_DATA_TYPE_OCTAL,
	ITEM_DATA_TYPE_HEXADECIMAL,
	ITEM_DATA_TYPE_BOOLEAN
} zbx_item_data_type_t;
const char	*zbx_item_data_type_string(zbx_item_data_type_t data_type);

/* service supported by discoverer */
typedef enum
{
	SVC_SSH = 0,
	SVC_LDAP,
	SVC_SMTP,
	SVC_FTP,
	SVC_HTTP,
	SVC_POP,
	SVC_NNTP,
	SVC_IMAP,
	SVC_TCP,
	SVC_AGENT,
	SVC_SNMPv1,
	SVC_SNMPv2c,
	SVC_ICMPPING,
	SVC_SNMPv3,
	SVC_HTTPS,
	SVC_TELNET
} zbx_dservice_type_t;
const char	*zbx_dservice_type_string(zbx_dservice_type_t service);

/* item snmpv3 security levels */
#define ITEM_SNMPV3_SECURITYLEVEL_NOAUTHNOPRIV	0
#define ITEM_SNMPV3_SECURITYLEVEL_AUTHNOPRIV	1
#define ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV	2

/* item snmpv3 authentication protocol */
#define ITEM_SNMPV3_AUTHPROTOCOL_MD5		0
#define ITEM_SNMPV3_AUTHPROTOCOL_SHA		1

/* item snmpv3 privacy protocol */
#define ITEM_SNMPV3_PRIVPROTOCOL_DES		0
#define ITEM_SNMPV3_PRIVPROTOCOL_AES		1

/* item multiplier types */
#define ITEM_MULTIPLIER_DO_NOT_USE		0
#define ITEM_MULTIPLIER_USE			1

/* item delta types */
typedef enum
{
	ITEM_STORE_AS_IS = 0,
	ITEM_STORE_SPEED_PER_SECOND,
	ITEM_STORE_SIMPLE_CHANGE
} zbx_item_store_type_t;

/* object types for operations */
#define OPERATION_OBJECT_USER	0
#define OPERATION_OBJECT_GROUP	1

/* condition evaluation types */
typedef enum
{
	ACTION_EVAL_TYPE_AND_OR	= 0,
	ACTION_EVAL_TYPE_AND,
	ACTION_EVAL_TYPE_OR
} zbx_action_eval_type_t;

/* condition types */
typedef enum
{
	CONDITION_TYPE_HOST_GROUP = 0,
	CONDITION_TYPE_HOST,
	CONDITION_TYPE_TRIGGER,
	CONDITION_TYPE_TRIGGER_NAME,
	CONDITION_TYPE_TRIGGER_SEVERITY,
	CONDITION_TYPE_TRIGGER_VALUE,
	CONDITION_TYPE_TIME_PERIOD,
	CONDITION_TYPE_DHOST_IP,
	CONDITION_TYPE_DSERVICE_TYPE,
	CONDITION_TYPE_DSERVICE_PORT,
	CONDITION_TYPE_DSTATUS,
	CONDITION_TYPE_DUPTIME,
	CONDITION_TYPE_DVALUE,
	CONDITION_TYPE_HOST_TEMPLATE,
	CONDITION_TYPE_EVENT_ACKNOWLEDGED,
	CONDITION_TYPE_APPLICATION,
	CONDITION_TYPE_MAINTENANCE,
	CONDITION_TYPE_NODE,
	CONDITION_TYPE_DRULE,
	CONDITION_TYPE_DCHECK,
	CONDITION_TYPE_PROXY,
	CONDITION_TYPE_DOBJECT,
	CONDITION_TYPE_HOST_NAME
} zbx_condition_type_t;

/* condition operators */
typedef enum
{
	CONDITION_OPERATOR_EQUAL = 0,
	CONDITION_OPERATOR_NOT_EQUAL,
	CONDITION_OPERATOR_LIKE,
	CONDITION_OPERATOR_NOT_LIKE,
	CONDITION_OPERATOR_IN,
	CONDITION_OPERATOR_MORE_EQUAL,
	CONDITION_OPERATOR_LESS_EQUAL,
	CONDITION_OPERATOR_NOT_IN
} zbx_condition_op_t;

typedef enum
{
	SYSMAP_ELEMENT_TYPE_HOST = 0,
	SYSMAP_ELEMENT_TYPE_MAP,
	SYSMAP_ELEMENT_TYPE_TRIGGER,
	SYSMAP_ELEMENT_TYPE_HOST_GROUP,
	SYSMAP_ELEMENT_TYPE_IMAGE
} zbx_sysmap_element_types_t;

typedef enum
{
	GRAPH_YAXIS_TYPE_CALCULATED = 0,
	GRAPH_YAXIS_TYPE_FIXED,
	GRAPH_YAXIS_TYPE_ITEM_VALUE
} zbx_graph_yaxis_types_t;

typedef enum
{
	AUDIT_RESOURCE_USER = 0,
/*	AUDIT_RESOURCE_ZABBIX,*/
	AUDIT_RESOURCE_ZABBIX_CONFIG = 2,
	AUDIT_RESOURCE_MEDIA_TYPE,
	AUDIT_RESOURCE_HOST,
	AUDIT_RESOURCE_ACTION,
	AUDIT_RESOURCE_GRAPH,
	AUDIT_RESOURCE_GRAPH_ELEMENT,
/*	AUDIT_RESOURCE_ESCALATION,
	AUDIT_RESOURCE_ESCALATION_RULE,
	AUDIT_RESOURCE_AUTOREGISTRATION,*/
	AUDIT_RESOURCE_USER_GROUP = 11,
	AUDIT_RESOURCE_APPLICATION,
	AUDIT_RESOURCE_TRIGGER,
	AUDIT_RESOURCE_HOST_GROUP,
	AUDIT_RESOURCE_ITEM,
	AUDIT_RESOURCE_IMAGE,
	AUDIT_RESOURCE_VALUE_MAP,
	AUDIT_RESOURCE_IT_SERVICE,
	AUDIT_RESOURCE_MAP,
	AUDIT_RESOURCE_SCREEN,
	AUDIT_RESOURCE_NODE,
	AUDIT_RESOURCE_SCENARIO,
	AUDIT_RESOURCE_DISCOVERY_RULE,
	AUDIT_RESOURCE_SLIDESHOW,
	AUDIT_RESOURCE_SCRIPT,
	AUDIT_RESOURCE_PROXY,
	AUDIT_RESOURCE_MAINTENANCE,
	AUDIT_RESOURCE_REGEXP
} zbx_auditlog_resourcetype_t;

/* special item key used for ICMP pings */
#define SERVER_ICMPPING_KEY	"icmpping"
/* special item key used for ICMP ping latency */
#define SERVER_ICMPPINGSEC_KEY	"icmppingsec"
/* special item key used for ICMP ping loss packages */
#define SERVER_ICMPPINGLOSS_KEY	"icmppingloss"

/* runtime control options */
#define ZBX_CONFIG_CACHE_RELOAD	"config_cache_reload"

/* value for not supported items */
#define ZBX_NOTSUPPORTED	"ZBX_NOTSUPPORTED"
/* Zabbix Agent non-critical error */
#define ZBX_ERROR		"ZBX_ERROR"

/* media types */
typedef enum
{
	MEDIA_TYPE_EMAIL = 0,
	MEDIA_TYPE_EXEC,
	MEDIA_TYPE_SMS,
	MEDIA_TYPE_JABBER,
	MEDIA_TYPE_EZ_TEXTING = 100
} zbx_media_type_t;

/* alert statuses */
typedef enum
{
	ALERT_STATUS_NOT_SENT = 0,
	ALERT_STATUS_SENT,
	ALERT_STATUS_FAILED
} zbx_alert_status_t;
const char	*zbx_alert_status_string(unsigned char type, unsigned char status);

/* escalation statuses */
typedef enum
{
	ESCALATION_STATUS_ACTIVE = 0,
	ESCALATION_STATUS_RECOVERY,	/* only in server code, never in DB */
	ESCALATION_STATUS_SLEEP,
	ESCALATION_STATUS_COMPLETED	/* only in server code, never in DB */
}
zbx_escalation_status_t;
const char      *zbx_escalation_status_string(unsigned char status);

/* alert types */
typedef enum
{
	ALERT_TYPE_MESSAGE = 0,
	ALERT_TYPE_COMMAND
} zbx_alert_type_t;
const char	*zbx_alert_type_string(unsigned char type);

/* item statuses */
typedef enum
{
	ITEM_STATUS_ACTIVE = 0,
	ITEM_STATUS_DISABLED,
/*ITEM_STATUS_TRAPPED		2*/
	ITEM_STATUS_NOTSUPPORTED = 3,
/*ITEM_STATUS_DELETED		4*/
/*ITEM_STATUS_NOTAVAILABLE	5*/
} zbx_item_status_t;

/* trigger types */
typedef enum
{
	TRIGGER_TYPE_NORMAL = 0,
	TRIGGER_TYPE_MULTIPLE_TRUE
} zbx_trigger_type_t;

/* group statuses */
typedef enum
{
       GROUP_STATUS_ACTIVE = 0,
       GROUP_STATUS_DISABLED
} zbx_group_status_type_t;

/* daemon type */
#define ZBX_DAEMON_TYPE_SERVER		0x01
#define ZBX_DAEMON_TYPE_PROXY_ACTIVE	0x02
#define ZBX_DAEMON_TYPE_PROXY_PASSIVE	0x04
#define ZBX_DAEMON_TYPE_PROXY		0x06	/* ZBX_DAEMON_TYPE_PROXY_ACTIVE | ZBX_DAEMON_TYPE_PROXY_PASSIVE */
#define ZBX_DAEMON_TYPE_AGENT		0x08

/* maintenance */
typedef enum
{
	TIMEPERIOD_TYPE_ONETIME = 0,
/*	TIMEPERIOD_TYPE_HOURLY,*/
	TIMEPERIOD_TYPE_DAILY = 2,
	TIMEPERIOD_TYPE_WEEKLY,
	TIMEPERIOD_TYPE_MONTHLY,
} zbx_timeperiod_type_t;

typedef enum
{
	MAINTENANCE_TYPE_NORMAL = 0,
	MAINTENANCE_TYPE_NODATA
} zbx_maintenance_type_t;

/* regular expressions */
typedef enum
{
	EXPRESSION_TYPE_INCLUDED = 0,
	EXPRESSION_TYPE_ANY_INCLUDED,
	EXPRESSION_TYPE_NOT_INCLUDED,
	EXPRESSION_TYPE_TRUE,
	EXPRESSION_TYPE_FALSE
} zbx_expression_type_t;

typedef enum
{
	ZBX_IGNORE_CASE = 0,
	ZBX_CASE_SENSITIVE
} zbx_case_sensitive_t;

/* HTTP tests statuses */
#define HTTPTEST_STATUS_MONITORED	0
#define HTTPTEST_STATUS_NOT_MONITORED	1

/* discovery rule */
#define DRULE_STATUS_MONITORED		0
#define DRULE_STATUS_NOT_MONITORED	1

/* host statuses */
#define HOST_STATUS_MONITORED		0
#define HOST_STATUS_NOT_MONITORED	1
/*#define HOST_STATUS_UNREACHABLE	2*/
#define HOST_STATUS_TEMPLATE		3
/*#define HOST_STATUS_DELETED		4*/
#define HOST_STATUS_PROXY_ACTIVE	5
#define HOST_STATUS_PROXY_PASSIVE	6

/* host maintenance status */
#define HOST_MAINTENANCE_STATUS_OFF	0
#define HOST_MAINTENANCE_STATUS_ON	1

/* host inventory mode */
#define HOST_INVENTORY_MANUAL		0
#define HOST_INVENTORY_AUTOMATIC	1

/* host availability */
#define HOST_AVAILABLE_UNKNOWN	0
#define HOST_AVAILABLE_TRUE	1
#define HOST_AVAILABLE_FALSE	2

/* trigger statuses */
#define TRIGGER_STATUS_ENABLED	0
#define TRIGGER_STATUS_DISABLED	1

/* trigger values */
#define TRIGGER_VALUE_FALSE	0
#define TRIGGER_VALUE_TRUE	1
#define TRIGGER_VALUE_UNKNOWN	2 /* only in "events" table */

/* trigger value flags */
#define TRIGGER_VALUE_FLAG_NORMAL	0
#define TRIGGER_VALUE_FLAG_UNKNOWN	1

/* trigger value change flags */
#define TRIGGER_VALUE_CHANGED_NO	0
#define TRIGGER_VALUE_CHANGED_YES	1

/* trigger severity */
#define TRIGGER_SEVERITY_NOT_CLASSIFIED	0
#define TRIGGER_SEVERITY_INFORMATION	1
#define TRIGGER_SEVERITY_WARNING	2
#define TRIGGER_SEVERITY_AVERAGE	3
#define TRIGGER_SEVERITY_HIGH		4
#define TRIGGER_SEVERITY_DISASTER	5
#define TRIGGER_SEVERITY_COUNT		6	/* number of trigger severities */

typedef enum
{
	ITEM_LOGTYPE_INFORMATION = 1,
	ITEM_LOGTYPE_WARNING,
	ITEM_LOGTYPE_ERROR = 4,
	ITEM_LOGTYPE_FAILURE_AUDIT = 7,
	ITEM_LOGTYPE_SUCCESS_AUDIT
} zbx_item_logtype_t;
const char	*zbx_item_logtype_string(zbx_item_logtype_t logtype);

/* media statuses */
#define MEDIA_STATUS_ACTIVE	0
#define MEDIA_STATUS_DISABLED	1

/* action statuses */
#define ACTION_STATUS_ACTIVE	0
#define ACTION_STATUS_DISABLED	1

/* max number of retries for alerts */
#define ALERT_MAX_RETRIES	3

/* media type statuses */
#define MEDIA_TYPE_STATUS_ACTIVE	0
#define MEDIA_TYPE_STATUS_DISABLED	1

/* operation types */
#define OPERATION_TYPE_MESSAGE		0
#define OPERATION_TYPE_COMMAND		1
#define OPERATION_TYPE_HOST_ADD		2
#define OPERATION_TYPE_HOST_REMOVE	3
#define OPERATION_TYPE_GROUP_ADD	4
#define OPERATION_TYPE_GROUP_REMOVE	5
#define OPERATION_TYPE_TEMPLATE_ADD	6
#define OPERATION_TYPE_TEMPLATE_REMOVE	7
#define OPERATION_TYPE_HOST_ENABLE	8
#define OPERATION_TYPE_HOST_DISABLE	9

/* algorithms for service status calculation */
#define SERVICE_ALGORITHM_NONE	0
#define SERVICE_ALGORITHM_MAX	1
#define SERVICE_ALGORITHM_MIN	2

/* types of nodes check sums */
#define	NODE_CKSUM_TYPE_OLD	0
#define	NODE_CKSUM_TYPE_NEW	1

/* types of operation in config log */
#define	NODE_CONFIGLOG_OP_UPDATE	0
#define	NODE_CONFIGLOG_OP_ADD		1
#define	NODE_CONFIGLOG_OP_DELETE	2

/* HTTP item types */
#define ZBX_HTTPITEM_TYPE_RSPCODE	0
#define ZBX_HTTPITEM_TYPE_TIME		1
#define ZBX_HTTPITEM_TYPE_SPEED		2
#define ZBX_HTTPITEM_TYPE_LASTSTEP	3
#define ZBX_HTTPITEM_TYPE_LASTERROR	4

/* user permissions */
typedef enum
{
	USER_TYPE_ZABBIX_USER = 1,
	USER_TYPE_ZABBIX_ADMIN,
	USER_TYPE_SUPER_ADMIN
} zbx_user_type_t;

typedef enum
{
	PERM_DENY = 0,
	PERM_READ_LIST,
	PERM_READ_ONLY,
	PERM_READ_WRITE,
	PERM_MAX = 3
} zbx_user_permission_t;

const char	*zbx_permission_string(int perm);

#define	ZBX_NODE_SLAVE	0
#define	ZBX_NODE_MASTER	1
const char	*zbx_nodetype_string(unsigned char nodetype);

typedef struct
{
	unsigned char	type;
	unsigned char	execute_on;
	char		*port;
	unsigned char	authtype;
	char		*username;
	char		*password;
	char		*publickey;
	char		*privatekey;
	char		*command;
	zbx_uint64_t	scriptid;
}
zbx_script_t;

#define ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT	0
#define ZBX_SCRIPT_TYPE_IPMI		1
#define ZBX_SCRIPT_TYPE_SSH		2
#define ZBX_SCRIPT_TYPE_TELNET		3
#define ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT	4

#define ZBX_SCRIPT_EXECUTE_ON_AGENT	0
#define ZBX_SCRIPT_EXECUTE_ON_SERVER	1

#define POLLER_DELAY		5
#define DISCOVERER_DELAY	60

#define	GET_SENDER_TIMEOUT	60

#ifndef MAX
#	define MAX(a, b) ((a) > (b) ? (a) : (b))
#endif

#ifndef MIN
#	define MIN(a, b) ((a) < (b) ? (a) : (b))
#endif

#define zbx_calloc(old, nmemb, size)	zbx_calloc2(__FILE__, __LINE__, old, nmemb, size)
#define zbx_malloc(old, size)		zbx_malloc2(__FILE__, __LINE__, old, size)
#define zbx_realloc(src, size)		zbx_realloc2(__FILE__, __LINE__, src, size)
#define zbx_strdup(old, str)		zbx_strdup2(__FILE__, __LINE__, old, str)

#define ZBX_STRDUP(var, str)	(var = zbx_strdup(var, str))

void    *zbx_calloc2(const char *filename, int line, void *old, size_t nmemb, size_t size);
void    *zbx_malloc2(const char *filename, int line, void *old, size_t size);
void    *zbx_realloc2(const char *filename, int line, void *old, size_t size);
char    *zbx_strdup2(const char *filename, int line, char *old, const char *str);

#define zbx_free(ptr)		\
				\
do				\
{				\
	if (ptr)		\
	{			\
		free(ptr);	\
		ptr = NULL;	\
	}			\
}				\
while (0)

#define zbx_fclose(file)	\
				\
do				\
{				\
	if (file)		\
	{			\
		fclose(file);	\
		file = NULL;	\
	}			\
}				\
while (0)

#define THIS_SHOULD_NEVER_HAPPEN	zbx_error("ERROR [file:%s,line:%d] "				\
							"Something impossible has just happened.",	\
							__FILE__, __LINE__)

#define MIN_ZABBIX_PORT 1024u
#define MAX_ZABBIX_PORT 65535u

extern const char	*progname;
extern const char	title_message[];
extern const char	usage_message[];
extern const char	*help_message[];

void	help();
void	usage();
void	version();

/* max length of base64 data */
#define ZBX_MAX_B64_LEN 16 * 1024

const char	*get_program_name(const char *path);

typedef enum
{
	ZBX_TASK_START = 0,
	ZBX_TASK_PRINT_SUPPORTED,
	ZBX_TASK_TEST_METRIC,
	ZBX_TASK_SHOW_USAGE,
	ZBX_TASK_INSTALL_SERVICE,
	ZBX_TASK_UNINSTALL_SERVICE,
	ZBX_TASK_START_SERVICE,
	ZBX_TASK_STOP_SERVICE,
	ZBX_TASK_CHANGE_NODEID,
	ZBX_TASK_CONFIG_CACHE_RELOAD
}
zbx_task_t;

typedef enum
{
	HTTPTEST_AUTH_NONE = 0,
	HTTPTEST_AUTH_BASIC,
	HTTPTEST_AUTH_NTLM
} zbx_httptest_auth_t;

#define ZBX_TASK_FLAG_MULTIPLE_AGENTS 0x01

typedef struct
{
	zbx_task_t	task;
	int		flags;
}
ZBX_TASK_EX;

char	*string_replace(const char *str, const char *sub_str1, const char *sub_str2);

int	is_double_suffix(const char *str);
int	is_double(const char *c);
int	is_uint_suffix(const char *c, unsigned int *value);
int	is_uint(const char *c);
int	is_int_prefix(const char *c);
#define is_uint64(src, value)	is_uint64_n(src, ZBX_MAX_UINT64_LEN, value)
int	is_uint64_n(const char *str, size_t n, zbx_uint64_t *value);
int	is_ushort(const char *str, unsigned short *value);
int	is_boolean(const char *str, zbx_uint64_t *value);
int	is_uoct(const char *str);
int	is_uhex(const char *str);
int	is_hex_string(const char *str);
int	is_ascii_string(const char *str);
int	zbx_rtrim(char *str, const char *charlist);
void	zbx_ltrim(char *str, const char *charlist);
void	zbx_remove_chars(register char *str, const char *charlist);
#define ZBX_WHITESPACE			" \t\r\n"
#define zbx_remove_spaces(str)		zbx_remove_chars(str, " ")
#define zbx_remove_whitespace(str)	zbx_remove_chars(str, ZBX_WHITESPACE)
void	compress_signs(char *str);
void	ltrim_spaces(char *c);
void	rtrim_spaces(char *c);
void	lrtrim_spaces(char *c);
void	del_zeroes(char *s);
int	get_param(const char *param, int num, char *buf, size_t max_len);
int	num_param(const char *param);
char	*get_param_dyn(const char *param, int num);
void	remove_param(char *param, int num);
const char	*get_string(const char *p, char *buf, size_t bufsize);
int	get_key_param(char *param, int num, char *buf, size_t max_len);
int	num_key_param(char *param);
size_t	zbx_get_escape_string_len(const char *src, const char *charlist);
char	*zbx_dyn_escape_string(const char *src, const char *charlist);
int	calculate_item_nextcheck(zbx_uint64_t interfaceid, zbx_uint64_t itemid, int item_type,
		int delay, const char *flex_intervals, time_t now, int *effective_delay);
time_t	calculate_proxy_nextcheck(zbx_uint64_t hostid, unsigned int delay, time_t now);
int	check_time_period(const char *period, time_t now);
char	zbx_num2hex(u_char c);
u_char	zbx_hex2num(char c);
size_t	zbx_binary2hex(const u_char *input, size_t ilen, char **output, size_t *olen);
size_t	zbx_hex2binary(char *io);
void	zbx_hex2octal(const char *input, char **output, int *olen);
#ifdef HAVE_POSTGRESQL
size_t	zbx_pg_escape_bytea(const u_char *input, size_t ilen, char **output, size_t *olen);
size_t	zbx_pg_unescape_bytea(u_char *io);
#endif
size_t	zbx_get_next_field(const char **line, char **output, size_t *olen, char separator);
int	str_in_list(const char *list, const char *value, char delimiter);
char	*str_linefeed(const char *src, size_t maxline, const char *delim);
void	zbx_strarr_init(char ***arr);
void	zbx_strarr_add(char ***arr, const char *entry);
void	zbx_strarr_free(char **arr);

#ifdef HAVE___VA_ARGS__
#	define zbx_setproctitle(fmt, ...) __zbx_zbx_setproctitle(ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#else
#	define zbx_setproctitle __zbx_zbx_setproctitle
#endif
void	__zbx_zbx_setproctitle(const char *fmt, ...);

#define ZBX_KIBIBYTE		1024
#define ZBX_MEBIBYTE		1048576
#define ZBX_GIBIBYTE		1073741824
#define ZBX_TEBIBYTE		__UINT64_C(1099511627776)

#define SEC_PER_MIN		60
#define SEC_PER_HOUR		3600
#define SEC_PER_DAY		86400
#define SEC_PER_WEEK		(7 * SEC_PER_DAY)
#define SEC_PER_MONTH		(30 * SEC_PER_DAY)
#define SEC_PER_YEAR		(365 * SEC_PER_DAY)
#define ZBX_JAN_1970_IN_SEC	2208988800.0	/* 1970 - 1900 in seconds */

#define ZBX_MAX_RECV_DATA_SIZE	(64 * ZBX_MEBIBYTE)

double	zbx_time();
void	zbx_timespec(zbx_timespec_t *ts);
double	zbx_current_time();

#ifdef HAVE___VA_ARGS__
#	define zbx_error(fmt, ...) __zbx_zbx_error(ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#	define zbx_snprintf(str, count, fmt, ...) __zbx_zbx_snprintf(str, count, ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#	define zbx_snprintf_alloc(str, alloc_len, offset, fmt, ...) \
       			__zbx_zbx_snprintf_alloc(str, alloc_len, offset, ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#else
#	define zbx_error __zbx_zbx_error
#	define zbx_snprintf __zbx_zbx_snprintf
#	define zbx_snprintf_alloc __zbx_zbx_snprintf_alloc
#endif
void	__zbx_zbx_error(const char *fmt, ...);
size_t	__zbx_zbx_snprintf(char *str, size_t count, const char *fmt, ...);
void	__zbx_zbx_snprintf_alloc(char **str, size_t *alloc_len, size_t *offset, const char *fmt, ...);

size_t	zbx_vsnprintf(char *str, size_t count, const char *fmt, va_list args);

void	zbx_strncpy_alloc(char **str, size_t *alloc_len, size_t *offset, const char *src, size_t n);
void	zbx_strcpy_alloc(char **str, size_t *alloc_len, size_t *offset, const char *src);
void	zbx_chrcpy_alloc(char **str, size_t *alloc_len, size_t *offset, char c);

/* secure string copy */
#define strscpy(x, y)	zbx_strlcpy(x, y, sizeof(x))
#define strscat(x, y)	zbx_strlcat(x, y, sizeof(x))
size_t	zbx_strlcpy(char *dst, const char *src, size_t siz);
size_t	zbx_strlcat(char *dst, const char *src, size_t siz);

char	*zbx_dvsprintf(char *dest, const char *f, va_list args);

#ifdef HAVE___VA_ARGS__
#	define zbx_dsprintf(dest, fmt, ...) __zbx_zbx_dsprintf(dest, ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#	define zbx_strdcatf(dest, fmt, ...) __zbx_zbx_strdcatf(dest, ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#else
#	define zbx_dsprintf __zbx_zbx_dsprintf
#	define zbx_strdcatf __zbx_zbx_strdcatf
#endif
char	*__zbx_zbx_dsprintf(char *dest, const char *f, ...);
char	*zbx_strdcat(char *dest, const char *src);
char	*__zbx_zbx_strdcatf(char *dest, const char *f, ...);

int	xml_get_data_dyn(const char *xml, const char *tag, char **data);
void	xml_free_data_dyn(char **data);

int	comms_parse_response(char *xml, char *host, size_t host_len, char *key, size_t key_len,
		char *data, size_t data_len, char *lastlogsize, size_t lastlogsize_len,
		char *timestamp, size_t timestamp_len, char *source, size_t source_len,
		char *severity, size_t severity_len);

#define ZBX_COMMAND_ERROR		0
#define ZBX_COMMAND_WITHOUT_PARAMS	1
#define ZBX_COMMAND_WITH_PARAMS		2
int 	parse_command(const char *command, char *cmd, size_t cmd_max_len, char *param, size_t param_max_len);

typedef struct
{
	char			*name;
	char			*expression;
	int			expression_type;
	char			exp_delimiter;
	zbx_case_sensitive_t	case_sensitive;
}
ZBX_REGEXP;

/* regular expressions */
char    *zbx_regexp_match(const char *string, const char *pattern, int *len);
char    *zbx_iregexp_match(const char *string, const char *pattern, int *len);

void	clean_regexps_ex(ZBX_REGEXP *regexps, int *regexps_num);
void	add_regexp_ex(ZBX_REGEXP **regexps, int *regexps_alloc, int *regexps_num,
		const char *name, const char *expression, int expression_type, char exp_delimiter, int case_sensitive);
int	regexp_match_ex(ZBX_REGEXP *regexps, int regexps_num, const char *string, const char *pattern,
		zbx_case_sensitive_t cs);

/* misc functions */
#ifdef HAVE_IPV6
int	is_ip6(const char *ip);
#endif
int	is_ip4(const char *ip);

void	zbx_on_exit(); /* calls exit() at the end! */

int	get_nodeid_by_id(zbx_uint64_t id);

int	int_in_list(char *list, int value);
int	uint64_in_list(char *list, zbx_uint64_t value);
int	ip_in_list(char *list, char *ip);

int	expand_ipv6(const char *ip, char *str, size_t str_len);
#ifdef HAVE_IPV6
char	*collapse_ipv6(char *str, size_t str_len);
#endif

/* time related functions */
double	time_diff(struct timeval *from, struct timeval *to);
char	*zbx_age2str(int age);
char	*zbx_date2str(time_t date);
char	*zbx_time2str(time_t time);

char	*zbx_strcasestr(const char *haystack, const char *needle);
int	zbx_mismatch(const char *s1, const char *s2);
int	starts_with(const char *str, const char *prefix);
int	cmp_key_id(const char *key_1, const char *key_2);

int	get_nearestindex(void *p, size_t sz, int num, zbx_uint64_t id);
int	uint64_array_add(zbx_uint64_t **values, int *alloc, int *num, zbx_uint64_t value, int alloc_step);
void	uint64_array_merge(zbx_uint64_t **values, int *alloc, int *num, zbx_uint64_t *value, int value_num, int alloc_step);
int	uint64_array_exists(zbx_uint64_t *values, int num, zbx_uint64_t value);
void	uint64_array_remove(zbx_uint64_t *values, int *num, zbx_uint64_t *rm_values, int rm_num);
void	uint64_array_remove_both(zbx_uint64_t *values, int *num, zbx_uint64_t *rm_values, int *rm_num);

#ifdef _WINDOWS
LPTSTR	zbx_acp_to_unicode(LPCSTR acp_string);
int	zbx_acp_to_unicode_static(LPCSTR acp_string, LPTSTR wide_string, int wide_size);
LPTSTR	zbx_utf8_to_unicode(LPCSTR utf8_string);
LPSTR	zbx_unicode_to_utf8(LPCTSTR wide_string);
LPSTR	zbx_unicode_to_utf8_static(LPCTSTR wide_string, LPSTR utf8_string, int utf8_size);
int	_wis_uint(LPCTSTR wide_string);
#endif
void	zbx_strlower(char *str);
void	zbx_strupper(char *str);
#if defined(_WINDOWS) || defined(HAVE_ICONV)
char	*convert_to_utf8(char *in, size_t in_size, const char *encoding);
#endif	/* HAVE_ICONV */
size_t	zbx_utf8_char_len(const char *text);
size_t	zbx_strlen_utf8(const char *text);
size_t	zbx_strlen_utf8_n(const char *text, size_t utf8_maxlen);

#define ZBX_UTF8_REPLACE_CHAR	'?'
char	*zbx_replace_utf8(const char *text);
void	zbx_replace_invalid_utf8(char *text);

void	dos2unix(char *str);
int	str2uint64(const char *str, const char *suffixes, zbx_uint64_t *value);
double	str2double(const char *str);

#if defined(_WINDOWS) && defined(_UNICODE)
int	__zbx_stat(const char *path, struct stat *buf);
int	__zbx_open(const char *pathname, int flags);
#endif	/* _WINDOWS && _UNICODE */
int	zbx_read(int fd, char *buf, size_t count, const char *encoding);
int	zbx_is_regular_file(const char *path);

int	MAIN_ZABBIX_ENTRY();

zbx_uint64_t	zbx_letoh_uint64(zbx_uint64_t data);
zbx_uint64_t	zbx_htole_uint64(zbx_uint64_t data);

int	zbx_check_hostname(const char *hostname);

int	is_hostname_char(char c);
int	is_key_char(char c);
int	is_function_char(char c);
int	is_macro_char(char c);

int	is_time_function(const char *func);

int	parse_host(char **exp, char **host);
int	parse_key(char **exp, char **key);
int	parse_function(char **exp, char **func, char **params);

int	parse_host_key(char *exp, char **host, char **key);

void	make_hostname(char *host);

unsigned char	get_interface_type_by_item_type(unsigned char type);

int	calculate_sleeptime(int nextcheck, int max_sleeptime);

void	zbx_replace_string(char **data, size_t l, size_t *r, const char *value);

void	zbx_trim_str_list(char *list, char delimiter);

#endif
