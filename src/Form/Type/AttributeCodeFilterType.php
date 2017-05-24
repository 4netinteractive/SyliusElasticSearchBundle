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
use Lakion\SyliusElasticSearchBundle\Search\Criteria\Filtering\ProductHasAttributeCodesFilter;
use Lakion\SyliusElasticSearchBundle\Search\Criteria\Filtering\ProductHasOptionCodesFilter;
use Lakion\SyliusElasticSearchBundle\Search\Elastic\Factory\Query\QueryFactoryInterface;
use Lakion\SyliusElasticSearchBundle\Search\Elastic\Factory\Search\SearchFactoryInterface;
use ONGR\ElasticsearchDSL\Aggregation\Bucketing\FiltersAggregation;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
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
     * @var QueryFactoryInterface
     */
    private $productInProductTaxonsQueryFactory;

    /**
     * @param RepositoryManagerInterface $repositoryManager
     * @param QueryFactoryInterface      $productHasAttributeCodeAndTaxonsQueryFactory
     * @param SearchFactoryInterface     $searchFactory
     * @param string                     $productModelClass
     * @param EntityRepository           $productAttributeValueRepository
     * @param QueryFactoryInterface      $productInProductTaxonsQueryFactory
     */
    public function __construct(
        RepositoryManagerInterface $repositoryManager,
        QueryFactoryInterface $productHasAttributeCodeAndTaxonsQueryFactory,
        SearchFactoryInterface $searchFactory,
        $productModelClass,
        EntityRepository $productAttributeValueRepository,
        $productInProductTaxonsQueryFactory
    ) {
        $this->repositoryManager                            = $repositoryManager;
        $this->productHasAttributeCodeAndTaxonsQueryFactory = $productHasAttributeCodeAndTaxonsQueryFactory;
        $this->searchFactory                                = $searchFactory;
        $this->productModelClass                            = $productModelClass;
        $this->productAttributeValueRepository              = $productAttributeValueRepository;
        $this->productInProductTaxonsQueryFactory           = $productInProductTaxonsQueryFactory;
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
        /** @var ProductAttributeValueInterface[] $optionValuesUniqueEntities */
        $optionValuesUnique = $optionValuesUniqueEntities = [];
        foreach ($optionValuesUnfiltered as $item) {
            if (!isset($optionValuesUnique[$item->getValue()])) {
                $optionValuesUnique[$item->getValue()] = true;
                $optionValuesUniqueEntities[]          = $item;
            }
        }
        unset($optionValuesUnfiltered);

        $aggregatedQuery = $this->buildAggregation($optionValuesUniqueEntities, $options['taxon'])->toArray();
        /** @var Repository $repository */
        $repository   = $this->repositoryManager->getRepository($this->productModelClass);
        $result       = $repository->createPaginatorAdapter($aggregatedQuery);
        $aggregations = $result->getAggregations();

        /** @var ProductAttributeValueInterface[] $attributeValues */
        $attributeValues = [];
        foreach ($optionValuesUniqueEntities as $optionValue) {
            $codeCount = (int)$aggregations[$optionValue->getValue()]['buckets']['code']['doc_count'];
            if ($codeCount > 0) {
                $attributeValues[] = $optionValue;
            }
        }

        $builder->add(
            'attribute',
            EntityType::class,
            [
                'class'        => $options['class'],
                'choice_value' => function (ProductAttributeValue $attributeValue) {
                    return str_replace(
                        ['(', ')', ' ', '.', '%', '/', ',', '.'],
                        ['_', '_', '_', '_', '_', '_', '_', '_'],
                        $attributeValue->getValue()
                    );
                },
                'block_name'   => '',
                'choices'      => $attributeValues,
                'choice_label' => function (ProductAttributeValue $attributeValue) use ($options) {
                    return $attributeValue->getValue();
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
        $search = $this->searchFactory->create();
        $search->addPostFilter(
            $this->productInProductTaxonsQueryFactory->create(['taxon_code' => $taxon]),
            BoolQuery::MUST
        );
        foreach ($optionValues as $optionValue) {
            $hasOptionValueAggregation = new FiltersAggregation($optionValue->getValue());

            $hasOptionValueAggregation->addFilter(
                $this->productHasAttributeCodeAndTaxonsQueryFactory->create(
                    [
                        'attribute_value_code' => $optionValue->getValue(),
                        'taxon_code'           => $taxon,
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
            ->setRequired('attribute_code')
            ->setAllowedTypes('attribute_code', 'string')
            ->setDefined('taxon')
            ->setAllowedTypes('taxon', 'string')
            ->setDefined('locale')
            ->setAllowedTypes('locale', 'string')
        ;
    }

    public function getBlockPrefix()
    {
        return 'attributes';
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
        if ($value['attribute'] instanceof Collection) {
            $productOptionCodes = $value['attribute']->map(
                function (ProductAttributeValue $productOptionValue) {
                    return $productOptionValue->getValue();
                }
            );

            if ($productOptionCodes->isEmpty()) {
                return null;
            }

            return new ProductHasAttributeCodesFilter($productOptionCodes->toArray());
        }

        return null;
    }
}
