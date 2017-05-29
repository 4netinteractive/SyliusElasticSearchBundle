<?php

namespace Lakion\SyliusElasticSearchBundle\Search\Elastic\Applicator\Filter;

use Lakion\SyliusElasticSearchBundle\Search\Criteria\Filtering\ProductHasAttributeCodesFilter;
use Lakion\SyliusElasticSearchBundle\Search\Elastic\Applicator\SearchCriteriaApplicator;
use Lakion\SyliusElasticSearchBundle\Search\Elastic\Factory\Query\QueryFactoryInterface;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Search;

/**
 * @author Arkadiusz Krakowiak <arkadiusz.krakowiak@lakion.com>
 */
final class ProductHasMultipleAttributeCodesApplicator extends SearchCriteriaApplicator
{
    /**
     * @var QueryFactoryInterface
     */
    private $productHasAttributeCodeQueryFactory;

    /**
     * {@inheritdoc}
     */
    public function __construct(QueryFactoryInterface $productHasAttributeCodeQueryFactory)
    {
        $this->productHasAttributeCodeQueryFactory = $productHasAttributeCodeQueryFactory;
    }

    /**
     * @param ProductHasAttributeCodesFilter $codesFilter
     * @param Search $search
     */
    public function applyProductHasAttributeCodesFilter(ProductHasAttributeCodesFilter $codesFilter, Search $search)
    {
        $list = [];
        foreach ($codesFilter->getCodes() as $code) {
            $list[] = is_array($code) ? $code[0] : $code;
        }
        $search->addPostFilter(
            $this->productHasAttributeCodeQueryFactory->create(['attribute_codes' => $list]),
            BoolQuery::MUST
        );
    }
}
