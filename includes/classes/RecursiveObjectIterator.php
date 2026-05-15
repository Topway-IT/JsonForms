<?php

namespace MediaWiki\Extension\JsonForms;

class RecursiveObjectIterator extends \RecursiveIteratorIterator {
    public function __construct( $data ) {
        parent::__construct(
            new RecursiveObjectArrayIterator( $data ),
            \RecursiveIteratorIterator::SELF_FIRST
        );
    }
}

class RecursiveObjectArrayIterator extends \RecursiveArrayIterator {
    public function __construct( $data ) {
        // Convert stdClass to array
        if ( is_object( $data ) ) {
            $data = (array) $data;
        }
        parent::__construct( $data );
    }
    
    public function getChildren() {
        $children = $this->current();
        
        // Convert stdClass to array for children
        if ( is_object( $children ) ) {
            $children = (array) $children;
        }
        
        return new self( $children );
    }
    
    public function hasChildren() {
        $current = $this->current();
        return is_array( $current ) || is_object( $current );
    }
}
