#ifndef MON_DEBUG_H
#define MON_DEBUG_H
 
enum
{
	dbg_fatal = 0,
	dbg_syserr,
	dbg_syswarn,
	dbg_proginfo
};

void	dbg_init( int level, char *filename );
void	dbg_flush( void );
void	dbg_write(int level, const char *fmt, ...);

#endif
