<?php
class WhacenterAPI {
    private $base_url = 'https://app.whacenter.id/api';
    
    private $device_id = 'dfd86b0797efbb1d79be53e26caf6fdb';

    public function sendMessage($number, $message, $file = null) {
        $url = $this->base_url . '/send';
        
        $params = [
            'device_id' => $this->device_id,
            'number'    => $this->normalizeNumber($number),
            'message'   => $message,
        ];

        if ($file !== null) {
            $params['file'] = $file;
        }

        return $this->postRequest($url, $params);
    }

    public function sendGroupMessage($group_name, $message) {
        $url = $this->base_url . '/sendGroup';
        
        $params = [
            'device_id' => $this->device_id,
            'group'     => $group_name,
            'message'   => $message,
        ];

        return $this->postRequest($url, $params);
    }

    public function getDeviceStatus() {
        $url = $this->base_url . '/status';
        $params = ['device_id' => $this->device_id];
        
        return $this->postRequest($url, $params);
    }

    private function postRequest($url, $params) {
        $ch = curl_init($url);

        if (is_array($params)) {
            $payload = $params;
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($http_code >= 200 && $http_code < 300) {
            return [
                'success' => true,
                'data'    => $result,
                'code'    => $http_code,
            ];
        }

        return [
            'success' => false,
            'error'   => $result['message'] ?? 'Unknown error',
            'code'    => $http_code,
        ];
    }

    private function normalizeNumber($number) {
        $number = preg_replace('/[^0-9]/', '', $number);

        if (substr($number, 0, 1) === '0') {
            $number = '62' . substr($number, 1);
        }

        if (substr($number, 0, 1) !== '6') {
            $number = '62' . $number;
        }

        return $number;
    }
}
