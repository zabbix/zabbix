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

#ifndef ZABBIX_SERIALIZE_H
#define ZABBIX_SERIALIZE_H

#include "zbxcommon.h"

#define zbx_serialize_prepare_str(len, str)					\
	do									\
	{									\
		str##_len = (NULL != str ? (zbx_uint32_t)strlen(str) + 1 : 0);	\
		len += str##_len + (zbx_uint32_t)sizeof(zbx_uint32_t);		\
	}									\
	while(0)

#define zbx_serialize_prepare_str_len(len, str, str_len)			\
	do									\
	{									\
		str_len = (NULL != str ? (zbx_uint32_t)strlen(str) + 1 : 0);	\
		len += str_len + (zbx_uint32_t)sizeof(zbx_uint32_t);		\
	}									\
	while(0)

#define zbx_serialize_prepare_vector_uint64_len(len, vector_uint64, vector_uint64_len)					\
	do														\
	{														\
		vector_uint64_len = (zbx_uint32_t)vector_uint64->values_num * (zbx_uint32_t)sizeof(zbx_uint64_t);	\
		len += vector_uint64_len + (zbx_uint32_t)sizeof(zbx_uint32_t);						\
	}														\
	while(0)

#define zbx_serialize_prepare_value(len, value)	do { len += (zbx_uint32_t)sizeof(value); } while(0)

#define zbx_serialize_uint64(buffer, value)			\
	(memcpy(buffer, (const zbx_uint64_t *)&value, sizeof(zbx_uint64_t)), sizeof(zbx_uint64_t))

#define zbx_serialize_int(buffer, value) (memcpy(buffer, (const int *)&value, sizeof(int)), sizeof(int))

#define zbx_serialize_short(buffer, value) (memcpy(buffer, (const short *)&value, sizeof(short)), sizeof(short))

#define zbx_serialize_double(buffer, value) (memcpy(buffer, (const double *)&value, sizeof(double)), sizeof(double))

#define zbx_serialize_char(buffer, value) (*buffer = (char)value, sizeof(char))

#define zbx_serialize_str_null(buffer)	(memset(buffer, 0, sizeof(zbx_uint32_t)), sizeof(zbx_uint32_t))

#define zbx_serialize_str(buffer, value, len)						\
	(										\
		0 == len ? zbx_serialize_str_null(buffer) :				\
		(									\
			memcpy(buffer, (zbx_uint32_t *)&len, sizeof(zbx_uint32_t)),	\
			memcpy(buffer + sizeof(zbx_uint32_t), value, len),		\
			len + sizeof(zbx_uint32_t)					\
		)									\
	)

#define zbx_serialize_vector_uint64(buffer, vector_uint64, len)					\
	(											\
		0 == len ? zbx_serialize_str_null(buffer) :					\
		(										\
			memcpy(buffer, (zbx_uint32_t *)&len, sizeof(zbx_uint32_t)),		\
			memcpy(buffer + sizeof(zbx_uint32_t), vector_uint64->values, len),	\
			len + sizeof(zbx_uint32_t)						\
		)										\
	)

#define zbx_serialize_value(buffer, value) (memcpy(buffer, &value, sizeof(value)), sizeof(value))

/* deserialization of primitive types */

#define zbx_deserialize_uint64(buffer, value) \
	(memcpy(value, buffer, sizeof(zbx_uint64_t)), sizeof(zbx_uint64_t))

#define zbx_deserialize_int(buffer, value) \
	(memcpy(value, buffer, sizeof(int)), sizeof(int))

#define zbx_deserialize_short(buffer, value) \
	(memcpy(value, buffer, sizeof(short)), sizeof(short))

#define zbx_deserialize_char(buffer, value) \
	(*value = *buffer, sizeof(char))

#define zbx_deserialize_double(buffer, value) \
	(memcpy(value, buffer, sizeof(double)), sizeof(double))

#define zbx_deserialize_str(buffer, value, value_len)					\
	(										\
		memcpy(&value_len, buffer, sizeof(zbx_uint32_t)),			\
		0 < value_len ? (							\
			*value = (char *)zbx_malloc(NULL, (zbx_uint64_t)value_len + 1),	\
			memcpy(*(value), buffer + sizeof(zbx_uint32_t), value_len),	\
			(*value)[value_len] = '\0'					\
		) : (*value = NULL, 0),							\
		value_len + sizeof(zbx_uint32_t)					\
	)

#define zbx_deserialize_vector_uint64(buffer, vector_uint64, value_len)			\
	(										\
		memcpy(&value_len, buffer, sizeof(zbx_uint32_t)),			\
		0 < value_len ? 							\
			zbx_vector_uint64_append_array(vector_uint64,			\
				(const zbx_uint64_t *)(buffer + sizeof(zbx_uint32_t)),	\
				(int)(value_len / sizeof(zbx_uint64_t)))		\
		: (void)0,								\
		value_len + sizeof(zbx_uint32_t)					\
	)

#define zbx_deserialize_value(buffer, value) \
	(memcpy(value, buffer, sizeof(*value)), sizeof(*value))

/* length prefixed binary data */
#define zbx_deserialize_bin(buffer, value, value_len)					\
	(										\
		memcpy(&value_len, buffer, sizeof(zbx_uint32_t)),			\
		*value = (void *)zbx_malloc(NULL, value_len + sizeof(zbx_uint32_t)),	\
		memcpy(*(value), buffer, value_len + sizeof(zbx_uint32_t)),		\
		value_len + sizeof(zbx_uint32_t)					\
	)

/* complex serialization/deserialization functions */

zbx_uint32_t	zbx_serialize_uint31_compact(unsigned char *ptr, zbx_uint32_t value);
zbx_uint32_t	zbx_deserialize_uint31_compact(const unsigned char *ptr, zbx_uint32_t *value);

#endif /* ZABBIX_SERIALIZE_H */
