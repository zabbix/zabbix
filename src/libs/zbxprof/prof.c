#include "zbxalgo.h"
#include "log.h"
#include "zbxtime.h"
#include "zbxprof.h"

#define PROF_LEVEL_MAX	10

typedef struct
{
	const char		*func_name;
	double			start;
	double			sec;
	double			sec_wait;
	unsigned int		locked;
	zbx_prof_scope_t	scope;
}
zbx_func_profile_t;

ZBX_PTR_VECTOR_DECL(func_profiles, zbx_func_profile_t*)
ZBX_PTR_VECTOR_IMPL(func_profiles, zbx_func_profile_t*)

static volatile int			zbx_prof_enable_requested;

static zbx_vector_func_profiles_t	zbx_func_profiles;
static int				zbx_prof_enabled;
static int				zbx_prof_initialized;

static zbx_func_profile_t		*zbx_func_profile[PROF_LEVEL_MAX];
static int				zbx_func_profile_level;

static void	zbx_prof_init(void)
{
	if (0 == zbx_prof_initialized)
	{
		zbx_prof_initialized = 1;
		zbx_vector_func_profiles_create(&zbx_func_profiles);
	}
}

static int	compare_func_profile(const void *d1, const void *d2)
{
	const zbx_func_profile_t	*func_profile1 = *((const zbx_func_profile_t **)d1);
	const zbx_func_profile_t	*func_profile2 = *((const zbx_func_profile_t **)d2);

	ZBX_RETURN_IF_NOT_EQUAL(func_profile1->func_name, func_profile2->func_name);

	return 0;
}

static void	func_profile_free(zbx_func_profile_t *func_profile)
{
	zbx_free(func_profile);
}

void	zbx_prof_start(const char *func_name, zbx_prof_scope_t scope)
{
	if (1 == zbx_prof_enabled)
	{
		int			i;
		zbx_func_profile_t	*func_profile;

		if (FAIL == (i = zbx_vector_func_profiles_bsearch(&zbx_func_profiles, (zbx_func_profile_t *)&func_name,
				compare_func_profile)))
		{
			func_profile = zbx_malloc(NULL, sizeof(zbx_func_profile_t));
			func_profile->func_name = func_name;
			func_profile->sec = 0;
			func_profile->locked = 0;
			func_profile->sec_wait = 0;
			func_profile->scope = scope;

			zbx_vector_func_profiles_append(&zbx_func_profiles, func_profile);
			zbx_vector_func_profiles_sort(&zbx_func_profiles, compare_func_profile);
		}
		else
			func_profile = zbx_func_profiles.values[i];

		func_profile->locked++;
		func_profile->start = zbx_time();

		zbx_func_profile[zbx_func_profile_level] = func_profile;
		zbx_func_profile_level++;
	}
}

void	zbx_prof_end_wait(void)
{
	if (1 == zbx_prof_enabled)
	{
		zbx_func_profile_t	*func_profile;

		func_profile = zbx_func_profile[zbx_func_profile_level - 1];

		func_profile->sec_wait += zbx_time() - func_profile->start;
	}
}

void	zbx_prof_end(void)
{
	if (1 == zbx_prof_enabled)
	{
		zbx_func_profile_t	*func_profile;

		func_profile = zbx_func_profile[zbx_func_profile_level - 1];
		func_profile->sec += zbx_time() - func_profile->start;
		zbx_func_profile_level--;
	}
}

static const char	*get_scope_string(zbx_prof_scope_t scope)
{
	switch (scope)
	{
		case ZBX_PROF_RWLOCK:
			return "rwlock";
		case ZBX_PROF_MUTEX:
			return "mutex";
		default:
			return "processing";
	}
}

static void	zbx_print_prof(void)
{
	if (1 == zbx_prof_enabled)
	{
		int			i;
		zbx_func_profile_t	*func_profile;
		static char		*str = NULL;
		static size_t		str_alloc;
		size_t			str_offset = 0;
		double			total_wait_lock = 0, total_busy_lock = 0;

		for (i = 0; i < zbx_func_profiles.values_num; i++)
		{
			func_profile = zbx_func_profiles.values[i];

			if (ZBX_PROF_PROCESSING == func_profile->scope)
			{
				zbx_snprintf_alloc(&str, &str_alloc, &str_offset, "processing %s() busy:" ZBX_FS_DBL
						"\n", func_profile->func_name, func_profile->sec);
			}
			else
			{
				zbx_snprintf_alloc(&str, &str_alloc, &str_offset, "%s() %s locked:%u busy:" ZBX_FS_DBL
						" wait:"ZBX_FS_DBL "\n", func_profile->func_name,
						get_scope_string(func_profile->scope), func_profile->locked,
						func_profile->sec - func_profile->sec_wait, func_profile->sec_wait);

				total_wait_lock += func_profile->sec_wait;
				total_busy_lock += func_profile->sec - func_profile->sec_wait;
			}
		}

		if (0 != str_offset)
		{
			zabbix_log(LOG_LEVEL_INFORMATION, "Profiling information: %s", str);
			zabbix_log(LOG_LEVEL_INFORMATION, "Time spent holding locks:" ZBX_FS_DBL
					" Time spent waiting for locks:" ZBX_FS_DBL "\n", total_busy_lock,
					total_wait_lock);
		}
	}
}

void	zbx_prof_enable(void)
{
	zbx_prof_enable_requested = 1;
}

void	zbx_prof_disable(void)
{
	zbx_prof_enable_requested = 0;
}

static void	zbx_reset_prof(void)
{
	if (0 != zbx_prof_initialized)
		zbx_vector_func_profiles_clear_ext(&zbx_func_profiles, func_profile_free);
}

void	zbx_prof_update(double time_now)
{
	static double	last_update;

	if (1 == zbx_prof_enable_requested)
	{
		zbx_prof_init();
		zbx_prof_enabled = 1;
	}
	else
		zbx_prof_enabled = 0;

	if (30 < time_now - last_update)
	{
		last_update = time_now;

		if (1 == zbx_prof_enabled)
			zbx_print_prof();
		else
			zbx_reset_prof();
	}
}
