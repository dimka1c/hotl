<?php

namespace Classes;

class Connect
{

    /** Proxy ip
     * @var array
     */
    public $proxyes = [];


    public function __construct()
    {
        echo 'Получение списка proxy...' . PHP_EOL;
        $objProxy = new GetProxy();
        $this->proxyes = $objProxy->getProxy();
        if (isset($this->proxyes['from_file']) && $this->proxyes['from_file'] == true) {
            unset($this->proxyes['from_file']);
        } else {
            $this->saveProxyToFile();
        }
        unset($objProxy);
        if ($this->proxyes == false) {
            echo 'Прокси не получены...' . PHP_EOL;
        }
    }


    public function getUrl($url)
    {
        $output = false;
        do {
            if (empty($this->proxyes) || count($this->proxyes) < 5) {
                echo 'Получение списка proxy...' . PHP_EOL;
                $objProxy = new GetProxy();
                $this->proxyes = $objProxy->getProxy($this->proxyes);
                if ($this->proxyes == false) {
                    echo 'Не удалось получить прокси' . PHP_EOL;
                    echo 'Выполнение программы завершено' . PHP_EOL;
                    die;
                }
                if (isset($this->proxyes['from_file']) && $this->proxyes['from_file'] == true) {
                    unset($this->proxyes['from_file']);
                } else {
                    $this->saveProxyToFile();
                }
                unset($objProxy);
            }

            $proxyNumber = array_rand($this->proxyes);
            $proxy = $this->proxyes[$proxyNumber];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            //curl_setopt($ch, CURLOPT_COOKIEJAR, BASE_DIR . '/cookies/cookie.txt'); // сохранять куки в файл
            //curl_setopt($ch, CURLOPT_COOKIEFILE,  BASE_DIR . '/cookies/cookie.txt');

            $type = explode(',', $proxy['type']);

            if ($type[0] == 'SOCKS4') {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
            }
            if ($type[1] == 'SOCKS5') {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
            }
            $proxyAddr = $proxy['ip'] . ':' . $proxy['port'];
            curl_setopt($ch, CURLOPT_PROXY, "$proxyAddr");
            echo 'proxy - ' . $proxyAddr . ' ' . $proxy['type'] . ' - ';

            $headers = [
                ':authority: hotline.ua',
                ':method: GET',
                //':path: /computer/noutbuki-netbuki/',
                ':scheme: https',
                'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3',
                //'accept-encoding: gzip, deflate, br',
                //'accept-language: en-US,en;q=0.9,ru;q=0.8',
                'cache-control: no-cache',
                'pragma: no-cache',
                'referer: https://hotline.ua',
                'sec-fetch-mode: navigate',
                'sec-fetch-site: none',
                'sec-fetch-user: ?1',
                'upgrade-insecure-requests: 1',
                'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.132 Safari/537.36',
            ];
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            $output = curl_exec($ch);

            $this->proxyes[$proxyNumber]['count_used']++;

            $errNo = curl_errno($ch);
            if ($errNo !== 0) {
                echo 'error ' . $errNo . PHP_EOL;
                $output = false;
                unset($this->proxyes[$proxyNumber]);
                $this->saveProxyToFile();
                if ($errNo == 3) {
                    echo $url . PHP_EOL;
                }
            }

            curl_close($ch);
            unset($ch);

            if ($this->hotlineBlockedCaptcha($output)) {
                //echo 'CAPTCHA' . PHP_EOL;
                $output = false;
                unset($this->proxyes[$proxyNumber]);
                $this->saveProxyToFile();
            }

        } while ($output == false);

        echo 'OK' . PHP_EOL;
        return $output;

    }


    protected function hotlineBlockedCaptcha($html)
    {
        $doc = \phpQuery::newDocument($html);
        $recaptcha = $doc->find("div.g-recaptcha")->attr('data-sitekey');
        $blockZeroProduct = $doc->find('div.messagebox > div.item-notice > div.text > p')->text();
        if ($blockZeroProduct == 'Вашему выбору соответствует ') {
            $this->captcha = true;
            $this->error = true;
            $this->errorCode = 0;
            $this->errorMessage = 'Заблокировало. Не выдает товары';
            echo PHP_EOL . ' --> сработала защита hotline (0 товаров) <--' . PHP_EOL;
            return true;
        }
        \phpQuery::unloadDocuments();
        gc_collect_cycles();
        if (!empty($recaptcha)) {
            $this->captcha = true;
            $this->error = true;
            $this->errorCode = 0;
            $this->errorMessage = 'Сработала Captha';
            unset($recaptcha);
            unset($doc);
            echo PHP_EOL . ' --> сработала защита hotline (GoogleCaptcha) <--' . PHP_EOL;
            return true;
        }
        unset($recaptcha);
        unset($doc);
        return false;
    }

    protected function saveProxyToFile()
    {
        $fp = fopen(BASE_DIR . '/user_config/proxy-list.json', 'w');
        $proxy = json_encode($this->proxyes);
        fwrite($fp, $proxy);
        fclose($fp);
        unset($fp);
        unset($proxy);
    }
}