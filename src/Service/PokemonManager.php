<?php

namespace App\Service;

use App\Repository\PokemonRepository;

class PokemonManager
{
    private PokemonRepository $repository;

    public function __construct() 
    {
        $this->repository = new PokemonRepository();
    }

    public function handleStartGame(string $discordId, string $channelId, array $options): string
    {
        try {
            $gens = $options['generations'] ?? '';
            $multiplayer = $options['multiplayer'] ?? false;

            $game = $channelId ? $this->repository->getGame($channelId) : null;
            if (!empty($game)) {
                $currentLetters = strtoupper($game['letters'] ?? '');
                $mask = $this->generateMask(strtoupper($game['pokemon_name']), $currentLetters);

                $content = "Une partie multijoueur est en cours sur ce salon : \n\n";
                $content .= "Pokémon de la génération " . $game['generation'] . ".\n` ";
                $content .= $mask . " \n";
                $content .= "`\nUtilise `/try-letter [lettre]` !";

                return $content;
            }

            $result = $this->repository->deleteGame($discordId);
            if (!$result) {
                return "Impossible de supprimer l'ancienne partie.";
            }
            $result = $this->repository->deleteGame($channelId);
            if (!$result) {
                return "Impossible de supprimer l'ancienne partie.";
            }

            $id = $multiplayer ? $channelId : $discordId;

            $sanitizedGens = $gens ? $this->splitIntegers($gens) : [];
            $pokemon = $this->repository->getRandomPokemon($id, implode(',', $sanitizedGens));

            $content = "🎮 **Nouveau Pendu lancé !**\n";
            $content .= "Pokémon de la génération " . $pokemon['generation'] . ".\n` ";
            $content .= $this->generateMask($pokemon['name'], "") . " \n";
            $content .= "`\nUtilise `/try-letter [lettre]` !";

            return $content;
        } catch (\Throwable $e) {
            return ":warning: Erreur (Start): " . str_replace("pokémon", "Pokémon", strtolower($e->getMessage()));
        }
    }

    public function handleLetterGuess(string $discordId, string $channelId, array $options): string
    {
        try {
            $multiplayer = true;
            $game = $this->repository->getGame($channelId);
            if (empty($game)) {
                $multiplayer = false;
                $game = $this->repository->getGame($discordId);
            }

            if (empty($game)) {
                $content = "Tu n'as pas de partie en cours.\n";
                $content .= "\nUtilise `/game (generations) (multiplayer)` !";

                return $content;
            }

            $letter = $options['letter'] ?? '';

            $letter = strtoupper(trim($letter));
            if (strlen($letter) !== 1) {
                return "Envoie une seule lettre !";
            }

            $currentLetters = strtoupper($game['letters'] ?? '');
            if (str_contains($currentLetters, $letter)) {
                return "Tu as déjà proposé la lettre **$letter** !";
            }

            $newLetters = $currentLetters . $letter;
            $this->repository->setLetters($game['id'], $newLetters);;

            $name = strtoupper($game['pokemon_name']);
            $mask = $this->generateMask($name, $newLetters);

            if (!str_contains($mask, '_')) {
                $this->repository->deleteGame($multiplayer ? $channelId : $discordId);

                $pokedexUrl = "https://www.pokebip.com/pokedex/pokemon/" . strtolower($game['pokemon_name']);
                $imgUrl = "https://www.pokebip.com/pokedex-images/300/" . $game['pokedex'] . ".png?v=ev-blueberry";
                return "✨ GAGNÉ ! C'était bien **[$name]($pokedexUrl)**\n[_]($imgUrl)";
            }

            return "Lettre : **$letter**\nMot : ` $mask `\nLettres jouées : $newLetters";
        } catch (\Throwable $e) {
            return ":warning: Erreur (Guess): " . str_replace("pokémon", "Pokémon", strtolower($e->getMessage()));
        }
    }

    public function handleNameGuess(string $discordId, string $channelId, array $options): string
    {
        try {
            $multiplayer = true;
            $game = $this->repository->getGame($channelId);
            if (empty($game)) {
                $multiplayer = false;
                $game = $this->repository->getGame($discordId);
            }

            if (empty($game)) {
                $content = "Tu n'as pas de partie en cours.\n";
                $content .= "\nUtilise `/game (generations) (multiplayer)` !";

                return $content;
            }
            $name = $options['name'] ?? '';

            $name = strtoupper(trim($name));
            if (empty($name)) {
                return "Envoie un nom !";
            }

            if (strtoupper($game['pokemon_name']) === $name) {
                $this->repository->deleteGame($multiplayer ? $channelId : $discordId);

                $pokedexUrl = "https://www.pokebip.com/pokedex/pokemon/" . strtolower($game['pokemon_name']);
                $imgUrl = "https://www.pokebip.com/pokedex-images/300/" . $game['pokedex'] . ".png?v=ev-blueberry";
                return "✨ GAGNÉ ! C'était bien **[$name]($pokedexUrl)**\n[_]($imgUrl)";
            }

            $currentLetters = strtoupper($game['letters'] ?? '');
            $mask = $this->generateMask(strtoupper($game['pokemon_name']), $currentLetters);
            return "Nom : **$name**\nMot : ` $mask `\nLettres jouées : $currentLetters";
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

    function splitIntegers(string $input): array
    {
        $trimmedInput = trim($input);

        // Regex expliquée :
        // ^\d+           : Commence par un ou plusieurs chiffres
        // (              : Groupe répétable
        //   \s*,\s* : Une virgule entourée d'espaces optionnels (\s*)
        //   \d+          : Suivi d'un nombre
        // )* : Le groupe peut se répéter 0 ou plusieurs fois
        if (!preg_match('/^\d+(\s*,\s*\d+)*$/', $trimmedInput)) {
            throw new \Error("Format invalide : seuls les entiers séparés par des virgules sont autorisés.");
        }

        $parts = explode(',', $trimmedInput);
        return array_map(function ($value) {
            return (int) trim($value);
        }, $parts);
    }
}
