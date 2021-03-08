<?php

require_once(dirname(__FILE__).'/phpquery/phpQuery/phpQuery.php');


date_default_timezone_set('Asia/Shanghai');

// 抢场地日期
$date = date('Ymd', strtotime('+4day'));

$fileName = 'page.' . $date . '.html';

// 开始时间1（hour）
$startTime1 = empty($argv[1]) ? "" : $argv[1];
// 开始时间2（hour）
$startTime2 = empty($argv[2]) ? "" : $argv[2];

// 特定场地1
$specialFieldNum1 = empty($argv[3]) ? "" : $argv[3];
// 特定场地2
$specialFieldNum2 = empty($argv[4]) ? "" : $argv[4];

$cookieKey = empty($argv[5]) ? "" : $argv[5];

$params = [
    "startTime1" => $startTime1,
    "startTime2" => $startTime2,
    "specialFieldNum1" => $specialFieldNum1,
    "specialFieldNum2" => $specialFieldNum2,
    "cookieKey" => $cookieKey,
];

echo date("Y-m-d H:i:s") . json_encode($params, JSON_UNESCAPED_UNICODE) . PHP_EOL;

// cookie信息
$cookies = [
    'zl' => 'Cookie: JSESSIONID=F88A88428518057DB6469D1029F12010; Hm_lpvt_bc864c0a0574a7cabe6b36d53206fb69=1615188671; Hm_lvt_bc864c0a0574a7cabe6b36d53206fb69=1615179661; gr_user_id=90ec9f8d-dd59-42f1-9975-ce752bea7394; gr_session_id_ade9dc5496ada31e_130004c3-90f9-4be1-9255-703706225ed4=true; gr_session_id_ade9dc5496ada31e=130004c3-90f9-4be1-9255-703706225ed4',
];

$cookie = $cookies[$cookieKey];

// 获取页面内容
getPageContent($date, $fileName, $cookie);

// 获取可用的场地信息
$availableFields = getAvailableFields($fileName);

echo date("Y-m-d H:i:s") . json_encode($availableFields, JSON_UNESCAPED_UNICODE) . PHP_EOL;

// 预定符合时间的特定场地
bookSpecialFields($availableFields, $date, $specialFieldNum1, $specialFieldNum2, $startTime1, $startTime2, $cookie);

// 预定符合时间的连续两个小时的场地
bookContinuityFields($availableFields, $date, $startTime1, $startTime2, $cookie);

// 预定符合时间的任意场地
bookAnyFields($availableFields, $date, $startTime1, $startTime2, $cookie);

function getPageContent($date, $fileName, $cookie)
{
    $cmd = "curl -H 'Host: webssl.xports.cn' -H 'Content-Type: application/x-www-form-urlencoded; charset=utf-8' -H '" . $cookie . "' -H 'Accept: */*' -H 'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 14_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 MicroMessenger/8.0.2(0x1800022e) NetType/WIFI Language/zh_CN' -H 'Referer: https://webssl.xports.cn/aisports-weixin/court/1101000301/1002/1254/20210222?venueName=%E5%8C%97%E4%BA%AC%E5%A4%A9%E9%80%9A%E8%8B%91%E4%BD%93%E8%82%B2%E9%A6%86&serviceName=%E7%BE%BD%E6%AF%9B%E7%90%83&fullTag=0&defaultFullTag=0' -H 'Accept-Language: zh-cn' -H 'X-Requested-With: XMLHttpRequest' --compressed 'https://webssl.xports.cn/aisports-weixin/court/ajax/1101000301/1002/1254/'" . $date . "'?fullTag=0&curFieldType=1254'";
    exec($cmd, $fileContent);

    file_put_contents($fileName, $fileContent);
}

function bookSpecialFields($availableFields, $date, $specialFieldNum1, $specialFieldNum2, $startTime1, $startTime2, $cookie)
{
    $specialField1 = $availableFields[$specialFieldNum1] ?? array();
    $specialField2 = $availableFields[$specialFieldNum2] ?? array();

    $specialFields = array_merge($specialField1, $specialField2);

    $allFieldInfo = array();
    foreach ($specialFields as $field) {
        if ($field['startTime'] == $startTime1) {
            $allFieldInfo[] = $field['fieldInfo'];
        }

        if ($field['startTime'] == $startTime2) {
            $allFieldInfo[] = $field['fieldInfo'];
        }
    }

    if (!empty($allFieldInfo)) {
        commit($allFieldInfo, $date, $cookie);
    }
}

function bookContinuityFields($availableFields, $date, $startTime1, $startTime2, $cookie)
{
    foreach ($availableFields as $fieldNum => $fieldList) {
        $available1 = $available2 = false;

        $field1 = $field2 = array();

        foreach ($fieldList as $field) {
            if ($field['startTime'] == $startTime1) {
                $available1 = true;

                $field1 = $field;
            }

            if ($field['startTime'] == $startTime2) {
                $available2 = true;

                $field2 = $field;
            }
        }

        if ($available1 && $available2) {
            $allFieldInfo = array($field1['fieldInfo'], $field2['fieldInfo']);

            if (!empty($allFieldInfo)) {
                commit($allFieldInfo, $date, $cookie);
            }
        }
    }
}

function bookAnyFields($availableFields, $date, $startTime1, $startTime2, $cookie)
{
    foreach ($availableFields as $fieldNum => $fieldList) {
        $allFieldInfo = array();

        foreach ($fieldList as $field) {
            if ($field['startTime'] == $startTime1) {
                $allFieldInfo[] = $field['fieldInfo'];
            }

            if ($field['startTime'] == $startTime2) {
                $allFieldInfo[] = $field['fieldInfo'];
            }

            if (!empty($allFieldInfo)) {
                commit($allFieldInfo, $date, $cookie);
            }
        }
    }
}

function getAvailableFields($fileName)
{
    $fileContent = file_get_contents($fileName);
    $doc = phpQuery::newDocumentHTML($fileContent);

    phpQuery::selectDocument($doc);

    $halfTime =  pq('div.half-time');
    $doc = phpQuery::newDocumentHTML($halfTime);

    phpQuery::selectDocument($doc);

    $data = array();
    foreach(pq('span') as $span) {
        $state = $span->getAttribute('state');
        if ($state != 0) {
            continue;
        }

        $fieldNum = $span->getAttribute('field-num');
        $data[$fieldNum][] = array(
            'price' => $span->getAttribute('price'),
            'startTime' => $span->getAttribute('start-time') / 2,
            'fieldInfo' => $span->getAttribute('field-segment-id')
        );
    }

    return $data;
}

function commit($fieldInfoList, $date, $cookie)
{
    $info = array(
        'venueId' => 1101000301,
        'serviceId' => 1002,
        'fieldType' => 1254,
        'day' => $date,
        'fieldInfo' => implode(',', $fieldInfoList)
    );
    $jsonInfo = json_encode($info);

    $cmd = "curl -H 'Host: webssl.xports.cn' -H 'Accept: */*' -H 'X-Requested-With: XMLHttpRequest' -H 'Accept-Language: zh-cn' -H 'Content-Type: application/json' -H 'Origin: https://webssl.xports.cn' -H 'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 14_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 MicroMessenger/8.0.2(0x1800022e) NetType/WIFI Language/zh_CN' -H 'Referer: https://webssl.xports.cn/aisports-weixin/court/1101000301/1002/1254/20210222?venueName=%E5%8C%97%E4%BA%AC%E5%A4%A9%E9%80%9A%E8%8B%91%E4%BD%93%E8%82%B2%E9%A6%86&serviceName=%E7%BE%BD%E6%AF%9B%E7%90%83&fullTag=0&defaultFullTag=0' -H '" . $cookie . "' --data-binary '" . $jsonInfo . "' --compressed 'https://webssl.xports.cn/aisports-weixin/court/commit'";
    exec($cmd, $result);

    echo date("Y-m-d H:i:s") . json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;

    $res = $result[0];
    $res = json_decode($res, true);

    // 超过预定限制
    if ($res['resultCode'] == 12030) {
        exit();
    }
}