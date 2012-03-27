<?php
require_once('connection.php');
require_once('uuid.php');

/**
 * @package phpcassa
 * @subpackage columnfamily
 */
class CassandraUtil {

	/**
	 * Creates a UUID object from a byte representation.
	 * @param string $bytes the byte representation of a UUID, which is
	 *        what is returned from functions like uuid1()
	 * @return a UUID object
	 */
	static public function import($bytes) {
		return UUID::import($bytes);
	}

	/**
	 * Generate a v1 UUID (timestamp based)
	 * @return string a byte[] representation of a UUID
	 * @param string $node what to use for the MAC portion of the UUID.  This will be generated
	 *        randomly if left as NULL
	 * @param int $time timestamp to use for the UUID.  This should be a number of microseconds
	 *        since the UNIX epoch.
	 */
	static public function uuid1($node=null, $time=null) {
		$uuid = UUID::mint(1, $node, null, $time);
		return $uuid->bytes;
	}

	/**
	 * Generate a v3 UUID
	 * @return string a byte[] representation of a UUID
	 */
	static public function uuid3($node=null, $namespace=null) {
		$uuid = UUID::mint(3, $node, $namespace);
		return $uuid->bytes;
	}

	/**
	 * Generate a v4 UUID
	 * @return string a byte[] representation of a UUID
	 */
	static public function uuid4() {
		$uuid = UUID::mint(4);
		return $uuid->bytes;
	}

	/**
	 * Generate a v5 UUID
	 * @return string a byte[] representation of a UUID
	 */
	static public function uuid5($node, $namespace=null) {
		$uuid = UUID::mint(5, $node, $namespace);
		return $uuid->bytes;
	}

	/**
	 * Get a timestamp with microsecond precision
	 */
	static public function get_time() {
		// By Zach Buller (zachbuller@gmail.com)
		$time1 = microtime();
		settype($time1, 'string'); //convert to string to keep trailing zeroes
		$time2 = explode(" ", $time1);
		$sub_secs = preg_replace('/0./', '', $time2[0], 1);
		$time3 = ($time2[1].$sub_secs)/100;
		return $time3;
	}

	/**
	 * Constructs an IndexExpression to be used in an IndexClause, which can
	 * be used with get_indexed_slices().
	 * @param mixed $column_name the name of the column this expression will apply to;
	 *        this column may or may not be indexed
	 * @param mixed $value the value that will be compared to column values using op
	 * @param classandra_IndexOperator $op the binary operator to apply to column values
	 *        and the 'value' parameter.  Defaults to testing for equality.
	 * @return cassandra_IndexExpression
	 */
	static public function create_index_expression($column_name, $value,
		$op=cassandra_IndexOperator::EQ) {
		$ie = new cassandra_IndexExpression();
		$ie->column_name = $column_name;
		$ie->value = $value;
		$ie->op = $op;
		return $ie;
	}

	/**
	 * Constructs a cassandra_IndexClause for use with get_indexed_slices().
	 * @param cassandra_IndexExpression[] $expr_list the list of expressions to match; at
	 *        least one of these must be on an indexed column
	 * @param string $start_key the key to begin searching from
	 * @param int $count the number of results to return
	 * @return cassandra_IndexClause
	 */
	static public function create_index_clause($expr_list, $start_key='',
		$count=ColumnFamily::DEFAULT_ROW_COUNT) {
		$ic = new cassandra_IndexClause();
		$ic->expressions = $expr_list;
		$ic->start_key = $start_key;
		$ic->count = $count;
		return $ic;
	}
}

/**
 * Representation of a ColumnFamily in Cassandra.  This may be used for
 * standard column families or super column families. All data insertions,
 * deletions, or retrievals will go through a ColumnFamily.
 *
 * @package phpcassa
 * @subpackage columnfamily
 */
class ColumnFamily {

	/** The default limit to the number of rows retrieved in queries. */
	const DEFAULT_ROW_COUNT = 2147483647; // default max # of rows for get_range()
	/** The default limit to the number of columns retrieved in queries. */
	const DEFAULT_COLUMN_COUNT = 2147483647; // default max # of columns for get()
	/** The maximum number that can be returned by get_count(). */
	const MAX_COUNT = 2147483647; # 2^31 - 1

	const DEFAULT_BUFFER_SIZE = 1024;

	public $client;
	private $column_family;
	private $is_super;
	private $cf_data_type;
	private $col_name_type;
	private $supercol_name_type;
	private $col_type_dict;


	public $autopack_names;
	public $autopack_values;
	public $autopack_keys;

	/** @var cassandra_ConsistencyLevel the default read consistency level */
	public $read_consistency_level;
	/** @var cassandra_ConsistencyLevel the default write consistency level */
	public $write_consistency_level;

	/** @var bool If true, column values in data fetching operations will be
	 * replaced by an array of the form array(column_value, column_timestamp). */
	public $include_timestamp = false;

	/**
	 * @var int When calling `get_range`, the intermediate results need
	 *       to be buffered if we are fetching many rows, otherwise the Cassandra
	 *       server will overallocate memory and fail.  This is the size of
	 *       that buffer in number of rows. The default is 1024.
	 */
	public $buffer_size = 1024;

	/**
	 * Constructs a ColumnFamily.
	 *
	 * @param Connection $connection the connection to use for this ColumnFamily
	 * @param string $column_family the name of the column family in Cassandra
	 * @param bool $autopack_names whether or not to automatically convert column names
	 *        to and from their binary representation in Cassandra
	 *        based on their comparator type
	 * @param bool $autopack_values whether or not to automatically convert column values
	 *        to and from their binary representation in Cassandra
	 *        based on their validator type
	 * @param cassandra_ConsistencyLevel $read_consistency_level the default consistency
	 *        level for read operations on this column family
	 * @param cassandra_ConsistencyLevel $write_consistency_level the default consistency
	 *        level for write operations on this column family
	 * @param int $buffer_size When calling `get_range`, the intermediate results need
	 *        to be buffered if we are fetching many rows, otherwise the Cassandra
	 *        server will overallocate memory and fail.  This is the size of
	 *        that buffer in number of rows.
	 */
	public function __construct($pool,
		$column_family,
		$autopack_names=true,
		$autopack_values=true,
		$read_consistency_level=cassandra_ConsistencyLevel::ONE,
		$write_consistency_level=cassandra_ConsistencyLevel::ONE,
		$buffer_size=self::DEFAULT_BUFFER_SIZE) {

		$this->pool = $pool;
		$this->column_family = $column_family;
		$this->read_consistency_level = $read_consistency_level;
		$this->write_consistency_level = $write_consistency_level;
		$this->buffer_size = $buffer_size;

		$ks = $this->pool->describe_keyspace();

		$cf_def = null;
		foreach($ks->cf_defs as $cfdef) {
			if ($cfdef->name == $this->column_family) {
				$cf_def = $cfdef;
				break;
			}
		}
		if ($cf_def == null)
			throw new cassandra_NotFoundException();
		else
			$this->cfdef = $cf_def;

		$this->cf_data_type = 'BytesType';
		$this->col_name_type = 'BytesType';
		$this->supercol_name_type = 'BytesType';
		$this->key_type = 'BytesType';
		$this->col_type_dict = array();

		$this->is_super = $this->cfdef->column_type === 'Super';
		$this->set_autopack_names($autopack_names);
		$this->set_autopack_values($autopack_values);
		$this->set_autopack_keys(true);
	}

	/**
	 * @param bool $pack_names whether or not column names are automatically packed/unpacked
	 */
	public function set_autopack_names($pack_names) {
		if ($pack_names) {
			if ($this->autopack_names)
				return;
			$this->autopack_names = true;
			if (!$this->is_super) {
				$this->col_name_type = self::extract_type_name($this->cfdef->comparator_type);
			} else {
				$this->col_name_type = self::extract_type_name($this->cfdef->subcomparator_type);
				$this->supercol_name_type = self::extract_type_name($this->cfdef->comparator_type);
			}
		} else {
			$this->autopack_names = false;
		}
	}

	/**
	 * @param bool $pack_values whether or not column values are automatically packed/unpacked
	 */
	public function set_autopack_values($pack_values) {
		if ($pack_values) {
			$this->autopack_values = true;
			$this->cf_data_type = self::extract_type_name($this->cfdef->default_validation_class);
			foreach($this->cfdef->column_metadata as $coldef) {
				$this->col_type_dict[$coldef->name] =
						self::extract_type_name($coldef->validation_class);
			}
		} else {
			$this->autopack_values = false;
		}
	}

	/**
	 * @param bool $pack_keys whether or not keys are automatically packed/unpacked
	 *
	 * Available since Cassandra 0.8.0.
	 */
	public function set_autopack_keys($pack_keys) {
		if ($pack_keys) {
			$this->autopack_keys = true;
			if (property_exists('cassandra_CfDef', "key_validation_class")) {
				$this->key_type = self::extract_type_name($this->cfdef->key_validation_class);
			} else {
				$this->key_type = 'BytesType';
			}
		} else {
			$this->autopack_keys = false;
		}
	}

	/**
	 * Fetch a row from this column family.
	 *
	 * @param string $key row key to fetch
	 * @param mixed[] $columns limit the columns or super columns fetched to this list
	 * @param mixed $column_start only fetch columns with name >= this
	 * @param mixed $column_finish only fetch columns with name <= this
	 * @param bool $column_reversed fetch the columns in reverse order
	 * @param int $column_count limit the number of columns returned to this amount
	 * @param mixed $super_column return only columns in this super column
	 * @param cassandra_ConsistencyLevel $read_consistency_level affects the guaranteed
	 *        number of nodes that must respond before the operation returns
	 *
	 * @return mixed array(column_name => column_value)
	 */
	public function get($key,
		$columns=null,
		$column_start="",
		$column_finish="",
		$column_reversed=False,
		$column_count=self::DEFAULT_COLUMN_COUNT,
		$super_column=null,
		$read_consistency_level=null) {

		$column_parent = $this->create_column_parent($super_column);
		$predicate = $this->create_slice_predicate($columns, $column_start, $column_finish,
			$column_reversed, $column_count);

		$resp = $this->pool->call("get_slice",
			$this->pack_key($key),
			$column_parent,
			$predicate,
			$this->rcl($read_consistency_level));

		if (count($resp) == 0)
			throw new cassandra_NotFoundException();

		return $this->supercolumns_or_columns_to_array($resp);
	}

	/**
	 * Fetch a set of rows from this column family.
	 *
	 * @param string[] $keys row keys to fetch
	 * @param mixed[] $columns limit the columns or super columns fetched to this list
	 * @param mixed $column_start only fetch columns with name >= this
	 * @param mixed $column_finish only fetch columns with name <= this
	 * @param bool $column_reversed fetch the columns in reverse order
	 * @param int $column_count limit the number of columns returned to this amount
	 * @param mixed $super_column return only columns in this super column
	 * @param cassandra_ConsistencyLevel $read_consistency_level affects the guaranteed
	 *        number of nodes that must respond before the operation returns
	 * @param int $buffer_size the number of keys to multiget at a single time. If your
	 *        rows are large, having a high buffer size gives poor performance; if your
	 *        rows are small, consider increasing this value.
	 *
	 * @return mixed array(key => array(column_name => column_value))
	 */
	public function multiget($keys,
		$columns=null,
		$column_start="",
		$column_finish="",
		$column_reversed=False,
		$column_count=self::DEFAULT_COLUMN_COUNT,
		$super_column=null,
		$read_consistency_level=null,
		$buffer_size=16)  {

		$column_parent = $this->create_column_parent($super_column);
		$predicate = $this->create_slice_predicate($columns, $column_start, $column_finish,
			$column_reversed, $column_count);

		$ret = array();
		foreach($keys as $key) {
			$ret[$key] = null;
		}

		$cl = $this->rcl($read_consistency_level);

		$resp = array();
		if(count($keys) <= $buffer_size) {
			$resp = $this->pool->call("multiget_slice",
				array_map(array($this, "pack_key"), $keys),
				$column_parent,
				$predicate,
				$cl);
		} else {
			$subset_keys = array();
			$i = 0;
			foreach($keys as $key) {
				$i += 1;
				$subset_keys[] = $key;
				if ($i == $buffer_size) {
					$sub_resp = $this->pool->call("multiget_slice",
						array_map(array($this, "pack_key"), $subset_keys),
						$column_parent,
						$predicate,
						$cl);
					$subset_keys = array();
					$i = 0;
					$resp = $resp + $sub_resp;
				}
			}
			if (count($subset_keys) != 0) {
				$sub_resp = $this->pool->call("multiget_slice",
					array_map(array($this, "pack_key"), $subset_keys),
					$column_parent,
					$predicate,
					$cl);
				$resp = $resp + $sub_resp;
			}
		}

		$non_empty_keys = array();
		foreach($resp as $key => $val) {
			if (count($val) > 0) {
				$unpacked_key = $this->unpack_key($key);
				$non_empty_keys[] = $unpacked_key;
				$ret[$unpacked_key] = $this->supercolumns_or_columns_to_array($val);
			}
		}

		foreach($keys as $key) {
			if (!in_array($key, $non_empty_keys))
				unset($ret[$key]);
		}
		return $ret;
	}

	/**
	 * Count the number of columns in a row.
	 *
	 * @param string $key row to be counted
	 * @param mixed[] $columns limit the possible columns or super columns counted to this list
	 * @param mixed $column_start only count columns with name >= this
	 * @param mixed $column_finish only count columns with name <= this
	 * @param mixed $super_column count only columns in this super column
	 * @param cassandra_ConsistencyLevel $read_consistency_level affects the guaranteed
	 *        number of nodes that must respond before the operation returns
	 *
	 * @return int
	 */
	public function get_count($key,
		$columns=null,
		$column_start='',
		$column_finish='',
		$super_column=null,
		$read_consistency_level=null) {

		$column_parent = $this->create_column_parent($super_column);
		$predicate = $this->create_slice_predicate($columns, $column_start, $column_finish,
			false, self::MAX_COUNT);

		$packed_key = $this->pack_key($key);
		return $this->pool->call("get_count", $packed_key, $column_parent, $predicate,
			$this->rcl($read_consistency_level));
	}

	/**
	 * Count the number of columns in a set of rows.
	 *
	 * @param string[] $keys rows to be counted
	 * @param mixed[] $columns limit the possible columns or super columns counted to this list
	 * @param mixed $column_start only count columns with name >= this
	 * @param mixed $column_finish only count columns with name <= this
	 * @param mixed $super_column count only columns in this super column
	 * @param cassandra_ConsistencyLevel $read_consistency_level affects the guaranteed
	 *        number of nodes that must respond before the operation returns
	 *
	 * @return mixed array(row_key => row_count)
	 */
	public function multiget_count($keys,
		$columns=null,
		$column_start='',
		$column_finish='',
		$super_column=null,
		$read_consistency_level=null) {

		$column_parent = $this->create_column_parent($super_column);
		$predicate = $this->create_slice_predicate($columns, $column_start, $column_finish,
			false, self::MAX_COUNT);

		$packed_keys = array_map(array($this, "pack_key"), $keys);
		$results = $this->pool->call("multiget_count", $packed_keys, $column_parent, $predicate,
			$this->rcl($read_consistency_level));
		$ret = array();
		foreach ($results as $key => $count) {
			$unpacked_key = $this->unpack_key($key);
			$ret[$key] = $count;
		}
		return $ret;
	}

	/**
	 * Get an iterator over a range of rows.
	 *
	 * @param string $key_start fetch rows with a key >= this
	 * @param string $key_finish fetch rows with a key <= this
	 * @param int $row_count limit the number of rows returned to this amount
	 * @param mixed[] $columns limit the columns or super columns fetched to this list
	 * @param mixed $column_start only fetch columns with name >= this
	 * @param mixed $column_finish only fetch columns with name <= this
	 * @param bool $column_reversed fetch the columns in reverse order
	 * @param int $column_count limit the number of columns returned to this amount
	 * @param mixed $super_column return only columns in this super column
	 * @param cassandra_ConsistencyLevel $read_consistency_level affects the guaranteed
	 *        number of nodes that must respond before the operation returns
	 * @param int $buffer_size When calling `get_range`, the intermediate results need
	 *        to be buffered if we are fetching many rows, otherwise the Cassandra
	 *        server will overallocate memory and fail.  This is the size of
	 *        that buffer in number of rows.
	 *
	 * @return RangeColumnFamilyIterator
	 */
	public function get_range($key_start="",
		$key_finish="",
		$row_count=self::DEFAULT_ROW_COUNT,
		$columns=null,
		$column_start="",
		$column_finish="",
		$column_reversed=false,
		$column_count=self::DEFAULT_COLUMN_COUNT,
		$super_column=null,
		$read_consistency_level=null,
		$buffer_size=null) {

		if ($buffer_size == null)
			$buffer_size = $this->buffer_size;
		if ($buffer_size < 2) {
			$ire = new cassandra_InvalidRequestException();
			$ire->message = 'buffer_size cannot be less than 2';
			throw $ire;
		}

		$column_parent = $this->create_column_parent($super_column);
		$predicate = $this->create_slice_predicate($columns, $column_start,
			$column_finish, $column_reversed,
			$column_count);

		$packed_key_start = $this->pack_key($key_start);
		$packed_key_finish = $this->pack_key($key_finish);
		return new RangeColumnFamilyIterator($this, $buffer_size,
			$packed_key_start, $packed_key_finish,
			$row_count, $column_parent, $predicate,
			$this->rcl($read_consistency_level));
	}

	/**
	 * Fetch a set of rows from this column family based on an index clause.
	 *
	 * @param cassandra_IndexClause $index_clause limits the keys that are returned based
	 *        on expressions that compare the value of a column to a given value.  At least
	 *        one of the expressions in the IndexClause must be on an indexed column. You
	 *        can use the CassandraUtil::create_index_expression() and
	 *        CassandraUtil::create_index_clause() methods to help build this.
	 * @param mixed[] $columns limit the columns or super columns fetched to this list
	 * @param mixed $column_start only fetch columns with name >= this
	 * @param mixed $column_finish only fetch columns with name <= this
	 * @param bool $column_reversed fetch the columns in reverse order
	 * @param int $column_count limit the number of columns returned to this amount
	 * @param mixed $super_column return only columns in this super column
	 * @param cassandra_ConsistencyLevel $read_consistency_level affects the guaranteed
	 * number of nodes that must respond before the operation returns
	 *
	 * @return mixed array(row_key => array(column_name => column_value))
	 */
	public function get_indexed_slices($index_clause,
		$columns=null,
		$column_start='',
		$column_finish='',
		$column_reversed=false,
		$column_count=self::DEFAULT_COLUMN_COUNT,
		$super_column=null,
		$read_consistency_level=null,
		$buffer_size=null) {

		if ($buffer_size == null)
			$buffer_size = $this->buffer_size;
		if ($buffer_size < 2) {
			$ire = new cassandra_InvalidRequestException();
			$ire->message = 'buffer_size cannot be less than 2';
			throw $ire;
		}

		$new_clause = new cassandra_IndexClause();
		foreach($index_clause->expressions as $expr) {
			$new_expr = new cassandra_IndexExpression();
			$new_expr->value = $this->pack_value($expr->value, $expr->column_name);
			$new_expr->column_name = $this->pack_name($expr->column_name);
			$new_expr->op = $expr->op;
			$new_clause->expressions[] = $new_expr;
		}
		$new_clause->start_key = $this->pack_key($index_clause->start_key);
		$new_clause->count = $index_clause->count;

		$column_parent = $this->create_column_parent($super_column);
		$predicate = $this->create_slice_predicate($columns, $column_start,
			$column_finish, $column_reversed,
			$column_count);

		return new IndexedColumnFamilyIterator($this, $new_clause, $buffer_size,
			$column_parent, $predicate,
			$this->rcl($read_consistency_level));
	}

	/**
	 * Insert or update columns in a row.
	 *
	 * @param string $key the row to insert or update the columns in
	 * @param mixed $columns array(column_name => column_value) the columns to insert or update
	 * @param int $timestamp the timestamp to use for this insertion. Leaving this as null will
	 *        result in a timestamp being generated for you
	 * @param int $ttl time to live for the columns; after ttl seconds they will be deleted
	 * @param cassandra_ConsistencyLevel $write_consistency_level affects the guaranteed
	 *        number of nodes that must respond before the operation returns
	 *
	 * @return int the timestamp for the operation
	 */
	public function insert($key,
		$columns,
		$timestamp=null,
		$ttl=null,
		$write_consistency_level=null) {

		if ($timestamp == null)
			$timestamp = CassandraUtil::get_time();

		$cfmap = array();
		$packed_key = $this->pack_key($key);
		$cfmap[$packed_key][$this->column_family] = $this->array_to_mutation($columns, $timestamp, $ttl);

		return $this->pool->call("batch_mutate", $cfmap, $this->wcl($write_consistency_level));
	}

	/**
	 * Increment or decrement a counter.
	 *
	 * `value` should be an integer, either positive or negative, to be added
	 * to a counter column. By default, `value` is 1.
	 *
	 * This method is not idempotent. Retrying a failed add may result
	 * in a double count. You should consider using a separate
	 * ConnectionPool with retries disabled for column families
	 * with counters.
	 *
	 * Only available in Cassandra 0.8.0 and later.
	 *
	 * @param string $key the row to insert or update the columns in
	 * @param mixed $column the column name of the counter
	 * @param int $value the amount to adjust the counter by
	 * @param mixed $super_column the super column to use if this is a
	 *        super column family
	 * @param cassandra_ConsistencyLevel $write_consistency_level affects the guaranteed
	 *        number of nodes that must respond before the operation returns
	 */
	public function add($key, $column, $value=1, $super_column=null,
		$write_consistency_level=null) {

		$cp = $this->create_column_parent($super_column);
		$packed_key = $this->pack_key($key);
		$counter = new cassandra_CounterColumn();
		$counter->name = $this->pack_name($column);
		$counter->value = $value;
		$this->pool->call("add", $packed_key, $cp, $counter, $this->wcl($write_consistency_level));
	}

	/**
	 * Insert or update columns in multiple rows. Note that this operation is only atomic
	 * per row.
	 *
	 * @param array $rows an array of keys, each of which maps to an array of columns. This
	 *        looks like array(key => array(column_name => column_value))
	 * @param int $timestamp the timestamp to use for these insertions. Leaving this as null will
	 *        result in a timestamp being generated for you
	 * @param int $ttl time to live for the columns; after ttl seconds they will be deleted
	 * @param cassandra_ConsistencyLevel $write_consistency_level affects the guaranteed
	 *        number of nodes that must respond before the operation returns
	 *
	 * @return int the timestamp for the operation
	 */
	public function batch_insert($rows, $timestamp=null, $ttl=null, $write_consistency_level=null) {
		if ($timestamp == null)
			$timestamp = CassandraUtil::get_time();

		$cfmap = array();
		foreach($rows as $key => $columns) {
			$packed_key = $this->pack_key($key);
			$cfmap[$packed_key][$this->column_family] = $this->array_to_mutation($columns, $timestamp, $ttl);
		}

		return $this->pool->call("batch_mutate", $cfmap, $this->wcl($write_consistency_level));
	}

	/**
	 * Remove columns from a row.
	 *
	 * @param string $key the row to remove columns from
	 * @param mixed[] $columns the columns to remove. If null, the entire row will be removed.
	 * @param mixed $super_column only remove this super column
	 * @param cassandra_ConsistencyLevel $write_consistency_level affects the guaranteed
	 *        number of nodes that must respond before the operation returns
	 *
	 * @return int the timestamp for the operation
	 */
	public function remove($key, $columns=null, $super_column=null, $write_consistency_level=null) {

		$timestamp = CassandraUtil::get_time();
		$packed_key = $this->pack_key($key);

		if ($columns == null || count($columns) == 1)
		{
			$cp = new cassandra_ColumnPath();
			$cp->column_family = $this->column_family;
			$cp->super_column = $this->pack_name($super_column, true);
			if ($columns != null) {
				if ($this->is_super && $super_column == null)
					$cp->super_column = $this->pack_name($columns[0], true);
				else
					$cp->column = $this->pack_name($columns[0], false);
			}
			return $this->pool->call("remove", $packed_key, $cp, $timestamp,
				$this->wcl($write_consistency_level));
		}

		$deletion = new cassandra_Deletion();
		$deletion->timestamp = $timestamp;
		$deletion->super_column = $this->pack_name($super_column, true);

		if ($columns != null) {
			$predicate = $this->create_slice_predicate($columns, '', '', false,
				self::DEFAULT_COLUMN_COUNT);
			$deletion->predicate = $predicate;
		}

		$mutation = new cassandra_Mutation();
		$mutation->deletion = $deletion;

		$mut_map = array($packed_key => array($this->column_family => array($mutation)));

		return $this->pool->call("batch_mutate", $mut_map, $this->wcl($write_consistency_level));
	}

	/**
	 * Remove a counter at the specified location.
	 *
	 * Note that counters have limited support for deletes: if you remove a
	 * counter, you must wait to issue any following update until the delete
	 * has reached all the nodes and all of them have been fully compacted.
	 *
	 * Available in Cassandra 0.8.0 and later.
	 *
	 * @param string $key the row to insert or update the columns in
	 * @param mixed $column the column name of the counter
	 * @param mixed $super_column the super column to use if this is a
	 *        super column family
	 * @param cassandra_ConsistencyLevel $write_consistency_level affects the guaranteed
	 *        number of nodes that must respond before the operation returns
	 */
	public function remove_counter($key, $column, $super_column=null,
		$write_consistency_level=null) {
		$cp = new cassandra_ColumnPath();
		$packed_key = $this->pack_key($key);
		$cp->column_family = $this->column_family;
		$cp->super_column = $this->pack_name($super_column, true);
		$cp->column = $this->pack_name($column);
		$this->pool->call("remove_counter", $packed_key, $cp, $this->wcl($write_consistency_level));
	}

	/**
	 * Mark the entire column family as deleted.
	 *
	 * From the user's perspective a successful call to truncate will result
	 * complete data deletion from cfname. Internally, however, disk space
	 * will not be immediatily released, as with all deletes in cassandra,
	 * this one only marks the data as deleted.
	 *
	 * The operation succeeds only if all hosts in the cluster at available
	 * and will throw an UnavailableException if some hosts are down.
	 */
	public function truncate() {
		return $this->pool->call("truncate", $this->column_family);
	}


	/********************* Helper functions *************************/

	private static $TYPES = array('BytesType', 'LongType', 'IntegerType',
		'UTF8Type', 'AsciiType', 'LexicalUUIDType',
		'TimeUUIDType');

	private static function extract_type_name($type_string) {
		if ($type_string == null or $type_string == '')
			return 'BytesType';

		$index = strrpos($type_string, '.');
		if ($index == false)
			return 'BytesType';

		$type = substr($type_string, $index + 1);
		if (!in_array($type, self::$TYPES))
			return 'BytesType';

		return $type;
	}

	private function rcl($read_consistency_level) {
		if ($read_consistency_level == null)
			return $this->read_consistency_level;
		else
			return $read_consistency_level;
	}

	private function wcl($write_consistency_level) {
		if ($write_consistency_level == null)
			return $this->write_consistency_level;
		else
			return $write_consistency_level;
	}

	private function create_slice_predicate($columns, $column_start, $column_finish,
		$column_reversed, $column_count) {

		$predicate = new cassandra_SlicePredicate();
		if ($columns !== null) {
			$packed_cols = array();
			foreach($columns as $col)
				$packed_cols[] = $this->pack_name($col, $this->is_super);
			$predicate->column_names = $packed_cols;
		} else {
			if ($column_start != null and $column_start != '')
				$column_start = $this->pack_name($column_start,
					$this->is_super,
					self::SLICE_START);
			if ($column_finish != null and $column_finish != '')
				$column_finish = $this->pack_name($column_finish,
					$this->is_super,
					self::SLICE_FINISH);

			$slice_range = new cassandra_SliceRange();
			$slice_range->count = $column_count;
			$slice_range->reversed = $column_reversed;
			$slice_range->start  = $column_start;
			$slice_range->finish = $column_finish;
			$predicate->slice_range = $slice_range;
		}
		return $predicate;
	}

	private function create_column_parent($super_column=null) {
		$column_parent = new cassandra_ColumnParent();
		$column_parent->column_family = $this->column_family;
		$column_parent->super_column = $this->pack_name($super_column, true);
		return $column_parent;
	}

	const NON_SLICE = 0;
	const SLICE_START = 1;
	const SLICE_FINISH = 2;

	private function pack_name($value, $is_supercol_name=false, $slice_end=self::NON_SLICE) {
		if (!$this->autopack_names)
			return $value;
		if ($value == null)
			return;
		if ($is_supercol_name)
			$d_type = $this->supercol_name_type;
		else
			$d_type = $this->col_name_type;

		return $this->pack($value, $d_type);
	}

	private function unpack_name($b, $is_supercol_name=false) {
		if (!$this->autopack_names)
			return $b;
		if ($b == null)
			return;

		if ($is_supercol_name)
			$d_type = $this->supercol_name_type;
		else
			$d_type = $this->col_name_type;

		return $this->unpack($b, $d_type);
	}

	public function pack_key($key) {
		if (!$this->autopack_keys)
			return $key;
		return $this->pack($key, $this->key_type);
	}

	public function unpack_key($b) {
		if (!$this->autopack_keys)
			return $b;
		return $this->unpack($b, $this->key_type);
	}

	private function get_data_type_for_col($col_name) {
		if (!in_array($col_name, array_keys($this->col_type_dict)))
			return $this->cf_data_type;
		else
			return $this->col_type_dict[$col_name];
	}

	private function pack_value($value, $col_name) {
		if (!$this->autopack_values)
			return $value;
		return $this->pack($value, $this->get_data_type_for_col($col_name));
	}

	private function unpack_value($value, $col_name) {
		if (!$this->autopack_values)
			return $value;
		return $this->unpack($value, $this->get_data_type_for_col($col_name));
	}

	private static function unpack_str($str, $len) {
		$tmp_arr = unpack("c".$len."chars", $str);
		$out_str = "";
		foreach($tmp_arr as $v)
			if($v > 0) { $out_str .= chr($v); }
		return $out_str;
	}

	private static function pack_str($str, $len) {
		$out_str = "";
		for($i=0; $i<$len; $i++)
			$out_str .= pack("c", ord(substr($str, $i, 1)));
		return $out_str;
	}

	private static function pack_long($value) {
		// If we are on a 32bit architecture we have to explicitly deal with
		// 64-bit twos-complement arithmetic since PHP wants to treat all ints
		// as signed and any int over 2^31 - 1 as a float
		if (PHP_INT_SIZE == 4) {
			$neg = $value < 0;

			if ($neg) {
				$value *= -1;
			}

			$hi = (int)($value / 4294967296);
			$lo = $value - $hi * 4294967296;

			if ($neg) {
				$hi = ~$hi;
				$lo = ~$lo;
				if (($lo & (int)0xffffffff) == (int)0xffffffff) {
					$lo = 0;
					$hi++;
				} else {
					$lo++;
				}
			}
			$data = pack('N2', $hi, $lo);
		} else {
			$hi = $value >> 32;
			$lo = $value & 0xFFFFFFFF;

			$data = pack('N2', $hi, $lo);
		}
		return $data;
	}

	private static function unpack_long($data) {
		$arr = unpack('N2', $data);

		// If we are on a 32bit architecture we have to explicitly deal with
		// 64-bit twos-complement arithmetic since PHP wants to treat all ints
		// as signed and any int over 2^31 - 1 as a float
		if (PHP_INT_SIZE == 4) {

			$hi = $arr[1];
			$lo = $arr[2];

			// TODO: use another way to convert to 64
			$hi = sprintf("%u", $hi);
			$lo = sprintf("%u", $lo);
			return bcadd(bcmul($hi, "4294967296", 0), $lo, 0);


			$isNeg = $hi  < 0;

			// Check for a negative
			if ($isNeg) {
				$hi = ~$hi & (int)0xffffffff;
				$lo = ~$lo & (int)0xffffffff;

				if ($lo == (int)0xffffffff) {
					$hi++;
					$lo = 0;
				} else {
					$lo++;
				}
			}

			// Force 32bit words in excess of 2G to pe positive - we deal wigh sign
			// explicitly below

			if ($hi & (int)0x80000000) {
				$hi &= (int)0x7fffffff;
				$hi += 0x80000000;
			}

			if ($lo & (int)0x80000000) {
				$lo &= (int)0x7fffffff;
				$lo += 0x80000000;
			}

			$value = $hi * 4294967296 + $lo;

			if ($isNeg)
				$value = 0 - $value;

		} else {
			// Upcast negatives in LSB bit
			if ($arr[2] & 0x80000000)
				$arr[2] = $arr[2] & 0xffffffff;

			// Check for a negative
			if ($arr[1] & 0x80000000) {
				$arr[1] = $arr[1] & 0xffffffff;
				$arr[1] = $arr[1] ^ 0xffffffff;
				$arr[2] = $arr[2] ^ 0xffffffff;
				$value = 0 - $arr[1]*4294967296 - $arr[2] - 1;
			} else {
				$value = $arr[1]*4294967296 + $arr[2];
			}
		}
		return $value;
	}

	private function pack($value, $data_type) {
		if ($data_type == 'LongType')
			return self::pack_long($value);
		else if ($data_type == 'IntegerType')
			return pack('N', $value); // Unsigned 32bit big-endian
		else if ($data_type == 'AsciiType')
			return self::pack_str($value, strlen($value));
		else if ($data_type == 'UTF8Type') {
			if (mb_detect_encoding($value, "UTF-8") != "UTF-8")
				$value = utf8_encode($value);
			return self::pack_str($value, strlen($value));
		}
		else if ($data_type == 'TimeUUIDType' or $data_type == 'LexicalUUIDType')
			return self::pack_str($value, 16);
		else
			return $value;
	}

	private function unpack($value, $data_type) {
		if ($data_type == 'LongType')
			return self::unpack_long($value);
		else if ($data_type == 'IntegerType') {
			$res = unpack('N', $value);
			return $res[1];
		}
		else if ($data_type == 'AsciiType')
			return self::unpack_str($value, strlen($value));
		else if ($data_type == 'UTF8Type')
			return utf8_decode(self::unpack_str($value, strlen($value)));
		else if ($data_type == 'TimeUUIDType' or $data_type == 'LexicalUUIDType')
			return $value;
		else
			return $value;
	}

	public function keyslices_to_array($keyslices) {
		$ret = null;
		foreach($keyslices as $keyslice) {
			$key = $this->unpack_key($keyslice->key);
			$columns = $keyslice->columns;
			$ret[$key] = $this->supercolumns_or_columns_to_array($columns);
		}
		return $ret;
	}

	private function supercolumns_or_columns_to_array($array_of_c_or_sc) {
		$ret = null;
		if(count($array_of_c_or_sc) == 0) {
			return $ret;
		}
		$first = $array_of_c_or_sc[0];
		if($first->column) { // normal columns
			if ($this->include_timestamp) {
				foreach($array_of_c_or_sc as $c_or_sc) {
					$name = $this->unpack_name($c_or_sc->column->name, false);
					$value = $this->unpack_value($c_or_sc->column->value, $c_or_sc->column->name);
					$ret[$name] = array($value, $c_or_sc->column->timestamp);
				}
			} else {
				foreach($array_of_c_or_sc as $c_or_sc) {
					$name = $this->unpack_name($c_or_sc->column->name, false);
					$value = $this->unpack_value($c_or_sc->column->value, $c_or_sc->column->name);
					$ret[$name] = $value;
				}
			}
		} else if($first->super_column) { // super columns
			foreach($array_of_c_or_sc as $c_or_sc) {
				$name = $this->unpack_name($c_or_sc->super_column->name, true);
				$columns = $c_or_sc->super_column->columns;
				$ret[$name] = $this->columns_to_array($columns);
			}
		} else if ($first->counter_column) {
			foreach($array_of_c_or_sc as $c_or_sc) {
				$name = $this->unpack_name($c_or_sc->counter_column->name, false);
				$ret[$name] = $c_or_sc->counter_column->value;
			}
		} else { // counter_super_column
			foreach($array_of_c_or_sc as $c_or_sc) {
				$name = $this->unpack_name($c_or_sc->counter_super_column->name, true);
				$columns = $c_or_sc->counter_super_column->columns;
				$ret[$name] = $this->counter_columns_to_array($columns);
			}
		}
		return $ret;
	}

	private function columns_to_array($array_of_c) {
		$ret = null;
		if ($this->include_timestamp) {
			foreach($array_of_c as $c) {
				$name  = $this->unpack_name($c->name, false);
				$value = $this->unpack_value($c->value, $c->name);
				$ret[$name] = array($value, $c->timestamp);
			}
		} else {
			foreach($array_of_c as $c) {
				$name  = $this->unpack_name($c->name, false);
				$value = $this->unpack_value($c->value, $c->name);
				$ret[$name] = $value;
			}
		}
		return $ret;
	}

	private function counter_columns_to_array($array_of_c) {
		$ret = array();
		foreach($array_of_c as $c) {
			$name = $this->unpack_name($c->name, false);
			$ret[$name] = $c->value;
		}
		return $ret;
	}

	private function array_to_mutation($array, $timestamp=null, $ttl=null) {
		if(empty($timestamp)) $timestamp = CassandraUtil::get_time();

		$c_or_sc = $this->array_to_supercolumns_or_columns($array, $timestamp, $ttl);
		$ret = null;
		foreach($c_or_sc as $row) {
			$mutation = new cassandra_Mutation();
			$mutation->column_or_supercolumn = $row;
			$ret[] = $mutation;
		}
		return $ret;
	}

	private function array_to_supercolumns_or_columns($array, $timestamp=null, $ttl=null) {
		if(empty($timestamp)) $timestamp = CassandraUtil::get_time();

		$ret = null;
		foreach($array as $name => $value) {
			$c_or_sc = new cassandra_ColumnOrSuperColumn();
			if(is_array($value)) {
				$c_or_sc->super_column = new cassandra_SuperColumn();
				$c_or_sc->super_column->name = $this->pack_name($name, true);
				$c_or_sc->super_column->columns = $this->array_to_columns($value, $timestamp, $ttl);
				$c_or_sc->super_column->timestamp = $timestamp;
			} else {
				$c_or_sc = new cassandra_ColumnOrSuperColumn();
				$c_or_sc->column = new cassandra_Column();
				$c_or_sc->column->name = $this->pack_name($name, false);
				$c_or_sc->column->value = $this->pack_value($value, $name);
				$c_or_sc->column->timestamp = $timestamp;
				$c_or_sc->column->ttl = $ttl;
			}
			$ret[] = $c_or_sc;
		}

		return $ret;
	}

	private function array_to_columns($array, $timestamp=null, $ttl=null) {
		if(empty($timestamp)) $timestamp = CassandraUtil::get_time();

		$ret = null;
		foreach($array as $name => $value) {
			$column = new cassandra_Column();
			$column->name = $this->pack_name($name, false);
			$column->value = $this->pack_value($value, $name);
			$column->timestamp = $timestamp;
			$column->ttl = $ttl;
			$ret[] = $column;
		}
		return $ret;
	}
}

class ColumnFamilyIterator implements Iterator {

	protected $column_family;
	protected $buffer_size;
	protected $row_count;
	protected $read_consistency_level;
	protected $column_parent, $predicate;

	protected $current_buffer;
	protected $next_start_key, $orig_start_key;
	protected $is_valid;
	protected $rows_seen;

	protected function __construct($column_family,
		$buffer_size,
		$row_count,
		$orig_start_key,
		$column_parent,
		$predicate,
		$read_consistency_level) {

		$this->column_family = $column_family;
		$this->buffer_size = $buffer_size;
		$this->row_count = $row_count;
		$this->orig_start_key = $orig_start_key;
		$this->next_start_key = $orig_start_key;
		$this->column_parent = $column_parent;
		$this->predicate = $predicate;
		$this->read_consistency_level = $read_consistency_level;

		if ($this->row_count != null)
			$this->buffer_size = min($this->row_count, $buffer_size);
	}

	public function rewind() {
		// Setup first buffer
		$this->rows_seen = 0;
		$this->is_valid = true;
		$this->next_start_key = $this->orig_start_key;
		$this->get_buffer();

		# If nothing was inserted, this may happen
		if (count($this->current_buffer) == 0) {
			$this->is_valid = false;
			return;
		}

		# If the very first row is a deleted row
		if (count(current($this->current_buffer)) == 0)
			$this->next();
		else
			$this->rows_seen++;
	}

	public function current() {
		return current($this->current_buffer);
	}

	public function key() {
		return key($this->current_buffer);
	}

	public function valid() {
		return $this->is_valid;
	}

	public function next() {
		$beyond_last_row = false;

		# If we haven't run off the end
		if ($this->current_buffer != null)
		{
			# Save this key incase we run off the end
			$this->next_start_key = key($this->current_buffer);
			next($this->current_buffer);

			if (count(current($this->current_buffer)) == 0)
			{
				# this is an empty row, skip it
				$key = key($this->current_buffer);
				$this->next();
			}
			else # count > 0
			{
				$key = key($this->current_buffer);
				$beyond_last_row = !isset($key);

				if (!$beyond_last_row)
				{
					$this->rows_seen++;
					if ($this->rows_seen > $this->row_count) {
						$this->is_valid = false;
						return;
					}
				}
			}
		}
		else
		{
			$beyond_last_row = true;
		}

		if($beyond_last_row && $this->current_page_size < $this->expected_page_size)
		{
			# The page was shorter than we expected, so we know that this
			# was the last page in the column family
			$this->is_valid = false;
		}
		else if($beyond_last_row)
		{
			# We've reached the end of this page, but there should be more
			# in the CF

			# Get the next buffer (next_start_key has already been set)
			$this->get_buffer();

			# If the result set is 1, we can stop because the first item
			# should always be skipped
			if(count($this->current_buffer) == 1)
				$this->is_valid = false;
			else
				$this->next();
		}
	}
}

/**
 * Iterates over a column family row-by-row, typically with only a subset
 * of each row's columns.
 *
 * @package phpcassa
 * @subpackage columnfamily
 */
class RangeColumnFamilyIterator extends ColumnFamilyIterator {

	private $key_start, $key_finish;

	public function __construct($column_family, $buffer_size,
		$key_start, $key_finish, $row_count,
		$column_parent, $predicate,
		$read_consistency_level) {

		$this->key_start = $key_start;
		$this->key_finish = $key_finish;

		parent::__construct($column_family, $buffer_size, $row_count,
			$key_start, $column_parent, $predicate,
			$read_consistency_level);
	}

	protected function get_buffer() {
		if($this->row_count != null)
			$buff_sz = min($this->row_count - $this->rows_seen + 1, $this->buffer_size);
		else
			$buff_sz = $this->buffer_size;
		$this->expected_page_size = $buff_sz;

		$key_range = new cassandra_KeyRange();
		$key_range->start_key = $this->column_family->pack_key($this->next_start_key);
		$key_range->end_key = $this->key_finish;
		$key_range->count = $buff_sz;

		$resp = $this->column_family->pool->call("get_range_slices", $this->column_parent, $this->predicate,
			$key_range, $this->read_consistency_level);

		$this->current_buffer = $this->column_family->keyslices_to_array($resp);
		$this->current_page_size = count($this->current_buffer);
	}
}

/**
 * Iterates over a column family row-by-row, typically with only a subset
 * of each row's columns.
 *
 * @package phpcassa
 * @subpackage columnfamily
 */
class IndexedColumnFamilyIterator extends ColumnFamilyIterator {

	private $index_clause;

	public function __construct($column_family, $index_clause, $buffer_size,
		$column_parent, $predicate,
		$read_consistency_level) {

		$this->index_clause = $index_clause;
		$row_count = $index_clause->count;
		$orig_start_key = $index_clause->start_key;

		parent::__construct($column_family, $buffer_size, $row_count,
			$orig_start_key, $column_parent, $predicate,
			$read_consistency_level);
	}

	protected function get_buffer() {
		# Figure out how many rows we need to get and record that
		if($this->row_count != null)
			$this->index_clause->count = min($this->row_count - $this->rows_seen + 1, $this->buffer_size);
		else
			$this->index_clause->count = $this->buffer_size;
		$this->expected_page_size = $this->index_clause->count;

		$this->index_clause->start_key = $this->column_family->pack_key($this->next_start_key);
		$resp = $this->column_family->pool->call("get_indexed_slices",
			$this->column_parent, $this->index_clause, $this->predicate,
			$this->read_consistency_level);

		$this->current_buffer = $this->column_family->keyslices_to_array($resp);
		$this->current_page_size = count($this->current_buffer);
	}
}

?>
