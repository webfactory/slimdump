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
     * @dataProvider provideValidReplacementIds
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
     * @dataProvider provideValidReplacementIds
     */
    public function testValidateReplacementConfiguredExisting($replacementId)
    {
        $fakerReplacer = new FakerReplacer();
        $fakerReplacer->generateReplacement($replacementId);
    }

    /**
     *
     */
    public function testValidateReplacementConfiguredNotExisting()
    {
        $fakerReplacer = new FakerReplacer();

        $this->setExpectedException(InvalidReplacementOptionException::class, 'FOOBAR is no valid faker replacement');

        // neither individual property nor faker property
        $fakerReplacer->generateReplacement('FAKER_FOOBAR');
    }

    /**
     * provides valid faker replacement ids
     * @return array
     */
    public function provideValidReplacementIds()
    {
        return [
            [FakerReplacer::PREFIX . 'firstname'], // original faker property
            [FakerReplacer::PREFIX . 'lastname'], // original faker property
        ];
    }

    /**
     * provides valid faker replacement ids
     * @return array
     */
    public function provideValidReplacementNames()
    {
        return [
            ['firstname'], // original faker property
            ['lastname'], // original faker property
        ];
    }
}
