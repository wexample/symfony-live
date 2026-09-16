<?php

namespace Wexample\SymfonyLive\EventListener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Wexample\SymfonyHelpers\Entity\Interfaces\AbstractEntityInterface;
use Wexample\SymfonyLive\Enum\LiveTopicAction;
use Wexample\SymfonyLive\Helper\LiveTopicHelper;
use Wexample\SymfonyLive\Interface\LiveEntityNormalizerInterface;
use Wexample\SymfonyLive\Interface\LivePublishedWithParentInterface;
use Wexample\SymfonyLive\Service\LiveEntityRegistryService;
use Wexample\SymfonyLive\Service\LivePublisherService;

/**
 * Publishes create, update and delete for every entity marked #[LiveEntity], so a service
 * changing one has nothing to call.
 *
 * The three entity events only note what happened; publication waits for postFlush. Pushing
 * from inside the transaction would announce a change that a rollback then undoes, and
 * subscribers have no way to hear the retraction.
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
class LiveEntityPublishListener
{
    /**
     * @var array<array{0: AbstractEntityInterface, 1: LiveTopicAction}>
     */
    private array $pending = [];

    /**
     * @var array<string, LiveEntityNormalizerInterface>|null
     */
    private ?array $normalizersByClassName = null;

    /**
     * @param iterable<LiveEntityNormalizerInterface> $normalizers
     */
    public function __construct(
        private readonly LiveEntityRegistryService $registry,
        private readonly LivePublisherService $publisher,
        #[AutowireIterator(LiveEntityNormalizerInterface::TAG)]
        private readonly iterable $normalizers,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->collect($args->getObject(), LiveTopicAction::CREATE);
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->collect($args->getObject(), LiveTopicAction::UPDATE);
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->collect($args->getObject(), LiveTopicAction::DELETE);
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $pending = $this->pending;
        $this->pending = [];

        foreach ($pending as [$entity, $action]) {
            $data = $this->buildData($entity, $action);

            foreach ($this->topicsFor($entity, $action) as $topic) {
                $this->publisher->publishEvent(
                    $topic,
                    $action->value,
                    $data
                );
            }
        }
    }

    /**
     * Its own topic, and those of whatever holds it.
     *
     * A collection watches the thing it is the collection of rather than each of
     * its rows, because the row it is waiting for is the one that does not exist
     * yet — see LivePublishedWithParentInterface.
     *
     * @return string[]
     */
    private function topicsFor(
        AbstractEntityInterface $entity,
        LiveTopicAction $action
    ): array {
        $topics = [LiveTopicHelper::entity($entity, $action)];

        if (! $entity instanceof LivePublishedWithParentInterface) {
            return $topics;
        }

        foreach ($entity->getLiveParents() as $parent) {
            // A parent nobody may subscribe to would be an address nothing
            // listens on: publishing there costs a round trip for no reader.
            if ($parent instanceof AbstractEntityInterface
                && $this->registry->findByClassName($parent)) {
                $topics[] = LiveTopicHelper::entity($parent, $action);
            }
        }

        return $topics;
    }

    private function collect(object $entity, LiveTopicAction $action): void
    {
        if (! $entity instanceof AbstractEntityInterface) {
            return;
        }

        $definition = $this->registry->findByClassName($entity);

        if (! $definition || ! in_array($action, $definition->actions, true)) {
            return;
        }

        $this->pending[] = [$entity, $action];
    }

    private function buildData(
        AbstractEntityInterface $entity,
        LiveTopicAction $action
    ): array {
        // A removed entity is still in memory but its relations are gone, and normalizing it
        // would walk into proxies of rows that no longer exist.
        if ($action !== LiveTopicAction::DELETE) {
            if ($normalizer = $this->findNormalizer($entity)) {
                return (array) $normalizer->normalize($entity);
            }
        }

        return ['id' => (string) $entity->getId()];
    }

    private function findNormalizer(AbstractEntityInterface $entity): ?LiveEntityNormalizerInterface
    {
        if ($this->normalizersByClassName === null) {
            $this->normalizersByClassName = [];

            foreach ($this->normalizers as $normalizer) {
                $this->normalizersByClassName[$normalizer::getEntityClassName()] = $normalizer;
            }
        }

        foreach ($this->normalizersByClassName as $className => $normalizer) {
            if ($entity instanceof $className) {
                return $normalizer;
            }
        }

        return null;
    }
}
