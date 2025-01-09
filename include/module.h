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

#ifndef ZABBIX_MODULE_H
#define ZABBIX_MODULE_H

#include "zbxtypes.h"

#define ZBX_MODULE_OK	0
#define ZBX_MODULE_FAIL	-1

/* zbx_module_api_version() MUST return this constant */
#define ZBX_MODULE_API_VERSION	2

/* old name alias is kept for source compatibility only, SHOULD NOT be used */
#define ZBX_MODULE_API_VERSION_ONE	ZBX_MODULE_API_VERSION

/* HINT: For conditional compilation with different module.h versions modules can use: */
/* #if ZBX_MODULE_API_VERSION == X                                                     */
/*         ...                                                                         */
/* #endif                                                                              */

#define get_rkey(request)		(request)->key
#define get_rparams_num(request)	(request)->nparam
#define get_rparam(request, num)	((request)->nparam > num ? (request)->params[num] : NULL)
#define get_rparam_type(request, num)	((request)->nparam > num ? (request)->types[num] : \
		REQUEST_PARAMETER_TYPE_UNDEFINED)

/* flags for command */
#define CF_HAVEPARAMS		0x01	/* item accepts either optional or mandatory parameters */
#define CF_MODULE		0x02	/* item is defined in a loadable module */
#define CF_USERPARAMETER	0x04	/* item is defined as user parameter */

typedef enum
{
	REQUEST_PARAMETER_TYPE_UNDEFINED = 0,
	REQUEST_PARAMETER_TYPE_STRING,
	REQUEST_PARAMETER_TYPE_ARRAY
}
zbx_request_parameter_type_t;

/* agent request structure */
typedef struct
{
	char				*key;
	int				nparam;
	char				**params;
	zbx_uint64_t			lastlogsize;
	int				mtime;
	zbx_request_parameter_type_t	*types;
	int				timeout;
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
#define AR_BIN		0x80

/* Agent return structure.                                       */
/* Need to preserve the compatibility with previous versions,    */
/* so new fields must be added at the end of the struct.         */
typedef struct
{
	zbx_uint64_t	lastlogsize;	/* meta information */
	zbx_uint64_t	ui64;
	double		dbl;
	char		*str;
	char		*text;
	char		*msg;		/* possible error message */
	zbx_log_t	*log;
	int		type;		/* flags: see AR_* above */
	int		mtime;		/* meta information */
	char		*bin;
}
AGENT_RESULT;

typedef struct
{
	char		*key;
	unsigned	flags;
	int		(*function)(AGENT_REQUEST *request, AGENT_RESULT *result);
	char		*test_param;	/* item test parameters; user parameter items keep command here */
}
zbx_metric_t;

/* for backward-compatibility */
#define ZBX_METRIC	zbx_metric_t

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

/* CHECK RESULT */

#define ZBX_ISSET_UI64(res)	((res)->type & AR_UINT64)
#define ZBX_ISSET_DBL(res)	((res)->type & AR_DOUBLE)
#define ZBX_ISSET_STR(res)	((res)->type & AR_STRING)
#define ZBX_ISSET_TEXT(res)	((res)->type & AR_TEXT)
#define ZBX_ISSET_LOG(res)	((res)->type & AR_LOG)
#define ZBX_ISSET_MSG(res)	((res)->type & AR_MESSAGE)
#define ZBX_ISSET_META(res)	((res)->type & AR_META)
#define ZBX_ISSET_BIN(res)	((res)->type & AR_BIN)

#define ZBX_ISSET_VALUE(res)	((res)->type & (AR_UINT64 | AR_DOUBLE | AR_STRING | AR_TEXT | AR_LOG))

/* UNSET RESULT */

#define ZBX_UNSET_UI64_RESULT(res)					\
									\
do									\
{									\
	(res)->type &= ~AR_UINT64;					\
	(res)->ui64 = (zbx_uint64_t)0;					\
}									\
while (0)

#define ZBX_UNSET_DBL_RESULT(res)					\
									\
do									\
{									\
	(res)->type &= ~AR_DOUBLE;					\
	(res)->dbl = (double)0;						\
}									\
while (0)

#define ZBX_UNSET_STR_RESULT(res)					\
									\
do									\
{									\
	if ((res)->type & AR_STRING)					\
	{								\
		zbx_free((res)->str);					\
		(res)->type &= ~AR_STRING;				\
	}								\
}									\
while (0)

#define ZBX_UNSET_TEXT_RESULT(res)					\
									\
do									\
{									\
	if ((res)->type & AR_TEXT)					\
	{								\
		zbx_free((res)->text);					\
		(res)->type &= ~AR_TEXT;				\
	}								\
}									\
while (0)

#define ZBX_UNSET_LOG_RESULT(res)					\
									\
do									\
{									\
	if ((res)->type & AR_LOG)					\
	{								\
		zbx_free((res)->log->source);				\
		zbx_free((res)->log->value);				\
		zbx_free((res)->log);					\
		(res)->type &= ~AR_LOG;					\
	}								\
}									\
while (0)

#define ZBX_UNSET_MSG_RESULT(res)					\
									\
do									\
{									\
	if ((res)->type & AR_MESSAGE)					\
	{								\
		zbx_free((res)->msg);					\
		(res)->type &= ~AR_MESSAGE;				\
	}								\
}									\
while (0)

#define ZBX_UNSET_BIN_RESULT(res)					\
									\
do									\
{									\
	if ((res)->type & AR_BIN)					\
	{								\
		zbx_free((res)->bin);					\
		(res)->type &= ~AR_BIN	;				\
	}								\
}									\
while (0)

/* AR_META is always excluded */
#define ZBX_UNSET_RESULT_EXCLUDING(res, exc_type)				\
										\
do										\
{										\
	if (!(exc_type & AR_UINT64))	ZBX_UNSET_UI64_RESULT(res);		\
	if (!(exc_type & AR_DOUBLE))	ZBX_UNSET_DBL_RESULT(res);		\
	if (!(exc_type & AR_STRING))	ZBX_UNSET_STR_RESULT(res);		\
	if (!(exc_type & AR_TEXT))	ZBX_UNSET_TEXT_RESULT(res);		\
	if (!(exc_type & AR_LOG))	ZBX_UNSET_LOG_RESULT(res);		\
	if (!(exc_type & AR_MESSAGE))	ZBX_UNSET_MSG_RESULT(res);		\
	if (!(exc_type & AR_BIN))	ZBX_UNSET_BIN_RESULT(res);		\
}										\
while (0)

#define	zbx_init_agent_result(result)	{memset((result), 0, sizeof(AGENT_RESULT));}

#define	zbx_free_agent_result(result)		\
{						\
	ZBX_UNSET_UI64_RESULT((result));	\
	ZBX_UNSET_DBL_RESULT((result));		\
	ZBX_UNSET_STR_RESULT((result));		\
	ZBX_UNSET_TEXT_RESULT((result));	\
	ZBX_UNSET_LOG_RESULT((result));		\
	ZBX_UNSET_MSG_RESULT((result));		\
	ZBX_UNSET_BIN_RESULT(result);		\
}

#define SYSINFO_RET_OK		0
#define SYSINFO_RET_FAIL	1

typedef struct
{
	zbx_uint64_t	itemid;
	int		clock;
	int		ns;
	double		value;
}
ZBX_HISTORY_FLOAT;

typedef struct
{
	zbx_uint64_t	itemid;
	int		clock;
	int		ns;
	zbx_uint64_t	value;
}
ZBX_HISTORY_INTEGER;

typedef struct
{
	zbx_uint64_t	itemid;
	int		clock;
	int		ns;
	const char	*value;
}
ZBX_HISTORY_STRING;

typedef struct
{
	zbx_uint64_t	itemid;
	int		clock;
	int		ns;
	const char	*value;
}
ZBX_HISTORY_TEXT;

typedef struct
{
	zbx_uint64_t	itemid;
	int		clock;
	int		ns;
	const char	*value;
	const char	*source;
	int		timestamp;
	int		logeventid;
	int		severity;
}
ZBX_HISTORY_LOG;

typedef struct
{
	void	(*history_float_cb)(const ZBX_HISTORY_FLOAT *history, int history_num);
	void	(*history_integer_cb)(const ZBX_HISTORY_INTEGER *history, int history_num);
	void	(*history_string_cb)(const ZBX_HISTORY_STRING *history, int history_num);
	void	(*history_text_cb)(const ZBX_HISTORY_TEXT *history, int history_num);
	void	(*history_log_cb)(const ZBX_HISTORY_LOG *history, int history_num);
}
ZBX_HISTORY_WRITE_CBS;

int	zbx_module_api_version(void);
int	zbx_module_init(void);
int	zbx_module_uninit(void);
void	zbx_module_item_timeout(int timeout);
ZBX_METRIC	*zbx_module_item_list(void);
ZBX_HISTORY_WRITE_CBS	zbx_module_history_write_cbs(void);

#endif
