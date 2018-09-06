<?php

namespace Lakion\SyliusElasticSearchBundle\Search\Elastic\Factory\Sort;

use Lakion\SyliusElasticSearchBundle\Search\Criteria\Ordering;
use ONGR\ElasticsearchDSL\Sort\FieldSort;

/**
 * @author Arkadiusz Krakowiak <arkadiusz.krakowiak@lakion.com>
 */
final class SortByFieldQueryFactory implements SortFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(Ordering $ordering)
    {
        switch ($ordering->getField()) {
            case 'price':
                return new FieldSort(
                    'variants.channelPricings.' . $ordering->getField(),
                    $ordering->getDirection(),
                    [
                        'nested_path' => 'variants.channelPricings',
                        'mode' => 'min'
                    ]
                );
                break;
            case 'position':
                return new FieldSort(
                    'productTaxons.' . $ordering->getField(),
                    $ordering->getDirection(),[
                        'nested_path' => 'productTaxons',
                        'mode' => 'min',
                        'nested_filter' => [
                            'term' => [
                                'productTaxons.taxon.code' => $ordering->getTaxonCode()
                            ]
                        ]
                    ]
                );
                break;
            default:
                return new FieldSort('raw_' . $ordering->getField(), $ordering->getDirection());
        }

    }
}
