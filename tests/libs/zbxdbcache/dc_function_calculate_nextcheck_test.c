int	zbx_dc_function_calculate_nextcheck(const zbx_trigger_timer_t *timer, time_t from, zbx_uint64_t seed);

int	zbx_dc_function_calculate_nextcheck(const zbx_trigger_timer_t *timer, time_t from, zbx_uint64_t seed)
{
	return dc_function_calculate_nextcheck(timer, from, seed);
}
