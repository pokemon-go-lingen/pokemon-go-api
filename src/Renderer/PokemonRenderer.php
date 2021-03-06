<?php

declare(strict_types=1);

namespace PokemonGoApi\PogoAPI\Renderer;

use PokemonGoApi\PogoAPI\Collections\AttacksCollection;
use PokemonGoApi\PogoAPI\Collections\PokemonAssetsCollection;
use PokemonGoApi\PogoAPI\Collections\TranslationCollectionCollection;
use PokemonGoApi\PogoAPI\Types\Pokemon;
use PokemonGoApi\PogoAPI\Types\PokemonType;
use PokemonGoApi\PogoAPI\Util\GenerationDeterminer;

use function array_map;

final class PokemonRenderer
{
    private TranslationCollectionCollection $translations;

    private PokemonAssetsCollection $assetsCollection;

    public function __construct(
        TranslationCollectionCollection $translations,
        PokemonAssetsCollection $assetsCollection
    ) {
        $this->translations     = $translations;
        $this->assetsCollection = $assetsCollection;
    }

    /**
     * @return mixed[]
     */
    public function render(
        Pokemon $pokemon,
        AttacksCollection $attacksCollection,
        bool $basePokemon = true
    ): array {
        $names = PokemonNameRenderer::renderPokemonNames(
            $pokemon,
            $this->translations
        );

        $struct = [
            'id'                  => $pokemon->getId(),
            'formId'              => $pokemon->getFormId(),
            'dexNr'               => $pokemon->getDexNr(),
            'generation'          => GenerationDeterminer::fromDexNr($pokemon->getDexNr()),
            'names'               => $names,
            'stats'               => $pokemon->getStats(),
            'primaryType'         => $this->renderType($pokemon->getTypePrimary(), $this->translations),
            'secondaryType'       => $this->renderType($pokemon->getTypeSecondary(), $this->translations),
            'quickMoves'          => $this->renderAttacks(
                $pokemon->getQuickMoveNames(),
                $attacksCollection,
                $this->translations
            ),
            'cinematicMoves'      => $this->renderAttacks(
                $pokemon->getCinematicMoveNames(),
                $attacksCollection,
                $this->translations
            ),
            'eliteQuickMoves'     => $this->renderAttacks(
                $pokemon->getEliteQuickMoveNames(),
                $attacksCollection,
                $this->translations
            ),
            'eliteCinematicMoves' => $this->renderAttacks(
                $pokemon->getEliteCinematicMoveNames(),
                $attacksCollection,
                $this->translations
            ),
            'assets' => [
                'image'      => $pokemon->getPokemonImage()->buildUrl(false),
                'shinyImage' => $pokemon->getPokemonImage()->buildUrl(true),
            ],
            'regionForms'         => array_map(
                fn (Pokemon $pokemon): array => $this->render($pokemon, $attacksCollection, false),
                $pokemon->getPokemonRegionForms()
            ),
            'hasMegaEvolution'    => $pokemon->hasTemporaryEvolutions(),
            'megaEvolutions'      => $this->renderMegaEvolutions($pokemon, $this->translations),
        ];

        if ($basePokemon) {
            $struct['assetForms'] = $this->renderAssetsForms($pokemon);
        }

        return $struct;
    }

    /**
     * @return array<string, mixed>
     */
    private function renderMegaEvolutions(Pokemon $pokemon, TranslationCollectionCollection $translations): array
    {
        $output = [];
        foreach ($pokemon->getTemporaryEvolutions() as $temporaryEvolution) {
            $tmpNames = [];
            foreach ($translations->getCollections() as $translationCollection) {
                $tmpNames[$translationCollection->getLanguageName()] = PokemonNameRenderer::renderPokemonMegaName(
                    $pokemon,
                    $temporaryEvolution->getId(),
                    $translationCollection
                );
            }

            $output[$temporaryEvolution->getId()] = [
                'id'            => $temporaryEvolution->getId(),
                'names'         => $tmpNames,
                'stats'         => $temporaryEvolution->getStats(),
                'primaryType'   => $this->renderType($temporaryEvolution->getTypePrimary(), $translations),
                'secondaryType' => $this->renderType($temporaryEvolution->getTypeSecondary(), $translations),
                'assets'        => [
                    'image'      => $pokemon->getPokemonImage($temporaryEvolution)->buildUrl(false),
                    'shinyImage' => $pokemon->getPokemonImage($temporaryEvolution)->buildUrl(true),
                ],
            ];
        }

        return $output;
    }

    /**
     * @return array<string, string|array<string, string|null>>
     */
    private function renderType(PokemonType $type, TranslationCollectionCollection $translations): ?array
    {
        if ($type->getType() === PokemonType::NONE) {
            return null;
        }

        $names = [];
        foreach ($translations->getCollections() as $translationCollection) {
            $names[$translationCollection->getLanguageName()] = $translationCollection->getTypeName(
                $type->getGameMasterTypeName()
            );
        }

        return [
            'type'  => $type->getGameMasterTypeName(),
            'names' => $names,
        ];
    }

    /**
     * @param string[] $moves
     *
     * @return array<string, mixed>
     */
    private function renderAttacks(
        array $moves,
        AttacksCollection $attacksCollection,
        TranslationCollectionCollection $translations
    ): array {
        $out = [];
        foreach ($moves as $moveName) {
            $attack = $attacksCollection->getByName($moveName);
            if ($attack === null) {
                continue;
            }

            $names = [];
            foreach ($translations->getCollections() as $translationCollection) {
                $names[$translationCollection->getLanguageName()] = $translationCollection->getMoveName(
                    $attack->getId()
                );
            }

            $combatMove = $attack->getCombatMove();
            $combat     = null;
            if ($combatMove !== null) {
                $combat = [
                    'energy' => $combatMove->getEnergy(),
                    'power'  => $combatMove->getPower(),
                    'turns' => 1 + $combatMove->getDurationTurns(),
                    'buffs' => null,
                ];
                $buffs  = $combatMove->getBuffs();
                if ($buffs !== null) {
                    $combat['buffs'] = [
                        'activationChance' => $buffs->getActivationChance(),
                        'attackerAttackStatsChange' => $buffs->getAttackerAttackStatStageChange(),
                        'attackerDefenseStatsChange' => $buffs->getAttackerDefenseStatStageChange(),
                        'targetAttackStatsChange' => $buffs->getTargetAttackStatStageChange(),
                        'targetDefenseStatsChange' => $buffs->getTargetDefenseStatStageChange(),
                    ];
                }
            }

            $out[$moveName] = [
                'id'         => $moveName,
                'power'      => $attack->getPower(),
                'energy'     => $attack->getEnergy(),
                'durationMs' => $attack->getDurationMs(),
                'type'       => $this->renderType($attack->getPokemonType(), $translations),
                'names'      => $names,
                'combat'     => $combat,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function renderAssetsForms(Pokemon $pokemon): array
    {
        $out = [];
        foreach ($this->assetsCollection->getImages($pokemon->getDexNr()) as $assetForm) {
            foreach ($pokemon->getPokemonRegionForms() as $regionForm) {
                if (
                    $regionForm->getAssetBundleSuffix() === $assetForm->getAssetBundleSuffix()
                    && $regionForm->getAssetsBundleId() === $assetForm->getAssetBundleValue()
                ) {
                    continue 2;
                }
            }

            foreach ($pokemon->getTemporaryEvolutions() as $temporaryEvolution) {
                if ($temporaryEvolution->getAssetsBundleId() === $assetForm->getAssetBundleValue()) {
                    continue 2;
                }
            }

            $out[] = [
                'image' => $assetForm->buildUrl(false),
                'shinyImage' => $assetForm->buildUrl(true),
            ];
        }

        return $out;
    }
}
