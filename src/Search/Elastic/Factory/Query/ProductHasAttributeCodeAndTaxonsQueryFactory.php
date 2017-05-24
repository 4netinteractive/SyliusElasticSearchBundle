<?php
/**
 * Created by PhpStorm.
 * User: psihius
 * Date: 22.05.2017
 * Time: 12:57
 */

namespace Lakion\SyliusElasticSearchBundle\Search\Elastic\Factory\Query;

use Lakion\SyliusElasticSearchBundle\Exception\MissingQueryParameterException;
use ONGR\ElasticsearchDSL\Query\Joining\NestedQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;

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
        if (!isset($parameters['attribute_value_code'])) {
            throw new MissingQueryParameterException('attribute_value_code', get_class($this));
        }
        if (!isset($parameters['taxon_code'])) {
            throw new MissingQueryParameterException('taxon_code', get_class($this));
        }

        return new NestedQuery(
            'attributes',
            new TermQuery(
                'attributes.value', $parameters['attribute_value_code']
            )
        );
    }
}