<?php

namespace Lakion\SyliusElasticSearchBundle\Search\Elastic\Factory\Query;

use Lakion\SyliusElasticSearchBundle\Exception\MissingQueryParameterException;
use ONGR\ElasticsearchDSL\Query\Joining\NestedQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;

/**
 * @author Arkadiusz Krakowiak <arkadiusz.krakowiak@lakion.com>
 */
final class ProductHasOptionCodeQueryFactory implements QueryFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(array $parameters = [])
    {
        if (!isset($parameters['option_codes'])) {
            throw new MissingQueryParameterException('option_codes', get_class($this));
        }

        return
            new NestedQuery(
                'variants',
                new NestedQuery(
                    'variants.optionValues',
                    new TermsQuery('variants.optionValues.code', $parameters['option_codes'])
                )
            );
    }
}
