<?php


namespace Classes;


class GetProxy
{

    protected $proxy;

    public $reserveProxy = [
                            'ip' => '91.200.124.197',
                            'port' => '38706',
                            'type' => 'SOCKS4'
                            ];


    public function __construct()
    {
        $fileReserveProxy = BASE_DIR . '/user_config/proxy-reserve.json';
        if (file_exists($fileReserveProxy)) {
            $this->reserveProxy = json_decode(file_get_contents($fileReserveProxy), true);
        }
    }

    public function getProxy($proxy = [])
    {
        if (!empty($proxy)) {
            $this->proxy = $proxy;
        } else {
            $this->proxy = $this->reserveProxy;
        }

        // Если файл с проксями был созданм менее 2х часов назад,
        // считаем, что данные актуальные и отдаем прокси из файла
        if (file_exists(BASE_DIR . '/user_config/proxy-list.json')) {
            $currentTime = microtime(true);
            $fileChangeTime = filemtime(BASE_DIR . '/user_config/proxy-list.json');
            $del = $currentTime - $fileChangeTime;

            if ($del < 7200) {
                $result = json_decode(file_get_contents(BASE_DIR . '/user_config/proxy-list.json'), true);
                if (empty($result)) $result = [];
                if (count($result) > 5) {
                    if (!empty($result)) {
                        $result['from_file'] = true;
                        return $result;
                    }
                }
            }
        }

        if (!empty($this->reserveProxy) && is_array($this->reserveProxy)) {
            foreach ($this->reserveProxy as $proxy) {
                echo 'Получение списка proxy со стороннего сервиса.';
                // https://hidemy.name
                $result = $this->getProxyCurl_HIDEMY($proxy);
                if (!empty($result)) {
                    echo ' - OK.' . PHP_EOL;
                    return $result;
                }
                $result = $this->getProxy_HIDEMY();
                if (!empty($result)) {
                    echo ' - OK.' . PHP_EOL;
                    return $result;
                }
                echo ' - Error.' . PHP_EOL;
            }
        }

        if (!empty($this->reserveProxy)) {
            echo 'Получение прокси из файла proxy-reserce.json - OK' . PHP_EOL;
            return $this->reserveProxy;
        } else {
            echo 'Получение прокси из файла proxy-reserce.json - ERROR' . PHP_EOL;
            echo 'Заполните файл proxy-reserce.json для дальнейшей работы' . PHP_EOL;
            echo 'Без прокси-адресов работа не возможна' . PHP_EOL;
        }

        return false;
    }



    public function getProxyCurl_HIDEMY($proxy = false)
    {
        $url = 'https://hidemy.name/ru/proxy-list/?country=UA&type=45#list';
        $ch = curl_init($url);
        //curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $headers = [
            ':authority: hidemy.name',
            ':method: GET',
            ':path: /ru/proxy-list/?country=UA&type=45',
            ':scheme: https',
            'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3',
            'cache-control: no-cache',
            'pragma: no-cache',
            'sec-fetch-mode: navigate',
            'sec-fetch-site: none',
            'sec-fetch-user: ?1',
            'upgrade-insecure-requests: 1',
            'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.132 Safari/537.36',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($proxy != false) {
            $type = explode(',', $proxy['type']);
            if ($type[0] == 'SOCKS4') {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
                unset($type);
            }
            if ($type[1] == 'SOCKS5') {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
            }
            $proxyAddr = $proxy['ip'] . ':' . $proxy['port'];
            curl_setopt($ch, CURLOPT_PROXY, "$proxyAddr");
        }

        $response = curl_exec($ch);

        curl_close($ch);

        $doc = \phpQuery::newDocument($response);
        $doc->find('script')->remove();
        $allIP = pq($doc)->find('table.proxy__t > tbody > tr');
        $arrIP = [];
        foreach ($allIP as $item) {
            $td = pq($item)->find('td');
            $res = [];
            $i = 0;
            foreach ($td as $_td) {
                if ($i == 0) {
                    $res['ip'] = $_td->nodeValue;
                }
                if ($i == 1) {
                    $res['port']= $_td->nodeValue;
                }
                if ($i == 4) {
                    $res['type'] = $_td->nodeValue;
                }
                $i++;
            }
            if (!empty($res)) {
                $res['count_used'] = 0;
                $arrIP[] = $res;
            }
        }

        return $arrIP;

    }


    public function getProxy_FREEPROXY()
    {
        $str = BASE_DIR . '/proxy/phantomjs.exe ' . BASE_DIR . '/proxy/freeproxy.js';
        $response = shell_exec($str);
        $doc = \phpQuery::newDocument($response);
        $doc->find('script')->remove();
        $allIP = pq($doc)->find('table#proxy_list > tbody > tr');
        $arrIP = [];
        foreach ($allIP as $item) {
            $res['ip'] = pq($item)->find('td.left > abbr')->text();
            $res['port'] = pq($item)->find('td > span.fport')->text();
            $res['type'] = pq($item)->find('td > small')->text();
        }
        return $arrIP;

    }


    public function getProxy_HIDEMY()
    {
        $str = BASE_DIR . '/proxy/phantomjs.exe ' . BASE_DIR . '/proxy/hidemy.js';
        $response = shell_exec($str);
        $doc = \phpQuery::newDocument($response);
        $doc->find('script')->remove();
        $allIP = pq($doc)->find('table.proxy__t > tbody > tr');
        $arrIP = [];
        foreach ($allIP as $item) {
            $td = pq($item)->find('td');
            $res = [];
            $i = 0;
            foreach ($td as $_td) {
                if ($i == 0) {
                    $res['ip'] = $_td->nodeValue;
                }
                if ($i == 1) {
                    $res['port']= $_td->nodeValue;
                }
                if ($i == 4) {
                    $res['type'] = $_td->nodeValue;
                }
                $i++;
            }
            if (!empty($res)) {
                $res['count_used'] = 0;
                $arrIP[] = $res;
            }
        }
        return $arrIP;
    }




}