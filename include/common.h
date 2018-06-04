/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
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

#ifdef strncasecmp
#	undef strncasecmp
#endif
#define strncasecmp	ERROR_DO_NOT_USE_STRNCASECMP_FUNCTION_TRY_TO_USE_ZBX_STRNCASECMP

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
#define	CONFIG_ERROR	-7

#define SUCCEED_OR_FAIL(result) (FAIL != (result) ? SUCCEED : FAIL)
const char	*zbx_sysinfo_ret_string(int ret);
const char	*zbx_result_string(int result);

#define MAX_ID_LEN		21
#define MAX_STRING_LEN		2048
#define MAX_BUFFER_LEN		65536
#define MAX_ZBX_HOSTNAME_LEN	128
#define MAX_ZBX_DNSNAME_LEN	255	/* maximum host DNS name length from RFC 1035 (without terminating '\0') */
#define MAX_EXECUTE_OUTPUT_LEN	(512 * ZBX_KIBIBYTE)

#define ZBX_MAX_UINT64		(~__UINT64_C(0))
#define ZBX_MAX_UINT64_LEN	21

/******************************************************************************
 *                                                                            *
 * Macro: ZBX_UNUSED                                                          *
 *                                                                            *
 * Purpose: silences compiler warning about unused function parameter         *
 *                                                                            *
 * Parameters:                                                                *
 *      var       - [IN] the unused parameter                                 *
 *                                                                            *
 * Comments: Use only on unused, non-volatile function parameters!            *
 *                                                                            *
 ******************************************************************************/
#define ZBX_UNUSED(var) (void)(var)

typedef struct
{
	int	sec;	/* seconds */
	int	ns;	/* nanoseconds */
}
zbx_timespec_t;

/* time zone offset */
typedef struct
{
	char	tz_sign;	/* '+' or '-' */
	int	tz_hour;
	int	tz_min;
}
zbx_timezone_t;

#define zbx_timespec_compare(t1, t2)	\
	((t1)->sec == (t2)->sec ? (t1)->ns - (t2)->ns : (t1)->sec - (t2)->sec)

#define ZBX_DOUBLE_EPSILON	0.000001
int	zbx_double_compare(double a, double b);

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
	ITEM_TYPE_SNMPTRAP,
	ITEM_TYPE_DEPENDENT,
	ITEM_TYPE_HTTPAGENT	/* 19 */
}
zbx_item_type_t;
const char	*zbx_agent_type_string(zbx_item_type_t item_type);

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

#define SNMP_BULK_DISABLED	0
#define SNMP_BULK_ENABLED	1

#define ZBX_FLAG_DISCOVERY_NORMAL	0x00
#define ZBX_FLAG_DISCOVERY_RULE		0x01
#define ZBX_FLAG_DISCOVERY_PROTOTYPE	0x02
#define ZBX_FLAG_DISCOVERY_CREATED	0x04

typedef enum
{
	ITEM_AUTHTYPE_PASSWORD = 0,
	ITEM_AUTHTYPE_PUBLICKEY
}
zbx_item_authtype_t;

/* event status */
#define EVENT_STATUS_RESOLVED		0
#define EVENT_STATUS_PROBLEM		1

/* event sources */
#define EVENT_SOURCE_TRIGGERS		0
#define EVENT_SOURCE_DISCOVERY		1
#define EVENT_SOURCE_AUTO_REGISTRATION	2
#define EVENT_SOURCE_INTERNAL		3
#define EVENT_SOURCE_COUNT		4

/* event objects */
#define EVENT_OBJECT_TRIGGER		0
#define EVENT_OBJECT_DHOST		1
#define EVENT_OBJECT_DSERVICE		2
#define EVENT_OBJECT_ZABBIX_ACTIVE	3
#define EVENT_OBJECT_ITEM		4
#define EVENT_OBJECT_LLDRULE		5

/* acknowledged flags */
#define EVENT_NOT_ACKNOWLEDGED		0
#define EVENT_ACKNOWLEDGED		1

typedef enum
{
	DOBJECT_STATUS_UP = 0,
	DOBJECT_STATUS_DOWN,
	DOBJECT_STATUS_DISCOVER,
	DOBJECT_STATUS_LOST
}
zbx_dstatus_t;

/* item value types */
typedef enum
{
	ITEM_VALUE_TYPE_FLOAT = 0,
	ITEM_VALUE_TYPE_STR,
	ITEM_VALUE_TYPE_LOG,
	ITEM_VALUE_TYPE_UINT64,
	ITEM_VALUE_TYPE_TEXT,
	/* the number of defined value types */
	ITEM_VALUE_TYPE_MAX
}
zbx_item_value_type_t;
const char	*zbx_item_value_type_string(zbx_item_value_type_t value_type);

typedef struct
{
	int	timestamp;
	int	logeventid;
	int	severity;
	char	*source;
	char	*value;
}
zbx_log_value_t;

typedef union
{
	double		dbl;
	zbx_uint64_t	ui64;
	char		*str;
	char		*err;
	zbx_log_value_t	*log;
}
history_value_t;

/* item data types */
typedef enum
{
	ITEM_DATA_TYPE_DECIMAL = 0,
	ITEM_DATA_TYPE_OCTAL,
	ITEM_DATA_TYPE_HEXADECIMAL,
	ITEM_DATA_TYPE_BOOLEAN
}
zbx_item_data_type_t;
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
}
zbx_dservice_type_t;
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
#define ITEM_STORE_AS_IS			0
#define ITEM_STORE_SPEED_PER_SECOND		1
#define ITEM_STORE_SIMPLE_CHANGE		2

/* condition evaluation types */
#define CONDITION_EVAL_TYPE_AND_OR		0
#define CONDITION_EVAL_TYPE_AND			1
#define CONDITION_EVAL_TYPE_OR			2
#define CONDITION_EVAL_TYPE_EXPRESSION		3

/* condition types */
#define CONDITION_TYPE_HOST_GROUP		0
#define CONDITION_TYPE_HOST			1
#define CONDITION_TYPE_TRIGGER			2
#define CONDITION_TYPE_TRIGGER_NAME		3
#define CONDITION_TYPE_TRIGGER_SEVERITY		4
/* #define CONDITION_TYPE_TRIGGER_VALUE		5	deprecated */
#define CONDITION_TYPE_TIME_PERIOD		6
#define CONDITION_TYPE_DHOST_IP			7
#define CONDITION_TYPE_DSERVICE_TYPE		8
#define CONDITION_TYPE_DSERVICE_PORT		9
#define CONDITION_TYPE_DSTATUS			10
#define CONDITION_TYPE_DUPTIME			11
#define CONDITION_TYPE_DVALUE			12
#define CONDITION_TYPE_HOST_TEMPLATE		13
#define CONDITION_TYPE_EVENT_ACKNOWLEDGED	14
#define CONDITION_TYPE_APPLICATION		15
#define CONDITION_TYPE_MAINTENANCE		16
#define CONDITION_TYPE_DRULE			18
#define CONDITION_TYPE_DCHECK			19
#define CONDITION_TYPE_PROXY			20
#define CONDITION_TYPE_DOBJECT			21
#define CONDITION_TYPE_HOST_NAME		22
#define CONDITION_TYPE_EVENT_TYPE		23
#define CONDITION_TYPE_HOST_METADATA		24
#define CONDITION_TYPE_EVENT_TAG		25
#define CONDITION_TYPE_EVENT_TAG_VALUE		26

/* condition operators */
#define CONDITION_OPERATOR_EQUAL		0
#define CONDITION_OPERATOR_NOT_EQUAL		1
#define CONDITION_OPERATOR_LIKE			2
#define CONDITION_OPERATOR_NOT_LIKE		3
#define CONDITION_OPERATOR_IN			4
#define CONDITION_OPERATOR_MORE_EQUAL		5
#define CONDITION_OPERATOR_LESS_EQUAL		6
#define CONDITION_OPERATOR_NOT_IN		7
#define CONDITION_OPERATOR_REGEXP		8
#define CONDITION_OPERATOR_NOT_REGEXP		9

/* event type action condition values */
#define EVENT_TYPE_ITEM_NOTSUPPORTED		0
/* #define EVENT_TYPE_ITEM_NORMAL		1	 deprecated */
#define EVENT_TYPE_LLDRULE_NOTSUPPORTED		2
/* #define EVENT_TYPE_LLDRULE_NORMAL		3	 deprecated */
#define EVENT_TYPE_TRIGGER_UNKNOWN		4
/* #define EVENT_TYPE_TRIGGER_NORMAL		5	 deprecated */

#define SCREEN_RESOURCE_GRAPH			0
#define SCREEN_RESOURCE_SIMPLE_GRAPH		1
#define SCREEN_RESOURCE_MAP			2
#define SCREEN_RESOURCE_PLAIN_TEXT		3
#define SCREEN_RESOURCE_HOST_INFO		4
#define SCREEN_RESOURCE_TRIGGER_INFO		5
#define SCREEN_RESOURCE_SERVER_INFO		6
#define SCREEN_RESOURCE_CLOCK			7
#define SCREEN_RESOURCE_SCREEN			8
#define SCREEN_RESOURCE_TRIGGER_OVERVIEW	9
#define SCREEN_RESOURCE_DATA_OVERVIEW		10
#define SCREEN_RESOURCE_URL			11
#define SCREEN_RESOURCE_ACTIONS			12
#define SCREEN_RESOURCE_EVENTS			13
#define SCREEN_RESOURCE_HOSTGROUP_TRIGGERS	14
#define SCREEN_RESOURCE_SYSTEM_STATUS		15
#define SCREEN_RESOURCE_HOST_TRIGGERS		16

typedef enum
{
	SYSMAP_ELEMENT_TYPE_HOST = 0,
	SYSMAP_ELEMENT_TYPE_MAP,
	SYSMAP_ELEMENT_TYPE_TRIGGER,
	SYSMAP_ELEMENT_TYPE_HOST_GROUP,
	SYSMAP_ELEMENT_TYPE_IMAGE
}
zbx_sysmap_element_types_t;

typedef enum
{
	GRAPH_YAXIS_TYPE_CALCULATED = 0,
	GRAPH_YAXIS_TYPE_FIXED,
	GRAPH_YAXIS_TYPE_ITEM_VALUE
}
zbx_graph_yaxis_types_t;

/* special item key used for ICMP pings */
#define SERVER_ICMPPING_KEY	"icmpping"
/* special item key used for ICMP ping latency */
#define SERVER_ICMPPINGSEC_KEY	"icmppingsec"
/* special item key used for ICMP ping loss packages */
#define SERVER_ICMPPINGLOSS_KEY	"icmppingloss"

/* runtime control options */
#define ZBX_CONFIG_CACHE_RELOAD	"config_cache_reload"
#define ZBX_HOUSEKEEPER_EXECUTE	"housekeeper_execute"
#define ZBX_LOG_LEVEL_INCREASE	"log_level_increase"
#define ZBX_LOG_LEVEL_DECREASE	"log_level_decrease"

/* value for not supported items */
#define ZBX_NOTSUPPORTED	"ZBX_NOTSUPPORTED"
/* the error message for not supported items when reason is unknown */
#define ZBX_NOTSUPPORTED_MSG	"Unknown error."

/* Zabbix Agent non-critical error (agents older than 2.0) */
#define ZBX_ERROR		"ZBX_ERROR"

/* media types */
typedef enum
{
	MEDIA_TYPE_EMAIL = 0,
	MEDIA_TYPE_EXEC,
	MEDIA_TYPE_SMS,
	MEDIA_TYPE_JABBER,
	MEDIA_TYPE_EZ_TEXTING = 100
}
zbx_media_type_t;

/* alert statuses */
typedef enum
{
	ALERT_STATUS_NOT_SENT = 0,
	ALERT_STATUS_SENT,
	ALERT_STATUS_FAILED,
	ALERT_STATUS_NEW
}
zbx_alert_status_t;
const char	*zbx_alert_status_string(unsigned char type, unsigned char status);

/* escalation statuses */
typedef enum
{
	ESCALATION_STATUS_ACTIVE = 0,
	ESCALATION_STATUS_RECOVERY,	/* only in server code, never in DB, deprecated */
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
}
zbx_alert_type_t;
const char	*zbx_alert_type_string(unsigned char type);

/* item statuses */
#define ITEM_STATUS_ACTIVE		0
#define ITEM_STATUS_DISABLED		1

/* item states */
#define ITEM_STATE_NORMAL		0
#define ITEM_STATE_NOTSUPPORTED		1
const char	*zbx_item_state_string(unsigned char state);

/* group statuses */
typedef enum
{
       GROUP_STATUS_ACTIVE = 0,
       GROUP_STATUS_DISABLED
}
zbx_group_status_type_t;

/* group internal flag */
#define ZBX_INTERNAL_GROUP		1

/* program type */
#define ZBX_PROGRAM_TYPE_SERVER		0x01
#define ZBX_PROGRAM_TYPE_PROXY_ACTIVE	0x02
#define ZBX_PROGRAM_TYPE_PROXY_PASSIVE	0x04
#define ZBX_PROGRAM_TYPE_PROXY		0x06	/* ZBX_PROGRAM_TYPE_PROXY_ACTIVE | ZBX_PROGRAM_TYPE_PROXY_PASSIVE */
#define ZBX_PROGRAM_TYPE_AGENTD		0x08
#define ZBX_PROGRAM_TYPE_SENDER		0x10
#define ZBX_PROGRAM_TYPE_GET		0x20
const char	*get_program_type_string(unsigned char program_type);

/* process type */
#define ZBX_PROCESS_TYPE_POLLER		0
#define ZBX_PROCESS_TYPE_UNREACHABLE	1
#define ZBX_PROCESS_TYPE_IPMIPOLLER	2
#define ZBX_PROCESS_TYPE_PINGER		3
#define ZBX_PROCESS_TYPE_JAVAPOLLER	4
#define ZBX_PROCESS_TYPE_HTTPPOLLER	5
#define ZBX_PROCESS_TYPE_TRAPPER	6
#define ZBX_PROCESS_TYPE_SNMPTRAPPER	7
#define ZBX_PROCESS_TYPE_PROXYPOLLER	8
#define ZBX_PROCESS_TYPE_ESCALATOR	9
#define ZBX_PROCESS_TYPE_HISTSYNCER	10
#define ZBX_PROCESS_TYPE_DISCOVERER	11
#define ZBX_PROCESS_TYPE_ALERTER	12
#define ZBX_PROCESS_TYPE_TIMER		13
#define ZBX_PROCESS_TYPE_HOUSEKEEPER	14
#define ZBX_PROCESS_TYPE_DATASENDER	15
#define ZBX_PROCESS_TYPE_CONFSYNCER	16
#define ZBX_PROCESS_TYPE_HEARTBEAT	17
#define ZBX_PROCESS_TYPE_SELFMON	18
#define ZBX_PROCESS_TYPE_VMWARE		19
#define ZBX_PROCESS_TYPE_COLLECTOR	20
#define ZBX_PROCESS_TYPE_LISTENER	21
#define ZBX_PROCESS_TYPE_ACTIVE_CHECKS	22
#define ZBX_PROCESS_TYPE_TASKMANAGER	23
#define ZBX_PROCESS_TYPE_IPMIMANAGER	24
#define ZBX_PROCESS_TYPE_ALERTMANAGER	25
#define ZBX_PROCESS_TYPE_PREPROCMAN	26
#define ZBX_PROCESS_TYPE_PREPROCESSOR	27
#define ZBX_PROCESS_TYPE_COUNT		28	/* number of process types */
#define ZBX_PROCESS_TYPE_UNKNOWN	255
const char	*get_process_type_string(unsigned char process_type);
int		get_process_type_by_name(const char *proc_type_str);

/* maintenance */
typedef enum
{
	TIMEPERIOD_TYPE_ONETIME = 0,
/*	TIMEPERIOD_TYPE_HOURLY,*/
	TIMEPERIOD_TYPE_DAILY = 2,
	TIMEPERIOD_TYPE_WEEKLY,
	TIMEPERIOD_TYPE_MONTHLY
}
zbx_timeperiod_type_t;

typedef enum
{
	MAINTENANCE_TYPE_NORMAL = 0,
	MAINTENANCE_TYPE_NODATA
}
zbx_maintenance_type_t;

/* regular expressions */
#define EXPRESSION_TYPE_INCLUDED	0
#define EXPRESSION_TYPE_ANY_INCLUDED	1
#define EXPRESSION_TYPE_NOT_INCLUDED	2
#define EXPRESSION_TYPE_TRUE		3
#define EXPRESSION_TYPE_FALSE		4

#define ZBX_IGNORE_CASE			0
#define ZBX_CASE_SENSITIVE		1

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
#define HOST_INVENTORY_DISABLED		-1	/* the host has no record in host_inventory */
						/* only in server code, never in DB */
#define HOST_INVENTORY_MANUAL		0
#define HOST_INVENTORY_AUTOMATIC	1

#define HOST_INVENTORY_FIELD_COUNT	70

/* host availability */
#define HOST_AVAILABLE_UNKNOWN		0
#define HOST_AVAILABLE_TRUE		1
#define HOST_AVAILABLE_FALSE		2

/* trigger statuses */
#define TRIGGER_STATUS_ENABLED		0
#define TRIGGER_STATUS_DISABLED		1

/* trigger types */
#define TRIGGER_TYPE_NORMAL		0
#define TRIGGER_TYPE_MULTIPLE_TRUE	1

/* trigger values */
#define TRIGGER_VALUE_OK		0
#define TRIGGER_VALUE_PROBLEM		1
#define TRIGGER_VALUE_UNKNOWN		2	/* only in server code, never in DB */
#define TRIGGER_VALUE_NONE		3	/* only in server code, never in DB */
const char	*zbx_trigger_value_string(unsigned char value);

/* trigger states */
#define TRIGGER_STATE_NORMAL		0
#define TRIGGER_STATE_UNKNOWN		1
const char	*zbx_trigger_state_string(unsigned char state);

/* trigger severity */
#define TRIGGER_SEVERITY_NOT_CLASSIFIED	0
#define TRIGGER_SEVERITY_INFORMATION	1
#define TRIGGER_SEVERITY_WARNING	2
#define TRIGGER_SEVERITY_AVERAGE	3
#define TRIGGER_SEVERITY_HIGH		4
#define TRIGGER_SEVERITY_DISASTER	5
#define TRIGGER_SEVERITY_COUNT		6	/* number of trigger severities */

/* trigger recovery mode */
#define TRIGGER_RECOVERY_MODE_EXPRESSION		0
#define TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION	1
#define TRIGGER_RECOVERY_MODE_NONE			2

#define ITEM_LOGTYPE_INFORMATION	1
#define ITEM_LOGTYPE_WARNING		2
#define ITEM_LOGTYPE_ERROR		4
#define ITEM_LOGTYPE_FAILURE_AUDIT	7
#define ITEM_LOGTYPE_SUCCESS_AUDIT	8
#define ITEM_LOGTYPE_CRITICAL		9
#define ITEM_LOGTYPE_VERBOSE		10
const char	*zbx_item_logtype_string(unsigned char logtype);

/* media statuses */
#define MEDIA_STATUS_ACTIVE	0
#define MEDIA_STATUS_DISABLED	1

/* action statuses */
#define ACTION_STATUS_ACTIVE	0
#define ACTION_STATUS_DISABLED	1

/* action maintenance mode */
#define ACTION_MAINTENANCE_MODE_NORMAL	0	/* ignore maintenance */
#define ACTION_MAINTENANCE_MODE_PAUSE	1	/* pause escalation while host is in maintenance */

/* max number of retries for alerts */
#define ALERT_MAX_RETRIES	3

/* media type statuses */
#define MEDIA_TYPE_STATUS_ACTIVE	0
#define MEDIA_TYPE_STATUS_DISABLED	1

/* SMTP security options */
#define SMTP_SECURITY_NONE	0
#define SMTP_SECURITY_STARTTLS	1
#define SMTP_SECURITY_SSL	2

/* SMTP authentication options */
#define SMTP_AUTHENTICATION_NONE		0
#define SMTP_AUTHENTICATION_NORMAL_PASSWORD	1

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
#define OPERATION_TYPE_HOST_INVENTORY	10
#define OPERATION_TYPE_RECOVERY_MESSAGE	11
#define OPERATION_TYPE_ACK_MESSAGE	12

/* normal and recovery operations */
#define ZBX_OPERATION_MODE_NORMAL	0
#define ZBX_OPERATION_MODE_RECOVERY	1
#define ZBX_OPERATION_MODE_ACK		2

/* algorithms for service status calculation */
#define SERVICE_ALGORITHM_NONE	0
#define SERVICE_ALGORITHM_MAX	1
#define SERVICE_ALGORITHM_MIN	2

/* HTTP item types */
#define ZBX_HTTPITEM_TYPE_RSPCODE	0
#define ZBX_HTTPITEM_TYPE_TIME		1
#define ZBX_HTTPITEM_TYPE_SPEED		2
#define ZBX_HTTPITEM_TYPE_LASTSTEP	3
#define ZBX_HTTPITEM_TYPE_LASTERROR	4

/* proxy_history flags */
#define PROXY_HISTORY_FLAG_META		0x01
#define PROXY_HISTORY_FLAG_NOVALUE	0x02

/* global correlation constants */
#define ZBX_CORRELATION_ENABLED				0
#define ZBX_CORRELATION_DISABLED			1

#define ZBX_CORR_CONDITION_OLD_EVENT_TAG		0
#define ZBX_CORR_CONDITION_NEW_EVENT_TAG		1
#define ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP		2
#define ZBX_CORR_CONDITION_EVENT_TAG_PAIR		3
#define ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE		4
#define ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE		5

#define ZBX_CORR_OPERATION_CLOSE_OLD			0
#define ZBX_CORR_OPERATION_CLOSE_NEW			1

/* trigger correlation modes */
#define ZBX_TRIGGER_CORRELATION_NONE	0
#define ZBX_TRIGGER_CORRELATION_TAG	1

/* acknowledgement actions (flags) */
#define ZBX_ACKNOWLEDGE_ACTION_NONE		0x0000
#define ZBX_ACKNOWLEDGE_ACTION_CLOSE_PROBLEM	0x0001

#define ZBX_USER_ONLINE_TIME	600

/* user permissions */
typedef enum
{
	USER_TYPE_ZABBIX_USER = 1,
	USER_TYPE_ZABBIX_ADMIN,
	USER_TYPE_SUPER_ADMIN
}
zbx_user_type_t;

typedef struct
{
	zbx_uint64_t	userid;
	zbx_user_type_t	type;
}
zbx_user_t;

typedef enum
{
	PERM_DENY = 0,
	PERM_READ = 2,
	PERM_READ_WRITE
}
zbx_user_permission_t;

const char	*zbx_permission_string(int perm);

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
	unsigned char	host_access;
}
zbx_script_t;

#define ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT	0
#define ZBX_SCRIPT_TYPE_IPMI		1
#define ZBX_SCRIPT_TYPE_SSH		2
#define ZBX_SCRIPT_TYPE_TELNET		3
#define ZBX_SCRIPT_TYPE_GLOBAL_SCRIPT	4

#define ZBX_SCRIPT_EXECUTE_ON_AGENT	0
#define ZBX_SCRIPT_EXECUTE_ON_SERVER	1
#define ZBX_SCRIPT_EXECUTE_ON_PROXY	2	/* fall back to execution on server if target not monitored by proxy */

#define POLLER_DELAY		5
#define DISCOVERER_DELAY	60

#define HOUSEKEEPER_STARTUP_DELAY	30	/* in minutes */

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

void	*zbx_calloc2(const char *filename, int line, void *old, size_t nmemb, size_t size);
void	*zbx_malloc2(const char *filename, int line, void *old, size_t size);
void	*zbx_realloc2(const char *filename, int line, void *old, size_t size);
char	*zbx_strdup2(const char *filename, int line, char *old, const char *str);

void	*zbx_guaranteed_memset(void *v, int c, size_t n);

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
extern const char	syslog_app_name[];
extern const char	*usage_message[];
extern const char	*help_message[];

#define ARRSIZE(a)	(sizeof(a) / sizeof(*a))

void	help(void);
void	usage(void);
void	version(void);

const char	*get_program_name(const char *path);

typedef enum
{
	ZBX_TASK_START = 0,
	ZBX_TASK_PRINT_SUPPORTED,
	ZBX_TASK_TEST_METRIC,
	ZBX_TASK_SHOW_USAGE,
	ZBX_TASK_SHOW_VERSION,
	ZBX_TASK_SHOW_HELP,
#ifdef _WINDOWS
	ZBX_TASK_INSTALL_SERVICE,
	ZBX_TASK_UNINSTALL_SERVICE,
	ZBX_TASK_START_SERVICE,
	ZBX_TASK_STOP_SERVICE
#else
	ZBX_TASK_RUNTIME_CONTROL
#endif
}
zbx_task_t;

#define ZBX_RTC_LOG_LEVEL_INCREASE	1
#define ZBX_RTC_LOG_LEVEL_DECREASE	2
#define ZBX_RTC_HOUSEKEEPER_EXECUTE	3
#define ZBX_RTC_CONFIG_CACHE_RELOAD	8

typedef enum
{
	HTTPTEST_AUTH_NONE = 0,
	HTTPTEST_AUTH_BASIC,
	HTTPTEST_AUTH_NTLM
}
zbx_httptest_auth_t;

#define ZBX_TASK_FLAG_MULTIPLE_AGENTS 0x01
#define ZBX_TASK_FLAG_FOREGROUND      0x02

typedef struct
{
	zbx_task_t	task;
	int		flags;
	int		data;
}
ZBX_TASK_EX;

#define ZBX_RTC_MSG_SHIFT	0
#define ZBX_RTC_SCOPE_SHIFT	8
#define ZBX_RTC_DATA_SHIFT	16

#define ZBX_RTC_MSG_MASK	0x000000ff
#define ZBX_RTC_SCOPE_MASK	0x0000ff00
#define ZBX_RTC_DATA_MASK	0xffff0000

#define ZBX_RTC_GET_MSG(task)	(int)((task & ZBX_RTC_MSG_MASK) >> ZBX_RTC_MSG_SHIFT)
#define ZBX_RTC_GET_SCOPE(task)	(int)((task & ZBX_RTC_SCOPE_MASK) >> ZBX_RTC_SCOPE_SHIFT)
#define ZBX_RTC_GET_DATA(task)	(int)((task & ZBX_RTC_DATA_MASK) >> ZBX_RTC_DATA_SHIFT)

#define ZBX_RTC_MAKE_MESSAGE(msg, scope, data)	((msg << ZBX_RTC_MSG_SHIFT) | (scope << ZBX_RTC_SCOPE_SHIFT) | \
	(data << ZBX_RTC_DATA_SHIFT))

char	*string_replace(const char *str, const char *sub_str1, const char *sub_str2);

#define ZBX_FLAG_DOUBLE_PLAIN	0x00
#define ZBX_FLAG_DOUBLE_SUFFIX	0x01
int	is_double_suffix(const char *str, unsigned char flags);
int	is_double(const char *c);
#define ZBX_LENGTH_UNLIMITED	0x7fffffff
int	is_time_suffix(const char *c, int *value, int length);
int	is_int_prefix(const char *c);
int	is_uint_n_range(const char *str, size_t n, void *value, size_t size, zbx_uint64_t min, zbx_uint64_t max);
int	is_hex_n_range(const char *str, size_t n, void *value, size_t size, zbx_uint64_t min, zbx_uint64_t max);

unsigned char	zbx_time2bool(const char *value_raw);

#define ZBX_SIZE_T_MAX	(~(size_t)0)

#define is_ushort(str, value) \
	is_uint_n_range(str, ZBX_SIZE_T_MAX, value, sizeof(unsigned short), 0x0, 0xFFFF)

#define is_uint32(str, value) \
	is_uint_n_range(str, ZBX_SIZE_T_MAX, value, 4, 0x0, 0xFFFFFFFF)

#define is_uint64(str, value) \
	is_uint_n_range(str, ZBX_SIZE_T_MAX, value, 8, 0x0, __UINT64_C(0xFFFFFFFFFFFFFFFF))

#define is_uint64_n(str, n, value) \
	is_uint_n_range(str, n, value, 8, 0x0, __UINT64_C(0xFFFFFFFFFFFFFFFF))

#define is_uint31(str, value) \
	is_uint_n_range(str, ZBX_SIZE_T_MAX, value, 4, 0x0, 0x7FFFFFFF)

#define is_uint31_1(str, value) \
	is_uint_n_range(str, ZBX_SIZE_T_MAX, value, 4, 0x0, 0x7FFFFFFE)

#define is_uint_range(str, value, min, max) \
	is_uint_n_range(str, ZBX_SIZE_T_MAX, value, sizeof(unsigned int), min, max)

int	is_boolean(const char *str, zbx_uint64_t *value);
int	is_uoct(const char *str);
int	is_uhex(const char *str);
int	is_hex_string(const char *str);
int	is_ascii_string(const char *str);
int	zbx_rtrim(char *str, const char *charlist);
void	zbx_ltrim(char *str, const char *charlist);
void	zbx_lrtrim(char *str, const char *charlist);
void	zbx_remove_chars(char *str, const char *charlist);
#define ZBX_WHITESPACE			" \t\r\n"
#define zbx_remove_whitespace(str)	zbx_remove_chars(str, ZBX_WHITESPACE)
void	del_zeroes(char *s);
int	get_param(const char *param, int num, char *buf, size_t max_len);
int	num_param(const char *param);
char	*get_param_dyn(const char *param, int num);

/******************************************************************************
 *                                                                            *
 * Purpose: replaces an item key, SNMP OID or their parameters                *
 *                                                                            *
 * Parameters:                                                                *
 *      data      - [IN] an item key, SNMP OID or their parameter             *
 *      key_type  - [IN] ZBX_KEY_TYPE_*                                       *
 *      level     - [IN] for item keys and OIDs the level will be 0;          *
 *                       for their parameters - 1 or higher (for arrays)      *
 *      num       - [IN] parameter number; for item keys and OIDs the level   *
 *                       will be 0; for their parameters - 1 or higher        *
 *      quoted    - [IN] 1 if parameter is quoted; 0 - otherwise              *
 *      cb_data   - [IN] callback function custom data                        *
 *      param     - [OUT] replaced item key string                            *
 *                                                                            *
 * Return value: SUCEED - if parameter doesn't change or has been changed     *
 *                        successfully                                        *
 *               FAIL   - otherwise                                           *
 *                                                                            *
 * Comments: The new string should be quoted if it contains special           *
 *           characters                                                       *
 *                                                                            *
 ******************************************************************************/
typedef int	(*replace_key_param_f)(const char *data, int key_type, int level, int num, int quoted, void *cb_data,
		char **param);
#define ZBX_KEY_TYPE_ITEM	0
#define ZBX_KEY_TYPE_OID	1
int	replace_key_params_dyn(char **data, int key_type, replace_key_param_f cb, void *cb_data, char *error,
		size_t maxerrlen);

void	remove_param(char *param, int num);
int	get_key_param(char *param, int num, char *buf, size_t max_len);
int	num_key_param(char *param);
size_t	zbx_get_escape_string_len(const char *src, const char *charlist);
char	*zbx_dyn_escape_string(const char *src, const char *charlist);

typedef struct zbx_custom_interval	zbx_custom_interval_t;
int	zbx_interval_preproc(const char *interval_str, int *simple_interval,
		zbx_custom_interval_t **custom_intervals, char **error);
void	zbx_custom_interval_free(zbx_custom_interval_t *custom_intervals);
int	calculate_item_nextcheck(zbx_uint64_t seed, int item_type, int simple_interval,
		const zbx_custom_interval_t *custom_intervals, time_t now);
time_t	calculate_proxy_nextcheck(zbx_uint64_t hostid, unsigned int delay, time_t now);
int	zbx_check_time_period(const char *period, time_t time, int *res);
void	zbx_hex2octal(const char *input, char **output, int *olen);
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
void	__zbx_zbx_setproctitle(const char *fmt, ...)
#if defined(__GNUC__) || defined(__clang__)
	__attribute__((__format__(__printf__, 1, 2)));
#else
	;
#endif

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
#define ZBX_JAN_2038		2145916800
#define ZBX_JAN_1970_IN_SEC	2208988800.0	/* 1970 - 1900 in seconds */

#define ZBX_MAX_RECV_DATA_SIZE	(128 * ZBX_MEBIBYTE)

/* max length of base64 data */
#define ZBX_MAX_B64_LEN		(16 * ZBX_KIBIBYTE)

double	zbx_time(void);
void	zbx_timespec(zbx_timespec_t *ts);
double	zbx_current_time(void);
void	zbx_get_time(struct tm *tm, long *milliseconds, zbx_timezone_t *tz);
int	zbx_utc_time(int year, int mon, int mday, int hour, int min, int sec, int *t);
int	zbx_day_in_month(int year, int mon);

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

size_t	__zbx_zbx_snprintf(char *str, size_t count, const char *fmt, ...)
#if defined(__GNUC__) || defined(__clang__)
	__attribute__((__format__(__printf__, 3, 4)));
#else
	;
#endif

void	__zbx_zbx_snprintf_alloc(char **str, size_t *alloc_len, size_t *offset, const char *fmt, ...);

size_t	zbx_vsnprintf(char *str, size_t count, const char *fmt, va_list args);

void	zbx_strncpy_alloc(char **str, size_t *alloc_len, size_t *offset, const char *src, size_t n);
void	zbx_strcpy_alloc(char **str, size_t *alloc_len, size_t *offset, const char *src);
void	zbx_chrcpy_alloc(char **str, size_t *alloc_len, size_t *offset, char c);
void	zbx_str_memcpy_alloc(char **str, size_t *alloc_len, size_t *offset, const char *src, size_t n);

/* secure string copy */
#define strscpy(x, y)	zbx_strlcpy(x, y, sizeof(x))
#define strscat(x, y)	zbx_strlcat(x, y, sizeof(x))
size_t	zbx_strlcpy(char *dst, const char *src, size_t siz);
void	zbx_strlcat(char *dst, const char *src, size_t siz);
size_t	zbx_strlcpy_utf8(char *dst, const char *src, size_t size);

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
char	*xml_escape_dyn(const char *data);
void	xml_escape_xpath(char **data);

int	comms_parse_response(char *xml, char *host, size_t host_len, char *key, size_t key_len,
		char *data, size_t data_len, char *lastlogsize, size_t lastlogsize_len,
		char *timestamp, size_t timestamp_len, char *source, size_t source_len,
		char *severity, size_t severity_len);

/* misc functions */
int	is_ip6(const char *ip);
int	is_ip4(const char *ip);
int	is_supported_ip(const char *ip);
int	is_ip(const char *ip);

int	zbx_validate_hostname(const char *hostname);

void	zbx_on_exit(void); /* calls exit() at the end! */

int	int_in_list(char *list, int value);
int	ip_in_list(const char *list, const char *ip);

/* IP range support */
#define ZBX_IPRANGE_V4	0
#define ZBX_IPRANGE_V6	1

#define ZBX_IPRANGE_GROUPS_V4	4
#define ZBX_IPRANGE_GROUPS_V6	8

typedef struct
{
	int	from;
	int	to;
}
zbx_range_t;

typedef struct
{
	/* contains groups of ranges for either ZBX_IPRANGE_V4 or ZBX_IPRANGE_V46 */
	/* ex. 127-127.0-0.0-0.2-254 (from-to.from-to.from-to.from-to)            */
	/*                                  0       1       2       3             */
	zbx_range_t	range[ZBX_IPRANGE_GROUPS_V6];

	/* range type - ZBX_IPRANGE_V4 or ZBX_IPRANGE_V6 */
	unsigned char	type;

	/* 1 if the range was defined with network mask, 0 otherwise */
	unsigned char   mask;
}
zbx_iprange_t;

int	iprange_parse(zbx_iprange_t *range, const char *address);
void	iprange_first(const zbx_iprange_t *range, int *address);
int	iprange_next(const zbx_iprange_t *range, int *address);
int	iprange_validate(const zbx_iprange_t *range, const int *address);
zbx_uint64_t	iprange_volume(const zbx_iprange_t *range);

/* time related functions */
char	*zbx_age2str(int age);
char	*zbx_date2str(time_t date);
char	*zbx_time2str(time_t time);

#define ZBX_NULL2STR(str)	(NULL != str ? str : "(null)")
#define ZBX_NULL2EMPTY_STR(str)	(NULL != (str) ? (str) : "")

char	*zbx_strcasestr(const char *haystack, const char *needle);
int	cmp_key_id(const char *key_1, const char *key_2);
int	zbx_strncasecmp(const char *s1, const char *s2, size_t n);

int	get_nearestindex(const void *p, size_t sz, int num, zbx_uint64_t id);
int	uint64_array_add(zbx_uint64_t **values, int *alloc, int *num, zbx_uint64_t value, int alloc_step);
int	uint64_array_exists(const zbx_uint64_t *values, int num, zbx_uint64_t value);
void	uint64_array_remove(zbx_uint64_t *values, int *num, const zbx_uint64_t *rm_values, int rm_num);

const char	*zbx_event_value_string(unsigned char source, unsigned char object, unsigned char value);

#ifdef _WINDOWS
const OSVERSIONINFOEX	*zbx_win_getversion(void);
void	zbx_wmi_get(const char *wmi_namespace, const char *wmi_query, char **utf8_value);
wchar_t	*zbx_acp_to_unicode(const char *acp_string);
wchar_t	*zbx_oemcp_to_unicode(const char *oemcp_string);
int	zbx_acp_to_unicode_static(const char *acp_string, wchar_t *wide_string, int wide_size);
wchar_t	*zbx_utf8_to_unicode(const char *utf8_string);
char	*zbx_unicode_to_utf8(const wchar_t *wide_string);
char	*zbx_unicode_to_utf8_static(const wchar_t *wide_string, char *utf8_string, int utf8_size);
int	_wis_uint(const wchar_t *wide_string);
#endif
void	zbx_strlower(char *str);
void	zbx_strupper(char *str);
#if defined(_WINDOWS) || defined(HAVE_ICONV)
char	*convert_to_utf8(char *in, size_t in_size, const char *encoding);
#endif	/* HAVE_ICONV */
size_t	zbx_utf8_char_len(const char *text);
size_t	zbx_strlen_utf8(const char *text);
size_t	zbx_strlen_utf8_nchars(const char *text, size_t utf8_maxlen);
size_t	zbx_strlen_utf8_nbytes(const char *text, size_t maxlen);

int	zbx_is_utf8(const char *text);
#define ZBX_UTF8_REPLACE_CHAR	'?'
char	*zbx_replace_utf8(const char *text);
void	zbx_replace_invalid_utf8(char *text);

void	dos2unix(char *str);
int	str2uint64(const char *str, const char *suffixes, zbx_uint64_t *value);
double	str2double(const char *str);

/* time and memory size suffixes */
#define ZBX_UNIT_SYMBOLS	"KMGTsmhdw"
zbx_uint64_t	suffix2factor(char c);

#if defined(_WINDOWS)
typedef struct __stat64	zbx_stat_t;
int	__zbx_stat(const char *path, zbx_stat_t *buf);
int	__zbx_open(const char *pathname, int flags);
#else
typedef struct stat	zbx_stat_t;
#endif	/* _WINDOWS */

void	find_cr_lf_szbyte(const char *encoding, const char **cr, const char **lf, size_t *szbyte);
int	zbx_read(int fd, char *buf, size_t count, const char *encoding);
int	zbx_is_regular_file(const char *path);

int	MAIN_ZABBIX_ENTRY(int flags);

zbx_uint64_t	zbx_letoh_uint64(zbx_uint64_t data);
zbx_uint64_t	zbx_htole_uint64(zbx_uint64_t data);

zbx_uint32_t	zbx_letoh_uint32(zbx_uint32_t data);
zbx_uint32_t	zbx_htole_uint32(zbx_uint32_t data);

int	zbx_check_hostname(const char *hostname, char **error);

int	is_hostname_char(unsigned char c);
int	is_key_char(unsigned char c);
int	is_function_char(unsigned char c);
int	is_macro_char(unsigned char c);

int	is_discovery_macro(const char *name);

int	is_time_function(const char *func);
int	is_snmp_type(unsigned char type);

int	parse_key(const char **exp);

int	parse_host_key(char *exp, char **host, char **key);

void	make_hostname(char *host);

int	zbx_number_parse(const char *number, int *len);

unsigned char	get_interface_type_by_item_type(unsigned char type);

int	calculate_sleeptime(int nextcheck, int max_sleeptime);

void	zbx_replace_string(char **data, size_t l, size_t *r, const char *value);
int	zbx_replace_mem_dyn(char **data, size_t *data_alloc, size_t *data_len, size_t offset, size_t sz_to,
		const char *from, size_t sz_from);

void	zbx_trim_str_list(char *list, char delimiter);

int	parse_serveractive_element(char *str, char **host, unsigned short *port, unsigned short port_default);

int	zbx_strcmp_null(const char *s1, const char *s2);

int	zbx_user_macro_parse(const char *macro, int *macro_r, int *context_l, int *context_r);
int	zbx_user_macro_parse_dyn(const char *macro, char **name, char **context, int *length);
char	*zbx_user_macro_unquote_context_dyn(const char *context, int len);
char	*zbx_user_macro_quote_context_dyn(const char *context, int force_quote);

#define ZBX_SESSION_ACTIVE	0
#define ZBX_SESSION_PASSIVE	1

char	*zbx_dyn_escape_shell_single_quote(const char *text);

#define ZBX_DO_NOT_SEND_RESPONSE	0
#define ZBX_SEND_RESPONSE		1

/* Do not forget to synchronize HOST_TLS_* definitions with DB schema ! */
#define HOST_TLS_ISSUER_LEN		4096				/* for up to 1024 UTF-8 characters */
#define HOST_TLS_ISSUER_LEN_MAX		(HOST_TLS_ISSUER_LEN + 1)
#define HOST_TLS_SUBJECT_LEN		4096				/* for up to 1024 UTF-8 characters */
#define HOST_TLS_SUBJECT_LEN_MAX	(HOST_TLS_SUBJECT_LEN + 1)
#define HOST_TLS_PSK_IDENTITY_LEN	512				/* for up to 128 UTF-8 characters */
#define HOST_TLS_PSK_IDENTITY_LEN_MAX	(HOST_TLS_PSK_IDENTITY_LEN + 1)
#define HOST_TLS_PSK_LEN		512				/* for up to 256 hex-encoded bytes (ASCII) */
#define HOST_TLS_PSK_LEN_MAX		(HOST_TLS_PSK_LEN + 1)
#define HOST_TLS_PSK_LEN_MIN		32				/* for 16 hex-encoded bytes (128-bit PSK) */

void	zbx_function_param_parse(const char *expr, size_t *param_pos, size_t *length, size_t *sep_pos);
char	*zbx_function_param_unquote_dyn(const char *param, size_t len, int *quoted);
int	zbx_function_param_quote(char **param, int forced);
int	zbx_function_validate_parameters(const char *expr, size_t *length);
int	zbx_function_find(const char *expr, size_t *func_pos, size_t *par_l, size_t *par_r,
		char *error, int max_error_len);

void	zbx_alarm_flag_set(void);
void	zbx_alarm_flag_clear(void);

#ifndef _WINDOWS
unsigned int	zbx_alarm_on(unsigned int seconds);
unsigned int	zbx_alarm_off(void);
#if defined(HAVE_RESOLV_H)
void	zbx_update_resolver_conf(void);		/* handle /etc/resolv.conf update */
#endif
#endif

int	zbx_alarm_timed_out(void);

#define zbx_bsearch(key, base, nmemb, size, compar)	(0 == (nmemb) ? NULL : bsearch(key, base, nmemb, size, compar))

int	zbx_strcmp_natural(const char *s1, const char *s2);

/* tokens used in expressions */
#define ZBX_TOKEN_OBJECTID	0x00001
#define ZBX_TOKEN_MACRO		0x00002
#define ZBX_TOKEN_LLD_MACRO	0x00004
#define ZBX_TOKEN_USER_MACRO	0x00008
#define ZBX_TOKEN_FUNC_MACRO	0x00010
#define ZBX_TOKEN_SIMPLE_MACRO	0x00020
#define ZBX_TOKEN_REFERENCE	0x00040

/* additional token flags */
#define ZBX_TOKEN_NUMERIC	0x08000
#define ZBX_TOKEN_JSON		0x10000
#define ZBX_TOKEN_XML		0x20000
#define ZBX_TOKEN_REGEXP	0x40000
#define ZBX_TOKEN_XPATH		0x80000

/* location of a substring */
typedef struct
{
	/* left position */
	size_t	l;
	/* right position */
	size_t	r;
}
zbx_strloc_t;

/* data used by macros, ldd macros and objectid tokens */
typedef struct
{
	zbx_strloc_t	name;
}
zbx_token_macro_t;

/* data used by user macros */
typedef struct
{
	/* macro name */
	zbx_strloc_t	name;
	/* macro context, for macros without context the context.l and context.r fields are set to 0 */
	zbx_strloc_t	context;
}
zbx_token_user_macro_t;

/* data used by macro functions */
typedef struct
{
	/* the macro including the opening and closing brackets {}, for example: {ITEM.VALUE} */
	zbx_strloc_t	macro;
	/* function + parameters, for example: regsub("([0-9]+)", \1) */
	zbx_strloc_t	func;
	/* parameters, for example: ("([0-9]+)", \1) */
	zbx_strloc_t	func_param;
}
zbx_token_func_macro_t;

/* data used by simple (host:key) macros */
typedef struct
{
	/* host name, supporting simple macros as a host name, for example Zabbix server or {HOST.HOST} */
	zbx_strloc_t	host;
	/* key + parameters, supporting {ITEM.KEYn} macro, for example system.uname or {ITEM.KEY1}  */
	zbx_strloc_t	key;
	/* function + parameters, for example avg(5m) */
	zbx_strloc_t	func;
	/* parameters, for example (5m) */
	zbx_strloc_t	func_param;
}
zbx_token_simple_macro_t;

/* data used by references */
typedef struct
{
	/* index of constant being referenced (1 for $1, 2 for $2, ..., 9 for $9) */
	int	index;
}
zbx_token_reference_t;

/* the token type specific data */
typedef union
{
	zbx_token_macro_t		objectid;
	zbx_token_macro_t		macro;
	zbx_token_macro_t		lld_macro;
	zbx_token_user_macro_t		user_macro;
	zbx_token_func_macro_t		func_macro;
	zbx_token_simple_macro_t	simple_macro;
	zbx_token_reference_t		reference;
}
zbx_token_data_t;

/* {} token data */
typedef struct
{
	/* token type, see ZBX_TOKEN_ defines */
	int			type;
	/* the token location in expression including opening and closing brackets {} */
	zbx_strloc_t		token;
	/* the token type specific data */
	zbx_token_data_t	data;
}
zbx_token_t;

typedef enum
{
	ZBX_TOKEN_SEARCH_BASIC,
	ZBX_TOKEN_SEARCH_REFERENCES
}
zbx_token_search_t;

int	zbx_token_find(const char *expression, int pos, zbx_token_t *token, zbx_token_search_t token_search);
int	zbx_number_find(const char *str, size_t pos, zbx_strloc_t *number_loc);
int	zbx_strmatch_condition(const char *value, const char *pattern, unsigned char op);

#define ZBX_COMPONENT_VERSION(major, minor)	((major << 16) | minor)
#define ZBX_COMPONENT_VERSION_MAJOR(version)	(version >> 16)
#define ZBX_COMPONENT_VERSION_MINOR(version)	(version & 0xFF)

#define ZBX_PREPROC_MULTIPLIER		1
#define ZBX_PREPROC_RTRIM		2
#define ZBX_PREPROC_LTRIM		3
#define ZBX_PREPROC_TRIM		4
#define ZBX_PREPROC_REGSUB		5
#define ZBX_PREPROC_BOOL2DEC		6
#define ZBX_PREPROC_OCT2DEC		7
#define ZBX_PREPROC_HEX2DEC		8
#define ZBX_PREPROC_DELTA_VALUE		9
#define ZBX_PREPROC_DELTA_SPEED		10
#define ZBX_PREPROC_XPATH		11
#define ZBX_PREPROC_JSONPATH		12

#define ZBX_HTTPFIELD_HEADER		0
#define ZBX_HTTPFIELD_VARIABLE		1
#define ZBX_HTTPFIELD_POST_FIELD	2
#define ZBX_HTTPFIELD_QUERY_FIELD	3

#define ZBX_POSTTYPE_RAW		0
#define ZBX_POSTTYPE_FORM		1
#define ZBX_POSTTYPE_JSON		2
#define ZBX_POSTTYPE_XML		3

zbx_log_value_t	*zbx_log_value_dup(const zbx_log_value_t *src);

typedef union
{
	zbx_uint64_t	ui64;
	double		dbl;
	char		*str;
}
zbx_variant_data_t;

typedef struct
{
	unsigned char		type;
	zbx_variant_data_t	data;
}
zbx_variant_t;

#define ZBX_VARIANT_NONE	0
#define ZBX_VARIANT_STR		1
#define ZBX_VARIANT_DBL		2
#define ZBX_VARIANT_UI64	3

void	zbx_variant_clear(zbx_variant_t *value);
void	zbx_variant_set_none(zbx_variant_t *value);
void	zbx_variant_set_str(zbx_variant_t *value, char *text);
void	zbx_variant_set_dbl(zbx_variant_t *value, double dbl);
void	zbx_variant_set_ui64(zbx_variant_t *value, zbx_uint64_t ui64);
void	zbx_variant_set_variant(zbx_variant_t *value, const zbx_variant_t *source);
int	zbx_variant_set_numeric(zbx_variant_t *value, const char *text);

int	zbx_variant_convert(zbx_variant_t *value, int type);
const char	*zbx_variant_value_desc(const zbx_variant_t *value);
const char	*zbx_variant_type_desc(const zbx_variant_t *value);

int	zbx_validate_value_dbl(double value);

#endif

