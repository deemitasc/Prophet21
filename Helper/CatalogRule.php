<?php

namespace Ripen\Prophet21\Helper;

use Magento\CatalogRule\Model\ResourceModel\Rule\CollectionFactory as CatalogRuleCollectionFactory;

class CatalogRule
{
    /**
     * @var CatalogRuleCollectionFactory
     */
    protected $catalogRuleCollectionFactory;

    public function __construct(
        CatalogRuleCollectionFactory $catalogRuleCollectionFactory
    ) {
        $this->catalogRuleCollectionFactory = $catalogRuleCollectionFactory;
    }

    /**
     * @param string $sku
     * @param $websiteId
     * @return \Magento\CatalogRule\Api\Data\RuleInterface|null
     */
    public function getRuleBySkuAndWebsiteId($sku, $websiteId)
    {
        $rule = $this->catalogRuleCollectionFactory->create()
            ->addFieldToFilter(
                'rule_sku', $sku
            );

        $rule->getSelect()->join(

            ['website' => 'catalogrule_website'],
            'website.rule_id = main_table.rule_id',
            []
        )->where(
            'website.website_id = ?', (int)$websiteId, 'int'
        );

        /** @var \Magento\CatalogRule\Api\Data\RuleInterface $ruleItem */
        $ruleItem = $rule->getFirstItem();

        return $ruleItem;
    }
}
