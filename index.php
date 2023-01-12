<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("1С-Битрикс: Управление сайтом");





//пишем код
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

CModule::IncludeModule('iblock');
$row = 1;
$IBLOCK_ID = 2; //id инфоблока
$el = new CIBlockElement;
$arProps = [];

$file = $_SERVER["DOCUMENT_ROOT"]."/upload/vacancy.csv"; //имя файла


$rsElement = CIBlockElement::getList([], ['IBLOCK_ID' => 37],false, false, ['ID', 'NAME']);
while ($ob = $rsElement->GetNextElement())
{
    $arFields = $ob->GetFields();
    $key = str_replace(['»', '«', '(', ')'], '', $arFields['NAME']);
    $key = strtolower($key);
    $arKey = explode(' ', $key);
    $key = '';
    foreach ($arKey as $part) {
        if (strlen($part) > 2) {
            $key .= trim($part) . ' ';
        }
    }
    $key = trim($key);
    $arProps['OFFICE'][$key] = $arFields['ID'];
}

$rsProp = CIBlockPropertyEnum::GetList(
    ["SORT" => "ASC", "VALUE" => "ASC"],
    ['IBLOCK_ID' => $IBLOCK_ID]
);
while ($arProp = $rsProp->Fetch()) {
    $key = trim($arProp['VALUE']);
    $arProps[$arProp['PROPERTY_CODE']][$key] = $arProp['ID'];
}

//удаляем старые записи из инфоблока
    $rsElements = CIBlockElement::GetList([], ['IBLOCK_ID' => $IBLOCK_ID], false, false, ['ID']);
    while ($element = $rsElements->GetNext()) {
        CIBlockElement::Delete($element['ID']);
    }



if (($handle = fopen($file, "r")) !== false)
{
    while (($data = fgetcsv($handle, 1000, ",")) !== false)
    {
        if ($row == 1){ $row++; continue;}
        $row++;

        $PROP['ACTIVITY']     = $data[9];              //Тип занятости
        $PROP['FIELD']        = $data[11];             //Сфера деятельности
        $PROP['OFFICE']       = $data[1];              //Комбинат/Офис
        $PROP['LOCATION']     = $data[2];              //Местоположение
        $PROP['REQUIRE']      = $data[4];              //Требования к соискателю
        $PROP['DUTY']         = $data[5];              //Основные обязанности
        $PROP['CONDITIONS']   = $data[6];              //Условия работы
        $PROP['EMAIL']        = $data[12];            //Электронная почта (e-mail)
        $PROP['DATE']         = date('d.m.Y');  //Дата размещения
        $PROP['TYPE']         = $data[8];              //Тип вакансии
        $PROP['SALARY_TYPE']  = '';                    //Заработная плата
        $PROP['SALARY_VALUE'] = $data[7];              //Заработная плата (значение)
        $PROP['SCHEDULE']     = $data[10];             //График работы

        foreach ($PROP as $key => &$value)
        {
            $value = trim($value); //удаляем пробелы
            $value = str_replace('\n', '', $value); // заменяем переносы на пустую строку
            if (stripos($value, '•') !== false)
            {
                $value = explode('•', $value);
                array_splice($value, 0, 1);
                foreach ($value as &$str) {
                    $str = trim($str);
                }
            }
            elseif ($arProps[$key])
            {
                $arSimilar = [];
                foreach ($arProps[$key] as $propKey => $propVal)
                {
                    if ($key == 'OFFICE')
                    {
                        $value = strtolower($value);
                        $arSimilar[similar_text($value, $propKey)] = $propVal;
                    }
                    if (stripos($propKey, $value) !== false) {
                        $value = $propVal;
                        break;
                    }

                    if (similar_text($propKey, $value) > 50) {
                        $value = $propVal;
                    }
                }
                if ($key == 'OFFICE' && !is_numeric($value)) {
                    ksort($arSimilar);
                    $value = array_pop($arSimilar);
                }
            }
        }
        if ($PROP['SALARY_VALUE'] == '-') { $PROP['SALARY_VALUE'] = ''; }
        elseif ($PROP['SALARY_VALUE'] == 'по договоренности') { $PROP['SALARY_VALUE'] = ''; $PROP['SALARY_TYPE'] = $arProps['SALARY_TYPE']['договорная']; }
        else
        {
            $arSalary = explode(' ', $PROP['SALARY_VALUE']);
            if ($arSalary[0] == 'от' || $arSalary[0] == 'до') {
                $PROP['SALARY_TYPE'] = $arProps['SALARY_TYPE'][$arSalary[0]];
                array_splice($arSalary, 0, 1);
                $PROP['SALARY_VALUE'] = implode(' ', $arSalary);
            } else {
                $PROP['SALARY_TYPE'] = $arProps['SALARY_TYPE']['='];
            }
        }


        $arLoadProductArray = [
            "MODIFIED_BY" => $USER->GetID(),
            "IBLOCK_SECTION_ID" => false,
            "IBLOCK_ID" => $IBLOCK_ID,
            "PROPERTY_VALUES" => $PROP,
            "NAME" => $data[3],
            "ACTIVE" => end($data) ? 'Y' : 'N',
        ];

        if ($PRODUCT_ID = $el->Add($arLoadProductArray)) {
            echo "Добавлен элемент с ID : " . $PRODUCT_ID . "<br>";
        } else {
            echo "Error: " . $el->LAST_ERROR . '<br>';
        }

    }



}



require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php"); ?>