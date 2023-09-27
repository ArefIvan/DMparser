<?php

namespace Aris\Parserdm\Core;

use Exception;
use React\Http\Browser;
use Psr\Http\Message\ResponseInterface;
use React\Http\Message\Response;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

class DmParser 
{

    private $client;

    private $products = [];
    private $urls = [];
    private string $brandName;
    private  int $brandId;
    private $locations = [];
    private $data = [];

    public function __construct(Browser $client , string $brandName = 'LEGO' , int $brandId = BRAND_ID )
    {
        $this->client = $client;
        $this->brandName = $brandName;
        if ( $brandName !== BRAND_NAME ){
            $this->setBrandId($brandName);
        } else {  
            $this->brandId = $brandId;
        }
      
    }

    private function getResponse( string $url) : string
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER , true);
        $response = curl_exec($ch);
        curl_close($ch);
    
        return $response;
    }

    private function setBrandId() : void
    {
        $url = URL_BRANDS ;
        
        $brands = json_decode($this->getResponse($url), associative:true);
        foreach ($brands as $brand) {
            if ( $brand['title'] == $this->brandName ) {
                $this->brandId = $brand['id'];
            }
        }
    
    }

    private function setLocations() : void
    {
        $url = URL_LOCATIONS;
        $locations = json_decode($this->getResponse($url), associative:true);
        foreach ($locations as $location) {
            $this->locations[$location['iso']] = $location['region'];
        }
    }

    private function setProducts(int $step = 10) : void
    {
        $products =[];
        $limit = $step;
        $offset = 0;
    
        do {
            $url = sprintf('%s?filter=brands[].id:%d&limit=%d&offset=%d',URL_PRODUCTS, $this->brandId , $limit, $offset);
            $response = json_decode($this->getResponse($url), associative:true);
            $offset += $step;
            foreach( $response as $product ) {
                $this->products[$product['id']] = $product['article'];
            }
        } while (count($response) >= 100 || $offset < 10);

    }
    public function getData()
    {
        return $this->data;
    } 

    public function parse()
    {
        // $this->setLocations();
        $this->setProducts();
        $this->locations = ["RU-AD" => 'adigea',"RU-AL" => 'altay'];
        foreach ($this->products as $productId => $productArcicle) {
            $this->data[$productId] = ['article' => $productArcicle];
            foreach ( $this->locations as $iso => $region ) {
                $url = sprintf('https://api.detmir.ru/v2/products/%d/delivery?filter=region.iso:%s', $productId, $iso ); 
                
                $this->client->get($url)
                        ->then(
                        function( ResponseInterface $response) use ($productId , $iso) {
                            $res = json_decode($response->getBody()->__toString(),associative:true);
                            $this->data[$productId] += [$iso => count( $res['types']['store']['variants'])];
                        },
                        function( Exception $e) {
                            print $e->getMessage();
                        }
                    );
            }

        }
        
    }
}