<?php

namespace Wexample\SymfonyLive\Interface;

use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * The normalizer whose output an entity is published with. An app declares one by adding this
 * interface to a normalizer it already has: `getEntityClassName()` is what
 * `AbstractEntityNormalizer` already exposes, so there is nothing else to write.
 *
 * An entity may have several normalizers — legacy ones, summary ones — and only the one
 * carrying this interface goes on the wire.
 */
interface LiveEntityNormalizerInterface extends NormalizerInterface
{
    public const string TAG = 'wexample_symfony_live.entity_normalizer';

    public static function getEntityClassName(): string;
}
