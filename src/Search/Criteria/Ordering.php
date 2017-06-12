<?php

namespace Lakion\SyliusElasticSearchBundle\Search\Criteria;

/**
 * @author Arkadiusz Krakowiak <arkadiusz.krakowiak@lakion.com>
 */
final class Ordering
{
    const DEFAULT_FIELD = 'createdAt';
    const DEFAULT_DIRECTION = self::DESCENDING_DIRECTION;
    const ASCENDING_DIRECTION = 'asc';
    const DESCENDING_DIRECTION = 'desc';

    /**
     * @var string
     */
    private $field;

    /**
     * @var string
     */
    private $direction;

    /**
     * @param string $field
     * @param string $direction
     */
    private function __construct($field, $direction)
    {
        $this->field = $field;
        $this->direction = $direction;
    }

    /**
     * @param array $parameters
     *
     * @return Ordering
     */
    public static function fromQueryParameters(array $parameters)
    {
        if (isset($parameters['sort']) && is_array($parameters['sort'])) {
            reset($parameters['sort']);
            $field = key($parameters['sort']);
            $direction = current($parameters['sort']);
        } else {
            $field = self::DEFAULT_FIELD;
            $direction = self::DEFAULT_DIRECTION;
        }
        return new self($field, $direction);
    }

    /**
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * @return string
     */
    public function getDirection()
    {
        return $this->direction;
    }
}
