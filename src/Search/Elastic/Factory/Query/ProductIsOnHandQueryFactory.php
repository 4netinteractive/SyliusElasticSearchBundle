<?php
/**
 * Created by PhpStorm.
 * User: psihius
 * Date: 14.06.2017
 * Time: 19:18
 */

namespace Lakion\SyliusElasticSearchBundle\Search\Elastic\Factory\Query;

use ONGR\ElasticsearchDSL\Query\Joining\NestedQuery;
use ONGR\ElasticsearchDSL\Query\Specialized\ScriptQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;

class ProductIsOnHandQueryFactory implements QueryFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(array $parameters = array())
    {
        return new NestedQuery('variants', new ScriptQuery("doc['variants.onHand'].value - doc['variants.onHold'].value > 0"));
    }
}
