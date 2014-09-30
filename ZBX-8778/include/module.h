/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
	zbx_uint64_t	lastlogsize;
	int		timestamp;
	int		severity;
	int		logeventid;
	int		mtime;
}
zbx_log_t;

/* agent return structure */
typedef struct
{
	int	 	type;
	zbx_uint64_t	ui64;
	double		dbl;
	char		*str;
	char		*text;
	char		*msg;

	/* null-terminated list of pointers */
	zbx_log_t	**logs;
}
AGENT_RESULT;

/* agent result types */
#define AR_UINT64	0x01
#define AR_DOUBLE	0x02
#define AR_STRING	0x04
#define AR_TEXT		0x08
#define AR_LOG		0x10
#define AR_MESSAGE	0x20

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
	(res)->logs = (zbx_log_t **)(val)	\
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
