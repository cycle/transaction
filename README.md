<div align="center">
    <br>
    <a href="https://cycle-orm.dev" target="_blank">
        <picture>
            <source media="(prefers-color-scheme: dark)" srcset="https://github.com/cycle/.github/blob/main/logo/words-vector-dark.svg?raw=true">
            <img width="50%" align="center" src="https://github.com/cycle/.github/blob/main/logo/words-vector-light.svg?raw=true" alt="CycleORM Logo">
        </picture>
    </a>
    <br>
    <br>
</div>

<div align="center">

[![Build Status](https://img.shields.io/github/actions/workflow/status/cycle/transaction/testing.yml?branch=1.x&style=flat-square&label=tests)](https://github.com/cycle/transaction/actions)
[![Codecov Coverage](https://img.shields.io/codecov/c/github/cycle/transaction/1.x?style=flat-square&logo=codecov)](https://app.codecov.io/gh/cycle/transaction/tree/1.x)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat-square&label=mutation%20score&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fcycle%2Ftransaction%2F1.x)](https://dashboard.stryker-mutator.io/reports/github.com/cycle/transaction/1.x)

[![Discord](https://img.shields.io/discord/538114875570913290?style=flat-square&logo=discord&labelColor=7289d9&logoColor=white&color=39456d)](https://discord.gg/spiralphp)
[![Follow on Twitter (X)](https://img.shields.io/badge/-Follow-black?style=flat-square&logo=X)](https://x.com/intent/follow?screen_name=SpiralPHP)

</div>

# Transaction Abstraction for Cycle ORM

This library provides a small transaction abstraction for Cycle ORM. It opens a single database
transaction and lets you run raw DBAL operations together with a scoped
[Entity Manager](https://cycle-orm.dev/docs/advanced-entity-manager) inside it.

Everything executed within the callback shares one transaction: if the callback throws, the
transaction is rolled back; otherwise it is committed. The Entity Manager is scoped to a single
database connection for the duration of the callback, so accidental cross-connection writes are
rejected instead of silently splitting your work across transactions.

<br>

## Installation

The preferred way to install this package is through [Composer](https://getcomposer.org/).

```bash
composer require cycle/transaction
```

[![PHP Version Require](https://poser.pugx.org/cycle/transaction/require/php?style=flat-square)](https://packagist.org/packages/cycle/transaction)
[![Latest Stable Version](https://img.shields.io/packagist/v/cycle/transaction?&style=flat-square)](https://packagist.org/packages/cycle/transaction)
[![License](https://img.shields.io/packagist/l/cycle/transaction.svg?style=flat-square)](LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/cycle/transaction?&style=flat-square)](https://packagist.org/packages/cycle/transaction)

<br>

## Usage

### Obtaining a Transaction

The package ships a single implementation of the `Cycle\Transaction\Transaction` interface. Build it
from your configured ORM and DBAL, or resolve `Cycle\Transaction\Transaction` from your framework's
container if it is registered there.

```php
use Cycle\Transaction\Internal\TransactionImpl;
use Cycle\Transaction\Transaction;

/**
 * @var \Cycle\ORM\ORMInterface $orm
 * @var \Cycle\Database\DatabaseProviderInterface $dbal
 */
$transaction = new TransactionImpl($orm, $dbal);
```

### Basic Example

The callback receives the scoped `EntityManagerInterface` and the `DatabaseInterface` of the resolved
connection. Persisted entities are flushed and committed automatically when the callback returns.

```php
use Cycle\Database\DatabaseInterface;
use Cycle\ORM\EntityManagerInterface;

$transaction->transact(function (EntityManagerInterface $em, DatabaseInterface $db): void {
    $em->persist(new User('john@example.com'));

    // The same transaction is visible to raw DBAL operations.
    $db->table('audit')->insertOne(['event' => 'user.created']);
});
```

If the callback throws, the whole transaction — both the Entity Manager changes and the raw DBAL
operations — is rolled back.

```php
$transaction->transact(function (EntityManagerInterface $em): void {
    $em->persist(new User('john@example.com'));

    throw new \DomainException('Something went wrong');
    // Nothing is committed: the new user is rolled back.
});
```

### Returning a Value

`transact()` returns whatever the callback returns.

```php
$user = $transaction->transact(function (EntityManagerInterface $em): User {
    $user = new User('john@example.com');
    $em->persist($user);

    return $user;
});
```

### Flush Modes

The `FlushMode` enum controls when and how the scoped Entity Manager flushes its pending changes.
Pass it via the `$flush` argument (defaults to `FlushMode::BeforeCommit`).

| Mode                     | Behaviour                                                                                          |
|--------------------------|----------------------------------------------------------------------------------------------------|
| `FlushMode::OnWrite`     | Flush every `persist`/`persistState`/`delete` to the database immediately.                         |
| `FlushMode::BeforeCommit` | Collect all changes and flush them once, right before the transaction is committed. **(default)**  |
| `FlushMode::FailOnPending` | Do not flush automatically; throw a `TransactionException` if any changes are still pending.       |
| `FlushMode::SkipPending` | Do not flush automatically; silently discard any pending changes (only DBAL operations are committed). |

```php
use Cycle\Transaction\FlushMode;

// Require the callback to flush explicitly; otherwise the transaction fails.
$transaction->transact(
    callback: function (EntityManagerInterface $em): void {
        $em->persist(new User('john@example.com'));
        $em->run(); // explicit flush — without it a TransactionException is thrown
    },
    flush: FlushMode::FailOnPending,
);
```

### Transaction Modes

The `TransactionMode` enum controls how the Entity Manager's Unit of Work interacts with the open
transaction. Pass it via the `$emMode` argument (defaults to `TransactionMode::Current`).

| Mode                       | Behaviour                                                                                      |
|----------------------------|------------------------------------------------------------------------------------------------|
| `TransactionMode::Current` | Reuse the currently opened transaction. Throws if none is open. **(default)**                  |
| `TransactionMode::OpenNew` | Open a new inner transaction per driver connection and close it on finish.                     |
| `TransactionMode::Ignore`  | Do not manage transactions for the Unit of Work.                                               |

```php
use Cycle\Transaction\TransactionMode;

$transaction->transact(
    callback: function (EntityManagerInterface $em): void {
        $em->persist(new User('john@example.com'));
    },
    emMode: TransactionMode::OpenNew,
);
```

### Selecting the Database

By default the transaction runs against the default database connection. Use the `$source` argument
to pick another connection — either by its name or by an entity class mapped to it. The scoped Entity
Manager then rejects entities that belong to a different connection.

```php
// By connection name.
$transaction->transact(
    callback: fn(EntityManagerInterface $em, DatabaseInterface $db) => $db->getName(),
    source: 'reporting',
);

// By entity class — resolves the connection the entity is mapped to.
$transaction->transact(
    callback: function (EntityManagerInterface $em): void {
        $em->persist(new User('john@example.com'));
    },
    source: User::class,
);
```