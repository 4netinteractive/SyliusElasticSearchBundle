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

use AppBundle\Form\AttributeEntityType;
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
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Search;
use Sylius\Component\Attribute\AttributeType\SelectAttributeType;
use Sylius\Component\Attribute\AttributeType\TextAttributeType;
use Sylius\Component\Product\Model\ProductAttribute;
use Sylius\Component\Product\Model\ProductAttributeValue;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Sylius\Component\Product\Model\ProductOptionValue;
use Sylius\Component\Product\Model\ProductOptionValueInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
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
     * @var EntityRepository
     */
    private $productAttributeRepository;

    /**
     * @var QueryFactoryInterface
     */
    private $productInProductTaxonsQueryFactory;

    /**
     * @var FactoryInterface
     */
    private $productAttributeValueFactory;

    /**
     * @param RepositoryManagerInterface $repositoryManager
     * @param QueryFactoryInterface      $productHasAttributeCodeAndTaxonsQueryFactory
     * @param SearchFactoryInterface     $searchFactory
     * @param string                     $productModelClass
     * @param EntityRepository           $productAttributeValueRepository
     * @param QueryFactoryInterface      $productInProductTaxonsQueryFactory
     * @param EntityRepository           $productAttributeRepository
     * @param FactoryInterface           $productAttributeValueFactory
     */
    public function __construct(
        RepositoryManagerInterface $repositoryManager,
        QueryFactoryInterface $productHasAttributeCodeAndTaxonsQueryFactory,
        SearchFactoryInterface $searchFactory,
        $productModelClass,
        EntityRepository $productAttributeValueRepository,
        $productInProductTaxonsQueryFactory,
        EntityRepository $productAttributeRepository,
        FactoryInterface $productAttributeValueFactory
    ) {
        $this->repositoryManager                            = $repositoryManager;
        $this->productHasAttributeCodeAndTaxonsQueryFactory = $productHasAttributeCodeAndTaxonsQueryFactory;
        $this->searchFactory                                = $searchFactory;
        $this->productModelClass                            = $productModelClass;
        $this->productAttributeValueRepository              = $productAttributeValueRepository;
        $this->productInProductTaxonsQueryFactory           = $productInProductTaxonsQueryFactory;
        $this->productAttributeRepository                   = $productAttributeRepository;
        $this->productAttributeValueFactory                 = $productAttributeValueFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (!is_array($options['attribute_code'])) {
            $options['attribute_code'] = [$options['attribute_code']];
        }

        /** @var ProductAttributeValueInterface[] $attributeValues */
        $attributeValues = [];

        foreach ($options['attribute_code'] as $attributeCode) {
            /** @var ProductAttribute $attribute */
            $attribute = $this->productAttributeRepository->findOneBy(['code' => $attributeCode]);

            if ($attribute->getType() === SelectAttributeType::TYPE) {
                $query = $this
                    ->productAttributeValueRepository
                    ->createQueryBuilder('o')
                    ->select('o.json')
                    ->distinct()
                    ->andWhere('o.localeCode = :locale')
                    ->andWhere('o.attribute = :attributeId')
                    ->setParameter(':attributeId', $attribute->getId())
                    ->setParameter(':locale', $options['locale'])
                    ->getQuery()
                ;
                foreach ($query->getResult() as $item) {
                    /** @var ProductAttributeValue $entity */
                    $entity = $this->productAttributeValueFactory->createNew();
                    $entity->setAttribute($attribute);
                    $entity->setValue($item['json']);
                    $attributeValues[] = $entity;
                }
            } else {
                /** @var ProductAttributeValue[] $optionValuesUnfiltered */
                $query =
                    $this
                        ->productAttributeValueRepository
                        ->createQueryBuilder('o')
                        ->select('o.text')
                        ->distinct(true)
                        ->leftJoin('o.attribute', 'attribute')
                        ->andWhere('attribute.code = :attributeCode')
                        ->andWhere('o.localeCode = :locale')
                        ->setParameter('attributeCode', $attributeCode)
                        ->setParameter('locale', $options['locale'])
                        ->getQuery()
                ;
                foreach ($query->getResult() as $item) {
                    /** @var ProductAttributeValue $entity */
                    $entity = $this->productAttributeValueFactory->createNew();
                    $entity->setAttribute($attribute);
                    $entity->setValue($item['text']);
                    $attributeValues[] = $entity;
                }

            }
        }

        $aggregatedQuery = $this->buildAggregation($attributeValues, $options)->toArray();
        /** @var Repository $repository */
        $repository   = $this->repositoryManager->getRepository($this->productModelClass);
        $result       = $repository->createPaginatorAdapter($aggregatedQuery);
        $aggregations = $result->getAggregations();

        /** @var ProductAttributeValueInterface[] $attributeValues */
        $attributeValuesFiltered = [];
        foreach ($attributeValues as $optionValue) {
            if ($optionValue->getAttribute()->getType() === SelectAttributeType::TYPE) {
                $value = $optionValue->getValue()[0];
            } else {
                $value = $optionValue->getValue();
            }
            $codeCount = (int)$aggregations[$value]['buckets']['code']['doc_count'];
            if ($codeCount > 0) {
                $attributeValuesFiltered[] = $optionValue;
            }
        }

        $builder->add(
            'attribute',
            EntityType::class,
            [
                'class'        => $options['class'],
                'choice_value' => function (ProductAttributeValue $attributeValue) {
                    if ($attributeValue->getAttribute()->getType() === SelectAttributeType::TYPE) {
                        return $attributeValue->getValue()[0];
                    } else {
                        return $attributeValue->getValue();
                    }
                },
                'choices'      => $attributeValuesFiltered,
                'choice_label' => function (ProductAttributeValue $attributeValue) {
                    if ($attributeValue->getAttribute()->getType() === SelectAttributeType::TYPE) {
                        return $attributeValue->getValue()[0];
                    } else {
                        return $attributeValue->getValue();
                    }
                },
                'choice_name'  => function (ProductAttributeValue $attributeValue) {
                    return uniqid();
                },
                'choice_attr'   => function (ProductAttributeValue $attributeValue) use ($aggregations) {
                    if ($attributeValue->getAttribute()->getType() === SelectAttributeType::TYPE) {
                        return ['data' => (int)$aggregations[$attributeValue->getValue()[0]]['buckets']['code']['doc_count']];
                    } else {
                        return ['data' => (int)$aggregations[$attributeValue->getValue()]['buckets']['code']['doc_count']];
                    }
                },
                'multiple'     => true,
                'expanded'     => true,
            ]
        );

        $builder->addModelTransformer($this);
    }

    /**
     * @param ProductAttributeValueInterface[] $optionValues
     * @param array                            $options
     *
     * @return Search
     */
    private function buildAggregation($optionValues, array $options)
    {
        $search = $this->searchFactory->create();
        $search->addPostFilter(
            new TermQuery('enabled', true),
            BoolQuery::MUST
        );
        if (!is_null($options['taxon'])) {
            $search->addPostFilter(
                $this->productInProductTaxonsQueryFactory->create(['taxon_code' => $options['taxon']]),
                BoolQuery::MUST
            );
        }
        foreach ($optionValues as $optionValue) {
            if ($optionValue->getAttribute()->getType() === SelectAttributeType::TYPE) {
                $value = $optionValue->getValue()[0];
            } else {
                $value = $optionValue->getValue();
            }
            $hasOptionValueAggregation = new FiltersAggregation($value);

            $hasOptionValueAggregation->addFilter(
                $this->productHasAttributeCodeAndTaxonsQueryFactory->create(
                    [
                        'attribute_value' => $value,
                        'taxon_code'      => $options['taxon'],
                        'attribute_code'  => $optionValue->getCode(),
                    ]
                ),
                'code'
            );

            $search->addAggregation($hasOptionValueAggregation);
        }
        $search->setSize(0);
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
            ->setAllowedTypes('attribute_code', ['array', 'string'])
            ->setDefined('taxon')
            ->setAllowedTypes('taxon', ['string', 'null'])
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
