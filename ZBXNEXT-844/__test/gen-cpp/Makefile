install: test

test: test.cpp Cassandra.cpp Cassandra.h cassandra_types.cpp cassandra_types.h cassandra_constants.cpp cassandra_constants.h
	g++ \
		-I/usr/local/include/thrift -lthrift \
		Cassandra.cpp Cassandra.h cassandra_types.cpp cassandra_types.h cassandra_constants.cpp cassandra_constants.h \
		test.cpp -o test
