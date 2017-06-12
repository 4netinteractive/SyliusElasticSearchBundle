<?php
/**
 * Created by PhpStorm.
 * User: psihius
 * Date: 22.05.2017
 * Time: 12:57
 */

namespace Lakion\SyliusElasticSearchBundle\Search\Elastic\Factory\Query;

use Lakion\SyliusElasticSearchBundle\Exception\MissingQueryParameterException;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\Joining\NestedQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;

/**
 * Class ProductHasAttributeCodeAndTaxonsQueryFactory
 * @package Lakion\SyliusElasticSearchBundle\Search\Elastic\Factory\Query
 * @author  Arvids Godjuks <arvids.godjuks@gmail.com>
 */
final class ProductHasAttributeCodeAndTaxonsQueryFactory implements QueryFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(array $parameters = [])
    {
        if (!isset($parameters['attribute_value'])) {
            throw new MissingQueryParameterException('attribute_value', get_class($this));
        }
        if (!isset($parameters['attribute_code'])) {
            throw new MissingQueryParameterException('attribute_code', get_class($this));
        }
        if (!array_key_exists('taxon_code', $parameters)) {
            throw new MissingQueryParameterException('taxon_code', get_class($this));
        }

        $boolQuery = new BoolQuery();

        $boolQuery->add(
            new TermQuery('enabled', true)
        );

        if (!is_null($parameters['taxon_code'])) {
            $boolQuery->add(
                new NestedQuery(
                    'productTaxons',
                    new TermQuery('productTaxons.taxon.code', strtolower($parameters['taxon_code']))
                )
            );
        }

        $boolQuery->add(
            new NestedQuery(
                'attributes',
                new TermQuery(
                    'attributes.value', $parameters['attribute_value']
                )
            )
        );
        $boolQuery->add(
            new NestedQuery(
            'attributes',
                new TermQuery(
                    'attributes.attribute.code', $parameters['attribute_code']
                )
            )
        );

        return $boolQuery;
    }
}
