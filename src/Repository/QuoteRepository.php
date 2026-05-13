<?php

namespace App\Repository;

class QuoteRepository extends DatabaseConnection
{
    public function __construct(?string $databaseName = null)
    {
        parent::__construct($databaseName);
    }

    public function deleteQuote(string $discordId): bool
    {
        $res = $this->prepare("DELETE FROM quote_game WHERE discord_id = :discordId", [
            'discordId' => $discordId
        ]);

        return !!$res;
    }

    public function getRandomQuote(string $discordId, int $category = 0, int $type = 0): array
    {
        $res = $this->prepare("
            SELECT id, category, quote 
            FROM quote 
            WHERE (:category = 0 OR category = :category)
            AND (:type = 0 OR type = :type)
            ORDER BY RAND() LIMIT 1
        ", [
            'category' => $category,
            'type' => $type
        ]);
        $quote = $res->fetch();

        if (!is_array($quote)) {
            throw new \Error("Aucune citation trouvée dans la base.");
        }

        $sql = "INSERT INTO quote_game (discord_id, quote_id) VALUES (:discordId, :quoteId)";
        $result = $this->prepare($sql, [
            'discordId' => $discordId, 
            'quoteId' => $quote['id'], 
        ]);

        if (!$result) {
            throw new \Error("Impossible de créer la citation.");
        }

        return $quote;
    }

    public function getGame(string $discordId): array|false
    {
        $sql = "SELECT g.id, q.quote
            FROM quote_game g 
            JOIN quote q ON g.quote_id = q.id 
            WHERE g.discord_id = :discordId";

        $result = $this->prepare($sql,  ['discordId' => $discordId]);
        if (!$result) {
            throw new \Error("Impossible de récupérer la partie.");
        }

        return $result->fetch();
    }
}