<?php
/**
 * Identifies carrier based on tracking number patterns
 *
 * @author SanGreel (Andrew Kurochkin), Lviv, Jim Chao <jchao@ripen.com>
 * @link https://github.com/SanGreel/delivery-service-recognition/blob/master/recognize_delivery_service.php
 */

namespace Ripen\Prophet21\Model;

class ShippingTrackingCarrierIdentifier extends \Magento\Framework\Model\AbstractModel
{
    /**
     * @param string $trackingNumber
     * @return string|null
     */
    public function getCarrierCode($trackingNumber)
    {
        if (empty($trackingNumber)) {
            return null;
        }

        // remove any spaces in the number
        $trackingNumber = str_replace(' ', '', $trackingNumber);

        if ($this->isUSPSTrack($trackingNumber)) {
            return 'usps';
        }
        if ($this->isUPSTrack($trackingNumber)) {
            return 'ups';
        }
        if ($this->isFedExTrack($trackingNumber)) {
            return 'fedex';
        }

        return \Magento\Sales\Model\Order\Shipment\Track::CUSTOM_CARRIER_CODE;
    }

    /**
     * @param string $carrierCode
     * @return bool
     */
    public function isCarrierCodeCustom($carrierCode)
    {
        return ($carrierCode === \Magento\Sales\Model\Order\Shipment\Track::CUSTOM_CARRIER_CODE);
    }

    /**
     * @param string $track
     * @return bool
     */
    protected function isUSPSTrack($track)
    {
        $usps = array();

        $usps[0] = '^(94|93|92|94|95)[0-9]{20}$';
        $usps[1] = '^(94|93|92|94|95)[0-9]{22}$';
        $usps[2] = '^(70|14|23|03)[0-9]{14}$';
        $usps[3] = '^(M0|82)[0-9]{8}$';
        $usps[4] = '^([A-Z]{2})[0-9]{9}([A-Z]{2})$';

        if (preg_match('/(' . $usps[0] . ')|(' . $usps[1] . ')|(' . $usps[2] . ')|(' . $usps[3] . ')|(' . $usps[4] . ')/', $track)) {
            return true;
        }

        return false;
    }

    /**
     * @param string $track
     * @return bool
     */
    protected function isUPSTrack($track)
    {
        $ups = array();

        $ups[0] = '^(1Z)[0-9A-Z]{16}$';
        $ups[1] = '^(T)+[0-9A-Z]{10}$';
        $ups[2] = '^[0-9]{9}$';
        $ups[3] = '^[0-9]{26}$';

        if (preg_match('/(' . $ups[0] . ')|(' . $ups[1] . ')|(' . $ups[2] . ')|(' . $ups[3] . ')/', $track)) {
            return true;
        }

        return false;
    }

    /**
     * @param string $track
     * @return bool
     */
    protected function isFedExTrack($track)
    {
        $fedex = array();

        $fedex[0] = '^[0-9]{20}$';
        $fedex[1] = '^[0-9]{15}$';
        $fedex[2] = '^[0-9]{12}$';
        $fedex[3] = '^[0-9]{22}$';

        if (preg_match('/(' . $fedex[0] . ')|(' . $fedex[1] . ')|(' . $fedex[2] . ')|(' . $fedex[3] . ')/', $track)) {
            return true;
        }

        return false;
    }
}
