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

#ifndef ZABBIX_SERIALIZE_H
#define ZABBIX_SERIALIZE_H

#include "common.h"

#define zbx_serialize_prepare_str(len, str)			\
	str##_len = (NULL != str ? strlen(str) + 1 : 0);	\
	len += str##_len + sizeof(zbx_uint32_t)

#define zbx_serialize_prepare_value(len, value)			\
	len += sizeof(value)

#define zbx_serialize_uint64(buffer, value) (memcpy(buffer, &value, sizeof(zbx_uint64_t)), sizeof(zbx_uint64_t))

#define zbx_serialize_int(buffer, value) (memcpy(buffer, (int *)&value, sizeof(int)), sizeof(int))

#define zbx_serialize_short(buffer, value) (memcpy(buffer, (short *)&value, sizeof(short)), sizeof(short))

#define zbx_serialize_double(buffer, value) (memcpy(buffer, (double *)&value, sizeof(double)), sizeof(double))

#define zbx_serialize_char(buffer, value) (*buffer = (char)value, sizeof(char))

#define zbx_serialize_str_null(buffer)				\
	(							\
		memset(buffer, 0, sizeof(zbx_uint32_t)),	\
		sizeof(zbx_uint32_t)				\
	)

#define zbx_serialize_str(buffer, value, len)						\
	(										\
		0 == len ? zbx_serialize_str_null(buffer) :				\
		(									\
			memcpy(buffer, (zbx_uint32_t *)&len, sizeof(zbx_uint32_t)),	\
			memcpy(buffer + sizeof(zbx_uint32_t), value, len),		\
			len + sizeof(zbx_uint32_t)					\
		)									\
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
			memcpy(&value_len, buffer, sizeof(zbx_uint32_t)),		\
			0 < value_len ? (						\
			*value = (char *)zbx_malloc(NULL, value_len + 1),			\
			memcpy(*(value), buffer + sizeof(zbx_uint32_t), value_len),	\
			(*value)[value_len] = '\0'					\
			) : (*value = NULL, 0),						\
		value_len + sizeof(zbx_uint32_t)					\
	)

#define zbx_deserialize_str_s(buffer, value, value_len)				\
	(									\
		memcpy(&value_len, buffer, sizeof(zbx_uint32_t)),		\
		memcpy(value, buffer + sizeof(zbx_uint32_t), value_len),	\
		value[value_len] = '\0',					\
		value_len + sizeof(zbx_uint32_t)				\
	)

#define zbx_deserialize_str_ptr(buffer, value, value_len)				\
	(										\
		memcpy(&value_len, buffer, sizeof(zbx_uint32_t)),			\
		0 < value_len ? (value = (char *)(buffer + sizeof(zbx_uint32_t))) :	\
		(value = NULL), value_len + sizeof(zbx_uint32_t)			\
	)

#define zbx_deserialize_value(buffer, value) \
	(memcpy(value, buffer, sizeof(*value)), sizeof(*value))

#endif /* ZABBIX_SERIALIZE_H */
