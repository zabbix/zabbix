#include "zbxmocktest.h"
#include "zbxmockdata.h"

#include "common.h"
#include "comms.h"

static zbx_mock_handle_t	recv_vector, recv_elem;

static int	read_yaml_ret(void)
{
	zbx_mock_handle_t	handle;
	zbx_mock_error_t	error;
	const char		*str;

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("ret", &handle)))
		fail_msg("Cannot get return code: %s", zbx_mock_error_string(error));

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(handle, &str)))
		fail_msg("Cannot read return code: %s", zbx_mock_error_string(error));

	if (0 == strcasecmp(str, "succeed"))
		return SUCCEED;

	if (0 != strcasecmp(str, "fail"))
		fail_msg("Incorrect return code '%s'", str);

	return FAIL;
}

static zbx_uint64_t	read_yaml_uint64(const char *out)
{
	zbx_mock_handle_t	handle;
	zbx_mock_error_t	error;
	const char		*str;
	zbx_uint64_t		value;

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter(out, &handle)))
		fail_msg("Cannot get interruptions since boot: %s", zbx_mock_error_string(error));

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(handle, &str)))
		fail_msg("Cannot read interruptions since boot: %s", zbx_mock_error_string(error));

	if (FAIL == is_uint64(str, &value))
		fail_msg("\"%s\" is not a valid numeric unsigned value", str);

	return value;
}

static int	zbx_tcp_connect_mock(zbx_socket_t *s, const char *source_ip, const char *ip, unsigned short port,
		int timeout, unsigned int tls_connect, const char *tls_arg1, const char *tls_arg2)
{
	memset(s, 0, sizeof(zbx_socket_t));

	s->buf_type = ZBX_BUF_TYPE_STAT;

	return SUCCEED;
}

ssize_t	__wrap_read(int fd, void *buf, size_t nbytes)
{
	zbx_mock_error_t	error;
	const char		*value;
	size_t			length;

	ZBX_UNUSED(fd);

	if (ZBX_MOCK_SUCCESS != zbx_mock_vector_element(recv_vector, &recv_elem))
		return 0;	/* no more data */

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_binary(recv_elem, &value, &length)))
		fail_msg("Cannot read data '%s'", zbx_mock_error_string(error));

	memcpy(buf, value, length);

	return length;
}

void	zbx_mock_test_entry(void **state)
{
	zbx_socket_t		s;
	ssize_t			bytes_received, bytes_expected;
	zbx_mock_error_t	error;
	int			i, ret;

	ZBX_UNUSED(state);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("recv data", &recv_vector)))
		fail_msg("Cannot get recv data handle: %s", zbx_mock_error_string(error));

	zbx_tcp_connect_mock(&s, "127.0.0.2", "127.0.0.1", 10050, 0, 0, NULL, NULL);

	if (read_yaml_ret() != SUCCEED_OR_FAIL((bytes_received = zbx_tcp_recv_ext(&s, 0))))
		fail_msg("Unexpected return code '%s'", zbx_result_string(SUCCEED_OR_FAIL(bytes_received)));

	if (FAIL == SUCCEED_OR_FAIL(bytes_received))
		return;

	if ((bytes_expected = read_yaml_uint64("number of bytes")) != bytes_received)
	{
		fail_msg("Expected number of bytes to receive:" ZBX_FS_UI64 " received:" ZBX_FS_UI64,
				bytes_received, bytes_expected);
	}
}
