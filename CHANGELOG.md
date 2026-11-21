# Changelog

## [1.1.1](https://github.com/cycle/transaction/compare/1.1.0...1.1.1) (2026-11-21)


### Documentation

* move the Discord badge to the first row ([#7](https://github.com/cycle/transaction/issues/7)) ([c549ad3](https://github.com/cycle/transaction/commit/c549ad3b4d46966aa86ab1803be26c1f0f256803))
* restructure the README by the cycle template ([#5](https://github.com/cycle/transaction/issues/5)) ([ba8a79b](https://github.com/cycle/transaction/commit/ba8a79b86f14c892fd3811eb9e77b4202bccd41f))


### Dependencies

* raise dependency minimums and polish the tooling ([#3](https://github.com/cycle/transaction/issues/3)) ([1bdc637](https://github.com/cycle/transaction/commit/1bdc637ccbea5df5163afb778e0147f81c4ff3a6))
* raise the minimum versions of the cycle packages ([#15](https://github.com/cycle/transaction/issues/15)) ([9d3bbc2](https://github.com/cycle/transaction/commit/9d3bbc2a594f479368bae5fa4db179bc4f13b682))

## [1.1.0](https://github.com/cycle/transaction/compare/1.0.0...1.1.0) (2026-06-25)


### Features

* Add Yii3, Laravel, and Spiral integrations ([89ee4b2](https://github.com/cycle/transaction/commit/89ee4b283f09d5d7cb286764b4d2125c84258649))
* Expose `TransactionMode::Exclusive` ([16c70f6](https://github.com/cycle/transaction/commit/16c70f63407b20311f92f31fc6232f4a16edbd36))

## 1.0.0 (2026-06-24)

The first stable release of `cycle/transaction` — a small transaction abstraction for Cycle ORM
that runs raw DBAL operations and a scoped Entity Manager within a single database transaction.

### Features

* Introduce the `Transaction::transact()` API: execute a callback inside a single database
  transaction, receiving a scoped `EntityManagerInterface` and the resolved `DatabaseInterface`.
  The transaction is committed when the callback returns and rolled back if it throws.
* Add the `FlushMode` enum to control when and how the scoped Entity Manager flushes pending changes:
  * `OnWrite` — flush every `persist`/`persistState`/`delete` immediately;
  * `BeforeCommit` — collect all changes and flush once, right before committing (default);
  * `FailOnPending` — throw a `TransactionException` if any changes are left unflushed;
  * `SkipPending` — silently discard any unflushed changes (only DBAL operations are committed).
* Add the `TransactionMode` enum to control how the Unit of Work interacts with the open
  transaction: `Current` (reuse the open transaction, default), `OpenNew` (open a dedicated inner
  transaction per connection) and `Ignore` (do not manage transactions for the Unit of Work).
* Resolve the target database from the `$source` argument — either by connection name or by an
  entity class mapped to it; the scoped Entity Manager rejects entities that belong to a different
  connection, preventing accidental cross-connection writes.

### Documentation

* Add a README covering installation and usage of the transaction API, flush modes and
  transaction modes.

### Requirements

* PHP 8.2 or higher
* `cycle/orm` ^2.18
* `cycle/database` ^2.20
