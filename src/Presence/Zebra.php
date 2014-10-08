<?php

namespace Presence;

use DateTime;
use Exception;

class Zebra
{
    private $zebraUrl = '';
    private $username = '';
    private $password = '';
    private $loggedIn = false;
    private $cookiePath = '/tmp/zebracookie';

    public function __construct(array $settings)
    {
        $this->zebraUrl = $settings['url'];
        $this->username = $settings['username'];
        $this->password = $settings['password'];
    }

    private function login()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookiePath);
        curl_setopt($ch, CURLOPT_URL, "{$this->zebraUrl}/login/user/{$this->username}.json");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "username={$this->username}&password={$this->password}");
        ob_start();
        curl_exec($ch);
        ob_end_clean();
        curl_close($ch);
    }

    private function request($method)
    {
        if (!$this->loggedIn) {
            $this->login();
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_COOKIEFILE, "/tmp/zebracookie");
        curl_setopt($ch, CURLOPT_URL, "{$this->zebraUrl}/{$method}");
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    public function getHolidaysFromZebra()
    {
        $result = $this->request('holiday.json');
        $days = json_decode($result, true);
        $holidays = array();
        if (NULL !== $days) {
            foreach ($days as $day) {
                $start = new DateTime($day['holiday_start']);
                $end = new DateTime($day['holiday_end']);
                $date = $start->format('y-m-d');
                $holidays[$date]['location'][$day['location_shortname']] = true;
                $holidays[$date]['name'] = $day['holiday_name'];
                $holidays[$date]['start'] = $start;
                $holidays[$date]['end'] = $end;
                if ('8' === $day['holiday_freetime']) {
                    $holidays[$date]['type']['morning'] = true;
                    $holidays[$date]['type']['afternoon'] = true;
                } else {
                    $noon = new DateTime($date . ' 12:00');
                    if ($start >= $noon) {
                        $holidays[$date]['type']['morning'] = false;
                        $holidays[$date]['type']['afternoon'] = true;
                    } elseif ($end < $noon) {
                        $holidays[$date]['type']['morning'] = true;
                        $holidays[$date]['type']['afternoon'] = false;
                    }
                }
            }
        }
        return $holidays;
    }

    public function getHolidays()
    {
        $id = 'holidays';
        $holidays = apc_fetch($id);
        if (false === $holidays) {
            $holidays = $this->getHolidaysFromZebra();
            // cache the holidays for 24h
            if (false === apc_store($id, $holidays, 86400)) {
                throw new Exception('Fatal error: Unable to store to APC!');
            };
        }
        return $holidays;
    }

    public function getUsersFromZebra()
    {

        $result = $this->request('user.json');
        $users = json_decode($result, true)['command']['users']['user'];
        if (NULL === $users) {
            return array();
        }
        return $users;
    }

    public function syncLocationWithDatabase($sqlite)
    {
        $id = 'users';
        $result = apc_fetch($id);
        if (false === $result) {
            $users = $this->getUsersFromZebra();
            // set flag in cache for 24h
            if (false === apc_store($id, true, 24*60*60)) {
                throw new Exception('Fatal error: Unable to store to APC!');
            };

            foreach ($users as $user) {
                if (isset($user['location_shortname']) && isset($user['email'])) {
                    $location = $user['location_shortname'];
                    $email = $user['email'];
                    $sqlite->updateLocation($email, $location);
                }
            }
        }
    }
}

// $users = $zebra->getUsers();
