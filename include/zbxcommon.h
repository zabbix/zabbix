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

#ifndef ZABBIX_COMMON_H
#define ZABBIX_COMMON_H

#include "zbxsysinc.h"
#include "module.h"
#include "version.h"

#if defined(__MINGW32__)
#	define __try
#	define __except(x) if (0)
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

#ifdef strncasecmp
#	undef strncasecmp
#endif
#define strncasecmp	ERROR_DO_NOT_USE_STRNCASECMP_FUNCTION_TRY_TO_USE_ZBX_STRNCASECMP

#define ON	1
#define OFF	0

#if defined(_WINDOWS)
#	pragma warning (disable: 4996)	/* warning C4996: <function> was declared deprecated */
#endif

#if defined(__GNUC__) && __GNUC__ >= 7
#	define ZBX_FALLTHROUGH	__attribute__ ((fallthrough))
#else
#	define ZBX_FALLTHROUGH
#endif

#define SUCCEED_OR_FAIL(result) (FAIL != (result) ? SUCCEED : FAIL)
const char	*zbx_sysinfo_ret_string(int ret);
const char	*zbx_result_string(int result);

#define MAX_ID_LEN			21
#define MAX_STRING_LEN			2048
#define MAX_BUFFER_LEN			65536
#define ZBX_MAX_HOSTNAME_LEN		128
#define ZBX_HOSTNAME_BUF_LEN	(ZBX_MAX_HOSTNAME_LEN + 1)
#define ZBX_MAX_DNSNAME_LEN		255	/* maximum host DNS name length from RFC 1035 */
						/*(without terminating '\0') */
#define MAX_EXECUTE_OUTPUT_LEN		(16 * ZBX_MEBIBYTE)

#define ZBX_MAX_UINT64		(~__UINT64_C(0))
#define ZBX_MAX_UINT64_LEN	21
#define ZBX_MAX_UINT32_LEN	11
#define ZBX_MAX_DOUBLE_LEN	24

#define ZBX_SIZE_T_MAX	(~(size_t)0)

#define ZBX_MALLOC_TRIM (128 * ZBX_KIBIBYTE)

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

/* item types */
typedef enum
{
	ITEM_TYPE_ZABBIX = 0,
/*	ITEM_TYPE_SNMPv1,*/
	ITEM_TYPE_TRAPPER = 2,
	ITEM_TYPE_SIMPLE,
/*	ITEM_TYPE_SNMPv2c,*/
	ITEM_TYPE_INTERNAL = 5,
/*	ITEM_TYPE_SNMPv3,*/
	ITEM_TYPE_ZABBIX_ACTIVE = 7,
/*	ITEM_TYPE_AGGREGATE, */
	ITEM_TYPE_HTTPTEST = 9,
	ITEM_TYPE_EXTERNAL,
	ITEM_TYPE_DB_MONITOR,
	ITEM_TYPE_IPMI,
	ITEM_TYPE_SSH,
	ITEM_TYPE_TELNET,
	ITEM_TYPE_CALCULATED,
	ITEM_TYPE_JMX,
	ITEM_TYPE_SNMPTRAP,
	ITEM_TYPE_DEPENDENT,
	ITEM_TYPE_HTTPAGENT,
	ITEM_TYPE_SNMP,
	ITEM_TYPE_SCRIPT,
	ITEM_TYPE_BROWSER	/* 22 */
}
zbx_item_type_t;

#define SNMP_BULK_DISABLED	0
#define SNMP_BULK_ENABLED	1

#define ZBX_IF_SNMP_VERSION_1	1
#define ZBX_IF_SNMP_VERSION_2	2
#define ZBX_IF_SNMP_VERSION_3	3

#define ZBX_FLAG_DISCOVERY_NORMAL	0x00
#define ZBX_FLAG_DISCOVERY_RULE		0x01
#define ZBX_FLAG_DISCOVERY_PROTOTYPE	0x02
#define ZBX_FLAG_DISCOVERY_CREATED	0x04

#define ZBX_HOST_PROT_INTERFACES_INHERIT	0
#define ZBX_HOST_PROT_INTERFACES_CUSTOM		1

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
#define EVENT_SOURCE_AUTOREGISTRATION	2
#define EVENT_SOURCE_INTERNAL		3
#define EVENT_SOURCE_SERVICE		4
#define EVENT_SOURCE_COUNT		5

/* event objects */
#define EVENT_OBJECT_TRIGGER		0
#define EVENT_OBJECT_DHOST		1
#define EVENT_OBJECT_DSERVICE		2
#define EVENT_OBJECT_ZABBIX_ACTIVE	3
#define EVENT_OBJECT_ITEM		4
#define EVENT_OBJECT_LLDRULE		5
#define EVENT_OBJECT_SERVICE		6

/* acknowledged flags */
#define EVENT_NOT_ACKNOWLEDGED		0
#define EVENT_ACKNOWLEDGED		1

typedef enum
{
	DOBJECT_STATUS_UP = 0,
	DOBJECT_STATUS_DOWN,
	DOBJECT_STATUS_DISCOVER,
	DOBJECT_STATUS_LOST,
	DOBJECT_STATUS_FINALIZED
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
	ITEM_VALUE_TYPE_BIN,	/* Last real value. In some places it is also used in size of array or */
				/* upper bound for iteration. Do not forget to update when adding new types. */
	ITEM_VALUE_TYPE_NONE	/* Artificial value, not written into DB, used internally in server. */
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

/* value for not supported items */
#define ZBX_NOTSUPPORTED	"ZBX_NOTSUPPORTED"
/* value for item not having any data */
#define ZBX_NODATA		"ZBX_NODATA"
/* the error message for not supported items when reason is unknown */
#define ZBX_NOTSUPPORTED_MSG	"Unknown error."

/* Zabbix Agent non-critical error (agents older than 2.0) */
#define ZBX_ERROR		"ZBX_ERROR"

/* program type */
#define ZBX_PROGRAM_TYPE_SERVER		0x01
#define ZBX_PROGRAM_TYPE_PROXY_ACTIVE	0x02
#define ZBX_PROGRAM_TYPE_PROXY_PASSIVE	0x04
#define ZBX_PROGRAM_TYPE_PROXY		0x06	/* ZBX_PROGRAM_TYPE_PROXY_ACTIVE | ZBX_PROGRAM_TYPE_PROXY_PASSIVE */
#define ZBX_PROGRAM_TYPE_AGENTD		0x08
#define ZBX_PROGRAM_TYPE_SENDER		0x10
#define ZBX_PROGRAM_TYPE_GET		0x20
const char	*get_program_type_string(unsigned char program_type);

#define ZBX_PROGRAM_VARIANT_AGENT	1
#define ZBX_PROGRAM_VARIANT_AGENT2	2

/* process type */
#define ZBX_PROCESS_TYPE_POLLER			0
#define ZBX_PROCESS_TYPE_UNREACHABLE		1
#define ZBX_PROCESS_TYPE_IPMIPOLLER		2
#define ZBX_PROCESS_TYPE_PINGER			3
#define ZBX_PROCESS_TYPE_JAVAPOLLER		4
#define ZBX_PROCESS_TYPE_HTTPPOLLER		5
#define ZBX_PROCESS_TYPE_TRAPPER		6
#define ZBX_PROCESS_TYPE_SNMPTRAPPER		7
#define ZBX_PROCESS_TYPE_PROXYPOLLER		8
#define ZBX_PROCESS_TYPE_ESCALATOR		9
#define ZBX_PROCESS_TYPE_HISTSYNCER		10
#define ZBX_PROCESS_TYPE_DISCOVERER		11
#define ZBX_PROCESS_TYPE_ALERTER		12
#define ZBX_PROCESS_TYPE_TIMER			13
#define ZBX_PROCESS_TYPE_HOUSEKEEPER		14
#define ZBX_PROCESS_TYPE_DATASENDER		15
#define ZBX_PROCESS_TYPE_CONFSYNCER		16
#define ZBX_PROCESS_TYPE_SELFMON		17
#define ZBX_PROCESS_TYPE_VMWARE			18
#define ZBX_PROCESS_TYPE_COLLECTOR		19
#define ZBX_PROCESS_TYPE_LISTENER		20
#define ZBX_PROCESS_TYPE_ACTIVE_CHECKS		21
#define ZBX_PROCESS_TYPE_TASKMANAGER		22
#define ZBX_PROCESS_TYPE_IPMIMANAGER		23
#define ZBX_PROCESS_TYPE_ALERTMANAGER		24
#define ZBX_PROCESS_TYPE_PREPROCMAN		25
#define ZBX_PROCESS_TYPE_PREPROCESSOR		26
#define ZBX_PROCESS_TYPE_LLDMANAGER		27
#define ZBX_PROCESS_TYPE_LLDWORKER		28
#define ZBX_PROCESS_TYPE_ALERTSYNCER		29
#define ZBX_PROCESS_TYPE_HISTORYPOLLER		30
#define ZBX_PROCESS_TYPE_AVAILMAN		31
#define ZBX_PROCESS_TYPE_REPORTMANAGER		32
#define ZBX_PROCESS_TYPE_REPORTWRITER		33
#define ZBX_PROCESS_TYPE_SERVICEMAN		34
#define ZBX_PROCESS_TYPE_TRIGGERHOUSEKEEPER	35
#define ZBX_PROCESS_TYPE_ODBCPOLLER		36
#define ZBX_PROCESS_TYPE_CONNECTORMANAGER	37
#define ZBX_PROCESS_TYPE_CONNECTORWORKER	38
#define ZBX_PROCESS_TYPE_DISCOVERYMANAGER	39
#define ZBX_PROCESS_TYPE_HTTPAGENT_POLLER	40
#define ZBX_PROCESS_TYPE_AGENT_POLLER		41
#define ZBX_PROCESS_TYPE_SNMP_POLLER		42
#define ZBX_PROCESS_TYPE_INTERNAL_POLLER	43
#define ZBX_PROCESS_TYPE_DBCONFIGWORKER		44
#define ZBX_PROCESS_TYPE_PG_MANAGER		45
#define ZBX_PROCESS_TYPE_BROWSERPOLLER		46
#define ZBX_PROCESS_TYPE_HA_MANAGER		47
#define ZBX_PROCESS_TYPE_COUNT			48	/* number of process types */

/* special processes that are not present worker list */
#define ZBX_PROCESS_TYPE_MAIN			126

#define ZBX_PROCESS_TYPE_UNKNOWN		255

const char	*get_process_type_string(unsigned char proc_type);
int		get_process_type_by_name(const char *proc_type_str);

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
	zbx_uint64_t	roleid;
	char		*username;
}
zbx_user_t;

typedef enum
{
	PERM_DENY = 0,
	PERM_READ = 2,
	PERM_READ_WRITE
}
zbx_user_permission_t;

#define POLLER_DELAY		5
#define DISCOVERER_DELAY	5

#define HOUSEKEEPER_STARTUP_DELAY	30	/* in minutes */

#define ZBX_DEFAULT_INTERVAL	SEC_PER_MIN

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

#if defined(_WINDOWS) || defined(__MINGW32__)
#	define zbx_get_thread_id()	(long int)GetCurrentThreadId()
#else
#	define zbx_get_thread_id()	(long int)getpid()
#endif

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

void	zbx_this_should_never_happen_backtrace(void);

#define THIS_SHOULD_NEVER_HAPPEN										\
														\
do														\
{														\
	zbx_this_should_never_happen_backtrace();								\
	zbx_error("ERROR [file and function: <%s,%s>, revision:%s, line:%d] Something unexpected has just "	\
			"happened.", __FILE__, __func__, ZABBIX_REVISION, __LINE__);				\
}														\
while (0)

#ifdef HAVE___VA_ARGS__
#	define THIS_SHOULD_NEVER_HAPPEN_MSG(...)								\
														\
	do													\
	{													\
		THIS_SHOULD_NEVER_HAPPEN;									\
		zbx_error(__VA_ARGS__);									\
	}													\
	while (0)
#else
#	define THIS_SHOULD_NEVER_HAPPEN_MSG									\
			THIS_SHOULD_NEVER_HAPPEN;								\
			zbx_error
#endif

#define ARRSIZE(a)	(sizeof(a) / sizeof(*a))

void	zbx_print_version(const char *title_message);

const char		*get_program_name(const char *path);
typedef unsigned char	(*zbx_get_program_type_f)(void);
typedef const char	*(*zbx_get_progname_f)(void);
typedef int		(*zbx_get_config_forks_f)(unsigned char process_type);
typedef const char	*(*zbx_get_config_str_f)(void);
typedef int		(*zbx_get_config_int_f)(void);
typedef void		(*zbx_backtrace_f)(void);

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
	ZBX_TASK_STOP_SERVICE,
	ZBX_TASK_SET_SERVICE_STARTUP_TYPE,
#else
	ZBX_TASK_RUNTIME_CONTROL,
#endif
	ZBX_TASK_TEST_CONFIG
}
zbx_task_t;

typedef enum
{
	HTTPTEST_AUTH_NONE = 0,
	HTTPTEST_AUTH_BASIC,
	HTTPTEST_AUTH_NTLM,
	HTTPTEST_AUTH_NEGOTIATE,
	HTTPTEST_AUTH_DIGEST,
	HTTPTEST_AUTH_BEARER
}
zbx_httptest_auth_t;

typedef struct
{
	zbx_task_t	task;
#define ZBX_TASK_FLAG_MULTIPLE_AGENTS	0x01
#define ZBX_TASK_FLAG_FOREGROUND	0x02
#ifdef _WINDOWS
	#define ZBX_TASK_FLAG_SERVICE_ENABLED		0x04
	#define ZBX_TASK_FLAG_SERVICE_AUTOSTART		0x08
	#define ZBX_TASK_FLAG_SERVICE_AUTOSTART_DELAYED	0x10
#endif
	unsigned int	flags;
	int		data;
	char		*opts;
}
ZBX_TASK_EX;

#define NET_DELAY_MAX	(SEC_PER_MIN / 4)

typedef struct
{
	int	values_num;
	int	period_end;
#define ZBX_PROXY_SUPPRESS_DISABLE	0x00
#define ZBX_PROXY_SUPPRESS_ACTIVE	0x01
#define ZBX_PROXY_SUPPRESS_MORE		0x02
#define ZBX_PROXY_SUPPRESS_EMPTY	0x04
#define ZBX_PROXY_SUPPRESS_ENABLE	(	\
		ZBX_PROXY_SUPPRESS_ACTIVE |	\
		ZBX_PROXY_SUPPRESS_MORE |	\
		ZBX_PROXY_SUPPRESS_EMPTY)
	int	flags;
}
zbx_proxy_suppress_t;

#define ZBX_RTC_MSG_SHIFT	0
#define ZBX_RTC_SCOPE_SHIFT	8
#define ZBX_RTC_DATA_SHIFT	16

#define ZBX_RTC_MSG_MASK	0x000000ff
#define ZBX_RTC_SCOPE_MASK	0x0000ff00
#define ZBX_RTC_DATA_MASK	0xffff0000

#define ZBX_RTC_GET_MSG(task)	(int)(((unsigned int)task & ZBX_RTC_MSG_MASK) >> ZBX_RTC_MSG_SHIFT)
#define ZBX_RTC_GET_SCOPE(task)	(int)(((unsigned int)task & ZBX_RTC_SCOPE_MASK) >> ZBX_RTC_SCOPE_SHIFT)
#define ZBX_RTC_GET_DATA(task)	(int)(((unsigned int)task & ZBX_RTC_DATA_MASK) >> ZBX_RTC_DATA_SHIFT)

#define ZBX_RTC_MAKE_MESSAGE(msg, scope, data)	(((zbx_uint32_t)msg << ZBX_RTC_MSG_SHIFT) | \
						((zbx_uint32_t)scope << ZBX_RTC_SCOPE_SHIFT) | \
						((zbx_uint32_t)data << ZBX_RTC_DATA_SHIFT))

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

#define ZBX_MAX_RECV_DATA_SIZE		(1 * ZBX_GIBIBYTE)
#if (4 < SIZEOF_SIZE_T)
#define ZBX_MAX_RECV_LARGE_DATA_SIZE	(__UINT64_C(16) * ZBX_GIBIBYTE)
#else
#define ZBX_MAX_RECV_LARGE_DATA_SIZE	(1 * ZBX_GIBIBYTE)
#endif

/* max length of base64 data */
#define ZBX_MAX_B64_LEN		(16 * ZBX_KIBIBYTE)

/* string functions that could not be moved into libzbxstr.a because they */
/* are used by libzbxcommon.a */

/* used by log which will be part of common */
#if defined(__GNUC__) || defined(__clang__)
#	define __zbx_attr_format_printf(idx1, idx2) __attribute__((__format__(__printf__, (idx1), (idx2))))
#	if defined(HAVE_TESTS)
#		define	__zbx_attr_weak		__attribute__((weak))
#		define	__zbx_static
#	endif
#else
#	define __zbx_attr_format_printf(idx1, idx2)
#endif

/* function override support for mock tests */

#if (defined(__GNUC__) || defined(__clang__)) && defined(HAVE_TESTS)
#	define	__zbx_attr_weak		__attribute__((weak))
#	define	__zbx_static
#endif

#if !defined(__zbx_attr_weak)
#	define __zbx_attr_weak
#endif

#if !defined(__zbx_static)
#	define	__zbx_static	static
#endif

/* used by cuid and also by log */
size_t	zbx_snprintf(char *str, size_t count, const char *fmt, ...) __zbx_attr_format_printf(3, 4);

/* could be moved into libzbxstr.a but it seems to be logically grouped with surrounding functions */
void	zbx_snprintf_alloc(char **str, size_t *alloc_len, size_t *offset, const char *fmt, ...)
		__zbx_attr_format_printf(4, 5);

#if defined(__hpux)
int	zbx_hpux_vsnprintf_is_c99(void);
#endif

/* used by log */
size_t	zbx_vsnprintf(char *str, size_t count, const char *fmt, va_list args);

int	zbx_vsnprintf_check_len(const char *fmt, va_list args);

/* used by log */
char	*zbx_dsprintf(char *dest, const char *f, ...) __zbx_attr_format_printf(2, 3);

/* used by zbxcommon, setproctitle */
size_t	zbx_strlcpy(char *dst, const char *src, size_t size);

/* used by dsprintf, which is used by log */
char	*zbx_dvsprintf(char *dest, const char *f, va_list args);

#define VALUE_ERRMSG_MAX	128
#define ZBX_LENGTH_UNLIMITED	0x7fffffff

#if defined(_WINDOWS) || defined(__MINGW32__)
wchar_t	*zbx_acp_to_unicode(const char *acp_string);
wchar_t	*zbx_utf8_to_unicode(const char *utf8_string);
wchar_t	*zbx_oemcp_to_unicode(const char *oemcp_string);
#endif
/* string functions that could not be moved into libzbxstr.a because they */
/* are used by libzbxcommon.a END */

char	**zbx_setproctitle_init(int argc, char **argv);
void	zbx_setproctitle(const char *fmt, ...) __zbx_attr_format_printf(1, 2);
void	zbx_setproctitle_deinit(void);
#if !defined(_WINDOWS) && !defined(__MINGW32__)
void	zbx_unsetenv(const char *envname);
#endif

void	zbx_error(const char *fmt, ...) __zbx_attr_format_printf(1, 2);

/* misc functions */
int	zbx_validate_hostname(const char *hostname);

int	get_nearestindex(const void *p, size_t sz, int num, zbx_uint64_t id);
int	uint64_array_add(zbx_uint64_t **values, int *alloc, int *num, zbx_uint64_t value, int alloc_step);
void	uint64_array_remove(zbx_uint64_t *values, int *num, const zbx_uint64_t *rm_values, int rm_num);

#if defined(_WINDOWS) || defined(__MINGW32__)
const OSVERSIONINFOEX	*zbx_win_getversion(void);
void	zbx_wmi_get(const char *wmi_namespace, const char *wmi_query, double timeout, char **utf8_value);
#endif

#if defined(_WINDOWS) || defined(__MINGW32__)
typedef struct __stat64	zbx_stat_t;
int	__zbx_stat(const char *path, zbx_stat_t *buf);
#else
typedef struct stat	zbx_stat_t;
#endif	/* _WINDOWS */

int	MAIN_ZABBIX_ENTRY(int flags);

#define ZBX_SESSION_ACTIVE		0
#define ZBX_SESSION_PASSIVE		1
#define ZBX_AUTH_TOKEN_ENABLED		0
#define ZBX_AUTH_TOKEN_DISABLED		1
#define ZBX_AUTH_TOKEN_NEVER_EXPIRES	0

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

#define ZBX_PSK_FOR_HOST		0x01				/* PSK can be used for a known host */
#define ZBX_PSK_FOR_AUTOREG		0x02				/* PSK can be used for host autoregistration */
#define ZBX_PSK_FOR_PROXY		0x04				/* PSK is configured on proxy */

void	zbx_alarm_flag_set(void);
void	zbx_alarm_flag_clear(void);

#ifndef _WINDOWS
unsigned int	zbx_alarm_on(unsigned int seconds);
unsigned int	zbx_alarm_off(void);
#endif

int	zbx_alarm_timed_out(void);

#define zbx_bsearch(key, base, nmemb, size, compar)	(0 == (nmemb) ? NULL : bsearch(key, base, nmemb, size, compar))

#define ZBX_PREPROC_NONE			0
#define ZBX_PREPROC_MULTIPLIER			1
#define ZBX_PREPROC_RTRIM			2
#define ZBX_PREPROC_LTRIM			3
#define ZBX_PREPROC_TRIM			4
#define ZBX_PREPROC_REGSUB			5
#define ZBX_PREPROC_BOOL2DEC			6
#define ZBX_PREPROC_OCT2DEC			7
#define ZBX_PREPROC_HEX2DEC			8
#define ZBX_PREPROC_DELTA_VALUE			9
#define ZBX_PREPROC_DELTA_SPEED			10
#define ZBX_PREPROC_XPATH			11
#define ZBX_PREPROC_JSONPATH			12
#define ZBX_PREPROC_VALIDATE_RANGE		13
#define ZBX_PREPROC_VALIDATE_REGEX		14
#define ZBX_PREPROC_VALIDATE_NOT_REGEX		15
#define ZBX_PREPROC_ERROR_FIELD_JSON		16
#define ZBX_PREPROC_ERROR_FIELD_XML		17
#define ZBX_PREPROC_ERROR_FIELD_REGEX		18
#define ZBX_PREPROC_THROTTLE_VALUE		19
#define ZBX_PREPROC_THROTTLE_TIMED_VALUE	20
#define ZBX_PREPROC_SCRIPT			21
#define ZBX_PREPROC_PROMETHEUS_PATTERN		22
#define ZBX_PREPROC_PROMETHEUS_TO_JSON		23
#define ZBX_PREPROC_CSV_TO_JSON			24
#define ZBX_PREPROC_STR_REPLACE			25
#define ZBX_PREPROC_VALIDATE_NOT_SUPPORTED	26
#define ZBX_PREPROC_XML_TO_JSON			27
#define ZBX_PREPROC_SNMP_WALK_VALUE		28
#define ZBX_PREPROC_SNMP_WALK_TO_JSON		29
#define ZBX_PREPROC_SNMP_GET_VALUE		30

/* custom on fail actions */
#define ZBX_PREPROC_FAIL_DEFAULT	0
#define ZBX_PREPROC_FAIL_DISCARD_VALUE	1
#define ZBX_PREPROC_FAIL_SET_VALUE	2
#define ZBX_PREPROC_FAIL_SET_ERROR	3

/* includes terminating '\0' */
#define CUID_LEN	26
void	zbx_new_cuid(char *cuid);

typedef struct
{
	char	*tag;
	char	*value;
}
zbx_tag_t;

#define ZBX_STR2UCHAR(var, string) var = (unsigned char)atoi(string)

#define ZBX_CONST_STRING(str) "" str
#define ZBX_CONST_STRLEN(str) (sizeof(ZBX_CONST_STRING(str)) - 1)

/* time and memory size suffixes */
zbx_uint64_t	suffix2factor(char c);

/******************************************************************************
 *                                                                            *
 * CODE BELOW IS LIBC WRAPPERS WHICH COULD BE LATER MOVED TO SEPARATE LIBRARY *
 *                                                                            *
 ******************************************************************************/

#define ZBX_MESSAGE_BUF_SIZE	1024

char	*zbx_strerror(int errnum);

#if !defined(_WINDOWS)
#	if defined(HAVE_LIBPTHREAD)
#		define zbx_sigmask	pthread_sigmask
#	else
#		define zbx_sigmask	sigprocmask
#	endif
#endif

#define ZBX_GET_CONFIG_VAR(type, varname, defvalue) \
static type	varname = defvalue; \
static type	get_##varname(void) \
{ \
	return varname; \
}

#define ZBX_GET_CONFIG_VAR2(type1, type2, varname, defvalue) \
static	type1	varname = defvalue; \
static	type2	get_##varname(void) \
{ \
	return varname; \
}

#define LOG_LEVEL_EMPTY		0	/* printing nothing (if not LOG_LEVEL_INFORMATION set) */
#define LOG_LEVEL_CRIT		1
#define LOG_LEVEL_ERR		2
#define LOG_LEVEL_WARNING	3
#define LOG_LEVEL_DEBUG		4
#define LOG_LEVEL_TRACE		5

#define LOG_LEVEL_INFORMATION	127	/* printing in any case no matter what level set */

#define ZBX_CHECK_LOG_LEVEL(level)			\
		((LOG_LEVEL_INFORMATION != (level) &&	\
		((level) > zbx_get_log_level() || LOG_LEVEL_EMPTY == (level))) ? FAIL : SUCCEED)

#ifdef HAVE___VA_ARGS__
#	define ZBX_ZABBIX_LOG_CHECK
#	define zabbix_log(level, ...)									\
													\
	do												\
	{												\
		if (SUCCEED == ZBX_CHECK_LOG_LEVEL(level))						\
			zbx_log_handle(level, __VA_ARGS__);						\
	}												\
	while (0)
#else
#	define zabbix_log zbx_log_handle
#endif

typedef void (*zbx_log_func_t)(int level, const char *fmt, va_list args);

void	zbx_init_library_common(zbx_log_func_t log_func, zbx_get_progname_f get_progname, zbx_backtrace_f backtrace);
void	zbx_log_handle(int level, const char *fmt, ...) __zbx_attr_format_printf(2, 3);
int	zbx_get_log_level(void);
void	zbx_set_log_level(int level);
const char	*zbx_get_log_component_name(void);

#ifndef _WINDOWS
void		zabbix_increase_log_level(void);
void		zabbix_decrease_log_level(void);
void		zabbix_report_log_level_change(void);
const char	*zabbix_get_log_level_string(void);

typedef struct
{
	int		level;
	const char	*name;
}
zbx_log_component_t;

void	zbx_set_log_component(const char *name, zbx_log_component_t *component);
void	zbx_change_component_log_level(zbx_log_component_t *component, int direction);
#endif

#endif
