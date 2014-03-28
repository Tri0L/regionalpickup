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

		$html = wa()->getView()
			->assign(array(
				'namespace' => $namespace,
				'values' => $values
				'p' => $this
			))
			->fetch($this->path . '/templates/settings.html');

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
     * Проверяет есть-ли у варианта ограничение по максимальному весу
     * и, если есть, разрешен-ли указанный вес для этого варианта
     *
     * @param array $rate массив с настройками варианта
     * @param float $weight вес заказа
     * @return boolean
     */
    private function isAllowedWeight($rate, $weight)
    {
        if(!isset($rate['maxweight']) || empty($rate['maxweight']))
            return TRUE;

        $maxweight = floatval(str_replace(',', '.', $rate['maxweight']));

        if ($maxweight == 0)
            return TRUE;

        if($weight <= $maxweight)
            return TRUE;

        return FALSE;
    }

    /**
     * Расчет стоимости доставки указанного варианта с учетом возможного
     * бесплатного порога. Если бесплатный порог не указан, пуст или равен 0
     * то возвращаем стоимость доставки. Иначе 0
     *
     * @param array $rate Настройки варианта
     * @param float $orderCost стоиомсть заказа
     * @return mixed
     */
    private function calcCost($rate, $orderCost)
    {
        if(!isset($rate['free']) || empty($rate['free']))
            return $rate['cost'];

        $freelimit = floatval(str_replace(',', '.', $rate['free']));

        if($freelimit == 0)
            return $rate['cost'];

        if($orderCost < $freelimit)
            return $rate['cost'];

        return 0;
    }
}
