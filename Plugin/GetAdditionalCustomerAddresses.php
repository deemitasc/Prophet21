<?php

namespace Ripen\Prophet21\Plugin;

use Magento\Customer\Model\Customer;
use Magento\Framework\Exception\LocalizedException;

class GetAdditionalCustomerAddresses
{
    /**
     * @var \Magento\Framework\App\Request\Http
     */
    protected $request;

    /**
     * @var \Ripen\SimpleApps\Model\Api
     */
    protected $api;

    /**
     * @var \Magento\Customer\Model\AddressFactory
     */
    protected $addressFactory;

    /**
     * @var \Magento\Directory\Model\RegionFactory
     */
    protected $regionFactory;

    /**
     * @var \Magento\Directory\Model\ResourceModel\Region\CollectionFactory
     */
    protected $regionCollection;

    /**
     * @var \Magento\Directory\Model\CountryFactory
     */
    protected $countryFactory;

    /**
     * @var \Magento\Directory\Model\ResourceModel\Country\CollectionFactory
     */
    protected $countryCollection;

    /**
     * @var \Ripen\Prophet21\Logger\Logger
     */
    protected $logger;

    /**
     * @var \Ripen\Prophet21\Helper\Customer
     */
    protected $customerHelper;

    /**
     * Actions allowed to add additional addresses
     */
    const WHITELISTED_ACTIONS = [
        'checkout_index_index',
    ];

    /**
     * @param \Magento\Framework\App\Request\Http $request
     * @param \Ripen\SimpleApps\Model\Api $api
     * @param \Magento\Customer\Model\AddressFactory $addressFactory
     * @param \Magento\Directory\Model\RegionFactory $regionFactory
     * @param \Magento\Directory\Model\ResourceModel\Region\CollectionFactory $regionCollection
     * @param \Magento\Directory\Model\CountryFactory $countryFactory
     * @param \Magento\Directory\Model\ResourceModel\Country\CollectionFactory $countryCollection
     * @param \Ripen\Prophet21\Logger\Logger $logger
     * @param \Ripen\Prophet21\Helper\Customer $customerHelper
     */
    public function __construct(
        \Magento\Framework\App\Request\Http $request,
        \Ripen\SimpleApps\Model\Api $api,
        \Magento\Customer\Model\AddressFactory $addressFactory,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Magento\Directory\Model\ResourceModel\Region\CollectionFactory $regionCollection,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Magento\Directory\Model\ResourceModel\Country\CollectionFactory $countryCollection,
        \Ripen\Prophet21\Logger\Logger $logger,
        \Ripen\Prophet21\Helper\Customer $customerHelper
    ) {
        $this->request = $request;
        $this->api = $api;
        $this->addressFactory = $addressFactory;
        $this->regionFactory = $regionFactory;
        $this->regionCollection = $regionCollection;
        $this->countryFactory = $countryFactory;
        $this->countryCollection = $countryCollection;
        $this->logger = $logger;
        $this->customerHelper = $customerHelper;
    }

    /**
     * @param \Magento\Customer\Model\Customer $customer
     * @param \Magento\Customer\Model\Address[] $addresses
     * @return \Magento\Customer\Model\Address[]
     */
    public function afterGetAddresses(Customer $customer, $addresses)
    {
        // If an address field isn't explicitly set (even to an empty value) on Magento addresses, then the quote
        // address can retain a previous selection value. Must use zero instead of null because Magento will filter
        // out null values when passing to the frontend code.
        foreach ($addresses as $address) {
            if (empty($address->getData('prophet_21_id'))) {
                $address->setData('prophet_21_id', 0);
            }
        }

        if ($this->shouldAddAddresses($customer)) {
            $p21Addresses = [];

            // Check memoized value.
            if ($customer->getData('p21_addresses')) {
                $p21Addresses = $customer->getData('p21_addresses');
            } else {
                try {
                    $p21Addresses = $this->getP21Addresses($customer);
                } catch (\Throwable $e) {
                    // On exception, log and continue rather than interrupting checkout with a fatal error.
                    // TODO: Show a message in frontend that ship-to addresses could not be retrieved.
                    $this->logger->error($e->getMessage());
                }
                $customer->setData('p21_addresses', $p21Addresses);
            }

            $additionalAddresses = array_udiff($p21Addresses, $addresses, function ($a, $b) {
                return $a->getData('prophet_21_id') <=> $b->getData('prophet_21_id');
            });
            $addresses = array_merge($addresses, $additionalAddresses);
        }

        return $addresses;
    }

    /**
     * @param \Magento\Customer\Model\Customer $customer
     * @return array
     * @throws \Ripen\Prophet21\Exception\P21ApiException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function getP21Addresses(Customer $customer)
    {
        $p21Addresses = [];
        $customerCode = $this->customerHelper->getP21CustomerId($customer);
        $shipToAddresses = $this->api->getCustomerShipTos($customerCode);

        foreach ($shipToAddresses as $address) {
            try {
                // only include the address if it has a valid country code
                $countryCode = $this->getCountryIso2Code($address['country_id']);
                if (is_null($countryCode)) {
                    throw new \Exception($address['country_id'] . ' is not a valid country in the system.');
                }

                // only include the address if it has a valid region code
                $regionCode = $this->getRegionIso2Code($address['region'], $countryCode);
                if (is_null($regionCode)) {
                    throw new \Exception($address['region'] . ' is not a valid region in the system.');
                }

                $address['region'] = $regionCode;       // normalized to ISO2 code
                $address['country_id'] = $countryCode;  // normalized to ISO2 code

                $p21Addresses[] = $this->createInstanceFromApiAddress($customer, $address);
            } catch (\Throwable $e) {
                $this->logger->error("Failed to create Magento address instance from ship-to {$address['address_id']}: " . $e->getMessage());
            }
        }

        return $p21Addresses;
    }

    /**
     * @return bool
     */
    protected function shouldAddAddresses(Customer $customer)
    {
        if (in_array($this->request->getFullActionName(), self::WHITELISTED_ACTIONS)) {
            try {
                $customerCode = $this->customerHelper->getP21CustomerId($customer);
            } catch (LocalizedException $e) {
                $this->logger->error($e);
                return false;
            }

            return (! empty($customerCode));
        }

        return false;
    }

    /**
     * Given region name string (iso2 or full name), fetch its iso2 code in the system, if found.
     * Serves as a way to validate a given code.
     *
     * @param string $regionName
     * @param string $countryCode
     * @return string|null
     */
    protected function getRegionIso2Code($regionName, $countryCode)
    {
        switch (strlen($regionName)) {
            case 2:
                $regionSearch = $this->regionFactory->create()->loadByCode($regionName, $countryCode);
                $regionCode = $regionSearch->getCode();

                return (! empty($regionCode)) ? $regionCode : null;

            default:
                $regionSearch = $this->getRegionByName($regionName);

                return (! is_null($regionSearch)) ? $regionSearch->getCode() : null;
        }
    }

    /**
     * @param string $regionName
     * @return \Magento\Directory\Model\Region|null
     */
    protected function getRegionByName($regionName)
    {
        /** @var \Magento\Directory\Model\Region $region */
        $region = $this->regionCollection->create()
            ->addRegionNameFilter($regionName)
            ->getFirstItem();
        return $region;
    }

    /**
     * Given country name string (iso2, iso3, or full name), fetch its iso2 code in the system, if found
     * Serves as a way to validate a given code.
     *
     * @param string $countryName
     * @return string|null
     */
    protected function getCountryIso2Code($countryName)
    {
        if (empty($countryName)) {
            return 'US';
        }

        // first search as if $countryName is either iso2 or iso3
        $countryIsoCode = strtoupper($countryName);
        try {
            $countrySearch = $this->countryFactory->create()->loadByCode($countryIsoCode);
            $countryId = $countrySearch->getCountryId();

            return (! empty($countryId)) ? $countryId : null;
        }
        // otherwise treat $countryName as full country name string
        catch (LocalizedException $e) {
            $countryName = ucwords($countryName);
            $countrySearch = $this->getCountryByName($countryName);

            return (! is_null($countrySearch)) ? $countrySearch->getCountryId() : null;
        }
    }

    /**
     * @param string $countryName
     * @return \Magento\Directory\Model\Country|null
     */
    protected function getCountryByName($countryName)
    {
        $countryCollection = $this->countryCollection->create();

        /** @var \Magento\Directory\Model\Country $countryRecord */
        foreach ($countryCollection as $countryRecord) {
            if ($countryRecord->getName() == $countryName) {
                return $countryRecord;
                break;
            }
        }

        return null;
    }

    /**
     * @param \Magento\Customer\Model\Customer $customer
     * @param array $addressData
     * @return \Magento\Customer\Model\Address
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function createInstanceFromApiAddress(Customer $customer, array $addressData)
    {
        /** @var \Magento\Customer\Model\Address $address */
        $address = $this->addressFactory->create();

        $region = $this->regionFactory->create()->loadByCode($addressData['region'], $addressData['country_id']);

        $address->setData([
            'parent_id' => $customer->getId(),
            'prophet_21_id' => $addressData['address_id'],
            'is_active' => 1,
            'city' => $addressData['city'],
            'company' => $addressData['name'],
            'country_id' => $addressData['country_id'],
            'firstname' => $customer->getFirstname(),
            'lastname' => $customer->getLastname(),
            'middlename' => $customer->getMiddlename(),
            'postcode' => $addressData['postcode'],
            'prefix' => $customer->getPrefix(),
            'region' => $region->getName(),
            'region_id' => $region->getId(),
            'street' => implode("\n", array_filter([$addressData['street1'], $addressData['street2'] ?? null])),
            'suffix' => $customer->getSuffix(),
            'telephone' => $addressData['telephone'],
            'customer_id' => $customer->getId(),
            'p21_default_carrier_id' => $addressData['default_carrier_id'],
        ]);
        $address->setIdFieldName('prophet_21_id');

        return $address;
    }
}
