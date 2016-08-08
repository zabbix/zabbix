/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#ifndef ZABBIX_MODULE_H
#define ZABBIX_MODULE_H

#include "zbxtypes.h"

/* Zabbix API */

/* these definitions MUST stay the same unless we decide to release new API version */

typedef unsigned char	zabbix_error_t;
typedef unsigned char	zabbix_label_t;
typedef zbx_uint64_t	zabbix_handle_t;

/* convenience wrapper to access contents of buffers passed to */
/* zabbix_get_object_member() and zabbix_get_vector_element () */
/* WILL NOT WORK if Zabbix binary and module binary use different endianness */
typedef union
{
	char		bytes[8];
	zbx_uint64_t	as_uint64;
	double		as_double;
	char		*as_string;
	zabbix_handle_t	as_object;
	zabbix_handle_t	as_vector;
}
zabbix_basic_t;

/* return codes */
#define ZABBIX_SUCCESS		0
#define ZABBIX_END_OF_VECTOR	1
#define ZABBIX_INVALID_HANDLE	2
#define ZABBIX_NOT_AN_OBJECT	3
#define ZABBIX_NOT_A_VECTOR	4
#define ZABBIX_NO_SUCH_MEMBER	5
#define ZABBIX_INTERNAL_ERROR	6

/* object labels */
#define ZABBIX_HISTORY_RECORD_ITEMID			1
#define ZABBIX_HISTORY_RECORD_CLOCK			2
#define ZABBIX_HISTORY_RECORD_NS			3
#define ZABBIX_HISTORY_RECORD_VALUETYPE			4
#define ZABBIX_HISTORY_RECORD_VALUE			5
#define ZABBIX_HISTORY_RECORD_VALUELOG_VALUE		6
#define ZABBIX_HISTORY_RECORD_VALUELOG_TIMESTAMP	7
#define ZABBIX_HISTORY_RECORD_VALUELOG_SOURCE		8
#define ZABBIX_HISTORY_RECORD_VALUELOG_LOGEVENTID	9
#define ZABBIX_HISTORY_RECORD_VALUELOG_SEVERITY		10

/* types */
#define ZABBIX_TYPE_UINT64	1
#define ZABBIX_TYPE_DOUBLE	2
#define ZABBIX_TYPE_STRING	3
#define ZABBIX_TYPE_OBJECT	4
#define ZABBIX_TYPE_VECTOR	5

unsigned char	zabbix_version(void);

const char * const	zabbix_error_message(zabbix_error_t error);

zabbix_error_t	zabbix_get_object_member(zabbix_handle_t object, zabbix_label_t label, void *buffer);
zabbix_error_t	zabbix_get_vector_element(zabbix_handle_t vector, void *buffer);

/* module API */

#define ZBX_MODULE_OK	0
#define ZBX_MODULE_FAIL	-1

#define ZBX_MODULE_API_VERSION_ONE	1

#define get_rkey(request)		(request)->key
#define get_rparams_num(request)	(request)->nparam
#define get_rparam(request, num)	((request)->nparam > num ? (request)->params[num] : NULL)

/* flags for command */
#define CF_HAVEPARAMS		0x01	/* item accepts either optional or mandatory parameters */
#define CF_MODULE		0x02	/* item is defined in a loadable module */
#define CF_USERPARAMETER	0x04	/* item is defined as user parameter */

typedef struct
{
	char		*key;
	unsigned	flags;
	int		(*function)();
	char		*test_param;	/* item test parameters; user parameter items keep command here */
}
ZBX_METRIC;

/* agent request structure */
typedef struct
{
	char		*key;
	int		nparam;
	char		**params;
	zbx_uint64_t	lastlogsize;
	int		mtime;
}
AGENT_REQUEST;

typedef struct
{
	char		*value;
	char		*source;
	int		timestamp;
	int		severity;
	int		logeventid;
}
zbx_log_t;

/* agent result types */
#define AR_UINT64	0x01
#define AR_DOUBLE	0x02
#define AR_STRING	0x04
#define AR_TEXT		0x08
#define AR_LOG		0x10
#define AR_MESSAGE	0x20
#define AR_META		0x40

/* agent return structure */
typedef struct
{
	zbx_uint64_t	lastlogsize;	/* meta information */
	zbx_uint64_t	ui64;
	double		dbl;
	char		*str;
	char		*text;
	char		*msg;		/* possible error message */
	zbx_log_t	*log;
	int	 	type;		/* flags: see AR_* above */
	int		mtime;		/* meta information */
}
AGENT_RESULT;

/* SET RESULT */

#define SET_UI64_RESULT(res, val)		\
(						\
	(res)->type |= AR_UINT64,		\
	(res)->ui64 = (zbx_uint64_t)(val)	\
)

#define SET_DBL_RESULT(res, val)		\
(						\
	(res)->type |= AR_DOUBLE,		\
	(res)->dbl = (double)(val)		\
)

/* NOTE: always allocate new memory for val! DON'T USE STATIC OR STACK MEMORY!!! */
#define SET_STR_RESULT(res, val)		\
(						\
	(res)->type |= AR_STRING,		\
	(res)->str = (char *)(val)		\
)

/* NOTE: always allocate new memory for val! DON'T USE STATIC OR STACK MEMORY!!! */
#define SET_TEXT_RESULT(res, val)		\
(						\
	(res)->type |= AR_TEXT,			\
	(res)->text = (char *)(val)		\
)

/* NOTE: always allocate new memory for val! DON'T USE STATIC OR STACK MEMORY!!! */
#define SET_LOG_RESULT(res, val)		\
(						\
	(res)->type |= AR_LOG,			\
	(res)->log = (zbx_log_t *)(val)		\
)

/* NOTE: always allocate new memory for val! DON'T USE STATIC OR STACK MEMORY!!! */
#define SET_MSG_RESULT(res, val)		\
(						\
	(res)->type |= AR_MESSAGE,		\
	(res)->msg = (char *)(val)		\
)

#define SYSINFO_RET_OK		0
#define SYSINFO_RET_FAIL	1

#endif
