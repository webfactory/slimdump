<?php

namespace Webfactory\Slimdump\Config;

use Webfactory\Slimdump\Exception\InvalidReplacementOptionException;

/**
 * Class FakerReplacerTest
 * @package Webfactory\Slimdump\Config
 */
class FakerReplacerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @param string $replacementId
     * @dataProvider provideValidReplacementOptions
     */
    public function testIsFakerColumnSuccess($replacementId)
    {
        $this->assertTrue(FakerReplacer::isFakerColumn($replacementId));
    }

    public function testIsFakerColumnFail()
    {
        $this->assertFalse(FakerReplacer::isFakerColumn('NOTFAKER_NAME'));
    }

    /**
     * no assertion because we only expect that no exception is thrown
     * @param string $replacementId
     * @dataProvider provideValidReplacementOptions
     */
    public function testValidateReplacementConfiguredExisting($replacementId)
    {
        $fakerReplacer = new FakerReplacer();
        $fakerReplacer->generateReplacement($replacementId);
    }

    public function testValidateReplacementConfiguredNotExisting()
    {
        $fakerReplacer = new FakerReplacer();

        $this->setExpectedException(InvalidReplacementOptionException::class, 'FAKER_FOOBAR is no valid faker replacement');
        $fakerReplacer->generateReplacement('FAKER_FOOBAR');
    }

    /**
     * @param string $replacementId
     * @dataProvider provideValidReplacementOptions
     */
    public function testGetReplacementByIdSuccess($replacementId)
    {
        $fakerReplacer = new \ReflectionClass(FakerReplacer::class);
        $replacementMethod = $fakerReplacer->getMethod('getReplacementById');
        $replacementMethod->setAccessible(true);

        $replacedValue = $replacementMethod->invokeArgs(new FakerReplacer(), [$replacementId]);
        $this->assertNotEmpty($replacedValue);
    }

    public function testGenerateFullNameReplacementContainsSpace()
    {
        $fakerReplacer = new \ReflectionClass(FakerReplacer::class);
        $replacementMethod = $fakerReplacer->getMethod('generateFullNameReplacement');
        $replacementMethod->setAccessible(true);

        $replacedValue = $replacementMethod->invoke(new FakerReplacer());
        $this->assertContains(' ', $replacedValue);
    }

    /**
     * provides valid faker replacement ids
     * @return array
     */
    public function provideValidReplacementOptions()
    {
        return [
            [FakerReplacer::PREFIX . 'NAME'],
            [FakerReplacer::PREFIX . 'FIRSTNAME'],
            [FakerReplacer::PREFIX . 'LASTNAME'],
        ];
    }
}
