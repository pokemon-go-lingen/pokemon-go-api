<?php

declare(strict_types=1);

namespace PokemonGoLingen\PogoAPI\Parser;

use DOMDocument;
use DOMElement;
use PokemonGoLingen\PogoAPI\Collections\PokemonCollection;
use PokemonGoLingen\PogoAPI\Collections\RaidBossCollection;
use PokemonGoLingen\PogoAPI\Types\RaidBoss;

use function assert;
use function count;
use function explode;
use function implode;
use function preg_match;
use function str_replace;
use function stripos;
use function strtoupper;
use function trim;

class LeekduckParser
{
    private PokemonCollection $pokemonCollection;

    public function __construct(PokemonCollection $pokemonCollection)
    {
        $this->pokemonCollection = $pokemonCollection;
    }

    public function parseRaidBosses(string $htmlPage): RaidBossCollection
    {
        $domDocument = new DOMDocument();
        @$domDocument->loadHTMLFile($htmlPage);

        $raidList = $domDocument->getElementById('raid-list');
        if ($raidList === null) {
            return new RaidBossCollection();
        }

        $liItems          = $raidList->getElementsByTagName('li');
        $raids            = new RaidBossCollection();
        $currentTierLevel = 'unknown';
        foreach ($liItems as $liItem) {
            assert($liItem instanceof DOMElement);
            $attributeClass = $liItem->attributes !== null ? $liItem->attributes->getNamedItem('class') : null;
            if ($attributeClass !== null && $attributeClass->nodeValue === 'header-li') {
                $currentTierLevelText = trim(str_replace('Tier', '', $liItem->textContent));
                switch ($currentTierLevelText) {
                    case 'EX':
                        $currentTierLevel = RaidBoss::RAID_LEVEL_EX;
                        break;
                    case 'Mega':
                        $currentTierLevel = RaidBoss::RAID_LEVEL_MEGA;
                        break;
                    case '5':
                        $currentTierLevel = RaidBoss::RAID_LEVEL_5;
                        break;
                    case '3':
                        $currentTierLevel = RaidBoss::RAID_LEVEL_3;
                        break;
                    case '1':
                        $currentTierLevel = RaidBoss::RAID_LEVEL_1;
                        break;
                }
            } else {
                $bossNameValue = trim($this->getElementsByClass($liItem, 'boss-name')[0]->nodeValue);
                [, $formName]  = $this->extractFormBossName($bossNameValue);

                $bossImageContainer = $this->getElementsByClass($liItem, 'boss-img')[0] ?? null;
                $matches            = [];
                if ($bossImageContainer !== null) {
                    $bossImage = $bossImageContainer->getElementsByTagName('img')[0];
                    if ($bossImage !== null) {
                        $imgSrc = $bossImage->getAttribute('src');
                        preg_match('~pokemon_icon_(pm)?(?<dexNr>\d+)_~', $imgSrc, $matches);
                    }
                }

                $dexNr = isset($matches['dexNr']) ? (int) $matches['dexNr'] : null;
                if ($dexNr === null) {
                    continue;
                }

                $pokemon = $basePokemon = $this->pokemonCollection->getByDexId($dexNr);
                if ($basePokemon === null || $pokemon === null) {
                    continue;
                }

                $pokemonIdParts = [$basePokemon->getId()];
                if ($formName !== null) {
                    $pokemonIdParts = [...$pokemonIdParts, ...explode(' ', trim($formName))];
                }

                $pokemonFormId = strtoupper(implode('_', $pokemonIdParts));

                $pokemonTemporaryEvolution = null;
                foreach ($pokemon->getTemporaryEvolutions() as $temporaryEvolution) {
                    if ($temporaryEvolution->getId() !== $pokemonFormId) {
                        continue;
                    }

                    $pokemonTemporaryEvolution = $temporaryEvolution;
                }

                foreach ($pokemon->getPokemonRegionForms() as $regionForm) {
                    if ($regionForm->getFormId() !== $pokemonFormId) {
                        continue;
                    }

                    $pokemon = $regionForm;
                }

                $raidboss = new RaidBoss(
                    $pokemon->getFormId(),
                    count($this->getElementsByClass($liItem, 'shiny-icon')) === 1,
                    $currentTierLevel,
                    $pokemon,
                    $pokemonTemporaryEvolution
                );
                $raids->add($raidboss);
            }
        }

        return $raids;
    }

    /**
     * @return DOMElement[]
     */
    private function getElementsByClass(DOMElement $node, string $className): array
    {
        $nodes = [];

        $childNodeList = $node->getElementsByTagName('*');
        for ($i = 0; $i < $childNodeList->length; $i++) {
            $temp = $childNodeList->item($i);
            if ($temp === null) {
                continue;
            }

            if (stripos($temp->getAttribute('class'), $className) === false) {
                continue;
            }

            $nodes[] = $temp;
        }

        return $nodes;
    }

    /**
     * @return array<int, string|null>
     */
    private function extractFormBossName(string $bossName): array
    {
        if (stripos($bossName, 'Mega') !== false) {
            if (stripos($bossName, ' X') !== false) {
                return [$bossName, 'Mega X'];
            }

            if (stripos($bossName, ' Y') !== false) {
                return [$bossName, 'Mega Y'];
            }

            return [$bossName, 'Mega'];
        }

        if (stripos($bossName, 'Alolan') !== false) {
            return [$bossName, 'Alola'];
        }

        if (stripos($bossName, 'Galarian') !== false) {
            return [$bossName, 'Galar'];
        }

        $matches = [];
        if (preg_match('~\((?<FormName>\w+)\)~', $bossName, $matches)) {
            return [str_replace($matches[0], '', $bossName), $matches['FormName']];
        }

        return [$bossName, null];
    }
}