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
 * Class ProductHasOptionCodeAndTaxonsQueryFactory
 * @package Lakion\SyliusElasticSearchBundle\Search\Elastic\Factory\Query
 * @author  Arvids Godjuks <arvids.godjuks@gmail.com>
 */
final class ProductHasOptionCodeAndTaxonsQueryFactory implements QueryFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(array $parameters = [])
    {
        if (!isset($parameters['option_value_code'])) {
            throw new MissingQueryParameterException('option_value_code', get_class($this));
        }
        if (!isset($parameters['taxon_code'])) {
            throw new MissingQueryParameterException('taxon_code', get_class($this));
        }

        $boolQuery = new BoolQuery();

        $boolQuery->add(
            new NestedQuery(
                'productTaxons',
                new TermQuery('productTaxons.taxon.code', strtolower($parameters['taxon_code']))
            )
        );

        $boolQuery->add(
            new NestedQuery(
                'variants',
                new NestedQuery(
                    'variants.optionValues',
                    new TermQuery('variants.optionValues.code', $parameters['option_value_code'])
                )
            )
        );

        return $boolQuery;
    }
}
