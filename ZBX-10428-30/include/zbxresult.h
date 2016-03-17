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

#ifndef ZABBIX_RESULT_H
#define ZABBIX_RESULT_H

#include "zbxalgo.h"
#include "module.h"

typedef struct zbx_result_log
{
	char		*value;
	char		*source;
	int		timestamp;
	int		severity;
	int		logeventid;
}
zbx_result_log_t;

typedef struct zbx_result
{
	zbx_uint64_t		lastlogsize;	/* meta information */
	zbx_uint64_t		ui64;
	double			dbl;
	char			*str;
	char			*text;
	char			*msg;		/* possible error message */
	zbx_result_log_t	*log;
	int	 		type;		/* flags: see AR_* of AGENT_RESULT */
	int			mtime;		/* meta information */
	int			meta;		/* != 0 if meta information is set */
}
zbx_result_t;

#define ZBX_SET_UI64_RESULT(res, val)		\
(						\
	(res)->type |= AR_UINT64,		\
	(res)->ui64 = (zbx_uint64_t)(val)	\
)

#define ZBX_SET_DBL_RESULT(res, val)		\
(						\
	(res)->type |= AR_DOUBLE,		\
	(res)->dbl = (double)(val)		\
)

/* NOTE: always allocate new memory for val! DON'T USE STATIC OR STACK MEMORY!!! */
#define ZBX_SET_STR_RESULT(res, val)		\
(						\
	(res)->type |= AR_STRING,		\
	(res)->str = (char *)(val)		\
)

/* NOTE: always allocate new memory for val! DON'T USE STATIC OR STACK MEMORY!!! */
#define ZBX_SET_TEXT_RESULT(res, val)		\
(						\
	(res)->type |= AR_TEXT,			\
	(res)->text = (char *)(val)		\
)

/* NOTE: always allocate new memory for val! DON'T USE STATIC OR STACK MEMORY!!! */
#define ZBX_SET_LOG_RESULT(res, val)		\
(						\
	(res)->type |= AR_LOG,			\
	(res)->log = (zbx_result_log_t *)(val)	\
)

/* NOTE: always allocate new memory for val! DON'T USE STATIC OR STACK MEMORY!!! */
#define ZBX_SET_MSG_RESULT(res, val)		\
(						\
	(res)->type |= AR_MESSAGE,		\
	(res)->msg = (char *)(val)		\
)

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
		zbx_result_log_free((res)->log);			\
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

#define ZBX_ISSET_UI64(res)	((res)->type & AR_UINT64)
#define ZBX_ISSET_DBL(res)	((res)->type & AR_DOUBLE)
#define ZBX_ISSET_STR(res)	((res)->type & AR_STRING)
#define ZBX_ISSET_TEXT(res)	((res)->type & AR_TEXT)
#define ZBX_ISSET_LOG(res)	((res)->type & AR_LOG)
#define ZBX_ISSET_MSG(res)	((res)->type & AR_MESSAGE)

#define ZBX_GET_UI64_RESULT(res)	((zbx_uint64_t *)zbx_result_get_value_by_type(res, AR_UINT64))
#define ZBX_GET_DBL_RESULT(res)		((double *)zbx_result_get_value_by_type(res, AR_DOUBLE))
#define ZBX_GET_STR_RESULT(res)		((char **)zbx_result_get_value_by_type(res, AR_STRING))
#define ZBX_GET_TEXT_RESULT(res)	((char **)zbx_result_get_value_by_type(res, AR_TEXT))
#define ZBX_GET_LOG_RESULT(res)		((zbx_result_log_t *)zbx_result_get_value_by_type(res, AR_LOG))
#define ZBX_GET_MSG_RESULT(res)		((char **)zbx_result_get_value_by_type(res, AR_MESSAGE))

void	zbx_result_init(zbx_result_t *result);
void	zbx_result_log_free(zbx_result_log_t *log);
void	zbx_result_free(zbx_result_t *result);

int	zbx_result_set_type(zbx_result_t *result, int value_type, int data_type, char *c);
void	zbx_result_set_meta(zbx_result_t *result, zbx_uint64_t lastlogsize, int mtime);

void    *zbx_result_get_value_by_type(zbx_result_t *result, int require_type);
void	zbx_extract_results(AGENT_RESULT *agent_result, zbx_vector_ptr_t *add_results);

#endif	/* ZABBIX_RESULT_H */
