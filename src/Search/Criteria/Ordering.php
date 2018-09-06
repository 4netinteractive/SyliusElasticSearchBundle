<?php

namespace Lakion\SyliusElasticSearchBundle\Search\Criteria;

/**
 * @author Arkadiusz Krakowiak <arkadiusz.krakowiak@lakion.com>
 */
final class Ordering
{
    const DEFAULT_FIELD = 'price';
    const DEFAULT_DIRECTION = self::DESCENDING_DIRECTION;
    const DEFAULT_TAXONCODE = '';
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
     * @var string
     */
    private $taxoncode;

    /**
     * @param string $field
     * @param string $direction
     * @param string $taxoncode
     */
    private function __construct($field, $direction, $taxoncode)
    {
        $this->field = $field;
        $this->direction = $direction;
        $this->taxoncode = $taxoncode;
    }

    /**
     * @param array $parameters
     *
     * @return Ordering
     */
    public static function fromQueryParameters(array $parameters)
    {
        $taxoncode = self::DEFAULT_TAXONCODE;
        if (isset($parameters['sort']) && is_array($parameters['sort'])) {
            reset($parameters['sort']);
            $field = key($parameters['sort']);
            $direction = current($parameters['sort']);
            if (substr(get_class($parameters[1]), -20) ==  "ProductInTaxonFilter") {
                $taxoncode = $parameters[1]->getTaxonCode();
            }
        } else {
            $field = self::DEFAULT_FIELD;
            $direction = self::DEFAULT_DIRECTION;
        }
        return new self($field, $direction, $taxoncode);
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

    /**
     * @return string
     */
    public function getTaxonCode()
    {
        return $this->taxoncode;
    }
}