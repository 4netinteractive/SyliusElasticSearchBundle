<?php

namespace Lakion\SyliusElasticSearchBundle\Search\Elastic\Factory\Query;

use Lakion\SyliusElasticSearchBundle\Exception\MissingQueryParameterException;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\Joining\NestedQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\RangeQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;

/**
 * @author Arkadiusz Krakowiak <arkadiusz.krakowiak@lakion.com>
 */
final class ProductHasAttributeCodeQueryFactory implements QueryFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(array $parameters = [])
    {
        if (!isset($parameters['attribute_codes'])) {
            throw new MissingQueryParameterException('attribute_codes', get_class($this));
        }

        $query = new BoolQuery();
        $query->add(
            new NestedQuery(
                'attributes', new TermsQuery('attributes.value', $parameters['attribute_codes'])
            )
        );
        $query->add(
            new NestedQuery(
                'variants', new RangeQuery('variants.onHand', ['gt' => 0])
            )
        );

        return $query;
    }
}
