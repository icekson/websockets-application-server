<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

 namespace Icekson\Utils;
 
/**
 * @desc Exchange data ro array and from array
 * @author Itsekson Alexey
 */
interface IArrayExchange {
    
    public function toArray();
    
    public function fromArray(array $data);
}

?>
