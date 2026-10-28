<?php

namespace Wexample\SymfonyLive\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Wexample\SymfonyHelpers\Entity\AbstractEntity;
use Wexample\SymfonyLive\Attribute\LiveEntity;

/**
 * The root of a hierarchy: what is open to subscription is declared here,
 * once, for every kind of room.
 */
#[LiveEntity]
#[ORM\Entity]
#[ORM\InheritanceType('JOINED')]
abstract class Room extends AbstractEntity
{
}
