<?php

namespace Lakion\SyliusElasticSearchBundle\Search\Elastic\Factory\Query;

use Lakion\SyliusElasticSearchBundle\Exception\MissingQueryParameterException;
use ONGR\ElasticsearchDSL\Query\Compound\BoolQuery;
use ONGR\ElasticsearchDSL\Query\Joining\NestedQuery;
use ONGR\ElasticsearchDSL\Query\FullText\MatchQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\PrefixQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermQuery;
use ONGR\ElasticsearchDSL\Query\TermLevel\TermsQuery;

/**
 * @author Arkadiusz Krakowiak <arkadiusz.krakowiak@lakion.com>
 */
final class MatchProductNameQueryFactory implements QueryFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(array $parameters = [])
    {
        if (!isset($parameters['phrase']) || null == $parameters['phrase']) {
            throw new MissingQueryParameterException('search', get_class($this));
        }

        $query = new BoolQuery();
        $query->add(
            new NestedQuery('translations', new MatchQuery('translations.name', $parameters['phrase'])),
            BoolQuery::SHOULD
        );
        $query->add(
            new NestedQuery('variants', new PrefixQuery('variants.code', $parameters['phrase'])),
            BoolQuery::SHOULD
        );

        $query->add(
            new NestedQuery(
                'attributes',
                new MatchQuery('attributes.value', $parameters['phrase'], ['operator' => 'or'])
            ),
            BoolQuery::SHOULD
        );

        return $query;
    }
}
