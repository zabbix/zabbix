#include "zbxmocktest.h"
#include "zbxmockdata.h"

#include "common.h"
#include "comms.h"

static zbx_mock_handle_t	fragments;

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
		fail_msg("Cannot get number of bytes: %s", zbx_mock_error_string(error));

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(handle, &str)))
		fail_msg("Cannot read number of bytes %s", zbx_mock_error_string(error));

	if (FAIL == is_uint64(str, &value))
		fail_msg("\"%s\" is not a valid numeric unsigned value", str);

	return value;
}

static char	*yaml_assemble_binary_data_array(size_t expected)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	fragment;
	const char		*value;
	size_t			length, offset = 0;
	char			*buffer;

	buffer = zbx_malloc(NULL, expected);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("fragments", &fragments)))
		fail_msg("Cannot get recv data handle: %s", zbx_mock_error_string(error));

	while (ZBX_MOCK_SUCCESS == zbx_mock_vector_element(fragments, &fragment))
	{
		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_binary(fragment, &value, &length)))
			fail_msg("Cannot read data '%s'", zbx_mock_error_string(error));

		if (offset + length > expected)
			fail_msg("Incorrect message size");

		memcpy(buffer + offset, value, length);
		offset += length;
	}

	if (offset != expected)
		fail_msg("Assembled message is smaller:" ZBX_FS_UI64 " than expected:" ZBX_FS_UI64, offset, expected);

	return buffer;
}

int	__wrap_connect(int fd, __CONST_SOCKADDR_ARG addr, socklen_t len)
{
	ZBX_UNUSED(fd);
	ZBX_UNUSED(addr);
	ZBX_UNUSED(len);

	return 0;
}

ssize_t	__wrap_read(int fd, void *buf, size_t nbytes)
{
	static int		remaining_length;
	static const char	*data;
	zbx_mock_error_t	error;
	zbx_mock_handle_t	fragment;
	size_t			length;

	ZBX_UNUSED(fd);

	if (0 == remaining_length)
	{
		if (ZBX_MOCK_SUCCESS != zbx_mock_vector_element(fragments, &fragment))
			return 0;	/* no more data */

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_binary(fragment, &data, &length)))
			fail_msg("Cannot read data '%s'", zbx_mock_error_string(error));
	}
	else
		length = remaining_length;

	if (nbytes < length)
	{
		remaining_length = length - nbytes;
		length = nbytes;
	}
	else
		remaining_length = 0;


	memcpy(buf, data, length);

	if (0 != remaining_length)
		data += length;

	return length;
}

void	zbx_mock_test_entry(void **state)
{
#define ZBX_TCP_HEADER_DATALEN_LEN	13

	char			*buffer;
	zbx_socket_t		s;
	ssize_t			received, expected;
	zbx_mock_error_t	error;

	ZBX_UNUSED(state);

	if (SUCCEED != zbx_tcp_connect(&s, NULL, "127.0.0.1", 10050, 0, ZBX_TCP_SEC_UNENCRYPTED, NULL, NULL))
		fail_msg("Failed to connect");

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("fragments", &fragments)))
			fail_msg("Cannot get fragments handle: %s", zbx_mock_error_string(error));

	if (read_yaml_ret() != SUCCEED_OR_FAIL((received = zbx_tcp_recv_ext(&s, 0))))
		fail_msg("Unexpected return code '%s'", zbx_result_string(SUCCEED_OR_FAIL(received)));

	if (FAIL == SUCCEED_OR_FAIL(received))
	{
		zbx_tcp_close(&s);
		return;
	}

	if (received != (expected = read_yaml_uint64("number of bytes")))
		fail_msg("Expected bytes to receive:" ZBX_FS_UI64 " received:" ZBX_FS_UI64, expected, received);

	if (ZBX_TCP_HEADER_DATALEN_LEN > received)
		fail_msg("Received less than header size");

	buffer = yaml_assemble_binary_data_array(received);

	if (0 != memcmp(buffer + ZBX_TCP_HEADER_DATALEN_LEN, s.buffer, received - ZBX_TCP_HEADER_DATALEN_LEN))
		fail_msg("Received message mismatch expected");

	zbx_tcp_close(&s);
	zbx_free(buffer);
#undef ZBX_TCP_HEADER_DATALEN_LEN
}
