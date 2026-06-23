<?php

declare(strict_types=1);

namespace Cycle\Transaction\Internal;

use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\Transaction\RunnerInterface;
use Cycle\ORM\Transaction\StateInterface;
use Cycle\ORM\Transaction\UnitOfWork;
use Cycle\Transaction\Exception\TransactionException;
use Cycle\Transaction\FlushMode;

/**
 * @internal
 */
final class EntityManager implements EntityManagerInterface
{
    private ?UnitOfWork $uow = null;

    /**
     * @param non-empty-string $driverName Database driver name
     */
    public function __construct(
        private readonly ORMInterface $orm,
        private readonly RunnerInterface $runner,
        private readonly string $driverName,
        private readonly FlushMode $flush = FlushMode::BeforeCommit,
    ) {}

    #[\Override]
    public function persistState(object $entity, bool $cascade = true): EntityManagerInterface
    {
        $this->validateSource($entity);
        $this->getUow()->persistState($entity, $cascade);
        if ($this->flush === FlushMode::OnWrite) { $this->run(); }
        return $this;
    }

    #[\Override]
    public function persist(object $entity, bool $cascade = true): EntityManagerInterface
    {
        $this->validateSource($entity);
        $this->getUow()->persistDeferred($entity, $cascade);
        if ($this->flush === FlushMode::OnWrite) { $this->run(); }
        return $this;
    }

    #[\Override]
    public function delete(object $entity, bool $cascade = true): EntityManagerInterface
    {
        $this->validateSource($entity);
        $this->getUow()->delete($entity, $cascade);
        if ($this->flush === FlushMode::OnWrite) { $this->run(); }
        return $this;
    }

    #[\Override]
    public function run(): StateInterface
    {
        if ($this->uow === null) {
            return new EmptyState();
        }

        $state = $this->uow->run();
        $this->clean();
        if (!$state->isSuccess()) {
            throw $state->getLastError() ?? new \RuntimeException('Transaction failed with unknown error');
        }
        return $state;
    }

    #[\Override]
    public function clean(): static
    {
        $this->uow = null;
        return $this;
    }

    public function hasPendingChanges(): bool
    {
        return $this->uow !== null && $this->uow->hasPendingChanges();
    }

    private function getUow(): UnitOfWork
    {
        return $this->uow ??= new UnitOfWork($this->orm, $this->runner);
    }

    /**
     * Validate that the entity belongs to the same database source.
     *
     * @throws TransactionException
     */
    private function validateSource(object $entity): void
    {
        $role = $this->orm->resolveRole($entity);
        $entityDriver = $this->orm->getSource($role)->getDatabase()->getDriver()->getName();

        if ($entityDriver !== $this->driverName) {
            throw new TransactionException(
                \sprintf(
                    'Entity database driver `%s` does not match the transaction database driver `%s`.',
                    $entityDriver,
                    $this->driverName,
                ),
            );
        }
    }
}
