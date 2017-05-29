<?php

namespace Lakion\SyliusElasticSearchBundle\Search\Elastic\Applicator\Filter;

use Lakion\SyliusElasticSearchBundle\Search\Criteria\Filtering\ProductInTaxonFilter;
use Lakion\SyliusElasticSearchBundle\Search\Criteria\Filtering\ProductIsEnabledFilter;
use Lakion\SyliusElasticSearchBundle\Search\Elastic\Applicator\SearchCriteriaApplicator;
use Lakion\SyliusElasticSearchBundle\Search\Elastic\Factory\Query\QueryFactoryInterface;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Search;

/**
 * @author Arkadiusz Krakowiak <arkadiusz.krakowiak@lakion.com>
 */
final class ProductIsEnabledApplicator extends SearchCriteriaApplicator
{
    /**
     * @var QueryFactoryInterface
     */
    private $productIsEnabledQueryFactory;

    /**
     * @param QueryFactoryInterface $productInMainTaxonQueryFactory
     */
    public function __construct(
        QueryFactoryInterface $productIsEnabledQueryFactory
    ) {
        $this->productIsEnabledQueryFactory = $productIsEnabledQueryFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function applyProductIsEnabledFilter(ProductIsEnabledFilter $productIsEnabledFilter, Search $search)
    {
        $search->addPostFilter(
            $this->productIsEnabledQueryFactory->create(['enabled' => $productIsEnabledFilter->getEnabled()]),
            BoolQuery::MUST
        );
    }
}
