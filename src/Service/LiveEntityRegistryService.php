<?php

namespace Wexample\SymfonyLive\Service;

use Doctrine\ORM\EntityManagerInterface;
use Wexample\Helpers\Helper\ClassHelper;
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
     * Doctrine hands out proxies, whose class name `getKebabName()` resolves back to the real
     * one before it kebabs it.
     */
    public function findByClassName(object|string $className): ?LiveEntityDefinition
    {
        return $this->find(ClassHelper::getKebabName($className));
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
            $attributes = $metadata->getReflectionClass()->getAttributes(LiveEntity::class);

            if (! $attributes) {
                continue;
            }

            /** @var LiveEntity $attribute */
            $attribute = $attributes[0]->newInstance();
            $name = ClassHelper::getKebabName($metadata->getName());

            $definitions[$name] = new LiveEntityDefinition(
                className: $metadata->getName(),
                name: $name,
                actions: $attribute->actions ?: LiveTopicAction::cases(),
            );
        }

        return $definitions;
    }
}
