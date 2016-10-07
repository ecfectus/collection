<?php
namespace Ecfectus\Collection;

use ArrayAccess;
use IteratorAggregate;
use Countable;
use JsonSerializable;
use Ecfectus\Dotable\DotableInterface;
use Ecfectus\Dotable\Dotabletrait;

/**
 * Class Collection
 * @package Ecfectus
 */
class Collection implements CollectionInterface, DotableInterface, ArrayAccess, IteratorAggregate, Countable, JsonSerializable
{
    use DotableTrait;
}
