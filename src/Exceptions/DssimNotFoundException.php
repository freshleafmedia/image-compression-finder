<?php declare(strict_types=1);

namespace Freshleafmedia\ImageCompressionFinder\Exceptions;

class DssimNotFoundException extends \Exception
{
    public function __construct()
    {
        parent::__construct('The DSSIM executable was not found. Please install it: https://github.com/kornelski/dssim#download');
    }
}