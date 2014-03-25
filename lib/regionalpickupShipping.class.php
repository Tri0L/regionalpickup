<?php

/**
 * Модуль расчета доставки в Пункты выдачи заказов с разбивкой по регионам
 *
 * @author Serge Rodovnichenko <sergerod@gmail.com>
 * @version 1.0
 */
class regionalpickupShipping extends waShipping {

    public function allowedCurrency() {
        return $this->currency;
    }

    public function allowedAddress() {
        $rate_zone = $this->rate_zone;
        $address = array();
        foreach ($rate_zone as $field => $value) {
            if (!empty($value)) {
                $address[$field] = $value;
            }
        }
        return array($address);
    }

    public function allowedWeightUnit() {
        return 'kg';
    }

    protected function calculate() {
        $rates = $this->rate;
        $currency = $this->currency;

        $deliveries = array();
        $i = 1;    // start from index 1
        foreach ($rates as $rate) {
            $deliveries[$i++] = array(
                'name' => $rate['location'],
                'currency' => $currency,
                'rate' => $rate['cost'],
                'est_delivery' => ''
            );
        }

        return $deliveries;
    }

    public function getSettingsHTML($params = array()) {
        $values = $this->getSettings();
        if (!empty($params['value'])) {
            $values = array_merge($values, $params['value']);
        }

        $view = wa()->getView();

        if (!$values['rate_zone']['country']) {
            $l = substr(wa()->getUser()->getLocale(), 0, 2);
            if ($l == 'ru') {
                $values['rate_zone']['country'] = 'rus';
                $values['rate_zone']['region'] = '77';
                $values['city'] = '';
            } else {
                $values['rate_zone']['country'] = 'usa';
            }
        }

        $cm = new waCountryModel();
        $view->assign('countires', $cm->all());

        if (!empty($values['rate_zone']['country'])) {
            $rm = new waRegionModel();
            $view->assign('regions', $rm->getByCountry($values['rate_zone']['country']));
        }

        $view->assign('xhr_url', wa()->getAppUrl('webasyst') . '?module=backend&action=regions');

        $namespace = '';
        if (!empty($params['namespace'])) {
            if (is_array($params['namespace'])) {
                $namespace = array_shift($params['namespace']);
                while (($namspace_chunk = array_shift($params['namespace'])) !== null) {
                    $namespace .= "[{$namspace_chunk}]";
                }
            } else {
                $namespace = $params['namespace'];
            }
        }

        $view->assign('namespace', $namespace);
        $view->assign('values', $values);
        $view->assign('p', $this);

        $html = '';
        $html .= $view->fetch($this->path . '/templates/settings.html');
        $html .= parent::getSettingsHTML($params);

        return $html;
    }

    public function requestedAddressFields()
    {
        return $this->prompt_address ? array() : false;
    }

}
