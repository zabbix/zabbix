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

#ifndef ZABBIX_SYSINFO_COMMON_MODBTYPE_H
#define ZABBIX_SYSINFO_COMMON_MODBTYPE_H

#include "zbxsysinfo.h"

#define ZBX_MODBUS_TCP_PORT_DEFAULT		502

#define ZBX_MODBUS_FUNCTION_EMPTY		0
#define ZBX_MODBUS_FUNCTION_COIL		1
#define ZBX_MODBUS_FUNCTION_DISCRETE_INPUT	2
#define ZBX_MODBUS_FUNCTION_HOLDING_REGISTERS	3
#define ZBX_MODBUS_FUNCTION_INPUT_REGISTERS	4

#define ZBX_MODBUS_SERIAL_PARAMS_PARITY_NONE	'N'
#define ZBX_MODBUS_SERIAL_PARAMS_PARITY_EVEN	'E'
#define ZBX_MODBUS_SERIAL_PARAMS_PARITY_ODD	'O'

#define ZBX_MODBUS_BYTE_SWAP_16(t_int16)	(((t_int16 >> 8) & 0xFF) | ((t_int16 & 0xFF) << 8))

#define ZBX_MODBUS_32BE(t_int16)						\
		(((uint32_t)t_int16[0]) << 16) | t_int16[1]
#define ZBX_MODBUS_32MLE(t_int16)						\
		(((uint32_t)t_int16[1]) << 16) | t_int16[0]
#define ZBX_MODBUS_32MBE(t_int16)						\
		(((uint32_t)ZBX_MODBUS_BYTE_SWAP_16(t_int16[0])) << 16) |	\
		ZBX_MODBUS_BYTE_SWAP_16(t_int16[1])
#define ZBX_MODBUS_32LE(t_int16)						\
		(((uint32_t)ZBX_MODBUS_BYTE_SWAP_16(t_int16[1])) << 16) |	\
		ZBX_MODBUS_BYTE_SWAP_16(t_int16[0])
#define ZBX_MODBUS_64BE(t_int16)						\
		(((uint64_t)t_int16[0]) << 48) |				\
		(((uint64_t)t_int16[1]) << 32) | 				\
		(((uint64_t)t_int16[2]) << 16) | t_int16[3]
#define ZBX_MODBUS_64MLE(t_int16)						\
		(((uint64_t)t_int16[3]) << 48) |				\
		(((uint64_t)t_int16[2]) << 32) |				\
		(((uint64_t)t_int16[1]) << 16) | t_int16[0]
#define ZBX_MODBUS_64MBE(t_int16)						\
		(((uint64_t)ZBX_MODBUS_BYTE_SWAP_16(t_int16[0])) << 48) |	\
		(((uint64_t)ZBX_MODBUS_BYTE_SWAP_16(t_int16[1])) << 32) |	\
		(((uint64_t)ZBX_MODBUS_BYTE_SWAP_16(t_int16[2])) << 16) |	\
		ZBX_MODBUS_BYTE_SWAP_16(t_int16[3])
#define ZBX_MODBUS_64LE(t_int16)						\
		(((uint64_t)ZBX_MODBUS_BYTE_SWAP_16(t_int16[3])) << 48) |	\
		(((uint64_t)ZBX_MODBUS_BYTE_SWAP_16(t_int16[2])) << 32) |	\
		(((uint64_t)ZBX_MODBUS_BYTE_SWAP_16(t_int16[1])) << 16) |	\
		ZBX_MODBUS_BYTE_SWAP_16(t_int16[0])

typedef enum
{
	ZBX_MODBUS_PROTOCOL_TCP,
	ZBX_MODBUS_PROTOCOL_RTU
} modbus_protocol_t;

typedef struct
{
	char	*ip;
	char	*port;
}
zbx_modbus_connection_tcp;

typedef struct
{
	char		*port;
	int		baudrate;
	unsigned char	data_bits;
	unsigned char	parity;
	unsigned char	stop_bits;
}
zbx_modbus_connection_serial;

typedef struct
{
	modbus_protocol_t	protocol;
	union
	{
		zbx_modbus_connection_tcp	tcp;
		zbx_modbus_connection_serial	serial;
	} conn_info;
}
zbx_modbus_endpoint_t;

typedef enum
{
	ZBX_MODBUS_DATATYPE_BIT,
	ZBX_MODBUS_DATATYPE_INT8,
	ZBX_MODBUS_DATATYPE_UINT8,
	ZBX_MODBUS_DATATYPE_INT16,
	ZBX_MODBUS_DATATYPE_UINT16,
	ZBX_MODBUS_DATATYPE_INT32,
	ZBX_MODBUS_DATATYPE_UINT32,
	ZBX_MODBUS_DATATYPE_FLOAT,
	ZBX_MODBUS_DATATYPE_UINT64,
	ZBX_MODBUS_DATATYPE_DOUBLE
} modbus_datatype_t;

typedef enum
{
	ZBX_MODBUS_ENDIANNESS_BE,
	ZBX_MODBUS_ENDIANNESS_LE,
	ZBX_MODBUS_ENDIANNESS_MBE,
	ZBX_MODBUS_ENDIANNESS_MLE
} modbus_endianness_t;

int	modbus_get(AGENT_REQUEST *request, AGENT_RESULT *result);

#endif /* ZABBIX_SYSINFO_COMMON_MODBTYPE_H */
