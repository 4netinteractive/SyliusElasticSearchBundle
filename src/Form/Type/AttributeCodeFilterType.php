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
use ONGR\ElasticsearchDSL\Search;
use Sylius\Component\Product\Model\ProductAttributeValue;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Sylius\Component\Product\Model\ProductOptionValue;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Arvids Godjuks <arvids.godjuks@gmail.com>
 */
final class AttributeCodeFilterType extends AbstractType implements DataTransformerInterface
{
    /**
     * @var RepositoryManagerInterface
     */
    private $repositoryManager;

    /**
     * @var QueryFactoryInterface
     */
    private $productHasAttributeCodeAndTaxonsQueryFactory;

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
    private $productAttributeValueRepository;

    /**
     * @param RepositoryManagerInterface $repositoryManager
     * @param QueryFactoryInterface      $productHasAttributeCodeAndTaxonsQueryFactory
     * @param SearchFactoryInterface     $searchFactory
     * @param string                     $productModelClass
     * @param EntityRepository           $productAttributeValueRepository
     */
    public function __construct(
        RepositoryManagerInterface $repositoryManager,
        QueryFactoryInterface $productHasAttributeCodeAndTaxonsQueryFactory,
        SearchFactoryInterface $searchFactory,
        $productModelClass,
        EntityRepository $productAttributeValueRepository
    ) {
        $this->repositoryManager                            = $repositoryManager;
        $this->productHasAttributeCodeAndTaxonsQueryFactory = $productHasAttributeCodeAndTaxonsQueryFactory;
        $this->searchFactory                                = $searchFactory;
        $this->productModelClass                            = $productModelClass;
        $this->productAttributeValueRepository              = $productAttributeValueRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var ProductAttributeValue[] $optionValuesUnfiltered */
        $optionValuesUnfiltered =
            $this
                ->productAttributeValueRepository
                ->createQueryBuilder('o')
                ->distinct(true)
                ->leftJoin('o.attribute', 'attribute')
                ->andWhere('attribute.code = :attributeCode')
                ->andWhere('o.localeCode = :locale')
                ->setParameter('attributeCode', $options['attribute_code'])
                ->setParameter('locale', $options['locale'])
                ->getQuery()
                ->getResult()
        ;
        $optionValuesUnique = [];
        foreach ($optionValuesUnfiltered as $item) {
            if (!isset($optionValuesUnique[$item->getValue()])) {
                $optionValuesUnique[$item->getValue()] = $item;
            }
        }
        unset($optionValuesUnfiltered);

        $aggregatedQuery = $this->buildAggregation($optionValuesUnique, $options['taxon'])->toArray();
        /** @var Repository $repository */
        $repository   = $this->repositoryManager->getRepository($this->productModelClass);
        $result       = $repository->createPaginatorAdapter($aggregatedQuery);
        $aggregations = $result->getAggregations();

        /** @var ProductAttributeValue[] $optionValues */
        $optionValues = [];
        foreach ($optionValuesUnique as $optionValue) {
            $codeCount = (int)$aggregations[$optionValue->getValue()]['buckets']['code']['doc_count'];
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
                'choice_value' => function (ProductAttributeValue $productOptionValue) {
                    return $productOptionValue->getValue();
                },
                'choices'      => $optionValues,
                'choice_label' => function (ProductAttributeValue $productOptionValue) use ($options) {
                    return $productOptionValue->getValue();
                },
                'multiple'     => true,
                'expanded'     => true,
            ]
        );

        $builder->addModelTransformer($this);
    }

    /**
     * @param ProductAttributeValueInterface[] $optionValues
     * @param string                           $taxon
     *
     * @return Search
     */
    private function buildAggregation($optionValues, $taxon)
    {
        $aggregationSearch = $this->searchFactory->create();
        foreach ($optionValues as $optionValue) {
            $hasOptionValueAggregation = new FiltersAggregation($optionValue->getValue());

            $hasOptionValueAggregation->addFilter(
                $this->productHasAttributeCodeAndTaxonsQueryFactory->create(
                    [
                        'attribute_value_code' => $optionValue->getValue(),
                        'taxon_code'        => $taxon,
                    ]
                ),
                'code'
            );

            $aggregationSearch->addAggregation($hasOptionValueAggregation);
        }

        return $aggregationSearch;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefault('class', ProductOptionValue::class)
            ->setRequired('attribute_code')
            ->setAllowedTypes('attribute_code', 'string')
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
