<?php

require_once('autoload.php');

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;


function getBrandLegoId( string $brandName) : int
{
    $url = 'https://api.detmir.ru/v2/brands/' ;

    $brands = json_decode(getResponse($url), associative:true);
    foreach ($brands as $brand) {
        if ( $brand['title'] == $brandName ) {
            return $brand['id'];
        }
    }

}

function getLocations() : array
{
    $url = 'https://api.detmir.ru/v1/locations/';
    return json_decode(getResponse($url), associative:true);
}

function getProducts(int $brandID, int $step = 10) : array
{
    $products = [];
    $limit = $step;
    $offset = 0;

    do {
        $url = sprintf('https://api.detmir.ru/v2/products?filter=brands[].id:%d&limit=%d&offset=%d', $brandID , $limit, $offset);
        $response = json_decode(getResponse($url), associative:true);
        $offset += $step;
        $products = array_merge($products, $response);
    } while (count($response) >= 100 || $offset < 10);

    return $products;
}

function getResponse( string $url) : string
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER , true);
    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

$locations = getLocations();
$locationsName = [];
$locationIso = [];
foreach ($locations as $location) {
    $locationsName[] = $location['region'];
    $locationIso[] = $location['iso'];
}

$products = getProducts(7);

$res=[];
foreach ( $products as $product ) {
    $resone = [$product['id'], $product['article']];
    foreach ($locationIso as $iso) {
        $url = sprintf('https://api.detmir.ru/v2/products/%d/delivery?filter=region.iso:%s', $product['id'], $iso );
        $stores = json_decode(getResponse($url),associative:true )['stores'];
        if ( is_array( $stores ) ) {
            $resone[] = count($stores);
        }
    }
    $res[] = $resone;
}


$spreadsheet = new Spreadsheet();


$spreadsheet->getProperties()
    ->setCreator('Maarten Balliauw')
    ->setLastModifiedBy('Maarten Balliauw')
    ->setTitle('PhpSpreadsheet Test Document')
    ->setSubject('PhpSpreadsheet Test Document')
    ->setDescription('Test document for PhpSpreadsheet, generated using PHP classes.')
    ->setKeywords('office PhpSpreadsheet php')
    ->setCategory('Test result file');

$spreadsheet->setActiveSheetIndex(0)
    ->setCellValue('A1', 'Идентификатор ID')
    ->setCellValue('B1', 'Артикул')
    ->fromArray($locationsName, startCell:'C1');
            
$spreadsheet->getActiveSheet()
    ->getStyle('1')
    ->getFont()
    ->setBold('bold');
$spreadsheet->getActiveSheet()
    ->fromArray($res, startCell:'A2');

    

$spreadsheet->getActiveSheet()
    ->setTitle('Simple');

$writer = IOFactory::createWriter($spreadsheet, 'Xls');
$writer->save('test.xls');