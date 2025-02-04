/*
** Zabbix
** Copyright (C) 2001-2025 Zabbix SIA
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

#include "zbxmocktest.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"
#include "../../../../src/libs/zbxsysinfo/agent/modbtype.h"
#include <modbus.h>

modbus_t	*__wrap_modbus_new_rtu(char *port, int baudrate,
		unsigned char parity, unsigned char data_bits,
		unsigned char stop_bits)
{
	zbx_mock_assert_str_eq("endpoint protocol", zbx_mock_get_parameter_string("out.endpoint.protocol"), "rtu");
	zbx_mock_assert_str_eq("endpoint port", zbx_mock_get_parameter_string("out.endpoint.port"), port);
	zbx_mock_assert_int_eq("endpoint parity", (int)(*zbx_mock_get_parameter_string("out.endpoint.parity")),
			(int)parity);
	zbx_mock_assert_int_eq("endpoint baudrate", zbx_mock_get_parameter_int("out.endpoint.baudrate"), baudrate);
	zbx_mock_assert_int_eq("endpoint data_bits", zbx_mock_get_parameter_int("out.endpoint.data_bits"),
			(int)data_bits);
	zbx_mock_assert_int_eq("endpoint stop_bits", zbx_mock_get_parameter_int("out.endpoint.stop_bits"),
			(int)stop_bits);

	return zbx_malloc(NULL, sizeof(int));
}

modbus_t	*__wrap_modbus_new_tcp_pi(char *ip, char *port)
{
	zbx_mock_assert_str_eq("endpoint protocol", zbx_mock_get_parameter_string("out.endpoint.protocol"), "tcp");
	zbx_mock_assert_str_eq("endpoint ip", zbx_mock_get_parameter_string("out.endpoint.ip"), ip);
	zbx_mock_assert_str_eq("endpoint port", zbx_mock_get_parameter_string("out.endpoint.port"), port);

	return zbx_malloc(NULL, sizeof(int));
}

void	__wrap_modbus_free(modbus_t *mdb_ctx)
{
	zbx_free(mdb_ctx);
}

void	__wrap_modbus_close(modbus_t *mdb_ctx)
{
	ZBX_UNUSED(mdb_ctx);
}

int	__wrap_modbus_connect(modbus_t *mdb_ctx)
{
	ZBX_UNUSED(mdb_ctx);

	return 0;
}

int	__wrap_modbus_set_slave(modbus_t *mdb_ctx, int slaveid)
{
	ZBX_UNUSED(mdb_ctx);

	zbx_mock_assert_int_eq("slaveid", zbx_mock_get_parameter_int("out.slaveid"), slaveid);

	return 0;
}

#ifdef HAVE_LIBMODBUS_3_0
void	__wrap_modbus_set_response_timeout(modbus_t *mdb_ctx, struct timeval *timeout)
{
	ZBX_UNUSED(mdb_ctx);
	ZBX_UNUSED(timeout);
}
#else
int	__wrap_modbus_set_response_timeout(modbus_t *mdb_ctx, uint32_t to_sec, uint32_t to_usec)
{
	ZBX_UNUSED(mdb_ctx);
	ZBX_UNUSED(to_sec);
	ZBX_UNUSED(to_usec);

	return 0;
}
#endif

static char	*read_common(int func, int addr, int nb, int chr_num)
{
	char	*data;
	size_t	len;

	zbx_mock_assert_int_eq("function", zbx_mock_get_parameter_int("out.function"), func);
	zbx_mock_assert_int_eq("address", zbx_mock_get_parameter_int("out.address"), addr);
	zbx_mock_assert_int_eq("total_count", zbx_mock_get_parameter_int("out.total_count"), nb);

	data = zbx_strdup(NULL, zbx_mock_get_parameter_string("in.data"));
	zbx_remove_chars(data, " ");
	len = strlen(data);

	zbx_mock_assert_int_eq("invalid length of data", len, nb * chr_num);

	return data;
}

static int	read_bits_common(int func, int addr, int nb, uint8_t *dest)
{
	char	*data;

	data = read_common(func, addr, nb, 1);

	for (int i = 0; i < nb; i++)
	{
		if ('0' == data[i])
			dest[i] = 0;
		else if ('1' == data[i])
			dest[i] = 1;
		else
			fail_msg("Invalid data value: %s", data);
	}

	zbx_free(data);

	return nb;
}

static int	read_registers_common(int func, int addr, int nb, uint16_t *dest)
{
	char	*data;
	int	k = 0, chr_num = sizeof(uint16_t) * 2;

	data = read_common(func, addr, nb, chr_num);

	for (int i = 0; i < nb * chr_num; i += chr_num)
	{
		if (SUCCEED != zbx_is_hex_n_range(data + i, (size_t)chr_num, dest + k++, sizeof(uint16_t), 0, 0xFFFF))
			fail_msg("Invalid data value: %s", data);
	}

	zbx_free(data);

	return nb;
}

int	__wrap_modbus_read_bits(modbus_t *mdb_ctx, int addr, int nb, uint8_t *dest)
{
	ZBX_UNUSED(mdb_ctx);

	return read_bits_common(1, addr, nb, dest);
}

int	__wrap_modbus_read_input_bits(modbus_t *mdb_ctx, int addr, int nb, uint8_t *dest)
{
	ZBX_UNUSED(mdb_ctx);

	return read_bits_common(2, addr, nb, dest);
}

int	__wrap_modbus_read_input_registers(modbus_t *mdb_ctx, int addr, int nb, uint16_t *dest)
{
	ZBX_UNUSED(mdb_ctx);

	return read_registers_common(4, addr, nb, dest);
}

int	__wrap_modbus_read_registers(modbus_t *mdb_ctx, int addr, int nb, uint16_t *dest)
{
	ZBX_UNUSED(mdb_ctx);

	return read_registers_common(3, addr, nb, dest);
}

void	zbx_mock_test_entry(void **state)
{
	AGENT_REQUEST	request;
	AGENT_RESULT	result;
	char		*item_key;
	int		ret;

	ZBX_UNUSED(state);

	item_key = zbx_mock_get_parameter_string("in.key");
	zbx_init_agent_request(&request);

	if (SUCCEED != zbx_parse_item_key(item_key, &request))
		fail_msg("Cannot parse item key: '%s'", item_key);

	zbx_init_agent_result(&result);
	ret = modbus_get(&request, &result);

	zbx_mock_assert_sysinfo_ret_eq("Return value",
			zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return")), ret);

	if (SUCCEED != ret)
	{
		zbx_mock_handle_t	handle;

		if (ZBX_MOCK_SUCCESS == zbx_mock_out_parameter("msg", &handle))
		{
			if (0 != ZBX_ISSET_MSG(&result))
			{
				zbx_mock_assert_str_eq("Error message", zbx_mock_get_parameter_string("out.msg"),
						*ZBX_GET_MSG_RESULT(&result));
			}
			else
				fail_msg("No error message");
		}

		goto out;
	}

	if (0 != ZBX_ISSET_STR(&result))
	{
		zbx_mock_assert_str_eq("result (str)", zbx_mock_get_parameter_string("out.result"),
				*ZBX_GET_STR_RESULT(&result));
	}
	else if (0 != ZBX_ISSET_UI64(&result))
	{
		zbx_mock_assert_uint64_eq("result (ui64)", zbx_mock_get_parameter_uint64("out.result"),
				*ZBX_GET_UI64_RESULT(&result));
	}
	else if (0 != ZBX_ISSET_DBL(&result))
	{
		zbx_mock_assert_double_eq("result (dbl)", zbx_mock_get_parameter_float("out.result"),
				*ZBX_GET_DBL_RESULT(&result));
	}
	else
		fail_msg("Invalid result type");
out:
	zbx_free_agent_request(&request);
	zbx_free_agent_result(&result);
}
