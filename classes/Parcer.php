<?php

namespace Classes;



class Parcer
{

    protected $urlHotline = 'https://hotline.ua';

    public $parseFiles = [];

    protected $connect = false;

    protected $arrInput = [];

    public $nameAttr = [];

    public $productForParse = [];

    public $notFoundProduct = [];

    protected $countProductForParsing = 0;

    public $attrConfig = [];


    public function __construct()
    {
        require 'phpQuery.php';
        $this->parseFiles = scandir(BASE_DIR . '/data');
        unset($this->parseFiles[0]);
        unset($this->parseFiles[1]);
        if (empty($this->parseFiles)) $this->parseFiles = false;
        if (file_exists(BASE_DIR . '/work/problem.txt')) unlink(BASE_DIR . '/work/problem.txt');
        $this->attrConfig = require BASE_DIR . '/config/config_attr.php';
    }


    public function start()
    {
        if ($this->parseFiles == false) {
            echo 'Нет файлов для обработки в папке data' . PHP_EOL;
            die;
        }

        // Получаем данные из файла
        foreach ($this->parseFiles as $file) {
            echo 'Получение данных из файла ' . $file . PHP_EOL;
            $this->readDataFromXLS($file);
            if (empty($this->productForParse)) {
                echo 'В файле все характеристики у товаров заполнены. Необходимости парсить нет.' . PHP_EOL;
                continue;
            }
            // парсим товары, у которых не все характеристики заполнены
            echo 'Данные получены.' . PHP_EOL;
            $this->countProductForParsing = count($this->productForParse);
            echo 'Найдено товаров для парсинга - ' . $this->countProductForParsing . PHP_EOL;
            echo '--------------------------------------------------------' . PHP_EOL;
            echo 'Начало работы парсера' . PHP_EOL;
            echo '--------------------------------------------------------' . PHP_EOL;

            if ($this->connect == false) {
                $this->connect = new Connect();
                if ($this->connect->proxyes == false) {
                    die;
                }
            }
            $cointProductIsParsing = 1;
            foreach ($this->productForParse as $key => $item) {
                //$item['product'] = 'Графический монитор Huion Kamvas GT-191 + перчатка';
                //$item['product'] = 'ASRock Phantom Gaming Radeon RX560 4G';
                echo 'Обработка ' . $cointProductIsParsing . ' из ' . $this->countProductForParsing . PHP_EOL;
                $cointProductIsParsing++;
                echo 'Поиск товара ' . $item['product'] . PHP_EOL;
                $product = $this->deleteRussianText($item['product']);
                $prepareUrl = $this->prepareUrl($product);
                $url = $this->urlHotline . '/sr/autocomplete/?term=' . $prepareUrl;
                // должен вернуться json
                $response = json_decode($this->connect->getUrl($url), true);
                if (empty($response)) {
                    echo 'Товар не найден' . PHP_EOL;

                    $fp = fopen(BASE_DIR . '/work/problem.txt', 'a+');
                    fwrite($fp, $item['product'] . PHP_EOL);
                    fclose($fp);
                    unset($fp);

                    echo '--------------------------------------------------------' . PHP_EOL;
                    $arrErr['name'] = $item['product'];
                    $arrErr['variants'] = [];
                    $this->notFoundProduct[] = $arrErr;
                    $this->productForParse[$key]['not_find'] = true;
                    continue;
                }
                $url = false;
                if (count($response) == 1) {
                    echo 'Товар найден' . PHP_EOL;
                    $url = $this->urlHotline . $response[0]['url'];
                } else {
                    foreach ($response as $k_resp => $val_resp) {
                        if (trim(mb_strtolower($val_resp['label'])) == trim(mb_strtolower($product))) {
                            // полное совпадение по имени товара
                            echo 'Товар найден' . PHP_EOL;
                            $url = $this->urlHotline . $val_resp['url'];
                            break;
                        }
                        if (stripos($product, $val_resp['label']) !== false) {
                            echo 'Товар найден' . PHP_EOL;
                            $url = $this->urlHotline . $val_resp['url'];
                            break;
                        }
                        if (stripos($val_resp['label'], $product) !== false) {
                            echo 'Товар найден' . PHP_EOL;
                            $url = $this->urlHotline . $val_resp['url'];
                            break;
                        }
                    }
                }

                if (!$url) {

                    $fp = fopen(BASE_DIR . '/work/problem.txt', 'a+');
                    fwrite($fp, $item['product'] . PHP_EOL);
                    fclose($fp);
                    unset($fp);

                    echo '***********************************************' . PHP_EOL;
                    echo 'Товар не найден, но есть похожие товары:' . PHP_EOL;
                    $this->productForParse[$key]['not_find'] = true;
                    $arrErr['name'] = $item['product'];
                    if (!empty($response)) {
                        foreach ($response as $k => $v) {
                            $varn['name'] = $v['label'];
                            echo $k . ' - ' . $varn['name'] . PHP_EOL;
                            $varn['url'] = $v['label'];
                            $arrErr['variants'][] = $varn;
                        }
                    }
                    $this->notFoundProduct[] = $arrErr;
                    echo '***********************************************' . PHP_EOL;
                }

                if ($url != false) {
                    $html = $this->connect->getUrl($url);
                    echo 'Получены характеристики товара' . PHP_EOL;

                    $attributes = $this->parseProduct($html, $item['article']);

                    foreach ($item['attr'] as $kAttr => $valAttr) {
                        if (!is_array($valAttr['name_attr'])) {
                            $nameAttr = trim($valAttr['name_attr'], ':');
                            $charExists = array_key_exists($nameAttr, $attributes['attr']);
                            if ($charExists == true) {
                                $this->productForParse[$key]['attr'][$kAttr]['value'] = $attributes['attr'][$nameAttr];
                            }
                        } else {
                            foreach ($valAttr['name_attr'] as $k => $v) {
                                $v = trim($v);
                                $charExists = array_key_exists($v, $attributes['attr']);
                                if ($charExists == true) {
                                    $this->productForParse[$key]['attr'][$kAttr]['value'] = $attributes['attr'][$v];
                                }
                            }
                        }
                    }

                    // ** test **
                    $fp = fopen(BASE_DIR . '/work/parse-ok.json', 'a+');
                    $prodJson = json_encode($this->productForParse[$key]);
                    fwrite($fp, $prodJson . PHP_EOL);
                    fclose($fp);
                    unset($fp);
                    unset($prodJson);
                    //******
                }
                echo '-----------------------------------' . PHP_EOL . PHP_EOL;
            }

            echo '-----------------Закончили парсить ' . $file . ' ---------------' . PHP_EOL;
            echo 'Заполняем файл ' . $file . ' характеристиками ' . PHP_EOL;

            $objPHPExcel = \PHPExcel_IOFactory::load(BASE_DIR . '/data/' . $file);
            $objPHPExcel->setActiveSheetIndex(0);
            $aSheet = $objPHPExcel->getActiveSheet();

            foreach ($this->productForParse as $keyProd => $itemProd) {
                if ($itemProd['not_find'] == true) continue;
                foreach ($itemProd['attr'] as $keyAttr => $itemAttr) {
                    if ($itemAttr['value'] == false) {
                        $ColumnLetter = \PHPExcel_Cell::stringFromColumnIndex($itemAttr['stolbec']);
                        $coord = $ColumnLetter . $itemProd['stroka'];
                        $aSheet->getStyle($coord)->getFill()->applyFromArray(array(
                            'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                            'startcolor' => array(
                                'rgb' => 'F28A8C'
                            )
                        ));
                        continue;
                    }
                    $ColumnLetter = \PHPExcel_Cell::stringFromColumnIndex($itemAttr['stolbec']);
                    $coord = $ColumnLetter . $itemProd['stroka'];
                    $aSheet->setCellValue($coord ,$itemAttr['value']);
                    $aSheet->getStyle($coord)->getFill()->applyFromArray(array(
                        'type' => \PHPExcel_Style_Fill::FILL_SOLID,
                        'startcolor' => array(
                            'rgb' => '07FF7C'
                        )
                    ));
                }
            }

            $objWriter = new \PHPExcel_Writer_Excel2007($objPHPExcel);
            $objWriter->save(BASE_DIR . '/data/' . $file);
            unset($objWriter);
            unset($aSheet);
            unset($objPHPExcel);
            gc_collect_cycles();

            echo 'Файл ' . $file . ' успешно сформирован' . PHP_EOL;
            echo '############################################################' . PHP_EOL . PHP_EOL;

        }


        echo 'Работа парсера завершена';
    }



    protected function prepareUrl($text)
    {
        $text = preg_replace('/\s+/', '+', $text);
        return $text;
    }

    /**
     * @param $text
     * @return string
     */
    protected function deleteRussianText($text)
    {
        if (!empty($text)) {
            //$text = preg_replace('/[\x{0410}-\x{042F}]+.*[\x{0410}-\x{042F}]+/iu', '', $text);
            $text = preg_replace('/[а-яА-Я]+/iu', '', $text);
        }
        $text = mb_strtolower($text);
        $text = str_replace(' (ups) - ', '', $text);
        $text = str_replace('gray', '', $text);
        $text = str_replace('yellow', '', $text);
        $text = str_replace('pink', '', $text);
        $text = str_replace('blue', '', $text);
        $text = str_replace('black', '', $text);
        $text = str_replace('squad', '', $text);
        $text = str_replace('sand', '', $text);
        $text = str_replace('white', '', $text);
        $text = str_replace('midnight black', '', $text);
        $text = str_replace('teal', '', $text);
        $text = str_replace('red', '', $text);
        $text = str_replace('gold', '', $text);
        $text = str_replace('red/black', '', $text);
        $text = str_replace('orange', '', $text);
        $text = str_replace('silver', '', $text);
        $text = str_replace('ocean blue', '', $text);

        $text = trim($text);
        $text = ltrim($text, '-');
        $text = ltrim($text, '+');
        $text = ltrim($text, '-');
        $text = ltrim($text, '/');
        $text = ltrim($text, '|');
        $text = ltrim($text, "(");
        $text = ltrim($text, ')');
        $text = ltrim($text, '&');
        $text = trim($text);

        return $text;
    }


    protected function readDataFromXLS($inputFileName)
    {
        $this->productForParse = [];
        $this->nameAttr = [];

        $path = BASE_DIR . '/data/' . $inputFileName;
        if (!file_exists($path)) {
            echo 'Файл ' . $inputFileName . ' в папке data отсутствует.' . PHP_EOL;
            echo 'работа программы завершена' . PHP_EOL;
            die;
        }

        $file_type = \PHPExcel_IOFactory::identify($path);
        // создаем объект для чтения
        $objReader = \PHPExcel_IOFactory::createReader($file_type);
        $objPHPExcel = $objReader->load($path); // загружаем данные файла в объект
        $result = $objPHPExcel->getActiveSheet()->toArray(); // выгружаем данные из объекта в масси
        unset($objPHPExcel);
        unset($objReader);
        unset($file_type);
        gc_collect_cycles();

        foreach ($result[0] as $item) {
            if (isset($this->attrConfig[$item])) {
                $this->nameAttr[] = $this->attrConfig[$item];
            } else {
                $this->nameAttr[] = $item;
            }

        }
        //$this->nameAttr = $result[0];
        foreach ($this->nameAttr as $key => $item) {
            if (!is_array($item)) {
                if (trim(mb_strtolower($item)) == 'название') {
                    $rowNameProduct = $key;
                    continue;
                }
                if (trim(mb_strtolower($item)) == 'артикул') {
                    $rowArticle = $key;
                    continue;
                }
            }
        }
        unset($result[0]);

        foreach ($result as $key => $item) {
            $arr = [];
            if (empty($item[$rowNameProduct])) continue;
            $arr['product'] = $item[$rowNameProduct]; // Название продукта
            $arr['stroka'] = $key + 1;  // Строка, где находится продукт
            $arr['article'] = $item[$rowArticle];
            $arr['not_find'] = false;
            unset($item[$rowArticle]);
            unset($item[$rowNameProduct]);
            foreach ($item as $k_attr => $attr) {
                if (empty($attr)) {
                    $arr['attr'][] = [
                        'stolbec' => $k_attr,
                        'name_attr' => $this->nameAttr[$k_attr],
                        'value' => false
                    ];
                }
            }
            if (isset($arr['attr']) && !empty($arr['attr'])) {
                $this->productForParse[] = $arr;
            }
        }
    }




    /**
     * парсим характеристики товара
     */
    public function parseProduct($html, $namePhoto)
    {
        $resultParsing = [];
        $docProd = \phpQuery::newDocument($html);
        $attrs = pq($docProd)->find('div.specification-table > div[data-pills="parent"] > div[data-pills="content"]:last-child > table > tr');
        $allAttrs = [];
        foreach ($attrs as $item)
        {
            $attr = [];
            pq($item)->find('td.cell-4 > div.tooltip ')->remove();
            $name = trim(pq($item)->find('td.cell-4')->text());
            $name = trim($name,':');
            $name = trim($name);
            $value = trim(pq($item)->find('td.cell-8 > div.p_l-10')->text());
            if (!empty($name)) {
                $allAttrs[$name] = $value;
            }
        }

        $resultParsing['attr'] = $allAttrs;

        $allImages = pq($docProd)->find('div.zg-nav-item.zg-nav-item-image');
        $images = [];
        foreach($allImages as $nameImage) {
            $images[] = pq($nameImage)->attr('data-src-canvas');
        }
        if (!empty($images)) {
            $saveImages = $this->getImageFromUrl($images, $namePhoto);
            $resultParsing['images'] = $saveImages;
        } else {
            echo 'У товара нет изображеий' . PHP_EOL;
            $resultParsing['images'] = false;
        }

        $docProd->unloadDocument();

        return $resultParsing;
    }


    /**
     * Парсим картинку
     *
     * @param $imageurl
     * @param $imagename
     * @return bool|string
     */
    public function getImageFromUrl($imageurl, $fileName)
    {
        $savedImage = [];
        foreach ($imageurl as $key => $item) {
            $numberImage = $key + 1;
            $imagename = basename($item);
            $info = new \SplFileInfo($imagename);
            $extensionFile = $info->getExtension();
            $sourcecode = $this->connect->getUrl($item);

            if (!file_exists(BASE_DIR . '/images/')) mkdir(BASE_DIR . '/images/', 777);
            if (!empty($sourcecode)) {
                try {
                    $fileNameSave = $fileName . '@' . $numberImage . '.' . $extensionFile;
                    $url = BASE_DIR . '/images/' . $fileNameSave;
                    if (file_exists($url)) {
                        echo 'Фото с именем ' . $fileNameSave . ' уже сущесвует' . PHP_EOL;
                    } else {
                        $savefile = fopen($url, 'w');
                        fwrite($savefile, $sourcecode);
                        fclose($savefile);
                        echo '-- получено фото ' . $fileNameSave . PHP_EOL;
                        unset($sourcecode);
                        unset($savefile);
                    }
                } catch (\Exception $e) {
                    $savedImage['error'][] = $imagename;
                }
            } else {
                $savedImage['error'][] = $imagename;
            }
            $savedImage[] = $fileNameSave;
        }

        return $savedImage;
    }


    public function parceAllProductInCategory()
    {
        if ($this->parceUrl && $this->countPage) {
            for ($i = 0; $i <= $this->countPage; $i++) {
                $products = [];
                $isOk = false;
                if ($i > 0) {
                    $url = $this->parceUrl . '?p=' . $i;
                } else {
                    $url = $this->parceUrl;
                }
                $html = $this->connect->getUrl($url);
                $dom = \phpQuery::newDocument($html);
                $items = pq($dom)->find('div.item-info > p.h4 > a');
                //$items = pq($dom)->find('li.product-item.promo-product');
                foreach ($items as $item) {
                    $isOk = true;
                    $prod = [];
                    $prod['name'] = trim(pq($item)->text());
                    $prod['href'] = 'https://hotline.ua' . trim(pq($item)->attr('href'));
                    //$prod['name'] = trim(pq($item)->find('div.item-info > p.h4 > a')->text());
                    //$prod['href'] = 'https://hotline.ua' . trim(pq($item)->find('div.item-info > p.h4 > a')->attr('href'));
/*
                    $price = pq($item)->find('div.item-price.stick-bottom > div.stick-pull > div.text-sm')->text();
                    $price = str_replace('грн', '', $price);
                    $price = explode('-', $price);
*/
                    $resutInsert = $this->DB->insertProduct($this->parseCategory, $prod);
                    if (!is_array($resutInsert) && is_int($resutInsert)) {
                        echo 'Найден новый товар ' . $prod['name'] . PHP_EOL;
                    }
                    $prod['sql_insert'] = $resutInsert;
                    $products[] = $prod;
                }
                if ($isOk) {
                    echo 'Страница ' . $i . ' - OK' . PHP_EOL;
                } else {
                    echo 'Страница ' . $i . ' - ERROR' . PHP_EOL;
                }
                \phpQuery::unloadDocuments();

                unset($items);

            }
        }
    }


}