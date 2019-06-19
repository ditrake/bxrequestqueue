<?php

namespace razout\bxrequestqueue;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use Bitrix\Highloadblock as HL;


class RequestQueue
{
    /**
     * @var int|mixed ID Highload блока
     */
    private static $HB_ID = 0;
    /**
     * @var string Имя Highload блока
     */
    private static $HB_NAME = "RequestQueue";
    /**
     * @var int Максимальное количество одновременных запросов
     */
    private static $MAX_REQUESTS = 10;
    /**
     * @var string Храним тут активные завпросы
     */
    private static $NOW_REQUESTS_PATH = "/queue";

    public function __construct()
    {
        Loader::includeModule('highloadblock');

        self::$HB_ID = $this->getHLTableIdByName(self::$HB_NAME);

        $hlblock = HL\HighloadBlockTable::getById(self::$HB_ID)->fetch();
        $this->entity = HL\HighloadBlockTable::compileEntity($hlblock); //генерация класса
        $this->entityClass = $this->entity->getDataClass();
    }

    /**Добавляем запрос в очередь
     * @param $request
     * @param $request_name
     * @param string $callback
     * @param string $call_back_params
     * @return array
     * @throws \Exception
     */
    public function add($request, $request_name, $callback = "", $call_back_params = "")
    {
        $fields = array(
            "UF_REQUEST" => $request,
            "UF_STATUS" => false,
            "UF_REQUEST_NAME" => $request_name,
            "UF_CALLBACK" => $callback,
            "UF_CALLBACK_PARAMS" => $call_back_params
        );
        $class = $this->entityClass;
        $res = $class::add($fields);
        if ($new_id = $res->getId()) {
            return array('result' => true, 'request_id' => $new_id);
        } else {
            return array('result' => false);
        }
    }

    /**Проверяем, сколько в очереди
     * @return bool
     */
    public function checkQueue()
    {
        $now = json_decode(file_get_contents(__DIR__.self::$NOW_REQUESTS_PATH));
        $queue = count($now);
        return $queue < self::$MAX_REQUESTS;
    }

    /**Вытаскиваем первый попавший в очередь не обработанный элемент
     * @return array|bool|false
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function shiftQueue()
    {
        $now = json_decode(file_get_contents(__DIR__.self::$NOW_REQUESTS_PATH));
        $class = $this->entityClass;
        $result = $class::getList(
            array(
                "filter" => array(
                    "UF_STATUS" => false,
                    "!ID" => $now
                ),
                "order" => array(
                    "ID" => "ASC"
                ),
                "limit" => self::$MAX_REQUESTS
            )
        );
        if ($ob = $result->fetch()) {
            return $ob;
        } else {
            return false;
        }
    }

    /**Проверяем статус запроса по ID
     * @param $ID
     * @return array|bool
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public function checkQueueItem($ID)
    {
        $class = $this->entityClass;
        $res = $class::getById($ID);
        if ($ob = $res->fetch()) {
            return ["status" => $ob['UF_STATUS'], "result" => json_decode($ob['UF_RESULT'], true)];
        }
        return false;
    }

    /**Записываем результат запрома в очередь
     * @param $ID
     * @param $result
     * @throws \Exception
     */
    public function queueItemUpdate($ID, $result)
    {
        $class = $this->entityClass;
        $class::update($ID, array("UF_RESULT" => $result, "UF_STATUS" => true));
    }

    /**Изменяем количество запросов в обработке
     * @param $how bool
     * @param $orderId int
     */
    public function changeQueue($how, $orderId)
    {
        $now = json_decode(file_get_contents(__DIR__.self::$NOW_REQUESTS_PATH));
        if ($how) {
            if (!in_array($orderId, $now)) {
                $now[] = $orderId;
            }
        } else {
            if (in_array($orderId, $now)) {
                unset($now[array_search($orderId, $now)]);
                sort($now);
            }
        }
        file_put_contents(__DIR__.self::$NOW_REQUESTS_PATH, json_encode($now));
    }

    /**Удаляем запрос из очереди
     * @param $ID
     * @throws \Exception
     */
    public function delete($ID)
    {
        $class = $this->entityClass;
        $class::delete($ID);
    }

    /**Получаем id Highload блока по имени
     * @param $name
     * @return mixed
     * @throws Exception
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    private function getHLTableIdByName($name)
    {
        Loader::includeModule('highloadblock');
        $hlblock = HighloadBlockTable::getList([
            'filter' => ['=NAME' => $name]
        ])->fetch();
        if (!$hlblock) {
            throw new Exception('Highload block not found');
        }
        return $hlblock['ID'];
    }
}