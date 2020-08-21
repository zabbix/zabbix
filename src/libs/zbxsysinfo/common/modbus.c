/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

#include "common.h"
#include "modbus.h"
#include "mutexs.h"

#ifdef HAVE_LIBMODBUS

zbx_mutex_t	modbus_lock = ZBX_MUTEX_NULL;
#define LOCK_MODBUS	zbx_mutex_lock(modbus_lock)
#define UNLOCK_MODBUS	zbx_mutex_unlock(modbus_lock)

#define ZBX_MODBUS_DATATYPE_STRLEN_MAX	6

static struct modbus_datatype_ref
{
	modbus_datatype_t	datatype;
	char			datatype_str[ZBX_MODBUS_DATATYPE_STRLEN_MAX + 1];
}
modbus_datatype_map[] =
{
	{ ZBX_MODBUS_DATATYPE_BIT,	"bit" },
	{ ZBX_MODBUS_DATATYPE_INT16,	"int16" },
	{ ZBX_MODBUS_DATATYPE_UINT16,	"uint16" },
	{ ZBX_MODBUS_DATATYPE_INT32,	"int32" },
	{ ZBX_MODBUS_DATATYPE_UINT32,	"uint32" },
	{ ZBX_MODBUS_DATATYPE_FLOAT,	"float" },
	{ ZBX_MODBUS_DATATYPE_UINT64,	"uint64" },
	{ ZBX_MODBUS_DATATYPE_DOUBLE,	"double" }
};

static uint32_t	read_reg_32(uint16_t *reg16, unsigned int idx, unsigned char endianess)
{
	switch(endianess)
	{
		case ZBX_MODBUS_ENDIANNESS_LE:
			return ZBX_MODBUS_32LE(reg16, idx);
		case ZBX_MODBUS_ENDIANNESS_MBE:
			return ZBX_MODBUS_32MBE(reg16, idx);
		case ZBX_MODBUS_ENDIANNESS_MLE:
			return ZBX_MODBUS_32MLE(reg16, idx);
		default:
			return ZBX_MODBUS_32BE(reg16, idx);
	}
}

static uint64_t	read_reg_64(uint16_t *reg16, unsigned int idx, unsigned char endianess)
{
	switch(endianess)
	{
		case ZBX_MODBUS_ENDIANNESS_LE:
			return ZBX_MODBUS_64LE(reg16, idx);
		case ZBX_MODBUS_ENDIANNESS_MBE:
			return ZBX_MODBUS_64MBE(reg16, idx);
		case ZBX_MODBUS_ENDIANNESS_MLE:
			return ZBX_MODBUS_64MLE(reg16, idx);
		default:
			return ZBX_MODBUS_64BE(reg16, idx);
	}
}

static void	set_serial_params_default(zbx_modbus_connection_serial *serial_params)
{
	serial_params->data_bits = 8;
	serial_params->parity = ZBX_MODBUS_SERIAL_PARAMS_PARITY_NONE;
	serial_params->stop_bits = 1;
}

/******************************************************************************
 *                                                                            *
 * Function: parse_params                                                     *
 *                                                                            *
 * Purpose: parse serial connection parameters                                *
 *                                                                            *
 * Parameters: params        - [IN] string holding parameters                 *
 *             serial_params - [OUT] parsed parameters                        *
 *                                                                            *
 * Return value: SUCCEED - parameters parsed successfully                     *
 *               FAIL    - failed to parse parameters                         *
 *                                                                            *
 ******************************************************************************/
static int	parse_params(char *params, zbx_modbus_connection_serial *serial_params)
{
	if (0 == isdigit(params[0]) || 0 == isdigit(params[2]))
		return FAIL;

	serial_params->data_bits = (unsigned char)params[0] - '0';

	if (5 > serial_params->data_bits || serial_params->data_bits > 8)
		return FAIL;

	serial_params->stop_bits = (unsigned char)params[2] - '0';

	if (1 > serial_params->stop_bits || serial_params->stop_bits > 2)
		return FAIL;

	serial_params->parity = toupper(params[1]);

	if (ZBX_MODBUS_SERIAL_PARAMS_PARITY_NONE != serial_params->parity &&
			ZBX_MODBUS_SERIAL_PARAMS_PARITY_EVEN != serial_params->parity &&
			ZBX_MODBUS_SERIAL_PARAMS_PARITY_ODD != serial_params->parity)
	{
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: endpoint_parse                                                   *
 *                                                                            *
 * Purpose: parse endpoint                                                    *
 *                                                                            *
 * Parameters: endpoint_str - [IN] string holding endpoint                    *
 *             endpoint     - [OUT] parsed endpoint                           *
 *                                                                            *
 * Return value: SUCCEED - endpoint parsed successfully                       *
 *               FAIL    - failed to parse endpoint                           *
 *                                                                            *
 ******************************************************************************/
static int	endpoint_parse(char *endpoint_str, zbx_modbus_endpoint_t *endpoint)
{
#define ZBX_MODBUS_PROTOCOL_PREFIX_TCP	"tcp://"
#define ZBX_MODBUS_PROTOCOL_PREFIX_RTU	"rtu://"
	char	*tmp;
	int	ret = SUCCEED;

	if (0 == zbx_strncasecmp(endpoint_str, ZBX_MODBUS_PROTOCOL_PREFIX_TCP,
			ZBX_CONST_STRLEN(ZBX_MODBUS_PROTOCOL_PREFIX_TCP)))
	{
		endpoint->protocol = ZBX_MODBUS_PROTOCOL_TCP;

		zbx_strsplit(endpoint_str + ZBX_CONST_STRLEN(ZBX_MODBUS_PROTOCOL_PREFIX_TCP), ':',
				&endpoint->conn_info.tcp.ip, &tmp);

		/* TODO - accept ipv6 and dns here */
		if (SUCCEED == (ret = is_ip4(endpoint->conn_info.tcp.ip)))
		{
			if (NULL != tmp)
				ret = is_ushort(tmp, &endpoint->conn_info.tcp.port);
			else
				endpoint->conn_info.tcp.port = ZBX_MODBUS_TCP_PORT_DEFAULT;
		}
		else
			zbx_free(endpoint->conn_info.tcp.ip);

		zbx_free(tmp);
	}
	else if (0 == zbx_strncasecmp(endpoint_str, ZBX_MODBUS_PROTOCOL_PREFIX_RTU,
			ZBX_CONST_STRLEN(ZBX_MODBUS_PROTOCOL_PREFIX_RTU)))
	{
		char	*ptr;

		endpoint->protocol = ZBX_MODBUS_PROTOCOL_RTU;

		ptr = endpoint_str + ZBX_CONST_STRLEN(ZBX_MODBUS_PROTOCOL_PREFIX_RTU);
		tmp = strchr(ptr, ':');

		if (NULL != tmp)
		{
			size_t	alloc_len = 0, offset = 0;
			char	*baudrate_str;

			zbx_strncpy_alloc(&endpoint->conn_info.serial.port, &alloc_len, &offset, ptr, tmp - ptr);
			zbx_strsplit(++tmp, ':', &baudrate_str, &ptr);
			endpoint->conn_info.serial.baudrate = atoi(baudrate_str);
			zbx_free(baudrate_str);

			if (NULL != ptr)
			{
				if (3 == strlen(ptr))
					ret = parse_params(ptr, &endpoint->conn_info.serial);
				else
					ret = FAIL;

				if (SUCCEED != ret)
					zbx_free(endpoint->conn_info.serial.port);

				zbx_free(ptr);
			}
			else
				set_serial_params_default(&endpoint->conn_info.serial);
		}
		else
		{
			endpoint->conn_info.serial.port = zbx_strdup(NULL, ptr);
			endpoint->conn_info.serial.baudrate = 115200;
			set_serial_params_default(&endpoint->conn_info.serial);
		}

#if !(defined(_WINDOWS) || defined(__MINGW32__))
		endpoint->conn_info.serial.port = zbx_dsprintf(endpoint->conn_info.serial.port, "/dev/%s",
				endpoint->conn_info.serial.port);
#endif
	}
	else
		ret = FAIL;

	return ret;
#undef ZBX_MODBUS_PROTOCOL_PREFIX_TCP
#undef ZBX_MODBUS_PROTOCOL_PREFIX_RTU
}

/******************************************************************************
 *                                                                            *
 * Function: modbus_read_data                                                 *
 *                                                                            *
 * Purpose: request and read modbus data                                      *
 *                                                                            *
 * Parameters: endpoint   - [IN] endpoint                                     *
 *             slaveid    - [IN] slave id                                     *
 *             function   - [IN] function                                     *
 *             address    - [IN] address of first register/coil/input to read *
 *             count      - [IN] count of sequenced same data type values to  *
 *                               be read from device                          *
 *             type       - [IN] data type                                    *
 *             endianness - [IN] endianness                                   *
 *             offset     - [IN] number of registers to be discarded          *
 *             res        - [OUT] retrieved modbus data                       *
 *             error      - [OUT] error message in case of failure            *
 *                                                                            *
 * Return value: SUCCEED - modbus data obtained successfully                  *
 *               FAIL    - failed to request or read modbus data              *
 *                                                                            *
 ******************************************************************************/
static int	modbus_read_data(zbx_modbus_endpoint_t *endpoint, unsigned char slaveid, unsigned char function,
		unsigned short address, unsigned short count, unsigned char type, unsigned char endianness,
		unsigned short offset, AGENT_RESULT *res, char **error)
{

	modbus_t	*mdb_ctx;
	uint8_t		*dest8 = NULL;
	uint16_t	*dest16 = NULL;
	int		ret = FAIL;
	unsigned int	count_w_offset, i;
	char		*list;

	switch (endpoint->protocol)
	{
		case ZBX_MODBUS_PROTOCOL_TCP:
			mdb_ctx = modbus_new_tcp(endpoint->conn_info.tcp.ip, (int)endpoint->conn_info.tcp.port);
			break;
		case ZBX_MODBUS_PROTOCOL_RTU:
			mdb_ctx = modbus_new_rtu(endpoint->conn_info.serial.port, endpoint->conn_info.serial.baudrate,
					endpoint->conn_info.serial.parity, endpoint->conn_info.serial.data_bits,
					endpoint->conn_info.serial.stop_bits);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			*error = zbx_strdup(*error, "invalid protocol");
			return FAIL;
	}

	if (NULL == mdb_ctx)
	{
		*error = zbx_dsprintf(*error, "modbus_new_%s() failed: %s",
				ZBX_MODBUS_PROTOCOL_TCP == endpoint->protocol ? "tcp" : "rtu", modbus_strerror(errno));
		return FAIL;
	}

	if (0 != modbus_set_slave(mdb_ctx, slaveid))
	{
		*error = zbx_dsprintf(*error, "modbus_set_slave() failed: %s", modbus_strerror(errno));
		goto out;
	}



#if defined(HAVE_LIBMODBUS_3_0)
	{
		struct timeval	tv;

		tv.tv_sec = CONFIG_TIMEOUT;
		tv.tv_usec = 0;
		modbus_set_response_timeout(mdb_ctx, &tv);

	}
#else /* HAVE_LIBMODBUS_3_1 at the moment */
	if (0 !=  modbus_set_response_timeout(mdb_ctx, CONFIG_TIMEOUT, 0))
	{
		*error = zbx_dsprintf(*error, "modbus_set_response_timeout() failed: %s", modbus_strerror(errno));
		goto out;
	}
#endif

	switch(type)
	{
		case ZBX_MODBUS_DATATYPE_BIT:
			count_w_offset = count + offset * ZBX_MODBUS_REGISTER_SZ;
			dest8 = zbx_malloc(NULL, sizeof(uint16_t) * count_w_offset);
			break;
		case ZBX_MODBUS_DATATYPE_INT32:
		case ZBX_MODBUS_DATATYPE_UINT32:
		case ZBX_MODBUS_DATATYPE_FLOAT:
			count_w_offset = count * 2 + offset;
			dest16 = zbx_malloc(NULL, sizeof(uint16_t) * count_w_offset);
			break;
		case ZBX_MODBUS_DATATYPE_UINT64:
		case ZBX_MODBUS_DATATYPE_DOUBLE:
			count_w_offset = count * 4 + offset;
			dest16 = zbx_malloc(NULL, sizeof(uint16_t) * count_w_offset);
			break;
		default:
			count_w_offset = count + offset;
			dest16 = zbx_malloc(NULL, sizeof(uint16_t) * count_w_offset);
	}

	if (ZBX_MODBUS_PROTOCOL_RTU == endpoint->protocol)
		LOCK_MODBUS;

	if (0 !=  modbus_connect(mdb_ctx))
	{
		*error = zbx_dsprintf(*error, "modbus_connect() failed: %s", modbus_strerror(errno));

		if (ZBX_MODBUS_PROTOCOL_RTU == endpoint->protocol)
			UNLOCK_MODBUS;

		goto out;
	}

	switch(function)
	{
		case ZBX_MODBUS_FUNCTION_COIL:
			ret = modbus_read_bits(mdb_ctx, address, count_w_offset, dest8);
			break;
		case ZBX_MODBUS_FUNCTION_DISCRETE_INPUT:
			ret = modbus_read_input_bits(mdb_ctx, address, count_w_offset, dest8);
			break;
		case ZBX_MODBUS_FUNCTION_INPUT_REGISTERS:
			ret = modbus_read_input_registers(mdb_ctx, address, count_w_offset, dest16);
			break;
		case ZBX_MODBUS_FUNCTION_HOLDING_REGISTERS:
			ret = modbus_read_registers(mdb_ctx, address, count_w_offset, dest16);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			modbus_close(mdb_ctx);

			if (ZBX_MODBUS_PROTOCOL_RTU == endpoint->protocol)
				UNLOCK_MODBUS;

			*error = zbx_strdup(*error, "invalid function");
			goto out;
	}

	modbus_close(mdb_ctx);

	if (ZBX_MODBUS_PROTOCOL_RTU == endpoint->protocol)
		UNLOCK_MODBUS;

	if (-1 == ret)
	{
		*error = zbx_dsprintf(*error, "modbus_read failed: %s", modbus_strerror(errno));
		goto out;
	}

	if (ZBX_MODBUS_DATATYPE_BIT == type)
	{
		uint8_t	*buf8;

		buf8 = dest8 + offset * ZBX_MODBUS_REGISTER_SZ;

		if (1 < count)
		{
			list = zbx_strdup(NULL, "[");

			for (i = 0; i < count; i++)
				list = zbx_dsprintf(list, "%s%s%u", list, 0 == i ? "" : ",", buf8[i]);

			list = zbx_dsprintf(list, "%s]", list);
			SET_STR_RESULT(res, list);
		}
		else
			SET_UI64_RESULT(res, *buf8);
	}
	else
	{
		uint16_t	*buf16;

		buf16 = dest16 + offset;

		if (1 == count)
		{
			switch(type)
			{
				case ZBX_MODBUS_DATATYPE_UINT32:
				case ZBX_MODBUS_DATATYPE_INT32:
					SET_UI64_RESULT(res, read_reg_32(buf16, 0, endianness));
					break;
				case ZBX_MODBUS_DATATYPE_FLOAT:
					SET_DBL_RESULT(res, read_reg_32(buf16, 0, endianness));
					break;
				case ZBX_MODBUS_DATATYPE_UINT64:
					SET_UI64_RESULT(res, read_reg_64(buf16, 0, endianness));
					break;
				case ZBX_MODBUS_DATATYPE_DOUBLE:
					SET_DBL_RESULT(res, read_reg_64(buf16, 0, endianness));
					break;
				default:
					SET_UI64_RESULT(res, *buf16);
			}
		}
		else
		{
			list = zbx_strdup(NULL, "[");

			for (i = 0; i < count; i++)
			{
				switch(type)
				{
					case ZBX_MODBUS_DATATYPE_UINT32:
						list = zbx_dsprintf(list, "%s%s%lu", list, 0 == i ? "" : ",",
								(long unsigned)(read_reg_32(buf16, i, endianness)));
						break;
					case ZBX_MODBUS_DATATYPE_INT32:
						list = zbx_dsprintf(list, "%s%s%li", list, 0 == i ? "" : ",",
								(long)(read_reg_32(buf16, i, endianness)));
						break;
					case ZBX_MODBUS_DATATYPE_FLOAT:
						list = zbx_dsprintf(list, "%s%s%f", list, 0 == i ? "" : ",",
								(float)(read_reg_32(buf16, i, endianness)));
						break;
					case ZBX_MODBUS_DATATYPE_UINT64:
						list = zbx_dsprintf(list, "%s%s%lu", list, 0 == i ? "" : ",",
								(long unsigned)(read_reg_64(buf16, i, endianness)));
						break;
					case ZBX_MODBUS_DATATYPE_DOUBLE:
						list = zbx_dsprintf(list, "%s%s%f", list, 0 == i ? "" : ",",
								(double)(read_reg_64(buf16, i, endianness)));
						break;
					default:
						list = zbx_dsprintf(list, "%s%s%u", list, 0 == i ? "" : ",", buf16[i]);
				}
			}

			list = zbx_dsprintf(list, "%s]", list);
			SET_STR_RESULT(res, list);
		}
	}

	ret = SUCCEED;
out:
	modbus_free(mdb_ctx);
	zbx_free(dest8);
	zbx_free(dest16);

	return ret;

}
#endif /* HAVE_LIBMODBUS */

int	MODBUS_GET(AGENT_REQUEST *request, AGENT_RESULT *result)
{
#ifdef HAVE_LIBMODBUS
	char			*tmp, *err = NULL;
	unsigned char		slaveid, function, type, endianess;
	unsigned short		count, address, offset;
	zbx_modbus_endpoint_t	endpoint;
	int			ret = SYSINFO_RET_FAIL;

	if (8 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	if (1 > request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		return SYSINFO_RET_FAIL;
	}

	/* endpoint */
	if (FAIL == endpoint_parse(get_rparam(request, 0), &endpoint))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	/* slave id */
	if (NULL == (tmp = get_rparam(request, 1)) || '\0' == *tmp)
	{
		slaveid = ZBX_MODBUS_PROTOCOL_TCP == endpoint.protocol ? 255 : 1;
	}
	else if (FAIL == is_uint_n_range(tmp, ZBX_SIZE_T_MAX, &slaveid, sizeof(unsigned char), 0,
			ZBX_MODBUS_PROTOCOL_TCP == endpoint.protocol ? 255 : 247))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		goto err;
	}

	/* function */
	if (NULL == (tmp = get_rparam(request, 2)) || '\0' == *tmp)
	{
		function = ZBX_MODBUS_FUNCTION_EMPTY;
	}
	else if (FAIL == is_uint_n_range(tmp, ZBX_SIZE_T_MAX, &function, sizeof(unsigned char), 1, 4))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		goto err;
	}

	/* address (and update function) */
	if (NULL == (tmp = get_rparam(request, 3)) || '\0' == *tmp)
	{
		address = 1;

		if (ZBX_MODBUS_FUNCTION_EMPTY == function)
			function = ZBX_MODBUS_FUNCTION_COIL;
	}
	else if (FAIL == is_ushort(tmp, &address))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fourth parameter."));
		goto err;
	}
	else if (ZBX_MODBUS_FUNCTION_EMPTY == function)
	{
		if (1 <= address && address <= 9999)
		{
			function = ZBX_MODBUS_FUNCTION_COIL;
		}
		else if (10001 <= address && address <= 19999)
		{
			function = ZBX_MODBUS_FUNCTION_DISCRETE_INPUT;
		}
		else if (30001 <= address && address <= 39999)
		{
			function = ZBX_MODBUS_FUNCTION_INPUT_REGISTERS;
		}
		else if (40001 <= address && address <= 49999)
		{
			function = ZBX_MODBUS_FUNCTION_HOLDING_REGISTERS;
		}
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Unsupported address for the specified function."));
			goto err;
		}
	}

	/* count */
	if (NULL == (tmp = get_rparam(request, 4)) || '\0' == *tmp)
	{
		count = 1;
	}
	else if (FAIL == is_ushort(tmp, &count) || 0 == count)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid fifth parameter."));
		goto err;
	}

	/* datatype */
	if (NULL == (tmp = get_rparam(request, 5)) || '\0' == *tmp)
	{
		if (ZBX_MODBUS_FUNCTION_COIL == function || ZBX_MODBUS_FUNCTION_DISCRETE_INPUT == function)
			type = ZBX_MODBUS_DATATYPE_BIT;
		else
			type = ZBX_MODBUS_DATATYPE_UINT16;
	}
	else
	{
		size_t	i;

		for (i = 0; i < ARRSIZE(modbus_datatype_map); i++)
		{
			if (0 == strcmp(modbus_datatype_map[i].datatype_str, tmp))
			{
				type = modbus_datatype_map[i].datatype;
				break;
			}
		}

		if (ARRSIZE(modbus_datatype_map) == i)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid sixth parameter."));
			goto err;
		}

		if ((ZBX_MODBUS_DATATYPE_BIT == type && (ZBX_MODBUS_FUNCTION_INPUT_REGISTERS == function ||
				ZBX_MODBUS_FUNCTION_HOLDING_REGISTERS == function)) ||
				(ZBX_MODBUS_DATATYPE_BIT != type && (ZBX_MODBUS_FUNCTION_COIL == function ||
						ZBX_MODBUS_FUNCTION_DISCRETE_INPUT == function)))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Unsupported data type for the specified function."));
			goto err;
		}
	}

	/* endianess */
	if (NULL == (tmp = get_rparam(request, 6)) || '\0' == *tmp)
	{
		endianess = ZBX_MODBUS_ENDIANNESS_BE;
	}
	else
	{
		char	*endianess_l;

		endianess_l = zbx_strdup(NULL, tmp);
		zbx_strlower(endianess_l);

		if (0 == strcmp(endianess_l, "be"))
		{
			endianess = ZBX_MODBUS_ENDIANNESS_BE;
		}
		else if (0 == strcmp(endianess_l, "le"))
		{
			endianess = ZBX_MODBUS_ENDIANNESS_LE;
		}
		else if (0 == strcmp(endianess_l, "mbe"))
		{
			endianess = ZBX_MODBUS_ENDIANNESS_MBE;
		}
		else if (0 == strcmp(endianess_l, "mle"))
		{
			endianess = ZBX_MODBUS_ENDIANNESS_MLE;
		}
		else
		{
			zbx_free(endianess_l);
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid seventh parameter."));
			goto err;
		}

		zbx_free(endianess_l);
	}

	/* offset */
	if (NULL == (tmp = get_rparam(request, 7)) || '\0' == *tmp)
	{
		offset = 0;
	}
	else if (FAIL == is_ushort(tmp, &offset))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid eighth parameter."));
		goto err;
	}

	if (SUCCEED != modbus_read_data(&endpoint, slaveid, function, address, count, type, endianess, offset, result,
			&err))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot read modbus data: %s.", err));
		goto err;
	}

	ret = SYSINFO_RET_OK;
err:
	if (ZBX_MODBUS_PROTOCOL_TCP == endpoint.protocol)
		zbx_free(endpoint.conn_info.tcp.ip);
	else if (ZBX_MODBUS_PROTOCOL_RTU == endpoint.protocol)
		zbx_free(endpoint.conn_info.serial.port);

	return ret;
#else
	ZBX_UNUSED(request);
	ZBX_UNUSED(result);
	return SYSINFO_RET_FAIL;
#endif /* HAVE_LIBMODBUS */
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_init_modbus                                                  *
 *                                                                            *
 * Purpose: create modbus mutex                                               *
 *                                                                            *
 * Parameters: error      - [OUT] error message in case of failure            *
 *                                                                            *
 * Return value: SUCCEED - modbus mutex created successfully                  *
 *               FAIL    - failed to create modbus mutex                      *
 *                                                                            *
 ******************************************************************************/
int zbx_init_modbus(char **error)
{
#ifdef HAVE_LIBMODBUS
	return zbx_mutex_create(&modbus_lock, ZBX_MUTEX_MODBUS, error);
#else
	ZBX_UNUSED(error);
	return SUCCEED;
#endif
}
