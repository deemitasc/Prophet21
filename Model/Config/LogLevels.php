<?php

namespace Ripen\Prophet21\Model\Config;

use Ripen\Prophet21\Logger\Logger;

class LogLevels implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public function toOptionArray()
    {
        $levels = array_keys(Logger::getLevels());
        return array_map(function ($level) { return ['value' => $level, 'label' => $level]; }, $levels);
    }
}
