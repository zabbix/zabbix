#ifndef MON_SYSINFO_H
#define MON_SYSINFO_H
 
float	INODE(const char * mountPoint);
float	DF(const char * mountPoint);
float	getPROC(char *file,int lineno,int fieldno);
float	FREEMEM(void);
float	PROCLOAD(void);
float	PROCLOAD5(void);
float	PROCLOAD15(void);
float	SWAPFREE(void);
float	EXECUTE(char *command);

#endif
