<?php

namespace App\Repository;

class PokemonRepository extends DatabaseConnection
{
    public function __construct(?string $databaseName = null)
    {
        parent::__construct($databaseName);
    }

    public function deleteGame(string $discordId): bool
    {
        $res = $this->prepare("DELETE FROM hanging_game WHERE discord_id = :discordId", [
            'discordId' => $discordId
        ]);

        return !!$res;
    }

    public function getRandomPokemon(string $discordId, string $gens = ''): array
    {
        $res = $this->prepare("SELECT id, name, generation FROM pokemon WHERE :gens = '' OR generation IN (:gens) ORDER BY RAND() LIMIT 1", [
            'gens' => $gens
        ]);
        $pokemon = $res->fetch();

        if (!is_array($pokemon)) {
            throw new \Error("Aucun Pokémon trouvé dans la base.");
        }

        $sql = "INSERT INTO hanging_game (discord_id, pokemon_id, letters) VALUES (:discordId, :pokemonId, :letters)";
        $result = $this->prepare($sql, [
            'discordId' => $discordId, 
            'pokemonId' => $pokemon['id'], 
            'letters' => ""
        ]);

        if (!$result) {
            throw new \Error("Impossible de créer la partie.");
        }

        return $pokemon;
    }

    public function getGame(string $discordId): array
    {
        $sql = "SELECT g.*, p.name as pokemon_name, p.pokedex
            FROM hanging_game g 
            JOIN pokemon p ON g.pokemon_id = p.id 
            WHERE g.discord_id = :discordId";

        $result = $this->prepare($sql,  ['discordId' => $discordId]);
        if (!$result) {
            throw new \Error("Impossible de récupérer la partie.");
        }

        $game = $result->fetch();
        if (!is_array($game)) {
            throw new \Error("Impossible de récupérer la partie.");
        }

        return $game;
    }

    public function setLetters(int $gameId, string $letters): bool
    {
        $sql = "SELECT g.*, p.name as pokemon_name, p.pokedex
            FROM hanging_game g 
            JOIN pokemon p ON g.pokemon_id = p.id 
            WHERE g.discord_id = :discordId";

        $result = $this->prepare("UPDATE hanging_game SET letters = :letters WHERE id = :id",  [
            'id' => $gameId,
            'letters' => $letters
        ]);
        if (!$result) {
            throw new \Error("Impossible de modifier la partie.");
        }

        return true;
    }
}