<?php declare(strict_types=1);

namespace ThemeDevTools\Tests;


use PHPUnit\Framework\TestCase;
use ThemeDevTools\Command\CopyView;


class UsedClassesAvailableTest extends TestCase
{

    public function testClassesAreInstantiable(): void
    {
        $foo = new CopyView();


        /*
        $required_classes = [
            '\ThemeDevTools\Command\CopyView'
        ];

        foreach($required_classes as $class) {
            if (!class_exists($class)) {
                $this->fail('foo');
            }
        }
        */
    
    }

}