<?php

namespace Lakion\SyliusElasticSearchBundle\Search\Criteria\Filtering;

/**
 * @author Arvids Godjuks <arvids.godjuks@gmail.com>
 */
final class ProductInAttributeTaxonFilter
{
    /**
     * @var string
     */
    private $taxonCode;

    /**
     * @var string
     */
    private $attributeCode;

    /**
     * @param string $taxonCode
     * @param string $attributeCode
     */
    public function __construct($taxonCode, $attributeCode)
    {
        $this->taxonCode = $taxonCode;
        $this->attributeCode = $attributeCode;
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
    public function getAttributeCode()
    {
        return $this->attributeCode;
    }
}
