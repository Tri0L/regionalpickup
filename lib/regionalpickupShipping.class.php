<?php
/**
 * Модуль расчета доставки в Пункты выдачи заказов с разбивкой по регионам.
 *
 * This library is free software; you can redistribute it and/or modify it
 * under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation; either version 2.1 of the License, or
 * (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser General Public
 * License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this library; if not, write to the
 * Free Software Foundation, Inc.,
 * 59 Temple Place, Suite 330,
 * Boston, MA 02111-1307 USA
 *
 * @license http://www.gnu.org/licenses/lgpl.html LGPL-2.1
 * @author Serge Rodovnichenko <sergerod@gmail.com>
 * @copyright (C) 2014 Serge Rodovnichenko <sergerod@gmail.com>
 * @version 1.2
 */
class regionalpickupShipping extends waShipping
{

    public function allowedCurrency()
    {
        return $this->currency;
    }

    public function allowedAddress()
    {
        $address = array();

        foreach ($this->rate_zone as $field => $value) {
            if (!empty($value)) {
                $address[$field] = $value;
            }
        }

        return array($address);
    }

    public function allowedWeightUnit()
    {
        return 'kg';
    }

    protected function calculate()
    {
        $address = $this->getAddress();

        if(
            !isset($address['country'])
            || $address['country'] !== $this->rate_zone['country']
            || !isset($address['region'])
            || $address['region'] !== $this->rate_zone['region']
        )
        {
            return _wp('No suitable pick-up points');
        }

        $rates = $this->rate;
        $currency = $this->currency;
        $weight = $this->getTotalWeight();
        $cost = $this->getTotalPrice();

        $deliveries = array();

        for ($i = 1; $i < count($rates); $i++) {
            if ($this->isAllowedWeight($rates[$i], $weight)) {
                $deliveries[$i] = array(
                    'name' => $rates[$i]['location'],
                    'currency' => $currency,
                    'rate' => $this->calcCost($rates[$i], $cost),
                    'est_delivery' => ''
                );
            }
        }

        return empty($deliveries) ? _wp('No suitable pick-up points') : $deliveries;
    }

    public function getSettingsHTML(array $params = array())
    {
        $values = $this->getSettings();
        if (!empty($params['value'])) {
            $values = array_merge($values, $params['value']);
        }

        $namespace = '';
        if ($params['namespace']) {
            if (is_array($params['namespace'])) {
                $namespace = '[' . implode('][', $params['namespace']) . ']';
            } else {
                $namespace = $params['namespace'];
            }
        }

        $view = wa()->getView();
        $view->assign(array(
                'namespace' => $namespace,
                'values' => $values,
                'p' => $this
            ));

        $html = $view->fetch($this->path . '/templates/settings.html');

        return $html . parent::getSettingsHTML($params);
    }

    public function requestedAddressFields()
    {
        if(!$this->prompt_address)
            return FALSE;

        return array(
            'country' => array('cost' => TRUE, 'required' => TRUE),
            'region' => array('cost' => TRUE)
        );
    }

    /**
     * Несмотря на название это, видимо, валидатор сохраняемых значений
     * конфигурации. Во всяком случае то, что он возвращает сохраняется
     * в БД.
     * 
     * Непонятно, можно-ли как-то отсюда ошибку выбрасывать, разбирать
     * цепочку вызовов лень, поэтому просто превратим в 0 все ошибочные
     * значения
     * 
     * @param array $settings
     * @return array
     */
    public function saveSettings($settings = array()) {

        foreach ($settings['rate'] as $index=>$item)
        {
            $settings['rate'][$index]['maxweight'] = isset($item['maxweight']) ? str_replace(',', '.', floatval($item['maxweight'])) : "0";
            $settings['rate'][$index]['free'] = isset($item['free']) ? str_replace(',', '.', floatval($item['free'])) : "0";
        }

        return parent::saveSettings($settings);
    }

    /**
     * Проверяет есть-ли у варианта ограничение по максимальному весу
     * и, если есть, разрешен-ли указанный вес для этого варианта
     *
     * @param array $rate массив с настройками варианта
     * @param float $weight вес заказа
     * @return boolean
     */
    private function isAllowedWeight($rate, $weight)
    {
        return (!$rate['maxweight'] || $weight <= $rate['maxweight']);
    }

    /**
     * Расчет стоимости доставки указанного варианта с учетом возможного
     * бесплатного порога. Если бесплатный порог не указан, пуст или равен 0
     * то возвращаем стоимость доставки. Иначе 0
     *
     * @param array $rate Настройки варианта
     * @param float $orderCost стоиомсть заказа
     * @return int|float стоимость доставки
     */
    private function calcCost($rate, $orderCost)
    {
        return !$rate['free'] || $orderCost < $rate['free'] ? 0 : $rate['cost'];
    }
}
