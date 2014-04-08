<?php 

namespace Mapper;

use Nette\Object;

class MapperExtension extends Object
{
    
	public function loadConfiguration()
	{
		$container = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		// db connection
		
        // annotation mapping
		$container->addDefinition($this->prefix('mapping'))->setClass('Mapper\Mapping\AnnotationMapping');
		
		// identity map
		$container->addDefinition($this->prefix('identityMap'))->setClass('Mapper\Hydration\IdentityMap');
		
		// hydrator
		$container->addDefinition($this->prefix('hydrator'))->setClass('Mapper\Hydration\ObjectHydrator');
        
	}

}
