<?php

require_once(dirname(__FILE__).'/phpquery/phpQuery/phpQuery.php');

$infos = include dirname(__FILE__).'/config.php';


date_default_timezone_set('Asia/Shanghai');

// 抢场地日期
$date = date('Ymd', strtotime('+4day'));

$num = 5;
$fileName = sprintf("pages/page%s.%s.html", $num, $date);
$cookie = $infos[$num-1]['cookie'];

for ($i = 0; $i < 6; $i++) {
    $availableFields = getAvailableFields($fileName, $date, $cookie);
    if (!empty($availableFields)) {
        break;
    }
}

if (empty($availableFields)) {
    echo 'get available fields failed: script1' . PHP_EOL;
    exit();
}

$i = $num - 1;
// 预定符合时间的特定场地
bookSpecialFields($availableFields,
    $date,
    $infos[$i]['field']['num1'],
    $infos[$i]['field']['num2'],
    $infos[$i]['time']['time1'],
    $infos[$i]['time']['time2'],
    $cookie);

// 预定符合时间的连续两个小时的场地
bookContinuityFields($availableFields,
    $date,
    $infos[$i]['time']['time1'],
    $infos[$i]['time']['time2'],
    $cookie);

// 预定符合时间的任意场地
bookAnyFields($availableFields,
    $date,
    $infos[$i]['time']['time1'],
    $infos[$i]['time']['time2'],
    $cookie);
exit(0);

function getAvailableFields($fileName, $date, $cookie)
{
    $cmd = "curl -H 'Host: webssl.xports.cn' -H 'Content-Type: application/x-www-form-urlencoded; charset=utf-8' -H '" . $cookie . "' -H 'Accept: */*' -H 'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 14_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 MicroMessenger/8.0.2(0x1800022e) NetType/WIFI Language/zh_CN' -H 'Referer: https://webssl.xports.cn/aisports-weixin/court/1101000301/1002/1254/20210222?venueName=%E5%8C%97%E4%BA%AC%E5%A4%A9%E9%80%9A%E8%8B%91%E4%BD%93%E8%82%B2%E9%A6%86&serviceName=%E7%BE%BD%E6%AF%9B%E7%90%83&fullTag=0&defaultFullTag=0' -H 'Accept-Language: zh-cn' -H 'X-Requested-With: XMLHttpRequest' --compressed 'https://webssl.xports.cn/aisports-weixin/court/ajax/1101000301/1002/1254/" . $date . "?fullTag=0' -s";
    exec($cmd, $fileContent);
    file_put_contents($fileName, $fileContent);
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

function getPhysicalAreaAvailableFields($fileName, $date, $cookie)
{
    $cmd = "curl -H 'Host: webssl.xports.cn' -H 'Content-Type: application/x-www-form-urlencoded; charset=utf-8' -H '" . $cookie . "' -H 'Accept: */*' -H 'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 14_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 MicroMessenger/8.0.2(0x1800022e) NetType/WIFI Language/zh_CN' -H 'Referer: https://webssl.xports.cn/aisports-weixin/court/1101000301/1002/1254/20210222?venueName=%E5%8C%97%E4%BA%AC%E5%A4%A9%E9%80%9A%E8%8B%91%E4%BD%93%E8%82%B2%E9%A6%86&serviceName=%E7%BE%BD%E6%AF%9B%E7%90%83&fullTag=0&defaultFullTag=0' -H 'Accept-Language: zh-cn' -H 'X-Requested-With: XMLHttpRequest' --compressed 'https://webssl.xports.cn/aisports-weixin/court/ajax/1101000301/1002/1449/" . $date . "' -s";
    exec($cmd, $fileContent);
    file_put_contents($fileName, $fileContent);
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

    $cmd = "curl -H 'Host: webssl.xports.cn' -H 'Accept: */*' -H 'X-Requested-With: XMLHttpRequest' -H 'Accept-Language: zh-cn' -H 'Content-Type: application/json' -H 'Origin: https://webssl.xports.cn' -H 'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 14_4 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 MicroMessenger/8.0.2(0x1800022e) NetType/WIFI Language/zh_CN' -H 'Referer: https://webssl.xports.cn/aisports-weixin/court/1101000301/1002/1254/20210222?venueName=%E5%8C%97%E4%BA%AC%E5%A4%A9%E9%80%9A%E8%8B%91%E4%BD%93%E8%82%B2%E9%A6%86&serviceName=%E7%BE%BD%E6%AF%9B%E7%90%83&fullTag=0&defaultFullTag=0' -H '" . $cookie . "' --data-binary '" . $jsonInfo . "' --compressed 'https://webssl.xports.cn/aisports-weixin/court/commit' -s";
    exec($cmd, $result);

    echo 'script' . $num . '||' . date("Y-m-d H:i:s") . $cookie . "||" . json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;

    $res = $result[0];
    $res = json_decode($res, true);

    // 超过预定限制
    if ($res['resultCode'] == 12030) {
        exit();
    }
}
