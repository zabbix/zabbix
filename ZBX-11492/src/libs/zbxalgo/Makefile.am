## Process this file with automake to produce Makefile.in

noinst_LIBRARIES = libzbxalgo.a

if SERVER
EVALUATE_C = evaluate.c
else
if PROXY
EVALUATE_C = evaluate.c
endif
endif

libzbxalgo_a_SOURCES = \
	algodefs.c \
	binaryheap.c \
	$(EVALUATE_C) \
	hashmap.c \
	hashset.c \
	int128.c \
	prediction.c \
	vector.c \
	vectorimpl.h
