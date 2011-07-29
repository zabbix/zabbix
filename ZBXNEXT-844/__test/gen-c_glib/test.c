#include <stdio.h>
#include <stdlib.h>
#include <assert.h>

#include <thrift.h>
#include <transport/thrift_socket.h>
#include <transport/thrift_framed_transport.h>
#include <protocol/thrift_protocol.h>
#include <protocol/thrift_binary_protocol.h>

#include "cassandra.h"

static void	output_byte_array(const char *label, const GByteArray *array)
{
	int	i;

	printf("%s='", label);

	for (i = 0; i < array->len; i++)
		printf("%02x", array->data[i]);

	printf("' ");
}

static void	report_error(InvalidRequestException *ire, NotFoundException *nfe, UnavailableException *ue, TimedOutException *toe, GError *error)
{
	if (NULL != error)
	{
		printf("got error: %s\n", error->message);
		g_error_free(error);
	}

	if (NULL != ire)
	{
		printf("got InvalidRequestException: %s\n", ire->why);
		g_object_unref(ire);
	}

	if (NULL != nfe)
	{
		printf("got NotFoundException\n");
		g_object_unref(nfe);
	}

	if (NULL != ue)
	{
		printf("got UnavailableException");
		g_object_unref(ue);
	}

	if (NULL != toe)
	{
		printf("got TimedOutException\n");
		g_object_unref(toe);
	}
}

static void	test_keyspace(CassandraClient *client)
{
	InvalidRequestException	*ire = NULL;
	GError			*error = NULL;

	if (FALSE == cassandra_client_set_keyspace(CASSANDRA_IF(client), "history_keyspace", &ire, &error))
	{
		report_error(ire, NULL, NULL, NULL, error);
		exit(EXIT_FAILURE);
	}
}

static void	test_set_value(CassandraClient *client)
{
	InvalidRequestException	*ire = NULL;
	UnavailableException	*ue = NULL;
	TimedOutException	*toe = NULL;
	GError			*error = NULL;

	GByteArray		*key;
	GByteArray		*name;
	GByteArray		*value;
	Column			*column;
	ColumnParent		*column_parent;

	key = g_byte_array_new();
	g_byte_array_append(key, "\0\0\0\0\0\0\0\1", 8);

	name = g_byte_array_new();
	g_byte_array_append(name, "\1", 1);

	value = g_byte_array_new();
	g_byte_array_append(value, "\1\1\1\1\1\1\1\1", 8);

	column_parent = g_object_new(TYPE_COLUMN_PARENT, NULL);
	column_parent->column_family = "metric2";

	column = g_object_new(TYPE_COLUMN, NULL);
	column->name = name;
	column->value = value;
	column->__isset_value = TRUE;
	column->timestamp = time(NULL);
	column->__isset_timestamp = TRUE;

	if (FALSE == cassandra_client_insert(CASSANDRA_IF(client), key, column_parent, column, CONSISTENCY_LEVEL_ONE, &ire, &ue, &toe, &error))
	{
		report_error(ire, NULL, ue, toe, error);
		exit(EXIT_FAILURE);
	}

	g_object_unref(column);
	g_object_unref(column_parent);
	g_byte_array_free(value, TRUE);
	g_byte_array_free(name, TRUE);
	g_byte_array_free(key, TRUE);
}

static void	test_get_value(CassandraClient *client)
{
	InvalidRequestException	*ire = NULL;
	NotFoundException	*nfe = NULL;
	UnavailableException	*ue = NULL;
	TimedOutException	*toe = NULL;
	GError			*error = NULL;

	GByteArray		*key;
	GByteArray		*column;
	ColumnPath		*column_path;
	ColumnOrSuperColumn	*query_result = NULL;

	key = g_byte_array_new();
	g_byte_array_append(key, "\0\0\0\0\0\0\0\1", 8);

	column = g_byte_array_new();
	g_byte_array_append(column, "\2", 1);

	column_path = g_object_new(TYPE_COLUMN_PATH, NULL);
	column_path->column_family = "metric2";
	column_path->__isset_column = TRUE;
	column_path->column = column;

	if (FALSE == cassandra_client_get(CASSANDRA_IF(client), &query_result, key, column_path, CONSISTENCY_LEVEL_ONE, &ire, &nfe, &ue, &toe, &error))
	{
		report_error(ire, nfe, ue, toe, error);
		exit(EXIT_FAILURE);
	}

	output_byte_array("column", column);
	output_byte_array("name", query_result->column->name);
	output_byte_array("value", query_result->column->value);
	printf("\n");

	/* should free query_result? */
	g_object_unref(column_path);
	g_byte_array_free(column, TRUE);
	g_byte_array_free(key, TRUE);
}

int main()
{
	ThriftSocket		*tsocket;
	ThriftTransport		*transport;
	ThriftBinaryProtocol	*protocol2;
	ThriftProtocol		*protocol;
	CassandraClient		*client;

	g_type_init();

	tsocket = g_object_new(THRIFT_TYPE_SOCKET, "hostname", "localhost", "port", 9160, NULL);
	transport = g_object_new(THRIFT_TYPE_FRAMED_TRANSPORT, "transport", THRIFT_TRANSPORT(tsocket), NULL);
	protocol2 = g_object_new(THRIFT_TYPE_BINARY_PROTOCOL, "transport", transport, NULL);
	protocol = THRIFT_PROTOCOL(protocol2);

	assert(TRUE == thrift_framed_transport_open(transport, NULL));
	assert(TRUE == thrift_framed_transport_is_open(transport));

	client = g_object_new(TYPE_CASSANDRA_CLIENT, "input_protocol", protocol, "output_protocol", protocol, NULL);

	test_keyspace(client);
	test_set_value(client);
	test_get_value(client);

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
