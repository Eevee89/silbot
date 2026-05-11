<?php

namespace App\Controller;

use App\Repository\PokemonRepository;
use App\Service\PokemonManager;
use Discord\Interaction;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route(name: 'app_')]
class AppController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index() 
    {
        $discordApplicationId = $_ENV['DISCORD_APPLICATION_ID'] ?? '';
        return $this->redirect("https://discord.com/oauth2/authorize?client_id=$discordApplicationId");
    }

    #[Route('/help', name: 'help')]
    public function help(ParameterBagInterface $params): Response
    {
        $jsonPath = $params->get('kernel.project_dir') . '/config/discord/commands.json';
        $commands = json_decode(file_get_contents($jsonPath), true);

        return $this->render('bot-help.html.twig', [
            'commands' => $commands
        ]);
    }

    #[Route('/interactions', name: 'interactions', methods: ['POST'])]
    public function handle(
        Request $request,
        HttpClientInterface $httpClient,
    ): JsonResponse {
        $signature = $request->headers->get('X-Signature-Ed25519');
        $timestamp = $request->headers->get('X-Signature-Timestamp');
        $body = $request->getContent();
        $discordPublicKey = $_ENV['DISCORD_PUBLIC_KEY'] ?? '';

        if (empty($signature) || empty($timestamp) || empty($body)) {
            return new JsonResponse(['error' => 'Invalid signature'], 401);
        }

        if (!Interaction::verifyKey($body, $signature, $timestamp, $discordPublicKey)) {
            return new JsonResponse(['error' => 'Invalid signature'], 401);
        }

        $data = json_decode($body, true);
        if ($data['type'] === 1) {
            return new JsonResponse(['type' => 1]);
        }

        if ($data['type'] === 2) {
            $response = new JsonResponse(['type' => 5]);
            $response->send();
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            $token = $data['token'];
            $appId = $data['application_id'];
            $command = $data['data']['name'];
            $discordId = $data['member']['user']['id'] ?? $data['user']['id'];

            $content = ['content' => "Commande inconnue."];

            $repository = new PokemonRepository();
            $manager = new PokemonManager($repository);

            $content = match ($command) {
                'pendu' => $manager->handleStartGame($discordId),
                'deviner' => $manager->handleGuess($discordId, $data['data']['options'][0]['value'] ?? ''),
                default => "Commande inconnue."
            };

            $url = "https://discord.com/api/v10/webhooks/{$appId}/{$token}/messages/@original";
            $httpClient->request('PATCH', $url, ['json' => [
                'content' => $content
            ]]);
            exit;
        }

        return new JsonResponse();
    }
}