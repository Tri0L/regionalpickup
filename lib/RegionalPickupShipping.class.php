<?php

/**
 * 
 * @author Serge Rodovnichenko <sergerod@gmail.com>
 * @version 1.0
 */
class RegionalPickupShipping extends waShipping {

    public function allowedCurrency() {
        return 'RUB';
    }

    public function allowedWeightUnit() {
        return 'KG';
    }

    protected function calculate() {
        return 10;
    }

    public function getSettingsHTML($params = array()) {
        $values = $this->getSettings();
        if (!empty($params['value'])) {
            $values = array_merge($values, $params['value']);
        }

        $view = wa()->getView();

        $app_config = wa()->getConfig();
        if (method_exists($app_config, 'getCurrencies')) {
            $view->assign('currencies', $app_config->getCurrencies());
        }

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

}
