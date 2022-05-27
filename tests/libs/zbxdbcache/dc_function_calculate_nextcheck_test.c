int	zbx_dc_function_calculate_nextcheck(const zbx_trigger_timer_t *timer, time_t from, zbx_uint64_t seed);

int	zbx_dc_function_calculate_nextcheck(const zbx_trigger_timer_t *timer, time_t from, zbx_uint64_t seed)
{
	/* note, the test must not have user macros in function period parameter */
	return dc_function_calculate_nextcheck((zbx_dc_um_handle_t *)1, timer, from, seed);
}
