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
 * AMOS script to parse English strings in the core
 *
 * This is supposed to be run regularly in a cronjob to register all changes
 * done in Moodle source code.
 *
 * @package   local_amos
 * @copyright 2010 David Mudrak <david.mudrak@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

set_time_limit(0);
$starttime = microtime();

// this cron script might be considered to be a CLI script even when accessed over HTTP,
// we do not want HTML in output and there is no real session ;-)
define('CLI_SCRIPT', true);

// Do not set moodle cookie because we do not need it here, it is better to emulate session
define('NO_MOODLE_COOKIES', true);

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/local/amos/cli/config.php');
require_once($CFG->dirroot . '/local/amos/mlanglib.php');

// send mime type and encoding
if (check_browser_version('MSIE')) {
    //ugly IE hack to work around downloading instead of viewing
    @header('Content-Type: text/html; charset=utf-8');
    echo "<xmp>"; //<pre> is not good enough for us here
} else {
    //send proper plaintext header
    @header('Content-Type: text/plain; charset=utf-8');
}

// no more headers and buffers
while(@ob_end_flush());

// increase memory limit (PHP 5.2 does different calculation, we need more memory now)
@raise_memory_limit('128M');

$tmp = make_upload_directory('amos/temp', false);
$var = make_upload_directory('amos/var', false);
$mem = memory_get_usage();

// the following commits contains a syntax typo and they can't be included for processing. They are skipped
$MLANG_BROKEN_CHECKOUTS = array(
    '52425959755ff22c733bc39b7580166f848e2e2a_lang_en_utf8_enrol_authorize.php',
    '46702071623f161c4e06ee9bbed7fbbd48356267_lang_en_utf8_enrol_authorize.php',
    '1ec0ef254c869f6bd020edafdb78a80d4126ba79_lang_en_utf8_role.php',
    '8871caf0ac9735b67200a6bdcae3477701077e63_lang_en_utf8_role.php',
    '50d30259479d27c982dabb5953b778b71d50d848_lang_en_utf8_countries.php',
    'e783513693c95d6ec659cb487acda8243d118b84_lang_en_utf8_countries.php',
    '5e924af4cac96414ee5cd6fc22b5daaedc86a476_lang_en_utf8_countries.php',
    'c2acd8318b4e95576015ccc649db0f2f1fe980f7_lang_en_utf8_grades.php',
    '5a7e8cf985d706b935a61366a0c66fd5c6fb20f9_lang_en_utf8_grades.php',
    '8e9d88f2f6b5660687c9bd5decbac890126c13e5_lang_en_utf8_debug.php',
    '1343697c8235003a91bf09ad11ab296f106269c7_lang_en_utf8_error.php',
    'c5d0eaa9afecd924d720fbc0b206d144eb68db68_lang_en_utf8_question.php',
    '06e84d52bd52a4901e2512ea92d87b6192edeffa_lang_en_utf8_error.php',
    '4416da02db714807a71d8a28c19af3a834d2a266_lang_en_utf8_enrol_mnet.php',
);

$MLANG_IGNORE_COMMITS = array(
    //
    // the following are MDL-21694 commits that just move the lang files. such a move is registered
    // as a deletion and re-addition of every string which is usually useless
    //
    '9d68aee7860398345b3921b552ccaefe094d438a',
    '5f251510549671a3864427e4ea161b8bd62d0df9',
    '60b00b6d99f10c084375d09c244f0011baabdec9',
    'f312fe1a9e00abe1f79348d1092697a485369bfb',
    '05162f405802faf006cac816443432d29e742458',
    '57223fbe95df69ebb9831ff681b89ec67de850ff',
    '7ae8954a02ebaf82f74e2842e4ad17c05f6af6a8',
    '1df58edc0f25db3892950816f6b9edb2de693a2c',
    'd8753184ec66575cffc834aaeb8ac25477da289b',
    '200fe7f26b1ba13d9ac63f073b6676ce4abd2976',
    '2476f5f22c2bfaf0626a7e1e8af0ffee316b01b4',
    'd8a81830333d99770a6072ddc0530c267ebddcde',
    'afbbc6c0f6667a5af2a55aab1319f3be00a667f1',
    '3158eb9350ed79c3fe81919ea8af67418de18277',
    'ffbb41f48f9c317347be4288771db84e36bfdf22',
    '81144b026f80665a7d7ccdadbde4e8f99d91e806',
    '675aa51db3038b629c7350a53e43f20c5d414045',
    'dee576ebbaece98483acfa401d459f62f0f0387d',
    'eea1d899bca628f9c5e0234068beb713e81a64fd',
    'ce0250ec19cf29479b36e17541c314030a2f9ab5',
    'bda5f9b1159bff09006dac0bcfaec1ec788f134c',
    '89422374d1944d4f5fff08e2187f2c0db75aaefc',
    'b4340cb296ce7665b6d8f64885aab259309271a6',
    '001fa4b3135b27c2364845a221d11ea725d446a0',
    'c811222ff9b1469633f7e8dbf6b06ddccafb8dbd',
    '7a4ddc172ae46014ee2ebb5b9f4ee2ada2cd7e1e',
    'bc3755be21025c5815de19670eb04b0875f5fa31',
    '96b838efa990d6a6a2db0050d9deeceeda234494',
    'cb9dc45c36ffbbdee1a0f22a50b4f31db47a5eb6',
    '33aadb2d70c4e8381281b635a9012f3f0673d397',
    '34970b7fc6c4932b15426ea80ad94867a1e1bb5b',
    '7a563f0f3586a4bc5b5263492282734410e01ee0',
    'b13af519fc48ee9d8b1e801c6056519454bf8400',
    'd1f62223b59d6acb1475d3979cdafda726cc1290',
    '2064cbaa0f6ea36fc5803fcebb5954ef8c642ac4',
);

$MLANG_PARSE_BRANCHES = array(
    'MOODLE_20_STABLE',
    'MOODLE_19_STABLE',
    'MOODLE_18_STABLE',
    'MOODLE_17_STABLE',
    'MOODLE_16_STABLE',
);

foreach ($MLANG_PARSE_BRANCHES as $branch) {
    echo "*****************************************\n";
    echo "BRANCH {$branch}\n";
    if ($branch == 'MOODLE_20_STABLE') {
        $gitbranch = 'origin/cvshead';
    } else {
        $gitbranch = 'origin/' . $branch;
    }
    $version = mlang_version::by_branch($branch);

    $startatlock = "{$var}/{$branch}.startat";
    $startat = '';
    if (file_exists($startatlock)) {
        $startat = trim(file_get_contents($startatlock));
        if (!empty($startat)) {
            $startat = '^' . $startat . '^';
        }
    }

    chdir(AMOS_REPO_MOODLE);
    $gitout = array();
    $gitstatus = 0;
    $gitcmd = "git whatchanged --reverse --format=format:COMMIT:%H {$gitbranch} {$startat}";
    echo "RUN {$gitcmd}\n";
    exec($gitcmd, $gitout, $gitstatus);

    if ($gitstatus <> 0) {
        // error occured
        die('ERROR git-log');
    }

    $commithash = '';
    foreach ($gitout as $line) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }
        if (substr($line, 0, 7) == 'COMMIT:') {
            // remember the processed commithash
            if (!empty($commithash)) {
                file_put_contents($startatlock, $commithash);
            }
            $commithash = substr($line, 7);
            continue;
        }
        if (in_array($commithash, $MLANG_IGNORE_COMMITS)) {
            echo "IGNORED {$commithash}\n";
            continue;
        }
        $parts = explode("\t", $line);
        $changetype = substr($parts[0], -1);    // A (added new file), M (modified), D (deleted)
        $file = $parts[1];
        if (!strstr($file, 'lang/en_utf8/')) {
            // this is not a language file
            continue;
        }
        if (substr($file, 0, 13) == 'install/lang/') {
            // ignore these auto generated files
            continue;
        }
        if (substr($file, -4) !== '.php') {
            // this is not a valid string file
            continue;
        }
        $memprev = $mem;
        $mem = memory_get_usage();
        $memdiff = $memprev < $mem ? '+' : '-';
        $memdiff = $memdiff . abs($mem - $memprev);
        echo "{$commithash} {$changetype} {$file} [{$mem} {$memdiff}]\n";

        // get some additional information of the commit
        $format = implode('%n', array('%an', '%ae', '%at', '%s')); // name, email, timestamp, subject
        $commitinfo = array();
        $gitcmd = "git log --format={$format} {$commithash} ^{$commithash}~";
        exec($gitcmd, $commitinfo);
        $committer      = $commitinfo[0];
        $committeremail = $commitinfo[1];
        $timemodified   = $commitinfo[2];
        $commitmsg      = iconv('UTF-8', 'UTF-8//IGNORE', $commitinfo[3]);

        if ($changetype == 'D') {
            // whole file removal
            $component = mlang_component::from_snapshot(mlang_component::name_from_filename($file), 'en', $version, $timemodified);
            foreach ($component->get_iterator() as $string) {
                $string->deleted = true;
                $string->timemodified = $timemodified;
            }
            $stage = new mlang_stage();
            $stage->add($component);
            $stage->commit($commitmsg, array(
                'source' => 'git',
                'userinfo' => $committer . ' <' . $committeremail . '>',
                'commithash' => $commithash
            ), true);
            $component->clear();
            unset($component);
            continue;
        }

        // dump the given revision of the file to a temporary area
        $checkout = $commithash . '_' . str_replace('/', '_', $file);
        if (in_array($checkout, $MLANG_BROKEN_CHECKOUTS)) {
            echo "BROKEN $checkout\n";
            continue;
        }
        $checkout = $tmp . '/' . $checkout;
        exec("git show {$commithash}:{$file} > {$checkout}");

        // convert the php file into strings in the staging area
        $component = mlang_component::from_phpfile($checkout, 'en', $version, $timemodified, mlang_component::name_from_filename($file));
        $stage = new mlang_stage();
        $stage->add($component);
        $stage->rebase($timemodified, true, $timemodified);
        $stage->commit($commitmsg, array(
                'source' => 'git',
                'userinfo' => $committer . ' <' . $committeremail . '>',
                'commithash' => $commithash
            ), true);
        $component->clear();
        unset($component);
        unlink($checkout);
    }
}
echo "DONE\n";