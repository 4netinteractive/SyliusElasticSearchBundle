<?php

namespace Lakion\SyliusElasticSearchBundle\Search\Elastic\Applicator\Filter;

use Lakion\SyliusElasticSearchBundle\Search\Criteria\Filtering\ProductInAttributeTaxonFilter;
use Lakion\SyliusElasticSearchBundle\Search\Elastic\Applicator\SearchCriteriaApplicator;
use Lakion\SyliusElasticSearchBundle\Search\Elastic\Factory\Query\QueryFactoryInterface;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Search;

/**
 * @author Arvids Godjuks <arvids.godjuks@gmail.com>
 */
final class ProductInAttributeAndTaxonApplicator extends SearchCriteriaApplicator
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
    private $productHasAttributeCodeQueryFactory;

    /**
     * @param QueryFactoryInterface $productInMainTaxonQueryFactory
     * @param QueryFactoryInterface $productInProductTaxonsQueryFactory
     * @param QueryFactoryInterface $productHasAttrbiuteCodeQueryFactory
     */
    public function __construct(
        QueryFactoryInterface $productInMainTaxonQueryFactory,
        QueryFactoryInterface $productInProductTaxonsQueryFactory,
        QueryFactoryInterface $productHasAttributeCodeQueryFactory
    ) {
        $this->productInMainTaxonQueryFactory = $productInMainTaxonQueryFactory;
        $this->productInProductTaxonsQueryFactory = $productInProductTaxonsQueryFactory;
        $this->productHasAttributeCodeQueryFactory = $productHasAttributeCodeQueryFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function applyProductInTaxonFilter(ProductInAttributeTaxonFilter $inCodeTaxonFilter, Search $search)
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
            $this->productHasAttributeCodeQueryFactory->create(['attribute_code' => $inCodeTaxonFilter->getAttributeCode()]),
            BoolQuery::MUST
        );
    }
}
