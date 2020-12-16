<?php

declare(strict_types=1);

namespace PokemonGoLingen\PogoAPI\Collections;

use function array_key_exists;
use function sprintf;

final class TranslationCollection
{
    private string $languageName;
    /** @var array<string, string> */
    private array $pokemonNames = [];
    /** @var array<string, array<int, string>> */
    private array $pokemonMegaNames = [];
    /** @var array<string, string> */
    private array $typeNames = [];
    /** @var array<string, string> */
    private array $moveNames = [];
    /** @var array<string, string> */
    private array $pokemonFormNames = [];

    public function __construct(string $languageName)
    {
        $this->languageName = $languageName;
    }

    public function getLanguageName(): string
    {
        return $this->languageName;
    }

    public function addPokemonName(int $dexNr, string $translation): void
    {
        $dexNrKey = sprintf('%04d', $dexNr);
        $this->pokemonNames[$dexNrKey] = $translation;
    }

    public function addPokemonMegaName(int $dexNr, string $translation): void
    {
        $dexNrKey = sprintf('%04d', $dexNr);
        if (! array_key_exists($dexNrKey, $this->pokemonMegaNames)) {
            $this->pokemonMegaNames[$dexNrKey] = [];
        }
        $this->pokemonMegaNames[$dexNrKey][] = $translation;
    }

    public function addPokemonFormName(string $formName, string $translation): void
    {
        $this->pokemonFormNames[mb_strtoupper($formName)] = $translation;
    }

    public function addTypeName(string $type, string $translation): void
    {
        $this->typeNames[mb_strtoupper($type)] = $translation;
    }

    public function addMoveName(int $moveId, string $translation): void
    {
        $this->moveNames[sprintf('%04d', $moveId)] = $translation;
    }

    public function getTypeName(string $typeName): ?string
    {
        return $this->typeNames[mb_strtoupper($typeName)] ?? null;
    }

    public function getMoveName(int $moveId): ?string
    {
        return $this->moveNames[sprintf('%04d', $moveId)] ?? null;
    }

    public function getPokemonName(int $dexNr): ?string
    {
        $dexNrKey = sprintf('%04d', $dexNr);

        return $this->pokemonNames[$dexNrKey] ?? null;
    }

    /**
     * @return string[]
     */
    public function getPokemonMegaNames(int $dexNr): array
    {
        $dexNrKey = sprintf('%04d', $dexNr);

        return $this->pokemonMegaNames[$dexNrKey] ?? [];
    }

    public function getPokemonFormName(string $formName, string $fallbackName): ?string
    {
        return $this->pokemonFormNames[mb_strtoupper($formName)] ?? $this->pokemonFormNames[mb_strtoupper($fallbackName)] ?? null;
    }
}
