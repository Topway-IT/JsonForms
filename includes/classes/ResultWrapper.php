<?php

namespace MediaWiki\Extension\JsonForms;

class ResultWrapper {
    public bool $ok;
    public mixed $value;
    public ?string $error;

    private function __construct(bool $ok, $value = null, ?string $error = null) {
        $this->ok = $ok;
        $this->value = $value;
        $this->error = $error;
    }

    public static function success($value): self {
        return new self(true, $value);
    }

    public static function failure(string $error): self {
        return new self(false, null, $error);
    }
    
    public function andThen( callable $fn ) {
    	if ( !$this->ok ) {
      	  return $this;
   	 }

    	return $fn( $this->value );
	}

}

