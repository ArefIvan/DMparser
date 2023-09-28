<?php

use Aris\Parserdm\Core\DmParser;
use React\Http\Browser;
use React\EventLoop\Loop;

require_once('../autoload.php');
require_once('../config.php');




$parser = new DmParser();
$parser->parse();



file_put_contents('../test.json', json_encode($parser->getData(), JSON_UNESCAPED_UNICODE));