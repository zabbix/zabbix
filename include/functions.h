#ifndef MON_FUNCTIONS_H
#define MON_FUNCTIONS_H

int	evaluate_LAST(float *Result,int ItemId,int Parameter);
int	evaluate_MIN(float *Result,int ItemId,int Parameter);
int	evaluate_MAX(float *Result,int ItemId,int Parameter);
int	evaluate_PREV(float *Result,int ItemId,int Parameter);
int	evaluate_DIFF(float *Result,int ItemId,int Parameter);
int	evaluate_NODATA(float *Result,int ItemId,int Parameter);
int	updateFunctions( int ItemId );

#endif
