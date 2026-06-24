# Changelog

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
