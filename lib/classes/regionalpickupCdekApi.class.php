<?php
/**
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
 * @version 1.0
 * @package regionalpickup/API
 */

/**
 * Реализация функций API СДЭК для отслеживания отправления
 * Используется cURL
 */
class regionalpickupCdekApi {
    
    const TRACKING_URL = "http://gw.edostavka.ru:11443/status_report_h.php";
    const TRACK_REQUEST_TPL = '<?xml version="1.0" encoding="UTF-8"?><StatusReport Date="%s" Account="%s" Secure="%s" ShowHistory="1"><Order DispatchNumber="%s" /></StatusReport>';
    
    /**
     * Запрашивает у сервера СДЭК информацию по номеру посылки.
     * Возвращает HTML с результатами трекинга или выбрасывает
     * исключение в случае ошибки или отсутствия информации
     * по посылке
     * 
     * @param string $number Номер отправления для отслеживания
     * @param string $account Номер аккаунта, выданный СДЭК
     * @param string $secure Секретный код, выданный СДЭК
     * @return string HTML таблицу с результатом трасировки
     * @throws waException
     */
    public function track($number, $account, $secure) {
        
        if(empty($account) || empty($secure))
            throw new waException(_wp("Tracking module is not configured"));

        if(empty($number))
            throw new waException(_wp("Tracking number is not set"));

        if(!extension_loaded("curl"))
            throw new waException(_wp("Tracking module requires a cURL extension"));
        
        $xml_request = sprintf(self::TRACK_REQUEST_TPL, date("Y-m-d"), $account, md5(date("Y-m-d") .'&'.$secure), $number);
        
        $ch = curl_init(self::TRACKING_URL);
        
        if(!$ch)
            throw new waException(_wp("Error initializing cURL"));
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array('xml_request' => $xml_request));
        
        $answer = curl_exec($ch);
        
        /** @todo Проверить код ответа сервера. Если не 200, тоже ошибка */
        
        curl_close($ch);
        
        if($answer === FALSE)
            throw new waException(_wp("curl_exec() failure"));
    
        $report = new SimpleXMLElement($answer);
        
        /** @todo Еще можно обработку ошибки, возвращаемой СДЭКом сделать */
        if ($report->getName() !== "StatusReport")
            throw new waException(_wp("No StatusReport int the CDEK server answer"));
        
        if ($report->count() < 1)
            throw new waException(_wp("No Order inside StatusReport"));


        return $this->processStatusReport($report);
    }
    
    private function processStatusReport($xml) {
        
        $result_html = "";
        
        $order = $xml->Order[0];
        
        if (is_null($order->Status))
            throw new waException(_wp("No Status for Order"));
        
        if(!is_null($order->Status["Description"]) && !is_null($order->Status["CityName"]))
            $result_html .= sprintf('<p>' . _wp("Current status: %s, city: %s") . '</p>', "<b>{$order->Status['Description']}</b>", "<b>{$order->Status['CityName']}");
            
        if(!is_null($order->Status->State) && $order->Status->State->count()) {
            
            $result_html .= '<table style="border:1px solid black;border-collapse:collapse;width:100%">';
            $result_html .= '<tr><th style="padding:3px 5px;border: 1px solid #999">' . _wp("Date") . '</th><th style="padding:3px 5px;border: 1px solid #999">' . _wp("City") . '</th><th style="padding:3px 5px;border: 1px solid #999">' . _wp("Description") . '</th></tr>';
            
            foreach ($order->Status->State as $state)
                sprintf ('<tr><td style="padding:3px 5px;border: 1px solid #999">%s</td><td style="padding:3px 5px;border: 1px solid #999">%s</td><td style="padding:3px 5px;border: 1px solid #999">%s</td></tr>', date("D, j-M-Y", strtotime($state['Date'])), $state['CityName'], $state['Description']);
            
            $result_html .= '</table>';
        }
        
        return $result_html;
    }
}
