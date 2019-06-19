<?php

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;

Loc::loadMessages(__FILE__);

class razout_bxrequestqueue extends CModule
{
    public function __construct()
    {
        $arModuleVersion = [];

        include __DIR__ . '/version.php';

        if (is_array($arModuleVersion) && array_key_exists('VERSION', $arModuleVersion)) {
            $this->MODULE_VERSION = $arModuleVersion['VERSION'];
            $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        }

        $this->MODULE_ID = 'razout.bxrequestqueue';
        $this->MODULE_NAME = Loc::getMessage('BX_CONTENT_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('BX_CONTENT_MODULE_DESCRIPTION');
        $this->MODULE_GROUP_RIGHTS = 'N';
        $this->PARTNER_NAME = Loc::getMessage('BX_CONTENT_MODULE_PARTNER_NAME');
    }

    public function doInstall()
    {
        ModuleManager::registerModule($this->MODULE_ID);
        $this->installFiles();
        $this->installDB();
    }

    public function doUninstall()
    {
        $this->unInstallFiles();
        $this->uninstallDB();
        ModuleManager::unregisterModule($this->MODULE_ID);
    }

    /**
     * Вносит в базу данных изменения, требуемые модулем
     *
     * @return bool
     */
    public function installDB()
    {
        //todo create highload table and agent
        $this->createHLTable();
    }

    /**
     * Удаляет из базы данных изменения, требуемые модулем
     *
     * @return bool
     */
    public function uninstallDB()
    {
        //todo remove highload table and agent
        $this->deleteHLTable();
    }

    /**
     * Копирует файлы модуля в битрикс
     *
     * @return bool
     */
    public function installFiles()
    {
        return true;
    }

    /**
     * Удаляет файлы модуля из битрикса.
     *
     * @return bool
     */
    public function unInstallFiles()
    {
        return true;
    }


    /**
     * Возвращает путь к папке с модулем
     *
     * @return string
     */
    public function getInstallatorPath()
    {
        return str_replace('\\', '/', __DIR__);
    }


    /**
     * Создает Highload-блок
     */
    protected function createHLTable()
    {
        Loader::includeModule('highloadblock');

        $highloadBlockData = array(
            "NAME" => "RequestQueue",
            "TABLE_NAME" => "request_queue"
        );
        $result = HighloadBlockTable::add($highloadBlockData);
        $highLoadBlockId = $result->getId();

        $this->createHLFields($highLoadBlockId);
    }

    protected function deleteHLTable()
    {
        Loader::includeModule('highloadblock');
        HighloadBlockTable::delete($this->getHLTableIdByName("RequestQueue"));

    }

    /**Добвляем поля в Highload-блок
     * @param int $highLoadBlockId
     * @return bool
     * @throws Exception
     */
    protected function createHLFields($highLoadBlockId = 0)
    {
        if (!$highLoadBlockId) {
            return false;
        }

        $userTypeEntity = new CUserTypeEntity();

        $typeArrs = array("REQUEST", "STATUS", "REQUEST_NAME", "CALLBACK", "CALLBACK_PARAMS", "RESULT");

        foreach ($typeArrs as $typeArr) {
            $userTypeData = array(
                "ENTITY_ID" => "HLBLOCK_" . $highLoadBlockId,
                "FIELD_NAME" => "UF_" . $typeArr,
                "USER_TYPE_ID" => "string",
                "XML_ID" => "XML_ID_" . $typeArr,
                "SORT" => 100,
                "MULTIPLE" => "N",
                "MANDATORY" => "N",
                "SHOW_FILTER" => "N",
                "SHOW_IN_LIST" => "",
                "EDIT_IN_LIST" => "",
                "IS_SEARCHABLE" => "N",
                "SETTINGS" => array(
                    "DEFAULT_VALUE" => "",
                    "SIZE" => "20",
                    "ROWS" => "1",
                    "MIN_LENGTH" => "0",
                    "MAX_LENGTH" => "0",
                    "REGEXP" => "",
                ),
                "EDIT_FORM_LABEL" => array(
                    "ru" => "",
                    "en" => "",
                ),
                "LIST_COLUMN_LABEL" => array(
                    "ru" => "",
                    "en" => "",
                ),
                "LIST_FILTER_LABEL" => array(
                    "ru" => "",
                    "en" => "",
                ),
                "ERROR_MESSAGE" => array(
                    "ru" => "",
                    "en" => "",
                ),
                "HELP_MESSAGE" => array(
                    "ru" => "",
                    "en" => "",
                ),
            );
            if (!$userTypeId = $userTypeEntity->Add($userTypeData)) {
                throw new \Exception("Can't add new field in Hihgloadblock: $highLoadBlockId");
            }
        }
    }

    /**Получаем id Highload блока по имени
     * @param $name
     * @return mixed
     * @throws Exception
     */
    protected function getHLTableIdByName($name)
    {
        Loader::includeModule('highloadblock');
        $hlblock = HighloadBlockTable::getList([
            'filter' => ['=NAME' => $name]
        ])->fetch();
        if (!$hlblock) {
            throw new \Exception('Highload block not found');
        }
        return $hlblock['ID'];
    }
}