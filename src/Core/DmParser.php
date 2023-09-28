<?php

namespace Aris\Parserdm\Core;

use Clue\React\Mq\Queue;
use Exception;
use React\Http\Browser;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\Http\Message\Response;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

class DmParser 
{

    // private $client;

    private $products = [];
    private $urls = [];
    private string $brandName;
    private  int $brandId;
    private $locations = [];
    private $data = [];

    public function __construct( string $brandName = 'LEGO' , int $brandId = BRAND_ID )
    {
        // $this->client = $client;
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

    public function setData()
    {
        $this->setLocations();
        $this->setProducts();
        foreach ($this->products as $productId => $productArcicle) {
            $this->data[$productId] = ['article' => $productArcicle];
            $this->data[$productId] += ['stores' => []];
            foreach ( $this->locations as $iso => $region ) {
                $this->data[$productId]['stores'] += [$iso => null];
            }
        }
    }

    private function parseData( array $data )
    {
        $loop = Loop::get();
        $client = new Browser($loop);
        $queue = new Queue(10, null, function($url) use ($client) {
            return $client->get($url);
        });

        foreach ( $data as $productId => $products ) {
            foreach ($products['stores'] as $iso => $item) {
                $url = sprintf(URL_DELIVERY, $productId, $iso);
                $queue($url)
                    ->then(
                        function( ResponseInterface $response) use ($productId , $iso) {
                            $res = json_decode((string)$response->getBody(), associative:true);
                            if ( null === $this->data[$productId]['stores'][$iso]) {
                                $this->data[$productId]['stores'][$iso] = count( $res['types']['store']['variants']);                                
                            }
                        },
                        function( Exception $e) use ($productId , $iso){
                            $errors = sprintf('ProductId: %d; LocationIso: %s; Error: %s', $productId, $iso, $e->getMessage());
                            file_put_contents('../errors.log',$errors . PHP_EOL, FILE_APPEND);
                        }
                    );
            }
        }

        $loop->run();

    }
    public function parse()
    {
        $this->setData();
        $data = $this->data;
        $this->parseData($data);
    }
}