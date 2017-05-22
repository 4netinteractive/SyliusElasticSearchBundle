<?php

namespace Lakion\SyliusElasticSearchBundle\Search\Criteria\Filtering;

/**
 * @author Arvids Godjuks <arvids.godjuks@gmail.com>
 */
final class ProductInCodeTaxonFilter
{
    /**
     * @var string
     */
    private $taxonCode;

    /**
     * @var string
     */
    private $optionCode;

    /**
     * @param string $taxonCode
     * @param string $optionCode
     */
    public function __construct($taxonCode, $optionCode)
    {
        $this->taxonCode = $taxonCode;
        $this->optionCode = $optionCode;
    }

    /**
     * @return string
     */
    public function getTaxonCode()
    {
        return $this->taxonCode;
    }

    /**
     * @return string
     */
    public function getOptionCode()
    {
        return $this->optionCode;
    }
}
