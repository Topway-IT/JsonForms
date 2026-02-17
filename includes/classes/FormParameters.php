<?php

namespace MediaWiki\Extension\JsonForms;

use MediaWiki\Extension\JsonForms\Aliases\Title as TitleClass;

/**
 * QueryLink-specific parameter processor
 */
class FormParameters extends ProcessParameters
{
    protected array $defaultParameters = [];

    public function __construct(array $argv = [], array $schema = [])
    {
    	$this->defaultParameters = $this->buildDefaultParametersFromSchema( $schema );
        parent::__construct($argv);
    }
}

