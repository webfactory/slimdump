<?php

namespace Webfactory\Slimdump\Config;

use PHPUnit\Framework\TestCase;
use Webfactory\Slimdump\Exception\InvalidReplacementOptionException;

class FakerReplacerTest extends TestCase
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
     * @param string $replacementId
     * @dataProvider provideValidReplacementIds
     * @doesNotPerformAssertions
     */
    public function testValidateReplacementConfiguredExisting($replacementId)
    {
        $fakerReplacer = new FakerReplacer();
        $fakerReplacer->generateReplacement($replacementId);
    }

    public function testValidateReplacementConfiguredNotExisting()
    {
        $fakerReplacer = new FakerReplacer();

        $this->expectException(InvalidReplacementOptionException::class);

        // neither individual property nor faker property
        $fakerReplacer->generateReplacement('FAKER_FOOBAR');
    }

    /**
     * provides valid faker replacement ids.
     *
     * @return array
     */
    public function provideValidReplacementIds()
    {
        return [
            [FakerReplacer::PREFIX.'firstname'], // original faker property
            [FakerReplacer::PREFIX.'lastname'], // original faker property
            [FakerReplacer::PREFIX.'randomDigitNot:0'], // faker method with single argument
            [FakerReplacer::PREFIX.'numberBetween:1,20'], // faker method with two arguments
            [FakerReplacer::PREFIX.'shuffle:"hello world"'], // faker property with single argument
            [FakerReplacer::PREFIX.'numerify:"Helo ###"'], // faker property with single argument
        ];
    }

    /**
     * provides valid faker replacement ids.
     *
     * @return array
     */
    public function provideValidReplacementNames()
    {
        return [
            ['firstname'], // original faker property
            ['lastname'], // original faker property
            ['randomDigitNot:0'], // original faker property
            ['numberBetween:1,20'], // original faker property
            ['shuffle:"hello world"'], // original faker property
            ['numerify:"Helo ###"'], // original faker property
        ];
    }
}
