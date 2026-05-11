<?php

namespace App\Service;

use App\Repository\PokemonRepository;

class PokemonManager
{
    public function __construct(private PokemonRepository $manager) {}

    public function handleStartGame(string $discordId): string
    {
        try {
            $result = $this->manager->deleteGame($discordId);
            if (!$result) {
                return "Impossible de supprimer l'ancienne partie.";
            }

            $pokemon = $this->manager->getRandomPokemon($discordId);

            $content = "🎮 **Nouveau Pendu lancé !**\n";
            $content .= "Pokémon de la génération " . $pokemon['generation'] . ".\n` ";
            $content .= $this->generateMask($pokemon['name'], "") . " \n";
            $content .= "`\nUtilise `/deviner [lettre]` !";

            return $content;
        } catch (\Throwable $e) {
            return ":warning: Erreur (Start): " . str_replace("pokémon", "Pokémon", strtolower($e->getMessage()));
        }
    }

    public function handleGuess(string $discordId, string $letter): string
    {
        try {
            $game = $this->manager->getGame($discordId);

            $letter = strtoupper(trim($letter));
            if (strlen($letter) !== 1) {
                return "Envoie une seule lettre !";
            }

            $currentLetters = strtoupper($game['letters'] ?? '');
            if (str_contains($currentLetters, $letter)) {
                return "Tu as déjà proposé la lettre **$letter** !";
            }

            $newLetters = $currentLetters . $letter;
            $this->manager->setLetters($game['id'], $newLetters);;

            $name = strtoupper($game['pokemon_name']);
            $mask = $this->generateMask($name, $newLetters);

            if (!str_contains($mask, '_')) {
                $this->manager->deleteGame($discordId);

                $url = "https://www.pokebip.com/pokedex-images/300/" . $game['pokedex'] . ".png?v=ev-blueberry";
                return "✨ GAGNÉ ! C'était bien **[$name]($url)**";
            }

            return "Lettre : **$letter**\nMot : ` $mask `\nLettres jouées : $newLetters";
        } catch (\Throwable $e) {
            return ":warning: Erreur (Guess): " . str_replace("pokémon", "Pokémon", strtolower($e->getMessage()));
        }
    }

    private function generateMask(string $name, string $letters): string
    {
        $name = strtoupper($name);
        $lettersArray = str_split(strtoupper($letters));
        $result = "";

        foreach (str_split($name) as $char) {
            if (in_array($char, $lettersArray) || $char === '-' || $char === ' ') {
                $result .= $char . " ";
            } else {
                $result .= "_ ";
            }
        }
        return trim($result);
    }
}
