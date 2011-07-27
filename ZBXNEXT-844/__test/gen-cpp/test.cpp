#include <string>
#include <vector>
using std::string;
using std::vector;

#include "Thrift.h"
#include "transport/TSocket.h"
#include "transport/TTransport.h"
#include "transport/TBufferTransports.h"
#include "protocol/TProtocol.h"
#include "protocol/TBinaryProtocol.h"
#include "Cassandra.h"

using namespace apache::thrift;
using namespace apache::thrift::transport;
using namespace apache::thrift::protocol;
using namespace org::apache::cassandra;

int main(int argc, char *argv[]){
  try{
    boost::shared_ptr<TTransport> socket = boost::shared_ptr<TSocket>(new TSocket("127.0.0.1", 9160));
    boost::shared_ptr<TTransport> tr = boost::shared_ptr<TFramedTransport>(new TFramedTransport (socket));
    boost::shared_ptr<TProtocol> p = boost::shared_ptr<TBinaryProtocol>(new TBinaryProtocol(tr));
    CassandraClient cass(p);
    tr->open();

    cass.set_keyspace("twissandra");

    string key = "someone";

    Column column;
    column.name = "password";
    column.value = "p@a55w0rd";

    // have to go through all of this just to get the timestamp in ms
    /*
    struct timeval td;
    gettimeofday(&td, NULL);
    int64_t ms = td.tv_sec;
    ms = ms * 1000;
    int64_t usec = td.tv_usec;
    usec = usec / 1000;
    ms += usec;
    column.timestamp = ms;
    */

    ColumnParent cparent;
    cparent.column_family = "users";

    // insert the "name" column
    cass.insert(key, cparent, column, ConsistencyLevel::ONE);

    /*
    // insert another column, "age"
    c.name = "age";
    c.value = "26";
    cass.insert(key, cparent, c, ConsistencyLevel::ONE);
    */

    /* OUTDATED!!!
    ColumnPath cpath;
    cpath.column_family = "users";
    cpath.__isset.column = true;
    cpath.column = "password";

    cass.insert(key, cpath, column, ConsistencyLevel::ONE);
    */

    /* WORKS!!!

    string key = "jsmith";

    // get a single cell
    ColumnPath cp;
    cp.__isset.column = true;           // this must be set of you'll get an error re: Padraig O'Sullivan
    cp.column = "password";
    cp.column_family = "users";
    cp.super_column = "";
    ColumnOrSuperColumn sc;

    cass.get(sc, key, cp, ConsistencyLevel::ONE);
    printf("Column [%s]  Value [%s]  TS [%lld]\n",
      sc.column.name.c_str(), sc.column.value.c_str(), sc.column.timestamp);

      */

    /*
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
    */

    tr->close();
  }catch(TTransportException te){
    printf("Exception: %s  [%d]\n", te.what(), te.getType());
  }catch(InvalidRequestException ire){
    printf("Exception: %s  [%s]\n", ire.what(), ire.why.c_str());
  }catch(NotFoundException nfe){
    printf("Exception: %s\n", nfe.what());
  }
  printf("Done!!!\n");
  return 0;
}
