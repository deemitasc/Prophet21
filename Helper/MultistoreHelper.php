<?php
/**
 * @TODO: move this class to Ripen_WholesaleCustomerRedirect, extracting the relevant store/website code methods to
 * where appropriate, and rename Ripen_WholesaleCustomerRedirect to be more generic like Ripen_WholesaleFeatureGating.
 * The reason being that due to the update that changed wholesale customer status to be customer group based, this class
 * more or less only exists to facilitate the domain/wholesale gating of Ripen_WholesaleCustomerRedirect, but only after
 * the store/website codes methods that Ripen_Prophet21 needs for product and order feeds are moved to appropriate locations
 */

namespace Ripen\Prophet21\Helper;

class MultistoreHelper extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \Magento\Framework\App\State
     */
    protected $state;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * MultistoreHelper constructor.
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\App\State $state
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\App\State $state,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->state = $state;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;

        parent::__construct($context);
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @deprecated For consistency, class should deal with current view only at website level, not stores.
     */
    protected function resolveCurrentStoreCode()
    {
        return $this->storeManager->getStore()->getCode();
    }

    /**
     * @return string|null
     */
    protected function resolveCurrentWebsiteCode()
    {
        try {
            return $this->storeManager->getWebsite()->getCode();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @deprecated Use isRetailWebsite()
     */
    public function isRetailStore()
    {
        return ($this->resolveCurrentStoreCode() == $this->getRetailStoreCode());
    }

    /**
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @deprecated Use isWholesaleWebsite()
     */
    public function isWholesaleStore()
    {
        return ($this->resolveCurrentStoreCode() == $this->getWholesaleStoreCode());
    }

    /**
     * @return bool
     */
    public function isRetailWebsite()
    {
        return ($this->resolveCurrentWebsiteCode() == $this->getRetailWebsiteCode());
    }

    /**
     * @return bool
     */
    public function isWholesaleWebsite()
    {
        return ($this->resolveCurrentWebsiteCode() == $this->getWholesaleWebsiteCode());
    }

    /**
     * @return string
     */
    public function getRetailStoreCode()
    {
        return $this->scopeConfig->getValue('p21/feeds/retail_store_code');
    }

    /**
     * @return string
     */
    public function getWholesaleStoreCode()
    {
        return $this->scopeConfig->getValue('p21/feeds/wholesale_store_code');
    }

    /**
     * @return string
     */
    public function getRetailWebsiteCode()
    {
        return $this->scopeConfig->getValue('p21/feeds/retail_website_code');
    }

    /**
     * @return string
     */
    public function getWholesaleWebsiteCode()
    {
        return $this->scopeConfig->getValue('p21/feeds/wholesale_website_code');
    }

    /**
     * @return string
     */
    public function getDefaultWebsiteUrl()
    {
        return $this->storeManager->getStore(
            $this->storeManager->getDefaultStoreView()->getWebsiteId()
        )->getBaseUrl();
    }

    /**
     * @param $code
     * @return int
     * @throws \Exception
     */
    protected function getStoreId($code)
    {
        $stores = $this->storeManager->getStores(true, false);
        foreach ($stores as $store) {
            if ($store->getCode() === $code) {
                return $store->getId();
            }
        }

        throw new \Exception("Invalid store code [{$code}]");
    }

    /**
     * @return int
     */
    public function getRetailStoreId()
    {
        return $this->getStoreId($this->getRetailStoreCode());
    }

    /**
     * @return int
     */
    public function getWholesaleStoreId()
    {
        return $this->getStoreId($this->getWholesaleStoreCode());
    }

    /**
     * @param $code
     * @return int
     * @throws \Exception
     */
    protected function getWebsiteId($code)
    {
        $websites = $this->storeManager->getWebsites(true, false);
        foreach ($websites as $website) {
            if ($website->getCode() === $code) {
                return $website->getId();
            }
        }

        throw new \Exception('Invalid website code.');
    }

    /**
     * @return int
     */
    public function getCurrentWebsiteId()
    {
        return $this->storeManager->getStore()->getWebsiteId();
    }

    /**
     * @return int
     */
    public function getRetailWebsiteId()
    {
        return $this->getWebsiteId($this->getRetailWebsiteCode());
    }

    /**
     * @return string
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getRetailWebsiteUrl()
    {
        return $this->storeManager->getStore(
            $this->getRetailWebsiteId()
        )->getBaseUrl();
    }

    /**
     * @return int
     */
    public function getWholesaleWebsiteId()
    {
        return $this->getWebsiteId($this->getWholesaleWebsiteCode());
    }

    /**
     * @return string
     */
    public function getWholesaleWebsiteUrl()
    {
        return $this->storeManager->getStore(
            $this->getWholesaleWebsiteId()
        )->getBaseUrl();
    }

    /**
     * @return array
     */
    protected function getStores()
    {
        $storeManagerDataList = $this->storeManager->getStores();
        return array_keys($storeManagerDataList);
    }

    /**
     * @return bool
     * @deprecated use isWholesaleWebsite()
     */
    public function isCurrentWebsiteWholesale()
    {
        try {
            $storeId = $this->storeManager->getStore()->getId();

            return ($storeId === $this->getWholesaleWebsiteId());
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return false;
        }
    }
}
