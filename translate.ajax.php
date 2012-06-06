<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package   local_amos
 * @copyright 2012 David Mudrak <david@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->libdir.'/filelib.php');

require_login(SITEID, false);

if (!has_capability('local/amos:usegoogle', context_system::instance())) {
    error_log('AMOS No capability to fetching Google translation');
    header('HTTP/1.1 403 Forbidden');
    die();
}

if (!confirm_sesskey(optional_param('sesskey', -1, PARAM_RAW))) {
    error_log('AMOS Invalid sesskey');
    header('HTTP/1.1 403 Forbidden');
    die();
}

$enid = optional_param('enid', null, PARAM_INT);
$lang = optional_param('lang', null, PARAM_SAFEDIR);
if (is_null($enid) or empty($lang)) {
    error_log('AMOS Invalid parameters provided');
    header('HTTP/1.1 400 Bad Request');
    die();
}

try {
    $string = $DB->get_record('amos_repository', array('id' => $enid, 'lang' => 'en', 'deleted' => 0), '*', MUST_EXIST);

} catch (Exception $e) {
    error_log('AMOS Unable to find the string to translate');
    header('HTTP/1.1 400 Bad Request');
    die();
}

add_to_log(SITEID, 'amos', 'usegoogle', '', $enid, 0, $USER->id);

// map Moodle language codes to Google language codes
switch ($lang) {
case 'zh_cn':
    $lang = 'zh-CN';
    break;
case 'zh_tw':
    $lang = 'zh-TW';
    break;
case 'pt_br':
    $lang = 'pt';
    break;
}

$curl = new curl(array('cache' => false, 'proxy' => true));
$params = array(
    'key' => $CFG->amosgoogleapi,
    'format' => 'text',
    'prettyprint' => 'false',
    'source' => 'en',
    'target' => $lang,
    'userIp' => getremoteaddr(),
    'q' => $string->text,
);

$response = $curl->get('https://www.googleapis.com/language/translate/v2', $params);
$curlinfo = $curl->get_info();
if (empty($curlinfo)) {
    error_log('AMOS Unable to fetch Google translation - empty cURL info');
    header('HTTP/1.1 500 Internal server error');
    die();
}

if ($curlinfo['http_code'] != 200) {
    error_log('AMOS Fetching Google translation - got HTTP response '.$curlinfo['http_code']);
    header('HTTP/1.1 500 Internal server error');
    die();
}

if ($response) {
    $response = json_decode($response);
    if (!empty($response->data->translations) and is_array($response->data->translations)) {
        $first = reset($response->data->translations);
        if (isset($first->translatedText)) {
            $translation = $first->translatedText;
        }
    }

} else {
    $translation = null;
}

if (is_null($translation)) {
    $response = array(
        'error' => array(
            'message' => 'Unable to translate'
        )
    );

} else {
    $response = array(
        'data' => array(
            'translation' => $translation,
        )
    );
}

header('Content-Type: application/json; charset: utf-8');
echo json_encode($response);
