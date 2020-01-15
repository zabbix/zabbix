Changelog from v3.0.1 and up. Prior changes don't have a changelog.

# v3.4.2

* Fix alignment for atomic values in structs (PR #171)

* Fix closing of sentinel instances while updating state (PR #173)

# v3.4.1

* Update xerrors package (PR #165)

* Have cluster Pools be closed outside of lock, to reduce contention during
  failover events (PR #168)

# v3.4.0

* Add `PersistentPubSubWithOpts` function, deprecating the old
  `PersistentPubSub` function. (PR #156)

* Make decode errors a bit more helpful. (PR #157)

* Refactor Pool to rely on its inner lock less, simplifying the code quite a bit
  and hopefully speeding up certain actions. (PR #160)

* Various documentation updates. (PR #138, Issue #162)

# v3.3.2

* Have `resp2.Error` match with a `resp.ErrDiscarded` when using `errors.As`.
  Fixes EVAL, among probably other problems. (PR #152)

# v3.3.1

* Use `xerrors` internally. (PR #113)

* Handle unmarshal errors better. Previously an unmarshaling error could leave
  the connection in an inconsistent state, because the full message wouldn't get
  completely read off the wire. After a lot of work, this has been fixed. (PR
  #127, #139, #145)

* Handle CLUSTERDOWN errors better. Upon seeing a CLUSTERDOWN, all commands will
  be delayed by a small amount of time. The delay will be stopped as soon as the
  first non-CLUSTERDOWN result is seen from the Cluster. The idea is that, if a
  failover happens, commands which are incoming will be paused long enough for
  the cluster to regain it sanity, thus minimizing the number of failed commands
  during the failover. (PR #137)

* Fix cluster redirect tracing. (PR #142)

# v3.3.0

**New**

* Add `trace` package with tracing callbacks for `Pool` and `Cluster`.
  (`Sentinel` coming soon!) (PR #100, PR #108, PR #111)

* Add `SentinelAddrs` method to `Sentinel` (PR #118)

* Add `DialUseTLS` option. (PR #104)

**Fixes and Improvements**

* Fix `NewSentinel` not handling URL AUTH parameters correctly (PR #120)

* Change `DefaultClientFunc`'s pool size from 20 to 4, on account of pipelining
  being enabled by default. (Issue #107)

* Reuse `reflect.Value` instances when unmarshaling into certain map types. (PR
  #96).

* Fix a panic in `FlatCmd`. (PR #97)

* Reuse field name `string` when unmarshaling into a struct. (PR #95)

* Reduce PubSub allocations significantly. (PR #92 + Issue #91)

* Reduce allocations in `Conn`. (PR #84)

# v3.2.3

* Optimize Scanner implementation.

* Fix bug with using types which implement resp.LenReader, encoding.TextMarshaler, and encoding.BinaryMarshaler. The encoder wasn't properly taking into account the interfaces when counting the number of elements in the message.

# v3.2.2

* Give Pool an ErrCh so that errors which happen internally may be reported to
  the user, if they care.

* Fix `PubSubConn`'s deadlock problems during Unsubscribe commands.

* Small speed optimizations in network protocol code.

# v3.2.1

* Move benchmarks to a submodule in order to clean up `go.mod` a bit.

# v3.2.0

* Add `StreamReader` type to make working with redis' new [Stream][stream]
  functionality easier.

* Make `Sentinel` properly respond to `Client` method calls. Previously it
  always created a new `Client` instance when a secondary was requested, now it
  keeps track of instances internally.

* Make default `Dial` call have a timeout for connect/read/write. At the same
  time, normalize default timeout values across the project.

* Implicitly pipeline commands in the default Pool implementation whenever
  possible. This gives a throughput increase of nearly 5x for a normal parallel
  workload.

[stream]: https://redis.io/topics/streams-intro

# v3.1.0

* Add support for marshaling/unmarshaling structs.

# v3.0.1

* Make `Stub` support `Pipeline` properly.
