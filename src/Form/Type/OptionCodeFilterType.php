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

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityRepository;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use FOS\ElasticaBundle\Repository;
use Lakion\SyliusElasticSearchBundle\Search\Criteria\Filtering\ProductHasOptionCodesFilter;
use Lakion\SyliusElasticSearchBundle\Search\Elastic\Factory\Query\QueryFactoryInterface;
use Lakion\SyliusElasticSearchBundle\Search\Elastic\Factory\Search\SearchFactoryInterface;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\FiltersAggregation;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Search;
use Sylius\Component\Product\Model\ProductOptionValue;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Arkadiusz Krakowiak <arkadiusz.krakowiak@lakion.com>
 */
final class OptionCodeFilterType extends AbstractType implements DataTransformerInterface
{
    /**
     * @var RepositoryManagerInterface
     */
    private $repositoryManager;

    /**
     * @var QueryFactoryInterface
     */
    private $productHasOptionCodeAndTaxonsQueryFactory;

    /**
     * @var string
     */
    private $productModelClass;

    /**
     * @var SearchFactoryInterface
     */
    private $searchFactory;

    /**
     * @var EntityRepository
     */
    private $productOptionValueRepository;

    /**
     * @var QueryFactoryInterface
     */
    private $productInProductTaxonsQueryFactory;

    /**
     * @param RepositoryManagerInterface $repositoryManager
     * @param QueryFactoryInterface      $productHasOptionCodeAndTaxonsQueryFactory
     * @param SearchFactoryInterface     $searchFactory
     * @param string                     $productModelClass
     * @param EntityRepository           $productOptionValueRepository
     * @param QueryFactoryInterface      $productInProductTaxonsQueryFactory
     */
    public function __construct(
        RepositoryManagerInterface $repositoryManager,
        QueryFactoryInterface $productHasOptionCodeAndTaxonsQueryFactory,
        SearchFactoryInterface $searchFactory,
        $productModelClass,
        EntityRepository $productOptionValueRepository,
        $productInProductTaxonsQueryFactory

    ) {
        $this->repositoryManager                         = $repositoryManager;
        $this->productHasOptionCodeAndTaxonsQueryFactory = $productHasOptionCodeAndTaxonsQueryFactory;
        $this->searchFactory                             = $searchFactory;
        $this->productModelClass                         = $productModelClass;
        $this->productOptionValueRepository              = $productOptionValueRepository;
        $this->productInProductTaxonsQueryFactory        = $productInProductTaxonsQueryFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var ProductOptionValueInterface[] $optionValuesUnfiltered */
        $optionValuesUnfiltered =
            $this
                ->productOptionValueRepository
                ->createQueryBuilder('o')
                ->addSelect('translation')
                ->leftJoin('o.option', 'option')
                ->innerJoin('o.translations', 'translation', 'WITH', 'translation.locale = :locale')
                ->andWhere('option.code = :optionCode')
                ->setParameter('optionCode', $options['option_code'])
                ->setParameter('locale', $options['locale'])
                ->addOrderBy('translation.value', 'ASC')
                ->getQuery()
                ->getResult()
        ;

        $aggregatedQuery = $this->buildAggregation($optionValuesUnfiltered, $options['taxon'])->toArray();
        /** @var Repository $repository */
        $repository   = $this->repositoryManager->getRepository($this->productModelClass);
        $result       = $repository->createPaginatorAdapter($aggregatedQuery);
        $aggregations = $result->getAggregations();

        /** @var ProductOptionValueInterface[] $optionValues */
        $optionValues = [];
        foreach ($optionValuesUnfiltered as $optionValue) {
            $codeCount = (int)$aggregations[$optionValue->getCode()]['buckets']['code']['doc_count'];
            if ($codeCount > 0) {
                $optionValues[] = $optionValue;
            }
        }
        unset($optionValuesUnfiltered);

        $builder->add(
            'code',
            EntityType::class,
            [
                'class'        => $options['class'],
                'choice_value' => function (ProductOptionValue $productOptionValue) {
                    return $productOptionValue->getCode();
                },
                'choices'      => $optionValues,
                'choice_label' => function (ProductOptionValue $productOptionValue) use ($options) {
                    return $productOptionValue->getValue();
                },
                'multiple'     => true,
                'expanded'     => true,
            ]
        );

        $builder->addModelTransformer($this);
    }

    /**
     * @param ProductOptionValueInterface[] $optionValues
     * @param string                        $taxon
     *
     * @return Search
     */
    private function buildAggregation($optionValues, $taxon)
    {
        $search = $this->searchFactory->create();

        $search->addPostFilter(
            $this->productInProductTaxonsQueryFactory->create(['taxon_code' => $taxon]),
            BoolQuery::MUST
        );

        foreach ($optionValues as $optionValue) {
            $hasOptionValueAggregation = new FiltersAggregation($optionValue->getCode());

            $hasOptionValueAggregation->addFilter(
                $this->productHasOptionCodeAndTaxonsQueryFactory->create(
                    [
                        'option_value_code' => $optionValue->getCode(),
                        'taxon_code'        => $taxon,
                    ]
                ),
                'code'
            );

            $search->addAggregation($hasOptionValueAggregation);
        }

        return $search;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefault('class', ProductOptionValue::class)
            ->setRequired('option_code')
            ->setAllowedTypes('option_code', 'string')
            ->setDefined('taxon')
            ->setAllowedTypes('taxon', 'string')
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
        if ($value['code'] instanceof Collection) {
            $productOptionCodes = $value['code']->map(
                function (ProductOptionValueInterface $productOptionValue) {
                    return $productOptionValue->getCode();
                }
            );

            if ($productOptionCodes->isEmpty()) {
                return null;
            }

            return new ProductHasOptionCodesFilter($productOptionCodes->toArray());
        }

        return null;
    }
}
