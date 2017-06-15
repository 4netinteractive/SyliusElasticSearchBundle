<?php

namespace Lakion\SyliusElasticSearchBundle\Search\Elastic\Applicator\Filter;

use Lakion\SyliusElasticSearchBundle\Search\Criteria\Filtering\ProductInTaxonFilter;
use Lakion\SyliusElasticSearchBundle\Search\Criteria\Filtering\ProductIsEnabledFilter;
use Lakion\SyliusElasticSearchBundle\Search\Criteria\Filtering\ProductIsOnHandFilter;
use Lakion\SyliusElasticSearchBundle\Search\Elastic\Applicator\SearchCriteriaApplicator;
use Lakion\SyliusElasticSearchBundle\Search\Elastic\Factory\Query\QueryFactoryInterface;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Search;

/**
 * @author Arkadiusz Krakowiak <arkadiusz.krakowiak@lakion.com>
 */
final class ProductIsOnHandApplicator extends SearchCriteriaApplicator
{
    /**
     * @var QueryFactoryInterface
     */
    private $productIsOnHandQueryFactory;

    /**
     * @param QueryFactoryInterface $productIsOnHandQueryFactory
     */
    public function __construct(
        QueryFactoryInterface $productIsOnHandQueryFactory
    ) {
        $this->productIsOnHandQueryFactory = $productIsOnHandQueryFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function applyProductIsOnHandFilter(ProductIsOnHandFilter $productIsEnabledFilter, Search $search)
    {
        $search->addPostFilter(
            $this->productIsOnHandQueryFactory->create(),
            BoolQuery::MUST
        );
    }
}
