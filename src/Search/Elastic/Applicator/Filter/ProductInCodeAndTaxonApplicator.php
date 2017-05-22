<?php

namespace Lakion\SyliusElasticSearchBundle\Search\Elastic\Applicator\Filter;

use Lakion\SyliusElasticSearchBundle\Search\Criteria\Filtering\ProductInCodeTaxonFilter;
use Lakion\SyliusElasticSearchBundle\Search\Elastic\Applicator\SearchCriteriaApplicator;
use Lakion\SyliusElasticSearchBundle\Search\Elastic\Factory\Query\QueryFactoryInterface;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Search;

/**
 * @author Arvids Godjuks <arvids.godjuks@gmail.com>
 */
final class ProductInCodeAndTaxonApplicator extends SearchCriteriaApplicator
{
    /**
     * @var QueryFactoryInterface
     */
    private $productInMainTaxonQueryFactory;

    /**
     * @var QueryFactoryInterface
     */
    private $productInProductTaxonsQueryFactory;

    /**
     * @var QueryFactoryInterface
     */
    private $productHasOptionCodeQueryFactory;

    /**
     * @param QueryFactoryInterface $productInMainTaxonQueryFactory
     * @param QueryFactoryInterface $productInProductTaxonsQueryFactory
     * @param QueryFactoryInterface $productHasOptionCodeQueryFactory
     */
    public function __construct(
        QueryFactoryInterface $productInMainTaxonQueryFactory,
        QueryFactoryInterface $productInProductTaxonsQueryFactory,
        QueryFactoryInterface $productHasOptionCodeQueryFactory
    ) {
        $this->productInMainTaxonQueryFactory = $productInMainTaxonQueryFactory;
        $this->productInProductTaxonsQueryFactory = $productInProductTaxonsQueryFactory;
        $this->productHasOptionCodeQueryFactory = $productHasOptionCodeQueryFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function applyProductInTaxonFilter(ProductInCodeTaxonFilter $inCodeTaxonFilter, Search $search)
    {
        $search->addPostFilter(
            $this->productInMainTaxonQueryFactory->create(['taxon_code' => $inCodeTaxonFilter->getTaxonCode()]),
            BoolQuery::SHOULD
        );
        $search->addPostFilter(
            $this->productInProductTaxonsQueryFactory->create(['taxon_code' => $inCodeTaxonFilter->getTaxonCode()]),
            BoolQuery::MUST
        );
        $search->addPostFilter(
            $this->productHasOptionCodeQueryFactory->create(['option_code' => $inCodeTaxonFilter->getOptionCode()]),
            BoolQuery::MUST
        );
    }
}
