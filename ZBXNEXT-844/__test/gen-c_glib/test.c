#include <stdio.h>
#include <assert.h>

#include <thrift.h>
#include <transport/thrift_socket.h>
#include <transport/thrift_framed_transport.h>
#include <protocol/thrift_protocol.h>
#include <protocol/thrift_binary_protocol.h>

#include "cassandra.h"

int main()
{
	ThriftSocket		*tsocket;
	ThriftTransport		*transport;
	ThriftBinaryProtocol	*protocol2;
	ThriftProtocol		*protocol;
	CassandraClient		*client;

	InvalidRequestException	*ire = NULL;
	GError			*error = NULL;

	g_type_init();

	tsocket = g_object_new(THRIFT_TYPE_SOCKET, "hostname", "localhost", "port", 9160, NULL);
	assert(NULL != tsocket);

	transport = g_object_new(THRIFT_TYPE_FRAMED_TRANSPORT, "transport", THRIFT_TRANSPORT(tsocket), NULL);
	assert(NULL != transport);

	protocol2 = g_object_new(THRIFT_TYPE_BINARY_PROTOCOL, "transport", transport, NULL);
	assert(NULL != protocol2);

	protocol = THRIFT_PROTOCOL(protocol2);
	assert(NULL != protocol);

	assert(TRUE == thrift_framed_transport_open(transport, NULL));
	assert(TRUE == thrift_framed_transport_is_open(transport));

	client = g_object_new(TYPE_CASSANDRA_CLIENT, "input_protocol", protocol, "output_protocol", protocol, NULL);
	assert(NULL != client);

	printf("been here 1\n"); fflush(stdout);
	cassandra_client_set_keyspace(CASSANDRA_IF(client), "Twissandra", &ire, &error);
	printf("been here 2\n"); fflush(stdout);

	g_object_unref(client);

	assert(TRUE == thrift_framed_transport_close(transport, NULL));

	g_object_unref(protocol2);
	g_object_unref(transport);
	g_object_unref(tsocket);

	return 0;
}

/*

int main(int argc, char *argv[]){
  try{
    boost::shared_ptr<TTransport> socket = boost::shared_ptr<TSocket>(new TSocket("127.0.0.1", 9160));
    boost::shared_ptr<TTransport> tr = boost::shared_ptr<TFramedTransport>(new TFramedTransport (socket));
    boost::shared_ptr<TProtocol> p = boost::shared_ptr<TBinaryProtocol>(new TBinaryProtocol(tr));

    CassandraClient cass(p);
    tr->open();

    cass.set_keyspace("Keyspace1");

    string key = "1";
    ColumnParent cparent;
    cparent.column_family = "Standard1";
    Column c;
    c.name = "name";
    c.value = "John Smith";

    // have to go through all of this just to get the timestamp in ms
    struct timeval td;
    gettimeofday(&td, NULL);
    int64_t ms = td.tv_sec;
    ms = ms * 1000;
    int64_t usec = td.tv_usec;
    usec = usec / 1000;
    ms += usec;
    c.timestamp = ms;

    // insert the "name" column
    cass.insert(key, cparent, c, ConsistencyLevel::ONE);

    // insert another column, "age"
    c.name = "age";
    c.value = "42";
    cass.insert(key, cparent, c, ConsistencyLevel::ONE);

    // get a single cell
    ColumnPath cp;
    cp.__isset.column = true;           // this must be set of you'll get an error re: Padraig O'Sullivan
    cp.column = "name";
    cp.column_family = "Standard1";
    cp.super_column = "";
    ColumnOrSuperColumn sc;

    cass.get(sc, key, cp, ConsistencyLevel::ONE);
    printf("Column [%s]  Value [%s]  TS [%lld]\n",
      sc.column.name.c_str(), sc.column.value.c_str(), sc.column.timestamp);

    // get the entire row for a key
    SliceRange sr;
    sr.start = "";
    sr.finish = "";

    SlicePredicate sp;
    sp.slice_range = sr;
    sp.__isset.slice_range = true; // set __isset for the columns instead if you use them

    KeyRange range;
    range.start_key = key;
    range.end_key = "";
    range.__isset.start_key = true;
    range.__isset.end_key = true;

    vector<KeySlice> results;
    cass.get_range_slices(results, cparent, sp, range, ConsistencyLevel::ONE);
    for(size_t i=0; i<results.size(); i++){
      printf("Key: %s\n", results[i].key.c_str());
      for(size_t x=0; x<results[i].columns.size(); x++){
        printf("Column: %s  Value: %s\n", results[i].columns[x].column.name.c_str(),
          results[i].columns[x].column.value.c_str());
      }
    }

    tr->close();
  }catch(TTransportException te){
    printf("Exception: %s  [%d]\n", te.what(), te.getType());
  }catch(InvalidRequestException ire){
    printf("Exception: %s  [%s]\n", ire.what(), ire.why.c_str());
  }catch(NotFoundException nfe){
    printf("Exception: %s\n", nfe.what());
  }
  printf("Done!!!\n");
  return;
}
*/
