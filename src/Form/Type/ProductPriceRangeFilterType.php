<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Lakion\SyliusElasticSearchBundle\Form\Type;

use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Lakion\SyliusElasticSearchBundle\Search\Criteria\Filtering\ProductInPriceRangeFilter;
use Lakion\SyliusElasticSearchBundle\Search\Elastic\Factory\Query\QueryFactoryInterface;
use Lakion\SyliusElasticSearchBundle\Search\Elastic\Factory\Search\SearchFactoryInterface;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\FiltersAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\NestedAggregation;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Aggregation\Metric\MinAggregation;
use ONGR\ElasticsearchDSL\Aggregation\Metric\MaxAggregation;
use ONGR\ElasticsearchDSL\Query\FullText\MatchQuery;
use ONGR\ElasticsearchDSL\Query\Joining\NestedQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Search;
use Sylius\Bundle\MoneyBundle\Form\Type\MoneyType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Arkadiusz Krakowiak <arkadiusz.krakowiak@lakion.com>
 */
final class ProductPriceRangeFilterType extends AbstractType implements DataTransformerInterface
{
    /**
     * @var RepositoryManagerInterface
     */
    private $repositoryManager;

    /**
     * @var string
     */
    private $productModelClass;

    /**
     * @var QueryFactoryInterface
     */
    private $productHasOptionCodeAndTaxonsQueryFactory;

    /**
     * @var SearchFactoryInterface
     */
    private $searchFactory;

    /**
     * @var QueryFactoryInterface
     */
    private $productInProductTaxonsQueryFactory;

    /**
     * @param RepositoryManagerInterface $repositoryManager
     * @param string                     $productModelClass
     * @param QueryFactoryInterface      $productHasOptionCodeAndTaxonsQueryFactory
     * @param SearchFactoryInterface     $searchFactory
     * @param QueryFactoryInterface      $productInProductTaxonsQueryFactory
     */
    public function __construct(
        RepositoryManagerInterface $repositoryManager,
        $productModelClass,
        QueryFactoryInterface $productHasOptionCodeAndTaxonsQueryFactory,
        SearchFactoryInterface $searchFactory,
        QueryFactoryInterface $productInProductTaxonsQueryFactory

    ) {
        $this->repositoryManager                         = $repositoryManager;
        $this->productModelClass                         = $productModelClass;
        $this->productHasOptionCodeAndTaxonsQueryFactory = $productHasOptionCodeAndTaxonsQueryFactory;
        $this->searchFactory                             = $searchFactory;
        $this->productInProductTaxonsQueryFactory        = $productInProductTaxonsQueryFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $search       = $this->buildAggregation($options)->toArray();
        $repository   = $this->repositoryManager->getRepository($this->productModelClass);
        $result       = $repository->createPaginatorAdapter($search);
        $aggregations = $result->getAggregations();
        $builder
            ->add('grater_than', MoneyType::class,
                [
                    'empty_data' => $this->getRecursiveValue($aggregations, 'min'),
                    'data' => $this->getRecursiveValue($aggregations, 'min'),
                    'attr' => ['min' => (int)$this->getRecursiveValue($aggregations, 'min')],
                ]
            )
            ->add('less_than', MoneyType::class,
                [
                    'data' => $this->getRecursiveValue($aggregations, 'max'),
                    'attr' => ['max' => (int)$this->getRecursiveValue($aggregations, 'max')],
                ]
            )
            ->addModelTransformer($this)
        ;
    }

    /**
     * @param array $options
     *
     * @return Search
     */
    private function buildAggregation($options)
    {
        $search = $this->searchFactory->create();

        if (!is_null($options['taxon'])) {
            $search->addQuery(
                $this->productInProductTaxonsQueryFactory->create(['taxon_code' => strtolower($options['taxon'])]),
                BoolQuery::MUST
            );
        }

        if (array_key_exists('search', $options) && !is_null($options['search'])) {
            $search->addQuery(
                new NestedQuery('translations', new MatchQuery('translations.name', $options['search']))
            );
        }

        $search->addQuery(new TermQuery('enabled', true));

        foreach (['min', 'max'] as $filter) {
            if ($filter === 'min') {
                $aggregationClass = new MinAggregation($filter, 'variants.channelPricings.price');
            } else {
                $aggregationClass = new MaxAggregation($filter, 'variants.channelPricings.price');
            }

            $nestedAggregation = new NestedAggregation($filter, 'variants.channelPricings');
            $nestedAggregation->addAggregation($aggregationClass);

            // 1st level agg
            $priceAggregation = new NestedAggregation($filter, 'variants');
            $priceAggregation->addAggregation($nestedAggregation);

            $search->addAggregation($priceAggregation);
        }
        $search->setSize(0);

        return $search;
    }

    /**
     * @param array  $array
     * @param string $key
     *
     * @return mixed
     */
    private function getRecursiveValue($array, $key)
    {
        if (isset($array[$key])) {
            return $this->getRecursiveValue($array[$key], $key);
        } else {
            return $key === 'min'
                ? floor((int)$array['value'] / 100) * 100
                : ceil((int)$array['value'] / 100) * 100;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefined('taxon')
            ->setAllowedTypes('taxon', ['string', 'null'])
            ->setDefined('search')
            ->setAllowedTypes('search', ['string', 'null'])
            ->setDefined('locale')
            ->setAllowedTypes('locale', 'string')
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function transform($value)
    {
        if (null === $value) {
            return null;
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function reverseTransform($value)
    {
        if (null === $value['grater_than'] || null === $value['less_than']) {
            return null;
        }

        return new ProductInPriceRangeFilter($value['grater_than'], $value['less_than']);
    }

}
