<?php

namespace Lakion\SyliusElasticSearchBundle\Search\Criteria\Filtering;

/**
 * @author Arkadiusz Krakowiak <arkadiusz.krakowiak@lakion.com>
 */
final class ProductIsEnabledFilter
{
    /**
     * @var bool
     */
    private $enabled;

    /**
     * @param bool $enabled
     */
    public function __construct($enabled)
    {
        $this->enabled = $enabled;
    }

    /**
     * @return string
     */
    public function getEnabled()
    {
        return $this->enabled;
    }
}
