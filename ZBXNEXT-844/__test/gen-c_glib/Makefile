install: test

test: test.c cassandra.c cassandra.h cassandra_types.c cassandra_types.h
	gcc \
		-I/usr/lib/glib-2.0/include -I/usr/include/glib-2.0 -I/usr/local/include/thrift \
		-lglib-2.0 -lgobject-2.0 -lthrift -lthrift_c_glib \
		cassandra.c cassandra.h cassandra_types.c cassandra_types.h \
		test.c -o test
