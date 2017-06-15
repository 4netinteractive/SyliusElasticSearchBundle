<?php

namespace Lakion\SyliusElasticSearchBundle\Search\Criteria\Filtering;

/**
 * @author Arkadiusz Krakowiak <arkadiusz.krakowiak@lakion.com>
 */
final class ProductIsOnHandFilter
{

    public function __construct()
    {
    }

    /**
     * @return int
     */
    public function getEnabled()
    {
        return 0;
    }
}
