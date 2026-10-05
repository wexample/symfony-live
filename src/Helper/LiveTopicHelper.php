<?php

namespace Wexample\SymfonyLive\Helper;

use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonyHelpers\Entity\Interfaces\AbstractEntityInterface;
use Wexample\SymfonyLive\Enum\LiveTopicAction;

class LiveTopicHelper
{
    final public const SEPARATOR = '/';
    final public const PREFIX_ENTITY = 'entity';

    /**
     * Builds `entity/<kebab-entity-name>/<action>/<id>`, the topic grammar the browser
     * rebuilds segment by segment in symfony-loader LiveUpdatesService.topic(): a change
     * here is a change on both sides.
     *
     * The last segment is the entity id and nothing else, cast the way the normalizers
     * cast it. A subscriber only ever holds what the API served it, whose `id` field comes
     * from that same expression — any second identifier would put the two halves on
     * topics that never meet.
     */
    public static function entity(
        AbstractEntityInterface $entity,
        LiveTopicAction|string $action
    ): string {
        return static::join(
            static::PREFIX_ENTITY,
            ClassHelper::getKebabName($entity),
            $action instanceof LiveTopicAction ? $action->value : $action,
            (string) $entity->getId()
        );
    }

    /**
     * Reads back what entity() built: null for a topic of another grammar.
     *
     * @return array{name: string, action: string, id: string}|null
     */
    public static function parseEntity(string $topic): ?array
    {
        $segments = explode(static::SEPARATOR, $topic);

        if (4 !== count($segments) || static::PREFIX_ENTITY !== $segments[0] || in_array('', $segments, true)) {
            return null;
        }

        return [
            'name' => $segments[1],
            'action' => $segments[2],
            'id' => $segments[3],
        ];
    }

    public static function join(string|int ...$segments): string
    {
        return implode(static::SEPARATOR, $segments);
    }
}
