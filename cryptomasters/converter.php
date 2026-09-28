<?php


    class Converter {}

    interface CanConvert {
        public function convert(float $value);
    }

    class CryptoConverter extends Converter {
      //Class can contain properties and methods.

      //Properties
        public  string $currencyCode;


      // Constructor Function
        public function __construct(string $currencyCode){
            $this->currencyCode = $currencyCode;

        }

      //Methods 

        public function convert(float $value){

        }


        

    }

    $c = new CryptoConverter(currencyCode:"BTC");