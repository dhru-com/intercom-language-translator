<?php
/*
 * Author : DHRU
 * Very simple language translator using google apis for intercom web hook and rest api
 */

$config[intercom_token] = "{intercom app token}";
$config[intercom_admin_id] = "{intercom admi id}";
$config[google_api_key] = "{Google translator api key}";
$config[target_language] = "en";


$inputJSON = json_decode(file_get_contents('php://input'), true);
$topic = $inputJSON[topic];
$data = $inputJSON[data][item];


switch ($topic) {
    case "conversation.user.replied":

        $_conversation_id = $data[id];
        $_msg = strip_tags($data[conversation_parts][conversation_parts][0][body]);
        $_author = $data[conversation_parts][conversation_parts][0][author];

        if($_msg) {

            /* detect language of content */
            $_google_api_url = "https://translation.googleapis.com/language/translate/v2/detect?key=$config[google_api_key]";
            $_google_api_post[q] = $_msg;
            $_detect = initCurl($_google_api_url, $_google_api_post, "");
            $_detected_lng = $_detect[data][detections][0][0][language];


            /* process only if language is not yours */
            if ($_detected_lng != $config[target_language] and $_detected_lng != 'und') {
                $_google_api_url = "https://translation.googleapis.com/language/translate/v2?key=$config[google_api_key]";
                $_google_api_post[q] = $_msg;
                $_google_api_post[target] = $config[target_language];
                $_translate = initCurl($_google_api_url, $_google_api_post, "");
                $_msg = $_translate['data']['translations'][0][translatedText];


                /* Post back to intercom as note */
                $_apiurl = "https://api.intercom.io/conversations/$_conversation_id/reply";
                $_apipost[admin_id] = $config[intercom_admin_id];
                $_apipost[body] = "Translate from:$_detected_lng to en:<br />" . $_msg;
                $_apipost[type] = "admin";
                $_apipost[message_type] = "note";
                initCurl($_apiurl, $_apipost, $config[intercom_token]);
            }
        }
        break;
    default:
}


function initCurl($url, $post, $token)
{
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_POST, 1);
        if ($post) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $_headers[] = 'Content-Type:application/json';
        $_headers[] = "Accept:application/json";
        if ($token) {
            $_headers[] = "Authorization:Bearer $token";
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $_headers);
        $result = curl_exec($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($result === false) {
            return false;
        } else {
            return json_decode($result, true);
        }
        curl_close($ch);
    } catch (Exception $e) {
        return false;
    }
}
