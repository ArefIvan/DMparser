<?php

use Aris\Parserdm\Core\DmParser;
use React\Http\Browser;
use React\EventLoop\Loop;

require_once('../autoload.php');
require_once('../config.php');


$loop = Loop::get();
$client = new Browser($loop);

$parser = new DmParser($client);
$parser->parse();

$loop->run();

var_dump($parser->getData());