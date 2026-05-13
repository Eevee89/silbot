<?php

namespace App\Service;

use App\Repository\QuoteRepository;

class QuoteManager
{
    private QuoteRepository $repository;

    public function __construct()
    {
        $this->repository = new QuoteRepository();
    }

    public function handleStartGame(string $discordId, array $options): string
    {
        try {
            $category = $options['category'] ?? 0;
            $type = $options['type'] ?? 0;

            $result = $this->repository->deleteQuote($discordId);
            if (!$result) {
                return "Impossible de supprimer l'ancienne citation.";
            }

            $quote = $this->repository->getRandomQuote($discordId, $category, $type);

            $content = ":speech_balloon: **Nouvelle citation :**\n\n";
            $content .= "> " . $quote['quote'];

            return $content;
        } catch (\Throwable $e) {
            return ":warning: Erreur (Start): " . strtolower($e->getMessage());
        }
    }
}
