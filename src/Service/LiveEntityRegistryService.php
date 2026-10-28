<?php

namespace Wexample\SymfonyLive\Service;

use Doctrine\ORM\EntityManagerInterface;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonyHelpers\Attribute\WorkerSafe;
use Wexample\SymfonyHelpers\Helper\EntityHierarchyHelper;
use Wexample\SymfonyLive\Attribute\LiveEntity;
use Wexample\SymfonyLive\Class\LiveEntityDefinition;
use Wexample\SymfonyLive\Enum\LiveTopicAction;

/**
 * Reads which entities carry #[LiveEntity] straight from the Doctrine mapping, so an
 * entity is opened to subscription where it is declared and nowhere else.
 */
class LiveEntityRegistryService
{
    /**
     * @var array<string, LiveEntityDefinition>|null
     */
    #[WorkerSafe('Read from the entities attributes, the same for every request.')]
    private ?array $definitions = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function find(string $name): ?LiveEntityDefinition
    {
        return $this->all()[$name] ?? null;
    }

    /**
     * By the root of the hierarchy the class belongs to: a subclass is open to
     * subscription under the root's name, as the root declared. Doctrine hands
     * out proxies, which the helper resolves back to the real class first.
     */
    public function findByClassName(object|string $className): ?LiveEntityDefinition
    {
        return $this->find(static::nameOf($className));
    }

    /**
     * The name a hierarchy of entities is subscribed to under: its root's, in
     * kebab case — the grammar of the topics.
     */
    public static function nameOf(object|string $className): string
    {
        return ClassHelper::getKebabName(EntityHierarchyHelper::getRootClass($className));
    }

    /**
     * @return array<string, LiveEntityDefinition>
     */
    public function all(): array
    {
        if ($this->definitions === null) {
            $this->definitions = $this->build();
        }

        return $this->definitions;
    }

    /**
     * @return array<string, LiveEntityDefinition>
     */
    private function build(): array
    {
        $definitions = [];

        foreach ($this->entityManager->getMetadataFactory()->getAllMetadata() as $metadata) {
            // The root of a hierarchy declares for every kind under it: a
            // subclass is the same entity to a subscriber, and registering
            // it apart would open a name nothing publishes on.
            if ($metadata->getName() !== $metadata->rootEntityName) {
                continue;
            }

            $attributes = $metadata->getReflectionClass()->getAttributes(LiveEntity::class);

            if (! $attributes) {
                continue;
            }

            /** @var LiveEntity $attribute */
            $attribute = $attributes[0]->newInstance();
            $name = static::nameOf($metadata->getName());

            $definitions[$name] = new LiveEntityDefinition(
                className: $metadata->getName(),
                name: $name,
                actions: $attribute->actions ?: LiveTopicAction::cases(),
            );
        }

        return $definitions;
    }
}
