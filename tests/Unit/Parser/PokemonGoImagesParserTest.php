<?php

declare(strict_types=1);

namespace Tests\Unit\PokemonGoLingen\PogoAPI\Parser;

use PHPUnit\Framework\TestCase;
use PokemonGoLingen\PogoAPI\Parser\PokemonGoImagesParser;

/**
 * @covers PokemonGoImagesParser
 */
class PokemonGoImagesParserTest extends TestCase
{
    public function testParseFile(): void
    {
        $sut        = new PokemonGoImagesParser();
        $collection = $sut->parseFile(__DIR__ . '/Fixtures/pokemon_images.json');

        self::assertNotEmpty($collection->getImages(3));
    }
}